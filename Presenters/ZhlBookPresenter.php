<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Schedule/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Attributes/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'Presenters/Reservation/ReservationPresenterFactory.php');
require_once(ROOT_DIR . 'Presenters/ZhlReservationFacade.php');
require_once(ROOT_DIR . 'Presenters/ZhlHauspostEmail.php');
require_once(ROOT_DIR . 'Presenters/ZhlMediaInfoEmail.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlTypeInfo.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlSettings.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlDauerAusnahme.php');
require_once(ROOT_DIR . 'Web/zhl-audit-lib.php');

/**
 * ZHL-Buchungs-Schritt (v-book). Eine ZHL-gestylte Seite zwischen Geräte-Wahl und Buchung:
 * zeigt Gerät + Zeitraum (mit Vorlauf) + Auswahl Abholung/Einführung und legt beim Absenden eine
 * ECHTE native Reservierung an — über ZhlReservationFacade → ReservationPresenterFactory →
 * nativer ReservationHandler (volle Validierung bleibt letzte Instanz). v-book-3 verdrahtet die
 * Abholung/Einführung zusätzlich mit dem Übergabe-Modul (zhl_booking_handover/terminplaner).
 */
class ZhlBookPresenter
{
    private const WD = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

    private $page;

    /** @var array<int,int[]> memoisierter Geräte-Pool je angeklickter resource_id (pro Request). */
    private $poolCache = [];

    public function __construct($page)
    {
        $this->page = $page;
    }

    /** GET: Buchungsformular anzeigen (oder Erfolgs-Panel nach Redirect). */
    public function PageLoad(UserSession $user)
    {
        $booked = isset($_GET['booked']) ? trim((string)$_GET['booked']) : '';
        if ($booked !== '') {
            $this->page->BindSuccess([
                'referenceNumber' => $booked,
                'warning' => isset($_GET['warn']) ? trim((string)$_GET['warn']) : '',
                'mediaInfos' => ZhlTypeInfo::ForReference(ServiceLocator::GetDatabase(), $booked),
            ]);
            return;
        }

        $tz = $user->Timezone;
        $rid = $this->readInt(QueryStringKeys::RESOURCE_ID, 0);
        $dateStr = $this->readDate(QueryStringKeys::RESERVATION_DATE, $tz);

        $resource = $this->loadResource($user, $rid);
        if ($resource === null) {
            $this->page->RedirectToDashboard();
            return;
        }
        $this->bindForm($user, $resource, $dateStr, '09:00', $dateStr, '17:00', 'pickup', []);
    }

    /**
     * AJAX: Abhol- + Einführungstermine zum gewählten Ausleihstart als JSON. Vom Kalender-/Slot-JS
     * aufgerufen, sobald ein Start gewählt ist → Termine werden gegen DAS Datum (nicht das Default-Datum)
     * gefiltert, ohne Page-Reload (Titel/Auswahl/Häkchen bleiben erhalten).
     */
    public function AjaxSlots(UserSession $user)
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $tz = $user->Timezone;
        $db = ServiceLocator::GetDatabase();
        $rid = $this->readInt(QueryStringKeys::RESOURCE_ID, 0);
        $resource = $this->loadResource($user, $rid);
        if ($resource === null) {
            echo json_encode(['error' => 'resource']);
            return;
        }
        $start = isset($_GET['start']) ? trim((string)$_GET['start']) : '';
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $start, $dm) || !checkdate((int)$dm[2], (int)$dm[3], (int)$dm[1])) {
            echo json_encode(['error' => 'date']);
            return;
        }
        $time = isset($_GET['time']) ? trim((string)$_GET['time']) : '';
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $time = '09:00'; // Tagesmodus: Start-des-Tages-Proxy wie der initiale GET-Render
        }
        // End-Tag optional (Rückgabe-Floor). Fehlt/ungültig → auf Start zurückfallen (Abwärtskompatibilität).
        $end = isset($_GET['end']) ? trim((string)$_GET['end']) : '';
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $end, $em) || !checkdate((int)$em[2], (int)$em[3], (int)$em[1]) || strcmp($end, $start) < 0) {
            $end = $start;
        }
        $ueb = $this->lookupUebergabe($db, $rid);
        $loanStartUtc = Date::Parse($start . ' ' . $time, $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');

        $einf = null;
        if ($ueb['einfuehrung'] !== 'keine') {
            // Admin-Gruppe (Nutzer-Vorgabe 2026-07-15): keine Einführung nötig, unabhängig vom Zertifikatsstatus.
            if ($user->IsAdmin || $this->userIsCertified($db, $user->UserId, $rid)) {
                $einf = ['certified' => true];
            } else {
                $f = $this->fetchEinfuehrungSlots($ueb['einfuehrung_typ'], $ueb['tp_member_id'], $loanStartUtc, $tz);
                // Slot-Modus (Studio): die Einführung reserviert das Gerät selbst → nur Termine
                // anbieten, an denen das Studio in dieser Stunde frei ist (SPEC-STUDIO-EINFUEHRUNG).
                if ($ueb['booking_mode'] === 'slot') {
                    $f['slots'] = $this->filterSlotsResourceFree($user, $resource, $f['slots']);
                }
                $einf = ['certified' => false, 'required' => ($ueb['einfuehrung'] === 'notwendig'), 'slots' => $f['slots'], 'days' => $this->groupPickupByDay($f['slots'], $tz), 'earliestLabel' => $f['earliestLabel']];
            }
        }
        $pickup = null;
        if ($this->pickupApplies($ueb)) {
            $f = $this->fetchHandoverSlots($this->handoverTypeLabels(), $ueb['tp_member_id'], $loanStartUtc, $tz);
            // Task B: Abhol-Slots gegen die Geräte-Verfügbarkeit über [Abholtag … Nutzungsende] filtern.
            // Die Reservierung beginnt am Abholtag → wählt der Nutzer einen frühen Abhol-Slot, an dessen
            // Tag das Gerät (bzw. der ganze Typ-Pool) bis Nutzungsende NICHT durchgehend frei ist, würde
            // der native Save ihn ablehnen. Im Tagesmodus filtern (Slot-Modus: Stunden-Logik, kein Vorlauf-
            // Abholtag-Fenster). Pool-bewusst über poolResourceIds (wie der Commit-Pfad pickFreeUnit).
            if ($ueb['booking_mode'] !== 'slot') {
                $f['slots'] = $this->filterPickupSlotsByResourceFree($user, $resource, $f['slots'], $end, $tz);
            }
            $mandatory = !$user->IsAdmin;
            $pickup = ['mandatory' => $mandatory, 'days' => $this->groupPickupByDay($f['slots'], $tz), 'earliestLabel' => $f['earliestLabel']];
        }
        // Rückgabe-Slots (SPEC-RUECKGABE): selber Typ wie Abholung, Floor = max(Endtag-Anfang, Start-Anker).
        $return = null;
        if ($this->returnApplies($ueb)) {
            $floorUtc = $this->returnFloorUtc($start, $time, $end, $tz);
            $f = $this->fetchReturnSlots($this->handoverTypeLabels(), $ueb['tp_member_id'], $floorUtc, $tz);
            $mandatory = !$user->IsAdmin;
            $return = ['mandatory' => $mandatory, 'days' => $this->groupPickupByDay($f['slots'], $tz), 'earliestLabel' => $f['earliestLabel']];
        }
        echo json_encode(['pickup' => $pickup, 'einf' => $einf, 'return' => $return], JSON_UNESCAPED_UNICODE);
    }

    /** POST: echte Reservierung anlegen. */
    public function HandlePost(UserSession $user)
    {
        $tz = $user->Timezone;
        $rid = (int)$this->post('resourceId');
        $resource = $this->loadResource($user, $rid);
        if ($resource === null) {
            $this->page->RedirectToDashboard();
            return;
        }

        $choice = $this->post('handoverChoice') === 'training' ? 'training' : 'pickup';
        $projectTitle = trim((string)$this->post('projectTitle'));
        $db = ServiceLocator::GetDatabase();
        $ueb = $this->lookupUebergabe($db, $rid);

        // Fulfillment-Wahl (C2): 'pickup' (Default, persönliche Abholung/Ablageort) oder 'hauspost' (Versand).
        // Hauspost ist ein EIGENSTÄNDIGES Geräte-Flag (Admin-Matrix) und gilt unabhängig vom Übergabe-Modus
        // — der Haken allein entscheidet (ZHL-Entscheidung 2026-06-26).
        $hauspostAllowed = !empty($ueb['hauspost_allowed']);
        $fulfillment = ($this->post('fulfillment') === 'hauspost' && $hauspostAllowed) ? 'hauspost' : 'pickup';
        // Hauspost-Formularwerte einsammeln (für Re-Render + ggf. Persistenz/Mail).
        $hpValues = [
            'einsatz_termin' => trim((string)$this->post('einsatz_termin')),
            'einsatz_raum' => trim((string)$this->post('einsatz_raum')),
            'einsatz_titel' => trim((string)$this->post('einsatz_titel')),
            'liefer_fenster' => trim((string)$this->post('liefer_fenster')),
            'rueckhol_fenster' => trim((string)$this->post('rueckhol_fenster')),
            'anlieferort' => trim((string)$this->post('anlieferort')),
            'abholort_hauspost' => trim((string)$this->post('abholort_hauspost')),
        ];

        // Für die Ausleihdauer-Prüfung: rohe Nutzungs-Tage (Tagesmodus). NULL = Stundenmodus → ceil(h/24).
        $usageDayStart = null;
        $usageDayEnd = null;

        if ($ueb['booking_mode'] === 'slot') {
            $beginDate = $this->postDate('slotDay', $tz);
            $endDate = $beginDate;
            $sb = $this->post('slotBegin');
            $se = $this->post('slotEnd');
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $sb) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $se) || strcmp($se, $sb) <= 0) {
                $this->bindForm($user, $resource, $beginDate, '09:00', $endDate, '11:00', $choice, ['Bitte wähle im Wochen-Raster eine freie Zeitspanne (Start- und End-Feld anklicken).'], [], $projectTitle, $this->post('pickup_slot'), $fulfillment, $hpValues);
                return;
            }
            $beginTime = $sb;
            $endTime = $se;
        } else {
            // Tagesmodus: Start- und End-Tag aus dem Monats-Kalender (Klick-Spanne über ganze Tage).
            $dayStartRaw = $this->post('dayStart');
            $dayEndRaw = $this->post('dayEnd');
            $validStart = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayStartRaw) && checkdate((int)substr($dayStartRaw, 5, 2), (int)substr($dayStartRaw, 8, 2), (int)substr($dayStartRaw, 0, 4));
            $validEnd = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayEndRaw) && checkdate((int)substr($dayEndRaw, 5, 2), (int)substr($dayEndRaw, 8, 2), (int)substr($dayEndRaw, 0, 4));
            if (!$validStart || !$validEnd || strcmp($dayEndRaw, $dayStartRaw) < 0) {
                $attrDefsErr = $this->applicableReservationAttributes($rid, (bool)$user->IsAdmin);
                $attrValuesErr = [];
                foreach ($attrDefsErr as $a) {
                    $attrValuesErr[(int)$a->Id()] = isset($_POST['attr_' . $a->Id()]) ? trim((string)$_POST['attr_' . $a->Id()]) : '';
                }
                $this->bindForm($user, $resource, $this->validDate($dayStartRaw, $tz), '09:00', $this->validDate($dayStartRaw, $tz), '17:00', $choice, ['Bitte wähle im Kalender einen Start- und End-Tag.'], $attrValuesErr, $projectTitle, $this->post('pickup_slot'), $fulfillment, $hpValues);
                return;
            }
            $beginDate = $dayStartRaw;
            $dayEnd = $dayEndRaw;
            // Rohe Nutzungs-Tage für die Dauer-Prüfung (vor der „endNextDay"-Mitternachts-Korrektur).
            $usageDayStart = $dayStartRaw;
            $usageDayEnd = $dayEndRaw;
            // Zeiten an die buchbaren Schedule-Grenzen ausrichten (sonst lehnt SchedulePeriodRule ab).
            // Start-Grenze aus dayStart, End-Grenze aus dayEnd (Wochentage können andere Perioden haben).
            $startBounds = $this->scheduleDayBounds($user, $resource, $beginDate);
            $endBounds = $this->scheduleDayBounds($user, $resource, $dayEnd);
            $beginTime = $startBounds['begin'];
            $endTime = $endBounds['end'];
            // Ausleihe deckt die ganzen Tage dayStart..dayEnd ab. Endet die letzte Periode um
            // Mitternacht (Ganztags-Layout), liegt das Ende am Folgetag von dayEnd.
            $endOffset = $endBounds['endNextDay'] ? 1 : 0;
            $endDate = Date::Parse($dayEnd . ' 00:00:00', $tz)->AddDays($endOffset)->Format('Y-m-d');
        }

        $type = $this->lookupType($db, $rid);

        // Reservierungs-Custom-Attribute (z. B. Pflichtfeld „Haftpflichtversicherung") einsammeln.
        $attrDefs = $this->applicableReservationAttributes($rid, (bool)$user->IsAdmin);
        $attrValues = [];      // id => eingegebener Wert (für Re-Render)
        $facadeAttrs = [];     // ->Id/->Value für die Facade
        foreach ($attrDefs as $a) {
            $val = isset($_POST['attr_' . $a->Id()]) ? trim((string)$_POST['attr_' . $a->Id()]) : '';
            $attrValues[(int)$a->Id()] = $val;
            $o = new stdClass();
            $o->Id = (int)$a->Id();
            $o->Value = $val;
            $facadeAttrs[] = $o;
        }

        // Projekttitel ist Pflicht (US-B). Titel der Reservierung = Projekttitel; Gerät/Übergabe in die Beschreibung.
        if ($projectTitle === '') {
            $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Bitte gib einen Titel für dein Projekt an.'], $attrValues, '', $this->post('pickup_slot'), $fulfillment, $hpValues);
            return;
        }
        $titel = $projectTitle;
        $desc = $this->uebergabeNote($ueb) . ' · Gerät: ' . ($type !== null ? $type : $resource->GetName());
        if ($fulfillment === 'hauspost') {
            $desc .= ' · Versand per Hauspost (Antrag wird vom Medienmanager geprüft)';
        }

        // --- Einführungs-Gate (US-16/US-20): Zertifikat ODER Terminplaner-Slot, je nach Gerät ---
        // Admin-Gruppe (Nutzer-Vorgabe 2026-07-15): keine Einführung nötig, unabhängig vom Zertifikatsstatus.
        $certified = $ueb['einfuehrung'] === 'keine' ? true : ($user->IsAdmin || $this->userIsCertified($db, $user->UserId, $rid));
        $einfRequired = ($ueb['einfuehrung'] === 'notwendig') && !$certified;
        $chosenSlot = $this->post('einf_slot');

        // „Zusammen" (Nutzer-Wahl 2026-06-26): EIN Termin deckt Einführung UND Abholung ab. Nur möglich,
        // wenn eine Einführung ansteht (Gerät verlangt sie, Nutzer nicht zertifiziert) UND eine persönliche
        // Abholung greift UND kein Hauspost. Dann ist der gewählte Einführungs-Slot zugleich der Übergabe-
        // termin — es wird KEIN zweiter Terminplaner-Slot gebucht.
        $handoverMode = ($this->post('handover_mode') === 'zusammen') ? 'zusammen' : 'getrennt';
        $combined = ($handoverMode === 'zusammen') && ($fulfillment !== 'hauspost')
            && $this->pickupApplies($ueb) && ($ueb['einfuehrung'] !== 'keine') && !$certified;

        // Abhol-Slot (C1) — für Re-Render und ggf. spätere Buchung. type_id/member_id werden NICHT
        // mehr aus dem POST gelesen (Hidden-Inputs entfernt) → serverseitig neu aufgelöst (Phase A).
        $pickupSlot = $this->post('pickup_slot');
        // Rückgabe-Slot (SPEC-RUECKGABE) — analog: nur slot_id aus POST, Rest serverseitig (Phase A).
        $returnSlot = $this->post('return_slot');

        // Im „Zusammen"-Modus IST der Einführungs-Slot der gemeinsame Termin → Pflicht (auch wenn die
        // Einführung sonst nur „möglich" wäre).
        if (($einfRequired || $combined) && $chosenSlot === '') {
            $msg = $combined
                ? 'Für „Einführung und Abholung zusammen" bitte einen gemeinsamen Termin wählen (oder es ist aktuell keiner vor deinem Ausleihstart frei).'
                : 'Für dieses Gerät ist eine Einführung nötig — bitte wähle zuerst einen Einführungstermin (oder es ist aktuell keiner vor deinem Ausleihstart frei).';
            $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, [$msg], $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
            return;
        }

        // Pflicht-Abholung VOR externen Buchungen prüfen (Codex): sonst würde eine Einführung gebucht,
        // obwohl die Buchung mangels Pflicht-Abholtermin ohnehin scheitert.
        // Bei Hauspost (C2) entfällt die persönliche Abholung komplett — kein Abholtermin nötig.
        // Im „Zusammen"-Modus entfällt der separate Abholtermin (er steckt im Einführungs-Slot).
        $pickupMandatory = ($fulfillment !== 'hauspost') && $this->pickupApplies($ueb) && !$user->IsAdmin && !$combined;
        if ($pickupMandatory && $pickupSlot === '') {
            $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Für dieses Gerät ist eine persönliche Abholung Pflicht — bitte einen Abholtermin wählen.'], $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
            return;
        }

        // Pflicht-RÜCKGABE (SPEC-RUECKGABE) — isoliert von Abholung/Hauspost/combined gehalten (AK-9):
        // bei Hauspost entfällt der Rückgabetermin (Rückversand per Post). Der „kein Slot frei"-Fall
        // ist bewusst NICHT hier verwoben — er greift erst über die leere Slot-Liste (Picker zeigt nichts),
        // sodass das parallele „Termin anfragen"-Feature dort andocken kann.
        $returnMandatory = ($fulfillment !== 'hauspost') && $this->returnApplies($ueb) && !$user->IsAdmin;
        if ($returnMandatory && $returnSlot === '') {
            $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Für dieses Gerät ist eine persönliche Rückgabe Pflicht — bitte einen Rückgabetermin wählen.'], $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
            return;
        }

        // --- Hauspost-Zweig (C2): Zusatzformular validieren + Vorlauf prüfen ---
        // Eigener Fulfillment-Pfad (Codex): KEIN Terminplaner-Abholtermin. Die native Reservierung
        // wird wie üblich angelegt; der Hauspost-Antrag + die Mail erst NACH dem Speichern.
        $hauspostActive = ($fulfillment === 'hauspost');
        if ($hauspostActive) {
            $required = [
                'einsatz_termin' => 'Termin des Einsatzes',
                'einsatz_raum' => 'Raum des Einsatzorts',
                'einsatz_titel' => 'Titel/Name des Einsatzes',
                'liefer_fenster' => 'Anlieferungs-Zeitfenster',
                'rueckhol_fenster' => 'Rückhol-Zeitfenster',
                'anlieferort' => 'Anlieferungsort',
                'abholort_hauspost' => 'Abholungsort',
            ];
            $missing = [];
            foreach ($required as $k => $label) {
                if ($hpValues[$k] === '') {
                    $missing[] = $label;
                }
            }
            if (!empty($missing)) {
                $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Für den Hauspost-Versand bitte alle Felder ausfüllen — es fehlt: ' . implode(', ', $missing) . '.'], $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
                return;
            }
            // Vorlauf-Regel: mind. lead_days Werktage zwischen heute und Einsatz. Best-effort, weil
            // einsatz_termin Freitext (ggf. Zeitraum) ist: parst sich ein Datum heraus, prüfen wir es;
            // sonst weiche Prüfung (kein Block) — Limitation dokumentiert.
            $leadDays = (int)($this->hauspostConfig()['lead_days'] ?? 3);
            $einsatzYmd = $this->parseLooseDate($hpValues['einsatz_termin'], $tz);
            if ($einsatzYmd !== null && $leadDays > 0) {
                $earliest = $this->addWorkingDays(Date::Now()->ToTimezone($tz)->Format('Y-m-d'), $leadDays, $tz);
                if ($einsatzYmd < $earliest) {
                    $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Bitte mindestens ' . $leadDays . ' Werktage Vorlauf, damit der Medienmanager das Material bereitstellen kann (frühester Einsatz: ' . Date::Parse($earliest . ' 00:00:00', $tz)->Format('d.m.Y') . ').'], $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
                    return;
                }
            }
        }

        // --- Phase A: Einführung serverseitig auflösen (NICHT buchen — erst nach erfolgreichem Save). ---
        $einfPlan = null;
        if ($chosenSlot !== '' && !$certified && $ueb['einfuehrung'] !== 'keine') {
            $einfLoanStartUtc = Date::Parse($beginDate . ' ' . $beginTime, $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
            $ef = $this->fetchEinfuehrungSlots($ueb['einfuehrung_typ'], $ueb['tp_member_id'], $einfLoanStartUtc, $tz);
            // Slot-Modus (Studio): nur Termine mit freiem Studio gelten (SPEC-STUDIO-EINFUEHRUNG) —
            // wurde das Studio seit der Anzeige belegt, fällt der Slot hier raus → klarer Fehler vor Save.
            if ($ueb['booking_mode'] === 'slot') {
                $ef['slots'] = $this->filterSlotsResourceFree($user, $resource, $ef['slots']);
            }
            $einfResolved = null;
            foreach ($ef['slots'] as $s) {
                if ((string)$s['slot_id'] === (string)$chosenSlot) {
                    $einfResolved = $s;
                    break;
                }
            }
            if ($einfResolved === null) {
                $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Der gewählte Einführungstermin ist nicht mehr verfügbar — bitte neu wählen.'], $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
                return;
            }
            $einfMemberId = (int)($einfResolved['member_id'] ?? $ef['memberId'] ?? 0);
            $einfTypeId = (int)($einfResolved['type_id'] ?? $ef['typeId'] ?? 0);
            if ($einfMemberId <= 0 || $einfTypeId <= 0) {
                $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Der Einführungstermin konnte nicht aufgelöst werden. Bitte beim ZHL-Team melden.'], $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
                return;
            }
            $einfStartUtc = !empty($einfResolved['start_utc']) ? Date::Parse($einfResolved['start_utc'], 'UTC')->Format('Y-m-d H:i:s') : null;
            $einfEndUtc = !empty($einfResolved['end_utc']) ? Date::Parse($einfResolved['end_utc'], 'UTC')->Format('Y-m-d H:i:s') : $einfStartUtc;
            $einfPlan = ['slot_id' => $chosenSlot, 'member_id' => $einfMemberId, 'type_id' => $einfTypeId, 'start_utc' => $einfStartUtc, 'end_utc' => $einfEndUtc, 'resourceId' => $rid];
        }

        // --- Phase A: Abholung serverseitig auflösen (NICHT buchen). Bei Hauspost UND im „Zusammen"-Modus
        //     entfällt der separate Abholtermin (im Zusammen-Modus steckt er im Einführungs-Slot). ---
        $pickupPlan = null;
        if (!$hauspostActive && !$combined && $this->pickupApplies($ueb) && $pickupSlot !== '') {
            $loanStartUtc = Date::Parse($beginDate . ' ' . $beginTime, $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
            $typeLabels = $this->handoverTypeLabels();
            $f = $this->fetchHandoverSlots($typeLabels, $ueb['tp_member_id'], $loanStartUtc, $tz);
            $resolved = null;
            foreach ($f['slots'] as $s) {
                if ((string)$s['slot_id'] === (string)$pickupSlot) {
                    $resolved = $s;
                    break;
                }
            }
            if ($resolved === null) {
                $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Der gewählte Abholtermin ist nicht mehr verfügbar — bitte neu wählen.'], $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
                return;
            }
            $pickupMemberId = (int)($resolved['member_id'] ?? $f['memberId'] ?? 0);
            $pickupTypeId = (int)($resolved['type_id'] ?? $f['typeId'] ?? 0);
            if ($pickupMemberId <= 0 || $pickupTypeId <= 0) {
                Log::Error('ZHL-Abholung: ungültige Terminplaner-IDs (member=%s, type=%s) für res=%s', $pickupMemberId, $pickupTypeId, $rid);
                $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Der Abholtermin konnte nicht aufgelöst werden (Terminplaner-Konfiguration). Bitte beim ZHL-Team melden.'], $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
                return;
            }
            $pickupStartUtc = Date::Parse($resolved['start_utc'], 'UTC')->Format('Y-m-d H:i:s');
            $pickupEndUtc = $resolved['end_utc'] !== null ? Date::Parse($resolved['end_utc'], 'UTC')->Format('Y-m-d H:i:s') : $pickupStartUtc;
            $pickupPlan = ['slot_id' => $pickupSlot, 'member_id' => $pickupMemberId, 'type_id' => $pickupTypeId, 'start_utc' => $pickupStartUtc, 'end_utc' => $pickupEndUtc];
        }

        // --- Phase A: Rückgabe serverseitig auflösen (NICHT buchen). Bei Hauspost entfällt sie. ---
        $returnPlan = null;
        if (!$hauspostActive && $this->returnApplies($ueb) && $returnSlot !== '') {
            $floorUtc = $this->returnFloorUtc($beginDate, $beginTime, $endDate, $tz);
            $typeLabels = $this->handoverTypeLabels();
            $rf = $this->fetchReturnSlots($typeLabels, $ueb['tp_member_id'], $floorUtc, $tz);
            $rResolved = null;
            foreach ($rf['slots'] as $s) {
                if ((string)$s['slot_id'] === (string)$returnSlot) {
                    $rResolved = $s;
                    break;
                }
            }
            if ($rResolved === null) {
                $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Der gewählte Rückgabetermin ist nicht mehr verfügbar — bitte neu wählen.'], $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
                return;
            }
            $returnMemberId = (int)($rResolved['member_id'] ?? $rf['memberId'] ?? 0);
            $returnTypeId = (int)($rResolved['type_id'] ?? $rf['typeId'] ?? 0);
            if ($returnMemberId <= 0 || $returnTypeId <= 0) {
                Log::Error('ZHL-Rückgabe: ungültige Terminplaner-IDs (member=%s, type=%s) für res=%s', $returnMemberId, $returnTypeId, $rid);
                $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Der Rückgabetermin konnte nicht aufgelöst werden (Terminplaner-Konfiguration). Bitte beim ZHL-Team melden.'], $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
                return;
            }
            $returnStartUtc = Date::Parse($rResolved['start_utc'], 'UTC')->Format('Y-m-d H:i:s');
            $returnEndUtc = $rResolved['end_utc'] !== null ? Date::Parse($rResolved['end_utc'], 'UTC')->Format('Y-m-d H:i:s') : $returnStartUtc;
            $returnPlan = ['slot_id' => $returnSlot, 'member_id' => $returnMemberId, 'type_id' => $returnTypeId, 'start_utc' => $returnStartUtc, 'end_utc' => $returnEndUtc];
        }

        // --- Reservierungsbeginn = ABHOLTAG: ab der Übergabe ist das Gerät physisch weg. Liegt der Abholtag
        //     VOR dem Nutzungsbeginn, muss die Reservierung schon ab dem Abholtag blockieren — sonst wäre die
        //     Lücke Abholung→Nutzung „frei", obwohl das Gerät ausgeliehen ist. Die native Verfügbarkeitsprüfung
        //     lehnt dann Überschneidungen (z. B. ein anderer Verleih dazwischen) korrekt ab. ---
        $reservBeginDate = $beginDate;
        $reservBeginTime = $beginTime;
        if ($pickupPlan !== null) {
            $pickupDay = Date::Parse($pickupPlan['start_utc'], 'UTC')->ToTimezone($tz)->Format('Y-m-d');
            if (strcmp($pickupDay, $beginDate) < 0) {
                $reservBeginDate = $pickupDay;
                $pbnds = $this->scheduleDayBounds($user, $resource, $pickupDay);
                $reservBeginTime = $pbnds['begin'];
                $desc .= ' · ab Abholtag ' . $pickupDay . ' reserviert (Nutzung ab ' . $beginDate . ')';
            }
        }
        // „Zusammen": der gemeinsame Termin (= Einführungs-Slot) ist auch der Übergabetag → Reservierung
        // ebenfalls ab diesem Tag blockieren, wenn er vor dem Nutzungsbeginn liegt.
        if ($combined && $pickupPlan === null && $einfPlan !== null && !empty($einfPlan['start_utc'])) {
            $einfDay = Date::Parse($einfPlan['start_utc'], 'UTC')->ToTimezone($tz)->Format('Y-m-d');
            if (strcmp($einfDay, $beginDate) < 0) {
                $reservBeginDate = $einfDay;
                $ebnds = $this->scheduleDayBounds($user, $resource, $einfDay);
                $reservBeginTime = $ebnds['begin'];
                $desc .= ' · ab gemeinsamem Termin ' . $einfDay . ' reserviert (Nutzung ab ' . $beginDate . ')';
            }
        }

        // --- Reservierungs-ENDE = RÜCKGABETAG (SPEC-RUECKGABE): Liegt der Rückgabetag NACH dem
        //     Nutzungsende, muss die Reservierung bis dahin blockieren — sonst wäre die Lücke
        //     Nutzungsende→Rückgabe „frei", obwohl das Gerät noch beim Ausleihenden ist. Spiegel der
        //     Beginn-Verlängerung. $reservEndDate/$reservEndTime sind das *reservierte* Fenster-Ende;
        //     $endDate/$endTime bleiben die fachliche Nutzungszeit. ---
        $reservEndDate = $endDate;
        $reservEndTime = $endTime;
        if ($returnPlan !== null) {
            $returnDay = Date::Parse($returnPlan['start_utc'], 'UTC')->ToTimezone($tz)->Format('Y-m-d');
            if (strcmp($returnDay, $endDate) > 0) {
                $rbnds = $this->scheduleDayBounds($user, $resource, $returnDay);
                $reservEndTime = $rbnds['end'];
                // Endet die Periode um Mitternacht (Ganztags-Layout), liegt das reservierte Ende am Folgetag.
                $reservEndDate = $rbnds['endNextDay'] ? Date::Parse($returnDay . ' 00:00:00', $tz)->AddDays(1)->Format('Y-m-d') : $returnDay;
                $desc .= ' · bis Rückgabetag ' . $returnDay . ' reserviert (Nutzung bis ' . $endDate . ')';
            }
        }

        // Ausleihdauer-Limit bindet an die URSPRÜNGLICH angeklickte Einheit (vor der Pool-Zuteilung).
        $origRid = $rid;

        // --- Pool-Zuteilung (SPEC-POOL-FALLBACK): die angeklickte Einheit kann im gewählten Fenster
        //     belegt sein, während eine gleichtypige Einheit frei ist. Wir reservieren die erste freie
        //     Einheit (Präferenz: die angeklickte). Keine frei → klare Meldung statt nativem Konflikt.
        //     Gleicher Typ ⇒ gleiche Übergabe-/Einführungs-/Zertifikatsregeln; nur der physisch
        //     reservierte resource_id (und damit die einf/Übergabe-Verknüpfung) folgt der Einheit. ---
        $poolIds = $this->poolResourceIds($user, $resource);
        if (count($poolIds) > 1) {
            $winBegin = Date::Parse($reservBeginDate . ' ' . $reservBeginTime, $tz);
            $winEnd = Date::Parse($reservEndDate . ' ' . $reservEndTime, $tz);
            $assignedRid = $this->pickFreeUnit($poolIds, $winBegin, $winEnd);
            if ($assignedRid === null) {
                $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Im gewählten Zeitraum ist aktuell kein Gerät dieses Typs frei. Bitte einen anderen Zeitraum wählen.'], $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
                return;
            }
            if ($assignedRid !== $rid) {
                $desc .= ' · Einheit automatisch zugewiesen (gleicher Geräte-Typ)';
                if ($einfPlan !== null) {
                    $einfPlan['resourceId'] = $assignedRid;
                }
                $rid = $assignedRid; // alle Folge-Schritte (Facade + Phase C) auf die reservierte Einheit
            }
        }

        // --- SPEC-AUSLEIHDAUER-LIMIT: Nutzungsdauer + Abhol-/Rückgabe-Puffer begrenzen (Admins ausgenommen).
        //     Misst die NUTZUNG (nicht das blockierte Fenster): D_nutz ≤ max_nutzung, P_vor/P_nach ≤ max_puffer.
        //     Ein gültiger Ausnahme-Grant (Token) hebt das Limit für genau diese Buchung auf (atomar
        //     eingelöst VOR dem Save; Save-Fehler → Grant wieder freigeben). Verfügbarkeit/Konflikt bleibt
        //     unberührt (greift erst beim Save). ---
        $ausnahmeClaim = null; // ['token'=>…, 'nonce'=>…] wenn ein Grant eingelöst wurde
        if (!$user->IsAdmin) {
            $lim = $this->zhlDurationLimits($ueb);
            $usageStartDay = $usageDayStart !== null ? $usageDayStart : $beginDate;
            $usageEndDay = $usageDayEnd !== null ? $usageDayEnd : $endDate;
            if ($usageDayStart !== null && $usageDayEnd !== null) {
                $dNutz = $this->zhlDayDiff($tz, $usageDayStart, $usageDayEnd);
            } else {
                $aSec = Date::Parse($beginDate . ' ' . $beginTime, $tz)->Timestamp();
                $bSec = Date::Parse($endDate . ' ' . $endTime, $tz)->Timestamp();
                $dNutz = (int)ceil(max(0, $bSec - $aSec) / 86400);
            }
            // Puffer: Abholtag (combined → Einführungs-Slot) vor Nutzungsstart; Rückgabetag nach Nutzungsende.
            $pVor = 0;
            $pNach = 0;
            $pickupUtc = null;
            if ($combined && $einfPlan !== null && !empty($einfPlan['start_utc'])) {
                $pickupUtc = (string)$einfPlan['start_utc'];
            } elseif ($pickupPlan !== null && !empty($pickupPlan['start_utc'])) {
                $pickupUtc = (string)$pickupPlan['start_utc'];
            }
            if ($pickupUtc !== null) {
                $pd = Date::Parse($pickupUtc, 'UTC')->ToTimezone($tz)->Format('Y-m-d');
                $pVor = max(0, $this->zhlDayDiff($tz, $pd, $usageStartDay));
            }
            if ($returnPlan !== null && !empty($returnPlan['start_utc'])) {
                $rd = Date::Parse((string)$returnPlan['start_utc'], 'UTC')->ToTimezone($tz)->Format('Y-m-d');
                $pNach = max(0, $this->zhlDayDiff($tz, $usageEndDay, $rd));
            }
            $violated = ($dNutz > $lim['nutzung']) || ($pVor > $lim['puffer']) || ($pNach > $lim['puffer']);
            if ($violated) {
                // Fenster-Bindung TAG-GRANULAR (Codex BLOCKER-2): das technische $endDate kann durch
                // Schedule-Bounds/„endNextDay" vom angefragten Zeitpunkt abweichen → gegen die NUTZUNGS-TAGE
                // (00:00 .. 23:59:59) prüfen, nicht gegen die technischen Zeiten. Ein gültiger Grant hebt den
                // GESAMTEN Dauer-Check (Nutzung UND Puffer) für dieses Fenster auf — gewollt: auch eine reine
                // Puffer-Verletzung ist ein legitimer Genehmigungsgrund (Codex BLOCKER-1, bewusst).
                $usageBeginUtc = Date::Parse($usageStartDay . ' 00:00:00', $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
                $usageEndUtc = Date::Parse($usageEndDay . ' 23:59:59', $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
                $tok = (string)$this->post('ausnahme_token');
                $granted = false;
                if (preg_match('/^[a-f0-9]{20,64}$/', $tok)
                    && ZhlDauerAusnahme::IsValidFor($db, $tok, (int)$user->UserId, $origRid, $usageBeginUtc, $usageEndUtc)) {
                    $nonce = bin2hex(random_bytes(20));
                    if (ZhlDauerAusnahme::ClaimGrant($db, $tok, (int)$user->UserId, $origRid, $nonce)) {
                        $granted = true;
                        $ausnahmeClaim = ['token' => $tok, 'nonce' => $nonce];
                    }
                }
                if (!$granted) {
                    $msgs = [];
                    if ($dNutz > $lim['nutzung']) {
                        $msgs[] = 'Die Nutzungsdauer (' . $dNutz . ' Tage) überschreitet das Maximum von ' . $lim['nutzung'] . ' Tagen.';
                    }
                    if ($pVor > $lim['puffer']) {
                        $msgs[] = 'Der Abstand zwischen Abholung und Nutzungsbeginn (' . $pVor . ' Tage) überschreitet ' . $lim['puffer'] . ' Tage.';
                    }
                    if ($pNach > $lim['puffer']) {
                        $msgs[] = 'Der Abstand zwischen Nutzungsende und Rückgabe (' . $pNach . ' Tage) überschreitet ' . $lim['puffer'] . ' Tage.';
                    }
                    $msgs[] = 'Brauchst du das Gerät länger oder mit größerem Puffer? Stelle über „Sonderfreigabe anfragen" eine begründete Anfrage — das ZHL-Team prüft sie individuell.';
                    $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, $msgs, $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
                    return;
                }
            }
        }

        // --- Phase B: native Reservierung SPEICHERN. Erst NACH Erfolg werden Terminplaner-Slots gebucht
        //     → schlägt das Speichern fehl (Pflichtfeld, Vorlauf, Konflikt), wird KEIN Termin gebucht. ---
        $facade = new ZhlReservationFacade($user->UserId, $rid, $titel, $desc, $reservBeginDate, $reservBeginTime, $reservEndDate, $reservEndTime, $facadeAttrs);
        try {
            $factory = new ReservationPresenterFactory();
            $presenter = $factory->Create($facade, $user);
            $series = $presenter->BuildReservation();
            $presenter->HandleReservation($series);
        } catch (Exception $ex) {
            Log::Error('ZHL-Buchung fehlgeschlagen: %s', $ex);
            if ($ausnahmeClaim !== null) {
                ZhlDauerAusnahme::ReleaseGrant($db, $ausnahmeClaim['token'], $ausnahmeClaim['nonce']);
            }
            $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Unerwarteter Fehler beim Buchen. Bitte erneut versuchen.'], $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
            return;
        }
        if (!$facade->WasSaved()) {
            // Save misslungen (z. B. Konflikt) → eingelösten Ausnahme-Grant wieder freigeben (wiederverwendbar).
            if ($ausnahmeClaim !== null) {
                ZhlDauerAusnahme::ReleaseGrant($db, $ausnahmeClaim['token'], $ausnahmeClaim['nonce']);
            }
            $errors = $facade->GetErrors();
            if (empty($errors)) {
                $errors = ['Die Buchung konnte nicht angelegt werden.'];
            }
            // E (Pool-TOCTOU-Härtung): Zwischen pickFreeUnit() (Lese-Prüfung) und dem nativen Persist kann
            // eine andere Buchung dieselbe Einheit gegriffen haben → die native Validierung lehnt ab. Bei
            // einem Pool >1 dem Nutzer einen klaren Wiederhol-Hinweis geben (statt nur der nativen Meldung).
            if (count($this->poolResourceIds($user, $resource)) > 1) {
                $errors[] = 'Hinweis: Eventuell wurde das Gerät gerade von jemand anderem gebucht. Bitte den Zeitraum erneut wählen oder die Seite neu laden — dann wird automatisch eine andere freie Einheit geprüft.';
            }
            $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, $errors, $attrValues, $projectTitle, $pickupSlot, $fulfillment, $hpValues);
            return;
        }
        $ref = (string)$facade->ReferenceNumber();
        // Ausnahme-Grant ist verbraucht — Reservierungsnummer nachtragen (Bookkeeping, best effort).
        if ($ausnahmeClaim !== null) {
            ZhlDauerAusnahme::SetUsedReference($db, $ausnahmeClaim['token'], $ausnahmeClaim['nonce'], $ref);
        }

        // --- Phase C: NACH erfolgreichem Save die Terminplaner-Slots buchen (Einführung, dann Abholung).
        //     Scheitert hier etwas (Slot zwischenzeitlich weg), bleibt die Reservierung bestehen → Hinweis. ---
        // WICHTIG: Ab hier ist die Reservierung gespeichert. JEDE Nach-Aktion ist in try/catch gekapselt,
        // damit ein Folge-Fehler (DB/HTTP/Mail) die bereits angelegte Buchung NIE in einen 500 kippt —
        // der Nutzer landet immer auf der Erfolgsseite (mit Hinweis), die Buchung bleibt bestehen.
        $warnings = [];
        $loanEndUtc = Date::Parse($endDate . ' ' . $endTime, $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');

        // Rückgabe-Slot (SPEC-RUECKGABE) ZUERST buchen, damit die Terminplaner-Buchungs-ID in die
        // (gemeinsame) return-Zeile der persistHandover-Aufrufe einfließt. Scheitert die Buchung,
        // bleibt $returnSlotData null → die return-Zeile fällt sauber auf das Ausleihende zurück.
        $returnSlotData = null;
        if ($returnPlan !== null) {
            try {
                $rb = $this->tpRequest('POST', '/api/book_slot.php', [], [
                    'member_id' => $returnPlan['member_id'],
                    'type_id' => $returnPlan['type_id'],
                    'slot_id' => $returnPlan['slot_id'],
                    'name' => trim($user->FirstName . ' ' . $user->LastName),
                    'email' => $user->Email,
                    'note' => 'ZHL Rückgabe — ' . $titel,
                ]);
                if (!$rb || ($rb['status'] ?? '') !== 'ok') {
                    $warnings[] = 'Die Ausleihe ist gebucht, aber der Rückgabetermin konnte nicht final reserviert werden — bitte beim ZHL-Team melden.';
                } else {
                    $returnSlotData = $returnPlan;
                    $returnSlotData['booking_id'] = isset($rb['booking_id']) ? (string)$rb['booking_id'] : null;
                }
            } catch (Throwable $e) {
                Log::Error('ZHL-Rückgabe (Phase C) nach erfolgreicher Buchung fehlgeschlagen (ref=%s, res=%s): %s', $ref, $rid, $e);
                $warnings[] = 'Die Ausleihe ist gebucht, aber der Rückgabetermin konnte nicht final reserviert werden — bitte beim ZHL-Team melden.';
            }
        }
        // Wurde die return-Zeile schon über einen pickup/combined-persistHandover geschrieben? Sonst legt
        // der Return-only-Zweig unten (Gerät mit persönlicher Rückgabe aber OHNE persönliche Abholung) sie an.
        $handoverPersisted = false;

        if ($einfPlan !== null) {
            try {
                $book = $this->tpRequest('POST', '/api/book_slot.php', [], [
                    'member_id' => $einfPlan['member_id'],
                    'type_id' => $einfPlan['type_id'],
                    'slot_id' => $einfPlan['slot_id'],
                    'name' => trim($user->FirstName . ' ' . $user->LastName),
                    'email' => $user->Email,
                    'note' => 'ZHL Medienausleihe — ' . $titel,
                ]);
                if (!$book || ($book['status'] ?? '') !== 'ok') {
                    $warnings[] = 'Die Ausleihe ist gebucht, aber der Einführungstermin konnte nicht final reserviert werden — bitte beim ZHL-Team melden.';
                } else {
                    // Einführungstermin lokal mitschreiben (zuerst — die Buchung steht bereits;
                    // so geht das Datum auch dann nicht verloren, wenn die Zertifikats-Bestätigung
                    // unten scheitert). persistEinfuehrung fängt eigene Fehler ab.
                    $einfBookingId = isset($book['booking_id']) ? (string)$book['booking_id'] : null;
                    $this->persistEinfuehrung($db, $ref, $rid, $einfPlan['member_id'], $einfBookingId, $einfPlan['start_utc'], $einfPlan['end_utc']);
                    $this->createCertConfirmation($db, (int)$user->UserId, $rid);
                    // Slot-Modus (Studio): die Einführung findet IM Gerät statt → zusätzlich zur
                    // Personal-Buchung eine native 60-Min-Geräte-Reservierung für die Einführungs-Stunde
                    // anlegen (SPEC-STUDIO-EINFUEHRUNG). Best-effort: scheitert sie, bleibt die Ausleihe.
                    if ($ueb['booking_mode'] === 'slot' && !empty($einfPlan['start_utc']) && !empty($einfPlan['end_utc'])) {
                        $einfResErr = $this->reserveEinfuehrungOnResource($user, $resource, $einfPlan['start_utc'], $einfPlan['end_utc'], $ref, $tz, $facadeAttrs);
                        if ($einfResErr !== null) {
                            Log::Error('ZHL-Einführung: Studio-Reservierung fehlgeschlagen (ref=%s, res=%s): %s', $ref, $rid, $einfResErr);
                            $warnings[] = 'Der Einführungstermin beim Team ist gebucht, aber die Studio-Reservierung für diese Stunde konnte nicht angelegt werden — bitte beim ZHL-Team melden.';
                        }
                    }
                    // „Zusammen": der gebuchte Einführungstermin IST zugleich der Übergabetermin →
                    // Übergabe-Zeile aus dem Einführungs-Slot ableiten (KEIN separater Abhol-Slot gebucht).
                    if ($combined) {
                        $combToken = bin2hex(random_bytes(16));
                        $combPersisted = $this->persistHandover($db, $combToken, (int)$user->UserId, $rid, $einfBookingId, $einfPlan['member_id'], $einfPlan['start_utc'], $einfPlan['end_utc'], $loanEndUtc, $returnSlotData);
                        if ($combPersisted) {
                            $handoverPersisted = true;
                            $this->backfillHandoverReference($db, $combToken, $ref);
                        } else {
                            Log::Error('ZHL-Zusammen: Übergabe-Zeilen (aus Einführung) konnten nicht gespeichert werden (token=%s, res=%s)', $combToken, $rid);
                            $warnings[] = 'Der gemeinsame Termin ist gebucht, aber die Übergabe konnte intern nicht hinterlegt werden — bitte beim ZHL-Team melden.';
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::Error('ZHL-Einführung (Phase C) nach erfolgreicher Buchung fehlgeschlagen (ref=%s, res=%s): %s', $ref, $rid, $e);
                $warnings[] = 'Die Ausleihe ist gebucht, aber der Einführungstermin konnte nicht final reserviert werden — bitte beim ZHL-Team melden.';
            }
        }
        if ($pickupPlan !== null) {
            try {
                $pb = $this->tpRequest('POST', '/api/book_slot.php', [], [
                    'member_id' => $pickupPlan['member_id'],
                    'type_id' => $pickupPlan['type_id'],
                    'slot_id' => $pickupPlan['slot_id'],
                    'name' => trim($user->FirstName . ' ' . $user->LastName),
                    'email' => $user->Email,
                    'note' => 'ZHL Abholung — ' . $titel,
                ]);
                if (!$pb || ($pb['status'] ?? '') !== 'ok') {
                    $warnings[] = 'Die Ausleihe ist gebucht, aber der Abholtermin konnte nicht final reserviert werden — bitte beim ZHL-Team melden.';
                } else {
                    $handoverToken = bin2hex(random_bytes(16));
                    $bookingId = isset($pb['booking_id']) ? (string)$pb['booking_id'] : null;
                    $persisted = $this->persistHandover($db, $handoverToken, (int)$user->UserId, $rid, $bookingId, $pickupPlan['member_id'], $pickupPlan['start_utc'], $pickupPlan['end_utc'], $loanEndUtc, $returnSlotData);
                    if ($persisted) {
                        $handoverPersisted = true;
                        $this->backfillHandoverReference($db, $handoverToken, $ref);
                    } else {
                        Log::Error('ZHL-Abholung: Übergabe-Zeilen konnten nicht gespeichert werden (token=%s, user=%s, res=%s)', $handoverToken, (int)$user->UserId, $rid);
                        $warnings[] = 'Abholtermin gebucht, aber intern nicht hinterlegt — bitte beim ZHL-Team melden.';
                    }
                }
            } catch (Throwable $e) {
                Log::Error('ZHL-Abholung (Phase C) nach erfolgreicher Buchung fehlgeschlagen (ref=%s, res=%s): %s', $ref, $rid, $e);
                $warnings[] = 'Die Ausleihe ist gebucht, aber der Abholtermin konnte nicht final reserviert werden — bitte beim ZHL-Team melden.';
            }
        }

        // Return-only (SPEC-RUECKGABE): Gerät mit persönlicher Rückgabe, aber OHNE persönliche Abholung/
        // Zusammen-Termin → es lief kein persistHandover. Hier die Handover-Zeilen (nur return) anlegen,
        // damit der Staff-Rückgabepfad (zhl-resource-return) den Slot kennt.
        if ($returnSlotData !== null && !$handoverPersisted) {
            try {
                $retToken = bin2hex(random_bytes(16));
                $retPersisted = $this->persistHandover($db, $retToken, (int)$user->UserId, $rid, null, 0, null, null, $loanEndUtc, $returnSlotData);
                if ($retPersisted) {
                    $this->backfillHandoverReference($db, $retToken, $ref);
                } else {
                    Log::Error('ZHL-Rückgabe: return-Zeile konnte nicht gespeichert werden (token=%s, user=%s, res=%s)', $retToken, (int)$user->UserId, $rid);
                    $warnings[] = 'Rückgabetermin gebucht, aber intern nicht hinterlegt — bitte beim ZHL-Team melden.';
                }
            } catch (Throwable $e) {
                Log::Error('ZHL-Rückgabe (Phase C, return-only) fehlgeschlagen (ref=%s, res=%s): %s', $ref, $rid, $e);
                $warnings[] = 'Rückgabetermin gebucht, aber intern nicht hinterlegt — bitte beim ZHL-Team melden.';
            }
        }

        // Hauspost (C2): Antrag speichern + Transport/Medien/Ausleihenden benachrichtigen.
        // Mail-/DB-Fehler darf die erfolgreiche Buchung nie kippen → try/catch.
        if ($hauspostActive) {
            try {
                $this->persistAndNotifyHauspost($db, $user, $rid, $ref, ($type !== null ? $type : (string)$resource->GetName()), $hpValues);
            } catch (Throwable $e) {
                Log::Error('ZHL-Hauspost: Antrag/Mail nach erfolgreicher Buchung fehlgeschlagen (ref=%s, res=%s): %s', $ref, $rid, $e);
                $warnings[] = 'Die Ausleihe ist gebucht, aber der Hauspost-Antrag/die Benachrichtigung konnte nicht final verarbeitet werden — bitte beim ZHL-Team melden.';
            }
        }

        zhl_audit_log(array_merge(zhl_audit_actor($user), [
            'action' => 'booking.create',
            'entity_type' => 'reservation',
            'entity_id' => (string)$rid,
            'reference_number' => $ref,
            'detail' => [
                'device' => ($type !== null ? $type : (string)$resource->GetName()),
                'fulfillment' => $fulfillment,
                'from' => $reservBeginDate,
                'to' => $endDate,
                'pickup' => $pickupPlan !== null,
                'einf' => $einfPlan !== null,
                'return' => $returnPlan !== null,
                'warnings' => $warnings,
            ],
        ]));

        // D2: Begleit-Mail mit Info-Material zu den gebuchten Medien (nur wenn ein Info-Link da ist).
        // Darf die erfolgreiche Buchung nie kippen → try/catch.
        $this->sendMediaInfoMail($db, $user, $ref);

        $this->page->RedirectToSuccess($ref, implode(' · ', $warnings));
    }

    // --- intern ---

    private function bindForm(UserSession $user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, array $errors, array $attrValues = [], string $projectTitle = '', string $pickupSlot = '', string $fulfillment = 'pickup', array $hauspostValues = [])
    {
        $db = ServiceLocator::GetDatabase();
        $tz = $user->Timezone;
        $rid = (int)$resource->GetId();
        $requestedDate = $beginDate; // vor der Vorlauf-Klammerung (für Wochen-Navigation im Slotmodus)

        // Reservierungs-Custom-Attribute, die für dieses Gerät gelten (Pflicht-/Optionalfelder).
        $attributes = [];
        foreach ($this->applicableReservationAttributes($rid, (bool)$user->IsAdmin) as $a) {
            $attributes[] = [
                'id' => (int)$a->Id(),
                'label' => (string)$a->Label(),
                'type' => (int)$a->Type(),
                'required' => (bool)$a->Required(),
                'options' => $a->PossibleValueList(),
                'value' => array_key_exists((int)$a->Id(), $attrValues) ? (string)$attrValues[(int)$a->Id()] : '',
            ];
        }
        $type = $this->lookupType($db, $rid);
        $scheduleName = $this->lookupScheduleName($db, (int)$resource->ScheduleId);

        $ueb = $this->lookupUebergabe($db, $rid);

        $noticeSec = $this->lookupMinNotice($db, $rid);
        $minNoticeDays = 0;
        $earliestLabel = null;
        $earliestYmd = null;
        if ($noticeSec > 0) {
            $earliest = Date::Now()->ApplyDifference(TimeInterval::Parse($noticeSec)->Interval())->ToTimezone($tz);
            $minNoticeDays = (int)ceil($noticeSec / 86400);
            $earliestLabel = $earliest->Format('d.m.Y');
            $earliestYmd = $earliest->Format('Y-m-d');
            if ($beginDate < $earliestYmd) {
                $beginDate = $earliestYmd;
            }
            if ($endDate < $beginDate) {
                $endDate = $beginDate;
            }
        }

        // Einführungs-Gate (F40-Zertifikat ODER Termin aus dem Terminplaner) — nur wenn konfiguriert.
        $einf = [
            'mode' => $ueb['einfuehrung'],
            'typLabel' => $ueb['einfuehrung_typ'],
            'certified' => false,
            'slots' => [],
            'days' => [],
            'earliestLabel' => null,
            'typeId' => null,
            'memberId' => null,
            'blocked' => false,
        ];
        if ($ueb['einfuehrung'] !== 'keine') {
            // Admin-Gruppe (Nutzer-Vorgabe 2026-07-15): keine Einführung nötig, unabhängig vom Zertifikatsstatus.
            $einf['certified'] = $user->IsAdmin || $this->userIsCertified($db, $user->UserId, $rid);
            if (!$einf['certified']) {
                $loanStartUtc = Date::Parse($beginDate . ' ' . $beginTime, $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
                $f = $this->fetchEinfuehrungSlots($ueb['einfuehrung_typ'], $ueb['tp_member_id'], $loanStartUtc, $tz);
                $einf['slots'] = $f['slots'];
                $einf['days'] = $this->groupPickupByDay($f['slots'], $tz);
                $einf['earliestLabel'] = $f['earliestLabel'];
                $einf['typeId'] = $f['typeId'];
                $einf['memberId'] = $f['memberId'];
                $einf['blocked'] = ($ueb['einfuehrung'] === 'notwendig' && empty($f['slots']));
            }
        }

        // Abhol-Slot-Picker (C1) — nur wenn das Gerät überhaupt abgeholt werden muss.
        $pickup = null;
        if ($this->pickupApplies($ueb)) {
            $loanStartUtc = Date::Parse($beginDate . ' ' . $beginTime, $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
            $f = $this->fetchHandoverSlots($this->handoverTypeLabels(), $ueb['tp_member_id'], $loanStartUtc, $tz);
            $mandatory = !$user->IsAdmin;
            $pickup = [
                'mode' => $ueb['abholung'],
                'mandatory' => $mandatory,
                'slots' => $f['slots'],
                'days' => $this->groupPickupByDay($f['slots'], $tz),
                'earliestLabel' => $f['earliestLabel'],
                'typeId' => $f['typeId'],
                'memberId' => $f['memberId'],
                'selected' => $pickupSlot,
                'blocked' => ($mandatory && empty($f['slots'])),
            ];
        }

        // Rückgabe-Slot-Picker (SPEC-RUECKGABE) — nur bei persönlicher Rückgabe. Floor = max(Endtag-Anfang,
        // Start-Anker); selber Terminplaner-Typ wie die Abholung. 'blocked' = Pflicht ohne freien Slot (AK-9-Andockpunkt).
        $return = null;
        if ($this->returnApplies($ueb)) {
            $floorUtc = $this->returnFloorUtc($beginDate, $beginTime, $endDate, $tz);
            $f = $this->fetchReturnSlots($this->handoverTypeLabels(), $ueb['tp_member_id'], $floorUtc, $tz);
            $mandatory = !$user->IsAdmin;
            $return = [
                'mode' => $ueb['rueckgabe'],
                'applies' => true,
                'mandatory' => $mandatory,
                'slots' => $f['slots'],
                'days' => $this->groupPickupByDay($f['slots'], $tz),
                'earliestLabel' => $f['earliestLabel'],
                'selected' => $this->post('return_slot'),
                'blocked' => ($mandatory && empty($f['slots'])),
            ];
        }

        // Hauspost-Alternative (C2) — eigenständiges Geräte-Flag, gilt unabhängig vom Übergabe-Modus.
        // Die Re-Render-Werte (a–f2) werden durchgereicht, damit ein Validierungsfehler die Eingaben behält.
        $hauspost = null;
        if (!empty($ueb['hauspost_allowed'])) {
            $cfg = $this->hauspostConfig();
            $hauspost = [
                'allowed' => true,
                'leadDays' => (int)($cfg['lead_days'] ?? 3),
                'transportEmail' => (string)($cfg['transport_email'] ?? ''),
                'medienEmail' => (string)($cfg['medien_email'] ?? ''),
                'selected' => ($fulfillment === 'hauspost'),
                'values' => [
                    'einsatz_termin' => (string)($hauspostValues['einsatz_termin'] ?? ''),
                    'einsatz_raum' => (string)($hauspostValues['einsatz_raum'] ?? ''),
                    'einsatz_titel' => (string)($hauspostValues['einsatz_titel'] ?? ''),
                    'liefer_fenster' => (string)($hauspostValues['liefer_fenster'] ?? ''),
                    'rueckhol_fenster' => (string)($hauspostValues['rueckhol_fenster'] ?? ''),
                    'anlieferort' => (string)($hauspostValues['anlieferort'] ?? ''),
                    'abholort_hauspost' => (string)($hauspostValues['abholort_hauspost'] ?? ''),
                ],
            ];
        }

        // Termin-Vorschläge: Wochen-Raster (Slotmodus, frei wählbare Spanne) bzw. Monats-Kalender (Tagesmodus).
        $picker = ['mode' => ($ueb['booking_mode'] === 'slot') ? 'slot' : 'day', 'grid' => null, 'cal' => null];
        if ($ueb['booking_mode'] === 'slot') {
            $picker['grid'] = $this->weekGrid($user, $resource, $requestedDate, $noticeSec);
        } else {
            $picker['cal'] = $this->monthGrid($user, $resource, $requestedDate, $noticeSec);
        }

        // „Zusammen oder getrennt" (Nutzer-Wahl): die Auswahl erscheint nur, wenn eine Einführung ansteht
        // (nicht zertifiziert) UND eine persönliche Abholung greift. Default: bei Pflicht-Einführung
        // „zusammen" (ein Termin), sonst „getrennt". Eine gepostete Wahl gewinnt (Re-Render).
        $combineEligible = ($ueb['einfuehrung'] !== 'keine') && empty($einf['certified']) && $this->pickupApplies($ueb);
        $postedMode = $this->post('handover_mode');
        if ($postedMode === 'zusammen' || $postedMode === 'getrennt') {
            $handoverModeView = $postedMode;
        } else {
            $handoverModeView = ($ueb['einfuehrung'] === 'notwendig') ? 'zusammen' : 'getrennt';
        }

        // Ausleihdauer-Limit: effektive Werte für Hinweistext + Token-Durchschleusung (Sonderfreigabe).
        $durLimits = $this->zhlDurationLimits($ueb);
        $ausnahmeToken = '';
        $rawTok = (string)($_REQUEST['ausnahme_token'] ?? '');
        if (preg_match('/^[a-f0-9]{20,64}$/', $rawTok)) {
            $ausnahmeToken = $rawTok;
        }

        $this->page->BindBooking([
            'resourceId' => $rid,
            'maxNutzungDays' => $durLimits['nutzung'],
            'maxPufferDays' => $durLimits['puffer'],
            'ausnahmeToken' => $ausnahmeToken,
            'scheduleId' => (int)$resource->ScheduleId,
            'resourceName' => (string)$resource->GetName(),
            'resourceType' => $type,
            'scheduleName' => $scheduleName,
            'projectTitle' => $projectTitle,
            'beginDate' => $beginDate,
            'endDate' => $endDate,
            'beginTime' => $beginTime,
            'endTime' => $endTime,
            'choice' => $choice,
            'minNoticeDays' => $minNoticeDays,
            'earliestLabel' => $earliestLabel,
            'earliestYmd' => $earliestYmd,
            'abholung' => $ueb['abholung'],
            'abholort' => $ueb['abholort'],
            'einfuehrung' => $ueb['einfuehrung'],
            'einfuehrungTyp' => $ueb['einfuehrung_typ'],
            'einf' => $einf,
            'pickup' => $pickup,
            'return' => $return,
            'rueckgabe' => $ueb['rueckgabe'],
            'rueckgabeort' => $ueb['rueckgabeort'] ?? null,
            'hauspost' => $hauspost,
            'fulfillment' => ($fulfillment === 'hauspost') ? 'hauspost' : 'pickup',
            'combineEligible' => $combineEligible,
            'handoverMode' => $handoverModeView,
            'picker' => $picker,
            'attributes' => $attributes,
            'errors' => $errors,
        ]);
    }

    /**
     * Reservierungs-Custom-Attribute, die für dieses Gerät gelten — gleiche Skip-Logik wie
     * AttributeService::Validate (Unique/Secondary-Entity-Filter + AdminOnly). So zeigt die ZHL-Seite
     * genau die Felder, die die native Save-Validierung erwartet (z. B. Pflicht „Haftpflichtversicherung").
     * @return CustomAttribute[]
     */
    private function applicableReservationAttributes(int $resourceId, bool $isAdmin): array
    {
        $svc = new AttributeService(new AttributeRepository());
        $ids = [$resourceId];
        $out = [];
        foreach ($svc->GetByCategory(CustomAttributeCategory::RESERVATION) as $a) {
            // ZHL-Buchungsseite zeigt bewusst NUR Pflichtfelder (z. B. Haftpflicht). Optionale
            // Attribute (Language, „Einführung gewünscht?") verwirren hier — die Einführung regelt
            // bereits der Slot-Picker. So bleibt das Formular schlank.
            if (!$a->Required()) {
                continue;
            }
            if (($a->UniquePerEntity() && count(array_intersect($ids, $a->EntityIds())) == 0) ||
                ($a->HasSecondaryEntities() && count(array_intersect($ids, $a->SecondaryEntityIds())) == 0)) {
                continue;
            }
            if ($a->AdminOnly() && !$isAdmin) {
                continue;
            }
            $out[] = $a;
        }
        return $out;
    }

    private function loadResource(UserSession $user, int $rid)
    {
        if ($rid <= 0) {
            return null;
        }
        $resourceService = new ResourceService(
            new ResourceRepository(),
            new SchedulePermissionService(PluginManager::Instance()->LoadPermission()),
            new AttributeService(new AttributeRepository()),
            new UserRepository(),
            new AccessoryRepository()
        );
        foreach ($resourceService->GetAllResources(false, $user) as $r) {
            if ((int)$r->GetId() === $rid && $r->StatusId != ResourceStatus::HIDDEN) {
                return $r;
            }
        }
        return null;
    }

    // --- Geräte-Pool (SPEC-POOL-FALLBACK): Verfügbarkeit über alle gleichtypigen Einheiten ---

    /**
     * Alle vom Nutzer buchbaren Einheiten DESSELBEN Geräte-Typs und Schedules wie $resource — der
     * „Pool", über den der Kalender Verfügbarkeit aggregiert und der Commit automatisch zuteilt.
     * Die angeklickte Einheit ($resource) steht vorn (Präferenz). Ohne Geräte-Typ (Einzelstück)
     * → nur diese eine Einheit (Verhalten exakt wie vorher). Pro Request memoisiert.
     * @return int[]
     */
    private function poolResourceIds(UserSession $user, $resource): array
    {
        $rid = (int)$resource->GetId();
        if (isset($this->poolCache[$rid])) {
            return $this->poolCache[$rid];
        }
        $db = ServiceLocator::GetDatabase();
        $type = $this->lookupType($db, $rid);
        if ($type === null) {
            return $this->poolCache[$rid] = [$rid];
        }
        $scheduleId = (int)$resource->ScheduleId;
        $idSet = array_fill_keys($this->resourceIdsOfType($db, $type), true);

        // Eine Ersatz-Einheit ist nur dann äquivalent (und damit automatisch zuteilbar), wenn sie für
        // DIESEN Nutzer dieselben ZHL-Regeln trägt wie die angeklickte: identische Übergabe-Konfiguration
        // (Abholung/Einführung/Modus/Hauspost …) UND derselbe Einführungs-Gate-Status (keine Einführung
        // nötig bzw. zertifiziert). Sonst könnte z. B. ein zertifikatsfreier Klick die Pflicht-Einführung
        // der Ersatz-Einheit umgehen (Codex-Befund). Diese vorab gegen $rid aufgelösten Entscheidungen
        // (Übergabe, Cert, Einführungs-/Abhol-Slot) gelten dann garantiert auch für die Ersatz-Einheit.
        $isAdmin = (bool)$user->IsAdmin;
        $refUeb = $this->lookupUebergabe($db, $rid);
        $refSig = $this->uebergabeSignature($refUeb);
        $refGate = $this->einfGateSatisfied($db, $user, $rid, $refUeb);
        // Auch die anwendbaren Reservierungs-Pflichtattribute müssen übereinstimmen — sonst würde die
        // native Validierung die Ersatz-Einheit ablehnen (am Klick-Gerät erhobene Attribute passen nicht).
        $refAttrSig = $this->reservationAttrSignature($rid, $isAdmin);

        $resourceService = new ResourceService(
            new ResourceRepository(),
            new SchedulePermissionService(PluginManager::Instance()->LoadPermission()),
            new AttributeService(new AttributeRepository()),
            new UserRepository(),
            new AccessoryRepository()
        );
        $others = [];
        foreach ($resourceService->GetAllResources(false, $user) as $r) {
            $id = (int)$r->GetId();
            if ($id === $rid || !isset($idSet[$id])) {
                continue;
            }
            // buchbar (nicht nur sichtbar), nicht versteckt, gleicher Schedule (identisches Kalender-Layout),
            // Einzelbelegung (maxConcurrent=1 — sonst stimmt die „belegt"-Logik nicht mit der nativen überein)
            if (!$r->CanBook || $r->StatusId == ResourceStatus::HIDDEN || (int)$r->ScheduleId !== $scheduleId || $r->GetMaxConcurrentReservations() > 1) {
                continue;
            }
            $cUeb = $this->lookupUebergabe($db, $id);
            if ($this->uebergabeSignature($cUeb) !== $refSig) {
                continue; // andere Übergabe-/Einführungsregeln
            }
            if ($this->einfGateSatisfied($db, $user, $id, $cUeb) !== $refGate) {
                continue; // anderer Einführungs-/Zertifikats-Status für diesen Nutzer
            }
            if ($this->reservationAttrSignature($id, $isAdmin) !== $refAttrSig) {
                continue; // andere Reservierungs-/Pflichtattribute
            }
            $others[] = $id;
        }
        sort($others);
        return $this->poolCache[$rid] = array_merge([$rid], $others);
    }

    /** Signatur der für ein Gerät anwendbaren Reservierungs-Attribut-IDs (für Pool-Äquivalenz). */
    private function reservationAttrSignature(int $rid, bool $isAdmin): string
    {
        $ids = [];
        foreach ($this->applicableReservationAttributes($rid, $isAdmin) as $a) {
            $ids[] = (int)$a->Id();
        }
        sort($ids);
        return implode(',', $ids);
    }

    /** Stabile Signatur der Übergabe-Konfiguration (für Pool-Äquivalenz, inkl. Buchungsmodus). */
    private function uebergabeSignature(array $ueb): string
    {
        ksort($ueb);
        return json_encode($ueb, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Ist das Einführungs-Gate für (Nutzer, Gerät) bereits erfüllt? true = keine Einführung nötig
     * (einfuehrung='keine') ODER der Nutzer ist zertifiziert. Bestimmt, ob eine Substitution dieselbe
     * Einführungs-/Abhol-Entscheidung erben darf.
     */
    private function einfGateSatisfied($db, UserSession $user, int $rid, array $ueb): bool
    {
        if (($ueb['einfuehrung'] ?? 'keine') === 'keine') {
            return true;
        }
        // Admin-Gruppe (Nutzer-Vorgabe 2026-07-15): keine Einführung nötig, unabhängig vom Zertifikatsstatus.
        return $user->IsAdmin || $this->userIsCertified($db, (int)$user->UserId, $rid);
    }

    /** Alle entity_ids (Ressourcen) eines Geräte-Typs — unabhängig von Permission. @return int[] */
    private function resourceIdsOfType($db, string $typeLabel): array
    {
        $cmd = new AdHocCommand(
            'SELECT v.entity_id AS rid FROM custom_attribute_values v ' .
            'JOIN custom_attributes a ON a.custom_attribute_id = v.custom_attribute_id ' .
            'WHERE a.display_label = @l AND a.attribute_category = 4 AND v.attribute_value = @t'
        );
        $cmd->AddParameter(new Parameter('@l', 'Geräte-Typ'));
        $cmd->AddParameter(new Parameter('@t', $typeLabel));
        $reader = $db->Query($cmd);
        $ids = [];
        while ($row = $reader->GetRow()) {
            $ids[] = (int)$row['rid'];
        }
        $reader->Free();
        return $ids;
    }

    /**
     * Reservierungs-/Blackout-Items nach resource_id gruppieren.
     * @return array<int, object[]>
     */
    private function splitItemsByResource(array $items): array
    {
        $byResource = [];
        foreach ($items as $it) {
            $byResource[(int)$it->GetResourceId()][] = $it;
        }
        return $byResource;
    }

    /** Überlappt mindestens ein Item das Fenster [$begin,$end)? */
    private function unitBusy(array $items, Date $begin, Date $end): bool
    {
        foreach ($items as $it) {
            if ($it->GetStartDate()->LessThan($end) && $it->GetEndDate()->GreaterThan($begin)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ist im Fenster [$begin,$end) mindestens EINE Pool-Einheit frei?
     * @param array<int,object[]> $byResource  vorab gruppierte Items (splitItemsByResource)
     * @param int[] $poolIds
     */
    private function poolWindowFree(array $byResource, array $poolIds, Date $begin, Date $end): bool
    {
        foreach ($poolIds as $pid) {
            if (!$this->unitBusy($byResource[$pid] ?? [], $begin, $end)) {
                return true;
            }
        }
        return false;
    }

    /** Erste freie Einheit des Pools im Fenster [$begin,$end) (Präferenz zuerst) oder null. */
    private function pickFreeUnit(array $poolIds, Date $begin, Date $end): ?int
    {
        if (empty($poolIds)) {
            return null;
        }
        $items = (new ResourceAvailability(new ReservationViewRepository()))->GetItemsBetween($begin, $end, $poolIds);
        $byResource = $this->splitItemsByResource($items);
        foreach ($poolIds as $pid) {
            if (!$this->unitBusy($byResource[$pid] ?? [], $begin, $end)) {
                return $pid;
            }
        }
        return null;
    }

    /**
     * SPEC-STUDIO-EINFUEHRUNG: behält nur Einführungs-Slots, deren Fenster [start_utc,end_utc) im
     * Geräte-Pool frei ist (Studio-Stunde verfügbar). Slots ohne saubere UTC-Zeiten fallen raus.
     * @param array[] $slots Slots aus fetchEinfuehrungSlots (mit start_utc/end_utc als 'Y-m-d H:i:s' UTC)
     * @return array[]
     */
    private function filterSlotsResourceFree(UserSession $user, $resource, array $slots): array
    {
        $poolIds = $this->poolResourceIds($user, $resource);
        $out = [];
        foreach ($slots as $s) {
            if (empty($s['start_utc']) || empty($s['end_utc'])) {
                continue;
            }
            try {
                $begin = Date::Parse((string)$s['start_utc'], 'UTC');
                $end = Date::Parse((string)$s['end_utc'], 'UTC');
            } catch (Exception $e) {
                continue;
            }
            if ($this->pickFreeUnit($poolIds, $begin, $end) !== null) {
                $out[] = $s;
            }
        }
        return array_values($out);
    }

    /**
     * Task B — Abhol-Slots gegen die Geräte-Verfügbarkeit über [Abholtag … Nutzungsende] filtern.
     * Behält nur Slots, an deren Abholtag mindestens EINE Pool-Einheit des Geräte-Typs durchgehend bis
     * zum Nutzungsende frei ist (Reservierung beginnt am Abholtag). Spiegelt den Commit-Pfad
     * (poolResourceIds + pickFreeUnit) → ein angebotener Slot ist beim Speichern auch erfüllbar.
     *
     * Fenster je Slot = [Abholtag-Schedule-Beginn … Nutzungsende-Schedule-Ende]. Nur Tagesmodus.
     * @param array[] $slots      Roh-Abhol-Slots (mit start_utc)
     * @param string  $loanEndYmd Nutzungs-End-Tag (Y-m-d)
     * @return array[]
     */
    private function filterPickupSlotsByResourceFree(UserSession $user, $resource, array $slots, string $loanEndYmd, $tz): array
    {
        if (empty($slots)) {
            return $slots;
        }
        $poolIds = $this->poolResourceIds($user, $resource);
        if (empty($poolIds)) {
            return $slots;
        }
        // Nutzungsende-Tagesgrenze (einmal): Reservierung endet am Nutzungs-End-Tag zur Schedule-Endzeit.
        $endBounds = $this->scheduleDayBounds($user, $resource, $loanEndYmd);
        $loanEndDate = $endBounds['endNextDay']
            ? Date::Parse($loanEndYmd . ' 00:00:00', $tz)->AddDays(1)->Format('Y-m-d')
            : $loanEndYmd;
        $loanEndUtc = Date::Parse($loanEndDate . ' ' . $endBounds['end'], $tz);

        // Codex-Fix (Perf): Belegung aller Pool-Einheiten EINMAL über das Gesamtfenster laden
        // [frühester Abholtag … Nutzungsende] statt je Slot eine eigene DB-Abfrage (pickFreeUnit).
        $earliestDay = null;
        foreach ($slots as $s) {
            if (empty($s['start_utc'])) {
                continue;
            }
            $d = Date::Parse((string)$s['start_utc'], 'UTC')->ToTimezone($tz)->Format('Y-m-d');
            if ($earliestDay === null || strcmp($d, $earliestDay) < 0) {
                $earliestDay = $d;
            }
        }
        if ($earliestDay === null) {
            return $slots;
        }
        $loadBegin = Date::Parse($earliestDay . ' 00:00:00', $tz);
        $items = (new ResourceAvailability(new ReservationViewRepository()))->GetItemsBetween($loadBegin, $loanEndUtc, $poolIds);
        $byResource = $this->splitItemsByResource($items);

        $out = [];
        foreach ($slots as $s) {
            if (empty($s['start_utc'])) {
                $out[] = $s; // ohne Tag nicht beurteilbar → konservativ behalten.
                continue;
            }
            $pickupDay = Date::Parse((string)$s['start_utc'], 'UTC')->ToTimezone($tz)->Format('Y-m-d');
            $sb = $this->scheduleDayBounds($user, $resource, $pickupDay);
            $winBegin = Date::Parse($pickupDay . ' ' . $sb['begin'], $tz);
            // Liegt der Abholtag NACH dem Nutzungsende (sollte nicht passieren), Fenster = Abholtag.
            $winEnd = $winBegin->GreaterThan($loanEndUtc)
                ? Date::Parse($pickupDay . ' ' . $sb['end'], $tz)
                : $loanEndUtc;
            if ($this->poolWindowFree($byResource, $poolIds, $winBegin, $winEnd)) {
                $out[] = $s;
            }
        }
        return array_values($out);
    }

    /**
     * Richtet ein Zeitfenster [beginUtc, endUtc) auf die buchbaren Schedule-Perioden des Geräts aus:
     * liefert Anfang/Ende der Vereinigung aller reservierbaren Perioden, die das Fenster überlappen.
     * Slot-Schedules (Studio) haben Stunden-Perioden → ein 30-Min-Einführungstermin wird so auf die
     * volle Stunde geweitet, damit die native SchedulePeriodRule das Ende akzeptiert.
     * @return ?array{begin:Date,end:Date} (lokale TZ) oder null = keine reservierbare Periode überlappt.
     */
    private function alignToSchedulePeriods(UserSession $user, $resource, Date $beginUtc, Date $endUtc): ?array
    {
        $tz = $user->Timezone;
        $repo = new ScheduleRepository();
        $layout = $repo->GetLayout((int)$resource->ScheduleId, new ScheduleLayoutFactory($tz));
        $startDay = $beginUtc->ToTimezone($tz)->Format('Y-m-d');
        $endDay = $endUtc->ToTimezone($tz)->Format('Y-m-d');
        $days = [Date::Parse($startDay . ' 00:00:00', $tz)];
        if ($endDay !== $startDay) {
            $days[] = Date::Parse($endDay . ' 00:00:00', $tz);
        }
        $alignedBegin = null;
        $alignedEnd = null;
        foreach ($days as $day) {
            foreach ($layout->GetLayout($day, false) as $p) {
                if (!method_exists($p, 'IsReservable') || !$p->IsReservable() || $p->BeginDate() === null || $p->EndDate() === null) {
                    continue;
                }
                $pb = $p->BeginDate();
                $pe = $p->EndDate();
                // Überlappt die Periode das Fenster? (Periodenanfang < Fensterende UND Periodenende > Fensteranfang)
                if ($pb->LessThan($endUtc) && $pe->GreaterThan($beginUtc)) {
                    if ($alignedBegin === null || $pb->LessThan($alignedBegin)) {
                        $alignedBegin = $pb;
                    }
                    if ($alignedEnd === null || $pe->GreaterThan($alignedEnd)) {
                        $alignedEnd = $pe;
                    }
                }
            }
        }
        if ($alignedBegin === null || $alignedEnd === null) {
            return null;
        }
        return ['begin' => $alignedBegin->ToTimezone($tz), 'end' => $alignedEnd->ToTimezone($tz)];
    }

    /**
     * SPEC-STUDIO-EINFUEHRUNG: legt für die Einführungs-Stunde eine native Geräte-Reservierung an
     * (Studio muss für die Einführung vor Ort & blockiert sein). Reserviert auf der freien Pool-Einheit;
     * volle native Validierung (Konflikt/Vorlauf/Periodengrenze) bleibt letzte Instanz.
     * @return ?string null bei Erfolg, sonst eine Fehlermeldung (Logging/Warnung)
     */
    private function reserveEinfuehrungOnResource(UserSession $user, $resource, string $startUtc, string $endUtc, string $ref, $tz, array $attributeValues = []): ?string
    {
        try {
            $beginUtc = Date::Parse($startUtc, 'UTC');
            $endUtcD = Date::Parse($endUtc, 'UTC');
        } catch (Exception $e) {
            return 'ungültige Einführungs-Zeit';
        }
        // Einführungstermin (z. B. 30 Min) auf die buchbaren Schedule-Perioden ausrichten — der Studio-
        // Schedule hat Stunden-Perioden, ein Ende wie 14:30 ist keine gültige Periodengrenze (native
        // SchedulePeriodRule lehnt sonst mit „Endzeit nicht gültig" ab). Wir blockieren die überlappende(n)
        // volle(n) Stunde(n) → deckt sich mit „das Studio ist für diese Stunde reserviert".
        $aligned = $this->alignToSchedulePeriods($user, $resource, $beginUtc, $endUtcD);
        $b = $aligned !== null ? $aligned['begin'] : $beginUtc->ToTimezone($tz);
        $e = $aligned !== null ? $aligned['end'] : $endUtcD->ToTimezone($tz);
        $poolIds = $this->poolResourceIds($user, $resource);
        $unit = $this->pickFreeUnit($poolIds, $b, $e) ?? (int)$resource->GetId();
        $facade = new ZhlReservationFacade(
            $user->UserId,
            $unit,
            'Einführung Videostudio',
            'Pflicht-Einführung zur Buchung ' . $ref . ' (das Studio ist für diese Stunde reserviert).',
            $b->Format('Y-m-d'),
            $b->Format('H:i'),
            $e->Format('Y-m-d'),
            $e->Format('H:i'),
            // Pflicht-Reservierungs-Attribute (z. B. „Haftpflichtversicherung") aus der Hauptbuchung
            // mitgeben — sonst lehnt die native Validierung die Einführungs-Reservierung ab.
            $attributeValues
        );
        try {
            $factory = new ReservationPresenterFactory();
            $presenter = $factory->Create($facade, $user);
            $series = $presenter->BuildReservation();
            $presenter->HandleReservation($series);
        } catch (Exception $ex) {
            return 'Reservierungs-Handler-Fehler: ' . $ex->getMessage();
        }
        if (!$facade->WasSaved()) {
            $errs = $facade->GetErrors();
            return empty($errs) ? 'Reservierung nicht gespeichert' : implode(' ', $errs);
        }
        return null;
    }

    // --- Verfügbarkeits-Vorschläge: freie Tage (Tagesmodus) bzw. 2h-Slots (Slotmodus) ---

    /**
     * Freie Tage ab $fromYmd (Vorlauf berücksichtigt), max $maxResults Treffer.
     * @return array[] [{date:'Y-m-d', label:'Do 03.07.'}]
     */
    private function availableDays(UserSession $user, $resource, string $fromYmd, int $minNoticeSec, int $maxResults = 14, int $windowDays = 45): array
    {
        $tz = $user->Timezone;
        $earliestYmd = $fromYmd;
        if ($minNoticeSec > 0) {
            $e = Date::Now()->ApplyDifference(TimeInterval::Parse($minNoticeSec)->Interval())->ToTimezone($tz)->Format('Y-m-d');
            if ($earliestYmd < $e) {
                $earliestYmd = $e;
            }
        }
        $todayYmd = Date::Now()->ToTimezone($tz)->Format('Y-m-d');
        if ($earliestYmd < $todayYmd) {
            $earliestYmd = $todayYmd;
        }
        $from = Date::Parse($earliestYmd . ' 00:00:00', $tz);
        $to = $from->AddDays($windowDays);
        $poolIds = $this->poolResourceIds($user, $resource);
        $byResource = $this->splitItemsByResource(
            (new ResourceAvailability(new ReservationViewRepository()))->GetItemsBetween($from, $to, $poolIds)
        );
        $out = [];
        for ($dd = 0; $dd < $windowDays && count($out) < $maxResults; $dd++) {
            $dayStart = $from->AddDays($dd);
            $dayEnd = $from->AddDays($dd + 1);
            // Tag frei, sobald irgendeine Pool-Einheit frei ist.
            if (!$this->poolWindowFree($byResource, $poolIds, $dayStart, $dayEnd)) {
                continue;
            }
            $local = $dayStart->ToTimezone($tz);
            $out[] = ['date' => $local->Format('Y-m-d'), 'label' => self::WD[(int)$local->Format('N')] . ' ' . $local->Format('d.m.')];
        }
        return $out;
    }

    /**
     * Freie 2h-Slots eines Tages aus den buchbaren Schedule-Perioden (Slotmodus, z. B. Videostudio).
     * @return array[] [{begin:'H:i', end:'H:i', label:'09:00–11:00', free:bool}]
     */
    private function freeSlots(UserSession $user, $resource, string $dayYmd, int $blockHours = 2): array
    {
        $tz = $user->Timezone;
        $repo = new ScheduleRepository();
        $layout = $repo->GetLayout((int)$resource->ScheduleId, new ScheduleLayoutFactory($tz));
        $day = Date::Parse($dayYmd . ' 00:00:00', $tz);
        $periods = $layout->GetLayout($day, false);

        // Buchbare Perioden (sortiert) sammeln. Pool-Belegung je Einheit gruppiert.
        $poolIds = $this->poolResourceIds($user, $resource);
        $byResource = $this->splitItemsByResource(
            (new ResourceAvailability(new ReservationViewRepository()))->GetItemsBetween($day, $day->AddDays(1), $poolIds)
        );
        $blocks = [];
        foreach ($periods as $p) {
            if (!method_exists($p, 'IsReservable') || !$p->IsReservable() || $p->BeginDate() === null || $p->EndDate() === null) {
                continue;
            }
            $blocks[] = ['begin' => $p->BeginDate(), 'end' => $p->EndDate()];
        }
        usort($blocks, fn($a, $b) => $a['begin']->Compare($b['begin']));

        // Je 2 aufeinanderfolgende Stunden-Perioden zu einem 2h-Slot bündeln (nicht überlappend).
        $slots = [];
        for ($i = 0; $i + $blockHours - 1 < count($blocks); $i += $blockHours) {
            $begin = $blocks[$i]['begin'];
            $end = $blocks[$i + $blockHours - 1]['end'];
            // zusammenhängend?
            $contiguous = true;
            for ($k = $i; $k < $i + $blockHours - 1; $k++) {
                if (!$blocks[$k]['end']->Equals($blocks[$k + 1]['begin'])) {
                    $contiguous = false;
                    break;
                }
            }
            if (!$contiguous) {
                continue;
            }
            $free = $this->poolWindowFree($byResource, $poolIds, $begin, $end);
            $bl = $begin->ToTimezone($tz)->Format('H:i');
            $el = $end->ToTimezone($tz)->Format('H:i');
            $slots[] = ['begin' => $bl, 'end' => $el, 'label' => $bl . '–' . $el, 'free' => $free];
        }
        return $slots;
    }

    /**
     * Buchbare Tagesgrenzen aus dem Schedule-Layout (erste reservable Periode → letzte) — damit
     * Tagesbuchungen auf Periodengrenzen liegen (native SchedulePeriodRule). 'endNextDay' = letzte
     * Periode endet um Mitternacht (Ganztags-Layout) → Ende liegt am Folgetag.
     * @return array{begin:string,end:string,endNextDay:bool}
     */
    private function scheduleDayBounds(UserSession $user, $resource, string $dayYmd): array
    {
        $tz = $user->Timezone;
        $repo = new ScheduleRepository();
        $layout = $repo->GetLayout((int)$resource->ScheduleId, new ScheduleLayoutFactory($tz));
        $day = Date::Parse($dayYmd . ' 00:00:00', $tz);
        $periods = $layout->GetLayout($day, false);
        $begin = null;
        $end = null;
        foreach ($periods as $p) {
            if (!method_exists($p, 'IsReservable') || !$p->IsReservable() || $p->BeginDate() === null || $p->EndDate() === null) {
                continue;
            }
            $b = $p->BeginDate();
            $e = $p->EndDate();
            if ($begin === null || $b->LessThan($begin)) {
                $begin = $b;
            }
            if ($end === null || $e->GreaterThan($end)) {
                $end = $e;
            }
        }
        if ($begin === null || $end === null) {
            return ['begin' => '09:00', 'end' => '17:00', 'endNextDay' => false];
        }
        $beginStr = $begin->ToTimezone($tz)->Format('H:i');
        $endStr = $end->ToTimezone($tz)->Format('H:i');
        // Endet die letzte Periode um Mitternacht (Ganztags-Layout)? → Ende liegt am Folgetag.
        $endNextDay = ($endStr === '00:00');
        return ['begin' => $beginStr, 'end' => $endStr, 'endNextDay' => $endNextDay];
    }

    /**
     * Wochen-Raster (Mo–Fr × Stunden-Perioden) für den Slotmodus — wie studio.uni-bayreuth.de.
     * Jede Zelle: state free|busy|past. Der Nutzer wählt im Frontend eine zusammenhängende Spanne.
     * @return array{weekStart:string, prevWeek:string, nextWeek:string, thisWeek:string, weekLabel:string, hours:string[], days:array[]}
     */
    private function weekGrid(UserSession $user, $resource, string $aroundYmd, int $minNoticeSec): array
    {
        $tz = $user->Timezone;
        $repo = new ScheduleRepository();
        $layout = $repo->GetLayout((int)$resource->ScheduleId, new ScheduleLayoutFactory($tz));
        $earliest = $minNoticeSec > 0
            ? Date::Now()->ApplyDifference(TimeInterval::Parse($minNoticeSec)->Interval())
            : Date::Now();

        $todayYmd = Date::Now()->ToTimezone($tz)->Format('Y-m-d');
        $refYmd = ($aroundYmd < $todayYmd) ? $todayYmd : $aroundYmd;
        $ref = Date::Parse($refYmd . ' 00:00:00', $tz)->ToTimezone($tz);
        $dow = (int)$ref->Format('N');
        $weekStart = $ref->AddDays(-($dow - 1)); // Montag
        $mondayToday = (function () use ($tz) {
            $t = Date::Now()->ToTimezone($tz);
            return $t->AddDays(-((int)$t->Format('N') - 1));
        })();

        // 1. Pass: je Tag die reservierbaren Stunden-Perioden als Map h => {he,state} sammeln.
        // Verschiedene Wochentage (v. a. Sa/So) können andere/keine Öffnungszeiten haben — deshalb
        // bauen wir die Spaltenüberschriften aus der VEREINIGUNG aller Stunden, damit das Raster
        // ausgerichtet bleibt, und füllen fehlende Stunden je Tag als 'closed' (nicht buchbar).
        $rawDays = [];
        $hourSet = [];
        $poolIds = $this->poolResourceIds($user, $resource);
        for ($dn = 0; $dn < 7; $dn++) {
            $day = $weekStart->AddDays($dn);
            $periods = $layout->GetLayout($day, false);
            $byResource = $this->splitItemsByResource(
                (new ResourceAvailability(new ReservationViewRepository()))->GetItemsBetween($day, $day->AddDays(1), $poolIds)
            );
            $cellMap = [];
            foreach ($periods as $p) {
                if (!method_exists($p, 'IsReservable') || !$p->IsReservable() || $p->BeginDate() === null || $p->EndDate() === null) {
                    continue;
                }
                $b = $p->BeginDate();
                $e = $p->EndDate();
                if ($b->LessThan($earliest)) {
                    $state = 'past';
                } else {
                    // Slot frei, sobald irgendeine Pool-Einheit in dieser Periode frei ist.
                    $state = $this->poolWindowFree($byResource, $poolIds, $b, $e) ? 'free' : 'busy';
                }
                $h = $b->ToTimezone($tz)->Format('H:i');
                $cellMap[$h] = ['h' => $h, 'he' => $e->ToTimezone($tz)->Format('H:i'), 'state' => $state];
                $hourSet[$h] = true;
            }
            $local = $day->ToTimezone($tz);
            $rawDays[] = ['date' => $local->Format('Y-m-d'), 'label' => self::WD[(int)$local->Format('N')], 'dm' => $local->Format('d.m.'), 'weekend' => (int)$local->Format('N') >= 6, 'cellMap' => $cellMap];
        }

        // 2. Pass: kanonische Stunden-Spalten + je Tag Zellen daran ausrichten (fehlende = 'closed').
        $hours = array_keys($hourSet);
        sort($hours);
        $days = [];
        $hasWeekend = false;
        foreach ($rawDays as $rd) {
            $cells = [];
            $open = false;
            foreach ($hours as $h) {
                if (isset($rd['cellMap'][$h])) {
                    $cells[] = $rd['cellMap'][$h];
                    if ($rd['cellMap'][$h]['state'] === 'free' || $rd['cellMap'][$h]['state'] === 'busy') {
                        $open = true;
                    }
                } else {
                    $cells[] = ['h' => $h, 'he' => '', 'state' => 'closed'];
                }
            }
            if ($rd['weekend'] && $open) {
                $hasWeekend = true;
            }
            $days[] = ['date' => $rd['date'], 'label' => $rd['label'], 'dm' => $rd['dm'], 'cells' => $cells, 'weekend' => $rd['weekend']];
        }
        $wsLocal = $weekStart->ToTimezone($tz);
        $weLocal = $weekStart->AddDays(6)->ToTimezone($tz);
        return [
            'weekStart' => $wsLocal->Format('Y-m-d'),
            'prevWeek' => $weekStart->AddDays(-7)->ToTimezone($tz)->Format('Y-m-d'),
            'nextWeek' => $weekStart->AddDays(7)->ToTimezone($tz)->Format('Y-m-d'),
            'thisWeek' => $mondayToday->Format('Y-m-d'),
            'weekLabel' => $wsLocal->Format('d.m.') . ' – ' . $weLocal->Format('d.m.Y'),
            'hours' => $hours,
            'days' => $days,
            'hasWeekend' => $hasWeekend,
        ];
    }

    /**
     * Monats-Kalender (6 Wochen × Mo–So) für den Tagesmodus — gleiches Klick-Spanne-Modell wie das
     * Wochen-Raster, aber Zellen sind GANZE TAGE und beliebig weit in die Zukunft navigierbar.
     * Der angezeigte Monat ist der, der max(heute+Vorlauf, $aroundYmd) enthält.
     * Jede Zelle: {date:'Y-m-d', dom, inMonth, state: 'free'|'busy'|'past'|'closed', weekend}.
     * @return array{monthLabel:string, prevMonth:string, nextMonth:string, thisMonth:string, weeks:array[]}
     */
    /** Hat der Tag mindestens eine reservierbare Schedule-Periode? */
    private function dayHasReservablePeriod($layout, $day): bool
    {
        foreach ($layout->GetLayout($day, false) as $p) {
            if (method_exists($p, 'IsReservable') && $p->IsReservable() && $p->BeginDate() !== null && $p->EndDate() !== null) {
                return true;
            }
        }
        return false;
    }

    private function monthGrid(UserSession $user, $resource, string $aroundYmd, int $minNoticeSec): array
    {
        $tz = $user->Timezone;
        $rid = (int)$resource->GetId();

        // Frühester buchbarer Tag (heute + Vorlauf).
        $earliestYmd = Date::Now()->ToTimezone($tz)->Format('Y-m-d');
        if ($minNoticeSec > 0) {
            $e = Date::Now()->ApplyDifference(TimeInterval::Parse($minNoticeSec)->Interval())->ToTimezone($tz)->Format('Y-m-d');
            if ($e > $earliestYmd) {
                $earliestYmd = $e;
            }
        }

        // Angezeigter Monat = der, der max(earliest, aroundYmd) enthält.
        $refYmd = ($aroundYmd > $earliestYmd) ? $aroundYmd : $earliestYmd;
        $ref = Date::Parse($refYmd . ' 00:00:00', $tz)->ToTimezone($tz);
        $year = (int)$ref->Format('Y');
        $month = (int)$ref->Format('m');
        $firstOfMonth = Date::Parse(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);

        // Raster-Start = Montag der Woche, die den Monatsersten enthält.
        $dow = (int)$firstOfMonth->Format('N');
        $gridStart = $firstOfMonth->AddDays(-($dow - 1));

        // Sichtbares Fenster (42 Tage) einmal abfragen, je Einheit gruppieren, je Tag pool-weise testen.
        $poolIds = $this->poolResourceIds($user, $resource);
        $byResource = $this->splitItemsByResource(
            (new ResourceAvailability(new ReservationViewRepository()))->GetItemsBetween($gridStart, $gridStart->AddDays(42), $poolIds)
        );
        // Schedule-Layout für die Erkennung „geschlossener" Tage (keine reservierbare Periode).
        $layout = (new ScheduleRepository())->GetLayout((int)$resource->ScheduleId, new ScheduleLayoutFactory($tz));

        $weeks = [];
        for ($w = 0; $w < 6; $w++) {
            $row = [];
            for ($d = 0; $d < 7; $d++) {
                $day = $gridStart->AddDays($w * 7 + $d);
                $local = $day->ToTimezone($tz);
                $ymd = $local->Format('Y-m-d');
                $inMonth = ((int)$local->Format('n') === $month && (int)$local->Format('Y') === $year);
                $weekend = (int)$local->Format('N') >= 6;

                if ($ymd < $earliestYmd) {
                    $state = 'past';
                } elseif (!$this->dayHasReservablePeriod($layout, $day)) {
                    // Tag ohne buchbare Schedule-Periode → geschlossen (nicht wählbar), sonst lehnt
                    // die native SchedulePeriodRule eine Buchung später ohnehin ab.
                    $state = 'closed';
                } else {
                    $dayStart = $day;
                    $dayEnd = $day->AddDays(1);
                    // Tag frei, sobald irgendeine Pool-Einheit frei ist.
                    $state = $this->poolWindowFree($byResource, $poolIds, $dayStart, $dayEnd) ? 'free' : 'busy';
                }
                $row[] = ['date' => $ymd, 'dom' => (int)$local->Format('j'), 'inMonth' => $inMonth, 'state' => $state, 'weekend' => $weekend];
            }
            $weeks[] = $row;
        }

        $months = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        $thisMonth = Date::Now()->ToTimezone($tz)->Format('Y-m-01');
        return [
            'monthLabel' => $months[$month] . ' ' . $year,
            'prevMonth' => $firstOfMonth->AddDays(-1)->ToTimezone($tz)->Format('Y-m-01'),
            'nextMonth' => $firstOfMonth->AddDays(35)->ToTimezone($tz)->Format('Y-m-01'),
            'thisMonth' => $thisMonth,
            'weeks' => $weeks,
        ];
    }

    /**
     * Abhol-Slots (5-Minuten-Raster) nach Kalendertag (lokale Zeitzone) gruppieren — für den
     * kompakten Picker. Pro Tag: {date:'Y-m-d', label:'Mo 13.07.', slots:[{slot_id, timeLabel:'13:45',
     * memberName:'Anna Schmidt'}]}. memberName ist gesetzt, sobald mehrere Personen denselben
     * Termintyp anbieten (SPEC-MULTI-ANBIETER), damit der Picker sie unterscheidbar anzeigt.
     * @param array[] $slots aus fetchHandoverSlots() (jeweils mit start_utc/slot_id/member_name)
     * @return array[]
     */
    private function groupPickupByDay(array $slots, $tz): array
    {
        $byDay = [];
        foreach ($slots as $s) {
            $start = isset($s['start_utc']) ? Date::Parse($s['start_utc'], 'UTC')->ToTimezone($tz) : null;
            if ($start === null) {
                continue;
            }
            $ymd = $start->Format('Y-m-d');
            if (!isset($byDay[$ymd])) {
                $byDay[$ymd] = [
                    'date' => $ymd,
                    'label' => self::WD[(int)$start->Format('N')] . ' ' . $start->Format('d.m.'),
                    'slots' => [],
                ];
            }
            $byDay[$ymd]['slots'][] = [
                'slot_id' => (string)$s['slot_id'],
                'timeLabel' => $start->Format('H:i'),
                'memberName' => !empty($s['member_name']) ? (string)$s['member_name'] : null,
            ];
        }
        ksort($byDay);
        return array_values($byDay);
    }

    // --- Terminplaner-Anbindung (Einführungs-Slots) + F40-Zertifikat ---

    private function tpConfig(): array
    {
        $f = ROOT_DIR . 'config/zhl-handover.php';
        if (!is_readable($f)) {
            $f = ROOT_DIR . 'config/zhl-handover.example.php';
        }
        $c = is_readable($f) ? (require $f) : [];
        return is_array($c) ? $c : [];
    }

    /**
     * Termintyp-Label(s) für Abholung/Rückgabe. Einzelne Personen benennen ihren Termintyp auf
     * meet.zhl-ubt.de uneinheitlich (Nutzer 2026-07-13: „Medienübergabe", „Abholung/Abgabe" statt
     * „Übergabe Medien") — bis alle vereinheitlicht haben, fragen wir alle bekannten Varianten ab und
     * führen die Ergebnisse zusammen (fetchHandoverSlots/fetchReturnSlots). config/zhl-handover.php kann
     * die Liste über 'handover_type_labels' (Array) vollständig überschreiben.
     * @return string[]
     */
    private function handoverTypeLabels(): array
    {
        $c = $this->tpConfig();
        if (!empty($c['handover_type_labels']) && is_array($c['handover_type_labels'])) {
            $labels = array_values(array_filter(array_map('strval', $c['handover_type_labels']), fn($l) => $l !== ''));
            if ($labels) {
                return $labels;
            }
        }
        $defaults = ['Übergabe Medien', 'Medienübergabe', 'Abholung/Abgabe'];
        $legacy = !empty($c['handover_type_label']) ? (string)$c['handover_type_label'] : null;
        if ($legacy !== null && !in_array($legacy, $defaults, true)) {
            $defaults[] = $legacy;
        }
        return $defaults;
    }

    /** Hauspost-Konfiguration (Empfänger + Vorlauf) mit Fallback auf die .example-Datei. */
    private function hauspostConfig(): array
    {
        $f = ROOT_DIR . 'config/zhl-hauspost.php';
        if (!is_readable($f)) {
            $f = ROOT_DIR . 'config/zhl-hauspost.example.php';
        }
        $c = is_readable($f) ? (require $f) : [];
        return is_array($c) ? $c : [];
    }

    /**
     * Best-effort-Datum aus dem Freitext-Feld einsatz_termin (kann ein Zeitraum sein).
     * Erkennt das ERSTE Vorkommen von TT.MM.JJJJ / TT.MM.JJ / JJJJ-MM-TT. Gibt 'Y-m-d' oder null.
     * Limitation: relative/umgangssprachliche Angaben („nächste Woche") werden NICHT geparst →
     * dann greift die Vorlauf-Prüfung bewusst nicht (weiche Prüfung, dokumentiert).
     */
    private function parseLooseDate(string $text, $tz): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        // TT.MM.JJJJ oder TT.MM.JJ
        if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4}|\d{2})/', $text, $m)) {
            $y = (int)$m[3];
            if ($y < 100) {
                $y += 2000;
            }
            $mo = (int)$m[2];
            $d = (int)$m[1];
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }
        // JJJJ-MM-TT
        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $text, $m)) {
            $y = (int)$m[1];
            $mo = (int)$m[2];
            $d = (int)$m[3];
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }
        return null;
    }

    /** Frühestes Datum (Y-m-d), das $workDays Werktage (Mo–Fr) nach $fromYmd liegt. */
    private function addWorkingDays(string $fromYmd, int $workDays, $tz): string
    {
        $d = Date::Parse($fromYmd . ' 00:00:00', $tz);
        $added = 0;
        while ($added < $workDays) {
            $d = $d->AddDays(1);
            $dow = (int)$d->Format('N'); // 1=Mo … 7=So
            if ($dow <= 5) {
                $added++;
            }
        }
        return $d->Format('Y-m-d');
    }

    /**
     * D2: Begleit-Mail mit Info-Material zu den gebuchten Medien an den Ausleihenden.
     * Nur wenn der Nutzer eine Mail-Adresse hat UND mindestens ein gebuchter Geräte-Typ einen
     * Info-Link gepflegt hat. Kapselt alles in try/catch — die Buchung darf nie kippen.
     */
    private function sendMediaInfoMail($db, UserSession $user, string $ref): void
    {
        try {
            if (trim((string)$user->Email) === '') {
                return;
            }
            $infos = ZhlTypeInfo::ForReference($db, $ref);
            if (empty(ZhlTypeInfo::DedupByType($infos))) {
                return; // kein Info-Link vorhanden → keine Begleit-Mail
            }
            $name = trim($user->FirstName . ' ' . $user->LastName);
            $intro = 'vielen Dank für deine Buchung! Hier sind die passenden Anleitungen zu deinen Medien.';
            $to = [new EmailAddress($user->Email, $name !== '' ? $name : $user->Email)];
            $lang = !empty($user->LanguageCode) ? $user->LanguageCode : null;
            ServiceLocator::GetEmailService()->Send(new ZhlMediaInfoEmail($to, $ref, $name, $intro, $infos, $lang));
        } catch (Throwable $e) {
            Log::Error('ZHL-D2: Info-Mail nach Buchung fehlgeschlagen (ref=%s): %s', $ref, $e);
        }
    }

    /**
     * Hauspost-Antrag speichern (status pending_review) + EINE Klartext-Mail an Transport + Medien (To)
     * und den Ausleihenden (CC). Wirft Exceptions an den Aufrufer hoch (dort in try/catch gekapselt,
     * damit eine bereits gespeicherte Buchung nie kippt).
     */
    private function persistAndNotifyHauspost($db, UserSession $user, int $rid, string $referenceNumber, string $device, array $hp): void
    {
        $cfg = $this->hauspostConfig();

        $ins = new AdHocCommand(
            'INSERT INTO zhl_hauspost_request (reference_number, resource_id, user_id, einsatz_termin, einsatz_raum, einsatz_titel, liefer_fenster, rueckhol_fenster, anlieferort, abholort_hauspost, status, created_at) ' .
            'VALUES (@ref,@rid,@uid,@termin,@raum,@titel,@liefer,@rueck,@anlief,@abhol,@status,@now)'
        );
        $ins->AddParameter(new Parameter('@ref', $referenceNumber !== '' ? $referenceNumber : null));
        $ins->AddParameter(new Parameter('@rid', $rid));
        $ins->AddParameter(new Parameter('@uid', (int)$user->UserId));
        $ins->AddParameter(new Parameter('@termin', $hp['einsatz_termin']));
        $ins->AddParameter(new Parameter('@raum', $hp['einsatz_raum']));
        $ins->AddParameter(new Parameter('@titel', $hp['einsatz_titel']));
        $ins->AddParameter(new Parameter('@liefer', $hp['liefer_fenster']));
        $ins->AddParameter(new Parameter('@rueck', $hp['rueckhol_fenster']));
        $ins->AddParameter(new Parameter('@anlief', $hp['anlieferort']));
        $ins->AddParameter(new Parameter('@abhol', $hp['abholort_hauspost']));
        $ins->AddParameter(new Parameter('@status', 'pending_review'));
        $ins->AddParameter(new Parameter('@now', gmdate('Y-m-d H:i:s')));
        $db->Execute($ins);

        zhl_audit_log(array_merge(zhl_audit_actor($user), [
            'action' => 'hauspost.request',
            'entity_type' => 'resource',
            'entity_id' => (string)$rid,
            'reference_number' => $referenceNumber,
            'detail' => ['device' => $device, 'einsatz' => $hp['einsatz_titel'], 'status' => 'pending_review'],
        ]));

        // --- Klartext-Mail ---
        $transportEmail = trim((string)($cfg['transport_email'] ?? ''));
        $medienEmail = trim((string)($cfg['medien_email'] ?? ''));
        $borrowerName = trim($user->FirstName . ' ' . $user->LastName);

        $to = [];
        if ($transportEmail !== '') {
            $to[] = new EmailAddress($transportEmail, 'Transport (Hauspost)');
        }
        if ($medienEmail !== '') {
            $to[] = new EmailAddress($medienEmail, 'ZHL Medien');
        }
        if (empty($to)) {
            Log::Error('ZHL-Hauspost: keine Empfänger konfiguriert (transport_email/medien_email) — Mail übersprungen (ref=%s).', $referenceNumber);
            return;
        }
        $cc = [];
        if (trim((string)$user->Email) !== '') {
            $cc[] = new EmailAddress($user->Email, $borrowerName);
        }

        $lines = [
            'Es liegt ein neuer Hauspost-Versand-Antrag für analoge Medien vor.',
            '',
            'Dieser Antrag MUSS vom Medienmanager geprüft und freigegeben werden, bevor das Material',
            'bereitgestellt und per Hauspost verschickt wird. Den Transport organisiert Stefan Bauernschmitt.',
            '',
            'Buchungsnummer: ' . ($referenceNumber !== '' ? $referenceNumber : '(folgt)'),
            'Gerät/Medium:   ' . $device,
            'Ausleihende(r): ' . $borrowerName . ' <' . $user->Email . '>',
            'Status:         pending_review',
            '',
            'Angaben aus dem Formular:',
            ' a) Termin des Einsatzes:        ' . $hp['einsatz_termin'],
            ' b) Raum des Einsatzorts:        ' . $hp['einsatz_raum'],
            ' c) Titel/Name des Einsatzes:    ' . $hp['einsatz_titel'],
            ' d) Anlieferung möglich:         ' . $hp['liefer_fenster'],
            ' e) Rückholung möglich:          ' . $hp['rueckhol_fenster'],
            ' f1) Anlieferungsort:            ' . $hp['anlieferort'],
            ' f2) Abholungsort:               ' . $hp['abholort_hauspost'],
            '',
            'ZHL Medienausleihe',
        ];
        $body = implode("\n", $lines);

        $lang = !empty($user->LanguageCode) ? $user->LanguageCode : null;
        ServiceLocator::GetEmailService()->Send(new ZhlHauspostEmail($to, $cc, $hp['einsatz_titel'], $body, $lang));
    }

    /** GET/POST an den Terminplaner (X-API-Key). @return array|null dekodiertes JSON */
    private function tpRequest(string $method, string $path, array $query = [], ?array $body = null): ?array
    {
        $c = $this->tpConfig();
        $base = rtrim((string)($c['terminplaner_base_url'] ?? ''), '/');
        $key = (string)($c['terminplaner_api_key'] ?? '');
        $timeout = (int)($c['http_timeout'] ?? 6);
        if ($base === '' || $key === '' || $key === 'REPLACE_WITH_CROSSBOOK_API_KEY') {
            return null;
        }
        $url = $base . $path . ($query ? ('?' . http_build_query($query)) : '');
        $header = "X-API-Key: $key\r\nUser-Agent: zhl-book/1\r\n";
        $opt = ['method' => $method, 'timeout' => $timeout, 'ignore_errors' => true];
        if ($body !== null) {
            $header = "X-API-Key: $key\r\nContent-Type: application/json\r\nUser-Agent: zhl-book/1\r\n";
            $opt['content'] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }
        $opt['header'] = $header;
        $raw = @file_get_contents($url, false, stream_context_create(['http' => $opt]));
        if ($raw === false) {
            return null;
        }
        $d = json_decode($raw, true);
        return is_array($d) ? $d : null;
    }

    /**
     * Einführungs-Slots aus dem Terminplaner, gefiltert auf Termine VOR dem Ausleihstart.
     * @return array{slots: array[], earliestLabel: ?string, typeId: ?int, memberId: ?int}
     */
    private function fetchEinfuehrungSlots(?string $typLabel, ?int $memberId, ?string $loanStartUtc, $tz): array
    {
        $out = ['slots' => [], 'earliestLabel' => null, 'typeId' => null, 'memberId' => null];
        if ($typLabel === null || $typLabel === '') {
            return $out;
        }
        $q = ['type_label' => $typLabel];
        if ($memberId) {
            $q['member_id'] = $memberId;
        }
        $resp = $this->tpRequest('GET', '/api/lesson_slots.php', $q);
        if (!$resp || empty($resp['members'])) {
            return $out;
        }
        $earliest = null;
        foreach ($resp['members'] as $mem) {
            $out['typeId'] = $out['typeId'] ?? (isset($mem['type_id']) ? (int)$mem['type_id'] : null);
            $out['memberId'] = $out['memberId'] ?? (isset($mem['member_id']) ? (int)$mem['member_id'] : null);
            foreach ($mem['slots'] ?? [] as $s) {
                $startUtc = $s['start_utc'] ?? null;
                $endUtc = $s['end_utc'] ?? null;
                if (!$startUtc) {
                    continue;
                }
                if ($earliest === null || $startUtc < $earliest) {
                    $earliest = $startUtc;
                }
                if ($loanStartUtc !== null && $startUtc >= $loanStartUtc) {
                    continue; // Einführung muss VOR der Nutzung liegen
                }
                $out['slots'][] = [
                    'slot_id' => (string)$s['slot_id'],
                    'label' => (string)($s['label'] ?? $startUtc),
                    // start_utc/end_utc mitführen — im „Zusammen"-Modus ist der Einführungs-Slot
                    // zugleich der Übergabetermin (persistHandover braucht echte Zeiten).
                    'start_utc' => (string)$startUtc,
                    'end_utc' => $endUtc !== null ? (string)$endUtc : null,
                    'type_id' => isset($mem['type_id']) ? (int)$mem['type_id'] : null,
                    'member_id' => isset($mem['member_id']) ? (int)$mem['member_id'] : null,
                    // Mehrere Personen können denselben Termintyp anbieten (SPEC-MULTI-ANBIETER) —
                    // Name mitführen, damit der Picker die Anbieter unterscheidbar anzeigen kann.
                    'member_name' => isset($mem['member_name']) ? (string)$mem['member_name'] : null,
                ];
            }
        }
        if ($earliest !== null) {
            $out['earliestLabel'] = Date::Parse($earliest, 'UTC')->ToTimezone($tz)->Format('d.m.Y, H:i') . ' Uhr';
        }
        return $out;
    }

    /**
     * Abhol-Slots aus dem Terminplaner („Übergabe Medien", type_id 35), gefiltert auf Termine,
     * deren ENDE <= Ausleihstart liegt (die Abholung muss vor/bei der Nutzung abgeschlossen sein).
     * Gleiche Response-Form wie fetchEinfuehrungSlots; behält zusätzlich start_utc/end_utc je Slot,
     * damit HandlePost den gewählten Slot serverseitig kanonisch auflösen kann.
     * @return array{slots: array[], earliestLabel: ?string, typeId: ?int, memberId: ?int}
     */
    private function fetchHandoverSlots(array $typeLabels, ?int $memberId, ?string $loanStartUtc, $tz): array
    {
        $out = ['slots' => [], 'earliestLabel' => null, 'typeId' => null, 'memberId' => null];
        $typeLabels = array_values(array_unique(array_filter(array_map('strval', $typeLabels), fn($l) => $l !== '')));
        if (!$typeLabels) {
            return $out;
        }
        $earliest = null;
        foreach ($typeLabels as $typeLabel) {
            $q = ['type_label' => $typeLabel];
            if ($memberId) {
                $q['member_id'] = $memberId;
            }
            $resp = $this->tpRequest('GET', '/api/lesson_slots.php', $q);
            if (!$resp || empty($resp['members'])) {
                continue;
            }
            foreach ($resp['members'] as $mem) {
                $out['typeId'] = $out['typeId'] ?? (isset($mem['type_id']) ? (int)$mem['type_id'] : null);
                $out['memberId'] = $out['memberId'] ?? (isset($mem['member_id']) ? (int)$mem['member_id'] : null);
                foreach ($mem['slots'] ?? [] as $s) {
                    $startUtc = $s['start_utc'] ?? null;
                    $endUtc = $s['end_utc'] ?? null;
                    if (!$startUtc) {
                        continue;
                    }
                    if ($earliest === null || $startUtc < $earliest) {
                        $earliest = $startUtc;
                    }
                    // Abholung muss VOR/BEI der Nutzung abgeschlossen sein: Slot-Ende <= Ausleihstart.
                    if ($loanStartUtc !== null && $endUtc !== null && $endUtc > $loanStartUtc) {
                        continue;
                    }
                    // Fallback (kein end_utc geliefert): am Start filtern.
                    if ($loanStartUtc !== null && $endUtc === null && $startUtc >= $loanStartUtc) {
                        continue;
                    }
                    $out['slots'][] = [
                        'slot_id' => (string)$s['slot_id'],
                        'label' => (string)($s['label'] ?? $startUtc),
                        'start_utc' => (string)$startUtc,
                        'end_utc' => $endUtc !== null ? (string)$endUtc : null,
                        'type_id' => isset($mem['type_id']) ? (int)$mem['type_id'] : null,
                        'member_id' => isset($mem['member_id']) ? (int)$mem['member_id'] : null,
                        'member_name' => isset($mem['member_name']) ? (string)$mem['member_name'] : null,
                    ];
                }
            }
        }
        if ($earliest !== null) {
            $out['earliestLabel'] = Date::Parse($earliest, 'UTC')->ToTimezone($tz)->Format('d.m.Y, H:i') . ' Uhr';
        }
        return $out;
    }

    /**
     * Rückgabe-Slots aus dem Terminplaner (SPEC-RUECKGABE) — selber Typ wie die Abholung
     * („Übergabe Medien"), aber Filter auf den Slot-START: Slot zeigen, wenn `startUtc >= $floorUtc`
     * (Rückgabezeit = Slot-Start; `end_utc` nur Persistenz/Anzeige). $floorUtc = returnFloorUtc().
     * Gleiche Response-Form wie fetchHandoverSlots.
     * @return array{slots: array[], earliestLabel: ?string, typeId: ?int, memberId: ?int}
     */
    private function fetchReturnSlots(array $typeLabels, ?int $memberId, ?string $floorUtc, $tz): array
    {
        $out = ['slots' => [], 'earliestLabel' => null, 'typeId' => null, 'memberId' => null];
        $typeLabels = array_values(array_unique(array_filter(array_map('strval', $typeLabels), fn($l) => $l !== '')));
        if (!$typeLabels) {
            return $out;
        }
        $earliest = null;
        foreach ($typeLabels as $typeLabel) {
            $q = ['type_label' => $typeLabel];
            if ($memberId) {
                $q['member_id'] = $memberId;
            }
            $resp = $this->tpRequest('GET', '/api/lesson_slots.php', $q);
            if (!$resp || empty($resp['members'])) {
                continue;
            }
            foreach ($resp['members'] as $mem) {
                $out['typeId'] = $out['typeId'] ?? (isset($mem['type_id']) ? (int)$mem['type_id'] : null);
                $out['memberId'] = $out['memberId'] ?? (isset($mem['member_id']) ? (int)$mem['member_id'] : null);
                foreach ($mem['slots'] ?? [] as $s) {
                    $startUtc = $s['start_utc'] ?? null;
                    $endUtc = $s['end_utc'] ?? null;
                    if (!$startUtc) {
                        continue;
                    }
                    // Rückgabe darf nicht VOR dem Floor (Endtag-Anfang bzw. Ausleihstart) liegen.
                    if ($floorUtc !== null && strcmp((string)$startUtc, $floorUtc) < 0) {
                        continue;
                    }
                    if ($earliest === null || $startUtc < $earliest) {
                        $earliest = $startUtc;
                    }
                    $out['slots'][] = [
                        'slot_id' => (string)$s['slot_id'],
                        'label' => (string)($s['label'] ?? $startUtc),
                        'start_utc' => (string)$startUtc,
                        'end_utc' => $endUtc !== null ? (string)$endUtc : null,
                        'type_id' => isset($mem['type_id']) ? (int)$mem['type_id'] : null,
                        'member_id' => isset($mem['member_id']) ? (int)$mem['member_id'] : null,
                        'member_name' => isset($mem['member_name']) ? (string)$mem['member_name'] : null,
                    ];
                }
            }
        }
        if ($earliest !== null) {
            $out['earliestLabel'] = Date::Parse($earliest, 'UTC')->ToTimezone($tz)->Format('d.m.Y, H:i') . ' Uhr';
        }
        return $out;
    }

    /**
     * Nach gebuchter Einführung: offene Bestätigung anlegen (Einweiser bestätigt später → Zertifikat).
     * Mappt das Gerät auf den passenden Zertifikatstyp; mailt den Bestätigungs-Link, falls am Typ
     * eine confirm_email hinterlegt ist.
     */
    private function createCertConfirmation($db, int $userId, int $rid): void
    {
        $cmd = new AdHocCommand('SELECT t.id, t.name, t.confirm_email FROM zhl_cert_type_resource ctr JOIN zhl_cert_type t ON t.id = ctr.cert_type_id WHERE ctr.resource_id = @r AND t.active = 1 ORDER BY t.id LIMIT 1');
        $cmd->AddParameter(new Parameter('@r', $rid));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        if (!$row) {
            return;
        }
        $ctid = (int)$row['id'];

        $chk = new AdHocCommand('SELECT token FROM zhl_cert_confirmation WHERE user_id = @u AND cert_type_id = @t AND status = @s LIMIT 1');
        $chk->AddParameter(new Parameter('@u', $userId));
        $chk->AddParameter(new Parameter('@t', $ctid));
        $chk->AddParameter(new Parameter('@s', 'pending'));
        $r2 = $db->Query($chk);
        $exists = $r2->GetRow();
        $r2->Free();

        if ($exists) {
            $token = (string)$exists['token'];
        } else {
            $token = bin2hex(random_bytes(16));
            $ins = new AdHocCommand('INSERT INTO zhl_cert_confirmation (token, user_id, cert_type_id, resource_id, status, created_at) VALUES (@tok,@u,@t,@r,@s,@now)');
            $ins->AddParameter(new Parameter('@tok', $token));
            $ins->AddParameter(new Parameter('@u', $userId));
            $ins->AddParameter(new Parameter('@t', $ctid));
            $ins->AddParameter(new Parameter('@r', $rid));
            $ins->AddParameter(new Parameter('@s', 'pending'));
            $ins->AddParameter(new Parameter('@now', gmdate('Y-m-d H:i:s')));
            $db->Execute($ins);
        }

        $confirmEmail = trim((string)($row['confirm_email'] ?? ''));
        if ($confirmEmail !== '') {
            $this->sendConfirmationMail($confirmEmail, (string)$row['name'], $token);
        }
    }

    private function sendConfirmationMail(string $to, string $certName, string $token): void
    {
        try {
            $auto = ROOT_DIR . 'vendor/autoload.php';
            if (!is_readable($auto)) {
                return;
            }
            require_once($auto);
            $cls = 'PHPMailer\\PHPMailer\\PHPMailer';
            if (!class_exists($cls)) {
                return;
            }
            $conf = include(ROOT_DIR . 'config/config.php');
            $p = $conf['settings']['phpmailer'];
            $em = $conf['settings']['email'];
            $host = $_SERVER['HTTP_HOST'] ?? 'media.zhl-ubt.de';
            $link = 'https://' . $host . '/Web/zhl-cert-confirm.php?t=' . $token;
            $mail = new $cls(false);
            $mail->isSMTP();
            $mail->Host = $p['smtp.host'];
            $mail->Port = (int)$p['smtp.port'];
            $mail->SMTPAuth = true;
            $mail->Username = $p['smtp.username'];
            $mail->Password = $p['smtp.password'];
            $mail->SMTPSecure = 'tls';
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($em['default.from.address'], $em['default.from.name']);
            $mail->addAddress($to);
            $mail->Subject = 'Einführung bestätigen: ' . $certName;
            $mail->isHTML(true);
            $mail->Body = '<p>Eine Einführung wurde gebucht. Bitte bestätige <em>nach</em> dem Termin, ob die Person die Einführung „' . htmlspecialchars($certName) . '" erfolgreich absolviert hat:</p>'
                . '<p><a href="' . htmlspecialchars($link) . '" style="display:inline-block;background:#009260;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;">Einführung bestätigen</a></p>'
                . '<p style="color:#888;font-size:13px;">Mit „Ja" erhält die Person automatisch das passende Zertifikat und kann das Material selbstständig buchen.</p>';
            $mail->send();
        } catch (Throwable $e) {
            Log::Error('ZHL cert-confirm mail: %s', $e->getMessage());
        }
    }

    /**
     * Übergabe-Token + pickup/return-Zeilen anlegen (C1). Spiegelt den AdHocCommand/Parameter-Stil
     * von createCertConfirmation. Keine DB-Transaktion verfügbar (Database::Execute connectet+
     * disconnectet je Aufruf → START TRANSACTION überlebt nicht), daher sequenzielle Inserts mit
     * Fehlerbehandlung statt stillem Schlucken.
     * @return bool true, wenn alle drei Zeilen geschrieben wurden.
     */
    private function persistHandover($db, string $token, int $userId, int $rid, ?string $bookingId, int $staffMemberId, ?string $pickupStartUtc, ?string $pickupEndUtc, string $loanEndUtc, ?array $returnSlot = null): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        try {
            // 1. Token an den User binden (Auth-Bindung des Gates).
            $insTok = new AdHocCommand('INSERT INTO zhl_handover_token (handover_token, user_id, created_at) VALUES (@tok,@u,@now)');
            $insTok->AddParameter(new Parameter('@tok', $token));
            $insTok->AddParameter(new Parameter('@u', $userId));
            $insTok->AddParameter(new Parameter('@now', $now));
            $db->Execute($insTok);

            // 2. PICKUP-Zeile — nur, wenn ein Abholtermin existiert (bei reiner Rückgabe entfällt sie).
            if ($pickupStartUtc !== null) {
                $insPickup = new AdHocCommand('INSERT INTO zhl_booking_handover (handover_token, type, resource_id, terminplaner_booking_id, staff_member_id, staff_role, scheduled_start_utc, scheduled_end_utc, status, created_at, updated_at) VALUES (@tok,@type,@rid,@bid,@staff,@role,@start,@end,@status,@now,@now)');
                $insPickup->AddParameter(new Parameter('@tok', $token));
                $insPickup->AddParameter(new Parameter('@type', 'pickup'));
                $insPickup->AddParameter(new Parameter('@rid', $rid));
                $insPickup->AddParameter(new Parameter('@bid', $bookingId));
                $insPickup->AddParameter(new Parameter('@staff', $staffMemberId > 0 ? $staffMemberId : null));
                $insPickup->AddParameter(new Parameter('@role', 'primary'));
                $insPickup->AddParameter(new Parameter('@start', $pickupStartUtc));
                $insPickup->AddParameter(new Parameter('@end', $pickupEndUtc));
                $insPickup->AddParameter(new Parameter('@status', 'confirmed'));
                $insPickup->AddParameter(new Parameter('@now', $now));
                $db->Execute($insPickup);
            }

            // 3. RETURN-Zeile. Mit gewähltem Rückgabe-Slot (SPEC-RUECKGABE) → echte Slot-Zeiten +
            //    Terminplaner-Buchung + Staff-Member; sonst Fallback = Ausleihende (kein eigener Slot).
            $rStart = $returnSlot !== null ? (string)$returnSlot['start_utc'] : $loanEndUtc;
            $rEnd = $returnSlot !== null ? (string)($returnSlot['end_utc'] ?? $returnSlot['start_utc']) : $loanEndUtc;
            $rBid = $returnSlot !== null ? ($returnSlot['booking_id'] ?? null) : null;
            $rStaff = ($returnSlot !== null && (int)($returnSlot['member_id'] ?? 0) > 0) ? (int)$returnSlot['member_id'] : null;
            $insReturn = new AdHocCommand('INSERT INTO zhl_booking_handover (handover_token, type, resource_id, terminplaner_booking_id, staff_member_id, staff_role, scheduled_start_utc, scheduled_end_utc, status, created_at, updated_at) VALUES (@tok,@type,@rid,@bid,@staff,@role,@start,@end,@status,@now,@now)');
            $insReturn->AddParameter(new Parameter('@tok', $token));
            $insReturn->AddParameter(new Parameter('@type', 'return'));
            $insReturn->AddParameter(new Parameter('@rid', $rid));
            $insReturn->AddParameter(new Parameter('@bid', $rBid));
            $insReturn->AddParameter(new Parameter('@staff', $rStaff));
            $insReturn->AddParameter(new Parameter('@role', $rStaff !== null ? 'primary' : null));
            $insReturn->AddParameter(new Parameter('@start', $rStart));
            $insReturn->AddParameter(new Parameter('@end', $rEnd));
            $insReturn->AddParameter(new Parameter('@status', 'confirmed'));
            $insReturn->AddParameter(new Parameter('@now', $now));
            $db->Execute($insReturn);

            return true;
        } catch (Exception $e) {
            Log::Error('ZHL-Abholung persistHandover fehlgeschlagen (token=%s): %s', $token, $e);
            // Teil-Persistenz aufräumen (keine Transaktion über Connect/Disconnect möglich).
            try {
                $del = new AdHocCommand('DELETE FROM zhl_booking_handover WHERE handover_token = @tok');
                $del->AddParameter(new Parameter('@tok', $token));
                $db->Execute($del);
                $delTok = new AdHocCommand('DELETE FROM zhl_handover_token WHERE handover_token = @tok');
                $delTok->AddParameter(new Parameter('@tok', $token));
                $db->Execute($delTok);
            } catch (Exception $e2) {
                Log::Error('ZHL-Abholung Cleanup nach Teil-Persistenz fehlgeschlagen (token=%s): %s', $token, $e2);
            }
            return false;
        }
    }

    /**
     * Einführungstermin lokal speichern (type='einf' in zhl_booking_handover), damit er je
     * Gerät auf der Detailseite mit Datum erscheint. Eigener Token je Zeile; reference_number
     * liegt hier schon vor (Phase C nach erfolgreichem Save). Best effort: ein Fehler beim
     * Speichern darf die bereits gebuchte Ausleihe + den gebuchten Slot nicht kippen.
     */
    private function persistEinfuehrung($db, string $ref, int $rid, int $staffMemberId, ?string $bookingId, ?string $startUtc, ?string $endUtc): void
    {
        if ($startUtc === null) {
            return;
        }
        try {
            $now = gmdate('Y-m-d H:i:s');
            $token = bin2hex(random_bytes(16));
            $ins = new AdHocCommand('INSERT INTO zhl_booking_handover (handover_token, type, reference_number, resource_id, terminplaner_booking_id, staff_member_id, staff_role, scheduled_start_utc, scheduled_end_utc, status, created_at, updated_at) VALUES (@tok,@type,@ref,@rid,@bid,@staff,@role,@start,@end,@status,@now,@now)');
            $ins->AddParameter(new Parameter('@tok', $token));
            $ins->AddParameter(new Parameter('@type', 'einf'));
            $ins->AddParameter(new Parameter('@ref', $ref));
            $ins->AddParameter(new Parameter('@rid', $rid));
            $ins->AddParameter(new Parameter('@bid', $bookingId));
            $ins->AddParameter(new Parameter('@staff', $staffMemberId > 0 ? $staffMemberId : null));
            $ins->AddParameter(new Parameter('@role', 'primary'));
            $ins->AddParameter(new Parameter('@start', $startUtc));
            $ins->AddParameter(new Parameter('@end', $endUtc ?? $startUtc));
            $ins->AddParameter(new Parameter('@status', 'confirmed'));
            $ins->AddParameter(new Parameter('@now', $now));
            $db->Execute($ins);
        } catch (Exception $e) {
            Log::Error('ZHL-Einführung persistEinfuehrung fehlgeschlagen (ref=%s, res=%s): %s', $ref, $rid, $e);
        }
    }

    /** Nach erfolgreicher Reservierung: reference_number an Token + Übergabe-Zeilen nachtragen. */
    private function backfillHandoverReference($db, string $token, string $referenceNumber): void
    {
        if ($referenceNumber === '') {
            return;
        }
        try {
            $u1 = new AdHocCommand('UPDATE zhl_booking_handover SET reference_number = @ref, updated_at = @now WHERE handover_token = @tok');
            $u1->AddParameter(new Parameter('@ref', $referenceNumber));
            $u1->AddParameter(new Parameter('@now', gmdate('Y-m-d H:i:s')));
            $u1->AddParameter(new Parameter('@tok', $token));
            $db->Execute($u1);
            $u2 = new AdHocCommand('UPDATE zhl_handover_token SET reference_number = @ref WHERE handover_token = @tok');
            $u2->AddParameter(new Parameter('@ref', $referenceNumber));
            $u2->AddParameter(new Parameter('@tok', $token));
            $db->Execute($u2);
        } catch (Exception $e) {
            Log::Error('ZHL-Abholung backfillHandoverReference fehlgeschlagen (token=%s, ref=%s): %s', $token, $referenceNumber, $e);
        }
    }

    /** custom_attribute_id des Reservierungs-Attributs 'handover_token' (Kategorie 1), oder null. */
    private function resolveHandoverTokenAttributeId($db): ?int
    {
        $cmd = new AdHocCommand('SELECT custom_attribute_id FROM custom_attributes WHERE display_label = @l AND attribute_category = @c LIMIT 1');
        $cmd->AddParameter(new Parameter('@l', 'handover_token'));
        $cmd->AddParameter(new Parameter('@c', 1));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row ? (int)$row['custom_attribute_id'] : null;
    }

    /** F40: hat der Nutzer ein gültiges Einführungs-Zertifikat für dieses Gerät? */
    private function userIsCertified($db, $userId, int $rid): bool
    {
        $cmd = new AdHocCommand('SELECT 1 FROM zhl_certificate WHERE user_id = @u AND resource_id = @r AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP()) LIMIT 1');
        $cmd->AddParameter(new Parameter('@u', $userId));
        $cmd->AddParameter(new Parameter('@r', $rid));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return (bool)$row;
    }

    private function lookupType($db, int $rid): ?string
    {
        $cmd = new AdHocCommand(
            'SELECT v.attribute_value AS t FROM custom_attribute_values v ' .
            'JOIN custom_attributes a ON a.custom_attribute_id = v.custom_attribute_id ' .
            'WHERE a.display_label = @l AND a.attribute_category = 4 AND v.entity_id = @e LIMIT 1'
        );
        $cmd->AddParameter(new Parameter('@l', 'Geräte-Typ'));
        $cmd->AddParameter(new Parameter('@e', $rid));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        $t = $row ? trim((string)$row['t']) : '';
        return $t !== '' ? $t : null;
    }

    private function lookupScheduleName($db, int $scheduleId): string
    {
        $cmd = new AdHocCommand('SELECT name FROM schedules WHERE schedule_id = @s LIMIT 1');
        $cmd->AddParameter(new Parameter('@s', $scheduleId));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row ? (string)$row['name'] : '';
    }

    /** Übergabe-Konfiguration des Geräts (zhl_uebergabe) mit Defaults, falls nicht gepflegt. */
    /**
     * Übergabe-Modus: braucht das Gerät eine persönliche Abholung (Terminplaner-Slot)?
     * Entspannte Modi ('ablageort' = am Ablageort abholen, 'nicht_noetig') brauchen KEINEN
     * Abholtermin; alles andere (inkl. Default/Legacy 'abholen', 'abholen_persoenlich') schon.
     * Sichere Vorgabe (ZHL-Entscheidung 2026-06-26): unkonfigurierte Geräte = Abholung Pflicht.
     */
    private function pickupApplies(array $ueb): bool
    {
        return !in_array($ueb['abholung'], ['ablageort', 'nicht_noetig'], true);
    }

    /** Persönliche Rückgabe = Pflicht-Rückgabetermin (SPEC-RUECKGABE). Nur der persönliche Fall braucht einen Slot. */
    private function returnApplies(array $ueb): bool
    {
        return ($ueb['rueckgabe'] ?? 'abgeben') === 'abgeben_persoenlich';
    }

    /**
     * Floor für Rückgabe-Slots (SPEC-RUECKGABE): Slot-START muss >= diesem Wert liegen.
     * = max(Tagesanfang Endtag, Ausleihstart-Anker). Multi-Day → Endtag-00:00 gewinnt
     * (Same-Day-Rückgabe am Endtag, AK-8); Same-Day (begin==end) → Start-Anker gewinnt
     * (kein Rückgabe-Slot VOR der Abholung/Nutzung). Rückgabe je UTC 'Y-m-d H:i:s'.
     */
    private function returnFloorUtc(string $beginDate, string $beginTime, string $endDate, $tz): string
    {
        $startAnchor = Date::Parse($beginDate . ' ' . $beginTime, $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
        $endDayStart = Date::Parse($endDate . ' 00:00:00', $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
        return strcmp($startAnchor, $endDayStart) >= 0 ? $startAnchor : $endDayStart;
    }

    private function lookupUebergabe($db, int $rid): array
    {
        $def = ['abholung' => 'abholen', 'abholort' => null, 'einfuehrung' => 'keine', 'einfuehrung_typ' => null, 'tp_member_id' => null, 'vorlauf_toleranz_h' => 0, 'booking_mode' => 'day', 'hauspost_allowed' => false, 'rueckgabe' => 'abgeben', 'rueckgabeort' => null, 'max_nutzung_tage' => null, 'max_puffer_tage' => null];
        $cmd = new AdHocCommand('SELECT abholung, abholort, einfuehrung, einfuehrung_typ, tp_member_id, vorlauf_toleranz_h, booking_mode, hauspost_allowed, rueckgabe, rueckgabeort, max_nutzung_tage, max_puffer_tage FROM zhl_uebergabe WHERE resource_id = @r LIMIT 1');
        $cmd->AddParameter(new Parameter('@r', $rid));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        if (!$row) {
            return $def;
        }
        return [
            'abholung' => (string)($row['abholung'] ?? 'abholen'),
            'abholort' => $row['abholort'] !== null && $row['abholort'] !== '' ? (string)$row['abholort'] : null,
            'einfuehrung' => (string)($row['einfuehrung'] ?? 'keine'),
            'einfuehrung_typ' => $row['einfuehrung_typ'] !== null && $row['einfuehrung_typ'] !== '' ? (string)$row['einfuehrung_typ'] : null,
            'tp_member_id' => $row['tp_member_id'] !== null ? (int)$row['tp_member_id'] : null,
            'vorlauf_toleranz_h' => (int)($row['vorlauf_toleranz_h'] ?? 0),
            'booking_mode' => (string)($row['booking_mode'] ?? 'day'),
            'hauspost_allowed' => (bool)($row['hauspost_allowed'] ?? false),
            'rueckgabe' => (string)($row['rueckgabe'] ?? 'abgeben'),
            'rueckgabeort' => $row['rueckgabeort'] !== null && $row['rueckgabeort'] !== '' ? (string)$row['rueckgabeort'] : null,
            'max_nutzung_tage' => $row['max_nutzung_tage'] !== null ? (int)$row['max_nutzung_tage'] : null,
            'max_puffer_tage' => $row['max_puffer_tage'] !== null ? (int)$row['max_puffer_tage'] : null,
        ];
    }

    /** Effektive Ausleihdauer-Limits: Gerät-Override (zhl_uebergabe) sonst globaler Default (zhl_settings). */
    private function zhlDurationLimits(array $ueb): array
    {
        $maxN = ($ueb['max_nutzung_tage'] ?? null) !== null && (int)$ueb['max_nutzung_tage'] > 0
            ? (int)$ueb['max_nutzung_tage'] : ZhlSettings::GetInt('ausleih_max_nutzung_tage', 14);
        $maxP = ($ueb['max_puffer_tage'] ?? null) !== null && (int)$ueb['max_puffer_tage'] >= 0
            ? (int)$ueb['max_puffer_tage'] : ZhlSettings::GetInt('ausleih_max_puffer_tage', 5);
        return ['nutzung' => max(1, $maxN), 'puffer' => max(0, $maxP)];
    }

    /** Ganztags-Differenz (lokale Mitternacht zu Mitternacht) in Tagen — Nächte-Zählung. */
    private function zhlDayDiff(string $tz, string $fromYmd, string $toYmd): int
    {
        try {
            $a = Date::Parse($fromYmd . ' 00:00:00', $tz)->Timestamp();
            $b = Date::Parse($toYmd . ' 00:00:00', $tz)->Timestamp();
            return (int)round(($b - $a) / 86400);
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** Notiz für die Reservierungs-Beschreibung aus der Übergabe-Konfiguration. */
    private function uebergabeNote(array $ueb): string
    {
        $parts = [];
        if ($ueb['abholung'] === 'abholen_persoenlich') {
            $parts[] = 'Abholung (persönlich)' . ($ueb['abholort'] ? ': ' . $ueb['abholort'] : '');
        } elseif ($ueb['abholung'] === 'abholen') {
            $parts[] = 'Abholung' . ($ueb['abholort'] ? ': ' . $ueb['abholort'] : '');
        } elseif ($ueb['abholung'] === 'ablageort') {
            $parts[] = 'Abholung am Ablageort' . ($ueb['abholort'] ? ': ' . $ueb['abholort'] : '');
        } elseif ($ueb['abholung'] === 'nicht_noetig') {
            $parts[] = 'keine Abholung nötig';
        }
        if ($ueb['einfuehrung'] === 'notwendig') {
            $parts[] = 'Einführung NÖTIG' . ($ueb['einfuehrung_typ'] ? ' (' . $ueb['einfuehrung_typ'] . ')' : '');
        } elseif ($ueb['einfuehrung'] === 'moeglich') {
            $parts[] = 'Einführung möglich' . ($ueb['einfuehrung_typ'] ? ' (' . $ueb['einfuehrung_typ'] . ')' : '');
        }
        return '[ZHL] ' . implode(' · ', $parts);
    }

    private function lookupMinNotice($db, int $rid): int
    {
        $cmd = new AdHocCommand('SELECT min_notice_time_add FROM resources WHERE resource_id = @r LIMIT 1');
        $cmd->AddParameter(new Parameter('@r', $rid));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row ? (int)$row['min_notice_time_add'] : 0;
    }

    private function readInt($key, $default)
    {
        $v = isset($_GET[$key]) ? (string)$_GET[$key] : '';
        return ($v === '' || !is_numeric($v)) ? $default : (int)$v;
    }

    private function readDate($key, $tz)
    {
        $v = isset($_GET[$key]) ? (string)$_GET[$key] : '';
        return $this->validDate($v, $tz);
    }

    private function postDate($key, $tz)
    {
        return $this->validDate($this->post($key), $tz);
    }

    private function postTime($key, $default)
    {
        $v = $this->post($key);
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : $default;
    }

    private function validDate($v, $tz)
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return $v;
        }
        return Date::Now()->ToTimezone($tz)->Format('Y-m-d');
    }

    private function post($key)
    {
        return isset($_POST[$key]) ? (string)$_POST[$key] : '';
    }
}

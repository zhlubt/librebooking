<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Schedule/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Attributes/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlBundleResolver.php');
require_once(ROOT_DIR . 'Presenters/Reservation/ReservationPresenterFactory.php');
require_once(ROOT_DIR . 'Presenters/ZhlReservationFacade.php');
require_once(ROOT_DIR . 'Web/zhl-audit-lib.php');

/**
 * ZHL Bundle-Buchung (SPEC-BUNDLE-BOOKING). Bucht ein ganzes Bundle „in EINER Reservierung" (Codex:
 * NICHT „atomar" nennen) für die Aufnahme-Phase + optional eine getrennte, NICHT-atomare Folge-
 * Buchung (Schnitt-/VR-PC) NACH der Aufnahme. Alternativen (Stativ ODER Gimbal), Packliste
 * (Transportkiste/Ladegeräte, nicht buchbar), Übergabe/Einführung für die Main-Phase.
 *
 * Reuse aus ZhlBookPresenter (Muster bewusst gespiegelt, nicht geteilt — eigenständige Seite):
 * monthGrid/scheduleDayBounds, fetchHandoverSlots/persistHandover/backfillHandoverReference,
 * userIsCertified/createCertConfirmation, Terminplaner-Anbindung.
 */
class ZhlBundleBookPresenter
{
    private const WD = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    private const SCHEDULE_BUNDLE = 5; // „Medientechnik Verleih" (alle Bundle-Geräte) — Default-Layout.

    private $page;
    /** Vom Nutzer gewählter Aufnahme-Zeitraum + Slots (für Re-Render nach POST-Fehler; '' bei initialem GET). */
    private $postedStart = '';
    private $postedEnd = '';
    private $postedPickupSlot = '';
    private $postedEinfSlot = '';

    public function __construct($page)
    {
        $this->page = $page;
    }

    /** GET: Bundle + Komposition + Kalender anzeigen (oder Erfolgs-Panel nach Redirect). */
    public function PageLoad(UserSession $user)
    {
        if (isset($_GET['booked'])) {
            $this->page->BindSuccess([
                'mainReference' => isset($_GET['booked']) ? trim((string)$_GET['booked']) : '',
                'afterReference' => isset($_GET['after']) ? trim((string)$_GET['after']) : '',
                'afterWarning' => isset($_GET['warn']) ? trim((string)$_GET['warn']) : '',
                'pickupInfo' => isset($_GET['pickup']) ? trim((string)$_GET['pickup']) : '',
            ]);
            return;
        }

        $tz = $user->Timezone;
        $bid = $this->readInt('bid', 0);
        $db = ServiceLocator::GetDatabase();
        $bundle = $this->loadBundle($db, $bid);
        if ($bundle === null) {
            $this->page->RedirectToDashboard();
            return;
        }
        $aroundYmd = $this->readDate('rd', $tz);
        $this->bindForm($user, $bundle, $aroundYmd, '', [], [], 7, false);
    }

    /**
     * AJAX: Abhol- + Einführungstermine zum gewählten Aufnahme-Start als JSON. Wird vom Kalender-JS
     * aufgerufen, sobald ein Zeitraum gewählt ist — so werden die Termine GEGEN das gewählte Datum
     * (nicht das Default-Datum) gefiltert, ohne Page-Reload (Titel/Auswahl/Häkchen bleiben erhalten).
     */
    public function AjaxSlots(UserSession $user)
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $tz = $user->Timezone;
        $db = ServiceLocator::GetDatabase();
        $bundle = $this->loadBundle($db, $this->readInt('bid', 0));
        if ($bundle === null) {
            echo json_encode(['error' => 'bundle']);
            return;
        }
        $start = isset($_GET['start']) ? trim((string)$_GET['start']) : '';
        if (!$this->isYmd($start)) {
            echo json_encode(['error' => 'date']);
            return;
        }
        $mainItems = $this->itemsForPhase($bundle, 'main');
        echo json_encode([
            'pickup' => $this->buildPickupVm($db, $user, $mainItems, $start, $tz),
            'einf' => $this->buildEinfVm($db, $user, $mainItems, $start, $tz),
        ], JSON_UNESCAPED_UNICODE);
    }

    /** POST: Bundle verbindlich buchen (Main-Phase + optional After-Phase). */
    public function HandlePost(UserSession $user)
    {
        $tz = $user->Timezone;
        $db = ServiceLocator::GetDatabase();
        $bid = (int)$this->post('bid');
        $bundle = $this->loadBundle($db, $bid);
        if ($bundle === null) {
            $this->page->RedirectToDashboard();
            return;
        }

        $projectTitle = trim((string)$this->post('projectTitle'));
        $dayStartRaw = $this->post('dayStart');
        $dayEndRaw = $this->post('dayEnd');
        // Gewählten Zeitraum merken → bei jedem Fehler-Re-Render bleiben Auswahl + Termine erhalten.
        $this->postedStart = $this->isYmd($dayStartRaw) ? $dayStartRaw : '';
        $this->postedEnd = $this->isYmd($dayEndRaw) ? $dayEndRaw : '';
        $this->postedPickupSlot = (string)$this->post('pickup_slot');
        $this->postedEinfSlot = (string)$this->post('einf_slot');
        $altChoices = $this->collectAltChoices($bundle);
        $afterChosen = $this->post('afterChosen') === '1' && $this->bundleHasAfter($bundle);
        $afterDays = $this->clampAfterDays((int)$this->post('afterDays'));

        // --- Doppel-Submit-Schutz: per-Load-Nonce in der Session prüfen + verbrauchen. ---
        $nonce = $this->post('formNonce');
        if (!$this->consumeNonce($nonce)) {
            $this->bindForm($user, $bundle, $dayStartRaw !== '' ? $dayStartRaw : $this->today($tz), $projectTitle, $altChoices, ['Das Formular wurde bereits abgeschickt (oder ist abgelaufen). Bitte lade die Seite neu und versuche es erneut.'], $afterDays, $afterChosen);
            return;
        }

        if ($projectTitle === '') {
            $this->bindForm($user, $bundle, $dayStartRaw !== '' ? $dayStartRaw : $this->today($tz), $projectTitle, $altChoices, ['Bitte gib einen Titel für dein Projekt an.'], $afterDays, $afterChosen);
            return;
        }

        // Aufnahme-Zeitraum aus dem Monats-Kalender (Klick-Spanne ganze Tage).
        $validStart = $this->isYmd($dayStartRaw);
        $validEnd = $this->isYmd($dayEndRaw);
        if (!$validStart || !$validEnd || strcmp($dayEndRaw, $dayStartRaw) < 0) {
            $this->bindForm($user, $bundle, $validStart ? $dayStartRaw : $this->today($tz), $projectTitle, $altChoices, ['Bitte wähle im Kalender einen Start- und End-Tag für die Aufnahme.'], $afterDays, $afterChosen);
            return;
        }

        $mainItems = $this->itemsForPhase($bundle, 'main');
        $resolver = $this->buildResolver($user);

        // --- 1. Verfügbarkeit ZUERST über die GANZEN gewählten Tage prüfen (schedule-agnostisches
        //     Grobfenster), damit der Resolver die konkreten Geräte – und damit ihren ECHTEN Schedule –
        //     bestimmt. Die danach berechneten präzisen Buchungszeiten liegen INNERHALB dieses Fensters
        //     (Grob ⊇ Präzise) → bleiben frei. So stammen die Tagesgrenzen garantiert vom tatsächlich
        //     reservierten Schedule, nicht von einem geratenen Leitgerät / pauschal SCHEDULE_BUNDLE=5.
        //     Geräte liegen auf eigenen Schedules (Drohne=7, VR=2, Videostudio=8 …) mit anderen Perioden-
        //     Grenzen; ein Ende auf Schedule-5-Grenze (22:00) ist dort ungültig → „Die angeforderte Endzeit
        //     ist nicht gültig". Mit dem echten Schedule passt das Ende auf eine gültige Periodengrenze. ---
        $coarseBeginUtc = Date::Parse($dayStartRaw . ' 00:00:00', $tz);
        $coarseEndUtc = Date::Parse($dayEndRaw . ' 00:00:00', $tz)->AddDays(1);
        $mainPhase = $resolver->ResolvePhase($mainItems, $coarseBeginUtc, $coarseEndUtc, $altChoices, $user, 'main');
        if (!$mainPhase->satisfiable) {
            $this->bindForm($user, $bundle, $dayStartRaw, $projectTitle, $altChoices, [$mainPhase->error ?? 'Das Bundle ist im gewählten Zeitraum nicht vollständig verfügbar.'], $afterDays, $afterChosen);
            return;
        }

        // Präzise Tagesgrenzen aus dem Schedule, den der Resolver tatsächlich zugeteilt hat.
        $mainScheduleId = (int)($mainPhase->scheduleId ?? self::SCHEDULE_BUNDLE);
        if ($mainScheduleId <= 0) {
            $mainScheduleId = self::SCHEDULE_BUNDLE;
        }
        $startBounds = $this->scheduleDayBounds($user, $mainScheduleId, $dayStartRaw);
        $endBounds = $this->scheduleDayBounds($user, $mainScheduleId, $dayEndRaw);
        $beginTime = $startBounds['begin'];
        $endTime = $endBounds['end'];
        $endOffset = $endBounds['endNextDay'] ? 1 : 0;
        $mainEndDate = Date::Parse($dayEndRaw . ' 00:00:00', $tz)->AddDays($endOffset)->Format('Y-m-d');
        $mainBeginUtc = Date::Parse($dayStartRaw . ' ' . $beginTime, $tz);
        $mainEndUtc = Date::Parse($mainEndDate . ' ' . $endTime, $tz);

        $afterPhase = null;
        $afterBeginDate = null;
        $afterEndDate = null;
        $afterBeginTime = null;
        $afterEndTime = null;
        if ($afterChosen) {
            // After-Phase = [Aufnahme-Ende+1 … +Dauer]. Der Schnitt-/VR-PC startet am TAG NACH dem
            // Aufnahme-Ende (mainEndDate), nicht am Aufnahme-Ende selbst (sonst überlappt der
            // Schnittplatz mit der laufenden Aufnahme). Beginn-Tag = mainEndDate + 1 Tag.
            $afterBeginDate = Date::Parse($mainEndDate . ' 00:00:00', $tz)->AddDays(1)->Format('Y-m-d');
            // Ende-Tag = Beginn-Tag + (Dauer-1), damit der Zeitraum genau $afterDays Kalendertage umfasst.
            $afterEndDate = Date::Parse($afterBeginDate . ' 00:00:00', $tz)->AddDays($afterDays - 1)->Format('Y-m-d');
            $afterItems = $this->itemsForPhase($bundle, 'after');
            // Wie Main: erst über die ganzen Tage auflösen (Grobfenster) → Schedule der After-Geräte
            // (Schnitt-/VR-PC liegt auf eigenem Schedule), dann präzise Grenzen daraus berechnen.
            $afterCoarseBegin = Date::Parse($afterBeginDate . ' 00:00:00', $tz);
            $afterCoarseEnd = Date::Parse($afterEndDate . ' 00:00:00', $tz)->AddDays(1);
            $afterPhase = $resolver->ResolvePhase($afterItems, $afterCoarseBegin, $afterCoarseEnd, $altChoices, $user, 'after');
            // After-Items sind optional (required=0) — bei Nicht-Verfügbarkeit klare Meldung, KEINE Buchung.
            if (!$afterPhase->satisfiable || count($afterPhase->AllResourceIds()) === 0) {
                $this->bindForm($user, $bundle, $dayStartRaw, $projectTitle, $altChoices, ['Schnitt-/VR-PC ist im Zeitraum nach der Aufnahme nicht verfügbar — buche die Aufnahme ohne Folge-Phase und den Schnittplatz später separat, oder wähle eine andere Dauer.'], $afterDays, $afterChosen);
                return;
            }
            $afterScheduleId = (int)($afterPhase->scheduleId ?? self::SCHEDULE_BUNDLE);
            if ($afterScheduleId <= 0) {
                $afterScheduleId = self::SCHEDULE_BUNDLE;
            }
            $ab = $this->scheduleDayBounds($user, $afterScheduleId, $afterBeginDate);
            $ae = $this->scheduleDayBounds($user, $afterScheduleId, $afterEndDate);
            $afterBeginTime = $ab['begin'];
            $afterEndTime = $ae['end'];
            $afterEndOffset = $ae['endNextDay'] ? 1 : 0;
            $afterEndDate = Date::Parse($afterEndDate . ' 00:00:00', $tz)->AddDays($afterEndOffset)->Format('Y-m-d');
            $afterBeginUtc = Date::Parse($afterBeginDate . ' ' . $afterBeginTime, $tz);
            $afterEndUtc = Date::Parse($afterEndDate . ' ' . $afterEndTime, $tz);
        }

        // --- 2. max_resources_per_reservation respektieren (0 = unlimitiert). ---
        $mainResourceIds = $mainPhase->AllResourceIds();
        $maxPer = $this->maxResourcesPerReservation($db, (int)($mainPhase->scheduleId ?? self::SCHEDULE_BUNDLE));
        if ($maxPer > 0 && count($mainResourceIds) > $maxPer) {
            $this->bindForm($user, $bundle, $dayStartRaw, $projectTitle, $altChoices, ['Dieses Bundle braucht mehr Geräte (' . count($mainResourceIds) . '), als pro Reservierung erlaubt sind (' . $maxPer . '). Bitte beim ZHL-Team melden.'], $afterDays, $afterChosen);
            return;
        }
        if (empty($mainResourceIds)) {
            $this->bindForm($user, $bundle, $dayStartRaw, $projectTitle, $altChoices, ['Es konnten keine buchbaren Geräte für die Aufnahme aufgelöst werden.'], $afterDays, $afterChosen);
            return;
        }

        // --- 3. Pflicht-Attribute (z. B. Haftpflichtversicherung) — VOR jeder Buchung einsammeln/prüfen.
        //        Die native Save-Validierung verlangt sie ohnehin; durch frühes Einsammeln scheitert ein
        //        fehlendes Pflichtfeld VOR jeder Terminplaner-Buchung (keine verwaisten Termine). ---
        $primary = (int)$mainResourceIds[0];
        $additional = array_slice($mainResourceIds, 1);
        $attrValues = [];
        $facadeAttrs = [];
        foreach ($this->applicableReservationAttributes($primary, (bool)$user->IsAdmin) as $a) {
            $val = isset($_POST['attr_' . $a->Id()]) ? trim((string)$_POST['attr_' . $a->Id()]) : '';
            $attrValues[(int)$a->Id()] = $val;
            $o = new stdClass();
            $o->Id = (int)$a->Id();
            $o->Value = $val;
            $facadeAttrs[] = $o;
        }

        // --- 4. Einführung VALIDIEREN + Slot serverseitig auflösen (NOCH NICHT buchen). ---
        $einfPlan = null;
        $einfNeed = $this->firstResourceNeedingEinf($db, $user, $mainResourceIds);
        if ($einfNeed !== null) {
            $chosenSlot = $this->post('einf_slot');
            if ($chosenSlot === '') {
                $this->bindForm($user, $bundle, $dayStartRaw, $projectTitle, $altChoices, ['Für mindestens ein Gerät dieses Bundles ist eine Einführung nötig — bitte zuerst einen Einführungstermin wählen.'], $afterDays, $afterChosen, $attrValues);
                return;
            }
            $loanStartUtc = $mainBeginUtc->ToTimezone('UTC')->Format('Y-m-d H:i:s');
            $ef = $this->fetchEinfuehrungSlots($einfNeed['einfuehrung_typ'], $einfNeed['tp_member_id'], $loanStartUtc, $tz);
            $einfResolved = null;
            foreach ($ef['slots'] as $s) {
                if ((string)$s['slot_id'] === (string)$chosenSlot) {
                    $einfResolved = $s;
                    break;
                }
            }
            if ($einfResolved === null) {
                $this->bindForm($user, $bundle, $dayStartRaw, $projectTitle, $altChoices, ['Der gewählte Einführungstermin ist nicht mehr verfügbar — bitte neu wählen.'], $afterDays, $afterChosen, $attrValues);
                return;
            }
            $einfMemberId = (int)($einfResolved['member_id'] ?? $ef['memberId'] ?? 0);
            $einfTypeId = (int)($einfResolved['type_id'] ?? $ef['typeId'] ?? 0);
            if ($einfMemberId <= 0 || $einfTypeId <= 0) {
                Log::Error('ZHL-Bundle Einführung: ungültige Terminplaner-IDs (member=%s, type=%s) für res=%s', $einfMemberId, $einfTypeId, (int)$einfNeed['resourceId']);
                $this->bindForm($user, $bundle, $dayStartRaw, $projectTitle, $altChoices, ['Der Einführungstermin konnte nicht aufgelöst werden. Bitte beim ZHL-Team melden.'], $afterDays, $afterChosen, $attrValues);
                return;
            }
            $einfStartUtc = !empty($einfResolved['start_utc']) ? Date::Parse($einfResolved['start_utc'], 'UTC')->Format('Y-m-d H:i:s') : null;
            $einfEndUtc = !empty($einfResolved['end_utc']) ? Date::Parse($einfResolved['end_utc'], 'UTC')->Format('Y-m-d H:i:s') : $einfStartUtc;
            $einfPlan = ['slot_id' => $chosenSlot, 'member_id' => $einfMemberId, 'type_id' => $einfTypeId, 'resourceId' => (int)$einfNeed['resourceId'], 'start_utc' => $einfStartUtc, 'end_utc' => $einfEndUtc];
        }

        // --- 5. Abholung VALIDIEREN + Slot serverseitig auflösen (NOCH NICHT buchen). ---
        // „Zusammen" (Nutzer-Wahl 2026-06-26): EIN gemeinsamer Termin (der Einführungs-Slot) deckt
        // Einführung UND Abholung ab → KEIN separater Abholtermin. Nur möglich, wenn beides ansteht
        // (Einführung gewählt + persönliche Abholung greift). Default „zusammen" (Bundle-Einführung ist Pflicht).
        $handoverMode = ($this->post('handover_mode') === 'getrennt') ? 'getrennt' : 'zusammen';
        $pickupPlan = null;
        $pickupNeed = $this->firstResourceNeedingPickup($db, $mainResourceIds);
        $combined = ($handoverMode === 'zusammen') && $einfPlan !== null
            && $pickupNeed !== null && $this->pickupApplies($pickupNeed);
        if ($pickupNeed !== null && !$combined) {
            $mandatory = $this->pickupApplies($pickupNeed) && !$user->IsAdmin;
            $pickupSlot = $this->post('pickup_slot');
            if ($mandatory && $pickupSlot === '') {
                $this->bindForm($user, $bundle, $dayStartRaw, $projectTitle, $altChoices, ['Für dieses Bundle ist eine persönliche Abholung Pflicht — bitte einen Abholtermin wählen.'], $afterDays, $afterChosen, $attrValues);
                return;
            }
            if ($pickupSlot !== '') {
                $loanStartUtc = $mainBeginUtc->ToTimezone('UTC')->Format('Y-m-d H:i:s');
                $typeLabel = $this->tpConfig()['handover_type_label'] ?? 'Übergabe Medien';
                $f = $this->fetchHandoverSlots($typeLabel, $pickupNeed['tp_member_id'], $loanStartUtc, $tz);
                $resolved = null;
                foreach ($f['slots'] as $s) {
                    if ((string)$s['slot_id'] === (string)$pickupSlot) {
                        $resolved = $s;
                        break;
                    }
                }
                if ($resolved === null) {
                    $this->bindForm($user, $bundle, $dayStartRaw, $projectTitle, $altChoices, ['Der gewählte Abholtermin ist nicht mehr verfügbar — bitte neu wählen.'], $afterDays, $afterChosen, $attrValues);
                    return;
                }
                $pickupMemberId = (int)($resolved['member_id'] ?? $f['memberId'] ?? 0);
                $pickupTypeId = (int)($resolved['type_id'] ?? $f['typeId'] ?? 0);
                if ($pickupMemberId <= 0 || $pickupTypeId <= 0) {
                    Log::Error('ZHL-Bundle Abholung: ungültige Terminplaner-IDs (member=%s, type=%s)', $pickupMemberId, $pickupTypeId);
                    $this->bindForm($user, $bundle, $dayStartRaw, $projectTitle, $altChoices, ['Der Abholtermin konnte nicht aufgelöst werden. Bitte beim ZHL-Team melden.'], $afterDays, $afterChosen, $attrValues);
                    return;
                }
                $pickupPlan = ['slot_id' => $pickupSlot, 'member_id' => $pickupMemberId, 'type_id' => $pickupTypeId, 'start_utc' => $resolved['start_utc'], 'end_utc' => $resolved['end_utc'], 'resourceId' => (int)$pickupNeed['resourceId']];
            }
        }

        // --- 6. Reservierungsbeginn = ABHOLTAG: ab der Übergabe ist das Gerät physisch weg, also blockiert
        //        die Reservierung ALLE Bundle-Geräte schon ab dem Abholtag (nicht erst ab Einsatzbeginn). ---
        $reservBeginDate = $dayStartRaw;
        $reservBeginTime = $beginTime;
        if ($pickupPlan !== null) {
            $pickupDay = Date::Parse($pickupPlan['start_utc'], 'UTC')->ToTimezone($tz)->Format('Y-m-d');
            if (strcmp($pickupDay, $dayStartRaw) < 0) {
                $reservBeginDate = $pickupDay;
                $pbnds = $this->scheduleDayBounds($user, $mainScheduleId, $pickupDay);
                $reservBeginTime = $pbnds['begin'];
            }
        }
        // „Zusammen": der gemeinsame Termin (= Einführungs-Slot) ist zugleich der Übergabetag.
        if ($combined && $pickupPlan === null && $einfPlan !== null && !empty($einfPlan['start_utc'])) {
            $einfDay = Date::Parse($einfPlan['start_utc'], 'UTC')->ToTimezone($tz)->Format('Y-m-d');
            if (strcmp($einfDay, $dayStartRaw) < 0) {
                $reservBeginDate = $einfDay;
                $ebnds = $this->scheduleDayBounds($user, $mainScheduleId, $einfDay);
                $reservBeginTime = $ebnds['begin'];
            }
        }
        $reservBeginUtc = Date::Parse($reservBeginDate . ' ' . $reservBeginTime, $tz);

        // --- 6b. Pool-Fallback über die GESAMT-Spanne (Abholtag→Einsatzende). Der Resolver in Schritt 1
        //        hat die Geräte nur fürs Aufnahme-Fenster geprüft. Liegt der Abholtag DAVOR, kann ein dort
        //        freies Gerät am Abholtag belegt sein → die native Save-Validierung lehnt es ab, OHNE auf
        //        ein anderes Gerät desselben Typs auszuweichen (Nutzer-Bug: „nächstes Mikro nicht automatisch
        //        angeboten"). Deshalb die Main-Phase jetzt mit dem vollen Fenster erneut auflösen — frischer
        //        Resolver, damit auch zuvor gewählte Einheiten wieder Kandidaten sind. Einführungs-/Abhol-
        //        Termine sind je TYP (nicht je Einheit) und bleiben gültig. ---
        if ($reservBeginUtc->LessThan($mainBeginUtc)) {
            $fullResolver = $this->buildResolver($user);
            $mainPhaseFull = $fullResolver->ResolvePhase($mainItems, $reservBeginUtc, $mainEndUtc, $altChoices, $user, 'main');
            if (!$mainPhaseFull->satisfiable || empty($mainPhaseFull->AllResourceIds())) {
                $this->bindForm($user, $bundle, $dayStartRaw, $projectTitle, $altChoices, ['Mindestens ein Gerät des Bundles ist im Zeitraum inkl. Abholtag (' . $reservBeginDate . ') nicht mehr verfügbar. Bitte einen späteren Abholtermin oder andere Tage wählen.'], $afterDays, $afterChosen, $attrValues);
                return;
            }
            $mainResourceIds = $mainPhaseFull->AllResourceIds();
            $primary = (int)$mainResourceIds[0];
            $additional = array_slice($mainResourceIds, 1);
            $mainPhase = $mainPhaseFull;
            // Geräte-Verknüpfung der (je Typ aufgelösten) Einführungs-/Abhol-Pläne auf die FINAL
            // zugeteilten Einheiten nachziehen — sonst zeigt die lokale Speicherung (persistEinfuehrung/
            // persistHandover) auf eine Einheit, die nach dem Ausweichen nicht mehr in der Reservierung
            // ist. Termin-Details (member/type/slot) sind je Typ und bleiben unverändert.
            if ($einfPlan !== null) {
                $en = $this->firstResourceNeedingEinf($db, $user, $mainResourceIds);
                if ($en !== null) {
                    $einfPlan['resourceId'] = (int)$en['resourceId'];
                }
            }
            if ($pickupPlan !== null) {
                $pn = $this->firstResourceNeedingPickup($db, $mainResourceIds);
                if ($pn !== null) {
                    $pickupPlan['resourceId'] = (int)$pn['resourceId'];
                }
            }
            // Pflicht-Attribute sind je Geräte-Typ stabil (gleicher erster Item-Typ ⇒ gleicher Primary-Typ
            // ⇒ gleiche anwendbaren Attribute) → bereits eingesammelte $facadeAttrs bleiben gültig.
        }

        // --- 7. Maximale Ausleihdauer (kleinste Geräte-max_duration im Bundle) prüfen — über die GESAMT-Spanne
        //        Abholtag→Einsatzende, VOR jeder Terminplaner-Buchung (Native würde es sonst erst beim Save fangen).
        //        Admins sind ausgenommen — wie die native AdminExcludedRule um ResourceMaximumDurationRule. ---
        $maxSec = $user->IsAdmin ? 0 : $this->bundleMaxDurationSeconds($db, $mainResourceIds);
        if ($maxSec > 0) {
            $durSec = $mainEndUtc->Timestamp() - $reservBeginUtc->Timestamp();
            if ($durSec > $maxSec) {
                $maxDaysDisp = (int)floor($maxSec / 86400);
                $spanDays = (int)ceil($durSec / 86400);
                $hint = ($reservBeginDate !== $dayStartRaw) ? ' (zählt ab Abholtag ' . $reservBeginDate . ', da das Gerät ab der Übergabe vergeben ist)' : '';
                $this->bindForm($user, $bundle, $dayStartRaw, $projectTitle, $altChoices, ['Der Buchungszeitraum (~' . $spanDays . ' Tage' . $hint . ') ist länger als für dieses Bundle erlaubt (max. ' . $maxDaysDisp . ' Tage). Bitte kürzer wählen oder einen späteren Abholtermin nehmen.'], $afterDays, $afterChosen, $attrValues);
                return;
            }
        }

        // --- 8. Main-Reservierung SPEICHERN (ab Abholtag). Erst NACH Erfolg werden Terminplaner-Slots gebucht
        //        → schlägt das Speichern fehl (Pflichtfeld, Dauer, Konflikt), wird KEIN Termin gebucht. ---
        $desc = '[ZHL-Bundle: ' . $bundle['name'] . '] ' . count($mainResourceIds) . ' Gerät(e) in einer Reservierung'
            . ($reservBeginDate !== $dayStartRaw ? ' · ab Abholtag ' . $reservBeginDate . ' (Einsatz ab ' . $dayStartRaw . ')' : '') . '.';
        $mainFacade = new ZhlReservationFacade($user->UserId, $primary, $projectTitle, $desc, $reservBeginDate, $reservBeginTime, $mainEndDate, $endTime, $facadeAttrs, $additional);
        try {
            $factory = new ReservationPresenterFactory();
            $presenter = $factory->Create($mainFacade, $user);
            $series = $presenter->BuildReservation();
            $presenter->HandleReservation($series);
        } catch (Exception $ex) {
            Log::Error('ZHL-Bundle Buchung fehlgeschlagen: %s', $ex);
            $this->bindForm($user, $bundle, $dayStartRaw, $projectTitle, $altChoices, ['Unerwarteter Fehler beim Buchen der Aufnahme. Bitte erneut versuchen.'], $afterDays, $afterChosen, $attrValues);
            return;
        }
        if (!$mainFacade->WasSaved()) {
            $errors = $this->friendlyErrors($mainFacade->GetErrors());
            if (empty($errors)) {
                $errors = ['Die Aufnahme-Buchung konnte nicht angelegt werden.'];
            }
            $this->bindForm($user, $bundle, $dayStartRaw, $projectTitle, $altChoices, $errors, $afterDays, $afterChosen, $attrValues);
            return;
        }
        $mainRef = (string)$mainFacade->ReferenceNumber();

        // --- 9. NACH erfolgreichem Save: Terminplaner-Slots buchen (Einführung, dann Abholung). Scheitert hier
        //        etwas (Slot zwischenzeitlich weg), bleibt die Reservierung bestehen → klarer Hinweis. ---
        $pickupInfo = '';
        $postWarnings = [];
        if ($einfPlan !== null) {
            $book = $this->tpRequest('POST', '/api/book_slot.php', [], [
                'member_id' => $einfPlan['member_id'],
                'type_id' => $einfPlan['type_id'],
                'slot_id' => $einfPlan['slot_id'],
                'name' => trim($user->FirstName . ' ' . $user->LastName),
                'email' => $user->Email,
                'note' => 'ZHL Medienausleihe (Bundle) — ' . $projectTitle,
            ]);
            if (!$book || ($book['status'] ?? '') !== 'ok') {
                $postWarnings[] = 'Die Aufnahme ist gebucht, aber der Einführungstermin konnte nicht final reserviert werden — bitte beim ZHL-Team melden.';
            } else {
                // Nachbereitung darf die bereits gebuchte Reservierung + den Slot nie kippen → try/catch.
                // Termin zuerst lokal speichern, dann Zertifikats-Bestätigung anstoßen.
                try {
                    $einfBookingId = isset($book['booking_id']) ? (string)$book['booking_id'] : null;
                    $this->persistEinfuehrung($db, $mainRef, (int)$einfPlan['resourceId'], (int)$einfPlan['member_id'], $einfBookingId, $einfPlan['start_utc'], $einfPlan['end_utc']);
                    $this->createCertConfirmation($db, (int)$user->UserId, $einfPlan['resourceId']);
                    // „Zusammen": der gebuchte Einführungstermin ist zugleich der Übergabetermin →
                    // Übergabe-Zeilen (pickup + return) aus dem Einführungs-Slot ableiten, geschlüsselt
                    // auf das Übergabe-Repräsentativ-Gerät (wie der normale Pickup). KEIN separater Abhol-Slot.
                    if ($combined) {
                        $loanEndUtc = $mainEndUtc->ToTimezone('UTC')->Format('Y-m-d H:i:s');
                        $combToken = bin2hex(random_bytes(16));
                        // Auf das Einführungs-Gerät schlüsseln: dessen resourceId wird im Pool-Fallback (6b)
                        // auf die final reservierte Einheit nachgezogen ($pickupNeed wäre dort veraltet).
                        $combResId = (int)$einfPlan['resourceId'];
                        $combOk = $this->persistHandover($db, $combToken, (int)$user->UserId, $combResId, $einfBookingId, (int)$einfPlan['member_id'], $einfPlan['start_utc'], $einfPlan['end_utc'], $loanEndUtc);
                        if ($combOk) {
                            $this->backfillHandoverReference($db, $combToken, $mainRef);
                        } else {
                            Log::Error('ZHL-Bundle Zusammen: Übergabe-Zeilen (aus Einführung) nicht gespeichert (token=%s, res=%s)', $combToken, $combResId);
                            $postWarnings[] = 'Der gemeinsame Termin ist gebucht, aber die Übergabe konnte intern nicht hinterlegt werden — bitte beim ZHL-Team melden.';
                        }
                    }
                } catch (Throwable $e) {
                    Log::Error('ZHL-Bundle Einführung-Nachbereitung fehlgeschlagen (ref=%s, res=%s): %s', $mainRef, (int)$einfPlan['resourceId'], $e);
                    $postWarnings[] = 'Die Aufnahme ist gebucht, der Einführungstermin reserviert — die Nachbereitung (Bestätigung/Hinterlegung) konnte aber nicht abgeschlossen werden. Bitte beim ZHL-Team melden.';
                }
            }
        }
        if ($pickupPlan !== null) {
            $pb2 = $this->tpRequest('POST', '/api/book_slot.php', [], [
                'member_id' => $pickupPlan['member_id'],
                'type_id' => $pickupPlan['type_id'],
                'slot_id' => $pickupPlan['slot_id'],
                'name' => trim($user->FirstName . ' ' . $user->LastName),
                'email' => $user->Email,
                'note' => 'ZHL Abholung (Bundle) — ' . $projectTitle,
            ]);
            if (!$pb2 || ($pb2['status'] ?? '') !== 'ok') {
                $postWarnings[] = 'Die Aufnahme ist gebucht, aber der Abholtermin konnte nicht final reserviert werden — bitte beim ZHL-Team melden.';
            } else {
                $pickupStartUtc = Date::Parse($pickupPlan['start_utc'], 'UTC')->Format('Y-m-d H:i:s');
                $pickupEndUtc = $pickupPlan['end_utc'] !== null ? Date::Parse($pickupPlan['end_utc'], 'UTC')->Format('Y-m-d H:i:s') : $pickupStartUtc;
                $loanEndUtc = $mainEndUtc->ToTimezone('UTC')->Format('Y-m-d H:i:s');
                $handoverToken = bin2hex(random_bytes(16));
                $bookingId = isset($pb2['booking_id']) ? (string)$pb2['booking_id'] : null;
                $persisted = $this->persistHandover($db, $handoverToken, (int)$user->UserId, $pickupPlan['resourceId'], $bookingId, $pickupPlan['member_id'], $pickupStartUtc, $pickupEndUtc, $loanEndUtc);
                if ($persisted) {
                    $this->backfillHandoverReference($db, $handoverToken, $mainRef);
                    $pickupInfo = 'Abholung gebucht (#' . (string)($bookingId ?? '') . ')';
                } else {
                    $postWarnings[] = 'Abholtermin gebucht, aber intern nicht hinterlegt — bitte beim ZHL-Team melden.';
                }
            }
        }

        // --- 10. After-Phase: ZWEITE native Reservierung (explizit NICHT-atomar). ---
        $afterRef = '';
        $afterWarning = '';
        if ($afterChosen && $afterPhase !== null) {
            $afterIds = $afterPhase->AllResourceIds();
            $afterPrimary = (int)$afterIds[0];
            $afterAdditional = array_slice($afterIds, 1);
            $afterDesc = '[ZHL-Bundle: ' . $bundle['name'] . '] Schnitt-/VR-PC · Folge-Buchung zu ' . $mainRef;
            $afterFacade = new ZhlReservationFacade($user->UserId, $afterPrimary, $projectTitle . ' (Schnitt)', $afterDesc, $afterBeginDate, $afterBeginTime, $afterEndDate, $afterEndTime, [], $afterAdditional);
            try {
                $factory2 = new ReservationPresenterFactory();
                $presenter2 = $factory2->Create($afterFacade, $user);
                $series2 = $presenter2->BuildReservation();
                $presenter2->HandleReservation($series2);
            } catch (Exception $ex) {
                Log::Error('ZHL-Bundle After-Phase fehlgeschlagen: %s', $ex);
            }
            if ($afterFacade->WasSaved()) {
                $afterRef = (string)$afterFacade->ReferenceNumber();
            } else {
                $afterWarning = 'Aufnahme gebucht — Schnitt-/VR-PC bitte separat nachbuchen (die Folge-Buchung konnte nicht angelegt werden).';
            }
        }

        $allWarnings = array_merge($postWarnings, ($afterWarning !== '' ? [$afterWarning] : []));

        zhl_audit_log(array_merge(zhl_audit_actor($user), [
            'action' => 'booking.create.bundle',
            'entity_type' => 'bundle',
            'entity_id' => (string)($bundle['id'] ?? ''),
            'reference_number' => $mainRef,
            'detail' => [
                'bundle' => (string)($bundle['name'] ?? ''),
                'afterRef' => $afterRef,
                'pickup' => $pickupPlan !== null,
                'einf' => $einfPlan !== null,
                'warnings' => $allWarnings,
            ],
        ]));

        $this->redirectSuccess($mainRef, $afterRef, implode(' · ', $allWarnings), $pickupInfo);
    }

    /**
     * Hängt den C1-Hinweis an die Fehlerliste an, dass die Einführung bereits gebucht wurde — für
     * JEDEN Early-Return, der NACH dem erfolgreichen Einführungs-book_slot greift. So erfährt der
     * Nutzer in jedem Fehlerpfad, dass der Terminplaner-Slot bereits liegt.
     * @param string[] $errors
     * @return string[]
     */
    private function appendEinfWarning(array $errors, bool $einfBooked): array
    {
        if ($einfBooked) {
            $errors[] = 'Hinweis: Dein Einführungstermin wurde bereits gebucht — bitte beim ZHL-Team melden, falls die Buchung nicht zustande kommt.';
        }
        return $errors;
    }

    private function redirectSuccess(string $mainRef, string $afterRef, string $afterWarning, string $pickupInfo): void
    {
        $q = 'booked=' . urlencode($mainRef);
        if ($afterRef !== '') {
            $q .= '&after=' . urlencode($afterRef);
        }
        if ($afterWarning !== '') {
            $q .= '&warn=' . urlencode($afterWarning);
        }
        if ($pickupInfo !== '') {
            $q .= '&pickup=' . urlencode($pickupInfo);
        }
        // Post-Redirect-Get über die Page-Redirect-Mechanik.
        $this->page->RedirectToSuccess($q);
    }

    // --- Rendern ---

    private function bindForm(UserSession $user, array $bundle, string $aroundYmd, string $projectTitle, array $altChoices, array $errors, int $afterDays, bool $afterChosen, array $attrValues = []): void
    {
        $db = ServiceLocator::GetDatabase();
        $tz = $user->Timezone;

        // Leitgerät = erstes erforderliches Main-Item (specific_resource_id oder Typ) → Kalender-Proxy.
        $mainItems = $this->itemsForPhase($bundle, 'main');
        $leitId = $this->leitResourceId($db, $user, $mainItems);
        $cal = $leitId > 0 ? $this->monthGrid($user, $leitId, $aroundYmd) : null;

        // Pflicht-Reservierungs-Attribute (z. B. Haftpflichtversicherung) — wie Einzelbuchung.
        $attributes = [];
        foreach ($this->applicableReservationAttributes($leitId, (bool)$user->IsAdmin) as $a) {
            $attributes[] = [
                'id' => (int)$a->Id(),
                'label' => (string)$a->Label(),
                'type' => (int)$a->Type(),
                'required' => (bool)$a->Required(),
                'options' => $a->PossibleValueList(),
                'value' => array_key_exists((int)$a->Id(), $attrValues) ? (string)$attrValues[(int)$a->Id()] : '',
            ];
        }

        // Maximale Ausleihdauer (Anzeige): kleinste max_duration der Leitgeräte-Typen, in Tagen.
        // 0 = keine Begrenzung. Gilt ab dem Abholtag (Hinweis im Template).
        $maxSec = $leitId > 0 ? $this->bundleMaxDurationSeconds($db, [$leitId]) : 0;
        $maxDays = $maxSec > 0 ? (int)floor($maxSec / 86400) : 0;

        // Alternativ-Gruppen (alt_group → [{label, models}]) für die Radios.
        $altGroups = $this->buildAltGroups($db, $user, $mainItems);

        // Packliste (quantity 0) + reguläre Items für die Anzeige.
        $display = [];
        $packlist = [];
        foreach ($mainItems as $it) {
            if ((int)$it['quantity'] <= 0) {
                $packlist[] = ['label' => (string)$it['type_label'], 'meta' => (string)($it['meta'] ?? '')];
                continue;
            }
            if (isset($it['alt_group']) && $it['alt_group'] !== '') {
                continue; // Alternativen separat als Radios.
            }
            $display[] = [
                'label' => (string)$it['type_label'],
                'quantity' => (int)$it['quantity'],
                'required' => ((int)$it['required']) === 1,
                'meta' => (string)($it['meta'] ?? ''),
            ];
        }

        $hasAfter = $this->bundleHasAfter($bundle);

        // Übergabe/Einführung werden NICHT mehr server-seitig gegen ein Default-Datum gerendert (das zeigte
        // fälschlich „kein Termin"). Stattdessen lädt das JS die Termine per AJAX zum GEWÄHLTEN Aufnahme-Start
        // (zhl-bundle-book.php?ajax=slots). Hier nur leichte Aktiv-Flags (kein Terminplaner-Call beim Laden).
        $pickupRid = $this->firstTypeResourceNeeding($db, $mainItems, 'pickup');
        $einfRid = $this->firstTypeResourceNeeding($db, $mainItems, 'einf');
        $pickupActive = $pickupRid > 0;
        $pickupMandatory = false;
        if ($pickupActive) {
            $pu = $this->lookupUebergabe($db, $pickupRid);
            $pickupMandatory = $this->pickupApplies($pu) && !$user->IsAdmin;
        }
        $einfCertified = $einfRid > 0 && $this->userIsCertified($db, $user->UserId, $einfRid);
        $einfActive = $einfRid > 0 && !$einfCertified;

        // Per-Load-Nonce (Doppel-Submit-Schutz).
        $nonce = $this->issueNonce();

        $this->page->BindBundle([
            'bundleId' => (int)$bundle['id'],
            'bundleName' => (string)$bundle['name'],
            'bundleUseCase' => (string)($bundle['use_case'] ?? ''),
            'bundleHint' => (string)($bundle['hint'] ?? ''),
            'projectTitle' => $projectTitle,
            'cal' => $cal,
            'leitId' => $leitId,
            'displayItems' => $display,
            'altGroups' => $altGroups,
            'altChoices' => $altChoices,
            'packlist' => $packlist,
            'hasAfter' => $hasAfter,
            'afterChosen' => $afterChosen,
            'afterDays' => $afterDays,
            'pickupActive' => $pickupActive,
            'pickupMandatory' => $pickupMandatory,
            'einfActive' => $einfActive,
            'einfCertified' => $einfCertified,
            // „Zusammen oder getrennt": nur sinnvoll, wenn Einführung UND Abholung anstehen. Default „zusammen".
            'combineEligible' => ($pickupActive && $einfActive),
            'handoverMode' => ($this->post('handover_mode') === 'getrennt') ? 'getrennt' : 'zusammen',
            'selStart' => $this->postedStart,
            'selEnd' => $this->postedEnd,
            'selPickupSlot' => $this->postedPickupSlot,
            'selEinfSlot' => $this->postedEinfSlot,
            'attributes' => $attributes,
            'maxDays' => $maxDays,
            'formNonce' => $nonce,
            'errors' => $errors,
        ]);
    }

    // --- Bundle/Item-Laden ---

    private function loadBundle($db, int $bid): ?array
    {
        if ($bid <= 0) {
            return null;
        }
        $cmd = new AdHocCommand('SELECT id, name, use_case, hint, active FROM zhl_bundle WHERE id = @id AND active = 1 LIMIT 1');
        $cmd->AddParameter(new Parameter('@id', $bid));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        if (!$row) {
            return null;
        }
        $bundle = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'use_case' => (string)($row['use_case'] ?? ''),
            'hint' => (string)($row['hint'] ?? ''),
            'items' => [],
        ];
        $ic = new AdHocCommand('SELECT id, type_label, quantity, required, alt_group, phase, meta, specific_resource_id FROM zhl_bundle_item WHERE bundle_id = @b ORDER BY sort_order, id');
        $ic->AddParameter(new Parameter('@b', $bid));
        $ir = $db->Query($ic);
        while ($r = $ir->GetRow()) {
            $bundle['items'][] = [
                'id' => (int)$r['id'],
                'type_label' => (string)$r['type_label'],
                'quantity' => (int)$r['quantity'],
                'required' => (int)$r['required'],
                'alt_group' => $r['alt_group'] !== null && $r['alt_group'] !== '' ? (string)$r['alt_group'] : null,
                'phase' => (string)($r['phase'] ?? 'main'),
                'meta' => $r['meta'] !== null && $r['meta'] !== '' ? (string)$r['meta'] : null,
                'specific_resource_id' => $r['specific_resource_id'] !== null ? (int)$r['specific_resource_id'] : null,
            ];
        }
        $ir->Free();
        return $bundle;
    }

    private function itemsForPhase(array $bundle, string $phase): array
    {
        $out = [];
        foreach ($bundle['items'] as $it) {
            if ((string)($it['phase'] ?? 'main') === $phase) {
                $out[] = $it;
            }
        }
        return $out;
    }

    private function bundleHasAfter(array $bundle): bool
    {
        foreach ($bundle['items'] as $it) {
            if ((string)($it['phase'] ?? 'main') === 'after' && (int)$it['quantity'] > 0) {
                return true;
            }
        }
        return false;
    }

    /** alt_group => gewählter type_label aus dem POST (nur Gruppen des Bundles). */
    private function collectAltChoices(array $bundle): array
    {
        $groups = [];
        foreach ($bundle['items'] as $it) {
            if (isset($it['alt_group']) && $it['alt_group'] !== '') {
                $groups[(string)$it['alt_group']] = true;
            }
        }
        $out = [];
        foreach (array_keys($groups) as $g) {
            $v = isset($_POST['alt_' . $g]) ? trim((string)$_POST['alt_' . $g]) : '';
            if ($v !== '') {
                $out[$g] = $v;
            }
        }
        return $out;
    }

    // --- Resolver / Verfügbarkeit ---

    private function buildResolver(UserSession $user): ZhlBundleResolver
    {
        $resourceService = new ResourceService(
            new ResourceRepository(),
            new SchedulePermissionService(PluginManager::Instance()->LoadPermission()),
            new AttributeService(new AttributeRepository()),
            new UserRepository(),
            new AccessoryRepository()
        );
        return new ZhlBundleResolver($resourceService, new ResourceAvailability(new ReservationViewRepository()));
    }

    /** Leitgerät-Ressourcen-ID für den Kalender-Proxy: erstes erforderliches Main-Item. */
    private function leitResourceId($db, UserSession $user, array $mainItems): int
    {
        foreach ($mainItems as $it) {
            if ((int)$it['quantity'] <= 0 || (int)$it['required'] !== 1) {
                continue;
            }
            if (isset($it['alt_group']) && $it['alt_group'] !== '') {
                continue; // Alternativ-Item nicht als Leitgerät.
            }
            if ($it['specific_resource_id'] !== null) {
                return (int)$it['specific_resource_id'];
            }
            $ids = $this->resourceIdsOfType($db, (string)$it['type_label']);
            if (!empty($ids)) {
                return (int)$ids[0];
            }
        }
        return 0;
    }

    /** Ressourcen-IDs eines Geräte-Typs (für Leitgerät/Alternativ-Modelle). */
    private function resourceIdsOfType($db, string $typeLabel): array
    {
        $cmd = new AdHocCommand(
            'SELECT v.entity_id AS rid FROM custom_attribute_values v ' .
            'JOIN custom_attributes a ON a.custom_attribute_id = v.custom_attribute_id ' .
            'WHERE a.display_label = @l AND a.attribute_category = @c AND v.attribute_value = @t ORDER BY v.entity_id'
        );
        $cmd->AddParameter(new Parameter('@l', 'Geräte-Typ'));
        $cmd->AddParameter(new Parameter('@c', CustomAttributeCategory::RESOURCE));
        $cmd->AddParameter(new Parameter('@t', $typeLabel));
        $reader = $db->Query($cmd);
        $ids = [];
        while ($r = $reader->GetRow()) {
            $ids[] = (int)$r['rid'];
        }
        $reader->Free();
        return $ids;
    }

    /** Alternativ-Gruppen für die Radios: alt_group => {options:[{type, models[]}]}. */
    private function buildAltGroups($db, UserSession $user, array $mainItems): array
    {
        $groups = [];
        foreach ($mainItems as $it) {
            if (!isset($it['alt_group']) || $it['alt_group'] === '') {
                continue;
            }
            $g = (string)$it['alt_group'];
            if (!isset($groups[$g])) {
                $groups[$g] = ['group' => $g, 'options' => []];
            }
            $models = [];
            foreach ($this->resourceIdsOfType($db, (string)$it['type_label']) as $rid) {
                $models[] = $this->resourceName($db, $rid);
            }
            $groups[$g]['options'][] = [
                'type' => (string)$it['type_label'],
                'models' => array_values(array_filter($models)),
            ];
        }
        return array_values($groups);
    }

    private function resourceName($db, int $rid): string
    {
        $cmd = new AdHocCommand('SELECT name FROM resources WHERE resource_id = @r LIMIT 1');
        $cmd->AddParameter(new Parameter('@r', $rid));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row ? (string)$row['name'] : '';
    }

    private function maxResourcesPerReservation($db, int $scheduleId): int
    {
        // max_resources_per_reservation ist eine SCHEDULE-Eigenschaft (0 = unlimitiert), nicht auf resources.
        $cmd = new AdHocCommand('SELECT max_resources_per_reservation AS m FROM schedules WHERE schedule_id = @s LIMIT 1');
        $cmd->AddParameter(new Parameter('@s', $scheduleId));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row && $row['m'] !== null ? (int)$row['m'] : 0;
    }

    /**
     * Kleinste max_duration (Sekunden) über die aufgelösten Bundle-Geräte (0 = unlimitiert).
     * Spiegelt die native ResourceMaximumDurationRule: das Gerät mit der kürzesten Maximaldauer
     * begrenzt das ganze Bundle (eine Reservierung = ein Zeitraum für alle Geräte).
     */
    private function bundleMaxDurationSeconds($db, array $resourceIds): int
    {
        $ids = array_values(array_filter(array_map('intval', $resourceIds), fn($i) => $i > 0));
        if (empty($ids)) {
            return 0;
        }
        $in = implode(',', $ids);
        $cmd = new AdHocCommand('SELECT MIN(max_duration) AS m FROM resources WHERE resource_id IN (' . $in . ') AND max_duration IS NOT NULL AND max_duration > 0');
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row && $row['m'] !== null ? (int)$row['m'] : 0;
    }

    /**
     * Pflicht-Reservierungs-Attribute, die für das (Primär-)Gerät gelten — gleiche Skip-Logik wie
     * AttributeService::Validate (Required + Unique/Secondary-Filter + AdminOnly). Identisch zur
     * Einzelbuchung (ZhlBookPresenter), damit z. B. „Haftpflichtversicherung" auch im Bundle erscheint
     * und die native Save-Validierung erfüllt wird.
     * @return CustomAttribute[]
     */
    private function applicableReservationAttributes(int $resourceId, bool $isAdmin): array
    {
        $svc = new AttributeService(new AttributeRepository());
        $ids = [$resourceId];
        $out = [];
        foreach ($svc->GetByCategory(CustomAttributeCategory::RESERVATION) as $a) {
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

    // --- Übergabe/Einführung (gegen die aufgelösten Main-Geräte) ---

    /** Erstes aufgelöstes Gerät, das eine Einführung braucht und für das der User NICHT zertifiziert ist. */
    private function firstResourceNeedingEinf($db, UserSession $user, array $resourceIds): ?array
    {
        foreach ($resourceIds as $rid) {
            $ueb = $this->lookupUebergabe($db, (int)$rid);
            if ($ueb['einfuehrung'] === 'notwendig' && !$this->userIsCertified($db, $user->UserId, (int)$rid)) {
                return ['resourceId' => (int)$rid] + $ueb;
            }
        }
        return null;
    }

    /** Erstes aufgelöstes Gerät, das abgeholt werden muss (abholen/abholen_persoenlich). */
    private function firstResourceNeedingPickup($db, array $resourceIds): ?array
    {
        foreach ($resourceIds as $rid) {
            $ueb = $this->lookupUebergabe($db, (int)$rid);
            if ($this->pickupApplies($ueb)) {
                return ['resourceId' => (int)$rid] + $ueb;
            }
        }
        return null;
    }

    /** Pickup-VM für die Anzeige (Proxy über die Typ-Geräte des Bundles). */
    private function buildPickupVm($db, UserSession $user, array $mainItems, string $aroundYmd, $tz): ?array
    {
        $rid = $this->firstTypeResourceNeeding($db, $mainItems, 'pickup');
        if ($rid <= 0) {
            return null;
        }
        $ueb = $this->lookupUebergabe($db, $rid);
        $loanStartUtc = Date::Parse($aroundYmd . ' 09:00', $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
        $f = $this->fetchHandoverSlots($this->tpConfig()['handover_type_label'] ?? 'Übergabe Medien', $ueb['tp_member_id'], $loanStartUtc, $tz);
        $mandatory = !$user->IsAdmin;
        return [
            'mandatory' => $mandatory,
            'days' => $this->groupPickupByDay($f['slots'], $tz),
            'typeId' => $f['typeId'],
            'memberId' => $f['memberId'],
            'earliestLabel' => $f['earliestLabel'],
            'blocked' => ($mandatory && empty($f['slots'])),
        ];
    }

    private function buildEinfVm($db, UserSession $user, array $mainItems, string $aroundYmd, $tz): ?array
    {
        $rid = $this->firstTypeResourceNeeding($db, $mainItems, 'einf');
        if ($rid <= 0) {
            return null;
        }
        if ($this->userIsCertified($db, $user->UserId, $rid)) {
            return ['certified' => true, 'slots' => [], 'blocked' => false];
        }
        $ueb = $this->lookupUebergabe($db, $rid);
        $loanStartUtc = Date::Parse($aroundYmd . ' 09:00', $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
        $f = $this->fetchEinfuehrungSlots($ueb['einfuehrung_typ'], $ueb['tp_member_id'], $loanStartUtc, $tz);
        return [
            'certified' => false,
            'slots' => $f['slots'],
            'days' => $this->groupPickupByDay($f['slots'], $tz),
            'typeId' => $f['typeId'],
            'memberId' => $f['memberId'],
            'earliestLabel' => $f['earliestLabel'],
            'blocked' => empty($f['slots']),
        ];
    }

    /** Erstes Typ-Gerät eines Main-Items, das Abholung ('pickup') bzw. Einführung ('einf') braucht. */
    private function firstTypeResourceNeeding($db, array $mainItems, string $kind): int
    {
        foreach ($mainItems as $it) {
            if ((int)$it['quantity'] <= 0) {
                continue;
            }
            $candidates = [];
            if ($it['specific_resource_id'] !== null) {
                $candidates[] = (int)$it['specific_resource_id'];
            } else {
                $candidates = $this->resourceIdsOfType($db, (string)$it['type_label']);
            }
            foreach ($candidates as $rid) {
                $ueb = $this->lookupUebergabe($db, (int)$rid);
                if ($kind === 'pickup' && $this->pickupApplies($ueb)) {
                    return (int)$rid;
                }
                if ($kind === 'einf' && $ueb['einfuehrung'] === 'notwendig') {
                    return (int)$rid;
                }
            }
        }
        return 0;
    }

    // --- Monats-Kalender (Tagesmodus, Proxy über das Leitgerät) ---

    private function monthGrid(UserSession $user, int $rid, string $aroundYmd): array
    {
        $tz = $user->Timezone;
        $scheduleId = $this->resourceScheduleId(ServiceLocator::GetDatabase(), $rid);
        $earliestYmd = Date::Now()->ToTimezone($tz)->Format('Y-m-d');
        $refYmd = ($aroundYmd > $earliestYmd) ? $aroundYmd : $earliestYmd;
        $ref = Date::Parse($refYmd . ' 00:00:00', $tz)->ToTimezone($tz);
        $year = (int)$ref->Format('Y');
        $month = (int)$ref->Format('m');
        $firstOfMonth = Date::Parse(sprintf('%04d-%02d-01 00:00:00', $year, $month), $tz);
        $dow = (int)$firstOfMonth->Format('N');
        $gridStart = $firstOfMonth->AddDays(-($dow - 1));

        $items = (new ResourceAvailability(new ReservationViewRepository()))->GetItemsBetween($gridStart, $gridStart->AddDays(42), [$rid]);
        $layout = (new ScheduleRepository())->GetLayout($scheduleId, new ScheduleLayoutFactory($tz));

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
                    $state = 'closed';
                } else {
                    $busy = false;
                    foreach ($items as $it) {
                        if ($it->GetStartDate()->LessThan($day->AddDays(1)) && $it->GetEndDate()->GreaterThan($day)) {
                            $busy = true;
                            break;
                        }
                    }
                    $state = $busy ? 'busy' : 'free';
                }
                $row[] = ['date' => $ymd, 'dom' => (int)$local->Format('j'), 'inMonth' => $inMonth, 'state' => $state, 'weekend' => $weekend];
            }
            $weeks[] = $row;
        }
        $months = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        return [
            'monthLabel' => $months[$month] . ' ' . $year,
            'prevMonth' => $firstOfMonth->AddDays(-1)->ToTimezone($tz)->Format('Y-m-01'),
            'nextMonth' => $firstOfMonth->AddDays(35)->ToTimezone($tz)->Format('Y-m-01'),
            'thisMonth' => Date::Now()->ToTimezone($tz)->Format('Y-m-01'),
            'weeks' => $weeks,
        ];
    }

    private function dayHasReservablePeriod($layout, $day): bool
    {
        foreach ($layout->GetLayout($day, false) as $p) {
            if (method_exists($p, 'IsReservable') && $p->IsReservable() && $p->BeginDate() !== null && $p->EndDate() !== null) {
                return true;
            }
        }
        return false;
    }

    private function scheduleDayBounds(UserSession $user, int $scheduleId, string $dayYmd): array
    {
        $tz = $user->Timezone;
        $layout = (new ScheduleRepository())->GetLayout($scheduleId, new ScheduleLayoutFactory($tz));
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
        return ['begin' => $beginStr, 'end' => $endStr, 'endNextDay' => ($endStr === '00:00')];
    }

    private function resourceScheduleId($db, int $rid): int
    {
        $cmd = new AdHocCommand('SELECT schedule_id FROM resources WHERE resource_id = @r LIMIT 1');
        $cmd->AddParameter(new Parameter('@r', $rid));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row ? (int)$row['schedule_id'] : self::SCHEDULE_BUNDLE;
    }

    /**
     * Kryptische native Reservierungs-Fehler in verständliche ZHL-Hinweise übersetzen (Sicherheitsnetz,
     * falls trotz korrekter Zeitberechnung ein Grenzfall die native Validierung auslöst).
     * @param string[] $errors
     * @return string[]
     */
    private function friendlyErrors(array $errors): array
    {
        $map = [
            'Die angeforderte Endzeit ist nicht gültig.' => 'Der Buchungszeitraum passt nicht auf die buchbaren Zeiten dieses Geräts. Bitte wähle Start- und End-Tag neu (ggf. einen Tag kürzer).',
            'Die angeforderte Startzeit ist nicht gültig.' => 'Der gewählte Starttag liegt außerhalb der buchbaren Zeiten dieses Geräts. Bitte einen anderen Tag wählen.',
        ];
        $out = [];
        foreach ($errors as $e) {
            $out[] = $map[trim((string)$e)] ?? (string)$e;
        }
        return $out;
    }

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
                $byDay[$ymd] = ['date' => $ymd, 'label' => self::WD[(int)$start->Format('N')] . ' ' . $start->Format('d.m.'), 'slots' => []];
            }
            $byDay[$ymd]['slots'][] = ['slot_id' => (string)$s['slot_id'], 'timeLabel' => $start->Format('H:i')];
        }
        ksort($byDay);
        return array_values($byDay);
    }

    // --- Terminplaner-Anbindung (gespiegelt aus ZhlBookPresenter) ---

    private function tpConfig(): array
    {
        $f = ROOT_DIR . 'config/zhl-handover.php';
        if (!is_readable($f)) {
            $f = ROOT_DIR . 'config/zhl-handover.example.php';
        }
        $c = is_readable($f) ? (require $f) : [];
        return is_array($c) ? $c : [];
    }

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
        $header = "X-API-Key: $key\r\nUser-Agent: zhl-bundle-book/1\r\n";
        $opt = ['method' => $method, 'timeout' => $timeout, 'ignore_errors' => true];
        if ($body !== null) {
            $header = "X-API-Key: $key\r\nContent-Type: application/json\r\nUser-Agent: zhl-bundle-book/1\r\n";
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
                    continue;
                }
                $out['slots'][] = [
                    'slot_id' => (string)$s['slot_id'],
                    'label' => (string)($s['label'] ?? $startUtc),
                    // start_utc/end_utc mitführen, sonst bricht persistEinfuehrung mit
                    // „startUtc === null" ab → es entsteht KEINE einf-Zeile (Termin nicht gespeichert).
                    'start_utc' => (string)$startUtc,
                    'end_utc' => $endUtc !== null ? (string)$endUtc : null,
                    'type_id' => isset($mem['type_id']) ? (int)$mem['type_id'] : null,
                    'member_id' => isset($mem['member_id']) ? (int)$mem['member_id'] : null,
                ];
            }
        }
        if ($earliest !== null) {
            $out['earliestLabel'] = Date::Parse($earliest, 'UTC')->ToTimezone($tz)->Format('d.m.Y, H:i') . ' Uhr';
        }
        return $out;
    }

    private function fetchHandoverSlots(?string $typeLabel, ?int $memberId, ?string $loanStartUtc, $tz): array
    {
        $out = ['slots' => [], 'earliestLabel' => null, 'typeId' => null, 'memberId' => null];
        if ($typeLabel === null || $typeLabel === '') {
            return $out;
        }
        $q = ['type_label' => $typeLabel];
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
                if ($loanStartUtc !== null && $endUtc !== null && $endUtc > $loanStartUtc) {
                    continue;
                }
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
                ];
            }
        }
        if ($earliest !== null) {
            $out['earliestLabel'] = Date::Parse($earliest, 'UTC')->ToTimezone($tz)->Format('d.m.Y, H:i') . ' Uhr';
        }
        return $out;
    }

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
        if (!$exists) {
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
    }

    /**
     * Einführungstermin lokal speichern (type='einf' in zhl_booking_handover) — analog zum
     * Single-Device-Flow, damit die Detailseite den Termin je Gerät mit Datum zeigt. Eigener
     * Token je Zeile; reference_number liegt hier schon vor. Best effort.
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
            Log::Error('ZHL-Bundle persistEinfuehrung fehlgeschlagen (ref=%s, res=%s): %s', $ref, $rid, $e);
        }
    }

    private function persistHandover($db, string $token, int $userId, int $rid, ?string $bookingId, int $staffMemberId, string $pickupStartUtc, string $pickupEndUtc, string $loanEndUtc): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        try {
            $insTok = new AdHocCommand('INSERT INTO zhl_handover_token (handover_token, user_id, created_at) VALUES (@tok,@u,@now)');
            $insTok->AddParameter(new Parameter('@tok', $token));
            $insTok->AddParameter(new Parameter('@u', $userId));
            $insTok->AddParameter(new Parameter('@now', $now));
            $db->Execute($insTok);

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

            $insReturn = new AdHocCommand('INSERT INTO zhl_booking_handover (handover_token, type, resource_id, scheduled_start_utc, scheduled_end_utc, status, created_at, updated_at) VALUES (@tok,@type,@rid,@start,@end,@status,@now,@now)');
            $insReturn->AddParameter(new Parameter('@tok', $token));
            $insReturn->AddParameter(new Parameter('@type', 'return'));
            $insReturn->AddParameter(new Parameter('@rid', $rid));
            $insReturn->AddParameter(new Parameter('@start', $loanEndUtc));
            $insReturn->AddParameter(new Parameter('@end', $loanEndUtc));
            $insReturn->AddParameter(new Parameter('@status', 'confirmed'));
            $insReturn->AddParameter(new Parameter('@now', $now));
            $db->Execute($insReturn);
            return true;
        } catch (Exception $e) {
            Log::Error('ZHL-Bundle persistHandover fehlgeschlagen (token=%s): %s', $token, $e);
            try {
                $del = new AdHocCommand('DELETE FROM zhl_booking_handover WHERE handover_token = @tok');
                $del->AddParameter(new Parameter('@tok', $token));
                $db->Execute($del);
                $delTok = new AdHocCommand('DELETE FROM zhl_handover_token WHERE handover_token = @tok');
                $delTok->AddParameter(new Parameter('@tok', $token));
                $db->Execute($delTok);
            } catch (Exception $e2) {
                Log::Error('ZHL-Bundle Cleanup nach Teil-Persistenz fehlgeschlagen (token=%s): %s', $token, $e2);
            }
            return false;
        }
    }

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
            Log::Error('ZHL-Bundle backfillHandoverReference fehlgeschlagen (token=%s): %s', $token, $e);
        }
    }

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

    /**
     * Übergabe-Modus: braucht das Gerät eine persönliche Abholung (Terminplaner-Slot)?
     * Entspannte Modi ('ablageort', 'nicht_noetig') brauchen KEINEN Abholtermin; alles andere
     * (inkl. Default/Legacy 'abholen', 'abholen_persoenlich') schon. Sichere Vorgabe: unkonfigurierte
     * Geräte = Abholung Pflicht (ZHL-Entscheidung 2026-06-26). Spiegelt ZhlBookPresenter::pickupApplies.
     */
    private function pickupApplies(array $ueb): bool
    {
        return !in_array($ueb['abholung'], ['ablageort', 'nicht_noetig'], true);
    }

    private function lookupUebergabe($db, int $rid): array
    {
        $def = ['abholung' => 'abholen', 'abholort' => null, 'einfuehrung' => 'keine', 'einfuehrung_typ' => null, 'tp_member_id' => null, 'vorlauf_toleranz_h' => 0, 'booking_mode' => 'day'];
        $cmd = new AdHocCommand('SELECT abholung, abholort, einfuehrung, einfuehrung_typ, tp_member_id, vorlauf_toleranz_h, booking_mode FROM zhl_uebergabe WHERE resource_id = @r LIMIT 1');
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
        ];
    }

    // --- Doppel-Submit-Nonce (leichtgewichtig, in der Session) ---

    private function issueNonce(): string
    {
        $nonce = bin2hex(random_bytes(16));
        $server = ServiceLocator::GetServer();
        $list = $server->GetSession('zhl_bundle_nonces');
        if (!is_array($list)) {
            $list = [];
        }
        $list[$nonce] = time();
        // Alte Nonces (> 1h) aufräumen, damit die Session nicht wächst.
        $cutoff = time() - 3600;
        foreach ($list as $k => $ts) {
            if ((int)$ts < $cutoff) {
                unset($list[$k]);
            }
        }
        $server->SetSession('zhl_bundle_nonces', $list);
        return $nonce;
    }

    private function consumeNonce(string $nonce): bool
    {
        if ($nonce === '') {
            return false;
        }
        $server = ServiceLocator::GetServer();
        $list = $server->GetSession('zhl_bundle_nonces');
        if (!is_array($list) || !isset($list[$nonce])) {
            return false;
        }
        unset($list[$nonce]);
        $server->SetSession('zhl_bundle_nonces', $list);
        return true;
    }

    // --- Eingabe-Helfer ---

    private function clampAfterDays(int $d): int
    {
        if ($d < 3) {
            return 3;
        }
        if ($d > 7) {
            return 7;
        }
        return $d;
    }

    private function today($tz): string
    {
        return Date::Now()->ToTimezone($tz)->Format('Y-m-d');
    }

    private function isYmd($v): bool
    {
        return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)
            && checkdate((int)substr($v, 5, 2), (int)substr($v, 8, 2), (int)substr($v, 0, 4));
    }

    private function readInt($key, $default)
    {
        $v = isset($_GET[$key]) ? (string)$_GET[$key] : '';
        return ($v === '' || !is_numeric($v)) ? $default : (int)$v;
    }

    private function readDate($key, $tz)
    {
        $v = isset($_GET[$key]) ? (string)$_GET[$key] : '';
        return $this->isYmd($v) ? $v : $this->today($tz);
    }

    private function post($key)
    {
        return isset($_POST[$key]) ? (string)$_POST[$key] : '';
    }
}

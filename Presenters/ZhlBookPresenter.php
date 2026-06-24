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

    public function __construct($page)
    {
        $this->page = $page;
    }

    /** GET: Buchungsformular anzeigen (oder Erfolgs-Panel nach Redirect). */
    public function PageLoad(UserSession $user)
    {
        $booked = isset($_GET['booked']) ? trim((string)$_GET['booked']) : '';
        if ($booked !== '') {
            $this->page->BindSuccess(['referenceNumber' => $booked]);
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
        $db = ServiceLocator::GetDatabase();
        $ueb = $this->lookupUebergabe($db, $rid);

        if ($ueb['booking_mode'] === 'slot') {
            $beginDate = $this->postDate('slotDay', $tz);
            $endDate = $beginDate;
            $sb = $this->post('slotBegin');
            $se = $this->post('slotEnd');
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $sb) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $se) || strcmp($se, $sb) <= 0) {
                $this->bindForm($user, $resource, $beginDate, '09:00', $endDate, '11:00', $choice, ['Bitte wähle im Wochen-Raster eine freie Zeitspanne (Start- und End-Feld anklicken).']);
                return;
            }
            $beginTime = $sb;
            $endTime = $se;
        } else {
            $beginDate = $this->postDate('beginDate', $tz);
            $dd = (int)$this->post('durationDays');
            $durationDays = ($dd >= 1 && $dd <= 31) ? $dd : 1;
            // Zeiten an die buchbaren Schedule-Grenzen ausrichten (sonst lehnt SchedulePeriodRule ab).
            $bounds = $this->scheduleDayBounds($user, $resource, $beginDate);
            $beginTime = $bounds['begin'];
            $endTime = $bounds['end'];
            $endOffset = $bounds['endNextDay'] ? $durationDays : ($durationDays - 1);
            $endDate = Date::Parse($beginDate . ' 00:00:00', $tz)->AddDays($endOffset)->Format('Y-m-d');
        }

        $type = $this->lookupType($db, $rid);
        $titel = 'Ausleihe: ' . ($type !== null ? $type : $resource->GetName());
        $desc = $this->uebergabeNote($ueb);

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

        // --- Einführungs-Gate (US-16/US-20): Zertifikat ODER Terminplaner-Slot, je nach Gerät ---
        $certified = $ueb['einfuehrung'] === 'keine' ? true : $this->userIsCertified($db, $user->UserId, $rid);
        $einfRequired = ($ueb['einfuehrung'] === 'notwendig') && !$certified;
        $chosenSlot = $this->post('einf_slot');
        $einfTypeId = (int)$this->post('einf_type_id');
        $einfMemberId = (int)$this->post('einf_member_id');
        $einfBooked = false;

        if ($einfRequired && $chosenSlot === '') {
            $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Für dieses Gerät ist eine Einführung nötig — bitte wähle zuerst einen Einführungstermin (oder es ist aktuell keiner vor deinem Ausleihstart frei).'], $attrValues);
            return;
        }

        if ($chosenSlot !== '' && !$certified && $ueb['einfuehrung'] !== 'keine') {
            // Reihenfolge laut Entscheidung: erst Slot buchen, dann Reservierung.
            $book = $this->tpRequest('POST', '/api/book_slot.php', [], [
                'member_id' => $einfMemberId,
                'type_id' => $einfTypeId,
                'slot_id' => $chosenSlot,
                'name' => trim($user->FirstName . ' ' . $user->LastName),
                'email' => $user->Email,
                'note' => 'ZHL Medienausleihe — ' . $titel,
            ]);
            if (!$book || ($book['status'] ?? '') !== 'ok') {
                $msg = isset($book['error']) ? (string)$book['error'] : 'Der Einführungstermin konnte nicht gebucht werden (evtl. nicht mehr verfügbar). Bitte einen anderen Termin wählen.';
                $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Einführungstermin: ' . $msg], $attrValues);
                return;
            }
            $einfBooked = true;
            $desc .= ' · Einführung gebucht (' . (string)$ueb['einfuehrung_typ'] . ', #' . (string)($book['booking_id'] ?? '') . ')';
            // Bestätigungs-Workflow: Einweiser bestätigt nach dem Termin → Zertifikat (US-16/F40).
            $this->createCertConfirmation($db, (int)$user->UserId, $rid);
        }

        $facade = new ZhlReservationFacade($user->UserId, $rid, $titel, $desc, $beginDate, $beginTime, $endDate, $endTime, $facadeAttrs);
        try {
            $factory = new ReservationPresenterFactory();
            $presenter = $factory->Create($facade, $user);
            $series = $presenter->BuildReservation();
            $presenter->HandleReservation($series);
        } catch (Exception $ex) {
            Log::Error('ZHL-Buchung fehlgeschlagen: %s', $ex);
            $errs = ['Unerwarteter Fehler beim Buchen. Bitte erneut versuchen.'];
            if ($einfBooked) {
                $errs[] = 'Hinweis: Dein Einführungstermin wurde bereits gebucht — bitte beim ZHL-Team melden, falls die Ausleihe nicht zustande kommt.';
            }
            $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, $errs, $attrValues);
            return;
        }

        if ($facade->WasSaved()) {
            $this->page->RedirectToSuccess($facade->ReferenceNumber());
            return;
        }

        $errors = $facade->GetErrors();
        if (empty($errors)) {
            $errors = ['Die Buchung konnte nicht angelegt werden.'];
        }
        if ($einfBooked) {
            $errors[] = 'Hinweis: Dein Einführungstermin wurde bereits gebucht — bitte beim ZHL-Team melden, falls die Ausleihe nicht zustande kommt.';
        }
        $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, $errors, $attrValues);
    }

    // --- intern ---

    private function bindForm(UserSession $user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, array $errors, array $attrValues = [])
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
            'earliestLabel' => null,
            'typeId' => null,
            'memberId' => null,
            'blocked' => false,
        ];
        if ($ueb['einfuehrung'] !== 'keine') {
            $einf['certified'] = $this->userIsCertified($db, $user->UserId, $rid);
            if (!$einf['certified']) {
                $loanStartUtc = Date::Parse($beginDate . ' ' . $beginTime, $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
                $f = $this->fetchEinfuehrungSlots($ueb['einfuehrung_typ'], $ueb['tp_member_id'], $loanStartUtc, $tz);
                $einf['slots'] = $f['slots'];
                $einf['earliestLabel'] = $f['earliestLabel'];
                $einf['typeId'] = $f['typeId'];
                $einf['memberId'] = $f['memberId'];
                $einf['blocked'] = ($ueb['einfuehrung'] === 'notwendig' && empty($f['slots']));
            }
        }

        // Termin-Vorschläge: Wochen-Raster (Slotmodus, frei wählbare Spanne) bzw. freie Starttage (Tagesmodus).
        $picker = ['mode' => $ueb['booking_mode'], 'days' => [], 'grid' => null];
        if ($ueb['booking_mode'] === 'slot') {
            $picker['grid'] = $this->weekGrid($user, $resource, $requestedDate, $noticeSec);
        } else {
            $picker['days'] = $this->availableDays($user, $resource, $beginDate, $noticeSec, 14);
        }

        $this->page->BindBooking([
            'resourceId' => $rid,
            'scheduleId' => (int)$resource->ScheduleId,
            'resourceName' => (string)$resource->GetName(),
            'resourceType' => $type,
            'scheduleName' => $scheduleName,
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
        $items = (new ResourceAvailability(new ReservationViewRepository()))->GetItemsBetween($from, $to, [(int)$resource->GetId()]);
        $out = [];
        for ($dd = 0; $dd < $windowDays && count($out) < $maxResults; $dd++) {
            $dayStart = $from->AddDays($dd);
            $dayEnd = $from->AddDays($dd + 1);
            $busy = false;
            foreach ($items as $it) {
                if ($it->GetStartDate()->LessThan($dayEnd) && $it->GetEndDate()->GreaterThan($dayStart)) {
                    $busy = true;
                    break;
                }
            }
            if ($busy) {
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

        // Buchbare Perioden (sortiert) sammeln.
        $resv = (new ResourceAvailability(new ReservationViewRepository()))->GetItemsBetween($day, $day->AddDays(1), [(int)$resource->GetId()]);
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
            $free = true;
            foreach ($resv as $it) {
                if ($it->GetStartDate()->LessThan($end) && $it->GetEndDate()->GreaterThan($begin)) {
                    $free = false;
                    break;
                }
            }
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

        $days = [];
        $hours = [];
        for ($dn = 0; $dn < 5; $dn++) {
            $day = $weekStart->AddDays($dn);
            $periods = $layout->GetLayout($day, false);
            $resv = (new ResourceAvailability(new ReservationViewRepository()))->GetItemsBetween($day, $day->AddDays(1), [(int)$resource->GetId()]);
            $cells = [];
            foreach ($periods as $p) {
                if (!method_exists($p, 'IsReservable') || !$p->IsReservable() || $p->BeginDate() === null || $p->EndDate() === null) {
                    continue;
                }
                $b = $p->BeginDate();
                $e = $p->EndDate();
                if ($b->LessThan($earliest)) {
                    $state = 'past';
                } else {
                    $busy = false;
                    foreach ($resv as $it) {
                        if ($it->GetStartDate()->LessThan($e) && $it->GetEndDate()->GreaterThan($b)) {
                            $busy = true;
                            break;
                        }
                    }
                    $state = $busy ? 'busy' : 'free';
                }
                $cells[] = ['h' => $b->ToTimezone($tz)->Format('H:i'), 'he' => $e->ToTimezone($tz)->Format('H:i'), 'state' => $state];
            }
            $local = $day->ToTimezone($tz);
            $days[] = ['date' => $local->Format('Y-m-d'), 'label' => self::WD[(int)$local->Format('N')], 'dm' => $local->Format('d.m.'), 'cells' => $cells];
        }
        if (!empty($days)) {
            foreach ($days[0]['cells'] as $c) {
                $hours[] = $c['h'];
            }
        }
        $wsLocal = $weekStart->ToTimezone($tz);
        $weLocal = $weekStart->AddDays(4)->ToTimezone($tz);
        return [
            'weekStart' => $wsLocal->Format('Y-m-d'),
            'prevWeek' => $weekStart->AddDays(-7)->ToTimezone($tz)->Format('Y-m-d'),
            'nextWeek' => $weekStart->AddDays(7)->ToTimezone($tz)->Format('Y-m-d'),
            'thisWeek' => $mondayToday->Format('Y-m-d'),
            'weekLabel' => $wsLocal->Format('d.m.') . ' – ' . $weLocal->Format('d.m.Y'),
            'hours' => $hours,
            'days' => $days,
        ];
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

    /** Notiz für die Reservierungs-Beschreibung aus der Übergabe-Konfiguration. */
    private function uebergabeNote(array $ueb): string
    {
        $parts = [];
        if ($ueb['abholung'] === 'abholen_persoenlich') {
            $parts[] = 'Abholung (persönlich)' . ($ueb['abholort'] ? ': ' . $ueb['abholort'] : '');
        } elseif ($ueb['abholung'] === 'abholen') {
            $parts[] = 'Abholung' . ($ueb['abholort'] ? ': ' . $ueb['abholort'] : '');
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

<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Schedule/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Attributes/namespace.php');
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

        $beginDate = $this->postDate('beginDate', $tz);
        $endDate = $this->postDate('endDate', $tz);
        $beginTime = $this->postTime('beginPeriod', '09:00');
        $endTime = $this->postTime('endPeriod', '17:00');
        $choice = $this->post('handoverChoice') === 'training' ? 'training' : 'pickup';

        $db = ServiceLocator::GetDatabase();
        $type = $this->lookupType($db, $rid);
        $titel = 'Ausleihe: ' . ($type !== null ? $type : $resource->GetName());
        // Übergabe-Anforderung des Geräts als Notiz an die Reservierung mitführen (v-book-3).
        $ueb = $this->lookupUebergabe($db, $rid);
        $desc = $this->uebergabeNote($ueb);

        $facade = new ZhlReservationFacade($user->UserId, $rid, $titel, $desc, $beginDate, $beginTime, $endDate, $endTime);
        try {
            $factory = new ReservationPresenterFactory();
            $presenter = $factory->Create($facade, $user);
            $series = $presenter->BuildReservation();
            $presenter->HandleReservation($series);
        } catch (Exception $ex) {
            Log::Error('ZHL-Buchung fehlgeschlagen: %s', $ex);
            $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, ['Unerwarteter Fehler beim Buchen. Bitte erneut versuchen.']);
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
        $this->bindForm($user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, $errors);
    }

    // --- intern ---

    private function bindForm(UserSession $user, $resource, $beginDate, $beginTime, $endDate, $endTime, $choice, array $errors)
    {
        $db = ServiceLocator::GetDatabase();
        $tz = $user->Timezone;
        $rid = (int)$resource->GetId();
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
            'errors' => $errors,
        ]);
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
        $def = ['abholung' => 'abholen', 'abholort' => null, 'einfuehrung' => 'keine', 'einfuehrung_typ' => null, 'tp_member_id' => null, 'vorlauf_toleranz_h' => 0];
        $cmd = new AdHocCommand('SELECT abholung, abholort, einfuehrung, einfuehrung_typ, tp_member_id, vorlauf_toleranz_h FROM zhl_uebergabe WHERE resource_id = @r LIMIT 1');
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

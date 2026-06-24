<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Schedule/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Attributes/namespace.php');

/**
 * ZHL-Buchungs-Schritt (v-book, Stufe 1: Anzeige). Eine ZHL-gestylte Seite, die nach der Geräte-Wahl
 * statt der nativen reservation.php erscheint: zeigt das gewählte Gerät (Laien-Tag + Modellname),
 * den Zeitraum (mit Vorlauf/frühestem Start) und die Auswahl „Abholung vs. Einführung".
 *
 * Stufe 1 ist READ-ONLY (kein eigener Write); der „Weiter"-Button übergibt vorerst an die native
 * reservation.php. Stufe 2 ersetzt das durch einen eigenen Write über die native Validierung
 * (ReservationPresenterFactory/Facade), Stufe 3 verdrahtet Abholung/Einführung mit dem Übergabe-Modul.
 */
class ZhlBookPresenter
{
    private $page;

    public function __construct($page)
    {
        $this->page = $page;
    }

    public function PageLoad(UserSession $user)
    {
        $tz = $user->Timezone;
        $rid = $this->readInt(QueryStringKeys::RESOURCE_ID, 0);
        $dateStr = $this->readDate(QueryStringKeys::RESERVATION_DATE, $tz);

        $resourceService = new ResourceService(
            new ResourceRepository(),
            new SchedulePermissionService(PluginManager::Instance()->LoadPermission()),
            new AttributeService(new AttributeRepository()),
            new UserRepository(),
            new AccessoryRepository()
        );

        // Permission-gefiltert laden — nur Geräte, die der Nutzer buchen darf.
        $resource = null;
        foreach ($resourceService->GetAllResources(false, $user) as $r) {
            if ((int)$r->GetId() === $rid && $r->StatusId != ResourceStatus::HIDDEN) {
                $resource = $r;
                break;
            }
        }
        if ($resource === null) {
            $this->page->RedirectToDashboard();
            return;
        }

        $db = ServiceLocator::GetDatabase();
        $type = $this->lookupType($db, $rid);
        $scheduleName = $this->lookupScheduleName($db, (int)$resource->ScheduleId);

        // Vorlauf: frühester buchbarer Tag (identisch zur nativen ResourceMinimumNoticeRuleAdd).
        $noticeSec = $this->lookupMinNotice($db, $rid);
        $earliestDateStr = $dateStr;
        $minNoticeDays = 0;
        $earliestLabel = null;
        if ($noticeSec > 0) {
            $earliest = Date::Now()->ApplyDifference(TimeInterval::Parse($noticeSec)->Interval())->ToTimezone($tz);
            $minNoticeDays = (int)ceil($noticeSec / 86400);
            $earliestLabel = $earliest->Format('d.m.Y');
            // Default-Datum nie vor dem frühesten Start.
            if ($dateStr < $earliest->Format('Y-m-d')) {
                $earliestDateStr = $earliest->Format('Y-m-d');
            }
        }

        $this->page->BindBooking([
            'resourceId' => $rid,
            'scheduleId' => (int)$resource->ScheduleId,
            'resourceName' => (string)$resource->GetName(),
            'resourceType' => $type,
            'scheduleName' => $scheduleName,
            'beginDate' => $earliestDateStr,
            'endDate' => $earliestDateStr,
            'beginTime' => '09:00',
            'endTime' => '17:00',
            'minNoticeDays' => $minNoticeDays,
            'earliestLabel' => $earliestLabel,
        ]);
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
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return $v;
        }
        return Date::Now()->ToTimezone($tz)->Format('Y-m-d');
    }
}

<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Schedule/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Attributes/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlAvailabilityService.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlBundleService.php');

/**
 * Presenter der ZHL-Dashboard-Startseite (verfügbarkeit-first Raster).
 * Read-only: liest validierte GET-Parameter, baut das Raster über den ZHL-Verfügbarkeits-
 * Service und reicht ein View-Model an die Page. Buchen läuft danach über die native
 * Reservierungsseite (reservation.php).
 */
class ZhlDashboardPresenter
{
    /** @var IZhlDashboardPage */
    private $page;

    public function __construct(IZhlDashboardPage $page)
    {
        $this->page = $page;
    }

    public function PageLoad(UserSession $user)
    {
        $tz = $user->Timezone;

        $startStr = $this->readDate('start', $tz);
        $days = $this->readInt('days', 7, 1, 31);
        $scheduleId = $this->readInt('schedule', 0, 0, PHP_INT_MAX);
        $search = trim((string)$this->readRaw('q'));

        $start = Date::Parse($startStr, $tz);

        $db = ServiceLocator::GetDatabase();
        $typeAttributeId = $this->lookupTypeAttributeId($db);

        $resourceService = new ResourceService(
            new ResourceRepository(),
            new SchedulePermissionService(PluginManager::Instance()->LoadPermission()),
            new AttributeService(new AttributeRepository()),
            new UserRepository(),
            new AccessoryRepository()
        );
        $service = new ZhlAvailabilityService(
            $resourceService,
            new ResourceAvailability(new ReservationViewRepository()),
            $db,
            $typeAttributeId
        );
        $grid = $service->BuildGrid($user, $start, $days, $scheduleId, $search);

        // Bundles: Verfügbarkeit aus dem VOLLEN Pool (unabhängig vom Kategorie-/Such-Filter des Rasters).
        $fullPools = ($scheduleId === 0 && $search === '')
            ? $grid['pools']
            : $service->BuildGrid($user, $start, $days, 0, '')['pools'];
        $typeFreeMap = [];
        foreach ($fullPools as $pool) {
            $typeFreeMap[$pool['type']] = $pool['free'];
        }
        $bundles = (new ZhlBundleService($db))->GetBundles($typeFreeMap);

        // Kategorien = Schedules, nur solche mit sichtbaren Geräten.
        $schedules = (new ScheduleRepository())->GetAll();
        $categories = [];
        foreach ($schedules as $s) {
            $sid = (int)$s->GetId();
            $count = $grid['categoryCounts'][$sid] ?? 0;
            if ($count === 0) {
                continue;
            }
            $categories[] = ['id' => $sid, 'name' => (string)$s->GetName(), 'count' => $count];
        }

        $end = $start->AddDays($days);
        $this->page->BindDashboard([
            'categories' => $categories,
            'rows' => $grid['rows'],
            'pools' => $grid['pools'],
            'bundles' => $bundles,
            'activeSchedule' => $scheduleId,
            'search' => $search,
            'startInput' => $start->Format('Y-m-d'),
            'days' => $days,
            'isAdmin' => ($user->IsAdmin || $user->IsResourceAdmin || $user->IsScheduleAdmin || $user->IsGroupAdmin),
            'rangeLabel' => $start->ToTimezone($tz)->Format('d.m.Y') . ' – '
                . $end->AddDays(-1)->ToTimezone($tz)->Format('d.m.Y'),
            'totalVisible' => $grid['totalVisible'],
        ]);
    }

    private function lookupTypeAttributeId($db)
    {
        $cmd = new AdHocCommand(
            "SELECT custom_attribute_id FROM custom_attributes " .
            "WHERE display_label = @label AND attribute_category = 4 LIMIT 1"
        );
        $cmd->AddParameter(new Parameter('@label', 'Geräte-Typ'));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row ? (int)$row['custom_attribute_id'] : 0;
    }

    private function readRaw($key)
    {
        return isset($_GET[$key]) ? (string)$_GET[$key] : '';
    }

    private function readInt($key, $default, $min, $max)
    {
        $v = $this->readRaw($key);
        if ($v === '' || !is_numeric($v)) {
            return $default;
        }
        $n = (int)$v;
        if ($n < $min) {
            $n = $min;
        }
        if ($n > $max) {
            $n = $max;
        }
        return $n;
    }

    private function readDate($key, $tz)
    {
        $v = $this->readRaw($key);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            return $v;
        }
        return Date::Now()->ToTimezone($tz)->Format('Y-m-d');
    }
}

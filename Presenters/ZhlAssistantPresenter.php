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
 * Vorhaben-Assistent (Dashboard v3, v1). Geführter Einstieg: „Was hast du vor?" → ein Bundle-Vorschlag
 * mit Live-Verfügbarkeit + den wichtigen Hinweisen (z. B. Einweisung). Read-only; Buchung läuft danach
 * über die Geräteliste (Trichter → native Buchung). Reuse von ZhlAvailabilityService/ZhlBundleService.
 */
class ZhlAssistantPresenter
{
    // US-15: Geräte-Typ des Schnittplatzes (Pool-/Such-Schlüssel; nicht der Modellname).
    private const SCHNITT_TYPE = 'Schnitt-/VR-PC';

    private $page;

    public function __construct($page)
    {
        $this->page = $page;
    }

    public function PageLoad(UserSession $user)
    {
        $tz = $user->Timezone;
        $startStr = $this->readDate('start', $tz);
        $days = $this->readInt('days', 7, 1, 31);
        $bundleId = $this->readInt('bundle', 0, 0, PHP_INT_MAX);
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
        $avail = new ZhlAvailabilityService($resourceService, new ResourceAvailability(new ReservationViewRepository()), $db, $typeAttributeId);
        $pools = $avail->BuildGrid($user, $start, $days, 0, '')['pools'];
        $typeFreeMap = [];
        foreach ($pools as $pool) {
            $typeFreeMap[$pool['type']] = $pool['free'];
        }

        $bundles = (new ZhlBundleService($db))->GetBundles($typeFreeMap);
        $selected = null;
        foreach ($bundles as $b) {
            if ($b->id === $bundleId) {
                $selected = $b;
                break;
            }
        }

        $end = $start->AddDays($days);
        // Schnitt-Folgebuchung (US-15) startet am Tag nach der Drehphase, eigener Zeitraum.
        $schnittStart = $end->ToTimezone($tz);
        $schnittPool = isset($typeFreeMap[self::SCHNITT_TYPE]) ? (int)$typeFreeMap[self::SCHNITT_TYPE] : null;
        $this->page->BindAssistant([
            'bundles' => $bundles,
            'selected' => $selected,
            'startInput' => $start->Format('Y-m-d'),
            'days' => $days,
            'rangeLabel' => $start->ToTimezone($tz)->Format('d.m.Y') . ' – ' . $end->AddDays(-1)->ToTimezone($tz)->Format('d.m.Y'),
            'schnittType' => self::SCHNITT_TYPE,
            'schnittStartInput' => $schnittStart->Format('Y-m-d'),
            'schnittPool' => $schnittPool,
        ]);
    }

    private function lookupTypeAttributeId($db)
    {
        $cmd = new AdHocCommand("SELECT custom_attribute_id FROM custom_attributes WHERE display_label = @l AND attribute_category = 4 LIMIT 1");
        $cmd->AddParameter(new Parameter('@l', 'Geräte-Typ'));
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
        return max($min, min($max, (int)$v));
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

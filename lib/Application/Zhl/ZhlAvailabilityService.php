<?php

/**
 * ZHL-Verfügbarkeits-Service (Dashboard v1/v2) — die EINE Wahrheitsquelle fürs
 * verfügbarkeit-first Raster (SPEC-UX-DASHBOARD §11.1).
 *
 * v1: rechte-gefilterte Ressourcen (ResourceService) + Belegung inkl. Blackouts
 *     (ResourceAvailability::GetItemsBetween, Interface IReservedItemView → gilt für
 *     Reservierung UND Blackout) → Tagesverfügbarkeit pro Gerät.
 * v2: „Geräte-Typ" (Ressourcen-Custom-Attribut) als Laien-Tag (US-6/US-7) + Pool-Schlüssel
 *     (US-12/US-14). Typ wird als verständlicher Titel gezeigt, fließt in die Suche ein und
 *     wird zu Pools aggregiert („N von M frei").
 *
 * READ-ONLY / PROGNOSE: Anzeige ist eine Vorschau; die verbindliche Prüfung bleibt die native
 * Save-Validierung beim eigentlichen Buchen (Codex-Gate §11). Hier wird NICHTS angelegt.
 */
class ZhlResourceAvailabilityRow
{
    /** @var int */    public $id;
    /** @var string */ public $name;          // Modell-/Inventarname (z. B. "DJI mic 1")
    /** @var string|null */ public $type = null; // Laien-Tag / Geräte-Typ (z. B. "Funkmikrofon")
    /** @var int */    public $scheduleId;
    /** @var array[] */ public $days = [];     // [{label, weekday, date, free}]
    /** @var int */    public $freeCount = 0;
    /** @var int */    public $totalDays = 0;
    /** @var bool */   public $anyFree = false;
    /** @var string|null */ public $nextFreeLabel = null;
}

class ZhlAvailabilityService
{
    /** @var IResourceService */
    private $resourceService;

    /** @var IResourceAvailabilityStrategy */
    private $availability;

    /** @var Database */
    private $db;

    /** @var int  custom_attribute_id des „Geräte-Typ" (0 = nicht vorhanden → ohne Tags/Pools) */
    private $typeAttributeId;

    /** ISO-Wochentag (1=Mo … 7=So) → deutsches Kürzel */
    private const WEEKDAYS = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

    public function __construct(IResourceService $resourceService, IResourceAvailabilityStrategy $availability, $db, int $typeAttributeId)
    {
        $this->resourceService = $resourceService;
        $this->availability = $availability;
        $this->db = $db;
        $this->typeAttributeId = $typeAttributeId;
    }

    /**
     * @return array{rows: ZhlResourceAvailabilityRow[], categoryCounts: array<int,int>, totalVisible: int, pools: array[]}
     */
    public function BuildGrid(UserSession $user, Date $start, int $days, int $scheduleId, string $search)
    {
        $tz = $user->Timezone;
        $allResources = $this->resourceService->GetAllResources(false, $user);
        $typeMap = $this->GetTypeMap();

        // Kategorie-Zählung über ALLE sichtbaren Geräte (vor Such-/Kategorie-Filter).
        $categoryCounts = [];
        $totalVisible = 0;
        foreach ($allResources as $r) {
            if ($r->StatusId == ResourceStatus::HIDDEN) {
                continue;
            }
            $totalVisible++;
            $sid = (int)$r->ScheduleId;
            $categoryCounts[$sid] = ($categoryCounts[$sid] ?? 0) + 1;
        }

        // Geräte fürs Raster filtern (Kategorie + Suche über Name ODER Typ).
        $resources = [];
        $resourceIds = [];
        foreach ($allResources as $r) {
            if ($r->StatusId == ResourceStatus::HIDDEN) {
                continue;
            }
            if ($scheduleId > 0 && (int)$r->ScheduleId !== $scheduleId) {
                continue;
            }
            $type = $typeMap[(int)$r->GetId()] ?? '';
            if ($search !== ''
                && stripos((string)$r->GetName(), $search) === false
                && stripos($type, $search) === false) {
                continue;
            }
            $resources[] = $r;
            $resourceIds[] = $r->GetId();
        }

        $end = $start->AddDays($days);
        $items = empty($resourceIds) ? [] : $this->availability->GetItemsBetween($start, $end, $resourceIds);

        // Belegung je Ressource indexieren — Interface-Methoden gelten für Reservierung UND Blackout.
        $byResource = [];
        foreach ($items as $item) {
            $byResource[$item->GetResourceId()][] = $item;
        }

        $rows = [];
        foreach ($resources as $r) {
            $row = new ZhlResourceAvailabilityRow();
            $row->id = (int)$r->GetId();
            $row->name = (string)$r->GetName();
            $row->type = $typeMap[(int)$r->GetId()] ?? null;
            $row->scheduleId = (int)$r->ScheduleId;
            $row->totalDays = $days;

            $resItems = $byResource[$r->GetId()] ?? [];
            for ($d = 0; $d < $days; $d++) {
                $dayStart = $start->AddDays($d);
                $dayEnd = $start->AddDays($d + 1);

                $busy = false;
                foreach ($resItems as $item) {
                    if ($item->GetStartDate()->LessThan($dayEnd) && $item->GetEndDate()->GreaterThan($dayStart)) {
                        $busy = true;
                        break;
                    }
                }
                $free = !$busy;

                $localDay = $dayStart->ToTimezone($tz);
                $weekday = self::WEEKDAYS[(int)$localDay->Format('N')] ?? '';
                $row->days[] = [
                    'label' => $localDay->Format('d.m.'),
                    'weekday' => $weekday,
                    'date' => $localDay->Format('Y-m-d'),
                    'free' => $free,
                ];
                if ($free) {
                    $row->freeCount++;
                    if ($row->nextFreeLabel === null) {
                        $row->nextFreeLabel = trim($weekday . ' ' . $localDay->Format('d.m.'));
                    }
                }
            }
            $row->anyFree = $row->freeCount > 0;
            $rows[] = $row;
        }

        return [
            'rows' => $rows,
            'categoryCounts' => $categoryCounts,
            'totalVisible' => $totalVisible,
            'pools' => $this->BuildPools($rows),
        ];
    }

    /**
     * Pool-Zählung je Geräte-Typ über die gefilterten Zeilen (US-12/US-14): wie viele Einheiten
     * eines Typs sind im Zeitraum überhaupt (an mind. einem Tag) frei.
     * @param ZhlResourceAvailabilityRow[] $rows
     * @return array[] [{type, total, free}]
     */
    private function BuildPools(array $rows)
    {
        $pools = [];
        foreach ($rows as $row) {
            $t = $row->type;
            if ($t === null || $t === '') {
                continue;
            }
            if (!isset($pools[$t])) {
                $pools[$t] = ['type' => $t, 'total' => 0, 'free' => 0];
            }
            $pools[$t]['total']++;
            if ($row->anyFree) {
                $pools[$t]['free']++;
            }
        }
        $list = array_values($pools);
        usort($list, function ($a, $b) {
            return $b['total'] <=> $a['total'] ?: strcmp($a['type'], $b['type']);
        });
        return $list;
    }

    /**
     * @return array<int,string>  resource_id → Geräte-Typ
     */
    private function GetTypeMap()
    {
        $map = [];
        if ($this->typeAttributeId <= 0) {
            return $map;
        }
        $cmd = new AdHocCommand(
            'SELECT entity_id, attribute_value FROM custom_attribute_values ' .
            'WHERE custom_attribute_id = @aid AND attribute_category = 4'
        );
        $cmd->AddParameter(new Parameter('@aid', $this->typeAttributeId));
        $reader = $this->db->Query($cmd);
        while ($row = $reader->GetRow()) {
            $val = trim((string)$row['attribute_value']);
            if ($val !== '') {
                $map[(int)$row['entity_id']] = $val;
            }
        }
        $reader->Free();
        return $map;
    }
}

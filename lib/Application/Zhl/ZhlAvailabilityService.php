<?php

/**
 * ZHL-Verfügbarkeits-Service (Dashboard v1) — die EINE Wahrheitsquelle fürs
 * verfügbarkeit-first Raster (SPEC-UX-DASHBOARD §11.1).
 *
 * Liest rechte-gefilterte Ressourcen (ResourceService::GetAllResources, respektiert
 * Permissions) und deren Belegung inkl. Blackouts (ResourceAvailability::GetItemsBetween,
 * Interface-Methoden gelten für Reservierung UND Blackout) und berechnet pro Gerät die
 * Tagesverfügbarkeit im gewählten Zeitraum.
 *
 * READ-ONLY / PROGNOSE: Die Anzeige ist eine Vorschau. Die verbindliche Prüfung bleibt die
 * native Save-Validierung beim eigentlichen Buchen (Codex-Gate §11). Hier wird KEINE
 * Reservierung angelegt.
 */
class ZhlResourceAvailabilityRow
{
    /** @var int */    public $id;
    /** @var string */ public $name;
    /** @var int */    public $scheduleId;
    /** @var array[] */ public $days = [];   // [{label, weekday, date, free}]
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

    /** ISO-Wochentag (1=Mo … 7=So) → deutsches Kürzel */
    private const WEEKDAYS = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

    public function __construct(IResourceService $resourceService, IResourceAvailabilityStrategy $availability)
    {
        $this->resourceService = $resourceService;
        $this->availability = $availability;
    }

    /**
     * @param UserSession $user  eingeloggter Nutzer (Rechte-Filter)
     * @param Date        $start Starttag (Mitternacht in der Nutzer-Zeitzone)
     * @param int         $days  Länge des Fensters in Tagen
     * @param int         $scheduleId  0 = alle Kategorien, sonst Filter auf eine Kategorie (Schedule)
     * @param string      $search  Freitext-Filter auf den Gerätenamen ('' = kein Filter)
     * @return array{rows: ZhlResourceAvailabilityRow[], categoryCounts: array<int,int>, totalVisible: int}
     */
    public function BuildGrid(UserSession $user, Date $start, int $days, int $scheduleId, string $search)
    {
        $tz = $user->Timezone;
        $allResources = $this->resourceService->GetAllResources(false, $user);

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

        // Geräte fürs Raster filtern (Kategorie + Suche).
        $resources = [];
        $resourceIds = [];
        foreach ($allResources as $r) {
            if ($r->StatusId == ResourceStatus::HIDDEN) {
                continue;
            }
            if ($scheduleId > 0 && (int)$r->ScheduleId !== $scheduleId) {
                continue;
            }
            if ($search !== '' && stripos((string)$r->GetName(), $search) === false) {
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
            $row->scheduleId = (int)$r->ScheduleId;
            $row->totalDays = $days;

            $resItems = $byResource[$r->GetId()] ?? [];
            for ($d = 0; $d < $days; $d++) {
                $dayStart = $start->AddDays($d);
                $dayEnd = $start->AddDays($d + 1);

                $busy = false;
                foreach ($resItems as $item) {
                    // Overlap [dayStart, dayEnd): item beginnt vor Tagesende UND endet nach Tagesbeginn.
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

        return ['rows' => $rows, 'categoryCounts' => $categoryCounts, 'totalVisible' => $totalVisible];
    }
}

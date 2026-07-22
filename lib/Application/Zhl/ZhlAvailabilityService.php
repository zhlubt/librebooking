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
    /** @var array[] */ public $days = [];     // [{label, weekday, date, free, state}] state: free|busy|vorlauf
    /** @var int */    public $freeCount = 0;
    /** @var int */    public $totalDays = 0;
    /** @var bool */   public $anyFree = false;
    /** @var string|null */ public $nextFreeLabel = null;
    /** @var int */    public $minNoticeDays = 0;          // Vorlauf in (aufgerundeten) Tagen, 0 = keiner
    /** @var string|null */ public $earliestLabel = null;  // frühester buchbarer Tag (US-17), z. B. „27.06.2026"
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

    /** @var string[]|null lokalisierte Wochentags-Kürzel (0=So … 6=Sa) der aktuellen Sprache; lazy gecacht */
    private $weekdayAbbr = null;

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

        // Geräte fürs Raster filtern (Kategorie + Volltextsuche, F25). Die Suche ist mehrwort-fähig
        // (ALLE Begriffe müssen vorkommen), umlaut-/akzent-unempfindlich und tippfehler-tolerant (fuzzy)
        // und durchsucht Name + Geräte-Typ + Beschreibung.
        $searchTerms = $search === '' ? [] : $this->tokenizeSearch($search);
        $descMap = empty($searchTerms) ? [] : $this->GetDescriptionMap();
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
            if (!empty($searchTerms)) {
                $haystack = $this->normalizeSearch(
                    (string)$r->GetName() . ' ' . $type . ' ' . ($descMap[(int)$r->GetId()] ?? '')
                );
                if (!$this->matchesAllTerms($haystack, $searchTerms)) {
                    continue;
                }
            }
            $resources[] = $r;
            $resourceIds[] = $r->GetId();
        }

        $end = $start->AddDays($days);
        $items = empty($resourceIds) ? [] : $this->availability->GetItemsBetween($start, $end, $resourceIds);
        // Vorlaufzeit je Ressource (native min_notice_time_add, in SEKUNDEN) → frühester buchbarer Tag (US-17).
        // Admins umgehen die Vorlaufzeit (sofort buchbar, wie im Buch-Flow ZhlBookPresenter) → im Raster
        // erst gar kein „vorlauf“-Zustand, damit die Anzeige zur tatsächlichen Buchbarkeit passt.
        $noticeMap = (empty($resourceIds) || $user->IsAdmin) ? [] : $this->GetMinNoticeMap($resourceIds);

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

            // Vorlauf: frühester buchbarer Tag = heute + min_notice_time_add (exakt wie native Rule).
            $noticeSec = (int)($noticeMap[(int)$r->GetId()] ?? 0);
            $earliestDateStr = null;
            if ($noticeSec > 0) {
                $earliestLocal = Date::Now()->ApplyDifference(TimeInterval::Parse($noticeSec)->Interval())->ToTimezone($tz);
                $earliestDateStr = $earliestLocal->Format('Y-m-d');
                $row->minNoticeDays = (int)ceil($noticeSec / 86400);
                $row->earliestLabel = $earliestLocal->Format('d.m.Y');
            }

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

                $localDay = $dayStart->ToTimezone($tz);
                $dayDateStr = $localDay->Format('Y-m-d');
                $vorlauf = ($earliestDateStr !== null && $dayDateStr < $earliestDateStr);
                $state = $busy ? 'busy' : ($vorlauf ? 'vorlauf' : 'free');
                $free = ($state === 'free');

                $weekday = $this->weekdayLabel((int)$localDay->Format('N'));
                $row->days[] = [
                    'label' => $localDay->Format('d.m.'),
                    'weekday' => $weekday,
                    'date' => $dayDateStr,
                    'free' => $free,
                    'state' => $state,
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
     * Vorlaufzeit je Ressource aus der nativen Spalte resources.min_notice_time_add (in SEKUNDEN).
     * Nur Ressourcen mit gesetztem Vorlauf > 0 landen in der Map.
     * @param int[] $resourceIds
     * @return array<int,int>  resource_id → Sekunden
     */
    private function GetMinNoticeMap(array $resourceIds)
    {
        $map = [];
        $ids = array_values(array_unique(array_map('intval', $resourceIds)));
        if (empty($ids)) {
            return $map;
        }
        // IDs sind app-intern (eigene Ressourcen-Filterung), kein User-Input → sichere Inline-Liste.
        $in = implode(',', $ids);
        $cmd = new AdHocCommand(
            'SELECT resource_id, min_notice_time_add FROM resources ' .
            'WHERE min_notice_time_add IS NOT NULL AND min_notice_time_add > 0 AND resource_id IN (' . $in . ')'
        );
        $reader = $this->db->Query($cmd);
        while ($row = $reader->GetRow()) {
            $map[(int)$row['resource_id']] = (int)$row['min_notice_time_add'];
        }
        $reader->Free();
        return $map;
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

    /**
     * resource_id → Beschreibung (Klartext, HTML entfernt) für die Volltextsuche (F25).
     * @return array<int,string>
     */
    private function GetDescriptionMap()
    {
        $map = [];
        $cmd = new AdHocCommand(
            'SELECT resource_id, description FROM resources WHERE description IS NOT NULL AND description <> @empty'
        );
        $cmd->AddParameter(new Parameter('@empty', ''));
        $reader = $this->db->Query($cmd);
        while ($row = $reader->GetRow()) {
            $txt = trim(strip_tags((string)$row['description']));
            if ($txt !== '') {
                $map[(int)$row['resource_id']] = $txt;
            }
        }
        $reader->Free();
        return $map;
    }

    /**
     * Normalisiert Text für die Suche: Kleinbuchstaben, deutsche Umlaute/Akzente entschärft,
     * alles außer a-z0-9 zu Leerzeichen. So matchen „Mikrofon"≈„mikrophone", „Stativ"≈„stativ".
     */
    private function normalizeSearch(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, [
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ç' => 'c', 'ñ' => 'n',
        ]);
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s);
        return trim((string)$s);
    }

    /**
     * Zerlegt die Suchanfrage in normalisierte, eindeutige Begriffe.
     * @return string[]
     */
    private function tokenizeSearch(string $search): array
    {
        $norm = $this->normalizeSearch($search);
        if ($norm === '') {
            return [];
        }
        return array_values(array_unique(array_filter(explode(' ', $norm), fn($t) => $t !== '')));
    }

    /**
     * Trefferlogik der Volltextsuche (F25): JEDER Begriff muss im (bereits normalisierten) Haystack
     * vorkommen — als Teilstring ODER tippfehler-tolerant (Levenshtein) gegen ein Haystack-Wort.
     * Fuzzy nur ab 4 Zeichen (kürzere Begriffe würden zu viel matchen).
     * @param string[] $terms bereits normalisierte Begriffe
     */
    private function matchesAllTerms(string $haystack, array $terms): bool
    {
        if ($haystack === '') {
            return false;
        }
        $words = null;
        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }
            if (strpos($haystack, $term) !== false) {
                continue; // Teilstring-Treffer
            }
            if (mb_strlen($term) < 4) {
                return false; // zu kurz für sinnvolle Fuzzy-Suche
            }
            if ($words === null) {
                $words = array_values(array_filter(explode(' ', $haystack), fn($w) => $w !== ''));
            }
            $maxDist = mb_strlen($term) >= 7 ? 2 : 1;
            $hit = false;
            foreach ($words as $w) {
                if (abs(strlen($w) - strlen($term)) > $maxDist) {
                    continue; // Längen-Vorfilter spart levenshtein-Aufrufe
                }
                if (levenshtein($term, $w) <= $maxDist) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }
        return true;
    }

    /**
     * Lokalisiertes Wochentags-Kürzel der aktuellen Sprache (DE „Mo", EN „Mon").
     * @param int $isoDay ISO-Wochentag 1=Mo … 7=So (aus Date::Format('N'))
     */
    private function weekdayLabel(int $isoDay): string
    {
        if ($this->weekdayAbbr === null) {
            // GetDays('abbr') liefert 0=So … 6=Sa → Index = ISO-Tag modulo 7 (So: 7 % 7 = 0).
            $this->weekdayAbbr = Resources::GetInstance()->GetDays('abbr');
        }
        return $this->weekdayAbbr[$isoDay % 7] ?? '';
    }
}

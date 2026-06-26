<?php

/**
 * ZHL Bundle-Resolver (SPEC-BUNDLE-BOOKING) — löst die Items EINER Bundle-Phase + Zeitraum +
 * Alternativ-Wahl auf konkrete, buchbare Ressourcen-IDs auf.
 *
 * Read-only. Die native Konfliktprüfung beim Speichern bleibt der Backstop (TOCTOU-Restfenster ist
 * akzeptiert = natives Verhalten). Regeln (Codex):
 *  - Pro Item: specific_resource_id → genau dieses Gerät; sonst alle Ressourcen mit Geräte-Typ
 *    (Custom-Attribut „Geräte-Typ", attr_id 15) == type_label, die im Zeitraum FREI sind UND die der
 *    User buchen darf (ResourceService::GetAllResources-Filter), die ersten `quantity` Einheiten.
 *  - Packlisten-Items (quantity = 0) werden NICHT gebucht (keine synthetischen IDs).
 *  - Alternativen (alt_group): nur die gewählte Option auflösen.
 *  - Global disjunkte Zuteilung: ein Gerät kann nicht zwei Items erfüllen (used-set über die Phase).
 *  - Same-Schedule-Regel: ALLE aufgelösten Ressourcen einer Phase MÜSSEN denselben schedule_id haben.
 *    Der Phasen-Schedule wird vom ERSTEN zugeteilten Gerät festgelegt; danach werden Kandidaten in
 *    fremden Schedules übersprungen (nicht die Phase abgebrochen). Nur wenn ein Pflicht-Item im
 *    festgelegten Schedule nicht erfüllbar ist, wird die Phase unerfüllbar.
 */
class ZhlResolvedItem
{
    public $itemId = 0;
    public $label = '';            // Anzeige-Label (type_label)
    public $typeLabel = '';
    public $quantity = 1;
    public $required = false;
    public $altGroup = null;
    public $packlist = false;      // quantity 0 → reine Info-Position
    /** @var int[] aufgelöste, buchbare Ressourcen-IDs */
    public $resolvedResourceIds = [];
    public $satisfied = false;     // genug Einheiten gefunden? (Packliste = immer true)
    public $scheduleId = null;     // schedule_id der aufgelösten Ressourcen (einheitlich)
}

class ZhlResolvedPhase
{
    public $phase = 'main';
    public $satisfiable = true;    // alle PFLICHT-Items erfüllt + einheitlicher Schedule
    public $scheduleId = null;     // gemeinsamer schedule_id der Phase (oder null)
    public $error = null;          // Klartext-Fehler bei Nicht-Erfüllbarkeit
    /** @var ZhlResolvedItem[] */
    public $items = [];

    /** Alle aufgelösten Ressourcen-IDs der Phase (über alle Items, in Item-Reihenfolge, disjunkt). */
    public function AllResourceIds(): array
    {
        $out = [];
        foreach ($this->items as $it) {
            foreach ($it->resolvedResourceIds as $rid) {
                $out[] = (int)$rid;
            }
        }
        return $out;
    }
}

class ZhlBundleResolver
{
    /** @var ResourceService */
    private $resourceService;
    /** @var ResourceAvailability */
    private $availability;

    public function __construct(ResourceService $resourceService, ResourceAvailability $availability)
    {
        $this->resourceService = $resourceService;
        $this->availability = $availability;
    }

    /**
     * Löst die übergebenen Items EINER Phase für [$beginUtc, $endUtc] auf.
     *
     * @param array  $items     Roh-Items (Assoc-Arrays mit id,type_label,quantity,required,alt_group,phase,meta,specific_resource_id)
     * @param Date   $begin     Beginn (beliebige TZ; wird intern verglichen)
     * @param Date   $end       Ende
     * @param array  $altChoices alt_group => gewählter type_label
     * @param UserSession $user
     * @param string $phaseName  'main' | 'after' (nur für die Rückgabe/Logik)
     */
    public function ResolvePhase(array $items, Date $begin, Date $end, array $altChoices, UserSession $user, string $phaseName): ZhlResolvedPhase
    {
        $phase = new ZhlResolvedPhase();
        $phase->phase = $phaseName;

        // Buchbare Ressourcen des Users einmal laden (Permission-Filter) → id => Resource.
        // GetAllResources(false, $user) liefert ResourceDto[]; CanAccess≠CanBook — eine Ressource
        // kann sichtbar/zugänglich sein, ohne dass der User sie BUCHEN darf. Nur buchbare Kandidaten
        // zulassen, sonst löst das Bundle auf ein Gerät auf, das der User nicht buchen kann (und ein
        // Terminplaner-Nebeneffekt — Abholung/Einführung — passiert, bevor die native Auth ablehnt).
        // ResourceDto hat keinen Getter für CanBook; das öffentliche Feld $r->CanBook ist die Quelle.
        $allowed = [];
        foreach ($this->resourceService->GetAllResources(false, $user) as $r) {
            if ((int)$r->GetStatusId() === ResourceStatus::HIDDEN) {
                continue;
            }
            if (!$r->CanBook) {
                continue;
            }
            $allowed[(int)$r->GetId()] = $r;
        }

        // Geräte-Typ je Ressource aus custom_attribute_values (Attr „Geräte-Typ") — gleiche
        // Wahrheitsquelle wie ZhlAvailabilityService::GetTypeMap (robuster als Resource-Attribut-Getter).
        $typeMap = $this->buildTypeMap();

        $used = [];        // global disjunkt: bereits vergebene Ressourcen-IDs
        $scheduleId = null; // gemeinsamer Schedule der Phase

        // Vor-Pass über die Alternativ-Gruppen: pro alt_group die Menge der gültigen Options-Labels
        // und ob die Gruppe als Ganzes erforderlich ist. Damit lässt sich erkennen, ob die gepostete
        // Wahl ($altChoices[group]) überhaupt eine echte Option der Gruppe ist (sonst: gefälscht/fehlend).
        $altGroupOptions = [];   // group => [type_label => true]
        $altGroupRequired = [];  // group => bool (mind. eine Option erforderlich)
        foreach ($items as $row) {
            $g = isset($row['alt_group']) && $row['alt_group'] !== '' ? (string)$row['alt_group'] : null;
            if ($g === null) {
                continue;
            }
            $tl = (string)($row['type_label'] ?? '');
            if ($tl !== '') {
                $altGroupOptions[$g][$tl] = true;
            }
            if (((int)($row['required'] ?? 0)) === 1) {
                $altGroupRequired[$g] = true;
            }
        }
        // Pro erforderlicher Gruppe genau EINMAL einen Fehler melden, falls die Wahl ungültig/unerfüllbar ist.
        $altGroupFailed = []; // group => true (Fehler bereits gesetzt)

        foreach ($items as $row) {
            $quantity = (int)($row['quantity'] ?? 1);
            $altGroup = isset($row['alt_group']) && $row['alt_group'] !== '' ? (string)$row['alt_group'] : null;
            $typeLabel = (string)($row['type_label'] ?? '');

            $ri = new ZhlResolvedItem();
            $ri->itemId = (int)($row['id'] ?? 0);
            $ri->label = $typeLabel;
            $ri->typeLabel = $typeLabel;
            $ri->quantity = $quantity;
            $ri->required = ((int)($row['required'] ?? 0)) === 1;
            $ri->altGroup = $altGroup;

            // Packliste: quantity 0 → reine Info, nicht buchen, immer „erfüllt".
            if ($quantity <= 0) {
                $ri->packlist = true;
                $ri->satisfied = true;
                $phase->items[] = $ri;
                continue;
            }

            // Alternativen: nur die gewählte Option auflösen. Die Wahl muss aber GENUINE sein —
            // eine fehlende/gefälschte Wahl darf eine erforderliche Gruppe nicht still verschwinden
            // lassen (sonst würde jede Nicht-Treffer-Option als „erfüllt" markiert).
            if ($altGroup !== null) {
                $chosen = isset($altChoices[$altGroup]) ? (string)$altChoices[$altGroup] : null;
                $validChoice = $chosen !== null && isset($altGroupOptions[$altGroup][$chosen]);
                $required = !empty($altGroupRequired[$altGroup]);

                if (!$validChoice) {
                    // Wahl fehlt oder ist keine echte Option dieser Gruppe.
                    if ($required && empty($altGroupFailed[$altGroup])) {
                        $phase->satisfiable = false;
                        if ($phase->error === null) {
                            $phase->error = 'Bitte für „' . $altGroup . '" eine Option wählen (z. B. ' . $this->describeAltOptions($altGroupOptions[$altGroup] ?? []) . ').';
                        }
                        $altGroupFailed[$altGroup] = true;
                    }
                    // Diese (nicht gewählte) Option weder buchen noch als erfüllt werten.
                    $ri->satisfied = false;
                    $ri->resolvedResourceIds = [];
                    $phase->items[] = $ri;
                    continue;
                }
                if ($chosen !== $typeLabel) {
                    // gültige Wahl, aber nicht DIESE Option: überspringen (die gewählte Option-Zeile
                    // wird separat aufgelöst und entscheidet über die Erfüllung der Gruppe).
                    $ri->satisfied = true;
                    $ri->resolvedResourceIds = [];
                    $phase->items[] = $ri;
                    continue;
                }
                // chosen === typeLabel → diese Option-Zeile wird unten regulär aufgelöst. Schlägt das
                // fehl (zu wenige freie Geräte), markiert die required-Prüfung weiter unten die Phase
                // als unerfüllbar — die Gruppe gilt also nur als erfüllt, wenn die Wahl echt aufgeht.
            }

            $candidateIds = $this->candidateResourceIds($row, $allowed, $typeMap);
            $picked = [];
            foreach ($candidateIds as $rid) {
                if (count($picked) >= $quantity) {
                    break;
                }
                if (isset($used[$rid])) {
                    continue;
                }
                $resource = $allowed[$rid];
                $sid = (int)$resource->GetScheduleId();
                // Same-Schedule-Regel: der Phasen-Schedule wird vom ERSTEN tatsächlich zugeteilten
                // Gerät bestimmt. Danach werden Kandidaten auf fremden Schedules ÜBERSPRUNGEN (nicht
                // die Phase abgebrochen) — ein verirrter Cross-Schedule-Kandidat darf das Item nicht
                // unerfüllbar machen, solange spätere Kandidaten im richtigen Schedule es erfüllen.
                if ($scheduleId !== null && $sid !== $scheduleId) {
                    continue;
                }
                if (!$this->isFree($rid, $begin, $end)) {
                    continue;
                }
                if ($scheduleId === null) {
                    $scheduleId = $sid;
                }
                $picked[] = $rid;
                $used[$rid] = true;
                $ri->scheduleId = $sid;
            }

            $ri->resolvedResourceIds = $picked;
            $ri->satisfied = count($picked) >= $quantity;
            if ($ri->required && !$ri->satisfied) {
                $phase->satisfiable = false;
                if ($phase->error === null) {
                    $phase->error = 'Nicht genügend freie Einheiten vom Typ „' . $typeLabel . '" im gewählten Zeitraum.';
                }
            }
            $phase->items[] = $ri;
        }

        $phase->scheduleId = $scheduleId;
        return $phase;
    }

    /**
     * Lesbare Aufzählung der Options-Labels einer Alternativ-Gruppe für Fehlermeldungen.
     * @param array<string,bool> $options type_label => true
     */
    private function describeAltOptions(array $options): string
    {
        $labels = array_keys($options);
        if (empty($labels)) {
            return 'eine der angebotenen Optionen';
        }
        if (count($labels) === 1) {
            return $labels[0];
        }
        $last = array_pop($labels);
        return implode(', ', $labels) . ' oder ' . $last;
    }

    /**
     * Kandidaten-Ressourcen für ein Item, in stabiler Reihenfolge (specific_resource_id zuerst,
     * sonst alle des Typs nach ID). Nur Ressourcen, die der User buchen darf ($allowed).
     * @param array<int,string> $typeMap resource_id → Geräte-Typ-Label
     * @return int[]
     */
    private function candidateResourceIds(array $row, array $allowed, array $typeMap): array
    {
        $specific = isset($row['specific_resource_id']) && $row['specific_resource_id'] !== null
            ? (int)$row['specific_resource_id'] : 0;
        if ($specific > 0) {
            // Konkretes Gerät: nur wenn buchbar/erlaubt.
            return isset($allowed[$specific]) ? [$specific] : [];
        }

        $typeLabel = (string)($row['type_label'] ?? '');
        if ($typeLabel === '') {
            return [];
        }
        // Alle erlaubten Ressourcen, deren Geräte-Typ (Attr „Geräte-Typ") == type_label.
        $ids = [];
        foreach ($allowed as $rid => $resource) {
            if (($typeMap[(int)$rid] ?? null) === $typeLabel) {
                $ids[] = (int)$rid;
            }
        }
        sort($ids);
        return $ids;
    }

    /**
     * Geräte-Typ je Ressource aus custom_attribute_values (Attr „Geräte-Typ", Kategorie 4).
     * @return array<int,string> resource_id → Typ-Label
     */
    private function buildTypeMap(): array
    {
        $map = [];
        $db = ServiceLocator::GetDatabase();
        $cmd = new AdHocCommand(
            'SELECT v.entity_id AS rid, v.attribute_value AS t FROM custom_attribute_values v ' .
            'JOIN custom_attributes a ON a.custom_attribute_id = v.custom_attribute_id ' .
            'WHERE a.display_label = @l AND a.attribute_category = @c'
        );
        $cmd->AddParameter(new Parameter('@l', 'Geräte-Typ'));
        $cmd->AddParameter(new Parameter('@c', CustomAttributeCategory::RESOURCE));
        $reader = $db->Query($cmd);
        while ($r = $reader->GetRow()) {
            $val = trim((string)$r['t']);
            if ($val !== '') {
                $map[(int)$r['rid']] = $val;
            }
        }
        $reader->Free();
        return $map;
    }

    /** Ist die Ressource im Zeitraum [$begin,$end) frei? (überlappende Reservierung = belegt) */
    private function isFree(int $rid, Date $begin, Date $end): bool
    {
        $items = $this->availability->GetItemsBetween($begin, $end, [$rid]);
        foreach ($items as $it) {
            if ($it->GetStartDate()->LessThan($end) && $it->GetEndDate()->GreaterThan($begin)) {
                return false;
            }
        }
        return true;
    }
}

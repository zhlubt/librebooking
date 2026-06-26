<?php

require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlTypeInfo.php');

/**
 * ZHL-Bundle-Service (Dashboard v2b) — lädt den Bundle-Katalog (zhl_bundle/_item) und berechnet
 * je Bundle die Live-Verfügbarkeit aus dem Pool je Geräte-Typ (US-9/US-12/US-14).
 *
 * Read-only. Bundle = mehrere Geräte-Typen mit Menge; „verfügbar" = für jede PFLICHT-Position sind
 * genügend freie Einheiten des Typs im Zeitraum vorhanden. Optionale Positionen blocken nicht.
 * Gebucht wird über die konkreten Einzelgeräte (Trichter → native Buchung).
 */
class ZhlBundleItemView
{
    public $type;       // Geräte-Typ (Label)
    public $quantity;
    public $required;   // bool
    public $note;
    public $free = 0;   // freie Einheiten dieses Typs im Zeitraum
    public $ok = true;  // genügend frei?
    public $altGroup = null;   // Alternativ-Gruppe (Auto-Prio); null = normale Position
    /** @var string[] Options-Labels der Gruppe in Prioritäts-Reihenfolge (nur bei altGroup) */
    public $altOptions = [];
    public $infoUrl = '';      // D2: Info-Material-Link des Geräte-Typs (kleines (i) am Bundle)
    public $infoText = '';     // D2: optionaler Info-Text
}

class ZhlBundleView
{
    public $id;
    public $name;
    public $useCase;
    public $difficulty;
    public $hint;
    public $einweisungLevel = 'keine';   // keine|empfehlenswert|zwingend|beratung
    public $einweisungText = '';
    public $einweisungUrl = '';
    public $offerSchnitt = false;   // US-15: Schnitt-/VR-PC als getrennte Folge-Buchung anbieten
    /** @var ZhlBundleItemView[] */
    public $items = [];
    public $available = true;   // alle Pflicht-Positionen erfüllbar
    /** @var string[] */
    public $blockers = [];      // nicht erfüllbare Pflicht-Positionen (für die Meldung)
}

class ZhlBundleService
{
    /** @var Database */
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * @param array<string,int> $typeFreeMap  Geräte-Typ → freie Einheiten im Zeitraum
     * @return ZhlBundleView[]
     */
    public function GetBundles(array $typeFreeMap)
    {
        $bundles = [];
        $reader = $this->db->Query(new AdHocCommand(
            'SELECT id, name, use_case, difficulty, hint, einweisung_level, einweisung_text, einweisung_url, offer_schnitt ' .
            'FROM zhl_bundle WHERE active = 1 ORDER BY sort_order, name'
        ));
        while ($row = $reader->GetRow()) {
            $b = new ZhlBundleView();
            $b->id = (int)$row['id'];
            $b->name = (string)$row['name'];
            $b->useCase = (string)($row['use_case'] ?? '');
            $b->difficulty = (string)$row['difficulty'];
            $b->hint = (string)($row['hint'] ?? '');
            $b->einweisungLevel = (string)($row['einweisung_level'] ?? 'keine');
            $b->einweisungText = (string)($row['einweisung_text'] ?? '');
            $b->einweisungUrl = (string)($row['einweisung_url'] ?? '');
            $b->offerSchnitt = ((int)($row['offer_schnitt'] ?? 0)) === 1;
            $bundles[$b->id] = $b;
        }
        $reader->Free();

        if (empty($bundles)) {
            return [];
        }

        $reader = $this->db->Query(new AdHocCommand(
            'SELECT bundle_id, type_label, quantity, required, alt_group, note FROM zhl_bundle_item ORDER BY bundle_id, sort_order, id'
        ));
        // Alternativ-Gruppen (Auto-Prio) werden zu EINER Position zusammengefasst: die Gruppe ist
        // erfüllbar, sobald EINE Option genügend freie Einheiten hat (der Resolver fällt automatisch
        // auf die nächste Priorität zurück). Sonst würde z. B. ein belegtes Shure-Mikro das Bundle als
        // „nicht frei" zeigen, obwohl Yeti frei ist (Codex-Fund).
        $groupIdx = []; // "bid:group" => Index der Sammel-Position in $b->items
        while ($row = $reader->GetRow()) {
            $bid = (int)$row['bundle_id'];
            if (!isset($bundles[$bid])) {
                continue;
            }
            $b = $bundles[$bid];
            $type = (string)$row['type_label'];
            $qty = (int)$row['quantity'];
            $req = ((int)$row['required']) === 1;
            $free = (int)($typeFreeMap[$type] ?? 0);
            $ok = $free >= $qty;
            $group = (isset($row['alt_group']) && $row['alt_group'] !== '') ? (string)$row['alt_group'] : null;

            if ($group !== null) {
                $key = $bid . ':' . $group;
                if (!isset($groupIdx[$key])) {
                    // Erste (höchste) Priorität bestimmt Label/Menge/Link der Sammel-Position.
                    $item = new ZhlBundleItemView();
                    $item->type = $type;
                    $item->quantity = $qty;
                    $item->required = $req;
                    $item->note = (string)($row['note'] ?? '');
                    $item->free = $free;
                    $item->ok = $ok;
                    $item->altGroup = $group;
                    $item->altOptions = [$type];
                    $groupIdx[$key] = count($b->items);
                    $b->items[] = $item;
                } else {
                    $item = $b->items[$groupIdx[$key]];
                    $item->altOptions[] = $type;
                    $item->required = $item->required || $req;   // Gruppe required, wenn EINE Option required
                    $item->ok = $item->ok || $ok;                // Gruppe ok, wenn EINE Option frei
                    if ($free > $item->free) {
                        $item->free = $free;                     // beste Option für die Anzeige
                    }
                }
                continue;
            }

            $item = new ZhlBundleItemView();
            $item->type = $type;
            $item->quantity = $qty;
            $item->required = $req;
            $item->note = (string)($row['note'] ?? '');
            $item->free = $free;
            $item->ok = $ok;
            $b->items[] = $item;
            if ($req && !$ok) {
                $b->available = false;
                $b->blockers[] = $qty . '× ' . $type . ' (nur ' . $free . ' frei)';
            }
        }
        $reader->Free();

        // Verfügbarkeit der Alternativ-Gruppen erst nach dem Sammeln aller Optionen prüfen.
        foreach ($bundles as $b) {
            foreach ($b->items as $item) {
                if ($item->altGroup !== null && $item->required && !$item->ok) {
                    $b->available = false;
                    $b->blockers[] = $item->quantity . '× ' . implode(' / ', $item->altOptions) . ' (keine Option frei)';
                }
            }
        }

        // D2: Info-Material je Geräte-Typ an die Positionen hängen (kleines (i) am Bundle).
        $infoMap = ZhlTypeInfo::Map($this->db);
        if (!empty($infoMap)) {
            foreach ($bundles as $b) {
                foreach ($b->items as $item) {
                    if (isset($infoMap[$item->type])) {
                        $item->infoUrl = $infoMap[$item->type]['url'];
                        $item->infoText = $infoMap[$item->type]['text'];
                    }
                }
            }
        }

        return array_values($bundles);
    }
}

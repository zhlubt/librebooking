<?php

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
}

class ZhlBundleView
{
    public $id;
    public $name;
    public $useCase;
    public $difficulty;
    public $hint;
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
            'SELECT id, name, use_case, difficulty, hint FROM zhl_bundle WHERE active = 1 ORDER BY sort_order, name'
        ));
        while ($row = $reader->GetRow()) {
            $b = new ZhlBundleView();
            $b->id = (int)$row['id'];
            $b->name = (string)$row['name'];
            $b->useCase = (string)($row['use_case'] ?? '');
            $b->difficulty = (string)$row['difficulty'];
            $b->hint = (string)($row['hint'] ?? '');
            $bundles[$b->id] = $b;
        }
        $reader->Free();

        if (empty($bundles)) {
            return [];
        }

        $reader = $this->db->Query(new AdHocCommand(
            'SELECT bundle_id, type_label, quantity, required, note FROM zhl_bundle_item ORDER BY bundle_id, sort_order, id'
        ));
        while ($row = $reader->GetRow()) {
            $bid = (int)$row['bundle_id'];
            if (!isset($bundles[$bid])) {
                continue;
            }
            $item = new ZhlBundleItemView();
            $item->type = (string)$row['type_label'];
            $item->quantity = (int)$row['quantity'];
            $item->required = ((int)$row['required']) === 1;
            $item->note = (string)($row['note'] ?? '');
            $item->free = (int)($typeFreeMap[$item->type] ?? 0);
            $item->ok = $item->free >= $item->quantity;

            $b = $bundles[$bid];
            $b->items[] = $item;
            if ($item->required && !$item->ok) {
                $b->available = false;
                $b->blockers[] = $item->quantity . '× ' . $item->type . ' (nur ' . $item->free . ' frei)';
            }
        }
        $reader->Free();

        return array_values($bundles);
    }
}

<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');

/**
 * Admin-Pflege der Bundles (Dashboard v2c). CRUD über zhl_bundle/zhl_bundle_item.
 * Nur Schreibzugriff per POST + CSRF (von der Page erzwungen). Prepared Statements.
 * Geräte-Typ wird aus den vorhandenen „Geräte-Typ"-Attributwerten als Auswahl angeboten
 * (konsistente Typen statt Freitext — teilweise Härtung des Codex-Hinweises).
 */
class ZhlBundlesAdminPresenter
{
    private $page;

    private const DIFFICULTIES = ['einfach', 'fortgeschritten', 'profi'];
    private const EINWEISUNG_LEVELS = ['keine', 'empfehlenswert', 'zwingend', 'beratung'];

    public function __construct($page)
    {
        $this->page = $page;
    }

    /** @return string Status-Meldung (für Post-Redirect-Get) */
    public function HandlePost()
    {
        $db = ServiceLocator::GetDatabase();
        $action = $this->post('action');

        switch ($action) {
            case 'create_bundle':
                $name = trim($this->post('name'));
                if ($name === '') {
                    return 'Name fehlt — Bundle nicht angelegt.';
                }
                $cmd = new AdHocCommand('INSERT INTO zhl_bundle (name, use_case, difficulty, hint, einweisung_level, einweisung_text, einweisung_url, offer_schnitt, active, sort_order) VALUES (@n,@u,@d,@h,@el,@et,@eu,@os,1,@s)');
                $cmd->AddParameter(new Parameter('@n', $name));
                $cmd->AddParameter(new Parameter('@u', trim($this->post('use_case'))));
                $cmd->AddParameter(new Parameter('@d', $this->difficulty($this->post('difficulty'))));
                $cmd->AddParameter(new Parameter('@h', trim($this->post('hint'))));
                $cmd->AddParameter(new Parameter('@el', $this->einweisungLevel($this->post('einweisung_level'))));
                $cmd->AddParameter(new Parameter('@et', trim($this->post('einweisung_text'))));
                $cmd->AddParameter(new Parameter('@eu', trim($this->post('einweisung_url'))));
                $cmd->AddParameter(new Parameter('@os', $this->post('offer_schnitt') ? 1 : 0));
                $cmd->AddParameter(new Parameter('@s', $this->int($this->post('sort_order'), 0, 0, 9999)));
                $db->Execute($cmd);
                return 'Bundle „' . $name . '" angelegt.';

            case 'update_bundle':
                $id = $this->int($this->post('bundle_id'), 0, 1, PHP_INT_MAX);
                if (!$id) {
                    return 'Ungültige Bundle-ID.';
                }
                $cmd = new AdHocCommand('UPDATE zhl_bundle SET name=@n, use_case=@u, difficulty=@d, hint=@h, einweisung_level=@el, einweisung_text=@et, einweisung_url=@eu, offer_schnitt=@os, sort_order=@s WHERE id=@id');
                $cmd->AddParameter(new Parameter('@n', trim($this->post('name'))));
                $cmd->AddParameter(new Parameter('@u', trim($this->post('use_case'))));
                $cmd->AddParameter(new Parameter('@d', $this->difficulty($this->post('difficulty'))));
                $cmd->AddParameter(new Parameter('@h', trim($this->post('hint'))));
                $cmd->AddParameter(new Parameter('@el', $this->einweisungLevel($this->post('einweisung_level'))));
                $cmd->AddParameter(new Parameter('@et', trim($this->post('einweisung_text'))));
                $cmd->AddParameter(new Parameter('@eu', trim($this->post('einweisung_url'))));
                $cmd->AddParameter(new Parameter('@os', $this->post('offer_schnitt') ? 1 : 0));
                $cmd->AddParameter(new Parameter('@s', $this->int($this->post('sort_order'), 0, 0, 9999)));
                $cmd->AddParameter(new Parameter('@id', $id));
                $db->Execute($cmd);
                return 'Bundle gespeichert.';

            case 'toggle_active':
                $id = $this->int($this->post('bundle_id'), 0, 1, PHP_INT_MAX);
                if ($id) {
                    $cmd = new AdHocCommand('UPDATE zhl_bundle SET active = 1 - active WHERE id=@id');
                    $cmd->AddParameter(new Parameter('@id', $id));
                    $db->Execute($cmd);
                }
                return 'Sichtbarkeit umgeschaltet.';

            case 'delete_bundle':
                $id = $this->int($this->post('bundle_id'), 0, 1, PHP_INT_MAX);
                if ($id) {
                    $cmd = new AdHocCommand('DELETE FROM zhl_bundle WHERE id=@id');
                    $cmd->AddParameter(new Parameter('@id', $id));
                    $db->Execute($cmd);
                }
                return 'Bundle gelöscht.';

            case 'add_item':
                $bid = $this->int($this->post('bundle_id'), 0, 1, PHP_INT_MAX);
                $type = trim($this->post('type_label'));
                if (!$bid || $type === '') {
                    return 'Typ fehlt — Position nicht hinzugefügt.';
                }
                // Nur echte Geräte-Typen erlauben (kein Freitext) — sonst zeigt die Position ins Leere.
                if (!in_array($type, $this->knownTypes($db), true)) {
                    return 'Unbekannter Geräte-Typ „' . $type . '" — bitte einen vorhandenen Typ aus der Liste wählen.';
                }
                $cmd = new AdHocCommand('INSERT INTO zhl_bundle_item (bundle_id, type_label, quantity, required, note, sort_order) VALUES (@b,@t,@q,@r,@n,@s)');
                $cmd->AddParameter(new Parameter('@b', $bid));
                $cmd->AddParameter(new Parameter('@t', $type));
                $cmd->AddParameter(new Parameter('@q', $this->int($this->post('quantity'), 1, 1, 999)));
                $cmd->AddParameter(new Parameter('@r', $this->post('required') ? 1 : 0));
                $cmd->AddParameter(new Parameter('@n', trim($this->post('note'))));
                $cmd->AddParameter(new Parameter('@s', $this->int($this->post('sort_order'), 0, 0, 9999)));
                $db->Execute($cmd);
                return 'Position hinzugefügt.';

            case 'delete_item':
                $iid = $this->int($this->post('item_id'), 0, 1, PHP_INT_MAX);
                if ($iid) {
                    $cmd = new AdHocCommand('DELETE FROM zhl_bundle_item WHERE id=@id');
                    $cmd->AddParameter(new Parameter('@id', $iid));
                    $db->Execute($cmd);
                }
                return 'Position entfernt.';
        }
        return '';
    }

    public function Load()
    {
        $db = ServiceLocator::GetDatabase();

        $bundles = [];
        $reader = $db->Query(new AdHocCommand('SELECT id, name, use_case, difficulty, hint, einweisung_level, einweisung_text, einweisung_url, offer_schnitt, active, sort_order FROM zhl_bundle ORDER BY sort_order, name'));
        while ($row = $reader->GetRow()) {
            $row['items'] = [];
            $bundles[(int)$row['id']] = $row;
        }
        $reader->Free();

        // Auflösungs-Karten: Typ→Geräte und konkrete Gerätenamen (damit der Admin SIEHT, was
        // hinter jeder Position steckt — keine Rätsel mehr beim Pflegen).
        $typeToDevices = $this->typeToDevices($db);
        $resById = $this->resourceNames($db);

        $reader = $db->Query(new AdHocCommand('SELECT id, bundle_id, type_label, quantity, required, note, sort_order, specific_resource_id FROM zhl_bundle_item ORDER BY bundle_id, sort_order, id'));
        while ($row = $reader->GetRow()) {
            $bid = (int)$row['bundle_id'];
            if (!isset($bundles[$bid])) {
                continue;
            }
            // Verknüpfte Geräte ermitteln: konkretes Gerät > Packliste (Menge 0) > Typ-Auflösung.
            $specificId = (int)($row['specific_resource_id'] ?? 0);
            if ($specificId > 0) {
                $row['resolvedKind'] = 'specific';
                $names = [$resById[$specificId] ?? ('#' . $specificId)];
            } elseif ((int)$row['quantity'] === 0) {
                $row['resolvedKind'] = 'packlist';
                $names = [];
            } else {
                $names = $typeToDevices[$row['type_label']] ?? [];
                $row['resolvedKind'] = empty($names) ? 'none' : 'type';
            }
            $row['resolvedCount'] = count($names);
            $row['resolvedLabel'] = implode(', ', $names); // im Template fertig escapen
            $bundles[$bid]['items'][] = $row;
        }
        $reader->Free();

        $this->page->SetBundles(array_values($bundles));
        $this->page->SetKnownTypes($this->knownTypes($db));
        $this->page->SetDifficulties(self::DIFFICULTIES);
        $this->page->SetEinweisungLevels(self::EINWEISUNG_LEVELS);
    }

    private function knownTypes($db)
    {
        $types = [];
        $cmd = new AdHocCommand(
            "SELECT DISTINCT v.attribute_value AS t FROM custom_attribute_values v " .
            "JOIN custom_attributes a ON a.custom_attribute_id = v.custom_attribute_id " .
            "WHERE a.display_label = @l AND a.attribute_category = 4 AND v.attribute_value <> '' ORDER BY t"
        );
        $cmd->AddParameter(new Parameter('@l', 'Geräte-Typ'));
        $reader = $db->Query($cmd);
        while ($row = $reader->GetRow()) {
            $types[] = $row['t'];
        }
        $reader->Free();
        return $types;
    }

    /** @return array<string,string[]> Geräte-Typ-Label → Liste der Gerätenamen (aktive Geräte). */
    private function typeToDevices($db)
    {
        $map = [];
        $cmd = new AdHocCommand(
            "SELECT v.attribute_value AS t, r.name AS n FROM custom_attribute_values v " .
            "JOIN custom_attributes a ON a.custom_attribute_id = v.custom_attribute_id " .
            "JOIN resources r ON r.resource_id = v.entity_id " .
            "WHERE a.display_label = @l AND a.attribute_category = 4 AND v.attribute_value <> '' AND r.status_id <> 0 " .
            "ORDER BY v.attribute_value, r.name"
        );
        $cmd->AddParameter(new Parameter('@l', 'Geräte-Typ'));
        $reader = $db->Query($cmd);
        while ($row = $reader->GetRow()) {
            $map[(string)$row['t']][] = (string)$row['n'];
        }
        $reader->Free();
        return $map;
    }

    /** @return array<int,string> resource_id → Name (aktive Geräte) für die Auflösung konkreter Geräte. */
    private function resourceNames($db)
    {
        $out = [];
        $reader = $db->Query(new AdHocCommand('SELECT resource_id, name FROM resources WHERE status_id <> 0'));
        while ($row = $reader->GetRow()) {
            $out[(int)$row['resource_id']] = (string)$row['name'];
        }
        $reader->Free();
        return $out;
    }

    private function post($key)
    {
        return isset($_POST[$key]) ? (string)$_POST[$key] : '';
    }

    private function int($v, $default, $min, $max)
    {
        if ($v === '' || !is_numeric($v)) {
            return $default;
        }
        $n = (int)$v;
        return max($min, min($max, $n));
    }

    private function difficulty($v)
    {
        return in_array($v, self::DIFFICULTIES, true) ? $v : 'einfach';
    }

    private function einweisungLevel($v)
    {
        return in_array($v, self::EINWEISUNG_LEVELS, true) ? $v : 'keine';
    }
}

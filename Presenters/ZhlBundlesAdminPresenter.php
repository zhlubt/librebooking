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
                $cmd = new AdHocCommand('INSERT INTO zhl_bundle (name, use_case, difficulty, hint, active, sort_order) VALUES (@n,@u,@d,@h,1,@s)');
                $cmd->AddParameter(new Parameter('@n', $name));
                $cmd->AddParameter(new Parameter('@u', trim($this->post('use_case'))));
                $cmd->AddParameter(new Parameter('@d', $this->difficulty($this->post('difficulty'))));
                $cmd->AddParameter(new Parameter('@h', trim($this->post('hint'))));
                $cmd->AddParameter(new Parameter('@s', $this->int($this->post('sort_order'), 0, 0, 9999)));
                $db->Execute($cmd);
                return 'Bundle „' . $name . '" angelegt.';

            case 'update_bundle':
                $id = $this->int($this->post('bundle_id'), 0, 1, PHP_INT_MAX);
                if (!$id) {
                    return 'Ungültige Bundle-ID.';
                }
                $cmd = new AdHocCommand('UPDATE zhl_bundle SET name=@n, use_case=@u, difficulty=@d, hint=@h, sort_order=@s WHERE id=@id');
                $cmd->AddParameter(new Parameter('@n', trim($this->post('name'))));
                $cmd->AddParameter(new Parameter('@u', trim($this->post('use_case'))));
                $cmd->AddParameter(new Parameter('@d', $this->difficulty($this->post('difficulty'))));
                $cmd->AddParameter(new Parameter('@h', trim($this->post('hint'))));
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
        $reader = $db->Query(new AdHocCommand('SELECT id, name, use_case, difficulty, hint, active, sort_order FROM zhl_bundle ORDER BY sort_order, name'));
        while ($row = $reader->GetRow()) {
            $row['items'] = [];
            $bundles[(int)$row['id']] = $row;
        }
        $reader->Free();

        $reader = $db->Query(new AdHocCommand('SELECT id, bundle_id, type_label, quantity, required, note, sort_order FROM zhl_bundle_item ORDER BY bundle_id, sort_order, id'));
        while ($row = $reader->GetRow()) {
            $bid = (int)$row['bundle_id'];
            if (isset($bundles[$bid])) {
                $bundles[$bid]['items'][] = $row;
            }
        }
        $reader->Free();

        $this->page->SetBundles(array_values($bundles));
        $this->page->SetKnownTypes($this->knownTypes($db));
        $this->page->SetDifficulties(self::DIFFICULTIES);
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
}

<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlCertTypeInfo.php');

/**
 * Admin-Pflege der benannten Zertifikate (v-cert). CRUD über zhl_cert_type / zhl_cert_type_resource /
 * zhl_cert_grant. Schreibt nur per POST + CSRF (von der Page erzwungen), Prepared Statements.
 *
 * Quelle der Wahrheit ist das benannte System; nach jeder Änderung an Geräte-Zuordnung oder Grants wird
 * `zhl_certificate` neu projiziert (RebuildProjection), damit das Einführungs-Gate (zhl-book.php) UND das
 * native F40-Plugin unverändert weiterlesen.
 */
class ZhlCertificatesAdminPresenter
{
    private $page;

    public function __construct($page)
    {
        $this->page = $page;
    }

    /** @return string Status-Meldung (Post-Redirect-Get) */
    public function HandlePost()
    {
        $db = ServiceLocator::GetDatabase();
        $action = $this->post('action');

        switch ($action) {
            case 'create_type':
                $name = trim($this->post('name'));
                if ($name === '') {
                    return 'Name fehlt — Zertifikat nicht angelegt.';
                }
                $cmd = new AdHocCommand('INSERT INTO zhl_cert_type (name, active, sort_order, confirm_email) VALUES (@n,1,@s,@ce)');
                $cmd->AddParameter(new Parameter('@n', $name));
                $cmd->AddParameter(new Parameter('@s', $this->int($this->post('sort_order'), 0, 0, 9999)));
                $cmd->AddParameter(new Parameter('@ce', trim($this->post('confirm_email')) ?: null));
                $db->Execute($cmd);
                return 'Zertifikat „' . $name . '" angelegt.';

            case 'update_type':
                $id = $this->int($this->post('type_id'), 0, 1, PHP_INT_MAX);
                $name = trim($this->post('name'));
                if (!$id || $name === '') {
                    return 'Ungültige Eingabe.';
                }
                $cmd = new AdHocCommand('UPDATE zhl_cert_type SET name=@n, sort_order=@s, confirm_email=@ce WHERE id=@id');
                $cmd->AddParameter(new Parameter('@n', $name));
                $cmd->AddParameter(new Parameter('@s', $this->int($this->post('sort_order'), 0, 0, 9999)));
                $cmd->AddParameter(new Parameter('@ce', trim($this->post('confirm_email')) ?: null));
                $cmd->AddParameter(new Parameter('@id', $id));
                $db->Execute($cmd);
                return 'Zertifikat gespeichert.';

            case 'save_info':
                // D3: vertrauliche Zusatz-Infos (Transponder-Code/News/Doc-Link) je Zertifikats-Typ.
                $id = $this->int($this->post('type_id'), 0, 1, PHP_INT_MAX);
                if (!$id) {
                    return 'Ungültige Eingabe.';
                }
                $code = mb_substr(trim($this->post('transponder_code')), 0, 190);
                $news = trim($this->post('news_text'));
                $news = $news === '' ? null : mb_substr($news, 0, 4000);
                $doc = trim($this->post('doc_url'));
                if ($doc !== '' && !preg_match('#^https?://#i', $doc)) {
                    $doc = ''; // nur http(s) zulassen (kein javascript:/data:)
                }
                $doc = mb_substr($doc, 0, 500);
                $active = $this->post('info_active') ? 1 : 0;
                $cmd = new AdHocCommand(
                    'INSERT INTO zhl_cert_type_info (cert_type_id, transponder_code, news_text, doc_url, active, updated_at) ' .
                    'VALUES (@id,@c,@n,@d,@a,@now) ' .
                    'ON DUPLICATE KEY UPDATE transponder_code=VALUES(transponder_code), news_text=VALUES(news_text), ' .
                    'doc_url=VALUES(doc_url), active=VALUES(active), updated_at=VALUES(updated_at)'
                );
                $cmd->AddParameter(new Parameter('@id', $id));
                $cmd->AddParameter(new Parameter('@c', $code));
                $cmd->AddParameter(new Parameter('@n', $news));
                $cmd->AddParameter(new Parameter('@d', $doc));
                $cmd->AddParameter(new Parameter('@a', $active));
                $cmd->AddParameter(new Parameter('@now', gmdate('Y-m-d H:i:s')));
                $db->Execute($cmd);
                return 'Vertrauliche Infos gespeichert.';

            case 'toggle_type':
                $id = $this->int($this->post('type_id'), 0, 1, PHP_INT_MAX);
                if ($id) {
                    $cmd = new AdHocCommand('UPDATE zhl_cert_type SET active = 1 - active WHERE id=@id');
                    $cmd->AddParameter(new Parameter('@id', $id));
                    $db->Execute($cmd);
                    $this->RebuildProjection($db);
                }
                return 'Sichtbarkeit umgeschaltet.';

            case 'delete_type':
                $id = $this->int($this->post('type_id'), 0, 1, PHP_INT_MAX);
                if ($id) {
                    $cmd = new AdHocCommand('DELETE FROM zhl_cert_type WHERE id=@id'); // CASCADE räumt Mappings+Grants
                    $cmd->AddParameter(new Parameter('@id', $id));
                    $db->Execute($cmd);
                    $this->RebuildProjection($db);
                }
                return 'Zertifikat gelöscht.';

            case 'add_resource':
                $tid = $this->int($this->post('type_id'), 0, 1, PHP_INT_MAX);
                $rid = $this->int($this->post('resource_id'), 0, 1, PHP_INT_MAX);
                if ($tid && $rid) {
                    $cmd = new AdHocCommand('INSERT IGNORE INTO zhl_cert_type_resource (cert_type_id, resource_id) VALUES (@t,@r)');
                    $cmd->AddParameter(new Parameter('@t', $tid));
                    $cmd->AddParameter(new Parameter('@r', $rid));
                    $db->Execute($cmd);
                    $this->RebuildProjection($db);
                }
                return 'Gerät zugeordnet.';

            case 'remove_resource':
                $tid = $this->int($this->post('type_id'), 0, 1, PHP_INT_MAX);
                $rid = $this->int($this->post('resource_id'), 0, 1, PHP_INT_MAX);
                if ($tid && $rid) {
                    $cmd = new AdHocCommand('DELETE FROM zhl_cert_type_resource WHERE cert_type_id=@t AND resource_id=@r');
                    $cmd->AddParameter(new Parameter('@t', $tid));
                    $cmd->AddParameter(new Parameter('@r', $rid));
                    $db->Execute($cmd);
                    $this->RebuildProjection($db);
                }
                return 'Gerät entfernt.';

            case 'grant':
                $tid = $this->int($this->post('type_id'), 0, 1, PHP_INT_MAX);
                $userRef = trim($this->post('user_ref'));
                if (!$tid || $userRef === '') {
                    return 'Nutzer oder Zertifikat fehlt.';
                }
                $uid = $this->resolveUser($db, $userRef);
                if (!$uid) {
                    return 'Nutzer „' . $userRef . '" nicht gefunden (E-Mail, Benutzername oder ID).';
                }
                // Jede Einführung gilt standardmäßig 1 Jahr (kein manuelles Ablaufdatum mehr).
                // Durchgängig UTC rechnen (konsistent mit granted_at), kein Default-TZ/DST-Drift.
                $nowDt = new DateTime('now', new DateTimeZone('UTC'));
                $now = $nowDt->format('Y-m-d H:i:s');
                $expires = $nowDt->modify('+1 year')->format('Y-m-d H:i:s');
                $cmd = new AdHocCommand('INSERT INTO zhl_cert_grant (user_id, cert_type_id, granted_at, expires_at, granted_by) VALUES (@u,@t,@g,@e,@by) ON DUPLICATE KEY UPDATE expires_at=VALUES(expires_at), granted_at=VALUES(granted_at)');
                $cmd->AddParameter(new Parameter('@u', $uid));
                $cmd->AddParameter(new Parameter('@t', $tid));
                $cmd->AddParameter(new Parameter('@g', $now));
                $cmd->AddParameter(new Parameter('@e', $expires));
                $cmd->AddParameter(new Parameter('@by', ServiceLocator::GetServer()->GetUserSession()->UserId));
                $db->Execute($cmd);
                $this->RebuildProjection($db);
                return 'Zertifikat zugewiesen.';

            case 'revoke':
                $gid = $this->int($this->post('grant_id'), 0, 1, PHP_INT_MAX);
                if ($gid) {
                    $cmd = new AdHocCommand('DELETE FROM zhl_cert_grant WHERE id=@id');
                    $cmd->AddParameter(new Parameter('@id', $gid));
                    $db->Execute($cmd);
                    $this->RebuildProjection($db);
                }
                return 'Zuweisung entfernt.';
        }
        return '';
    }

    /** zhl_certificate vollständig aus den Grants neu aufbauen (Projektion für die bestehenden Gates). */
    private function RebuildProjection($db)
    {
        $db->Execute(new AdHocCommand('DELETE FROM zhl_certificate'));
        $db->Execute(new AdHocCommand(
            'INSERT INTO zhl_certificate (user_id, resource_id, granted_at, expires_at) ' .
            'SELECT g.user_id, ctr.resource_id, MIN(g.granted_at), ' .
            'CASE WHEN SUM(g.expires_at IS NULL) > 0 THEN NULL ELSE MAX(g.expires_at) END ' .
            'FROM zhl_cert_grant g ' .
            'JOIN zhl_cert_type_resource ctr ON ctr.cert_type_id = g.cert_type_id ' .
            'JOIN zhl_cert_type t ON t.id = g.cert_type_id AND t.active = 1 ' .
            'GROUP BY g.user_id, ctr.resource_id'
        ));
    }

    public function Load()
    {
        $db = ServiceLocator::GetDatabase();

        $types = [];
        $reader = $db->Query(new AdHocCommand('SELECT id, name, active, sort_order, confirm_email FROM zhl_cert_type ORDER BY sort_order, name'));
        while ($row = $reader->GetRow()) {
            $row['resources'] = [];
            $row['grants'] = [];
            $row['info'] = ['transponder_code' => '', 'news_text' => '', 'doc_url' => '', 'active' => 1];
            $types[(int)$row['id']] = $row;
        }
        $reader->Free();

        // D3: vertrauliche Zusatz-Infos je Typ (Admin-Sicht, ungated — nur fürs Editieren).
        foreach (ZhlCertTypeInfo::Map($db) as $tid => $info) {
            if (isset($types[$tid])) {
                $types[$tid]['info'] = $info;
            }
        }

        // Geräte je Zertifikat
        $reader = $db->Query(new AdHocCommand(
            'SELECT ctr.cert_type_id, ctr.resource_id, r.name FROM zhl_cert_type_resource ctr ' .
            'LEFT JOIN resources r ON r.resource_id = ctr.resource_id ORDER BY r.name'
        ));
        while ($row = $reader->GetRow()) {
            $t = (int)$row['cert_type_id'];
            if (isset($types[$t])) {
                $types[$t]['resources'][] = ['resource_id' => (int)$row['resource_id'], 'name' => (string)($row['name'] ?? ('#' . $row['resource_id']))];
            }
        }
        $reader->Free();

        // Grants je Zertifikat
        $reader = $db->Query(new AdHocCommand(
            'SELECT g.id, g.user_id, g.cert_type_id, g.granted_at, g.expires_at, u.fname, u.lname, u.email ' .
            'FROM zhl_cert_grant g LEFT JOIN users u ON u.user_id = g.user_id ORDER BY u.lname, u.fname'
        ));
        $nowUtc = gmdate('Y-m-d H:i:s');
        while ($row = $reader->GetRow()) {
            $t = (int)$row['cert_type_id'];
            if (isset($types[$t])) {
                $exp = $row['expires_at'];
                $types[$t]['grants'][] = [
                    'id' => (int)$row['id'],
                    'name' => trim(((string)$row['fname']) . ' ' . ((string)$row['lname'])),
                    'email' => (string)($row['email'] ?? ''),
                    'expires_at' => $exp,
                    'expired' => ($exp !== null && $exp < $nowUtc),
                ];
            }
        }
        $reader->Free();

        $this->page->SetTypes(array_values($types));
        $this->page->SetResources($this->allResources($db));
    }

    /** @return array[] [{resource_id, label}] alle nicht-versteckten Geräte (für die Zuordnung) */
    private function allResources($db)
    {
        // Geräte-Typ-Attribut für hübschere Labels.
        $aid = 0;
        $reader = $db->Query(new AdHocCommand("SELECT custom_attribute_id FROM custom_attributes WHERE display_label='Geräte-Typ' AND attribute_category=4 LIMIT 1"));
        if ($r = $reader->GetRow()) {
            $aid = (int)$r['custom_attribute_id'];
        }
        $reader->Free();

        $typeMap = [];
        if ($aid > 0) {
            $cmd = new AdHocCommand('SELECT entity_id, attribute_value FROM custom_attribute_values WHERE custom_attribute_id=@a AND attribute_category=4');
            $cmd->AddParameter(new Parameter('@a', $aid));
            $reader = $db->Query($cmd);
            while ($row = $reader->GetRow()) {
                $typeMap[(int)$row['entity_id']] = trim((string)$row['attribute_value']);
            }
            $reader->Free();
        }

        $out = [];
        $reader = $db->Query(new AdHocCommand('SELECT resource_id, name FROM resources WHERE status_id <> 0 ORDER BY name'));
        while ($row = $reader->GetRow()) {
            $rid = (int)$row['resource_id'];
            $type = $typeMap[$rid] ?? '';
            $out[] = ['resource_id' => $rid, 'label' => ($type !== '' ? "[$type] " : '') . (string)$row['name']];
        }
        $reader->Free();
        return $out;
    }

    /** Nutzer per E-Mail, Benutzername oder numerischer ID auflösen. */
    private function resolveUser($db, $ref)
    {
        $cmd = new AdHocCommand('SELECT user_id FROM users WHERE email = @r OR username = @r' . (ctype_digit($ref) ? ' OR user_id = @id' : '') . ' LIMIT 1');
        $cmd->AddParameter(new Parameter('@r', $ref));
        if (ctype_digit($ref)) {
            $cmd->AddParameter(new Parameter('@id', (int)$ref));
        }
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row ? (int)$row['user_id'] : 0;
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
        return max($min, min($max, (int)$v));
    }
}

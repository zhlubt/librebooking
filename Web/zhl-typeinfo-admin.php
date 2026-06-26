<?php
/**
 * ZHL D2 — Info-Material je Geräte-Typ, Admin-Editor (2026-06-26).
 *
 * Pflegt pro Geräte-Typ (Attr „Geräte-Typ") einen Info-Link (Webseite/PDF/Video) + kurzen
 * Info-Text in zhl_type_info. Diese Infos erscheinen:
 *   - nach der Buchung auf der Erfolgsseite + per Bestätigungs-Mail („Geräte-Name → Info-Material"),
 *   - als kleines (i) an den Bundle-Positionen (Assistent).
 *
 * Nur fürs ZHL-Team (Admin). SecurePage + Admin-Check + CSRF. Additive ZHL-Datei, kein Core.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(__DIR__ . '/zhl-handover-lib.php');
require_once(__DIR__ . '/zhl-audit-lib.php');

class ZhlTypeInfoAdminPage extends SecurePage
{
    private const TYP_ATTR_ID = 15; // custom_attributes.display_label = 'Geräte-Typ'

    public function __construct()
    {
        parent::__construct('');
    }

    public function PageLoad()
    {
        $session = $this->server->GetUserSession();
        if (!($session->IsAdmin || $session->IsResourceAdmin || $session->IsScheduleAdmin || $session->IsGroupAdmin)) {
            http_response_code(403);
            echo 'Nur für das ZHL-Team (Admin).';
            return;
        }

        $pdo = zhl_handover_db();
        $h = fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

        // Gültige Geräte-Typen (aus dem Attribut), gegen die gespeichert werden darf.
        $validTypes = [];
        $stmt = $pdo->prepare(
            'SELECT DISTINCT TRIM(attribute_value) AS typ FROM custom_attribute_values
             WHERE custom_attribute_id = ? AND TRIM(attribute_value) <> ? ORDER BY typ'
        );
        $stmt->execute([self::TYP_ATTR_ID, '']);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $validTypes[(string)$r['typ']] = true;
        }

        $flash = null;
        $flashErr = null;
        if ($this->IsPost() && $this->GetForm('action') === 'save') {
            $this->EnforceCSRFCheck();
            $labels = is_array($_POST['typelabel'] ?? null) ? $_POST['typelabel'] : [];
            $urls = is_array($_POST['info_url'] ?? null) ? $_POST['info_url'] : [];
            $texts = is_array($_POST['info_text'] ?? null) ? $_POST['info_text'] : [];
            $actives = is_array($_POST['active'] ?? null) ? $_POST['active'] : [];

            $up = $pdo->prepare(
                'INSERT INTO zhl_type_info (type_label, info_url, info_text, active, updated_at)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE info_url = VALUES(info_url), info_text = VALUES(info_text),
                     active = VALUES(active), updated_at = NOW()'
            );
            $n = 0;
            $pdo->beginTransaction();
            try {
                foreach (array_keys($labels) as $i) {
                    $label = trim((string)($labels[$i] ?? ''));
                    if ($label === '' || empty($validTypes[$label])) {
                        continue; // nur echte Geräte-Typen
                    }
                    $url = trim((string)($urls[$i] ?? ''));
                    // nur http/https-Links zulassen (sonst leeren) — Schutz vor javascript:-URLs.
                    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                        $url = '';
                    }
                    $url = mb_substr($url, 0, 500);
                    $text = trim((string)($texts[$i] ?? ''));
                    $text = $text === '' ? null : mb_substr($text, 0, 1000);
                    $active = !empty($actives[$i]) ? 1 : 0;
                    $up->execute([$label, $url, $text, $active]);
                    $n++;
                }
                $pdo->commit();
                $flash = $n . ' Geräte-Typen gespeichert.';
                zhl_audit_log(array_merge(zhl_audit_actor($session), [
                    'action' => 'typeinfo.save',
                    'entity_type' => 'config',
                    'detail' => ['types' => $n],
                ]));
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $flashErr = 'Speichern fehlgeschlagen: ' . $ex->getMessage();
            }
        }

        // Bestehende Infos laden und mit der Typenliste mergen.
        $existing = [];
        foreach ($pdo->query('SELECT type_label, info_url, info_text, active FROM zhl_type_info') as $r) {
            $existing[(string)$r['type_label']] = $r;
        }
        $types = array_keys($validTypes);
        sort($types, SORT_NATURAL | SORT_FLAG_CASE);

        $csrf = (string)$session->CSRFToken;
        $configured = 0;
        foreach ($types as $t) {
            if (isset($existing[$t]) && trim((string)$existing[$t]['info_url']) !== '') {
                $configured++;
            }
        }
        $total = count($types);
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Info-Material je Geräte-Typ — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .savebar { position:sticky; top:0; z-index:20; background:#fff; border-bottom:1px solid #e3eae6; }
        table.matrix td { vertical-align:middle; }
        .legend code { background:#eef3f0; padding:1px 5px; border-radius:4px; }
    </style>
</head>
<body>
<form method="post" action="zhl-typeinfo-admin.php">
<input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
<input type="hidden" name="action" value="save">

<div class="savebar py-2 mb-3">
  <div class="container d-flex justify-content-between align-items-center" style="max-width:1100px">
    <h1 class="h5 mb-0"><i class="bi bi-info-circle text-success"></i> Info-Material je Geräte-Typ
      <span class="text-muted fs-6 fw-normal">· <?= $configured ?>/<?= $total ?> mit Link</span>
    </h1>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="zhl-uebergabe-admin.php"><i class="bi bi-truck"></i> Übergabe-Matrix</a>
      <a class="btn btn-outline-secondary btn-sm" href="zhl-audit.php"><i class="bi bi-journal-text"></i> Audit-Log</a>
      <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-save"></i> Speichern</button>
    </div>
  </div>
</div>

<div class="container pb-5" style="max-width:1100px">

  <?php if ($flash !== null): ?>
    <div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> <?= $h($flash) ?></div>
  <?php endif; ?>
  <?php if ($flashErr !== null): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> <?= $h($flashErr) ?></div>
  <?php endif; ?>

  <div class="alert alert-light border legend small">
    <b>Wo erscheinen diese Infos?</b>
    <ul class="mb-0 mt-1">
      <li>Nach der Buchung auf der <b>Erfolgsseite</b> und in der <b>Bestätigungs-Mail</b>: „<i>Geräte-Name → Info-Material</i>".</li>
      <li>Als kleines <b>ℹ</b> an den <b>Bundle-Positionen</b> im Assistenten.</li>
      <li>Der <b>Link</b> muss mit <code>http://</code> oder <code>https://</code> beginnen (Webseite, PDF oder Video). Leer = kein Info-Link.</li>
    </ul>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="table-responsive">
      <table class="table table-sm table-hover matrix mb-0">
        <thead class="table-light">
          <tr>
            <th style="min-width:220px">Geräte-Typ</th>
            <th style="min-width:320px">Info-Link (http/https)</th>
            <th style="min-width:260px">Info-Text (optional)</th>
            <th class="text-center">aktiv</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($types as $i => $typ): $e = $existing[$typ] ?? null; $act = $e === null ? true : !empty($e['active']); ?>
          <tr>
            <td>
              <?= $h($typ) ?>
              <input type="hidden" name="typelabel[<?= $i ?>]" value="<?= $h($typ) ?>">
            </td>
            <td><input class="form-control form-control-sm" type="url" name="info_url[<?= $i ?>]" maxlength="500"
                       value="<?= $h($e['info_url'] ?? '') ?>" placeholder="https://…"></td>
            <td><input class="form-control form-control-sm" type="text" name="info_text[<?= $i ?>]" maxlength="1000"
                       value="<?= $h($e['info_text'] ?? '') ?>" placeholder="kurzer Hinweis"></td>
            <td class="text-center">
              <input class="form-check-input" type="checkbox" name="active[<?= $i ?>]" value="1" <?= $act ? 'checked' : '' ?>>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="d-flex justify-content-end">
    <button class="btn btn-success" type="submit"><i class="bi bi-save"></i> Speichern</button>
  </div>
</div>
</form>
<script src="assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
        <?php
    }
}

$page = new ZhlTypeInfoAdminPage();
$page->PageLoad();

<?php
/**
 * ZHL Übergabe-Matrix — Admin-Editor (2026-06-26).
 *
 * Pflegt pro Gerät die Bereitstellungs-/Einweisungs-Regeln (Tabelle zhl_uebergabe):
 *   - Übergabe-Modus: persönliche Abholung (Termin Pflicht) | am Ablageort abholen | nicht nötig
 *   - Einweisung: keine | möglich | notwendig
 *   - Hauspost-Versand erlaubt (eigenständiges Flag, unabhängig vom Übergabe-Modus)
 *   - Abhol- und Rückgabeort (Freitext)
 *
 * Steuert direkt den Buchungspfad: „persönliche Abholung" erzwingt einen Abholtermin
 * (ZhlBookPresenter/ZhlBundleBookPresenter::pickupApplies). SICHERE VORGABE: Geräte ohne
 * Konfiguration gelten als Abholung-Pflicht (ZHL-Entscheidung 2026-06-26).
 *
 * Nur fürs ZHL-Team (Admin). SecurePage + Admin-Check + CSRF. Additive ZHL-Datei, kein Core.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(__DIR__ . '/zhl-handover-lib.php');
require_once(__DIR__ . '/zhl-audit-lib.php');

class ZhlUebergabeAdminPage extends SecurePage
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

        $ABHOLUNG = [
            'abholen_persoenlich' => 'Persönliche Abholung (Termin Pflicht)',
            'ablageort' => 'Am Ablageort abholen',
            'nicht_noetig' => 'Nicht nötig',
        ];
        $EINF = [
            'keine' => 'Keine',
            'moeglich' => 'Möglich (optional)',
            'notwendig' => 'Notwendig (Pflicht)',
        ];

        $flash = null;
        $flashErr = null;
        if ($this->IsPost() && $this->GetForm('action') === 'save') {
            $this->EnforceCSRFCheck();
            $abh = is_array($_POST['abholung'] ?? null) ? $_POST['abholung'] : [];
            $einf = is_array($_POST['einfuehrung'] ?? null) ? $_POST['einfuehrung'] : [];
            $hp = is_array($_POST['hauspost'] ?? null) ? $_POST['hauspost'] : [];
            $aort = is_array($_POST['abholort'] ?? null) ? $_POST['abholort'] : [];
            $rort = is_array($_POST['rueckgabeort'] ?? null) ? $_POST['rueckgabeort'] : [];

            // Nur echte, aktive Geräte zulassen (gegen DB gegengeprüft).
            $valid = [];
            foreach ($pdo->query('SELECT resource_id FROM resources WHERE status_id = 1') as $r) {
                $valid[(int)$r['resource_id']] = true;
            }
            $up = $pdo->prepare(
                'INSERT INTO zhl_uebergabe (resource_id, abholung, einfuehrung, hauspost_allowed, abholort, rueckgabeort, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE abholung = VALUES(abholung), einfuehrung = VALUES(einfuehrung),
                     hauspost_allowed = VALUES(hauspost_allowed), abholort = VALUES(abholort),
                     rueckgabeort = VALUES(rueckgabeort), updated_at = NOW()'
            );
            $n = 0;
            $pdo->beginTransaction();
            try {
                foreach (array_map('intval', array_keys($abh)) as $rid) {
                    if (empty($valid[$rid])) {
                        continue;
                    }
                    $a = isset($ABHOLUNG[$abh[$rid] ?? '']) ? (string)$abh[$rid] : 'abholen_persoenlich';
                    $e = isset($EINF[$einf[$rid] ?? '']) ? (string)$einf[$rid] : 'keine';
                    $hpv = !empty($hp[$rid]) ? 1 : 0;
                    $ao = trim((string)($aort[$rid] ?? ''));
                    $ro = trim((string)($rort[$rid] ?? ''));
                    $up->execute([$rid, $a, $e, $hpv, $ao === '' ? null : mb_substr($ao, 0, 200), $ro === '' ? null : mb_substr($ro, 0, 200)]);
                    $n++;
                }
                $pdo->commit();
                $flash = $n . ' Geräte gespeichert.';
                zhl_audit_log(array_merge(zhl_audit_actor($session), [
                    'action' => 'uebergabe.save',
                    'entity_type' => 'config',
                    'detail' => ['devices' => $n],
                ]));
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $flashErr = 'Speichern fehlgeschlagen: ' . $ex->getMessage();
            }
        }

        $rows = $pdo->query(
            "SELECT r.resource_id AS rid, r.name AS name,
                    COALESCE(cav.attribute_value, '(ohne Geräte-Typ)') AS typ,
                    u.abholung, u.einfuehrung, u.hauspost_allowed, u.abholort, u.rueckgabeort
             FROM resources r
             LEFT JOIN custom_attribute_values cav
                    ON cav.entity_id = r.resource_id AND cav.custom_attribute_id = " . self::TYP_ATTR_ID . "
             LEFT JOIN zhl_uebergabe u ON u.resource_id = r.resource_id
             WHERE r.status_id = 1
             ORDER BY typ, r.name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $byType = [];
        foreach ($rows as $r) {
            $byType[$r['typ']][] = $r;
        }

        // Legacy 'abholen' (und NULL/unbekannt) → im Editor als „persönlich" anzeigen, da der
        // Buchungspfad genau das jetzt erzwingt. Nur 'ablageort'/'nicht_noetig' sind entspannt.
        $normAbh = static fn(?string $v): string => in_array($v, ['ablageort', 'nicht_noetig'], true) ? $v : 'abholen_persoenlich';
        $normEinf = static fn(?string $v): string => in_array($v, ['moeglich', 'notwendig'], true) ? $v : 'keine';

        $csrf = (string)$session->CSRFToken;
        $configured = count(array_filter($rows, static fn($r) => $r['abholung'] !== null));
        $total = count($rows);
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Übergabe-Matrix — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .savebar { position:sticky; top:0; z-index:20; background:#fff; border-bottom:1px solid #e3eae6; }
        .typ-head { background:#eef3f0; }
        table.matrix td { vertical-align:middle; }
        table.matrix .form-select-sm, table.matrix .form-control-sm { min-width:130px; }
        .legend code { background:#eef3f0; padding:1px 5px; border-radius:4px; }
    </style>
</head>
<body>
<form method="post" action="zhl-uebergabe-admin.php">
<input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
<input type="hidden" name="action" value="save">

<div class="savebar py-2 mb-3">
  <div class="container d-flex justify-content-between align-items-center" style="max-width:1200px">
    <h1 class="h5 mb-0"><i class="bi bi-truck text-success"></i> Übergabe-Matrix
      <span class="text-muted fs-6 fw-normal">· <?= $configured ?>/<?= $total ?> Geräte konfiguriert</span>
    </h1>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="zhl-audit.php"><i class="bi bi-journal-text"></i> Audit-Log</a>
      <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-save"></i> Alle Änderungen speichern</button>
    </div>
  </div>
</div>

<div class="container pb-5" style="max-width:1200px">

  <?php if ($flash !== null): ?>
    <div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> <?= $h($flash) ?></div>
  <?php endif; ?>
  <?php if ($flashErr !== null): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> <?= $h($flashErr) ?></div>
  <?php endif; ?>

  <div class="alert alert-light border legend small">
    <b>So wirkt die Matrix auf die Buchung:</b>
    <ul class="mb-0 mt-1">
      <li><b>Übergabe-Modus</b> <code>Persönliche Abholung</code> = beim Buchen ist ein <b>Abholtermin Pflicht</b> (Terminplaner). <code>Am Ablageort</code> / <code>Nicht nötig</code> = kein Termin.</li>
      <li><b>Sichere Vorgabe:</b> Geräte ohne Eintrag gelten als <b>Abholung Pflicht</b>, bis Sie sie hier bewusst lockern.</li>
      <li><b>Einweisung</b> <code>Notwendig</code> = ohne Zertifikat/Einführungstermin keine Buchung möglich.</li>
      <li><b>Hauspost-Versand</b> = dieses Gerät darf per Hauspost verschickt werden. Der Haken entscheidet allein, <b>unabhängig vom Übergabe-Modus</b> (auch bei „Am Ablageort" oder „Nicht nötig").</li>
    </ul>
  </div>

  <?php foreach ($byType as $typ => $devices): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header typ-head fw-semibold py-2">
        <i class="bi bi-tag text-success"></i> <?= $h($typ) ?>
        <span class="text-muted fw-normal">· <?= count($devices) ?> Gerät<?= count($devices) === 1 ? '' : 'e' ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover matrix mb-0">
          <thead class="table-light">
            <tr>
              <th style="min-width:200px">Gerät</th>
              <th>Übergabe-Modus</th>
              <th>Einweisung</th>
              <th class="text-center">Hauspost</th>
              <th>Abholort</th>
              <th>Rückgabeort</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($devices as $d): $rid = (int)$d['rid']; $curAbh = $normAbh($d['abholung']); $curEinf = $normEinf($d['einfuehrung']); ?>
            <tr>
              <td>
                <?= $h($d['name']) ?>
                <?php if ($d['abholung'] === null): ?><span class="badge text-bg-warning ms-1" title="Noch nicht konfiguriert — gilt als Abholung Pflicht">neu</span><?php endif; ?>
              </td>
              <td>
                <select class="form-select form-select-sm" name="abholung[<?= $rid ?>]">
                  <?php foreach ($ABHOLUNG as $val => $label): ?>
                    <option value="<?= $h($val) ?>" <?= $curAbh === $val ? 'selected' : '' ?>><?= $h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <select class="form-select form-select-sm" name="einfuehrung[<?= $rid ?>]">
                  <?php foreach ($EINF as $val => $label): ?>
                    <option value="<?= $h($val) ?>" <?= $curEinf === $val ? 'selected' : '' ?>><?= $h($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="text-center">
                <input class="form-check-input" type="checkbox" name="hauspost[<?= $rid ?>]" value="1" <?= !empty($d['hauspost_allowed']) ? 'checked' : '' ?>>
              </td>
              <td><input class="form-control form-control-sm" type="text" name="abholort[<?= $rid ?>]" maxlength="200" value="<?= $h($d['abholort']) ?>" placeholder="z. B. Raum 4.2.38"></td>
              <td><input class="form-control form-control-sm" type="text" name="rueckgabeort[<?= $rid ?>]" maxlength="200" value="<?= $h($d['rueckgabeort']) ?>" placeholder="z. B. Rückgaberegal"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="d-flex justify-content-end">
    <button class="btn btn-success" type="submit"><i class="bi bi-save"></i> Alle Änderungen speichern</button>
  </div>
</div>
</form>
<script src="assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
        <?php
    }
}

$page = new ZhlUebergabeAdminPage();
$page->PageLoad();

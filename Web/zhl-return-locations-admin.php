<?php
/**
 * ZHL Selbst-Rückgabe — Ablageort-Verwaltung (SPEC-SELBSTRUECKGABE).
 *
 * Pflegt die Ablageorte (Tabelle zhl_return_location) für die Selbst-Rückgabe kleiner Medien:
 *   - Standort anlegen / Label & Hinweis bearbeiten / aktiv schalten
 *   - orts-eigenen QR anzeigen, als PNG öffnen/drucken, Token neu erzeugen (alter QR wird ungültig)
 *
 * Nur fürs ZHL-Team (Admin). SecurePage + Admin-Check + CSRF. Additive ZHL-Datei, kein Core.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(__DIR__ . '/zhl-return-lib.php');
require_once(__DIR__ . '/zhl-audit-lib.php');

class ZhlReturnLocationsAdminPage extends SecurePage
{
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

        $h = fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $flash = null;
        $flashErr = null;

        if ($this->IsPost()) {
            $this->EnforceCSRFCheck();
            $action = (string)$this->GetForm('action');
            try {
                if ($action === 'create') {
                    $label = trim((string)($_POST['label'] ?? ''));
                    if ($label === '') {
                        throw new InvalidArgumentException('Bitte einen Namen angeben.');
                    }
                    $id = zhl_return_location_create(
                        $label,
                        (string)($_POST['slug'] ?? ''),
                        (string)($_POST['note'] ?? ''),
                        !empty($_POST['active'])
                    );
                    $flash = 'Ablageort „' . $label . '" angelegt.';
                    zhl_audit_log(array_merge(zhl_audit_actor($session), [
                        'action' => 'return_location.create', 'entity_type' => 'config',
                        'entity_id' => $id, 'detail' => ['label' => $label],
                    ]));
                } elseif ($action === 'update') {
                    $id = (int)($_POST['id'] ?? 0);
                    zhl_return_location_update(
                        $id,
                        trim((string)($_POST['label'] ?? '')),
                        (string)($_POST['note'] ?? ''),
                        !empty($_POST['active'])
                    );
                    $flash = 'Ablageort gespeichert.';
                    zhl_audit_log(array_merge(zhl_audit_actor($session), [
                        'action' => 'return_location.update', 'entity_type' => 'config', 'entity_id' => $id,
                    ]));
                } elseif ($action === 'regen') {
                    $id = (int)($_POST['id'] ?? 0);
                    zhl_return_location_regenerate_token($id);
                    $flash = 'Neues QR-Token erzeugt — bitte den QR-Code am Ablageort austauschen.';
                    zhl_audit_log(array_merge(zhl_audit_actor($session), [
                        'action' => 'return_location.regen', 'entity_type' => 'config', 'entity_id' => $id,
                    ]));
                }
            } catch (Throwable $ex) {
                $flashErr = 'Aktion fehlgeschlagen: ' . $ex->getMessage();
            }
        }

        $locations = zhl_return_locations_all();
        $csrf = (string)$session->CSRFToken;
        $base = rtrim((string)Configuration::Instance()->GetScriptUrl(), '/');
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ablageorte (Selbst-Rückgabe) — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .wrap { max-width: 1000px; }
        .btn-zhl { background:#009260; border-color:#009260; color:#fff; }
        .btn-zhl:hover { background:#007a50; border-color:#007a50; color:#fff; }
        .qr-img { width:160px; height:160px; border:1px solid #e3eae6; border-radius:8px; background:#fff; }
        .url-box { font-family: ui-monospace, SFMono-Regular, monospace; font-size:.8rem; word-break:break-all; }
    </style>
</head>
<body>
<div class="container wrap py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-geo-alt text-success"></i> Ablageorte — Selbst-Rückgabe</h1>
    <a class="btn btn-outline-secondary btn-sm" href="zhl-uebergabe-admin.php"><i class="bi bi-truck"></i> Übergabe-Matrix</a>
  </div>

  <p class="text-muted">
    Kleine Medien (Rückgabe-Modus „Am Rückgabeort abgeben" / „Nicht nötig") können Ausleihende selbst an
    einem dieser Orte ablegen: QR scannen → einloggen oder per E-Mail anmelden → Medien wählen → Pflicht-Foto
    → Meldung. Das Team bestätigt die Rückgabe anschließend über die normale Checkliste.
  </p>

  <?php if ($flash !== null): ?><div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= $h($flash) ?></div><?php endif; ?>
  <?php if ($flashErr !== null): ?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= $h($flashErr) ?></div><?php endif; ?>

  <?php foreach ($locations as $loc):
      $id = (int)$loc['id'];
      $dropUrl = $base . '/zhl-return-drop.php?loc=' . urlencode((string)$loc['qr_token']);
  ?>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3 text-center">
            <img class="qr-img" src="zhl-return-qr.php?loc=<?= $id ?>" alt="QR <?= $h((string)$loc['label']) ?>">
            <div class="mt-2 d-flex flex-column gap-1">
              <a class="btn btn-outline-secondary btn-sm" href="zhl-return-qr.php?loc=<?= $id ?>" target="_blank" rel="noopener">
                <i class="bi bi-printer"></i> QR öffnen / drucken
              </a>
              <form method="post" onsubmit="return confirm('Neues Token erzeugen? Der bisher gedruckte QR wird damit ungültig.');">
                <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
                <input type="hidden" name="action" value="regen">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button class="btn btn-outline-danger btn-sm w-100" type="submit"><i class="bi bi-arrow-repeat"></i> Token neu</button>
              </form>
            </div>
          </div>
          <div class="col-md-9">
            <form method="post">
              <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= $id ?>">
              <div class="row g-2">
                <div class="col-sm-8">
                  <label class="form-label mb-0 small">Name</label>
                  <input class="form-control form-control-sm" name="label" maxlength="120" value="<?= $h((string)$loc['label']) ?>">
                </div>
                <div class="col-sm-4 d-flex align-items-end">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="active" id="active<?= $id ?>" <?= (int)$loc['active'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="active<?= $id ?>">aktiv (QR funktioniert)</label>
                  </div>
                </div>
                <div class="col-12">
                  <label class="form-label mb-0 small">Hinweis auf der Rückgabe-Seite (optional)</label>
                  <input class="form-control form-control-sm" name="note" maxlength="255" value="<?= $h((string)($loc['note'] ?? '')) ?>">
                </div>
              </div>
              <div class="mt-2 small text-muted">Slug: <code><?= $h((string)$loc['slug']) ?></code></div>
              <div class="mt-1 small">Drop-URL: <span class="url-box"><?= $h($dropUrl) ?></span></div>
              <button class="btn btn-zhl btn-sm mt-2" type="submit"><i class="bi bi-save"></i> Speichern</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <h2 class="h6"><i class="bi bi-plus-circle text-success"></i> Neuen Ablageort anlegen</h2>
      <form method="post" class="row g-2">
        <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
        <input type="hidden" name="action" value="create">
        <div class="col-sm-5">
          <label class="form-label mb-0 small">Name</label>
          <input class="form-control form-control-sm" name="label" maxlength="120" placeholder="z. B. Cateringwagen" required>
        </div>
        <div class="col-sm-3">
          <label class="form-label mb-0 small">Slug (optional)</label>
          <input class="form-control form-control-sm" name="slug" maxlength="40" placeholder="auto aus Name">
        </div>
        <div class="col-sm-4 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="active" id="newActive" checked>
            <label class="form-check-label" for="newActive">aktiv</label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label mb-0 small">Hinweis (optional)</label>
          <input class="form-control form-control-sm" name="note" maxlength="255" placeholder="z. B. Bitte in die graue Box legen">
        </div>
        <div class="col-12">
          <button class="btn btn-zhl btn-sm mt-1" type="submit"><i class="bi bi-plus-lg"></i> Ablageort anlegen</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
        <?php
    }
}

$page = new ZhlReturnLocationsAdminPage();
$page->PageLoad();

<?php
/**
 * ZHL Material-QR — Scan-Ziel / Rückgabe-Einstieg pro Gerät (Block D3).
 *
 * Wird vom stabilen Geräte-QR (zhl-resource-qr.php) aufgerufen. Findet die AKTUELL
 * OFFENE Rückgabe des Geräts (zhl_booking_handover type='return',
 * status IN ('requested','confirmed'), älteste Soll-Rückgabe zuerst) und zeigt
 * Gerät, Ausleihender, Soll-Rückgabe und Rückgabeort. Mit einer Bestätigungs-Aktion
 * leitet die Seite auf die BESTEHENDE Checkliste (zhl-handover-check.php, type=return)
 * weiter — dort wird das Protokoll geschrieben und status='done' gesetzt (bewährter Pfad,
 * keine Reimplementierung). Ohne offene Rückgabe: klare Meldung + Link zum Medienmanager.
 *
 * ADDITIV, ADMIN-only. SecurePage + Admin-Check. Kein Eingriff in den Buchungspfad.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(__DIR__ . '/zhl-handover-lib.php');
require_once(__DIR__ . '/zhl-return-lib.php');

class ZhlResourceReturnPage extends SecurePage
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

        $resourceId = (int)$this->GetQuerystring('resource');
        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        // Gerätename (auch ohne offene Rückgabe für die Anzeige nützlich).
        $resourceName = '';
        if ($resourceId > 0) {
            $stmt = zhl_handover_db()->prepare('SELECT name FROM resources WHERE resource_id = ?');
            $stmt->execute([$resourceId]);
            $resourceName = (string)$stmt->fetchColumn();
        }

        $open = $resourceId > 0 ? zhl_handover_open_return_for_resource($resourceId) : null;

        // Soll-Rückgabe lokal anzeigen.
        $tzName = Configuration::Instance()->GetDefaultTimezone();
        try {
            $tz = new DateTimeZone($tzName);
        } catch (Throwable $e) {
            $tz = new DateTimeZone('UTC');
        }
        $utc = new DateTimeZone('UTC');
        $fmtLocal = function (?string $utcStr) use ($utc, $tz): string {
            if (!$utcStr) {
                return '—';
            }
            try {
                return (new DateTime($utcStr, $utc))->setTimezone($tz)->format('d.m.Y H:i');
            } catch (Throwable $e) {
                return '—';
            }
        };

        $borrower = '';
        $confirmQs = '';
        $self = $open ? zhl_return_self_for_handover((int)$open['id']) : null;
        if ($open) {
            $ref = (string)($open['reference_number'] ?? '');
            $token = (string)($open['handover_token'] ?? '');
            $borrower = zhl_handover_borrower_name($ref !== '' ? $ref : null, $token !== '' ? $token : null);
            $confirmQs = 'ref=' . urlencode($ref) . '&token=' . urlencode($token)
                . '&type=return&resource=' . $resourceId;
            if ($resourceName === '') {
                $resourceName = (string)($open['resource_name'] ?? '');
            }
        }
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geräte-Rückgabe — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .zhl-wrap { max-width: 620px; }
        .btn-zhl { background:#009260; border-color:#009260; color:#fff; }
        .btn-zhl:hover { background:#007a50; border-color:#007a50; color:#fff; }
    </style>
</head>
<body>
<div class="container zhl-wrap py-4">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h1 class="h4 mb-3"><i class="bi bi-qr-code-scan text-success"></i> Geräte-Rückgabe</h1>

      <?php if ($resourceId <= 0): ?>
        <div class="alert alert-danger">Ungültige Geräte-ID.</div>
        <a class="btn btn-outline-secondary" href="zhl-medienmanager.php"><i class="bi bi-arrow-left"></i> Zum Medienmanager</a>

      <?php elseif (!$open): ?>
        <p class="text-muted">
          <?php if ($resourceName !== ''): ?><strong><?= $h($resourceName) ?></strong><?php else: ?>Gerät #<?= $resourceId ?><?php endif; ?>
        </p>
        <div class="alert alert-light border">
          <i class="bi bi-info-circle text-success"></i>
          Für dieses Gerät ist aktuell keine Rückgabe offen.
        </div>
        <a class="btn btn-outline-secondary" href="zhl-medienmanager.php"><i class="bi bi-truck"></i> Zum Medienmanager</a>

      <?php else: ?>
        <dl class="row mb-3">
          <dt class="col-sm-4">Gerät</dt>
          <dd class="col-sm-8"><?= $resourceName !== '' ? $h($resourceName) : 'Gerät #' . $resourceId ?></dd>

          <dt class="col-sm-4">Ausleihende:r</dt>
          <dd class="col-sm-8"><?= $borrower !== '' ? $h($borrower) : '<span class="text-muted">unbekannt</span>' ?></dd>

          <dt class="col-sm-4">Soll-Rückgabe</dt>
          <dd class="col-sm-8"><?= $h($fmtLocal($open['scheduled_end_utc'] ?? null)) ?> Uhr</dd>

          <dt class="col-sm-4">Rückgabeort</dt>
          <dd class="col-sm-8"><?= ($open['rueckgabeort'] ?? '') !== '' ? $h((string)$open['rueckgabeort']) : '—' ?></dd>
        </dl>

        <?php if ($self !== null): ?>
          <div class="alert alert-info">
            <i class="bi bi-box-arrow-in-down"></i>
            <strong>Vom Nutzer selbst abgelegt</strong> am
            <?= $h($fmtLocal($self['reported_at'] ?? null)) ?> Uhr
            <?php if (($self['location_label'] ?? '') !== ''): ?>am <strong><?= $h((string)$self['location_label']) ?></strong><?php endif; ?>.
            <?php if (($self['note'] ?? '') !== ''): ?><br><span class="small">Notiz: <?= $h((string)$self['note']) ?></span><?php endif; ?>
            <?php if (($self['photo_path'] ?? '') !== ''): ?>
              <br><a class="small" href="zhl-return-photo.php?id=<?= (int)$self['id'] ?>" target="_blank" rel="noopener"><i class="bi bi-image"></i> Foto-Nachweis ansehen</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <p class="small text-muted">
          Mit „Rückgabe bestätigen" öffnen Sie die Checkliste, erfassen Zustand und Vollständigkeit
          und schließen die Rückgabe ab (Gerät wird wieder ausleihbar).
        </p>

        <a class="btn btn-zhl btn-lg w-100" href="zhl-handover-check.php?<?= $h($confirmQs) ?>">
          <i class="bi bi-clipboard-check"></i> Rückgabe bestätigen
        </a>
        <a class="btn btn-link btn-sm mt-2" href="zhl-medienmanager.php"><i class="bi bi-arrow-left"></i> Zum Medienmanager</a>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
        <?php
    }
}

$page = new ZhlResourceReturnPage();
$page->PageLoad();

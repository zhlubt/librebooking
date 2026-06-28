<?php
/**
 * ZHL Medienmanager — Tagesseite (Block D1).
 *
 * Zeigt dem ZHL-Team (Admin) alle FÄLLIGEN RÜCKGABEN eines Tages
 * (zhl_booking_handover type='return', scheduled_end_utc am gewählten Tag),
 * gruppiert nach RÜCKGABEORT (zhl_uebergabe.rueckgabeort), damit der Medienmanager
 * weiß, wo er nachsehen muss. Je Zeile: Gerät, Ausleihender, Soll-Rückgabe (lokal),
 * Status und „Rückgabe bestätigen" → bestehende Checkliste (zhl-handover-check.php),
 * die das Protokoll schreibt und status='done' setzt.
 *
 * ADDITIV, ADMIN-only — kein Eingriff in den Buchungspfad. SecurePage + Admin-Check,
 * Stil wie zhl-handover-admin.php (eigenständige Seite, echoet HTML mit dem grünen ZHL-Theme).
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(__DIR__ . '/zhl-handover-lib.php');

class ZhlMedienmanagerPage extends SecurePage
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

        // App-Zeitzone bestimmen (für Tagesgrenzen + Anzeige lokaler Zeiten).
        $tzName = Configuration::Instance()->GetDefaultTimezone();
        try {
            $tz = new DateTimeZone($tzName);
        } catch (Throwable $e) {
            $tz = new DateTimeZone('UTC');
        }
        $utc = new DateTimeZone('UTC');

        // Tag aus ?day=YYYY-MM-DD (validiert), sonst „heute" in App-Zeitzone.
        $dayParam = (string)$this->GetQuerystring('day');
        $day = DateTime::createFromFormat('!Y-m-d', $dayParam, $tz);
        $errors = DateTime::getLastErrors();
        if (!$day || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
            $day = new DateTime('today', $tz);
        }
        $dayStr = $day->format('Y-m-d');

        // Lokale Tagesgrenzen → UTC (halboffenes Intervall [start, nextDay)).
        $startLocal = new DateTime($dayStr . ' 00:00:00', $tz);
        $endLocal = (clone $startLocal)->modify('+1 day');
        $startUtc = (clone $startLocal)->setTimezone($utc)->format('Y-m-d H:i:s');
        $endUtc = (clone $endLocal)->setTimezone($utc)->format('Y-m-d H:i:s');

        $prevDay = (clone $startLocal)->modify('-1 day')->format('Y-m-d');
        $nextDay = $endLocal->format('Y-m-d');
        $today = (new DateTime('today', $tz))->format('Y-m-d');

        $rows = zhl_handover_returns_due($startUtc, $endUtc);

        // Gruppieren nach Rückgabeort (leerer Ort → „—" / Sammelgruppe ans Ende).
        $groups = [];
        foreach ($rows as $r) {
            $ort = trim((string)($r['rueckgabeort'] ?? ''));
            $key = $ort !== '' ? $ort : '__none__';
            $groups[$key][] = $r;
        }
        // Sammelgruppe ohne Ort ans Ende sortieren.
        if (isset($groups['__none__'])) {
            $none = $groups['__none__'];
            unset($groups['__none__']);
            $groups['__none__'] = $none;
        }

        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $badge = ['requested' => 'text-bg-secondary', 'confirmed' => 'text-bg-primary', 'done' => 'text-bg-success', 'cancelled' => 'text-bg-light'];
        $statusLabel = ['requested' => 'angefragt', 'confirmed' => 'terminiert', 'done' => 'zurück', 'cancelled' => 'storniert'];

        // UTC-Zeit der Soll-Rückgabe lokal anzeigen.
        $fmtLocal = function (?string $utcStr) use ($utc, $tz): string {
            if (!$utcStr) {
                return '—';
            }
            try {
                return (new DateTime($utcStr, $utc))->setTimezone($tz)->format('H:i');
            } catch (Throwable $e) {
                return '—';
            }
        };

        $weekday = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'][(int)$day->format('w')];
        $total = count($rows);
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medienmanager — Rückgaben des Tages — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .btn-zhl { background:#009260; border-color:#009260; color:#fff; }
        .btn-zhl:hover { background:#007a50; border-color:#007a50; color:#fff; }
        .ort-head { color:#009260; }
    </style>
</head>
<body>
<div class="container pt-3" style="max-width:1100px">
  <a class="btn btn-sm btn-outline-secondary" href="zhl-dashboard.php"><i class="bi bi-arrow-left"></i> Zurück zum Dashboard</a>
</div>
<div class="container py-4" style="max-width:1100px">
  <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
    <h1 class="h4 mb-0"><i class="bi bi-truck text-success"></i> Medienmanager — Rückgaben</h1>
    <a class="btn btn-sm btn-outline-secondary" href="zhl-handover-admin.php"><i class="bi bi-box-seam"></i> Alle Übergaben</a>
  </div>
  <p class="text-muted mb-3">Fällige Rückgaben — gruppiert nach Rückgabeort. Bitte bestätigen Sie jede zurückgegebene Position über die Checkliste.</p>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="btn-group btn-group-sm">
      <a class="btn btn-outline-secondary" href="?day=<?= $h($prevDay) ?>"><i class="bi bi-chevron-left"></i> Vortag</a>
      <a class="btn btn-outline-secondary <?= $dayStr === $today ? 'active' : '' ?>" href="?day=<?= $h($today) ?>">Heute</a>
      <a class="btn btn-outline-secondary" href="?day=<?= $h($nextDay) ?>">Folgetag <i class="bi bi-chevron-right"></i></a>
    </div>
    <div class="fw-semibold">
      <i class="bi bi-calendar-event text-success"></i>
      <?= $h($weekday) ?>, <?= $h($day->format('d.m.Y')) ?>
      <span class="badge text-bg-success ms-1"><?= (int)$total ?> Rückgabe<?= $total === 1 ? '' : 'n' ?></span>
    </div>
  </div>

  <?php if ($total === 0): ?>
    <div class="alert alert-light border text-center py-4">
      <i class="bi bi-check2-circle text-success fs-3 d-block mb-2"></i>
      Für <?= $h($day->format('d.m.Y')) ?> sind keine Rückgaben fällig.
    </div>
  <?php else: ?>
    <?php foreach ($groups as $key => $items):
        $ortLabel = $key === '__none__' ? 'Ohne festen Rückgabeort' : $key; ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h2 class="h6 mb-0 ort-head">
            <i class="bi bi-geo-alt-fill"></i> <?= $h($ortLabel) ?>
            <span class="text-muted fw-normal">(<?= count($items) ?>)</span>
          </h2>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr>
              <th>Gerät</th><th>Ausleihende:r</th><th>Soll-Rückgabe</th>
              <th>Status</th><th class="text-end">Aktion</th>
            </tr></thead>
            <tbody>
            <?php foreach ($items as $r):
                $resourceId = (int)($r['resource_id'] ?? 0);
                $ref = (string)($r['reference_number'] ?? '');
                $token = (string)($r['handover_token'] ?? '');
                $borrower = zhl_handover_borrower_name($ref !== '' ? $ref : null, $token !== '' ? $token : null);
                $status = (string)$r['status'];
                $qs = 'ref=' . urlencode($ref) . '&token=' . urlencode($token)
                    . '&type=return&resource=' . $resourceId; ?>
              <tr>
                <td>
                  <?= $h((string)($r['resource_name'] ?? '—')) ?>
                  <?php if ($resourceId): ?>
                    <a class="text-muted ms-1" title="Geräte-QR drucken" href="zhl-resource-qr.php?resource=<?= $resourceId ?>"><i class="bi bi-qr-code"></i></a>
                  <?php endif; ?>
                </td>
                <td><?= $borrower !== '' ? $h($borrower) : '<span class="text-muted">unbekannt</span>' ?></td>
                <td class="small"><?= $h($fmtLocal($r['scheduled_end_utc'] ?? null)) ?> Uhr</td>
                <td><span class="badge <?= $badge[$status] ?? 'text-bg-secondary' ?>"><?= $h($statusLabel[$status] ?? $status) ?></span></td>
                <td class="text-end">
                  <?php if ($status === 'done'): ?>
                    <span class="text-success"><i class="bi bi-check-circle"></i> bestätigt</span>
                  <?php else: ?>
                    <a class="btn btn-sm btn-zhl" href="zhl-handover-check.php?<?= $h($qs) ?>">
                      <i class="bi bi-clipboard-check"></i> Rückgabe bestätigen
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<script src="assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
        <?php
    }
}

$page = new ZhlMedienmanagerPage();
$page->PageLoad();

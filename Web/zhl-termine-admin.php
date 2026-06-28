<?php
/**
 * ZHL — Geplante Termine (Übergaben, Rückgaben, Einführungen) als Admin-Übersicht.
 *
 * Eine einzige, gut lesbare Tabelle aller terminierten zhl_booking_handover-Vorgänge
 * (type pickup/return/einf) in einem Zeitfenster. Je Zeile: lokaler Termin, Typ, Gerät,
 * Ausleihende:r, dessen E-Mail (auslesbar + „Mail schreiben" via mailto → öffnet das
 * lokale Outlook), Status, Link zur Buchung (zhl-booking-detail.php) und zum Protokoll.
 *
 * Dazu der Abo-Link auf den Outlook-Kalender-Feed (zhl-calendar.php), der dieselben
 * Termine + die Videostudio-Buchungen als .ics liefert.
 *
 * ADDITIV, ADMIN-only — kein Eingriff in den Buchungspfad. SecurePage + Admin-Check,
 * eigenständige Seite im grünen ZHL-Theme (Muster wie zhl-handover-admin.php /
 * zhl-medienmanager.php).
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(__DIR__ . '/zhl-handover-lib.php');

class ZhlTermineAdminPage extends SecurePage
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

        // App-Zeitzone für Fensterberechnung + Anzeige.
        $tzName = Configuration::Instance()->GetDefaultTimezone();
        try {
            $tz = new DateTimeZone($tzName);
        } catch (Throwable $e) {
            $tz = new DateTimeZone('UTC');
        }
        $utc = new DateTimeZone('UTC');
        $todayLocal = new DateTime('today', $tz);

        // Zeitfenster wählen (?range=upcoming|week|all).
        $range = (string)$this->GetQuerystring('range');
        if (!in_array($range, ['upcoming', 'week', 'all'], true)) {
            $range = 'upcoming';
        }
        if ($range === 'week') {
            // Aktuelle ISO-Woche (Mo–So).
            $startLocal = (clone $todayLocal)->modify('monday this week');
            $endLocal = (clone $startLocal)->modify('+7 days');
        } elseif ($range === 'all') {
            // Letzte 30 Tage bis +180 Tage (Betriebssicht inkl. jüngster Vergangenheit).
            $startLocal = (clone $todayLocal)->modify('-30 days');
            $endLocal = (clone $todayLocal)->modify('+180 days');
        } else {
            // Geplant: ab heute bis +90 Tage.
            $startLocal = clone $todayLocal;
            $endLocal = (clone $todayLocal)->modify('+90 days');
        }
        $startUtc = (clone $startLocal)->setTimezone($utc)->format('Y-m-d H:i:s');
        $endUtc = (clone $endLocal)->setTimezone($utc)->format('Y-m-d H:i:s');

        // Typ-Filter (?type=pickup|return|einf).
        $type = (string)$this->GetQuerystring('type');
        $type = in_array($type, ['pickup', 'return', 'einf'], true) ? $type : null;

        $rows = zhl_handover_planned($startUtc, $endUtc, $type);

        // Kalender-Abo-URL (absolut), falls ein Schlüssel konfiguriert ist.
        $base = rtrim(Configuration::Instance()->GetScriptUrl(), '/');
        $calKey = '';
        $calFile = dirname(__DIR__) . '/config/zhl-calendar.php';
        if (is_readable($calFile)) {
            $calConf = require $calFile;
            $calKey = is_array($calConf) ? (string)($calConf['key'] ?? '') : '';
        }
        $calUrl = ($calKey !== '') ? ($base . '/zhl-calendar.php?key=' . urlencode($calKey)) : '';

        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        // UTC-Datetime lokal formatieren.
        $fmt = function (?string $utcStr, string $f) use ($utc, $tz): string {
            if (!$utcStr) {
                return '—';
            }
            try {
                return (new DateTime($utcStr, $utc))->setTimezone($tz)->format($f);
            } catch (Throwable $e) {
                return '—';
            }
        };

        $typeLabel = ['pickup' => 'Abholung', 'return' => 'Rückgabe', 'einf' => 'Einführung'];
        $typeBadge = ['pickup' => 'text-bg-light text-dark border', 'return' => 'text-bg-info', 'einf' => 'text-bg-warning text-dark'];
        $statusBadge = ['requested' => 'text-bg-secondary', 'confirmed' => 'text-bg-primary', 'done' => 'text-bg-success'];
        $statusLabel = ['requested' => 'angefragt', 'confirmed' => 'terminiert', 'done' => 'erledigt'];

        $weekdayShort = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

        $borrowerDisplay = function (array $r): string {
            $name = trim((string)($r['borrower_name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
            return trim((string)($r['borrower_email'] ?? ''));
        };

        $total = count($rows);
        $rangeLabel = ['upcoming' => 'Geplant (ab heute, 90 Tage)', 'week' => 'Diese Woche', 'all' => 'Alle (−30 … +180 Tage)'][$range];
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geplante Termine — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .btn-zhl { background:#009260; border-color:#009260; color:#fff; }
        .btn-zhl:hover { background:#007a50; border-color:#007a50; color:#fff; }
        .text-zhl { color:#009260; }
        .mono { font-variant-numeric: tabular-nums; }
        td.mail-cell { max-width: 230px; }
        td.mail-cell .addr { word-break: break-all; }
    </style>
</head>
<body>
<div class="container py-4" style="max-width:1200px">
  <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
    <h1 class="h4 mb-0"><i class="bi bi-calendar2-week text-success"></i> Geplante Termine</h1>
    <div class="btn-group btn-group-sm">
      <a class="btn btn-outline-secondary" href="zhl-medienmanager.php"><i class="bi bi-truck"></i> Rückgaben heute</a>
      <a class="btn btn-outline-secondary" href="zhl-handover-admin.php"><i class="bi bi-box-seam"></i> Alle Übergaben</a>
    </div>
  </div>
  <p class="text-muted mb-3">Übergaben, Rückgaben und Einführungen im Überblick — mit Buchungslink und Mailkontakt zum Ausleihenden.</p>

  <?php if ($calUrl !== ''): ?>
    <div class="alert alert-light border d-flex align-items-center justify-content-between flex-wrap gap-2 py-2">
      <span><i class="bi bi-calendar-plus text-success"></i>
        <strong>Outlook-Kalender abonnieren:</strong>
        diese Termine + Videostudio-Buchungen als laufend aktualisierter Kalender.</span>
      <span class="d-flex gap-2">
        <input class="form-control form-control-sm" style="min-width:320px" readonly
               onclick="this.select()" value="<?= $h($calUrl) ?>">
        <a class="btn btn-sm btn-zhl" href="<?= $h(str_replace(['https://','http://'], 'webcal://', $calUrl)) ?>">
          <i class="bi bi-calendar-check"></i> Abonnieren
        </a>
      </span>
    </div>
  <?php else: ?>
    <div class="alert alert-warning border py-2 small">
      <i class="bi bi-exclamation-triangle"></i> Kalender-Abo noch nicht aktiv —
      <code>config/zhl-calendar.php</code> mit einem <code>key</code> anlegen (siehe <code>config/zhl-calendar.example.php</code>).
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="btn-group btn-group-sm" role="group" aria-label="Zeitraum">
      <a class="btn btn-outline-secondary <?= $range === 'upcoming' ? 'active' : '' ?>" href="?range=upcoming<?= $type ? '&type=' . $h($type) : '' ?>">Geplant</a>
      <a class="btn btn-outline-secondary <?= $range === 'week' ? 'active' : '' ?>" href="?range=week<?= $type ? '&type=' . $h($type) : '' ?>">Diese Woche</a>
      <a class="btn btn-outline-secondary <?= $range === 'all' ? 'active' : '' ?>" href="?range=all<?= $type ? '&type=' . $h($type) : '' ?>">Alle</a>
    </div>
    <div class="btn-group btn-group-sm" role="group" aria-label="Typ">
      <a class="btn btn-outline-secondary <?= $type === null ? 'active' : '' ?>" href="?range=<?= $h($range) ?>">Alle Typen</a>
      <a class="btn btn-outline-secondary <?= $type === 'pickup' ? 'active' : '' ?>" href="?range=<?= $h($range) ?>&type=pickup">Abholungen</a>
      <a class="btn btn-outline-secondary <?= $type === 'return' ? 'active' : '' ?>" href="?range=<?= $h($range) ?>&type=return">Rückgaben</a>
      <a class="btn btn-outline-secondary <?= $type === 'einf' ? 'active' : '' ?>" href="?range=<?= $h($range) ?>&type=einf">Einführungen</a>
    </div>
    <span class="fw-semibold text-zhl"><?= $h($rangeLabel) ?> · <?= (int)$total ?> Termin<?= $total === 1 ? '' : 'e' ?></span>
  </div>

  <?php if ($total === 0): ?>
    <div class="alert alert-light border text-center py-4">
      <i class="bi bi-calendar-x text-success fs-3 d-block mb-2"></i>
      Keine Termine in diesem Zeitraum.
    </div>
  <?php else: ?>
    <div class="card shadow-sm"><div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr>
          <th>Termin</th><th>Typ</th><th>Gerät</th><th>Ausleihende:r</th>
          <th>E-Mail</th><th>Status</th><th class="text-end">Aktionen</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $t = (string)$r['type'];
            $ref = (string)($r['reference_number'] ?? '');
            $token = (string)($r['handover_token'] ?? '');
            $resourceId = (int)($r['resource_id'] ?? 0);
            $name = $borrowerDisplay($r);
            $email = trim((string)($r['borrower_email'] ?? ''));
            $status = (string)$r['status'];
            $startUtcRow = $r['scheduled_start_utc'] ?? null;
            $endUtcRow = $r['scheduled_end_utc'] ?? null;
            $dateStr = $fmt($startUtcRow, 'd.m.Y');
            $wd = '';
            if ($startUtcRow) {
                try {
                    $wd = $weekdayShort[(int)(new DateTime($startUtcRow, $utc))->setTimezone($tz)->format('w')];
                } catch (Throwable $e) {
                    $wd = '';
                }
            }
            $timeStr = $fmt($startUtcRow, 'H:i') . '–' . $fmt($endUtcRow, 'H:i');
            $checkQs = 'ref=' . urlencode($ref) . '&token=' . urlencode($token)
                . '&type=' . urlencode($t === 'einf' ? 'pickup' : $t) . '&resource=' . $resourceId;
            // mailto: Betreff mit Vorgang + Gerät; Body als freundliche Anrede vorbefüllt.
            // Härtung gegen Header-Injection: aus Adresse + Betreff ALLE Steuerzeichen
            // (inkl. CR/LF) entfernen; im Body Steuerzeichen außer dem Zeilenumbruch (\n).
            $stripCtl = static fn(string $s): string => (string)preg_replace('/[\x00-\x1F\x7F]/', '', $s);
            $stripCtlBody = static fn(string $s): string => (string)preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $s);
            $subject = sprintf('ZHL Medienausleihe: %s — %s', $typeLabel[$t] ?? $t, (string)($r['resource_name'] ?? ''));
            $bodyGreeting = 'Hallo' . ($name !== '' ? ' ' . $name : '') . ",\n\n";
            $mailto = 'mailto:' . rawurlencode($stripCtl($email))
                . '?subject=' . rawurlencode($stripCtl($subject))
                . '&body=' . rawurlencode($stripCtlBody($bodyGreeting)); ?>
          <tr>
            <td class="mono">
              <span class="fw-semibold"><?= $h($wd) ?> <?= $h($dateStr) ?></span><br>
              <span class="small text-muted"><?= $h($timeStr) ?> Uhr</span>
            </td>
            <td><span class="badge <?= $typeBadge[$t] ?? 'text-bg-secondary' ?>"><?= $h($typeLabel[$t] ?? $t) ?></span></td>
            <td><?= $h((string)($r['resource_name'] ?? '—')) ?></td>
            <td><?= $name !== '' ? $h($name) : '<span class="text-muted">unbekannt</span>' ?></td>
            <td class="mail-cell small">
              <?php if ($email !== ''): ?>
                <span class="addr"><?= $h($email) ?></span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= $statusBadge[$status] ?? 'text-bg-secondary' ?>"><?= $h($statusLabel[$status] ?? $status) ?></span></td>
            <td class="text-end text-nowrap">
              <?php if ($email !== ''): ?>
                <a class="btn btn-sm btn-zhl" href="<?= $h($mailto) ?>" title="Mail im Outlook schreiben">
                  <i class="bi bi-envelope"></i> Mail
                </a>
              <?php endif; ?>
              <?php if ($ref !== ''): ?>
                <a class="btn btn-sm btn-outline-secondary" href="zhl-booking-detail.php?id=<?= $h(urlencode($ref)) ?>" title="Buchung öffnen">
                  <i class="bi bi-box-arrow-up-right"></i> Buchung
                </a>
              <?php endif; ?>
              <a class="btn btn-sm btn-outline-secondary" href="zhl-handover-check.php?<?= $h($checkQs) ?>" title="Protokoll / Checkliste">
                <i class="bi bi-clipboard-check"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  <?php endif; ?>
</div>
<script src="assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
        <?php
    }
}

$page = new ZhlTermineAdminPage();
$page->PageLoad();

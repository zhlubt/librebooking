<?php
/**
 * ZHL Medienmanager — Ausleihen-Übersicht (Agenda).
 *
 * Zeigt dem ZHL-Team einen vorausschauenden Blick auf anstehende ABHOLUNGEN
 * (zhl_booking_handover type='pickup', status requested|confirmed), chronologisch
 * nach Tag gruppiert für die nächsten N Tage. Je Zeile: Zeit, Gerät, Ausleihende:r,
 * ZUSTÄNDIGE Person (terminplaner-Mitglied, best-effort per lesson_slots.php
 * aufgelöst — Fallback = Rolle), Abholort, Status, Aktion (Protokoll/QR).
 *
 * Ersetzt für den ZHL-Betrieb den nativen admin/manage_reservations.php-Blick
 * (reine, ungruppierte Reservierungsliste) durch die operative Frage „was geht
 * demnächst raus, wer kümmert sich darum". Gegenstück zu zhl-medienmanager.php
 * (dort: fällige RÜCKGABEN eines einzelnen Tages, nach Rückgabeort gruppiert).
 *
 * ADDITIV, ADMIN-only — kein Eingriff in den Buchungspfad. SecurePage + Admin-Check,
 * Stil wie zhl-medienmanager.php / zhl-handover-admin.php (eigenständige Seite,
 * echoet HTML mit dem grünen ZHL-Theme).
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(__DIR__ . '/zhl-handover-lib.php');

class ZhlMedienmanagerAusleihenPage extends SecurePage
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

        // App-Zeitzone bestimmen (für Fensterberechnung + Anzeige lokaler Zeiten).
        $tzName = Configuration::Instance()->GetDefaultTimezone();
        try {
            $tz = new DateTimeZone($tzName);
        } catch (Throwable $e) {
            $tz = new DateTimeZone('UTC');
        }
        $utc = new DateTimeZone('UTC');

        // Vorschau-Fenster aus ?days=7|14|30 (validiert), sonst 14 Tage.
        $daysParam = (int)$this->GetQuerystring('days');
        $days = in_array($daysParam, [7, 14, 30], true) ? $daysParam : 14;

        $todayLocal = new DateTime('today', $tz);
        $windowEndLocal = (clone $todayLocal)->modify('+' . $days . ' days');
        $startUtc = (clone $todayLocal)->setTimezone($utc)->format('Y-m-d H:i:s');
        $endUtc = (clone $windowEndLocal)->setTimezone($utc)->format('Y-m-d H:i:s');

        $rows = zhl_handover_pickups_due($startUtc, $endUtc);

        // Zuständige Person best-effort auflösen (terminplaner, einmal pro Seitenaufruf).
        $staffNames = zhl_handover_staff_names(zhl_handover_type_labels());

        // Nach lokalem Kalendertag gruppieren.
        $groups = [];
        foreach ($rows as $r) {
            $localStart = null;
            if (!empty($r['scheduled_start_utc'])) {
                try {
                    $localStart = (new DateTime((string)$r['scheduled_start_utc'], $utc))->setTimezone($tz);
                } catch (Throwable $e) {
                    $localStart = null;
                }
            }
            if ($localStart === null) {
                continue; // ohne auflösbares Datum nicht in der Agenda einsortierbar
            }
            $key = $localStart->format('Y-m-d');
            $r['_localStart'] = $localStart;
            $groups[$key][] = $r;
        }
        ksort($groups);

        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $badge = ['requested' => 'text-bg-secondary', 'confirmed' => 'text-bg-primary'];
        $statusLabel = ['requested' => 'angefragt', 'confirmed' => 'terminiert'];
        $weekdayNames = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];

        $todayKey = $todayLocal->format('Y-m-d');
        $tomorrowKey = (clone $todayLocal)->modify('+1 day')->format('Y-m-d');
        $total = count($rows);
        $rangeLabel = $todayLocal->format('d.m.') . '–' . (clone $windowEndLocal)->modify('-1 day')->format('d.m.Y');
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medienmanager — Ausleihen — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .btn-zhl { background:#009260; border-color:#009260; color:#fff; }
        .btn-zhl:hover { background:#007a50; border-color:#007a50; color:#fff; }
        .day-head { color:#009260; }
        .role-primary { color:#009260; }
        .role-backup { color:#718096; }
    </style>
</head>
<body>
<div class="container pt-3" style="max-width:1100px">
  <a class="btn btn-sm btn-outline-secondary" href="zhl-dashboard.php"><i class="bi bi-arrow-left"></i> Zurück zum Dashboard</a>
</div>
<div class="container py-4" style="max-width:1100px">
  <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
    <h1 class="h4 mb-0"><i class="bi bi-box-arrow-up-right text-success"></i> Medienmanager — Ausleihen</h1>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="zhl-medienmanager.php"><i class="bi bi-truck"></i> Rückgaben</a>
      <a class="btn btn-sm btn-outline-secondary" href="zhl-handover-admin.php"><i class="bi bi-box-seam"></i> Alle Übergaben</a>
    </div>
  </div>
  <p class="text-muted mb-3">Was leihen wir demnächst aus, und wer kümmert sich darum — chronologisch, über alle Geräte.</p>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="btn-group btn-group-sm">
      <a class="btn btn-outline-secondary <?= $days === 7 ? 'active' : '' ?>" href="?days=7">7 Tage</a>
      <a class="btn btn-outline-secondary <?= $days === 14 ? 'active' : '' ?>" href="?days=14">14 Tage</a>
      <a class="btn btn-outline-secondary <?= $days === 30 ? 'active' : '' ?>" href="?days=30">30 Tage</a>
    </div>
    <div class="fw-semibold">
      <i class="bi bi-calendar-range text-success"></i>
      <?= $h($rangeLabel) ?>
      <span class="badge text-bg-success ms-1"><?= (int)$total ?> Ausleihe<?= $total === 1 ? '' : 'n' ?></span>
    </div>
  </div>

  <?php if ($total === 0): ?>
    <div class="alert alert-light border text-center py-4">
      <i class="bi bi-check2-circle text-success fs-3 d-block mb-2"></i>
      In den nächsten <?= (int)$days ?> Tagen stehen keine Abholungen an.
    </div>
  <?php else: ?>
    <?php foreach ($groups as $key => $items):
        /** @var DateTime $first */
        $first = $items[0]['_localStart'];
        $weekday = $weekdayNames[(int)$first->format('w')];
        $dayTag = $key === $todayKey ? 'Heute' : ($key === $tomorrowKey ? 'Morgen' : null); ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h2 class="h6 mb-0 day-head">
            <i class="bi bi-calendar-event"></i> <?= $h($weekday) ?>, <?= $h($first->format('d.m.Y')) ?>
            <?php if ($dayTag !== null): ?><span class="badge text-bg-success ms-1"><?= $h($dayTag) ?></span><?php endif; ?>
            <span class="text-muted fw-normal">(<?= count($items) ?>)</span>
          </h2>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr>
              <th>Zeit</th><th>Gerät</th><th>Ausleihende:r</th><th>Zuständig</th>
              <th>Abholort</th><th>Status</th><th class="text-end">Aktion</th>
            </tr></thead>
            <tbody>
            <?php foreach ($items as $r):
                $resourceId = (int)($r['resource_id'] ?? 0);
                $ref = (string)($r['reference_number'] ?? '');
                $token = (string)($r['handover_token'] ?? '');
                $borrower = zhl_handover_borrower_name($ref !== '' ? $ref : null, $token !== '' ? $token : null);
                $status = (string)$r['status'];
                $staffId = isset($r['staff_member_id']) ? (int)$r['staff_member_id'] : 0;
                $staffRole = (string)($r['staff_role'] ?? '');
                $staff = $staffId > 0 ? ($staffNames[$staffId] ?? null) : null;
                $staffName = $staff['name'] ?? null;
                $roleKey = $staff['role'] ?? ($staffRole !== '' ? $staffRole : null);
                $roleLabel = $roleKey === 'primary' ? 'Hilfskraft' : ($roleKey === 'backup' ? 'Team' : null);
                $ort = trim((string)($r['abholort'] ?? ''));
                $qs = 'ref=' . urlencode($ref) . '&token=' . urlencode($token)
                    . '&type=pickup&resource=' . $resourceId; ?>
              <tr>
                <td class="small"><?= $h($r['_localStart']->format('H:i')) ?> Uhr</td>
                <td>
                  <?= $h((string)($r['resource_name'] ?? '—')) ?>
                  <?php if ($resourceId): ?>
                    <a class="text-muted ms-1" title="Geräte-QR drucken" href="zhl-resource-qr.php?resource=<?= $resourceId ?>"><i class="bi bi-qr-code"></i></a>
                  <?php endif; ?>
                </td>
                <td><?= $borrower !== '' ? $h($borrower) : '<span class="text-muted">unbekannt</span>' ?></td>
                <td class="small">
                  <?php if ($staffName !== null): ?>
                    <?= $h($staffName) ?><?php if ($roleLabel !== null): ?> <span class="role-<?= $h((string)$roleKey) ?>">(<?= $h($roleLabel) ?>)</span><?php endif; ?>
                  <?php elseif ($roleLabel !== null): ?>
                    <span class="role-<?= $h((string)$roleKey) ?>"><?= $h($roleLabel) ?></span>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="small"><?= $ort !== '' ? $h($ort) : '<span class="text-muted">—</span>' ?></td>
                <td><span class="badge <?= $badge[$status] ?? 'text-bg-secondary' ?>"><?= $h($statusLabel[$status] ?? $status) ?></span></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-zhl" href="zhl-handover-check.php?<?= $h($qs) ?>">
                    <i class="bi bi-clipboard-check"></i> Ausleihe bestätigen
                  </a>
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

$page = new ZhlMedienmanagerAusleihenPage();
$page->PageLoad();

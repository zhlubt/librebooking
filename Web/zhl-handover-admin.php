<?php
/**
 * ZHL Übergabe-Übersicht (Phase B, Betriebsansicht).
 *
 * Zeigt dem ZHL-Team offene/bestätigte/erledigte Übergaben (zhl_booking_handover)
 * mit Links zur Checkliste (Web/zhl-handover-check.php) und QR (Web/zhl-handover-qr.php).
 * Schließt die von Codex bemängelte Lücke „keine Admin-/Betriebsansicht".
 *
 * Nur fürs ZHL-Team (Admin). SecurePage + Admin-Check.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(__DIR__ . '/zhl-handover-lib.php');

class ZhlHandoverAdminPage extends SecurePage
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

        $filter = $this->GetQuerystring('status');
        $filter = in_array($filter, ['requested', 'confirmed', 'done'], true) ? $filter : null;
        $rows = zhl_handover_list($filter);

        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $badge = ['requested' => 'text-bg-secondary', 'confirmed' => 'text-bg-primary', 'done' => 'text-bg-success'];
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Übergaben — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>body{background:#f6f8f7}.qr-pop{max-width:160px}</style>
</head>
<body>
<div class="container pt-3" style="max-width:1100px">
  <a class="btn btn-sm btn-outline-secondary" href="zhl-dashboard.php"><i class="bi bi-arrow-left"></i> Zurück zum Dashboard</a>
</div>
<div class="container py-4" style="max-width:1100px">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-box-seam text-success"></i> Übergaben</h1>
    <div class="btn-group btn-group-sm">
      <a class="btn btn-outline-secondary <?= $filter === null ? 'active' : '' ?>" href="zhl-handover-admin.php">Alle</a>
      <a class="btn btn-outline-secondary <?= $filter === 'requested' ? 'active' : '' ?>" href="?status=requested">Offen</a>
      <a class="btn btn-outline-secondary <?= $filter === 'confirmed' ? 'active' : '' ?>" href="?status=confirmed">Terminiert</a>
      <a class="btn btn-outline-secondary <?= $filter === 'done' ? 'active' : '' ?>" href="?status=done">Erledigt</a>
    </div>
  </div>

  <?php if (empty($rows)): ?>
    <div class="alert alert-light border">Keine Übergaben in dieser Ansicht.</div>
  <?php else: ?>
    <div class="card shadow-sm"><div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr>
          <th>Typ</th><th>Gerät</th><th>Vorgang</th><th>Termin (UTC)</th>
          <th>Rolle</th><th>Status</th><th>Protokoll</th><th class="text-end">Aktion</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $type = $r['type'];
            $typeLabel = $type === 'return' ? 'Rückgabe' : 'Abholung';
            $qs = 'ref=' . urlencode((string)$r['reference_number']) . '&token=' . urlencode((string)$r['handover_token'])
                . '&type=' . urlencode($type) . '&resource=' . (int)$r['resource_id']; ?>
          <tr>
            <td><span class="badge <?= $type === 'return' ? 'text-bg-info' : 'text-bg-light' ?>"><?= $h($typeLabel) ?></span></td>
            <td><?= $h((string)($r['resource_name'] ?? '—')) ?></td>
            <td class="small"><?= $h((string)($r['reference_number'] ?? '—')) ?></td>
            <td class="small"><?= $h((string)($r['scheduled_start_utc'] ?? '—')) ?></td>
            <td class="small"><?= $h((string)($r['staff_role'] ?? '—')) ?></td>
            <td><span class="badge <?= $badge[$r['status']] ?? 'text-bg-secondary' ?>"><?= $h((string)$r['status']) ?></span></td>
            <td><?= $r['has_check'] ? '<span class="text-success"><i class="bi bi-check-circle"></i></span>' : '<span class="text-muted">—</span>' ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary" href="zhl-handover-check.php?<?= $h($qs) ?>"><i class="bi bi-clipboard-check"></i> Protokoll</a>
              <?php if ((int)$r['resource_id'] > 0): ?>
                <a class="btn btn-sm btn-outline-secondary" title="Stabilen Geräte-QR drucken" href="zhl-resource-qr.php?resource=<?= (int)$r['resource_id'] ?>"><i class="bi bi-printer"></i> Geräte-QR</a>
              <?php endif; ?>
              <a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#qr<?= (int)$r['id'] ?>"><i class="bi bi-qr-code"></i></a>
              <div class="collapse" id="qr<?= (int)$r['id'] ?>">
                <img class="qr-pop mt-2" alt="QR" src="zhl-handover-qr.php?<?= $h($qs) ?>">
              </div>
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

$page = new ZhlHandoverAdminPage();
$page->PageLoad();

<?php
/**
 * ZHL Audit-Log — Admin-Ansicht (F37, 2026-06-26).
 *
 * Read-only-Protokoll relevanter ZHL-Aktionen aus Tabelle `zhl_audit` (Schreib-Helfer:
 * Web/zhl-audit-lib.php). Filter nach Aktion, Zeitraum und Freitext; Pagination.
 * Nur fürs ZHL-Team (Admin). SecurePage + Admin-Check. Additive ZHL-Datei, kein Core.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(__DIR__ . '/zhl-handover-lib.php');

class ZhlAuditPage extends SecurePage
{
    private const PER_PAGE = 50;

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

        // --- Filter (alle optional, GET) ---
        $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 190);
        $action = trim((string)($_GET['action'] ?? ''));
        $days = (int)($_GET['days'] ?? 30);
        if ($days < 0 || $days > 3650) {
            $days = 30;
        }
        $page = max(1, (int)($_GET['page'] ?? 1));

        // Distinct-Aktionen für das Dropdown.
        $actions = [];
        foreach ($pdo->query('SELECT DISTINCT action FROM zhl_audit ORDER BY action') as $r) {
            $actions[] = (string)$r['action'];
        }

        // WHERE dynamisch + parametrisiert.
        $where = [];
        $params = [];
        if ($days > 0) {
            $where[] = 'created_utc >= ?';
            $params[] = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        }
        if ($action !== '' && in_array($action, $actions, true)) {
            $where[] = 'action = ?';
            $params[] = $action;
        }
        if ($q !== '') {
            $where[] = '(actor_name LIKE ? OR action LIKE ? OR reference_number LIKE ? OR entity_id LIKE ? OR detail LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Gesamtzahl + Seite.
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM zhl_audit $whereSql");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $pages = max(1, (int)ceil($total / self::PER_PAGE));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * self::PER_PAGE;

        $listStmt = $pdo->prepare(
            "SELECT created_utc, actor_user_id, actor_name, action, entity_type, entity_id, reference_number, detail, ip
             FROM zhl_audit $whereSql ORDER BY id DESC LIMIT " . self::PER_PAGE . " OFFSET " . (int)$offset
        );
        $listStmt->execute($params);
        $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        // UTC → Europe/Berlin für die Anzeige.
        $berlin = new DateTimeZone('Europe/Berlin');
        $fmt = static function (?string $utc) use ($berlin): string {
            if (!$utc) {
                return '';
            }
            try {
                $dt = new DateTime($utc, new DateTimeZone('UTC'));
                $dt->setTimezone($berlin);
                return $dt->format('d.m.Y H:i');
            } catch (Throwable $e) {
                return (string)$utc;
            }
        };
        // Aktion → Badge-Farbe (Bootstrap).
        $badge = static function (string $a): string {
            if (str_starts_with($a, 'booking')) {
                return 'success';
            }
            if (str_starts_with($a, 'hauspost')) {
                return 'info';
            }
            if (str_starts_with($a, 'handover')) {
                return 'primary';
            }
            if (str_starts_with($a, 'uebergabe') || str_starts_with($a, 'config')) {
                return 'secondary';
            }
            return 'light';
        };

        $keep = static fn(array $over) => http_build_query(array_merge(
            ['q' => $q, 'action' => $action, 'days' => $days],
            $over
        ));
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit-Log — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .savebar { position:sticky; top:0; z-index:20; background:#fff; border-bottom:1px solid #e3eae6; }
        table.audit td { vertical-align:top; font-size:.9rem; }
        table.audit .detail { color:#52635b; font-size:.82rem; word-break:break-word; max-width:480px; }
        table.audit code { background:#eef3f0; padding:1px 5px; border-radius:4px; }
        .nowrap { white-space:nowrap; }
    </style>
</head>
<body>

<div class="savebar py-2 mb-3">
  <div class="container d-flex justify-content-between align-items-center" style="max-width:1200px">
    <h1 class="h5 mb-0"><i class="bi bi-journal-text text-success"></i> Audit-Log
      <span class="text-muted fs-6 fw-normal">· <?= $total ?> Einträge<?= $days > 0 ? ' (letzte ' . $days . ' Tage)' : '' ?></span>
    </h1>
    <a class="btn btn-outline-secondary btn-sm" href="zhl-uebergabe-admin.php"><i class="bi bi-arrow-left"></i> Übergabe-Matrix</a>
  </div>
</div>

<div class="container pb-5" style="max-width:1200px">

  <form class="row g-2 align-items-end mb-3" method="get" action="zhl-audit.php">
    <div class="col-md-4">
      <label class="form-label small mb-0">Suche (Person, Aktion, Buchung, Detail)</label>
      <input class="form-control form-control-sm" type="text" name="q" value="<?= $h($q) ?>" placeholder="z. B. Name oder Buchungsnummer">
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-0">Aktion</label>
      <select class="form-select form-select-sm" name="action">
        <option value="">Alle</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= $h($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= $h($a) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-0">Zeitraum</label>
      <select class="form-select form-select-sm" name="days">
        <?php foreach ([7 => '7 Tage', 30 => '30 Tage', 90 => '90 Tage', 365 => '1 Jahr', 0 => 'Alles'] as $val => $label): ?>
          <option value="<?= $val ?>" <?= $days === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-success btn-sm w-100" type="submit"><i class="bi bi-funnel"></i> Filtern</button>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-sm table-hover audit mb-0">
        <thead class="table-light">
          <tr>
            <th class="nowrap">Zeit</th>
            <th>Person</th>
            <th>Aktion</th>
            <th>Objekt</th>
            <th>Buchung</th>
            <th>Detail</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Keine Einträge für diese Filter.</td></tr>
        <?php else: foreach ($rows as $row): ?>
          <tr>
            <td class="nowrap"><?= $h($fmt($row['created_utc'])) ?></td>
            <td><?= $h($row['actor_name']) ?><?php if ($row['actor_user_id']): ?> <span class="text-muted">#<?= (int)$row['actor_user_id'] ?></span><?php endif; ?></td>
            <td><span class="badge text-bg-<?= $badge((string)$row['action']) ?>"><?= $h($row['action']) ?></span></td>
            <td><?php if ($row['entity_type']): ?><span class="text-muted"><?= $h($row['entity_type']) ?></span><?php if ($row['entity_id']): ?> <code><?= $h($row['entity_id']) ?></code><?php endif; ?><?php endif; ?></td>
            <td><?php if ($row['reference_number']): ?><code><?= $h($row['reference_number']) ?></code><?php endif; ?></td>
            <td class="detail"><?= $h($row['detail']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="mt-3 d-flex justify-content-between align-items-center">
      <span class="text-muted small">Seite <?= $page ?> von <?= $pages ?></span>
      <div class="btn-group">
        <a class="btn btn-outline-secondary btn-sm <?= $page <= 1 ? 'disabled' : '' ?>" href="?<?= $h($keep(['page' => $page - 1])) ?>">← Neuer</a>
        <a class="btn btn-outline-secondary btn-sm <?= $page >= $pages ? 'disabled' : '' ?>" href="?<?= $h($keep(['page' => $page + 1])) ?>">Älter →</a>
      </div>
    </nav>
  <?php endif; ?>

</div>
</body>
</html>
        <?php
    }
}

$page = new ZhlAuditPage();
$page->PageLoad();

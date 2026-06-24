<?php
/**
 * ZHL Einführungs-Bestätigung (v-cert) — öffentliche, token-basierte Seite. Der Einweiser erhält per
 * Mail einen Link „Hat <User> die Einführung absolviert?"; mit Klick auf „Ja" wird dem Nutzer das
 * passende Zertifikat vergeben (zhl_cert_grant + Projektion auf zhl_certificate). Token = Zugriffsschutz,
 * daher KEIN Login nötig. Eigenständige ZHL-Datei (PDO aus config.php), wie die zhl-handover-*-Seiten.
 */
declare(strict_types=1);

function db(): PDO
{
    $conf = require dirname(__DIR__) . '/config/config.php';
    $d = $conf['settings']['database'] ?? [];
    return new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $d['hostspec'] ?? '127.0.0.1', $d['name'] ?? ''),
        $d['user'] ?? '',
        $d['password'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}
function rebuildProjection(PDO $pdo): void
{
    $pdo->exec('DELETE FROM zhl_certificate');
    $pdo->exec('INSERT INTO zhl_certificate (user_id,resource_id,granted_at,expires_at)
        SELECT g.user_id, ctr.resource_id, MIN(g.granted_at),
          CASE WHEN SUM(g.expires_at IS NULL)>0 THEN NULL ELSE MAX(g.expires_at) END
        FROM zhl_cert_grant g JOIN zhl_cert_type_resource ctr ON ctr.cert_type_id=g.cert_type_id
        JOIN zhl_cert_type t ON t.id=g.cert_type_id AND t.active=1
        GROUP BY g.user_id, ctr.resource_id');
}
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$token = (string)($_REQUEST['t'] ?? '');
$pdo = db();
$row = null;
if (preg_match('/^[A-Za-z0-9]{16,64}$/', $token)) {
    $st = $pdo->prepare('SELECT c.*, t.name AS cert_name, u.fname, u.lname, u.email
        FROM zhl_cert_confirmation c JOIN zhl_cert_type t ON t.id=c.cert_type_id
        JOIN users u ON u.user_id=c.user_id WHERE c.token=? LIMIT 1');
    $st->execute([$token]);
    $row = $st->fetch() ?: null;
}

$result = null;
if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $decision = (string)($_POST['decision'] ?? '');
    if ($row['status'] === 'pending' && $decision === 'ja') {
        $now = gmdate('Y-m-d H:i:s');
        $g = $pdo->prepare('INSERT INTO zhl_cert_grant (user_id,cert_type_id,granted_at,expires_at,granted_by)
            VALUES (?,?,?,NULL,NULL) ON DUPLICATE KEY UPDATE granted_at=VALUES(granted_at)');
        $g->execute([$row['user_id'], $row['cert_type_id'], $now]);
        $pdo->prepare('UPDATE zhl_cert_confirmation SET status=?, confirmed_at=? WHERE id=?')->execute(['confirmed', $now, $row['id']]);
        rebuildProjection($pdo);
        $result = 'confirmed';
    } elseif ($row['status'] === 'pending' && $decision === 'nein') {
        $pdo->prepare('UPDATE zhl_cert_confirmation SET status=? WHERE id=?')->execute(['rejected', $row['id']]);
        $result = 'rejected';
    }
    $row['status'] = $result === 'confirmed' ? 'confirmed' : ($result === 'rejected' ? 'rejected' : $row['status']);
}
$name = $row ? trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')) : '';
?><!DOCTYPE html>
<html lang="de"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Einführungs-Bestätigung — ZHL Medienausleihe</title>
<link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
<link rel="stylesheet" href="css/zhl-theme.css">
</head><body class="bg-light">
<div class="container" style="max-width:560px;margin-top:60px;">
  <div class="card shadow-sm"><div class="card-body p-4">
    <div class="text-uppercase text-muted small fw-semibold mb-1">Medienausleihe ZHL</div>
    <h1 class="h4 mb-3">Einführungs-Bestätigung</h1>
    <?php if (!$row): ?>
      <div class="alert alert-warning mb-0">Dieser Bestätigungs-Link ist ungültig oder abgelaufen.</div>
    <?php elseif ($result === 'confirmed' || ($row['status'] === 'confirmed' && $result === null)): ?>
      <div class="alert alert-success mb-0">✅ Danke! <strong><?= h($name) ?></strong> hat das Zertifikat <strong>„<?= h($row['cert_name']) ?>"</strong> erhalten und kann das Material jetzt selbstständig buchen.</div>
    <?php elseif ($result === 'rejected' || $row['status'] === 'rejected'): ?>
      <div class="alert alert-secondary mb-0">Notiert — es wurde <strong>kein</strong> Zertifikat vergeben.</div>
    <?php else: ?>
      <p>Hat <strong><?= h($name) ?></strong> <span class="text-muted">(<?= h($row['email']) ?>)</span> die Einführung <strong>„<?= h($row['cert_name']) ?>"</strong> erfolgreich absolviert?</p>
      <form method="post" class="d-flex gap-2 mt-3">
        <input type="hidden" name="t" value="<?= h($token) ?>">
        <button name="decision" value="ja" class="btn btn-success">✅ Ja, bestätigen</button>
        <button name="decision" value="nein" class="btn btn-outline-secondary">Nein</button>
      </form>
      <p class="text-muted small mt-3 mb-0">Bitte erst <em>nach</em> dem durchgeführten Termin bestätigen.</p>
    <?php endif; ?>
  </div></div>
</div>
</body></html>

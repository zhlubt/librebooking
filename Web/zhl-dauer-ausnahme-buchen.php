<?php
/**
 * ZHL — Sonderfreigabe einlösen (SPEC-AUSLEIHDAUER-LIMIT §6.3). LOGIN-FREI, token-basiert.
 *
 * Der Nutzer öffnet den Link aus der Genehmigungs-Mail (?token=…). Diese Seite prüft den Token nur und
 * zeigt das genehmigte Fenster + einen Button in die Buchung (zhl-book.php mit ausnahme_token). Die
 * eigentliche Buchung bleibt SecurePage (Login nötig) + CSRF; das Limit wird dort beim passenden Fenster
 * für genau diese Buchung aufgehoben. Verfügbarkeit/Konflikt werden weiterhin geprüft.
 *
 * Token = Zugriffsschutz (kein Login auf DIESER Seite). Eigenständige ZHL-Datei; bootstrappt LB lazy.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'lib/Server/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlDauerAusnahme.php');

ExceptionHandler::SetExceptionHandler(new WebExceptionHandler(function () {
    http_response_code(500);
    echo 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen oder das ZHL-Team kontaktieren.';
}));

function zhl_da_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function zhl_da_fmt(string $utc, string $tz, string $fmt): string
{
    try {
        return Date::Parse($utc, 'UTC')->ToTimezone($tz)->Format($fmt);
    } catch (Throwable $e) {
        return '—';
    }
}

$db = ServiceLocator::GetDatabase();
$token = (string)($_REQUEST['token'] ?? '');
$req = null;
if (preg_match('/^[a-f0-9]{20,64}$/', $token)) {
    $req = ZhlDauerAusnahme::GetByToken($db, $token);
}
if ($req === null) {
    http_response_code(404);
}

$tz = ($req !== null && (string)($req['timezone'] ?? '') !== '') ? (string)$req['timezone'] : 'Europe/Berlin';
$status = $req !== null ? (string)($req['status'] ?? '') : '';
$resName = $req !== null ? (string)($req['resource_name'] ?? 'Gerät') : '';
$expired = false;
if ($req !== null && $status === 'approved') {
    $until = (string)($req['approved_until_utc'] ?? '');
    if ($until !== '' && strcmp($until, gmdate('Y-m-d H:i:s')) < 0) {
        $expired = true;
    }
}
// Deep-Link in die Buchung: rd öffnet den Kalender nahe dem Starttag; ausnahme_token schaltet das Limit frei.
$bookLink = '';
if ($req !== null && $status === 'approved' && !$expired) {
    $startDay = zhl_da_fmt((string)$req['requested_begin_utc'], $tz, 'Y-m-d');
    $bookLink = 'zhl-book.php?rid=' . (int)$req['resource_id'] . '&rd=' . urlencode($startDay) . '&ausnahme_token=' . urlencode($token);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sonderfreigabe einlösen — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>body{background:#f6f8f7;} .wrap{max-width:600px;}</style>
</head>
<body>
<div class="container wrap py-4">
  <h1 class="h4 mb-3"><i class="bi bi-hourglass-split text-success"></i> Längere Ausleihe</h1>

  <?php if ($req === null): ?>
    <div class="alert alert-danger"><i class="bi bi-x-circle"></i> Dieser Link ist ungültig oder abgelaufen.</div>

  <?php elseif ($status === 'approved' && !$expired): ?>
    <p class="text-muted">Gerät: <strong><?= zhl_da_h($resName) ?></strong></p>
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6"><i class="bi bi-check-circle text-success"></i> Genehmigt</h2>
        <p class="mb-1">Genehmigtes Fenster:
          <strong><?= zhl_da_h(zhl_da_fmt((string)$req['requested_begin_utc'], $tz, 'd.m.Y H:i')) ?>
          – <?= zhl_da_h(zhl_da_fmt((string)$req['requested_end_utc'], $tz, 'd.m.Y H:i')) ?> Uhr</strong>
          (<?= (int)$req['nutzung_tage'] ?> Tage)</p>
        <?php if (($req['admin_note'] ?? '') !== ''): ?>
          <p class="text-muted small">Hinweis vom Team: <?= zhl_da_h((string)$req['admin_note']) ?></p>
        <?php endif; ?>
        <hr>
        <p class="mb-2 small text-muted">Bitte buche dein zugesagtes Zeitfenster. Das Dauer-Limit ist für diese
          Buchung aufgehoben; Verfügbarkeit und Konflikte werden beim Buchen geprüft. Ggf. ist eine Anmeldung nötig.</p>
        <a class="btn btn-success" href="<?= zhl_da_h($bookLink) ?>"><i class="bi bi-box-arrow-in-right"></i> Jetzt buchen</a>
      </div>
    </div>

  <?php elseif ($status === 'approved' && $expired): ?>
    <div class="alert alert-secondary"><i class="bi bi-clock-history"></i> Diese Freigabe ist abgelaufen. Bitte beim ZHL-Medien-Team eine neue Freigabe anfragen.</div>
  <?php elseif ($status === 'used'): ?>
    <div class="alert alert-secondary"><i class="bi bi-check2-all"></i> Diese Freigabe wurde bereits zum Buchen verwendet.</div>
  <?php elseif ($status === 'declined'): ?>
    <div class="alert alert-secondary"><i class="bi bi-x-circle"></i> Diese Anfrage wurde leider abgelehnt.</div>
  <?php else: ?>
    <div class="alert alert-light border"><i class="bi bi-hourglass-split"></i> Diese Anfrage wird noch bearbeitet.</div>
  <?php endif; ?>
</div>
</body>
</html>

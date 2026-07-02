<?php
/**
 * ZHL — Einführungstermin stornieren (SPEC-EINFUEHRUNG-AUSHANDLUNG §4.4). LOGIN-FREI, token-basiert.
 *
 * Nutzer ODER Einweiser klickt den Storno-Link aus der Bestätigungs-Mail. Mit Bestätigung wird der
 * Termin FÜR ALLE Beteiligten abgesagt:
 *   - Request → cancelled, gewähltes Angebot → withdrawn (ics_sequence=1),
 *   - native Einführungs-Reservierung (falls Slot-Gerät) wird abgesagt,
 *   - CANCEL-ICS (gleiche UID, SEQUENCE 1) an Nutzer + Einweiser → Termin verschwindet in beiden Kalendern.
 *
 * Ist die Anfrage noch nicht bestätigt (offered/open), wird sie schlicht zurückgezogen (kein ICS).
 * Token = Zugriffsschutz; `who` (user|admin) wäre nur Anzeige/Audit und wird NICHT als Berechtigung genutzt.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'lib/Server/namespace.php');
require_once(ROOT_DIR . 'lib/Email/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlTerminRequest.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlEinfuehrungReservation.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlIcs.php');
require_once(ROOT_DIR . 'Presenters/ZhlTerminRequestEmail.php');

ExceptionHandler::SetExceptionHandler(new WebExceptionHandler(function () {
    http_response_code(500);
    echo 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen oder das ZHL-Team kontaktieren.';
}));

function zhl_ts_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function zhl_ts_fmt(string $utc, string $tz, string $fmt): string
{
    try {
        return Date::Parse($utc, 'UTC')->ToTimezone($tz)->Format($fmt);
    } catch (Throwable $e) {
        return '—';
    }
}

/** CANCEL-ICS an Nutzer + Einweiser. Best effort. */
function zhl_ts_send_cancel($db, array $req, array $offer, string $tz): void
{
    try {
        $label = (string)($req['label'] ?? 'Gerät');
        $instructorName = (string)($offer['instructor_name'] ?? '');
        $instructorEmail = trim((string)($offer['instructor_email'] ?? ''));
        $userName = trim(((string)($req['fname'] ?? '')) . ' ' . ((string)($req['lname'] ?? '')));
        $userEmail = trim((string)($req['email'] ?? ''));
        $isReturn = (($req['purpose'] ?? 'einf') === 'return');
        $terminWort = $isReturn ? 'Rückgabetermin' : 'Einführungstermin';
        $wannWort = $isReturn ? 'Rückgabe' : 'Einführung';
        $icsFile = $isReturn ? 'rueckgabe.ics' : 'einfuehrung.ics';
        $when = zhl_ts_fmt((string)$offer['start_utc'], $tz, 'd.m.Y H:i') . '–' . zhl_ts_fmt((string)$offer['end_utc'], $tz, 'H:i') . ' Uhr';

        $ics = ZhlIcs::Build([
            'uid' => (string)$offer['ics_uid'],
            'sequence' => 1,
            'method' => 'CANCEL',
            'start_utc' => (string)$offer['start_utc'],
            'end_utc' => (string)$offer['end_utc'],
            'summary' => 'ZHL ' . $wannWort . ': ' . $label,
            'description' => 'Dieser ' . $terminWort . ' wurde abgesagt.',
            'location' => ($offer['note'] ?? '') !== '' ? (string)$offer['note'] : 'ZHL Medien, Universität Bayreuth',
            'organizer_name' => $instructorName,
            'organizer_email' => $instructorEmail,
            'attendees' => array_values(array_filter([
                $userEmail !== '' ? [$userName !== '' ? $userName : $userEmail, $userEmail] : null,
                $instructorEmail !== '' ? [$instructorName !== '' ? $instructorName : $instructorEmail, $instructorEmail] : null,
            ])),
        ]);

        $lines = [
            'Der ' . $terminWort . ' für „' . $label . '" wurde abgesagt.',
            'Termin (abgesagt): ' . $when,
            $wannWort . ': ' . $instructorName,
            '',
            'Die Absage für Ihren Kalender ist als Datei (' . $icsFile . ') angehängt.',
            'Bei Bedarf stellen Sie bitte eine neue Terminanfrage in der Medienausleihe.',
            '', 'Viele Grüße', 'ZHL Medienausleihe',
        ];
        $to = [];
        if ($userEmail !== '') {
            $to[] = new EmailAddress($userEmail, $userName !== '' ? $userName : $userEmail);
        }
        if ($instructorEmail !== '') {
            $to[] = new EmailAddress($instructorEmail, $instructorName !== '' ? $instructorName : $instructorEmail);
        }
        if (empty($to)) {
            return;
        }
        $lang = !empty($req['language']) ? (string)$req['language'] : null;
        $mail = new ZhlTerminRequestEmail($to, [], 'ZHL Medienausleihe — ' . $terminWort . ' abgesagt: ' . $label, implode("\n", $lines), $lang);
        $mail->AddStringAttachment($ics, $icsFile);
        ServiceLocator::GetEmailService()->Send($mail);
    } catch (Throwable $e) {
        Log::Error('ZHL-TerminStorno: CANCEL-Mail fehlgeschlagen: %s', $e);
    }
}

$db = ServiceLocator::GetDatabase();
$token = (string)($_REQUEST['token'] ?? '');
$req = preg_match('/^[a-f0-9]{20,64}$/', $token) ? ZhlTerminRequest::GetByToken($db, $token) : null;
if ($req === null) {
    http_response_code(404);
}

if ($req !== null && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'cancel') {
    $rid = (int)$req['id'];
    $tz = ((string)($req['timezone'] ?? '')) !== '' ? (string)$req['timezone'] : 'Europe/Berlin';
    $statusBefore = (string)$req['status'];

    if ($statusBefore === 'confirmed') {
        $offerId = (int)($req['chosen_offer_id'] ?? 0);
        $offer = $offerId > 0 ? ZhlTerminRequest::GetOffer($db, $offerId) : null;
        // 1) Status umlegen (idempotent über WHERE status='confirmed').
        ZhlTerminRequest::CancelConfirmed($db, $rid);
        // 2) Native Einführungs-Reservierung absagen (falls vorhanden).
        $resRef = trim((string)($req['einf_reservation_ref'] ?? ''));
        if ($resRef !== '' && $offer !== null) {
            ZhlEinfuehrungReservation::Cancel($db, $resRef, (int)$offer['instructor_uid']);
        }
        // 3) CANCEL-ICS an alle.
        if ($offer !== null) {
            zhl_ts_send_cancel($db, $req, $offer, $tz);
        }
    } elseif (in_array($statusBefore, ['offered', 'open'], true)) {
        ZhlTerminRequest::WithdrawRequest($db, $rid);
    }
    header('Location: zhl-termin-storno.php?token=' . urlencode($token) . '&done=1');
    exit;
}

$tz = ($req !== null && (string)($req['timezone'] ?? '') !== '') ? (string)$req['timezone'] : 'Europe/Berlin';
$status = $req !== null ? (string)$req['status'] : '';
$label = $req !== null ? (string)($req['label'] ?? 'Gerät') : '';
$pgIsReturn = ($req !== null && (($req['purpose'] ?? 'einf') === 'return'));
$pgTerminWort = $pgIsReturn ? 'Rückgabetermin' : 'Einführungstermin';
$done = isset($_GET['done']);
$chosen = null;
if ($req !== null && (int)($req['chosen_offer_id'] ?? 0) > 0) {
    $chosen = ZhlTerminRequest::GetOffer($db, (int)$req['chosen_offer_id']);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= zhl_ts_h($pgTerminWort) ?> absagen — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>body { background:#f6f8f7; } .wrap { max-width:560px; }</style>
</head>
<body>
<div class="container wrap py-4">
  <h1 class="h4 mb-3"><i class="bi bi-calendar-x text-danger"></i> <?= zhl_ts_h($pgTerminWort) ?> absagen</h1>

  <?php if ($req === null): ?>
    <div class="alert alert-danger"><i class="bi bi-x-circle"></i> Dieser Link ist ungültig oder abgelaufen.</div>
  <?php elseif ($done || $status === 'cancelled'): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle"></i> Der Termin wurde abgesagt. Alle Beteiligten wurden benachrichtigt.</div>
  <?php elseif ($status === 'confirmed' && $chosen !== null): ?>
    <p>Möchten Sie diesen <?= zhl_ts_h($pgTerminWort) ?> für <strong>„<?= zhl_ts_h($label) ?>"</strong> wirklich absagen?</p>
    <p class="text-muted"><?= zhl_ts_h(zhl_ts_fmt((string)$chosen['start_utc'], $tz, 'd.m.Y H:i')) ?>–<?= zhl_ts_h(zhl_ts_fmt((string)$chosen['end_utc'], $tz, 'H:i')) ?> Uhr · <?= $pgIsReturn ? 'Rückgabe bei' : 'Einführung' ?>: <?= zhl_ts_h((string)($chosen['instructor_name'] ?? '')) ?></p>
    <p class="small text-muted">Die Absage gilt für alle Beteiligten (Sie und die <?= $pgIsReturn ? 'entgegennehmende' : 'einführende' ?> Person).</p>
    <form method="post">
      <input type="hidden" name="token" value="<?= zhl_ts_h($token) ?>">
      <input type="hidden" name="action" value="cancel">
      <button class="btn btn-danger"><i class="bi bi-calendar-x"></i> Termin jetzt absagen</button>
      <a class="btn btn-outline-secondary" href="zhl-termin-auswahl.php?token=<?= zhl_ts_h($token) ?>">Zurück</a>
    </form>
  <?php elseif (in_array($status, ['offered', 'open'], true)): ?>
    <p>Diese Anfrage ist noch nicht bestätigt. Möchten Sie sie zurückziehen?</p>
    <form method="post">
      <input type="hidden" name="token" value="<?= zhl_ts_h($token) ?>">
      <input type="hidden" name="action" value="cancel">
      <button class="btn btn-danger">Anfrage zurückziehen</button>
    </form>
  <?php else: ?>
    <div class="alert alert-secondary">Für diese Anfrage ist keine Absage (mehr) möglich.</div>
  <?php endif; ?>
</div>
</body>
</html>

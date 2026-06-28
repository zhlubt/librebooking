<?php
/**
 * ZHL — Einführungstermin auswählen (SPEC-EINFUEHRUNG-AUSHANDLUNG §4.3). LOGIN-FREI, token-basiert.
 *
 * Der Nutzer öffnet den Link aus der Angebots-Mail (?token=…), sieht die angebotenen Einführungs-
 * termine und wählt einen aus. Die Auswahl ist die Bestätigung:
 *   - atomarer Gewinner-Claim (genau ein Angebot wird 'chosen'),
 *   - bei stundenweisen Geräten native Reservierung der Einführungs-Stunde (§6); Konflikt → rückgängig,
 *   - übrige Angebote zurückgezogen, ICS-Einladung (REQUEST) an Nutzer + Einweiser,
 *   - Storno-Link an beide; CTA: die Geräte-Leihe separat ab Einführungs-Ende buchen.
 *
 * Token = Zugriffsschutz (kein Login). Eigenständige ZHL-Datei; bootstrappt das LB-Framework lazy.
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

function zhl_ta_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function zhl_ta_fmt(string $utc, string $tz, string $fmt): string
{
    try {
        return Date::Parse($utc, 'UTC')->ToTimezone($tz)->Format($fmt);
    } catch (Throwable $e) {
        return '—';
    }
}

function zhl_ta_base(): string
{
    $url = rtrim((string)Configuration::Instance()->GetScriptUrl(), '/');
    return $url !== '' ? $url . '/' : '/Web/';
}

$db = ServiceLocator::GetDatabase();

$token = (string)($_REQUEST['token'] ?? '');
if (!preg_match('/^[a-f0-9]{20,64}$/', $token)) {
    http_response_code(404);
    $req = null;
} else {
    $req = ZhlTerminRequest::GetByToken($db, $token);
}

$error = null;
$flash = null;

if ($req !== null && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $rid = (int)$req['id'];
    $tz = ((string)($req['timezone'] ?? '')) !== '' ? (string)$req['timezone'] : 'Europe/Berlin';

    if ($action === 'withdraw') {
        // Nutzer zieht eine noch nicht bestätigte Anfrage zurück.
        if (in_array((string)$req['status'], ['open', 'offered'], true)) {
            ZhlTerminRequest::WithdrawRequest($db, $rid);
        }
        header('Location: zhl-termin-auswahl.php?token=' . urlencode($token));
        exit;
    }

    if ($action === 'confirm') {
        if ((string)$req['status'] !== 'offered') {
            $error = 'Diese Anfrage kann nicht (mehr) bestätigt werden.';
        } else {
            $offerId = (int)($_POST['offer_id'] ?? 0);
            $offer = $offerId > 0 ? ZhlTerminRequest::GetOffer($db, $offerId) : null;
            if ($offer === null || (int)$offer['request_id'] !== $rid || (string)$offer['status'] !== 'open') {
                $error = 'Dieser Termin ist nicht mehr verfügbar. Bitte einen anderen wählen.';
            } else {
                // Gewinner-Claim: zufällige UID dient zugleich als Nonce.
                $icsUid = 'zhl-einf-' . bin2hex(random_bytes(16)) . '@media.zhl-ubt.de';
                $won = ZhlTerminRequest::ClaimOffer($db, $rid, $offerId, $icsUid);
                if (!$won) {
                    $error = 'Dieser Termin wurde gerade vergeben oder die Anfrage ist bereits bestätigt.';
                } else {
                    ZhlTerminRequest::SetConfirmed($db, $rid, $offerId);
                    // §6: Stunde am Gerät reservieren (nur Slot-Geräte). Konflikt → rückgängig.
                    $rsv = ZhlEinfuehrungReservation::Reserve($db, $req, $offer);
                    if (!$rsv['skipped'] && $rsv['error'] !== null) {
                        ZhlTerminRequest::UndoConfirm($db, $rid, $offerId);
                        $error = 'Dieser Termin lässt sich nicht mehr sichern (' . $rsv['error'] . '). Bitte einen anderen Termin wählen oder das ZHL-Team kontaktieren.';
                    } else {
                        ZhlTerminRequest::WithdrawOtherOpenOffers($db, $rid, $offerId);
                        if (($rsv['ref'] ?? null) !== null && $rsv['ref'] !== '') {
                            ZhlTerminRequest::SetReservationRef($db, $rid, (string)$rsv['ref']);
                        }
                        zhl_ta_send_invite($db, $req, $offer, $token, $tz);
                        header('Location: zhl-termin-auswahl.php?token=' . urlencode($token) . '&done=1');
                        exit;
                    }
                }
            }
        }
    }
}

// Frischen Stand laden (nach evtl. POST mit Fehler).
if ($req !== null && $error !== null) {
    $fresh = ZhlTerminRequest::GetByToken($db, $token);
    if ($fresh !== null) {
        $req = $fresh;
    }
}
if (isset($_GET['done'])) {
    $flash = 'Vielen Dank! Ihr Einführungstermin ist bestätigt.';
}

/**
 * Verschickt die ICS-Einladung (REQUEST) + Bestätigungs-/Storno-Mail an Nutzer UND Einweiser.
 * Best effort: ein Mailfehler kippt die bereits gespeicherte Bestätigung NICHT.
 */
function zhl_ta_send_invite($db, array $req, array $offer, string $token, string $tz): void
{
    try {
        $label = (string)($req['label'] ?? 'Gerät');
        $startLocal = zhl_ta_fmt((string)$offer['start_utc'], $tz, 'd.m.Y H:i');
        $endLocal = zhl_ta_fmt((string)$offer['end_utc'], $tz, 'H:i');
        $instructorName = (string)($offer['instructor_name'] ?? '');
        $instructorEmail = trim((string)($offer['instructor_email'] ?? ''));
        $userName = trim(((string)($req['fname'] ?? '')) . ' ' . ((string)($req['lname'] ?? '')));
        $userEmail = trim((string)($req['email'] ?? ''));
        $stornoLink = zhl_ta_base() . 'zhl-termin-storno.php?token=' . urlencode($token);
        // CTA: Leihe ab Einführungs-Ende (lokales Datum/Uhrzeit) selbst buchen.
        $bookStart = zhl_ta_fmt((string)$offer['end_utc'], $tz, 'Y-m-d');
        $bookTime = zhl_ta_fmt((string)$offer['end_utc'], $tz, 'H:i');
        $bookLink = zhl_ta_base() . 'zhl-book.php?rid=' . (int)$req['resource_id'] . '&start=' . urlencode($bookStart) . '&time=' . urlencode($bookTime);

        $ics = ZhlIcs::Build([
            'uid' => (string)$offer['ics_uid'],
            'sequence' => 0,
            'method' => 'REQUEST',
            'start_utc' => (string)$offer['start_utc'],
            'end_utc' => (string)$offer['end_utc'],
            'summary' => 'ZHL Einführung: ' . $label,
            'description' => 'Einführung für die Medienausleihe.' . (($offer['note'] ?? '') !== '' ? ' Hinweis: ' . (string)$offer['note'] : ''),
            'location' => ($offer['note'] ?? '') !== '' ? (string)$offer['note'] : 'ZHL Medien, Universität Bayreuth',
            'organizer_name' => $instructorName,
            'organizer_email' => $instructorEmail,
            'attendees' => array_values(array_filter([
                $userEmail !== '' ? [$userName !== '' ? $userName : $userEmail, $userEmail] : null,
                $instructorEmail !== '' ? [$instructorName !== '' ? $instructorName : $instructorEmail, $instructorEmail] : null,
            ])),
        ]);

        $lines = [
            ($userName !== '' ? 'Hallo ' . $userName . ',' : 'Hallo,'), '',
            'Ihr Einführungstermin für „' . $label . '" ist bestätigt:',
            '  ' . $startLocal . '–' . $endLocal . ' Uhr',
            '  Einführung: ' . $instructorName,
            (($offer['note'] ?? '') !== '' ? '  Hinweis: ' . (string)$offer['note'] : ''),
            '',
            'Die Termineinladung für Ihren Kalender ist als Datei (einfuehrung.ics) angehängt.',
            '',
            'WICHTIG — Gerät separat buchen:',
            'Bitte buchen Sie Ihre Ressource selbst ab ' . zhl_ta_fmt((string)$offer['end_utc'], $tz, 'd.m.Y H:i') . ' Uhr (wenn die Einführung zu Ende ist):',
            '  ' . $bookLink,
            '',
            'Termin doch absagen? Über diesen Link wird der Termin für alle Beteiligten storniert:',
            '  ' . $stornoLink,
            '', 'Viele Grüße', 'ZHL Medienausleihe',
        ];
        $body = implode("\n", array_filter($lines, fn($l) => $l !== null));

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
        $mail = new ZhlTerminRequestEmail($to, [], 'ZHL Medienausleihe — Einführungstermin bestätigt: ' . $label, $body, $lang);
        $mail->AddStringAttachment($ics, 'einfuehrung.ics');
        ServiceLocator::GetEmailService()->Send($mail);
    } catch (Throwable $e) {
        Log::Error('ZHL-TerminAuswahl: Einladung/Mail fehlgeschlagen: %s', $e);
    }
}

// ---------------------------------------------------------------------------------------------------
$tz = ($req !== null && (string)($req['timezone'] ?? '') !== '') ? (string)$req['timezone'] : 'Europe/Berlin';
$status = $req !== null ? (string)$req['status'] : '';
$label = $req !== null ? (string)($req['label'] ?? 'Gerät') : '';
$offers = ($req !== null) ? ZhlTerminRequest::ListOffers($db, (int)$req['id'], 'open') : [];
$chosen = null;
if ($req !== null && $status === 'confirmed' && (int)($req['chosen_offer_id'] ?? 0) > 0) {
    $chosen = ZhlTerminRequest::GetOffer($db, (int)$req['chosen_offer_id']);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einführungstermin wählen — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .wrap { max-width:640px; }
        .opt { border:1px solid #e3eae6; border-radius:10px; padding:12px 14px; margin-bottom:10px; cursor:pointer; display:block; }
        .opt:hover { border-color:#009260; }
        .opt input { margin-right:8px; }
    </style>
</head>
<body>
<div class="container wrap py-4">
  <h1 class="h4 mb-3"><i class="bi bi-mortarboard text-success"></i> Einführungstermin</h1>

  <?php if ($req === null): ?>
    <div class="alert alert-danger"><i class="bi bi-x-circle"></i> Dieser Link ist ungültig oder abgelaufen.</div>

  <?php else: ?>
    <?php if ($flash !== null): ?>
      <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= zhl_ta_h($flash) ?></div>
    <?php endif; ?>
    <?php if ($error !== null): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= zhl_ta_h($error) ?></div>
    <?php endif; ?>

    <p class="text-muted">Gerät: <strong><?= zhl_ta_h($label) ?></strong></p>

    <?php if ($status === 'confirmed' && $chosen !== null): ?>
      <div class="card shadow-sm">
        <div class="card-body">
          <h2 class="h6"><i class="bi bi-calendar-check text-success"></i> Ihr Termin steht</h2>
          <p class="mb-1"><strong><?= zhl_ta_h(zhl_ta_fmt((string)$chosen['start_utc'], $tz, 'd.m.Y H:i')) ?>–<?= zhl_ta_h(zhl_ta_fmt((string)$chosen['end_utc'], $tz, 'H:i')) ?> Uhr</strong></p>
          <p class="mb-1">Einführung: <?= zhl_ta_h((string)($chosen['instructor_name'] ?? '')) ?></p>
          <?php if (($chosen['note'] ?? '') !== ''): ?><p class="text-muted small">Hinweis: <?= zhl_ta_h((string)$chosen['note']) ?></p><?php endif; ?>
          <hr>
          <p class="mb-2"><strong>Nächster Schritt — Gerät buchen:</strong><br>
            Bitte buchen Sie Ihre Ressource selbst ab <strong><?= zhl_ta_h(zhl_ta_fmt((string)$chosen['end_utc'], $tz, 'd.m.Y H:i')) ?> Uhr</strong> (wenn die Einführung zu Ende ist).</p>
          <a class="btn btn-success btn-sm" href="zhl-book.php?rid=<?= (int)$req['resource_id'] ?>&start=<?= zhl_ta_h(zhl_ta_fmt((string)$chosen['end_utc'], $tz, 'Y-m-d')) ?>&time=<?= zhl_ta_h(zhl_ta_fmt((string)$chosen['end_utc'], $tz, 'H:i')) ?>"><i class="bi bi-box-arrow-in-right"></i> Gerät jetzt buchen</a>
          <hr>
          <form method="post" onsubmit="return confirm('Termin wirklich für alle Beteiligten absagen?');">
            <input type="hidden" name="token" value="<?= zhl_ta_h($token) ?>">
            <input type="hidden" name="action" value="withdraw">
            <span class="text-muted small">Termin doch nicht? </span>
            <a class="text-danger small" href="zhl-termin-storno.php?token=<?= zhl_ta_h($token) ?>">Termin absagen</a>
          </form>
        </div>
      </div>

    <?php elseif ($status === 'cancelled'): ?>
      <div class="alert alert-secondary"><i class="bi bi-x-circle"></i> Diese Anfrage wurde storniert.</div>
    <?php elseif ($status === 'declined'): ?>
      <div class="alert alert-secondary"><i class="bi bi-x-circle"></i> Diese Anfrage wurde leider abgelehnt. Bei Fragen wenden Sie sich an das ZHL-Medien-Team.</div>
    <?php elseif ($status === 'offered' && !empty($offers)): ?>
      <p>Bitte wählen Sie einen Einführungstermin:</p>
      <form method="post">
        <input type="hidden" name="token" value="<?= zhl_ta_h($token) ?>">
        <input type="hidden" name="action" value="confirm">
        <?php foreach ($offers as $i => $o): ?>
          <label class="opt">
            <input type="radio" name="offer_id" value="<?= (int)$o['id'] ?>" <?= $i === 0 ? 'checked' : '' ?> required>
            <strong><?= zhl_ta_h(zhl_ta_fmt((string)$o['start_utc'], $tz, 'd.m.Y H:i')) ?>–<?= zhl_ta_h(zhl_ta_fmt((string)$o['end_utc'], $tz, 'H:i')) ?> Uhr</strong>
            — Einführung: <?= zhl_ta_h((string)($o['instructor_name'] ?? '')) ?>
            <?php if (($o['note'] ?? '') !== ''): ?><br><span class="text-muted small" style="margin-left:24px;"><?= zhl_ta_h((string)$o['note']) ?></span><?php endif; ?>
          </label>
        <?php endforeach; ?>
        <button class="btn btn-success mt-2"><i class="bi bi-check2-circle"></i> Termin bestätigen</button>
      </form>
      <form method="post" class="mt-3" onsubmit="return confirm('Anfrage zurückziehen?');">
        <input type="hidden" name="token" value="<?= zhl_ta_h($token) ?>">
        <input type="hidden" name="action" value="withdraw">
        <button class="btn btn-link btn-sm text-muted p-0">Keiner passt? Anfrage zurückziehen</button>
      </form>
    <?php else: ?>
      <div class="alert alert-light border"><i class="bi bi-hourglass-split"></i> Es liegen derzeit keine Terminvorschläge vor. Das ZHL-Medien-Team meldet sich.</div>
    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>

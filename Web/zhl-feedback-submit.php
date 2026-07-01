<?php
/**
 * ZHL Medienausleihe — Problem-Melder (Übergangszeit "Seite in Entwicklung").
 *
 * Nimmt die Meldung aus dem Badge/Modal (Web/scripts/zhl-feedback.js) entgegen und schickt eine
 * Mail ans Medien-Team. Mitgeschickt: aktuelle Seite (URL + Titel, vom Client) sowie — hier
 * serverseitig aus der LB-Session ermittelt — der eingeloggte Nutzer (Name/E-Mail/ID). Optional
 * gibt der Melder eine Kontakt-E-Mail an (z. B. wenn nicht eingeloggt).
 *
 * Bewusst OHNE Login-Zwang (das Badge erscheint auch auf öffentlichen Seiten/Login). Missbrauch
 * wird pragmatisch eingedämmt: Same-Origin-Prüfung, Honeypot, Längen-Limits, Session-Rate-Limit.
 *
 * Eigenständige ZHL-Datei (Vorbild zhl-return-drop.php), bootstrappt das LB-Framework lazy.
 * Kein LibreBooking-Core berührt.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Server/namespace.php');
require_once(ROOT_DIR . 'lib/Email/namespace.php');
require_once(ROOT_DIR . 'Presenters/ZhlTerminRequestEmail.php');

// === Empfänger der Meldungen (bei Bedarf hier anpassen) ===
const ZHL_FEEDBACK_RECIPIENT = 'paul.doelle@uni-bayreuth.de';
const ZHL_FEEDBACK_RECIPIENT_NAME = 'Paul Doelle';
const ZHL_FEEDBACK_MIN_LEN = 10;
const ZHL_FEEDBACK_MAX_LEN = 5000;
const ZHL_FEEDBACK_MIN_INTERVAL = 15;  // Sekunden zwischen zwei Meldungen je Session
const ZHL_FEEDBACK_MAX_PER_SESSION = 30;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/** Einheitliche JSON-Antwort und Ende. */
function zhl_fb_json(bool $ok, int $code = 200, string $error = ''): void
{
    http_response_code($code);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    zhl_fb_json(false, 405, 'method');
}

// Same-Origin: wenn Origin/Referer mitkommt, muss der Host passen (verhindert Fremdseiten-POSTs).
$host = (string)($_SERVER['HTTP_HOST'] ?? '');
$originHeader = (string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');
if ($originHeader !== '') {
    $originHost = (string)(parse_url($originHeader, PHP_URL_HOST) ?? '');
    if ($host !== '' && $originHost !== '' && strcasecmp($originHost, $host) !== 0) {
        zhl_fb_json(false, 403, 'origin');
    }
}

// Honeypot: ausgefüllt = Bot. Neutral "ok" zurückgeben, aber nichts senden.
if (trim((string)($_POST['hp'] ?? '')) !== '') {
    zhl_fb_json(true);
}

$message = trim((string)($_POST['message'] ?? ''));
$href = trim((string)($_POST['href'] ?? ''));
$pageTitle = trim((string)($_POST['page_title'] ?? ''));
$contactEmail = trim((string)($_POST['contact_email'] ?? ''));
$lang = trim((string)($_POST['lang'] ?? 'de'));

if (mb_strlen($message) < ZHL_FEEDBACK_MIN_LEN) {
    zhl_fb_json(false, 422, 'too_short');
}
if (mb_strlen($message) > ZHL_FEEDBACK_MAX_LEN) {
    $message = mb_substr($message, 0, ZHL_FEEDBACK_MAX_LEN) . ' […]';
}

// Eingeloggten Nutzer aus der LB-Session ziehen (falls vorhanden). GetUserSession() startet zugleich
// die PHP-Session über das Framework — daher VOR dem Rate-Limit, damit unsere $_SESSION-Marker
// nicht von der Framework-eigenen Session-Initialisierung wieder verworfen werden.
$userLine = 'nicht angemeldet';
try {
    $session = ServiceLocator::GetServer()->GetUserSession();
    if ($session !== null && $session->IsLoggedIn()) {
        $name = trim(((string)($session->FirstName ?? '')) . ' ' . ((string)($session->LastName ?? '')));
        $email = (string)($session->Email ?? '');
        $uid = (string)($session->UserId ?? '');
        $userLine = ($name !== '' ? $name : '(ohne Namen)')
            . ($email !== '' ? ' <' . $email . '>' : '')
            . ($uid !== '' ? ' (User-ID ' . $uid . ')' : '');
    }
} catch (Throwable $e) {
    // Session nicht verfügbar → "nicht angemeldet"
}

// Rate-Limit pro Session (best effort; Session ist nun durch das Framework gestartet).
$now = time();
$last = (int)($_SESSION['zhl_fb_last'] ?? 0);
$count = (int)($_SESSION['zhl_fb_count'] ?? 0);
if ($last > 0 && ($now - $last) < ZHL_FEEDBACK_MIN_INTERVAL) {
    zhl_fb_json(false, 429, 'rate');
}
if ($count >= ZHL_FEEDBACK_MAX_PER_SESSION) {
    zhl_fb_json(false, 429, 'rate');
}

// Klartext-Body zusammenbauen (ZhlTerminRequestEmail escaped + nl2br beim Rendern).
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$when = date('d.m.Y H:i');
$contactClean = filter_var($contactEmail, FILTER_VALIDATE_EMAIL) ? $contactEmail : ($contactEmail !== '' ? $contactEmail . ' (ungültig)' : '–');

$bodyLines = [
    'Eine Nutzer-Rückmeldung von der ZHL-Medienausleihe (Status „in Entwicklung"):',
    '',
    'Meldung:',
    $message,
    '',
    '────────────────────────',
    'Angemeldet als: ' . $userLine,
    'Kontakt für Rückfragen: ' . $contactClean,
    'Seite: ' . ($pageTitle !== '' ? $pageTitle . ' — ' : '') . ($href !== '' ? $href : '(unbekannt)'),
    'Zeitpunkt: ' . $when,
    'Browser: ' . ($ua !== '' ? $ua : '(unbekannt)'),
];
$body = implode("\n", $bodyLines);

$subjectName = 'Problemmeldung media.zhl-ubt.de';
$to = [new EmailAddress(ZHL_FEEDBACK_RECIPIENT, ZHL_FEEDBACK_RECIPIENT_NAME)];

try {
    // Empfänger ist das (deutschsprachige) Team → Mail-Rahmen immer 'de', unabhängig von der
    // UI-Sprache des Melders. Dessen Sprache steht ggf. im Body-Text selbst.
    ServiceLocator::GetEmailService()->Send(new ZhlTerminRequestEmail($to, [], $subjectName, $body, 'de'));
} catch (Throwable $e) {
    Log::Error('ZHL Feedback: Mailversand fehlgeschlagen: %s', $e->getMessage());
    zhl_fb_json(false, 500, 'send_failed');
}

$_SESSION['zhl_fb_last'] = $now;
$_SESSION['zhl_fb_count'] = $count + 1;

zhl_fb_json(true);

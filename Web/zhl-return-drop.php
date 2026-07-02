<?php
/**
 * ZHL Selbst-Rückgabe — öffentliche QR-Landeseite (SPEC-SELBSTRUECKGABE).
 *
 * Aufruf vom orts-eigenen QR: zhl-return-drop.php?loc=<qr_token>[&t=<magic-link-token>]
 *
 * Identifikation:
 *  - gültiges ?t=  → login-freier Magic-Link (das Token IST die Capability/CSRF-Schutz, einmalig, 2 h),
 *  - sonst bestehende LB-Session (ohne Zwangs-Redirect; CSRF via $session->CSRFToken),
 *  - sonst Auswahl: „Einloggen" ODER „E-Mail eingeben" (Magic-Link, immer neutrale Antwort).
 *
 * Der Nutzer wählt seine gerade abgelegten Medien (nur selbst-rückgabefähige, eigene), macht ein
 * PFLICHT-Foto (Kamera in der App) und sendet ab → je Medium eine zhl_self_return-Zeile (status
 * 'reported'), Foto privat abgelegt, Mail mit Foto-Nachweis an Nutzer + Team. Das Gerät bleibt
 * gesperrt, bis das Team die Rückgabe über die bestehende Checkliste bestätigt.
 *
 * Eigenständige ZHL-Datei (Vorbild zhl-termin-auswahl.php), bootstrappt das LB-Framework lazy.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'lib/Server/namespace.php');
require_once(ROOT_DIR . 'lib/Email/namespace.php');
require_once(__DIR__ . '/zhl-return-lib.php');

ExceptionHandler::SetExceptionHandler(new WebExceptionHandler(function () {
    http_response_code(500);
    echo 'Es ist ein Fehler aufgetreten. Bitte später erneut versuchen oder das ZHL-Team kontaktieren.';
}));

// #5: Magic-Link-Token (?t=) ist ein Capability-Token — nicht als Referer an Subresourcen weitergeben.
if (!headers_sent()) {
    header('Referrer-Policy: no-referrer');
}

$h = fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$tzName = 'Europe/Berlin';
try {
    $tzName = (string)Configuration::Instance()->GetDefaultTimezone();
} catch (Throwable $e) {
    // Fallback
}
$fmtLocal = function (?string $utcStr) use ($tzName): string {
    if (!$utcStr) {
        return '—';
    }
    try {
        return (new DateTime($utcStr, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone($tzName))->format('d.m.Y H:i');
    } catch (Throwable $e) {
        return '—';
    }
};

/** Magic-Link-Mail verschicken (best effort). */
function zhl_return_send_link(array $user, array $location, string $token): void
{
    try {
        require_once(dirname(__DIR__) . '/Presenters/ZhlTerminRequestEmail.php');
        $name = trim((string)($user['fname'] ?? '') . ' ' . (string)($user['lname'] ?? ''));
        $email = trim((string)($user['email'] ?? ''));
        if ($email === '') {
            return;
        }
        $link = zhl_return_base_url() . 'zhl-return-drop.php?loc=' . urlencode((string)$location['qr_token'])
            . '&t=' . urlencode($token);
        $lines = [
            ($name !== '' ? 'Hallo ' . $name . ',' : 'Hallo,'), '',
            'Sie möchten am Ablageort „' . (string)$location['label'] . '" Medien zurückgeben.',
            'Über diesen Link sehen Sie Ihre ausgeliehenen Medien und können die Rückgabe melden',
            '(der Link gilt 2 Stunden und ist einmalig nutzbar):',
            '',
            '  ' . $link,
            '',
            'Falls Sie das nicht angefordert haben, können Sie diese Mail ignorieren.',
            '',
            'Viele Grüße',
            'ZHL Medienausleihe',
        ];
        $body = implode("\n", $lines);
        $lang = !empty($user['language']) ? (string)$user['language'] : null;
        $mail = new ZhlTerminRequestEmail([new EmailAddress($email, $name !== '' ? $name : $email)], [],
            'ZHL Medienausleihe — Rückgabe melden', $body, $lang);
        ServiceLocator::GetEmailService()->Send($mail);
    } catch (Throwable $e) {
        Log::Error('zhl_return_send_link: %s', $e);
    }
}

// --- Standort auflösen ----------------------------------------------------------------------------
$loc = (string)($_REQUEST['loc'] ?? '');
$location = $loc !== '' ? zhl_return_location_by_qr($loc) : null;

// --- Identität bestimmen --------------------------------------------------------------------------
$accessToken = (string)($_REQUEST['t'] ?? '');
$server = ServiceLocator::GetServer();
$session = $server->GetUserSession();

$user = null;
$viaMagic = false;
$csrf = '';
if ($accessToken !== '') {
    $acc = zhl_return_access_resolve($accessToken);
    if ($acc !== null) {
        $user = zhl_return_user_by_id((int)$acc['user_id']);
        $viaMagic = $user !== null;
        // #2: Standort ist an das Token gebunden — maßgeblich ist die im Token gespeicherte location_id,
        // NICHT der (manipulierbare) loc-Parameter. Verhindert das Melden an einem fremden Ablageort.
        if ($viaMagic && !empty($acc['location_id'])) {
            $tokLoc = zhl_return_location_by_id((int)$acc['location_id']);
            $location = $tokLoc; // null → unten freundliche Ablehnung (Ort entfernt/deaktiviert)
        }
    }
}
if ($user === null && $session->IsLoggedIn()) {
    $user = zhl_return_user_by_id((int)$session->UserId);
    if ($user !== null) {
        $csrf = (string)$session->CSRFToken;
    }
}

$error = null;
$flash = null;
$linkSent = false;
$done = null; // array{count,label} nach erfolgreicher Meldung

// --- POST verarbeiten -----------------------------------------------------------------------------
if ($location !== null && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'request_link') {
        $emailIn = trim((string)($_POST['email'] ?? ''));
        $u = zhl_return_user_by_email($emailIn);
        if ($u !== null) {
            $token = zhl_return_access_create((int)$u['user_id'], (int)$location['id']);
            zhl_return_send_link($u, $location, $token);
        }
        // Immer neutral (keine Account-Enumeration).
        $linkSent = true;

    } elseif ($action === 'submit') {
        if ($user === null) {
            $error = 'Bitte zuerst einloggen oder per E-Mail anmelden.';
        } elseif (!$viaMagic && !hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            $error = 'Sicherheitsprüfung fehlgeschlagen. Bitte die Seite neu laden.';
        } else {
            $openItems = zhl_return_open_items_for_user((int)$user['user_id']);
            $byId = [];
            foreach ($openItems as $it) {
                if (!(int)$it['already_reported']) {
                    $byId[(int)$it['handover_id']] = $it;
                }
            }
            $selRaw = $_POST['items'] ?? [];
            $selIds = is_array($selRaw) ? array_map('intval', $selRaw) : [];
            $chosen = [];
            foreach ($selIds as $id) {
                if (isset($byId[$id])) {
                    $chosen[] = $byId[$id];
                }
            }

            $photo = $_FILES['photo'] ?? null;
            $hasPhoto = is_array($photo) && (int)($photo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

            if (empty($chosen)) {
                $error = 'Bitte wählen Sie mindestens ein Medium aus, das Sie jetzt zurückgeben.';
            } elseif (!$hasPhoto) {
                $error = 'Bitte machen Sie ein Foto der abgelegten Medien (Pflicht-Nachweis).';
            } else {
                $store = zhl_return_store_photo($photo);
                if (!$store['ok']) {
                    $error = $store['error'] ?? 'Foto konnte nicht verarbeitet werden.';
                } elseif ($viaMagic && !zhl_return_access_claim($accessToken)) {
                    // #1: Magic-Link genau einmal — wer das Rennen verliert oder einen abgelaufenen
                    // Link nutzt, speichert NICHT. Soeben hochgeladenes Foto verwerfen (kein Waisenfile).
                    $orphan = zhl_return_photo_abs_path((string)$store['path']);
                    if ($orphan !== null) {
                        @unlink($orphan);
                    }
                    $error = 'Dieser Link wurde bereits verwendet oder ist abgelaufen. Bitte scannen Sie den QR-Code erneut.';
                } else {
                    $note = trim((string)($_POST['note'] ?? ''));
                    $n = zhl_return_record(
                        (int)$location['id'],
                        (int)$user['user_id'],
                        (string)($user['email'] ?? ''),
                        $chosen,
                        (string)$store['path'],
                        $note !== '' ? $note : null
                    );
                    if ($n > 0) {
                        $abs = zhl_return_photo_abs_path((string)$store['path']);
                        if ($abs !== null) {
                            zhl_return_notify($user, (string)($user['email'] ?? ''), $location, $chosen, $abs, $note !== '' ? $note : null);
                        }
                        $done = ['count' => $n, 'label' => (string)$location['label']];
                    } else {
                        // Nichts gespeichert (z. B. alles bereits gemeldet) → Foto verwerfen.
                        $orphan = zhl_return_photo_abs_path((string)$store['path']);
                        if ($orphan !== null) {
                            @unlink($orphan);
                        }
                        $error = 'Es wurde nichts gespeichert — diese Medien sind möglicherweise bereits als zurückgegeben gemeldet.';
                    }
                }
            }
        }
    }
}

// --- Items für die Anzeige (nach erfolgreicher Meldung neu, also frisch) ---------------------------
$items = ($user !== null && $done === null) ? zhl_return_open_items_for_user((int)$user['user_id']) : [];
$userName = $user !== null ? trim((string)($user['fname'] ?? '') . ' ' . (string)($user['lname'] ?? '')) : '';
// LB-Login mit Rücksprung auf DIESE Drop-Seite: ohne ?redirect= landet der Nutzer nach dem Login auf
// dem Dashboard und der Ablageort-Kontext (loc) ginge verloren. Das Ziel ist ein relativer Pfad (gleiches
// /Web/-Verzeichnis wie index.php) → RedirectUrlSanitizer lässt es als same-origin passieren.
$loginUrl = zhl_return_base_url() . 'index.php';
if ($location !== null) {
    $loginUrl .= '?redirect=' . rawurlencode('zhl-return-drop.php?loc=' . (string)$location['qr_token']);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medien zurückgeben — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .wrap { max-width: 620px; }
        .btn-zhl { background:#009260; border-color:#009260; color:#fff; }
        .btn-zhl:hover { background:#007a50; border-color:#007a50; color:#fff; }
        .item { border:1px solid #e3eae6; border-radius:10px; padding:12px 14px; margin-bottom:10px; }
        .item.disabled { opacity:.6; }
        #photoPreview { max-width:100%; border-radius:10px; margin-top:10px; display:none; }
        .photo-drop { border:2px dashed #b9c2bd; border-radius:12px; padding:18px; text-align:center; }
    </style>
</head>
<body>
<div class="container wrap py-4">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h1 class="h4 mb-3"><i class="bi bi-box-arrow-in-down text-success"></i> Medien zurückgeben</h1>

      <?php if ($location === null): ?>
        <div class="alert alert-danger"><i class="bi bi-x-circle"></i> Dieser QR-Code ist ungültig oder der Ablageort ist nicht aktiv.</div>
        <p class="text-muted small mb-0">Bitte wenden Sie sich an das ZHL-Medien-Team.</p>

      <?php elseif ($done !== null): ?>
        <div class="alert alert-success">
          <i class="bi bi-check-circle"></i> Vielen Dank! <strong><?= (int)$done['count'] ?></strong>
          <?= $done['count'] === 1 ? 'Medium wurde' : 'Medien wurden' ?> als am
          <strong><?= $h($done['label']) ?></strong> abgelegt gemeldet.
        </div>
        <p class="text-muted small">
          Sie haben eine Bestätigungs-Mail mit dem Foto-Nachweis erhalten. Das ZHL-Team holt die Medien
          vom Ablageort und schließt die Rückgabe nach einer kurzen Sichtprüfung ab.
        </p>

      <?php else: ?>
        <p class="text-muted">Ablageort: <strong><?= $h((string)$location['label']) ?></strong></p>
        <?php if (($location['note'] ?? '') !== ''): ?>
          <div class="alert alert-light border small"><i class="bi bi-info-circle text-success"></i> <?= $h((string)$location['note']) ?></div>
        <?php endif; ?>

        <?php if ($error !== null): ?>
          <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= $h($error) ?></div>
        <?php endif; ?>

        <?php if ($linkSent): ?>
          <div class="alert alert-info">
            <i class="bi bi-envelope-check"></i> Falls ein Konto mit dieser E-Mail-Adresse existiert,
            haben wir Ihnen einen Link zum Melden der Rückgabe geschickt. Bitte prüfen Sie Ihr Postfach.
          </div>
        <?php endif; ?>

        <?php if ($user === null): ?>
          <!-- Identifikation: Login ODER E-Mail-Magic-Link -->
          <p>Um Ihre ausgeliehenen Medien anzuzeigen, melden Sie sich bitte an:</p>
          <a class="btn btn-zhl w-100 mb-3" href="<?= $h($loginUrl) ?>">
            <i class="bi bi-box-arrow-in-right"></i> Mit ZHL-Konto einloggen
          </a>
          <div class="text-center text-muted small mb-3">— oder Passwort vergessen? —</div>
          <form method="post">
            <input type="hidden" name="loc" value="<?= $h((string)$location['qr_token']) ?>">
            <input type="hidden" name="action" value="request_link">
            <label class="form-label">E-Mail-Adresse Ihres ZHL-Kontos</label>
            <div class="input-group">
              <input type="email" class="form-control" name="email" required placeholder="name@uni-bayreuth.de">
              <button class="btn btn-outline-success" type="submit"><i class="bi bi-send"></i> Link senden</button>
            </div>
            <div class="form-text">Sie erhalten einen Link per E-Mail, ohne Passwort.</div>
          </form>

        <?php else: ?>
          <!-- Identität bekannt: Medien wählen + Foto -->
          <p class="mb-1">Angemeldet als <strong><?= $h($userName !== '' ? $userName : (string)$user['email']) ?></strong>
            <?php if ($viaMagic): ?><span class="badge bg-secondary">per E-Mail-Link</span><?php endif; ?>
          </p>

          <?php
          $selectable = array_filter($items, fn($it) => !(int)$it['already_reported']);
          if (empty($items)):
          ?>
            <div class="alert alert-light border">
              <i class="bi bi-info-circle text-success"></i> Für Ihr Konto ist aktuell keine
              selbst-rückgabefähige Ausleihe offen. Geräte mit persönlicher Pflicht-Rückgabe geben Sie
              bitte wie gewohnt zum Übergabetermin zurück.
            </div>
          <?php else: ?>
            <form method="post" enctype="multipart/form-data" id="dropForm">
              <input type="hidden" name="loc" value="<?= $h((string)$location['qr_token']) ?>">
              <?php if ($viaMagic): ?>
                <input type="hidden" name="t" value="<?= $h($accessToken) ?>">
              <?php else: ?>
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
              <?php endif; ?>
              <input type="hidden" name="action" value="submit">

              <p class="mb-2">Welche Medien legen Sie jetzt ab?</p>
              <?php foreach ($items as $it):
                  $reported = (int)$it['already_reported'];
                  $hid = (int)$it['handover_id'];
                  $rname = ($it['resource_name'] ?? '') !== '' ? (string)$it['resource_name'] : 'Gerät #' . (int)$it['resource_id'];
              ?>
                <label class="item d-flex align-items-start gap-2 <?= $reported ? 'disabled' : '' ?>">
                  <input class="form-check-input mt-1" type="checkbox" name="items[]" value="<?= $hid ?>"
                         <?= $reported ? 'disabled' : 'checked' ?>>
                  <span>
                    <strong><?= $h($rname) ?></strong>
                    <?php if ($reported): ?>
                      <span class="badge bg-secondary">bereits gemeldet</span>
                    <?php endif; ?>
                    <br>
                    <span class="text-muted small">
                      Rückgabe bis: <?= $h($fmtLocal($it['scheduled_end_utc'] ?? null)) ?> Uhr
                      <?php if (($it['rueckgabeort'] ?? '') !== ''): ?> · regulärer Ort: <?= $h((string)$it['rueckgabeort']) ?><?php endif; ?>
                    </span>
                  </span>
                </label>
              <?php endforeach; ?>

              <div class="mt-3">
                <label class="form-label">Foto der abgelegten Medien <span class="text-danger">*</span></label>
                <div class="photo-drop">
                  <input class="form-control" type="file" name="photo" id="photoInput"
                         accept="image/*" capture="environment" required>
                  <div class="form-text">Pflicht-Nachweis. Auf dem Handy öffnet sich direkt die Kamera.</div>
                  <img id="photoPreview" alt="Vorschau">
                </div>
              </div>

              <div class="mt-3">
                <label class="form-label">Notiz (optional)</label>
                <textarea class="form-control" name="note" rows="2" maxlength="500"
                          placeholder="z. B. Akku fast leer, Tasche fehlt …"></textarea>
              </div>

              <button class="btn btn-zhl btn-lg w-100 mt-3" type="submit" id="submitBtn" <?= empty($selectable) ? 'disabled' : '' ?>>
                <i class="bi bi-check2-circle"></i> Rückgabe melden
              </button>
            </form>
            <script>
              (function () {
                var input = document.getElementById('photoInput');
                var preview = document.getElementById('photoPreview');
                if (input) {
                  input.addEventListener('change', function () {
                    if (input.files && input.files[0]) {
                      preview.src = URL.createObjectURL(input.files[0]);
                      preview.style.display = 'block';
                    }
                  });
                }
              })();
            </script>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <p class="text-center text-muted small mt-3">ZHL Medienausleihe · Universität Bayreuth</p>
</div>
<?php if ($viaMagic): ?>
<script>
  // #5: Capability-Token aus der sichtbaren Adresszeile/History entfernen, sobald die Seite geladen ist
  // (das Formular trägt es weiterhin im Hidden-Feld). Mindert das Mitloggen des Tokens bei Reload/Teilen.
  try {
    if (location.search.indexOf('t=') !== -1) {
      history.replaceState(null, '', location.pathname);
    }
  } catch (e) {}
</script>
<?php endif; ?>
</body>
</html>

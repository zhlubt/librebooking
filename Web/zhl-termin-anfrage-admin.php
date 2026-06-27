<?php
/**
 * ZHL B — Wunschtermin-Anfragen, Admin-Bearbeitung (2026-06-27).
 *
 * Listet offene Anfragen (zhl_termin_request, status=open). Pro Anfrage:
 *   - „Als Admin buchen" (nur Einzelgeräte): legt die Reservierung im NAMEN des Anfragenden an
 *     (ZhlReservationFacade mit dessen user_id, ausgeführt mit der Admin-Session → umgeht die
 *     ZHL-Pflicht-Slots/Mindestfristen) → status=booked + Referenz + Mail an den Nutzer.
 *   - „Ablehnen" (mit Grund): status=declined + Mail an den Nutzer.
 *   - Bundle-Anfragen: 1-Klick-Buchen nicht möglich → Deep-Link zur Bundle-Buchung (manuell).
 *
 * Nur fürs ZHL-Team (Admin). SecurePage + Admin-Check + CSRF. Additive ZHL-Datei, kein Core.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlTerminRequest.php');
require_once(ROOT_DIR . 'Presenters/ZhlTerminRequestEmail.php');
require_once(ROOT_DIR . 'Presenters/Reservation/ReservationPresenterFactory.php');
require_once(ROOT_DIR . 'Presenters/ZhlReservationFacade.php');
require_once(__DIR__ . '/zhl-audit-lib.php');

class ZhlTerminAnfrageAdminPage extends SecurePage
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

        $db = ServiceLocator::GetDatabase();
        $h = fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $tz = $session->Timezone;

        $flash = null;
        $flashErr = null;
        if ($this->IsPost()) {
            $this->EnforceCSRFCheck();
            $action = (string)($_POST['action'] ?? '');
            $id = (int)($_POST['req_id'] ?? 0);
            $req = $id > 0 ? ZhlTerminRequest::Get($db, $id) : null;
            if ($req === null || ($req['status'] ?? '') !== 'open') {
                $flashErr = 'Anfrage nicht gefunden oder bereits bearbeitet.';
            } elseif ($action === 'book') {
                $res = $this->adminBook($session, $req, $tz);
                if ($res['ok']) {
                    ZhlTerminRequest::SetStatus($db, $id, 'booked', (int)$session->UserId, 'Als Admin gebucht.', $res['ref']);
                    $this->notifyUser($db, $req, 'booked', $res['ref'], '');
                    zhl_audit_log(array_merge(zhl_audit_actor($session), [
                        'action' => 'termin.request.book', 'entity_type' => 'reservation',
                        'entity_id' => (string)($req['resource_id'] ?? ''), 'reference_number' => $res['ref'],
                        'detail' => ['req' => $id, 'for_user' => (int)$req['user_id']],
                    ]));
                    $flash = 'Gebucht (Ref ' . $res['ref'] . ') und Nutzer benachrichtigt.';
                } else {
                    $flashErr = $res['error'];
                }
            } elseif ($action === 'decline') {
                $note = trim((string)($_POST['decline_note'] ?? ''));
                ZhlTerminRequest::SetStatus($db, $id, 'declined', (int)$session->UserId, $note !== '' ? $note : 'Abgelehnt.');
                $this->notifyUser($db, $req, 'declined', '', $note);
                zhl_audit_log(array_merge(zhl_audit_actor($session), [
                    'action' => 'termin.request.decline', 'entity_type' => 'request',
                    'entity_id' => (string)$id, 'detail' => ['note' => $note],
                ]));
                $flash = 'Anfrage abgelehnt und Nutzer benachrichtigt.';
            }
        }

        $open = ZhlTerminRequest::ListOpen($db);
        $csrf = (string)$session->CSRFToken;
        $fmt = function (?string $utc) use ($tz): string {
            if ($utc === null || $utc === '') {
                return '—';
            }
            try {
                return Date::Parse($utc, 'UTC')->ToTimezone($tz)->Format('d.m.Y');
            } catch (Throwable $e) {
                return '—';
            }
        };
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wunschtermin-Anfragen — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .savebar { position:sticky; top:0; z-index:20; background:#fff; border-bottom:1px solid #e3eae6; }
        .req-card { border:1px solid #e3eae6; }
        .req-msg { white-space:pre-wrap; background:#f6f8f7; border-radius:8px; padding:8px 10px; font-size:.92rem; }
    </style>
</head>
<body>

<div class="savebar py-2 mb-3">
  <div class="container d-flex justify-content-between align-items-center" style="max-width:1000px">
    <h1 class="h5 mb-0"><i class="bi bi-calendar2-plus text-success"></i> Wunschtermin-Anfragen
      <span class="text-muted fs-6 fw-normal">· <?= count($open) ?> offen</span>
    </h1>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="zhl-dashboard.php"><i class="bi bi-arrow-left"></i> Zurück</a>
      <a class="btn btn-outline-secondary btn-sm" href="zhl-audit.php"><i class="bi bi-journal-text"></i> Audit-Log</a>
    </div>
  </div>
</div>

<div class="container pb-5" style="max-width:1000px">

  <?php if ($flash !== null): ?>
    <div class="alert alert-success py-2"><i class="bi bi-check-circle"></i> <?= $h($flash) ?></div>
  <?php endif; ?>
  <?php if ($flashErr !== null): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> <?= $h($flashErr) ?></div>
  <?php endif; ?>

  <?php if (empty($open)): ?>
    <div class="alert alert-light border"><i class="bi bi-inbox"></i> Keine offenen Anfragen.</div>
  <?php endif; ?>

  <?php foreach ($open as $r): $rid = (int)$r['id']; $isBundle = ($r['kind'] ?? 'single') === 'bundle'; ?>
    <div class="card req-card shadow-sm mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <div class="fw-semibold">
              <i class="bi <?= $isBundle ? 'bi-box-seam' : 'bi-camera-video' ?> text-success"></i>
              <?= $h($r['label']) ?>
              <?php if ($isBundle): ?><span class="badge text-bg-secondary">Bundle</span><?php endif; ?>
            </div>
            <div class="text-muted small mt-1">
              Wunsch: <strong><?= $h($fmt($r['desired_start'])) ?></strong> – <strong><?= $h($fmt($r['desired_end'])) ?></strong>
              · Projekt: <?= $h(($r['project_title'] ?? '') !== '' ? $r['project_title'] : '—') ?>
            </div>
            <div class="text-muted small">
              Von: <?= $h(trim(((string)($r['fname'] ?? '')) . ' ' . ((string)($r['lname'] ?? '')))) ?> &lt;<?= $h((string)($r['email'] ?? '')) ?>&gt;
            </div>
          </div>
          <span class="text-muted small">#<?= $rid ?></span>
        </div>

        <?php if (($r['message'] ?? '') !== ''): ?>
          <div class="req-msg mt-2"><?= $h((string)$r['message']) ?></div>
        <?php endif; ?>

        <div class="d-flex gap-2 mt-3 flex-wrap align-items-center">
          <?php if (!$isBundle): ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Reservierung jetzt im Namen des Nutzers anlegen (09:00–17:00 im Wunsch-Zeitraum)?');">
              <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
              <input type="hidden" name="action" value="book">
              <input type="hidden" name="req_id" value="<?= $rid ?>">
              <button class="btn btn-success btn-sm"><i class="bi bi-calendar-check"></i> Als Admin buchen</button>
            </form>
          <?php else: ?>
            <a class="btn btn-outline-success btn-sm" href="zhl-bundle-book.php?bid=<?= (int)($r['bundle_id'] ?? 0) ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Bundle manuell buchen</a>
          <?php endif; ?>

          <form method="post" class="d-flex gap-2 align-items-center ms-auto" onsubmit="return confirm('Anfrage ablehnen? Der Nutzer wird benachrichtigt.');">
            <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="decline">
            <input type="hidden" name="req_id" value="<?= $rid ?>">
            <input type="text" class="form-control form-control-sm" name="decline_note" maxlength="500" placeholder="Grund (optional, geht an den Nutzer)" style="min-width:240px">
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle"></i> Ablehnen</button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

</div>
</body>
</html>
        <?php
    }

    /**
     * Reservierung im Namen des Anfragenden anlegen (nur Einzelgerät). 09:00–17:00 im Wunsch-Zeitraum.
     * @return array{ok:bool,ref:string,error:string}
     */
    private function adminBook(UserSession $session, array $req, $tz): array
    {
        if (($req['kind'] ?? 'single') !== 'single' || empty($req['resource_id'])) {
            return ['ok' => false, 'ref' => '', 'error' => 'Nur Einzelgeräte können per 1-Klick gebucht werden — Bundle bitte manuell buchen.'];
        }
        try {
            $startLocal = Date::Parse((string)$req['desired_start'], 'UTC')->ToTimezone($tz)->Format('Y-m-d');
            $endLocal = Date::Parse((string)$req['desired_end'], 'UTC')->ToTimezone($tz)->Format('Y-m-d');
        } catch (Throwable $e) {
            return ['ok' => false, 'ref' => '', 'error' => 'Wunsch-Zeitraum nicht lesbar.'];
        }
        $titel = trim((string)($req['project_title'] ?? ''));
        if ($titel === '') {
            $titel = 'ZHL Ausleihe — ' . (string)$req['label'];
        }
        $desc = '[ZHL] Wunschtermin-Buchung durch das Medien-Team';
        $facade = new ZhlReservationFacade((int)$req['user_id'], (int)$req['resource_id'], $titel, $desc, $startLocal, '09:00', $endLocal, '17:00', []);
        try {
            $factory = new ReservationPresenterFactory();
            $presenter = $factory->Create($facade, $session);
            $series = $presenter->BuildReservation();
            $presenter->HandleReservation($series);
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: adminBook fehlgeschlagen: %s', $e);
            return ['ok' => false, 'ref' => '', 'error' => 'Buchung fehlgeschlagen: ' . $e->getMessage()];
        }
        if (!$facade->WasSaved()) {
            $errs = $facade->GetErrors();
            return ['ok' => false, 'ref' => '', 'error' => 'Buchung abgelehnt: ' . (empty($errs) ? 'Konflikt/Validierung' : implode(' · ', $errs))];
        }
        return ['ok' => true, 'ref' => (string)$facade->ReferenceNumber(), 'error' => ''];
    }

    /** Rückmeldung an den Anfragenden (gebucht/abgelehnt). Best effort. */
    private function notifyUser($db, array $req, string $kind, string $ref, string $note): void
    {
        try {
            $cmd = new AdHocCommand('SELECT email, fname, lname, language FROM users WHERE user_id = @uid');
            $cmd->AddParameter(new Parameter('@uid', (int)$req['user_id']));
            $reader = $db->Query($cmd);
            $u = $reader->GetRow();
            $reader->Free();
            if ($u === false || trim((string)($u['email'] ?? '')) === '') {
                return;
            }
            $name = trim(((string)($u['fname'] ?? '')) . ' ' . ((string)($u['lname'] ?? '')));
            $label = (string)$req['label'];
            if ($kind === 'booked') {
                $subject = 'ZHL Medienausleihe — Termin bestätigt: ' . $label;
                $lines = [
                    ($name !== '' ? 'Hallo ' . $name . ',' : 'Hallo,'), '',
                    'gute Nachricht: Dein Wunschtermin für „' . $label . '" wurde gebucht.',
                    'Buchungsnummer: ' . $ref,
                    'Details findest du unter „Meine Buchungen".', '',
                    'Viele Grüße', 'ZHL Medienausleihe',
                ];
            } else {
                $subject = 'ZHL Medienausleihe — Wunschtermin nicht möglich: ' . $label;
                $lines = [
                    ($name !== '' ? 'Hallo ' . $name . ',' : 'Hallo,'), '',
                    'leider konnten wir deinen Wunschtermin für „' . $label . '" nicht wie angefragt umsetzen.',
                    ($note !== '' ? "\nHinweis vom Team:\n" . $note : ''),
                    '', 'Bitte melde dich beim ZHL-Medien-Team für einen alternativen Termin.', '',
                    'Viele Grüße', 'ZHL Medienausleihe',
                ];
            }
            $to = [new EmailAddress((string)$u['email'], $name !== '' ? $name : (string)$u['email'])];
            $lang = !empty($u['language']) ? (string)$u['language'] : null;
            ServiceLocator::GetEmailService()->Send(new ZhlTerminRequestEmail($to, [], $subject, implode("\n", $lines), $lang));
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: notifyUser fehlgeschlagen: %s', $e);
        }
    }
}

$page = new ZhlTerminAnfrageAdminPage();
$page->PageLoad();

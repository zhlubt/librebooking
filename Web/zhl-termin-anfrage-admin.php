<?php
/**
 * ZHL — Einführungs-Terminwunsch, Admin-Aushandlung (SPEC-EINFUEHRUNG-AUSHANDLUNG, 2026-06-28).
 *
 * Löst den alten 1-Klick-„Als Admin buchen"-Range-Pfad ab (der eine durchgehende Mehrtages-
 * Reservierung anlegte — beim stundenweisen Studio falsch). Stattdessen Aushandlung:
 *   - „Termin anbieten" (action=offer): konkreter Einführungs-Slot + „wer macht die Einführung"
 *     (Dropdown aller Admins) + Notiz → Zeile in zhl_termin_offer (open). Mehrere Admins/Termine ok.
 *   - „zurückziehen" (action=withdraw_offer): Angebot → withdrawn.
 *   - „Angebote senden" (action=send_offers): Mail an den Nutzer mit allen offenen Angeboten +
 *     login-freiem Auswahllink (accept_token) → Request-Status offered. Blockiert ohne Angebote.
 *   - „Ablehnen" (action=decline): Gerät nicht verfügbar → declined + Mail.
 *
 * Die eigentliche Bestätigung (Auswahl) macht der Nutzer login-frei in zhl-termin-auswahl.php;
 * dort entstehen ICS-Einladung + (bei Studio) die native Einführungs-Reservierung. Die Geräte-LEIHE
 * bleibt separat (Nutzer bucht selbst ab Einführungs-Ende).
 *
 * Nur fürs ZHL-Team (Admin). SecurePage + Admin-Check + CSRF. Additive ZHL-Datei, kein Core.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlTerminRequest.php');
require_once(ROOT_DIR . 'Presenters/ZhlTerminRequestEmail.php');
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
            $status = $req !== null ? (string)($req['status'] ?? '') : '';
            $actionable = in_array($status, ['open', 'offered'], true);

            if ($req === null) {
                $flashErr = 'Anfrage nicht gefunden.';
            } elseif ($action === 'offer') {
                if (!$actionable) {
                    $flashErr = 'Diese Anfrage ist bereits abgeschlossen.';
                } else {
                    [$flash, $flashErr] = $this->addOffer($db, $session, $req, $tz);
                }
            } elseif ($action === 'withdraw_offer') {
                $offId = (int)($_POST['offer_id'] ?? 0);
                ZhlTerminRequest::WithdrawOffer($db, $offId, $id);
                zhl_audit_log(array_merge(zhl_audit_actor($session), [
                    'action' => 'termin.offer.withdraw', 'entity_type' => 'offer', 'entity_id' => (string)$offId,
                    'detail' => ['req' => $id],
                ]));
                $flash = 'Angebot zurückgezogen.';
            } elseif ($action === 'send_offers') {
                if (!$actionable) {
                    $flashErr = 'Diese Anfrage ist bereits abgeschlossen.';
                } elseif (ZhlTerminRequest::CountOpenOffers($db, $id) < 1) {
                    $flashErr = 'Bitte zuerst mindestens einen Termin anbieten.';
                } else {
                    $token = ZhlTerminRequest::EnsureToken($db, $id);
                    if ($token === null || $token === '') {
                        $flashErr = 'Auswahl-Link konnte nicht erzeugt werden.';
                    } else {
                        ZhlTerminRequest::MarkOffered($db, $id);
                        $sent = $this->notifyUserOffers($db, $req, $token);
                        zhl_audit_log(array_merge(zhl_audit_actor($session), [
                            'action' => 'termin.offers.send', 'entity_type' => 'request', 'entity_id' => (string)$id,
                            'detail' => ['count' => ZhlTerminRequest::CountOpenOffers($db, $id)],
                        ]));
                        $flash = $sent ? 'Angebote an den Nutzer geschickt.' : 'Status gesetzt, aber die Mail an den Nutzer ist fehlgeschlagen (siehe Log).';
                    }
                }
            } elseif ($action === 'decline') {
                if (!$actionable) {
                    $flashErr = 'Diese Anfrage ist bereits abgeschlossen.';
                } else {
                    $note = trim((string)($_POST['decline_note'] ?? ''));
                    if (ZhlTerminRequest::DeclineRequest($db, $id, (int)$session->UserId, $note)) {
                        $this->notifyUserDecline($db, $req, $note);
                        zhl_audit_log(array_merge(zhl_audit_actor($session), [
                            'action' => 'termin.request.decline', 'entity_type' => 'request', 'entity_id' => (string)$id,
                            'detail' => ['note' => $note],
                        ]));
                        $flash = 'Anfrage abgelehnt und Nutzer benachrichtigt.';
                    } else {
                        $flashErr = 'Anfrage konnte nicht abgelehnt werden (bereits abgeschlossen).';
                    }
                }
            }
        }

        $open = ZhlTerminRequest::ListActionable($db);
        $admins = ZhlTerminRequest::ListAdmins($db);
        $csrf = (string)$session->CSRFToken;
        $fmtDay = function (?string $utc) use ($tz): string {
            if ($utc === null || $utc === '') {
                return '—';
            }
            try {
                return Date::Parse($utc, 'UTC')->ToTimezone($tz)->Format('d.m.Y');
            } catch (Throwable $e) {
                return '—';
            }
        };
        $fmtDateTime = function (?string $utc) use ($tz): string {
            if ($utc === null || $utc === '') {
                return '—';
            }
            try {
                return Date::Parse($utc, 'UTC')->ToTimezone($tz)->Format('d.m.Y H:i');
            } catch (Throwable $e) {
                return '—';
            }
        };
        // Vorbelegung Angebots-Datum: morgen, 10:00–11:00 (nur Default).
        $defDay = Date::Now()->ToTimezone($tz)->AddDays(1)->Format('Y-m-d');
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einführungs-Terminwünsche — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .savebar { position:sticky; top:0; z-index:20; background:#fff; border-bottom:1px solid #e3eae6; }
        .req-card { border:1px solid #e3eae6; }
        .req-msg { white-space:pre-wrap; background:#f6f8f7; border-radius:8px; padding:8px 10px; font-size:.92rem; }
        .offer-row { border:1px solid #e3eae6; border-radius:8px; padding:8px 10px; }
        .offer-form { background:#f6f8f7; border-radius:8px; padding:12px; }
        .badge-offered { background:#e8f3ee; color:#0a7d4e; }
    </style>
</head>
<body>

<div class="savebar py-2 mb-3">
  <div class="container d-flex justify-content-between align-items-center" style="max-width:1000px">
    <h1 class="h5 mb-0"><i class="bi bi-calendar2-plus text-success"></i> Einführungs-Terminwünsche
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

  <div class="alert alert-light border small">
    <i class="bi bi-info-circle text-success"></i>
    Der Nutzer wünscht eine <strong>Einführung</strong>. Biete 1–3 konkrete Termine an (auch mehrere
    Kolleg:innen können Termine eintragen) und schick sie dem Nutzer zur Auswahl. Erst seine Auswahl
    bucht den Termin und verschickt Kalendereinladungen. Ist das Gerät gar nicht verfügbar →
    <strong>Ablehnen</strong>. <em>Keine</em> Mehrtages-Buchung mehr.
  </div>

  <?php if (empty($open)): ?>
    <div class="alert alert-light border"><i class="bi bi-inbox"></i> Keine offenen Anfragen.</div>
  <?php endif; ?>

  <?php foreach ($open as $r): $rid = (int)$r['id']; $isBundle = ($r['kind'] ?? 'single') === 'bundle';
        $offers = ZhlTerminRequest::ListOffers($db, $rid);
        $openOffers = array_values(array_filter($offers, fn($o) => ($o['status'] ?? '') === 'open'));
        $isOffered = ($r['status'] ?? '') === 'offered'; ?>
    <div class="card req-card shadow-sm mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <div class="fw-semibold">
              <i class="bi <?= $isBundle ? 'bi-box-seam' : 'bi-camera-video' ?> text-success"></i>
              <?= $h($r['label']) ?>
              <?php if ($isBundle): ?><span class="badge text-bg-secondary">Bundle</span><?php endif; ?>
              <?php if ($isOffered): ?><span class="badge badge-offered">Angebote raus</span><?php endif; ?>
            </div>
            <div class="text-muted small mt-1">
              Wunsch-Zeitraum: <strong><?= $h($fmtDay($r['desired_start'])) ?></strong> – <strong><?= $h($fmtDay($r['desired_end'])) ?></strong>
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

        <?php // --- Bereits eingetragene Angebote --- ?>
        <?php if (!empty($offers)): ?>
          <div class="mt-3">
            <div class="text-uppercase text-muted small fw-semibold mb-1">Angebotene Termine</div>
            <?php foreach ($offers as $o): $ostat = (string)($o['status'] ?? 'open'); ?>
              <div class="offer-row d-flex justify-content-between align-items-center mb-1 <?= $ostat !== 'open' ? 'opacity-50' : '' ?>">
                <div class="small">
                  <i class="bi bi-clock"></i> <strong><?= $h($fmtDateTime($o['start_utc'])) ?></strong>–<?= $h($fmtDateTime($o['end_utc']) === '—' ? '' : Date::Parse((string)$o['end_utc'], 'UTC')->ToTimezone($tz)->Format('H:i')) ?>
                  · Einführung: <?= $h((string)($o['instructor_name'] ?? '')) ?>
                  <?php if (($o['note'] ?? '') !== ''): ?><span class="text-muted">· <?= $h((string)$o['note']) ?></span><?php endif; ?>
                  <?php if ($ostat !== 'open'): ?><span class="badge text-bg-light ms-1"><?= $h($ostat) ?></span><?php endif; ?>
                </div>
                <?php if ($ostat === 'open'): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Angebot zurückziehen?');">
                    <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="action" value="withdraw_offer">
                    <input type="hidden" name="req_id" value="<?= $rid ?>">
                    <input type="hidden" name="offer_id" value="<?= (int)$o['id'] ?>">
                    <button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-x"></i> zurückziehen</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!$isBundle): ?>
          <?php // --- Neues Angebot eintragen --- ?>
          <form method="post" class="offer-form mt-3">
            <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="offer">
            <input type="hidden" name="req_id" value="<?= $rid ?>">
            <div class="row g-2 align-items-end">
              <div class="col-auto">
                <label class="form-label small mb-0">Datum</label>
                <input type="date" class="form-control form-control-sm" name="offer_date" value="<?= $h($defDay) ?>" required>
              </div>
              <div class="col-auto">
                <label class="form-label small mb-0">von</label>
                <input type="time" class="form-control form-control-sm" name="offer_start" value="10:00" required>
              </div>
              <div class="col-auto">
                <label class="form-label small mb-0">bis</label>
                <input type="time" class="form-control form-control-sm" name="offer_end" value="11:00" required>
              </div>
              <div class="col-auto">
                <label class="form-label small mb-0">Einführung macht</label>
                <select class="form-select form-select-sm" name="instructor_uid" required>
                  <?php foreach ($admins as $a): $auid = (int)$a['user_id']; $aname = trim(((string)($a['fname'] ?? '')) . ' ' . ((string)($a['lname'] ?? ''))); ?>
                    <option value="<?= $auid ?>" <?= $auid === (int)$session->UserId ? 'selected' : '' ?>><?= $h($aname !== '' ? $aname : (string)($a['email'] ?? ('#' . $auid))) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col">
                <label class="form-label small mb-0">Notiz (optional, geht an den Nutzer)</label>
                <input type="text" class="form-control form-control-sm" name="offer_note" maxlength="500" placeholder="z. B. Treffpunkt Raum 1.2.10">
              </div>
              <div class="col-auto">
                <button class="btn btn-outline-success btn-sm"><i class="bi bi-plus-lg"></i> Termin anbieten</button>
              </div>
            </div>
          </form>
        <?php else: ?>
          <div class="alert alert-warning small mt-3 mb-0">Bundle-Anfrage — Termine bitte manuell mit dem Nutzer abstimmen.</div>
        <?php endif; ?>

        <div class="d-flex gap-2 mt-3 flex-wrap align-items-center">
          <?php if (!$isBundle): ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Die <?= count($openOffers) ?> offenen Termine dem Nutzer zur Auswahl schicken?');">
              <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
              <input type="hidden" name="action" value="send_offers">
              <input type="hidden" name="req_id" value="<?= $rid ?>">
              <button class="btn btn-success btn-sm" <?= empty($openOffers) ? 'disabled' : '' ?>>
                <i class="bi bi-send"></i> <?= $isOffered ? 'Angebote erneut senden' : 'Angebote an Nutzer senden' ?>
                (<?= count($openOffers) ?>)
              </button>
            </form>
          <?php endif; ?>

          <form method="post" class="d-flex gap-2 align-items-center ms-auto" onsubmit="return confirm('Anfrage ablehnen? Der Nutzer wird benachrichtigt.');">
            <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="decline">
            <input type="hidden" name="req_id" value="<?= $rid ?>">
            <input type="text" class="form-control form-control-sm" name="decline_note" maxlength="500" placeholder="Grund (optional, z. B. Gerät verliehen)" style="min-width:220px">
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
     * Neues Termin-Angebot aus dem POST anlegen.
     * @return array{0:?string,1:?string} [flash, flashErr]
     */
    private function addOffer($db, UserSession $session, array $req, $tz): array
    {
        $dateStr = trim((string)($_POST['offer_date'] ?? ''));
        $startT = trim((string)($_POST['offer_start'] ?? ''));
        $endT = trim((string)($_POST['offer_end'] ?? ''));
        $instructorUid = (int)($_POST['instructor_uid'] ?? 0);
        $note = trim((string)($_POST['offer_note'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr) || !preg_match('/^\d{2}:\d{2}$/', $startT) || !preg_match('/^\d{2}:\d{2}$/', $endT)) {
            return [null, 'Bitte Datum sowie Start- und Endzeit angeben.'];
        }
        if ($endT <= $startT) {
            return [null, 'Die Endzeit muss nach der Startzeit liegen.'];
        }
        // Instructor muss ein gültiger Admin sein (kein Vertrauen in POST-Wert).
        $admins = ZhlTerminRequest::ListAdmins($db);
        $instructor = null;
        foreach ($admins as $a) {
            if ((int)$a['user_id'] === $instructorUid) {
                $instructor = $a;
                break;
            }
        }
        if ($instructor === null) {
            return [null, 'Bitte eine gültige Person für die Einführung wählen.'];
        }
        try {
            $startUtc = Date::Parse($dateStr . ' ' . $startT . ':00', $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
            $endUtc = Date::Parse($dateStr . ' ' . $endT . ':00', $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return [null, 'Termin-Zeit nicht lesbar.'];
        }
        if (Date::Parse($startUtc, 'UTC')->LessThan(Date::Now())) {
            return [null, 'Der Termin liegt in der Vergangenheit.'];
        }
        $instructorName = trim(((string)($instructor['fname'] ?? '')) . ' ' . ((string)($instructor['lname'] ?? '')));
        $offId = ZhlTerminRequest::AddOffer($db, [
            'request_id' => (int)$req['id'],
            'instructor_uid' => $instructorUid,
            'instructor_name' => $instructorName !== '' ? $instructorName : (string)($instructor['email'] ?? ''),
            'created_by_uid' => (int)$session->UserId,
            'start_utc' => $startUtc,
            'end_utc' => $endUtc,
            'note' => $note !== '' ? $note : null,
        ]);
        if ($offId <= 0) {
            return [null, 'Angebot konnte nicht gespeichert werden.'];
        }
        zhl_audit_log(array_merge(zhl_audit_actor($session), [
            'action' => 'termin.offer.add', 'entity_type' => 'offer', 'entity_id' => (string)$offId,
            'detail' => ['req' => (int)$req['id'], 'instructor' => $instructorUid, 'start' => $startUtc],
        ]));
        return ['Termin angeboten. „Angebote senden" schickt sie dem Nutzer.', null];
    }

    /** Mail an den Nutzer mit allen offenen Angeboten + login-freiem Auswahllink. */
    private function notifyUserOffers($db, array $req, string $token): bool
    {
        try {
            $u = $this->loadUser($db, (int)$req['user_id']);
            if ($u === null) {
                return false;
            }
            $tz = !empty($u['timezone']) ? (string)$u['timezone'] : 'Europe/Berlin';
            $offers = ZhlTerminRequest::ListOffers($db, (int)$req['id'], 'open');
            $name = trim(((string)($u['fname'] ?? '')) . ' ' . ((string)($u['lname'] ?? '')));
            $label = (string)$req['label'];
            $link = $this->absoluteBase() . 'zhl-termin-auswahl.php?token=' . urlencode($token);
            $lines = [
                ($name !== '' ? 'Hallo ' . $name . ',' : 'Hallo,'), '',
                'für Ihre Einführung zu „' . $label . '" schlagen wir folgende Termine vor.',
                'Bitte wählen Sie einen Termin aus:',
                '  ' . $link,
                '',
                'Mögliche Termine:',
            ];
            foreach ($offers as $o) {
                $start = $this->fmtLocal((string)$o['start_utc'], $tz, 'd.m.Y H:i');
                $end = $this->fmtLocal((string)$o['end_utc'], $tz, 'H:i');
                $line = '  • ' . $start . '–' . $end . ' Uhr — Einführung: ' . (string)($o['instructor_name'] ?? '');
                if (($o['note'] ?? '') !== '') {
                    $line .= ' (' . (string)$o['note'] . ')';
                }
                $lines[] = $line;
            }
            $lines = array_merge($lines, [
                '',
                'Nach Ihrer Auswahl erhalten Sie eine Kalendereinladung. Die Buchung des Geräts selbst',
                'nehmen Sie anschließend separat vor (ab dem Ende der Einführung).',
                '', 'Viele Grüße', 'ZHL Medienausleihe',
            ]);
            $to = [new EmailAddress((string)$u['email'], $name !== '' ? $name : (string)$u['email'])];
            $lang = !empty($u['language']) ? (string)$u['language'] : null;
            ServiceLocator::GetEmailService()->Send(new ZhlTerminRequestEmail($to, [], 'ZHL Medienausleihe — Terminvorschläge für Ihre Einführung: ' . $label, implode("\n", $lines), $lang));
            return true;
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: notifyUserOffers fehlgeschlagen: %s', $e);
            return false;
        }
    }

    /** Ablehnungs-Mail an den Nutzer. */
    private function notifyUserDecline($db, array $req, string $note): void
    {
        try {
            $u = $this->loadUser($db, (int)$req['user_id']);
            if ($u === null) {
                return;
            }
            $name = trim(((string)($u['fname'] ?? '')) . ' ' . ((string)($u['lname'] ?? '')));
            $label = (string)$req['label'];
            $lines = [
                ($name !== '' ? 'Hallo ' . $name . ',' : 'Hallo,'), '',
                'leider können wir Ihren Einführungs-Terminwunsch für „' . $label . '" derzeit nicht umsetzen.',
                ($note !== '' ? "\nHinweis vom Team:\n" . $note : ''),
                '', 'Bei Fragen melden Sie sich gern beim ZHL-Medien-Team.', '',
                'Viele Grüße', 'ZHL Medienausleihe',
            ];
            $to = [new EmailAddress((string)$u['email'], $name !== '' ? $name : (string)$u['email'])];
            $lang = !empty($u['language']) ? (string)$u['language'] : null;
            ServiceLocator::GetEmailService()->Send(new ZhlTerminRequestEmail($to, [], 'ZHL Medienausleihe — Einführungs-Terminwunsch nicht möglich: ' . $label, implode("\n", $lines), $lang));
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: notifyUserDecline fehlgeschlagen: %s', $e);
        }
    }

    private function loadUser($db, int $userId): ?array
    {
        $cmd = new AdHocCommand('SELECT email, fname, lname, language, timezone FROM users WHERE user_id = @uid');
        $cmd->AddParameter(new Parameter('@uid', $userId));
        $reader = $db->Query($cmd);
        $u = $reader->GetRow();
        $reader->Free();
        if ($u === false || trim((string)($u['email'] ?? '')) === '') {
            return null;
        }
        return $u;
    }

    private function fmtLocal(string $utc, string $tz, string $fmt): string
    {
        try {
            return Date::Parse($utc, 'UTC')->ToTimezone($tz)->Format($fmt);
        } catch (Throwable $e) {
            return '—';
        }
    }

    private function absoluteBase(): string
    {
        $url = rtrim((string)Configuration::Instance()->GetScriptUrl(), '/');
        return $url !== '' ? $url . '/' : '/Web/';
    }
}

$page = new ZhlTerminAnfrageAdminPage();
$page->PageLoad();

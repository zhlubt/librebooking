<?php
/**
 * ZHL — Sonderfreigaben (überlange Ausleihe) bearbeiten (SPEC-AUSLEIHDAUER-LIMIT §6.3).
 *
 *   - „Genehmigen" (action=approve): Status → approved, Grant-Token + Ablauf gesetzt; Mail an den Nutzer
 *     mit login-freiem Buchungs-Link (zhl-dauer-ausnahme-buchen.php?token=…).
 *   - „Ablehnen" (action=decline): Status → declined + Notiz; Mail an den Nutzer.
 *
 * Die genehmigte Freigabe hebt beim Buchen NUR das Dauer-Limit auf (Verfügbarkeit/Konflikt bleibt).
 * Nur fürs ZHL-Team (Admin). SecurePage + Admin-Check + CSRF. Additive ZHL-Datei, kein Core.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlDauerAusnahme.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlSettings.php');
require_once(ROOT_DIR . 'Presenters/ZhlTerminRequestEmail.php');
require_once(__DIR__ . '/zhl-audit-lib.php');

class ZhlDauerAusnahmeAdminPage extends SecurePage
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
        $tz = $session->Timezone !== '' ? $session->Timezone : 'Europe/Berlin';

        $flash = null;
        $flashErr = null;
        if ($this->IsPost()) {
            $this->EnforceCSRFCheck();
            $action = (string)($_POST['action'] ?? '');
            $id = (int)($_POST['req_id'] ?? 0);
            $req = $id > 0 ? ZhlDauerAusnahme::Get($db, $id) : null;

            if ($req === null) {
                $flashErr = 'Anfrage nicht gefunden.';
            } elseif ((string)($req['status'] ?? '') !== 'open') {
                $flashErr = 'Diese Anfrage ist bereits bearbeitet.';
            } elseif ($action === 'approve') {
                $note = trim((string)($_POST['admin_note'] ?? ''));
                $gueltig = ZhlSettings::GetInt('ausleih_ausnahme_gueltig_tage', 21);
                $token = ZhlDauerAusnahme::Approve($db, $id, (int)$session->UserId, $gueltig, $note !== '' ? $note : null);
                if ($token === null) {
                    $flashErr = 'Genehmigung fehlgeschlagen (bereits bearbeitet?).';
                } else {
                    $sent = $this->notifyUser($db, $req, true, $token, $note, $gueltig);
                    zhl_audit_log(array_merge(zhl_audit_actor($session), [
                        'action' => 'dauerausnahme.approve', 'entity_type' => 'dauerausnahme', 'entity_id' => (string)$id,
                        'detail' => ['gueltig_tage' => $gueltig],
                    ]));
                    $flash = $sent ? 'Genehmigt und Nutzer benachrichtigt.' : 'Genehmigt, aber die Mail an den Nutzer ist fehlgeschlagen (siehe Log).';
                }
            } elseif ($action === 'decline') {
                $note = trim((string)($_POST['admin_note'] ?? ''));
                if (ZhlDauerAusnahme::Decline($db, $id, (int)$session->UserId, $note)) {
                    $this->notifyUser($db, $req, false, null, $note, 0);
                    zhl_audit_log(array_merge(zhl_audit_actor($session), [
                        'action' => 'dauerausnahme.decline', 'entity_type' => 'dauerausnahme', 'entity_id' => (string)$id,
                        'detail' => ['note' => $note],
                    ]));
                    $flash = 'Anfrage abgelehnt und Nutzer benachrichtigt.';
                } else {
                    $flashErr = 'Ablehnung fehlgeschlagen (bereits bearbeitet?).';
                }
            }
        }

        $open = ZhlDauerAusnahme::ListOpen($db);
        $csrf = (string)$session->CSRFToken;
        $fmt = function (?string $utc) use ($tz): string {
            if ($utc === null || $utc === '') {
                return '—';
            }
            try {
                return Date::Parse($utc, 'UTC')->ToTimezone($tz)->Format('d.m.Y H:i');
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
    <title>Sonderfreigaben — ZHL Medienausleihe</title>
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
    <h1 class="h5 mb-0"><i class="bi bi-hourglass-split text-success"></i> Sonderfreigaben (längere Ausleihe)
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
    Genehmigst du, erhält der Nutzer einen Link zum Selbstbuchen — das Dauer-Limit (Nutzungsdauer <em>und</em>
    Abhol-/Rückgabe-Puffer) ist für genau dieses Zeitfenster aufgehoben. <strong>Verfügbarkeit/Konflikte werden
    weiterhin geprüft.</strong> Bitte vorab prüfen, ob das Gerät im Zeitraum frei ist.
  </div>

  <?php if (empty($open)): ?>
    <div class="alert alert-light border"><i class="bi bi-inbox"></i> Keine offenen Anfragen.</div>
  <?php endif; ?>

  <?php foreach ($open as $r): $rid = (int)$r['id']; ?>
    <div class="card req-card shadow-sm mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <div class="fw-semibold"><i class="bi bi-camera-video text-success"></i> <?= $h((string)($r['resource_name'] ?? ('#' . $r['resource_id']))) ?></div>
            <div class="text-muted small mt-1">
              Nutzung: <strong><?= $h($fmt($r['requested_begin_utc'])) ?></strong> – <strong><?= $h($fmt($r['requested_end_utc'])) ?></strong>
              · <strong><?= (int)$r['nutzung_tage'] ?> Tage</strong>
            </div>
            <div class="text-muted small">
              Von: <?= $h(trim(((string)($r['fname'] ?? '')) . ' ' . ((string)($r['lname'] ?? '')))) ?> &lt;<?= $h((string)($r['email'] ?? '')) ?>&gt;
            </div>
          </div>
          <span class="text-muted small">#<?= $rid ?></span>
        </div>

        <?php if (($r['reason'] ?? '') !== ''): ?>
          <div class="req-msg mt-2"><?= $h((string)$r['reason']) ?></div>
        <?php endif; ?>

        <div class="d-flex gap-2 mt-3 flex-wrap align-items-center">
          <form method="post" class="d-flex gap-2 align-items-center" onsubmit="return confirm('Sonderfreigabe genehmigen? Der Nutzer kann dann selbst buchen.');">
            <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="req_id" value="<?= $rid ?>">
            <input type="text" class="form-control form-control-sm" name="admin_note" maxlength="500" placeholder="Hinweis an den Nutzer (optional)" style="min-width:240px">
            <button class="btn btn-success btn-sm"><i class="bi bi-check2-circle"></i> Genehmigen</button>
          </form>

          <form method="post" class="d-flex gap-2 align-items-center ms-auto" onsubmit="return confirm('Anfrage ablehnen? Der Nutzer wird benachrichtigt.');">
            <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="decline">
            <input type="hidden" name="req_id" value="<?= $rid ?>">
            <input type="text" class="form-control form-control-sm" name="admin_note" maxlength="500" placeholder="Grund (optional)" style="min-width:220px">
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

    /** Mail an den Nutzer (genehmigt → Buchungs-Link; abgelehnt → Notiz). @return bool */
    private function notifyUser($db, array $req, bool $approved, ?string $token, string $note, int $gueltigTage): bool
    {
        try {
            $u = $this->loadUser($db, (int)$req['user_id']);
            if ($u === null) {
                return false;
            }
            $name = trim(((string)($u['fname'] ?? '')) . ' ' . ((string)($u['lname'] ?? '')));
            $resName = $this->resourceName($db, (int)$req['resource_id']);
            if ($approved && $token !== null) {
                $link = $this->absoluteBase() . 'zhl-dauer-ausnahme-buchen.php?token=' . urlencode($token);
                $lines = [
                    ($name !== '' ? 'Hallo ' . $name . ',' : 'Hallo,'), '',
                    'deine Sonderfreigabe für eine längere Ausleihe von „' . $resName . '" ist genehmigt.',
                    ($note !== '' ? "\nHinweis vom Team:\n" . $note . "\n" : ''),
                    'Bitte buche jetzt über diesen Link (das Dauer-Limit ist für diese Buchung aufgehoben):',
                    '  ' . $link,
                    '',
                    'Der Link ist ' . max(1, $gueltigTage) . ' Tage gültig. Bitte buche dein zugesagtes Zeitfenster.',
                    'Hinweis: Das Gerät muss im Zeitraum verfügbar sein — Konflikte werden beim Buchen geprüft.',
                    '', 'Viele Grüße', 'ZHL Medienausleihe',
                ];
                $subject = 'ZHL Medienausleihe — Sonderfreigabe genehmigt: ' . $resName;
            } else {
                $lines = [
                    ($name !== '' ? 'Hallo ' . $name . ',' : 'Hallo,'), '',
                    'leider können wir deine Anfrage für eine längere Ausleihe von „' . $resName . '" nicht genehmigen.',
                    ($note !== '' ? "\nHinweis vom Team:\n" . $note : ''),
                    '', 'Bei Fragen melde dich gern beim ZHL-Medien-Team.', '',
                    'Viele Grüße', 'ZHL Medienausleihe',
                ];
                $subject = 'ZHL Medienausleihe — Sonderfreigabe nicht möglich: ' . $resName;
            }
            $body = implode("\n", array_filter($lines, fn($l) => $l !== null));
            $to = [new EmailAddress((string)$u['email'], $name !== '' ? $name : (string)$u['email'])];
            $lang = !empty($u['language']) ? (string)$u['language'] : null;
            ServiceLocator::GetEmailService()->Send(new ZhlTerminRequestEmail($to, [], $subject, $body, $lang));
            return true;
        } catch (Throwable $e) {
            Log::Error('ZHL-DauerAusnahme: notifyUser fehlgeschlagen: %s', $e);
            return false;
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

    private function resourceName($db, int $rid): string
    {
        $cmd = new AdHocCommand('SELECT name FROM resources WHERE resource_id = @r');
        $cmd->AddParameter(new Parameter('@r', $rid));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row === false ? ('#' . $rid) : (string)($row['name'] ?? ('#' . $rid));
    }

    private function absoluteBase(): string
    {
        $url = rtrim((string)Configuration::Instance()->GetScriptUrl(), '/');
        return $url !== '' ? $url . '/' : '/Web/';
    }
}

$page = new ZhlDauerAusnahmeAdminPage();
$page->PageLoad();

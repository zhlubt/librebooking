<?php
/**
 * ZHL — Rückgabe-Mahnungen freigeben (Admin, 2026-06-27).
 *
 * Listet offene (status='pending') überfällige Rückgabe-Mahnungen (zhl_rueckgabe_mahnung), die der
 * Job eingereiht, aber NICHT automatisch versendet hat. Pro Eintrag:
 *   - „Mahnung senden": verschickt die Stufen-Mail (ZhlReturnMail) an den Ausleihenden → status='sent'.
 *   - „Gerät ist zurück": markiert die Rückgabe als erledigt (zhl_booking_handover.status='done') und
 *     verwirft offene Mahnungen dieser Rückgabe (keine weitere Eskalation).
 *   - „Ausblenden": verwirft nur diese eine Mahnung (false positive), ohne die Rückgabe abzuschließen.
 *
 * Die freundliche VORTAG-Erinnerung (Stufe 1) läuft automatisch im Job und erscheint hier nicht.
 * Nur fürs ZHL-Team (Admin). SecurePage + Admin-Check + CSRF. Additive ZHL-Datei, kein Core.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'Presenters/ZhlReturnMail.php');
require_once(__DIR__ . '/zhl-audit-lib.php');

class ZhlMahnungenAdminPage extends SecurePage
{
    private const CONTACT = 'zhlmedien@uni-bayreuth.de';

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
        $emailEnabled = Configuration::Instance()->GetKey(ConfigKeys::EMAIL_ENABLED, new BooleanConverter());

        $flash = null;
        $flashErr = null;
        if ($this->IsPost()) {
            $this->EnforceCSRFCheck();
            $action = (string)($_POST['action'] ?? '');
            $id = (int)($_POST['mahnung_id'] ?? 0);
            $m = $id > 0 ? $this->loadMahnung($db, $id) : null;
            if ($m === null || ($m['status'] ?? '') !== 'pending') {
                $flashErr = 'Mahnung nicht gefunden oder bereits bearbeitet.';
            } elseif ($action === 'send') {
                // Atomarer Freigabe-Claim gegen Doppelversand (kein affected-rows in der DB-API):
                // pending → sent + eindeutige send_nonce; nur wer beim Re-Read die EIGENE Nonce sieht,
                // hat den Claim gewonnen und versendet. Parallele/Doppel-POSTs verlieren → kein 2. Mail.
                $nonce = bin2hex(random_bytes(8));
                $claim = new AdHocCommand(
                    "UPDATE zhl_rueckgabe_mahnung SET status = 'sent', handled_by = @by, sent_at = @sent, send_nonce = @nonce " .
                    "WHERE id = @id AND status = 'pending'"
                );
                $claim->AddParameter(new Parameter('@by', (int)$session->UserId));
                $claim->AddParameter(new Parameter('@sent', gmdate('Y-m-d H:i:s')));
                $claim->AddParameter(new Parameter('@nonce', $nonce));
                $claim->AddParameter(new Parameter('@id', $id));
                $db->Execute($claim);

                $after = $this->loadMahnung($db, $id);
                if ($after === null || (string)($after['send_nonce'] ?? '') !== $nonce) {
                    $flashErr = 'Mahnung wurde parallel bereits bearbeitet.';
                } else {
                    if ($emailEnabled) {
                        $name = (string)$m['recipient_name'];
                        $dueLabel = $m['due_utc'] ? Date::Parse((string)$m['due_utc'], 'UTC')->ToTimezone($tz)->Format('d.m.Y') : '';
                        // EmailService::Send() wirft NICHT bei SMTP-Fehlern (zentrales Verhalten, loggt nur) —
                        // wie bei allen ZHL-Mails. Der Status bleibt 'sent' (= „Versand angestoßen").
                        ServiceLocator::GetEmailService()->Send(new ZhlReturnMail(
                            new EmailAddress((string)$m['recipient_email'], new FullName($name, '')),
                            (int)$m['stage'],
                            $name,
                            (string)$m['resource_name'],
                            $dueLabel,
                            self::CONTACT,
                            $m['language'] ?? null
                        ));
                    }
                    zhl_audit_log(array_merge(zhl_audit_actor($session), [
                        'action' => 'rueckgabe.mahnung.send', 'entity_type' => 'mahnung',
                        'entity_id' => (string)$id, 'reference_number' => (string)($m['reference_number'] ?? ''),
                        'detail' => ['stage' => (int)$m['stage'], 'to' => (string)$m['recipient_email']],
                    ]));
                    $flash = 'Mahnung (Stufe ' . (int)$m['stage'] . ') an ' . $m['recipient_email'] . ' gesendet.';
                }
            } elseif ($action === 'returned') {
                // Rückgabe als erledigt markieren + alle offenen Mahnungen dieser Rückgabe verwerfen.
                $upd = new AdHocCommand("UPDATE zhl_booking_handover SET status = 'done' WHERE id = @hid AND type = 'return'");
                $upd->AddParameter(new Parameter('@hid', (int)$m['handover_id']));
                $db->Execute($upd);
                $dis = new AdHocCommand("UPDATE zhl_rueckgabe_mahnung SET status = 'dismissed', handled_by = @by WHERE handover_id = @hid AND status = 'pending'");
                $dis->AddParameter(new Parameter('@hid', (int)$m['handover_id']));
                $dis->AddParameter(new Parameter('@by', (int)$session->UserId));
                $db->Execute($dis);
                // Reservierung auch verkürzen, damit das Medium nicht gesperrt bleibt.
                require_once __DIR__ . '/zhl-handover-lib.php';
                if (!empty($m['reference_number'])) {
                    zhl_handover_shorten_reservation_on_return((string)$m['reference_number']);
                }
                zhl_audit_log(array_merge(zhl_audit_actor($session), [
                    'action' => 'rueckgabe.mahnung.returned', 'entity_type' => 'handover',
                    'entity_id' => (string)$m['handover_id'], 'detail' => ['mahnung' => $id],
                ]));
                $flash = 'Rückgabe als erledigt markiert — keine weiteren Mahnungen für dieses Gerät.';
            } elseif ($action === 'dismiss') {
                $this->setStatus($db, $id, 'dismissed', (int)$session->UserId, false);
                $flash = 'Mahnung ausgeblendet.';
            }
        }

        $rows = $this->loadPending($db);
        $csrf = (string)$session->CSRFToken;
        $stageLabel = static fn(int $s): string => $s >= ZhlReturnMail::STAGE_FINAL ? 'Letzte Erinnerung' : 'Überfällig (deutlich)';
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
    <title>Rückgabe-Mahnungen — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .savebar { position:sticky; top:0; z-index:20; background:#fff; border-bottom:1px solid #e3eae6; }
        .stage-final { color:#b02a37; }
    </style>
</head>
<body>

<div class="savebar py-2 mb-3">
  <div class="container d-flex justify-content-between align-items-center" style="max-width:1000px">
    <h1 class="h5 mb-0"><i class="bi bi-bell text-success"></i> Rückgabe-Mahnungen freigeben
      <span class="text-muted fs-6 fw-normal">· <?= count($rows) ?> offen</span>
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
    Die freundliche <strong>Vortag-Erinnerung</strong> läuft automatisch. Hier erscheinen nur <strong>überfällige</strong>
    Rückgaben — bitte vor dem Senden prüfen, ob das Gerät nicht schon (unbestätigt) zurück ist.
  </div>

  <?php if (empty($rows)): ?>
    <div class="alert alert-light border"><i class="bi bi-inbox"></i> Keine offenen Mahnungen.</div>
  <?php endif; ?>

  <?php foreach ($rows as $r): $id = (int)$r['id']; $stage = (int)$r['stage']; ?>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <div class="fw-semibold">
              <i class="bi bi-camera-video text-success"></i> <?= $h($r['resource_name']) ?>
              <span class="badge <?= $stage >= ZhlReturnMail::STAGE_FINAL ? 'text-bg-danger' : 'text-bg-warning' ?>"><?= $h($stageLabel($stage)) ?></span>
            </div>
            <div class="text-muted small mt-1">
              Fällig war: <strong><?= $h($fmt($r['due_utc'])) ?></strong>
              <?php if (($r['reference_number'] ?? '') !== ''): ?> · Buchung <?= $h($r['reference_number']) ?><?php endif; ?>
            </div>
            <div class="text-muted small">
              Ausleihende(r): <?= $h($r['recipient_name']) ?> &lt;<?= $h($r['recipient_email']) ?>&gt;
            </div>
          </div>
          <span class="text-muted small">#<?= $id ?></span>
        </div>

        <div class="d-flex gap-2 mt-3 flex-wrap">
          <form method="post" class="d-inline" onsubmit="return confirm('Mahnung (Stufe <?= $stage ?>) jetzt an den Ausleihenden senden?');">
            <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="send">
            <input type="hidden" name="mahnung_id" value="<?= $id ?>">
            <button class="btn btn-success btn-sm"><i class="bi bi-send"></i> Mahnung senden</button>
          </form>
          <form method="post" class="d-inline" onsubmit="return confirm('Rückgabe als erledigt markieren? Es werden keine weiteren Mahnungen für dieses Gerät erzeugt.');">
            <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="returned">
            <input type="hidden" name="mahnung_id" value="<?= $id ?>">
            <button class="btn btn-outline-success btn-sm"><i class="bi bi-check2-circle"></i> Gerät ist zurück</button>
          </form>
          <form method="post" class="d-inline ms-auto" onsubmit="return confirm('Nur diese Mahnung ausblenden (Rückgabe bleibt offen)?');">
            <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
            <input type="hidden" name="action" value="dismiss">
            <input type="hidden" name="mahnung_id" value="<?= $id ?>">
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-eye-slash"></i> Ausblenden</button>
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

    /** @return array|null */
    private function loadMahnung($db, int $id): ?array
    {
        $cmd = new AdHocCommand('SELECT * FROM zhl_rueckgabe_mahnung WHERE id = @id');
        $cmd->AddParameter(new Parameter('@id', $id));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row === false ? null : $row;
    }

    /** @return array[] */
    private function loadPending($db): array
    {
        $out = [];
        $reader = $db->Query(new AdHocCommand(
            "SELECT * FROM zhl_rueckgabe_mahnung WHERE status = 'pending' ORDER BY stage DESC, created_at ASC"
        ));
        while ($row = $reader->GetRow()) {
            $out[] = $row;
        }
        $reader->Free();
        return $out;
    }

    /** Status nur aus 'pending' setzen (Race-/Doppel-Submit-sicher). */
    private function setStatus($db, int $id, string $status, int $adminId, bool $stampSent): bool
    {
        $sql = 'UPDATE zhl_rueckgabe_mahnung SET status = @status, handled_by = @by' .
            ($stampSent ? ', sent_at = @sent' : '') .
            " WHERE id = @id AND status = 'pending'";
        $cmd = new AdHocCommand($sql);
        $cmd->AddParameter(new Parameter('@status', $status));
        $cmd->AddParameter(new Parameter('@by', $adminId));
        if ($stampSent) {
            $cmd->AddParameter(new Parameter('@sent', gmdate('Y-m-d H:i:s')));
        }
        $cmd->AddParameter(new Parameter('@id', $id));
        $db->Execute($cmd);
        return true;
    }
}

$page = new ZhlMahnungenAdminPage();
$page->PageLoad();

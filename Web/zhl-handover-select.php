<?php
/**
 * ZHL Übergabe-Assistent (login-gebunden).
 *
 * Führt den eingeloggten Nutzer durch die Wahl von Abhol- UND Rückgabe-Termin für
 * ein übergabepflichtiges Gerät. Slots liefert terminplaner_ubt (Google-iCal je
 * Team-Mitglied); studentische Hilfskräfte (primary) zuerst, ZHL-Team (backup) danach.
 *
 * Sicherheit (Codex-Gate-Finding eingearbeitet):
 *  - **SecurePage**: nicht eingeloggte Nutzer werden auf den LB-Login umgeleitet.
 *  - **Token an User gebunden** (zhl_handover_token): fremde Token sind nicht sicht-/syncbar.
 *  - **Sync nur per POST + CSRF** (kein GET-Seiteneffekt mehr).
 *
 * Ablauf: Token erzeugen → Abholung+Rückgabe über terminplaner buchen
 * (Marker [HUE:<token>:<typ>]) → "Status aktualisieren" (POST) zieht die Buchungen
 * und schreibt sie bestätigt nach zhl_booking_handover → Token ins Reservierungs-
 * Attribut "handover_token" eintragen; das PreReservation-Plugin ZhlHandover prüft
 * Bestätigung UND Eigentümerschaft.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(__DIR__ . '/zhl-handover-lib.php');

class ZhlHandoverSelectPage extends SecurePage
{
    public function __construct()
    {
        // pageDepth 0 wie die nativen Web/-Seiten (z.B. DashboardPage) → Login-Redirect
        // bleibt relativ im /Web/-Pfad ('index.php'), nicht im Server-Root.
        parent::__construct('');
    }

    public function PageLoad()
    {
        $session = $this->server->GetUserSession();
        $userId = (int)$session->UserId;

        $ref = preg_replace('/[^A-Za-z0-9._-]/', '', (string)$this->GetQuerystring('ref'));
        $ref = $ref === '' ? null : $ref;

        // --- Token bestimmen (an User gebunden) ---
        $token = (string)$this->GetQuerystring('token');
        if (!zhl_handover_valid_token($token)) {
            $token = bin2hex(random_bytes(16)); // 32 hex chars
        }
        // Beanspruchen und DANACH Eigentümerschaft hart prüfen. Deckt fremde Token UND
        // einen parallelen Erst-Claim ab (TOCTOU): gehört das Token nach dem Claim nicht
        // diesem User, ist Schluss.
        zhl_handover_claim_token($token, $userId, $ref);
        if (zhl_handover_token_owner($token) !== $userId) {
            http_response_code(403);
            echo 'Dieses Übergabe-Token gehört einem anderen Konto.';
            return;
        }

        // --- Sync nur per POST + CSRF ---
        if ($this->IsPost() && $this->GetForm('action') === 'sync') {
            $this->EnforceCSRFCheck(); // stirbt bei CSRF-Mismatch
            if (zhl_handover_token_owner($token) === $userId) {
                zhl_handover_sync($token);
            }
            $loc = 'zhl-handover-select.php?token=' . urlencode($token);
            if ($ref !== null) {
                $loc .= '&ref=' . urlencode($ref);
            }
            header('Location: ' . $loc);
            return;
        }

        $this->Render($session, $token, $ref);
    }

    private function Render($session, string $token, ?string $ref): void
    {
        $status = zhl_handover_status($token);
        $slots = zhl_handover_get('/api/handover_slots.php', ['role' => 'any', 'limit' => 1]);
        $members = $slots['members'] ?? [];
        $base = rtrim((string)(zhl_handover_config()['terminplaner_base_url'] ?? ''), '/');
        $csrf = (string)$session->CSRFToken;

        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $bookLink = fn(int $memberId, string $type): string => $base . '/member.php?id=' . $memberId
            . '&hue=' . urlencode($token) . '&hue_type=' . urlencode($type);
        $bothDone = $status['pickup'] && $status['return'];
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Übergabe-Termin wählen — ZHL Medienausleihe</title>
    <!-- Lokale App-Assets (kein CDN) -> Optik wie der Rest der App -->
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .zhl-wrap { max-width: 760px; }
        .row-link { border-left-width:4px !important; }
        .role-primary { border-left-color:#009260 !important; }
        .role-backup  { border-left-color:#b9c2bd !important; }
        .token-box { font-family: ui-monospace, SFMono-Regular, monospace; letter-spacing:.02em; }
        .btn-zhl { background:#009260; border-color:#009260; color:#fff; }
        .btn-zhl:hover { background:#007a50; border-color:#007a50; color:#fff; }
    </style>
</head>
<body>
<div class="container zhl-wrap py-5">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <h1 class="h4 mb-1"><i class="bi bi-box-seam text-success"></i> Übergabe-Termin wählen</h1>
      <p class="text-muted">
        Dieses Gerät wird persönlich ausgegeben und zurückgenommen. Bitte wählen Sie einen
        <strong>Abhol-</strong> und einen <strong>Rückgabe-Termin</strong>.
        <?php if ($ref !== null): ?><br><small>Vorgang: <?= $h($ref) ?></small><?php endif; ?>
      </p>

      <?php if ($base === '' || empty($members)): ?>
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle"></i>
          Es konnten gerade keine Übergabe-Slots geladen werden. Bitte später erneut versuchen
          oder das ZHL-Team kontaktieren.
        </div>
      <?php endif; ?>

      <?php foreach (['pickup' => '1. Abholung', 'return' => '2. Rückgabe'] as $type => $heading): ?>
        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0"><?= $h($heading) ?></h2>
            <span class="badge <?= $status[$type] ? 'text-bg-success' : 'text-bg-secondary' ?>">
              <?= $status[$type] ? '✓ gebucht' : 'offen' ?>
            </span>
          </div>
          <?php foreach ($members as $m):
              $role = ($m['handover_role'] ?? 'backup') === 'primary' ? 'primary' : 'backup'; ?>
            <a class="list-group-item list-group-item-action row-link role-<?= $role ?> border rounded mb-2 d-block"
               target="_blank" rel="noopener"
               href="<?= $h($bookLink((int)$m['member_id'], $type)) ?>">
              <span class="fw-semibold"><?= $h((string)$m['member_name']) ?></span>
              <span class="badge <?= $role === 'primary' ? 'text-bg-success' : 'text-bg-light' ?> ms-1">
                <?= $role === 'primary' ? 'Hilfskraft' : 'Team (Backup)' ?>
              </span>
              <small class="text-muted d-block"><?= $h((string)$m['type_label']) ?></small>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>

      <form method="POST" class="mb-3"
            action="zhl-handover-select.php?token=<?= urlencode($token) ?><?= $ref !== null ? '&ref=' . urlencode($ref) : '' ?>">
        <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
        <input type="hidden" name="action" value="sync">
        <button type="submit" class="btn btn-zhl"><i class="bi bi-arrow-clockwise"></i> Status aktualisieren</button>
        <small class="text-muted ms-2">Nach dem Buchen beider Termine hier klicken.</small>
      </form>

      <div class="alert <?= $bothDone ? 'alert-success' : 'alert-light border' ?> mb-0">
        <div class="fw-semibold mb-1">
          <?= $bothDone ? '✓ Übergabe terminiert' : 'Übergabe-Token' ?>
        </div>
        <p class="small mb-2">
          Tragen Sie dieses Token im Reservierungsformular in das Feld
          <em>„Übergabe-Token (handover_token)"</em> ein:
        </p>
        <div class="token-box fs-5 p-2 bg-white border rounded"><?= $h($token) ?></div>
        <?php if (!$bothDone): ?>
          <p class="small text-muted mt-2 mb-0">
            Die Reservierung lässt sich erst speichern, wenn <strong>Abholung und Rückgabe</strong>
            gebucht und bestätigt sind.
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
        <?php
    }
}

$page = new ZhlHandoverSelectPage();
$page->PageLoad();

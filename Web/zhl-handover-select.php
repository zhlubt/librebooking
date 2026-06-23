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
    <link href="css/zhl-theme.css" rel="stylesheet">
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:#f6f8f7; color:#1f2a26; margin:0; }
        .wrap { max-width: 760px; margin: 2.5rem auto; padding: 0 1rem; }
        .card { background:#fff; border:1px solid #e3e8e5; border-radius:12px; padding:1.75rem; box-shadow:0 1px 3px rgba(0,0,0,.05); }
        h1 { font-size:1.4rem; margin:0 0 .25rem; }
        .muted { color:#6b7770; }
        .step { margin:1.5rem 0; }
        .step-head { display:flex; justify-content:space-between; align-items:center; }
        .badge { font-size:.78rem; padding:.15rem .55rem; border-radius:999px; }
        .b-ok { background:#e3f3ec; color:#0a7a52; } .b-open { background:#eef1ef; color:#6b7770; }
        .b-primary { background:#e3f3ec; color:#0a7a52; } .b-backup { background:#eef1ef; color:#6b7770; }
        .row-link { display:block; padding:.7rem .9rem; border:1px solid #e3e8e5; border-left-width:4px; border-radius:8px; margin-bottom:.5rem; text-decoration:none; color:inherit; }
        .row-link:hover { background:#f3f7f5; }
        .role-primary { border-left-color:#009260; } .role-backup { border-left-color:#b9c2bd; }
        .name { font-weight:600; } .small { font-size:.85rem; }
        .btn { display:inline-block; background:#009260; color:#fff; border:0; padding:.55rem 1rem; border-radius:8px; cursor:pointer; font-size:.95rem; }
        .alert { padding:1rem; border-radius:8px; margin-top:1rem; }
        .alert-ok { background:#e3f3ec; } .alert-light { background:#fafbfa; border:1px solid #e3e8e5; }
        .token-box { font-family: ui-monospace, monospace; font-size:1.15rem; padding:.5rem .7rem; background:#fff; border:1px solid #e3e8e5; border-radius:8px; }
    </style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Übergabe-Termin wählen</h1>
    <p class="muted">
      Dieses Gerät wird persönlich ausgegeben und zurückgenommen. Bitte wählen Sie einen
      <strong>Abhol-</strong> und einen <strong>Rückgabe-Termin</strong>.
      <?php if ($ref !== null): ?><br><span class="small">Vorgang: <?= $h($ref) ?></span><?php endif; ?>
    </p>

    <?php if ($base === '' || empty($members)): ?>
      <div class="alert alert-light">
        Es konnten gerade keine Übergabe-Slots geladen werden. Bitte später erneut versuchen
        oder das ZHL-Team kontaktieren.
      </div>
    <?php endif; ?>

    <?php foreach (['pickup' => '1. Abholung', 'return' => '2. Rückgabe'] as $type => $heading): ?>
      <div class="step">
        <div class="step-head">
          <h2 style="font-size:1.05rem;margin:0 0 .5rem;"><?= $h($heading) ?></h2>
          <span class="badge <?= $status[$type] ? 'b-ok' : 'b-open' ?>">
            <?= $status[$type] ? '✓ gebucht' : 'offen' ?>
          </span>
        </div>
        <?php foreach ($members as $m):
            $role = ($m['handover_role'] ?? 'backup') === 'primary' ? 'primary' : 'backup'; ?>
          <a class="row-link role-<?= $role ?>" target="_blank" rel="noopener"
             href="<?= $h($bookLink((int)$m['member_id'], $type)) ?>">
            <span class="name"><?= $h((string)$m['member_name']) ?></span>
            <span class="badge <?= $role === 'primary' ? 'b-primary' : 'b-backup' ?>">
              <?= $role === 'primary' ? 'Hilfskraft' : 'Team (Backup)' ?>
            </span>
            <span class="small muted" style="display:block;"><?= $h((string)$m['type_label']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <form method="POST" action="zhl-handover-select.php?token=<?= urlencode($token) ?><?= $ref !== null ? '&ref=' . urlencode($ref) : '' ?>">
      <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
      <input type="hidden" name="action" value="sync">
      <button type="submit" class="btn">Status aktualisieren</button>
      <span class="small muted" style="margin-left:.5rem;">Nach dem Buchen beider Termine hier klicken.</span>
    </form>

    <div class="alert <?= $bothDone ? 'alert-ok' : 'alert-light' ?>">
      <div style="font-weight:600;margin-bottom:.4rem;">
        <?= $bothDone ? '✓ Übergabe terminiert' : 'Übergabe-Token' ?>
      </div>
      <p class="small" style="margin:.2rem 0 .6rem;">
        Tragen Sie dieses Token im Reservierungsformular in das Feld
        <em>„Übergabe-Token (handover_token)"</em> ein:
      </p>
      <div class="token-box"><?= $h($token) ?></div>
      <?php if (!$bothDone): ?>
        <p class="small muted" style="margin:.6rem 0 0;">
          Die Reservierung lässt sich erst speichern, wenn <strong>Abholung und Rückgabe</strong>
          gebucht und bestätigt sind.
        </p>
      <?php endif; ?>
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

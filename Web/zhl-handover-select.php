<?php
/**
 * ZHL Übergabe-Assistent.
 *
 * Führt den Nutzer durch die Wahl von Abhol- UND Rückgabe-Termin für ein
 * übergabepflichtiges Gerät. Die Slots liefert terminplaner_ubt (Google-iCal je
 * Team-Mitglied); studentische Hilfskräfte (primary) werden zuerst angeboten,
 * das ZHL-Team (backup) als Alternative.
 *
 * Ablauf:
 *  1. Token erzeugen (Join-Schlüssel zur Reservierung).
 *  2. Abholung + Rückgabe über terminplaner buchen (Marker [HUE:<token>:<typ>]).
 *  3. "Status aktualisieren" zieht die Buchungen aus terminplaner und schreibt sie
 *     bestätigt nach zhl_booking_handover (Transaktion + Unique-Constraint).
 *  4. Token in das Reservierungs-Custom-Attribut "handover_token" eintragen; das
 *     PreReservation-Plugin ZhlHandover prüft, ob Abholung+Rückgabe bestätigt sind.
 *
 * Prototyp (wie Web/zhl-welcome.php) — leichtgewichtig, ZHL-eigene Datei.
 * TODO Härtung: an LibreBooking-Login binden (aktuell offen, Server-Gate ist das Plugin).
 */

declare(strict_types=1);

require __DIR__ . '/zhl-handover-lib.php';

// --- Token bestimmen oder erzeugen ---
$token = (string)($_GET['token'] ?? '');
if (!zhl_handover_valid_token($token)) {
    $token = bin2hex(random_bytes(16)); // 32 hex chars
}
$ref = preg_replace('/[^A-Za-z0-9._-]/', '', (string)($_GET['ref'] ?? ''));

// --- Sync-Aktion (Post/Redirect/Get) ---
if (($_GET['action'] ?? '') === 'sync') {
    zhl_handover_sync($token);
    $loc = 'zhl-handover-select.php?token=' . urlencode($token);
    if ($ref !== '') {
        $loc .= '&ref=' . urlencode($ref);
    }
    header('Location: ' . $loc);
    exit;
}

$status = zhl_handover_status($token);
$slots = zhl_handover_get('/api/handover_slots.php', ['role' => 'any', 'limit' => 1]);
$members = $slots['members'] ?? [];
$base = rtrim((string)(zhl_handover_config()['terminplaner_base_url'] ?? ''), '/');

function zhl_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Buchungs-Link auf terminplaner für einen Member + Übergabe-Typ. */
function zhl_book_link(string $base, int $memberId, string $token, string $type): string
{
    return $base . '/member.php?id=' . $memberId
        . '&hue=' . urlencode($token)
        . '&hue_type=' . urlencode($type);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Übergabe-Termin wählen — ZHL Medienausleihe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/zhl-theme.css" rel="stylesheet">
    <style>
        body { background:#f6f8f7; }
        .step-card { max-width: 760px; }
        .role-primary { border-left: 4px solid #009260; }
        .role-backup  { border-left: 4px solid #b9c2bd; }
        .token-box { font-family: ui-monospace, monospace; letter-spacing:.03em; }
    </style>
</head>
<body>
<div class="container py-5">
  <div class="card step-card mx-auto shadow-sm">
    <div class="card-body p-4">
      <h1 class="h4 mb-1">Übergabe-Termin wählen</h1>
      <p class="text-muted">
        Dieses Gerät wird persönlich ausgegeben und zurückgenommen. Bitte wählen Sie
        einen <strong>Abhol-</strong> und einen <strong>Rückgabe-Termin</strong>.
        <?php if ($ref !== ''): ?><br><small>Vorgang: <?= zhl_h($ref) ?></small><?php endif; ?>
      </p>

      <?php if ($base === '' || empty($members)): ?>
        <div class="alert alert-warning">
          Es konnten gerade keine Übergabe-Slots geladen werden. Bitte später erneut
          versuchen oder das ZHL-Team kontaktieren.
        </div>
      <?php endif; ?>

      <!-- Schritt 1+2: Termine buchen -->
      <?php foreach (['pickup' => '1. Abholung', 'return' => '2. Rückgabe'] as $type => $heading): ?>
        <div class="mb-4">
          <div class="d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-2"><?= zhl_h($heading) ?></h2>
            <?php if ($status[$type]): ?>
              <span class="badge bg-success">✓ gebucht</span>
            <?php else: ?>
              <span class="badge bg-secondary">offen</span>
            <?php endif; ?>
          </div>
          <div class="list-group">
            <?php foreach ($members as $m):
                $role = $m['handover_role'] ?? 'backup';
                $cls = $role === 'primary' ? 'role-primary' : 'role-backup'; ?>
              <a class="list-group-item list-group-item-action <?= $cls ?>"
                 href="<?= zhl_h(zhl_book_link($base, (int)$m['member_id'], $token, $type)) ?>"
                 target="_blank" rel="noopener">
                <span class="fw-semibold"><?= zhl_h($m['member_name']) ?></span>
                <?php if ($role === 'primary'): ?>
                  <span class="badge bg-success-subtle text-success-emphasis ms-1">Hilfskraft</span>
                <?php else: ?>
                  <span class="badge bg-light text-muted ms-1">Team (Backup)</span>
                <?php endif; ?>
                <span class="text-muted small d-block"><?= zhl_h($m['type_label']) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <!-- Schritt 3: Status aktualisieren -->
      <div class="d-flex gap-2 align-items-center mb-4">
        <a class="btn btn-outline-secondary"
           href="zhl-handover-select.php?action=sync&token=<?= urlencode($token) ?><?= $ref !== '' ? '&ref=' . urlencode($ref) : '' ?>">
          Status aktualisieren
        </a>
        <small class="text-muted">Nach dem Buchen beider Termine hier klicken.</small>
      </div>

      <!-- Schritt 4: Token übernehmen -->
      <?php $bothDone = $status['pickup'] && $status['return']; ?>
      <div class="alert <?= $bothDone ? 'alert-success' : 'alert-light border' ?>">
        <div class="fw-semibold mb-1">
          <?= $bothDone ? '✓ Übergabe terminiert' : 'Übergabe-Token' ?>
        </div>
        <p class="mb-2 small">
          Tragen Sie dieses Token im Reservierungsformular in das Feld
          <em>„Übergabe-Token (handover_token)"</em> ein:
        </p>
        <div class="token-box fs-5 p-2 bg-white border rounded"><?= zhl_h($token) ?></div>
        <?php if (!$bothDone): ?>
          <p class="text-muted small mt-2 mb-0">
            Die Reservierung lässt sich erst speichern, wenn <strong>Abholung und
            Rückgabe</strong> gebucht und bestätigt sind.
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>

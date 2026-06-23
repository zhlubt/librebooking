<?php
/**
 * ZHL Übergabe-Checkliste (Phase B, F10/F30).
 *
 * QR-verifizierte Aus-/Rückgabe: Das ZHL-Team (Admin) scannt einen ZHL-eigenen
 * QR-Code (Web/zhl-handover-qr.php) und landet hier. Die Seite lädt das Ressourcen-
 * Zubehör, erfasst je Position ok/fehlt/beschädigt + Notiz, dazu Gesamtzustand,
 * Zustandsnotiz und Unterschrift, und schreibt das Protokoll nach
 * zhl_handover_check (+ _item). Danach wird die Übergabe auf 'done' gesetzt.
 *
 * Bewusst eigene Seite (NICHT der native QR-Router, der auf die Reservierung zeigt) —
 * mit eigener Auth/CSRF/Permission. Nur fürs ZHL-Team (Admin-Rollen), nicht für User
 * (ZHL-Entscheidung). SecurePage + Admin-Check.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(__DIR__ . '/zhl-handover-lib.php');

class ZhlHandoverCheckPage extends SecurePage
{
    public function __construct()
    {
        parent::__construct('');
    }

    public function PageLoad()
    {
        $session = $this->server->GetUserSession();
        if (!$this->isStaff($session)) {
            http_response_code(403);
            echo 'Übergabe-Protokolle sind nur für das ZHL-Team (Admin) zugänglich.';
            return;
        }

        $pdo = zhl_handover_db();
        $ref = preg_replace('/[^A-Za-z0-9._-]/', '', (string)$this->GetQuerystring('ref'));
        $token = (string)$this->GetQuerystring('token');
        if (!zhl_handover_valid_token($token)) {
            $token = '';
        }
        $type = $this->GetQuerystring('type') === 'return' ? 'return' : 'pickup';
        $resourceId = (int)$this->GetQuerystring('resource');

        // Übergabe-Kontext auflösen (optional; Protokoll geht auch ohne).
        $handover = null;
        if ($ref !== '' || $token !== '') {
            $stmt = $pdo->prepare(
                'SELECT * FROM zhl_booking_handover
                 WHERE type = ? AND (reference_number = ? OR handover_token = ?)
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$type, $ref, $token]);
            $handover = $stmt->fetch() ?: null;
        }
        if ($resourceId === 0 && $handover && $handover['resource_id']) {
            $resourceId = (int)$handover['resource_id'];
        }

        $resourceName = '';
        if ($resourceId) {
            $s = $pdo->prepare('SELECT name FROM resources WHERE resource_id = ?');
            $s->execute([$resourceId]);
            $resourceName = (string)$s->fetchColumn();
        }
        $accessories = $resourceId ? zhl_handover_resource_accessories($resourceId) : [];

        $saved = false;
        $error = '';
        if ($this->IsPost() && $this->GetForm('action') === 'save') {
            $this->EnforceCSRFCheck();
            try {
                $this->Save($pdo, (int)$session->UserId, $ref, $token, $type, $resourceId, $accessories);
                $saved = true;
            } catch (Throwable $e) {
                Log::Error('ZhlHandoverCheck: %s', $e->getMessage());
                $error = 'Speichern fehlgeschlagen. Bitte erneut versuchen.';
            }
        }

        $this->Render($session, $ref, $token, $type, $resourceId, $resourceName, $accessories, $saved, $error);
    }

    private function isStaff($session): bool
    {
        return $session->IsAdmin || $session->IsResourceAdmin
            || $session->IsScheduleAdmin || $session->IsGroupAdmin;
    }

    private function Save($pdo, int $userId, string $ref, string $token, string $type, int $resourceId, array $accessories): void
    {
        $overall = in_array($this->GetForm('overall_condition'), ['ok', 'minor', 'damaged'], true)
            ? $this->GetForm('overall_condition') : null;
        $conditionNote = mb_substr(trim((string)$this->GetForm('condition_note')), 0, 60000);
        $signature = mb_substr(trim((string)$this->GetForm('signature_name')), 0, 255);
        $now = gmdate('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO zhl_handover_check
                    (handover_token, reference_number, resource_id, type, overall_condition,
                     checked_by_user_id, condition_note, signature_name, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $token !== '' ? $token : null,
                $ref !== '' ? $ref : null,
                $resourceId ?: null,
                $type,
                $overall,
                $userId,
                $conditionNote !== '' ? $conditionNote : null,
                $signature !== '' ? $signature : null,
                $now,
            ]);
            $checkId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO zhl_handover_check_item (check_id, accessory_id, label, state, note)
                 VALUES (?, ?, ?, ?, ?)'
            );

            // Strukturierte Zubehör-Positionen.
            $states = (array)$this->GetForm('state');
            $notes = (array)$this->GetForm('note');
            foreach ($accessories as $a) {
                $aid = (int)$a['accessory_id'];
                $rawState = $states[$aid] ?? 'ok';
                $state = in_array($rawState, ['ok', 'missing', 'damaged'], true) ? $rawState : 'ok';
                $note = mb_substr(trim((string)($notes[$aid] ?? '')), 0, 500);
                $itemStmt->execute([$checkId, $aid, mb_substr((string)$a['accessory_name'], 0, 255), $state, $note !== '' ? $note : null]);
            }

            // Ad-hoc-Positionen (ohne strukturiertes Accessory).
            $adLabels = (array)$this->GetForm('adhoc_label');
            $adStates = (array)$this->GetForm('adhoc_state');
            $adNotes = (array)$this->GetForm('adhoc_note');
            foreach ($adLabels as $i => $label) {
                $label = mb_substr(trim((string)$label), 0, 255);
                if ($label === '') {
                    continue;
                }
                $rawState = $adStates[$i] ?? 'ok';
                $state = in_array($rawState, ['ok', 'missing', 'damaged'], true) ? $rawState : 'ok';
                $note = mb_substr(trim((string)($adNotes[$i] ?? '')), 0, 500);
                $itemStmt->execute([$checkId, null, $label, $state, $note !== '' ? $note : null]);
            }

            // Übergabe als erledigt markieren (token- oder referenz-gebunden).
            if ($ref !== '' || $token !== '') {
                $upd = $pdo->prepare(
                    "UPDATE zhl_booking_handover SET status = 'done', updated_at = ?
                     WHERE type = ? AND (reference_number = ? OR handover_token = ?)"
                );
                $upd->execute([$now, $type, $ref, $token]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function Render($session, string $ref, string $token, string $type, int $resourceId, string $resourceName, array $accessories, bool $saved, string $error): void
    {
        $csrf = (string)$session->CSRFToken;
        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $typeLabel = $type === 'return' ? 'Rückgabe' : 'Abholung';
        $qs = 'ref=' . urlencode($ref) . '&token=' . urlencode($token) . '&type=' . urlencode($type) . '&resource=' . $resourceId;
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Übergabe-Protokoll — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .zhl-wrap { max-width: 800px; }
        .btn-zhl { background:#009260; border-color:#009260; color:#fff; }
        .btn-zhl:hover { background:#007a50; border-color:#007a50; color:#fff; }
        .state-damaged { color:#b02a37; } .state-missing { color:#b8860b; }
    </style>
</head>
<body>
<div class="container zhl-wrap py-4">
  <div class="card shadow-sm">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-start">
        <h1 class="h4 mb-1"><i class="bi bi-clipboard-check text-success"></i> Übergabe-Protokoll</h1>
        <span class="badge text-bg-secondary fs-6"><?= $h($typeLabel) ?></span>
      </div>
      <p class="text-muted mb-3">
        <?php if ($resourceName !== ''): ?><strong><?= $h($resourceName) ?></strong><?php else: ?>Gerät unbekannt<?php endif; ?>
        <?php if ($ref !== ''): ?> · Vorgang <?= $h($ref) ?><?php endif; ?>
      </p>

      <?php if ($saved): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i>
          Protokoll gespeichert. Die Übergabe ist als <strong>erledigt</strong> markiert.</div>
        <a class="btn btn-outline-secondary" href="zhl-handover-admin.php"><i class="bi bi-arrow-left"></i> Zur Übersicht</a>
      <?php else: ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>

        <form method="POST" action="zhl-handover-check.php?<?= $h($qs) ?>">
          <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
          <input type="hidden" name="action" value="save">

          <h2 class="h6 mt-2">Zubehör / Komponenten</h2>
          <?php if (empty($accessories)): ?>
            <p class="small text-muted">Kein strukturiertes Zubehör hinterlegt — bitte Positionen unten manuell erfassen.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead><tr><th>Position</th><th style="width:170px">Zustand</th><th>Notiz</th></tr></thead>
                <tbody>
                <?php foreach ($accessories as $a): $aid = (int)$a['accessory_id']; ?>
                  <tr>
                    <td><?= $h((string)$a['accessory_name']) ?></td>
                    <td>
                      <select name="state[<?= $aid ?>]" class="form-select form-select-sm">
                        <option value="ok">✓ ok</option>
                        <option value="missing">fehlt</option>
                        <option value="damaged">beschädigt</option>
                      </select>
                    </td>
                    <td><input type="text" name="note[<?= $aid ?>]" class="form-control form-control-sm" maxlength="500" placeholder="optional"></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <h2 class="h6 mt-3">Weitere Positionen (manuell)</h2>
          <div id="adhoc-rows" class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr><th>Bezeichnung</th><th style="width:170px">Zustand</th><th>Notiz</th></tr></thead>
              <tbody id="adhoc-body"></tbody>
            </table>
          </div>
          <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addAdhoc()"><i class="bi bi-plus"></i> Position hinzufügen</button>

          <h2 class="h6">Gesamtzustand</h2>
          <div class="mb-3">
            <div class="btn-group" role="group">
              <input type="radio" class="btn-check" name="overall_condition" id="oc-ok" value="ok" checked>
              <label class="btn btn-outline-success" for="oc-ok">✓ Einwandfrei</label>
              <input type="radio" class="btn-check" name="overall_condition" id="oc-minor" value="minor">
              <label class="btn btn-outline-warning" for="oc-minor">Gebrauchsspuren</label>
              <input type="radio" class="btn-check" name="overall_condition" id="oc-damaged" value="damaged">
              <label class="btn btn-outline-danger" for="oc-damaged">Beschädigt</label>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Zustandsnotiz</label>
            <textarea name="condition_note" class="form-control" rows="2" placeholder="z.B. Kratzer am Gehäuse, Akku bei 80 %"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Unterschrift / Name (Übergabe durch)</label>
            <input type="text" name="signature_name" class="form-control" maxlength="255" value="<?= $h(trim(($session->FirstName ?? '') . ' ' . ($session->LastName ?? ''))) ?>">
          </div>

          <button type="submit" class="btn btn-zhl"><i class="bi bi-check-lg"></i> Protokoll speichern &amp; Übergabe abschließen</button>
        </form>

        <script>
        function addAdhoc() {
          const b = document.getElementById('adhoc-body');
          const tr = document.createElement('tr');
          tr.innerHTML = `<td><input type="text" name="adhoc_label[]" class="form-control form-control-sm" maxlength="255" placeholder="z.B. Ladekabel"></td>`
            + `<td><select name="adhoc_state[]" class="form-select form-select-sm"><option value="ok">✓ ok</option><option value="missing">fehlt</option><option value="damaged">beschädigt</option></select></td>`
            + `<td><input type="text" name="adhoc_note[]" class="form-control form-control-sm" maxlength="500" placeholder="optional"></td>`;
          b.appendChild(tr);
        }
        addAdhoc();
        </script>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
        <?php
    }
}

$page = new ZhlHandoverCheckPage();
$page->PageLoad();

<?php
/**
 * ZHL — Sonderfreigabe für überlange Ausleihe ANFRAGEN (SPEC-AUSLEIHDAUER-LIMIT §6.3).
 *
 * Eingeloggter Nutzer, der am Dauer-Limit gescheitert ist, stellt hier eine begründete Anfrage:
 * gewünschtes Nutzungsfenster (Start/Ende) + Begründung. Speichert eine open-Anfrage
 * (zhl_dauer_ausnahme) und benachrichtigt das ZHL-Medien-Team. Die Genehmigung erfolgt im Admin
 * (zhl-dauer-ausnahme-admin.php); danach bucht der Nutzer selbst per Token-Link.
 *
 * SecurePage (Login nötig) + CSRF. Additive ZHL-Datei, kein Core.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlDauerAusnahme.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlSettings.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlTerminRequest.php');
require_once(ROOT_DIR . 'Presenters/ZhlTerminRequestEmail.php');

class ZhlDauerAusnahmePage extends SecurePage
{
    public function __construct()
    {
        parent::__construct('');
    }

    public function PageLoad()
    {
        $session = $this->server->GetUserSession();
        $db = ServiceLocator::GetDatabase();
        $tz = $session->Timezone !== '' ? $session->Timezone : 'Europe/Berlin';
        $h = fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

        $rid = (int)($_REQUEST['rid'] ?? 0);
        $res = $this->loadResource($db, $rid);
        if ($res === null) {
            http_response_code(404);
            echo 'Gerät nicht gefunden.';
            return;
        }
        $lim = $this->effectiveLimits($db, $rid);
        $projectTitle = trim((string)($_REQUEST['pt'] ?? ''));

        // Default-Fenster: aus Query (start/end) oder morgen + (Limit+1) Tage als Anhalt.
        $defStart = $this->validDate((string)($_GET['start'] ?? ''), $tz)
            ?? Date::Now()->ToTimezone($tz)->AddDays(1)->Format('Y-m-d');
        $defEnd = $this->validDate((string)($_GET['end'] ?? ''), $tz)
            ?? Date::Parse($defStart . ' 00:00:00', $tz)->AddDays($lim['nutzung'] + 1)->Format('Y-m-d');

        $flash = null;
        $flashErr = null;
        $sent = false;

        if ($this->IsPost()) {
            $this->EnforceCSRFCheck();
            $startStr = trim((string)($_POST['start_date'] ?? ''));
            $endStr = trim((string)($_POST['end_date'] ?? ''));
            $startT = trim((string)($_POST['start_time'] ?? '')) ?: '09:00';
            $endT = trim((string)($_POST['end_time'] ?? '')) ?: '17:00';
            $reason = trim((string)($_POST['reason'] ?? ''));
            $projectTitle = trim((string)($_POST['pt'] ?? $projectTitle));

            $validStart = $this->validDate($startStr, $tz);
            $validEnd = $this->validDate($endStr, $tz);
            if ($validStart === null || $validEnd === null) {
                $flashErr = 'Bitte ein gültiges Start- und Enddatum angeben.';
            } elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $startT) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $endT)) {
                $flashErr = 'Bitte gültige Uhrzeiten angeben.';
            } elseif (strcmp($endStr, $startStr) < 0 || ($endStr === $startStr && $endT <= $startT)) {
                $flashErr = 'Das Ende muss nach dem Beginn liegen.';
            } elseif (mb_strlen($reason) < 10) {
                $flashErr = 'Bitte begründe deinen Sonderfall (mindestens ein Satz).';
            } else {
                $defStart = $startStr;
                $defEnd = $endStr;
                // TAG-granular speichern (00:00 .. 23:59:59 lokal): die Fenster-Bindung beim Buchen vergleicht
                // Nutzungs-TAGE, nicht exakte Zeiten (Schedule-Bounds/endNextDay würden sonst nicht passen).
                $beginUtc = Date::Parse($startStr . ' 00:00:00', $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
                $endUtc = Date::Parse($endStr . ' 23:59:59', $tz)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
                $nutzungTage = (int)round((Date::Parse($endStr . ' 00:00:00', $tz)->Timestamp() - Date::Parse($startStr . ' 00:00:00', $tz)->Timestamp()) / 86400);
                $id = ZhlDauerAusnahme::Create($db, [
                    'user_id' => (int)$session->UserId,
                    'resource_id' => $rid,
                    'requested_begin_utc' => $beginUtc,
                    'requested_end_utc' => $endUtc,
                    'nutzung_tage' => max(0, $nutzungTage),
                    'puffer_vor_tage' => 0,
                    'puffer_nach_tage' => 0,
                    'reason' => $reason . ($projectTitle !== '' ? "\n\nProjekt: " . $projectTitle : ''),
                ]);
                if ($id <= 0) {
                    $flashErr = 'Die Anfrage konnte nicht gespeichert werden. Bitte später erneut versuchen.';
                } else {
                    $this->notifyTeam($db, $session, $res, $rid, $startStr, $endStr, $nutzungTage, $reason, $projectTitle);
                    $flash = 'Deine Sonderfreigabe-Anfrage ist beim ZHL-Medien-Team eingegangen. Du bekommst eine E-Mail, sobald sie bearbeitet wurde.';
                    $sent = true;
                }
            }
        }

        $csrf = (string)$session->CSRFToken;
        $resName = (string)($res['name'] ?? 'Gerät');
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sonderfreigabe anfragen — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>body{background:#f6f8f7;} .wrap{max-width:640px;}</style>
</head>
<body>
<div class="container wrap py-4">
  <h1 class="h4 mb-3"><i class="bi bi-hourglass-split text-success"></i> Längere Ausleihe anfragen</h1>
  <p class="text-muted">Gerät: <strong><?= $h($resName) ?></strong></p>

  <?php if ($flash !== null): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= $h($flash) ?></div>
  <?php endif; ?>
  <?php if ($flashErr !== null): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?= $h($flashErr) ?></div>
  <?php endif; ?>

  <?php if ($sent): ?>
    <a class="btn btn-outline-secondary btn-sm" href="zhl-dashboard.php"><i class="bi bi-arrow-left"></i> Zurück zur Übersicht</a>
  <?php else: ?>
    <div class="alert alert-light border small">
      <i class="bi bi-info-circle text-success"></i>
      Standardmäßig sind Ausleihen auf <strong><?= (int)$lim['nutzung'] ?> Tage</strong> Nutzung begrenzt
      (Abholung/Rückgabe je bis zu <?= (int)$lim['puffer'] ?> Tage Versatz). Wenn du das Gerät länger
      brauchst, beschreibe hier deinen Bedarf — das Team entscheidet individuell. <strong>Voraussetzung:</strong>
      das Gerät muss im Zeitraum verfügbar sein.
    </div>

    <form method="post" class="card shadow-sm">
      <div class="card-body">
        <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
        <input type="hidden" name="rid" value="<?= $rid ?>">
        <input type="hidden" name="pt" value="<?= $h($projectTitle) ?>">

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small mb-0">Von (Datum)</label>
            <input type="date" class="form-control form-control-sm" name="start_date" value="<?= $h($defStart) ?>" required>
          </div>
          <div class="col-6">
            <label class="form-label small mb-0">Uhrzeit</label>
            <input type="time" class="form-control form-control-sm" name="start_time" value="09:00">
          </div>
          <div class="col-6">
            <label class="form-label small mb-0">Bis (Datum)</label>
            <input type="date" class="form-control form-control-sm" name="end_date" value="<?= $h($defEnd) ?>" required>
          </div>
          <div class="col-6">
            <label class="form-label small mb-0">Uhrzeit</label>
            <input type="time" class="form-control form-control-sm" name="end_time" value="17:00">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label small mb-0">Begründung <span class="text-danger">*</span></label>
          <textarea class="form-control" name="reason" rows="4" maxlength="2000" required
            placeholder="Warum brauchst du das Gerät länger als üblich? (z. B. mehrtägige Exkursion, Projektphase …)"><?= $h(trim((string)($_POST['reason'] ?? ''))) ?></textarea>
        </div>

        <button class="btn btn-success"><i class="bi bi-send"></i> Anfrage absenden</button>
        <a class="btn btn-link text-muted" href="zhl-book.php?rid=<?= $rid ?>">Abbrechen</a>
      </div>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
        <?php
    }

    /** Aktive Ressource (id + Name). @return array|null */
    private function loadResource($db, int $rid): ?array
    {
        if ($rid <= 0) {
            return null;
        }
        $cmd = new AdHocCommand('SELECT resource_id, name FROM resources WHERE resource_id = @r AND status_id = 1');
        $cmd->AddParameter(new Parameter('@r', $rid));
        $reader = $db->Query($cmd);
        $row = $reader->GetRow();
        $reader->Free();
        return $row === false ? null : $row;
    }

    /** Effektive Limits: Gerät-Override (zhl_uebergabe) sonst globaler Default (zhl_settings). */
    private function effectiveLimits($db, int $rid): array
    {
        $maxN = null;
        $maxP = null;
        try {
            $cmd = new AdHocCommand('SELECT max_nutzung_tage, max_puffer_tage FROM zhl_uebergabe WHERE resource_id = @r LIMIT 1');
            $cmd->AddParameter(new Parameter('@r', $rid));
            $reader = $db->Query($cmd);
            $row = $reader->GetRow();
            $reader->Free();
            if ($row !== false) {
                $maxN = $row['max_nutzung_tage'] !== null ? (int)$row['max_nutzung_tage'] : null;
                $maxP = $row['max_puffer_tage'] !== null ? (int)$row['max_puffer_tage'] : null;
            }
        } catch (Throwable $e) {
            // Fallback auf globale Defaults.
        }
        $n = ($maxN !== null && $maxN > 0) ? $maxN : ZhlSettings::GetInt('ausleih_max_nutzung_tage', 14);
        $p = ($maxP !== null && $maxP >= 0) ? $maxP : ZhlSettings::GetInt('ausleih_max_puffer_tage', 5);
        return ['nutzung' => max(1, $n), 'puffer' => max(0, $p)];
    }

    private function validDate(string $s, string $tz): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) || !checkdate((int)substr($s, 5, 2), (int)substr($s, 8, 2), (int)substr($s, 0, 4))) {
            return null;
        }
        return $s;
    }

    /** Mail ans Medien-Team über die neue Anfrage. Empfänger: zhl_settings sonst Hauspost-Config. */
    private function notifyTeam($db, UserSession $session, array $res, int $rid, string $start, string $end, int $tage, string $reason, string $projectTitle): void
    {
        try {
            $recipient = trim(ZhlSettings::Get('ausleih_ausnahme_empfaenger', ''));
            if ($recipient === '') {
                $recipient = trim(ZhlTerminRequest::RecipientEmail());
            }
            if ($recipient === '') {
                return;
            }
            $userName = trim($session->FirstName . ' ' . $session->LastName);
            $resName = (string)($res['name'] ?? ('#' . $rid));
            $adminLink = $this->absoluteBase() . 'zhl-dauer-ausnahme-admin.php';
            $lines = [
                'Neue Sonderfreigabe-Anfrage (längere Ausleihe):', '',
                'Gerät:    ' . $resName,
                'Nutzer:   ' . ($userName !== '' ? $userName : (string)$session->Email) . ' <' . (string)$session->Email . '>',
                'Zeitraum: ' . $start . ' – ' . $end . ' (' . $tage . ' Tage Nutzung)',
                ($projectTitle !== '' ? 'Projekt:  ' . $projectTitle : ''),
                '', 'Begründung:', $reason, '',
                'Bearbeiten (genehmigen/ablehnen):', '  ' . $adminLink,
                '', 'ZHL Medienausleihe',
            ];
            $body = implode("\n", array_filter($lines, fn($l) => $l !== null));
            $to = [new EmailAddress($recipient, 'ZHL Medien')];
            $cc = [];
            if (trim((string)$session->Email) !== '') {
                $cc[] = new EmailAddress((string)$session->Email, $userName !== '' ? $userName : (string)$session->Email);
            }
            ServiceLocator::GetEmailService()->Send(new ZhlTerminRequestEmail($to, $cc, 'ZHL Medienausleihe — Sonderfreigabe angefragt: ' . $resName, $body));
        } catch (Throwable $e) {
            Log::Error('ZHL-DauerAusnahme: notifyTeam fehlgeschlagen: %s', $e);
        }
    }

    private function absoluteBase(): string
    {
        $url = rtrim((string)Configuration::Instance()->GetScriptUrl(), '/');
        return $url !== '' ? $url . '/' : '/Web/';
    }
}

$page = new ZhlDauerAusnahmePage();
$page->PageLoad();

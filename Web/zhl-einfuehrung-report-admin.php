<?php
/**
 * ZHL — Admin für den wöchentlichen Einführungs-/Übergabetermine-Report (2026-06-27).
 *
 * Pflegt die Schwellen (Mindest-Tage / Mindest-Termine pro Woche & Kategorie) + den Empfänger,
 * zeigt eine Live-Vorschau der Mail und kann eine Testmail an den eingeloggten Admin senden.
 * Der eigentliche Versand läuft montags früh über Jobs/zhl_einfuehrung_report.php (ZHL-Cron).
 *
 * Nur fürs ZHL-Team (Admin). SecurePage + Admin-Check + CSRF. Additive ZHL-Datei, kein Core.
 */

declare(strict_types=1);

define('ROOT_DIR', '../');
require_once(ROOT_DIR . 'Pages/SecurePage.php');
require_once(ROOT_DIR . 'lib/Email/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlSettings.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlEinfuehrungReport.php');
require_once(ROOT_DIR . 'Presenters/ZhlEinfuehrungReportEmail.php');
require_once(__DIR__ . '/zhl-audit-lib.php');

class ZhlEinfuehrungReportAdminPage extends SecurePage
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

        $h = fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $flash = null;
        $flashErr = null;
        $previewHtml = null;

        if ($this->IsPost()) {
            $this->EnforceCSRFCheck();
            $action = $this->GetForm('action');

            if ($action === 'save') {
                $minDays = (int)($_POST['min_days'] ?? 1);
                $minAppts = (int)($_POST['min_appts'] ?? 1);
                $recipient = trim((string)($_POST['recipient'] ?? ''));
                if ($minDays < 0 || $minDays > 14 || $minAppts < 0 || $minAppts > 100) {
                    $flashErr = 'Bitte plausible Schwellen angeben (Tage 0–14, Termine 0–100).';
                } elseif ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $flashErr = 'Bitte eine gültige Empfänger-E-Mail angeben.';
                } else {
                    ZhlSettings::Set('einf_report_min_days', (string)$minDays);
                    ZhlSettings::Set('einf_report_min_appts', (string)$minAppts);
                    ZhlSettings::Set('einf_report_recipient', $recipient);
                    $flash = 'Einstellungen gespeichert.';
                    zhl_audit_log(array_merge(zhl_audit_actor($session), [
                        'action' => 'einf_report.save',
                        'entity_type' => 'config',
                        'detail' => ['min_days' => $minDays, 'min_appts' => $minAppts, 'recipient' => $recipient],
                    ]));
                }
            } elseif ($action === 'preview') {
                $previewHtml = ZhlEinfuehrungReport::RenderHtml(ZhlEinfuehrungReport::Build(4));
            } elseif ($action === 'test') {
                $to = trim((string)$session->Email);
                if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    $flashErr = 'Deine Konto-E-Mail ist ungültig — Testmail nicht möglich.';
                } else {
                    try {
                        $report = ZhlEinfuehrungReport::Build(4);
                        $html = ZhlEinfuehrungReport::RenderHtml($report);
                        $subj = '[TEST] ZHL Einführungs-/Übergabetermine — ' . $report['windowLabel'];
                        ServiceLocator::GetEmailService()->Send(new ZhlEinfuehrungReportEmail([new EmailAddress($to)], $subj, $html));
                        $flash = 'Testmail an ' . $to . ' gesendet.';
                        $previewHtml = $html;
                    } catch (Throwable $ex) {
                        $flashErr = 'Testmail fehlgeschlagen: ' . $ex->getMessage();
                    }
                }
            }
        }

        $minDays = ZhlSettings::GetInt('einf_report_min_days', 1);
        $minAppts = ZhlSettings::GetInt('einf_report_min_appts', 1);
        $recipient = ZhlSettings::Get('einf_report_recipient', 'zhlmedien@uni-bayreuth.de');
        $csrf = (string)$session->CSRFToken;
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termine-Report — ZHL Medienausleihe</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/5.3.3/css/bootstrap.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/1.11.3/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/zhl-theme.css">
    <style>
        body { background:#f6f8f7; }
        .savebar { position:sticky; top:0; z-index:20; background:#fff; border-bottom:1px solid #e3eae6; }
        .preview-frame { width:100%; height:900px; border:1px solid #e3eae6; border-radius:10px; background:#eef1f0; }
    </style>
</head>
<body>
<div class="savebar py-2 mb-3">
  <div class="container d-flex justify-content-between align-items-center" style="max-width:1000px">
    <h1 class="h5 mb-0"><i class="bi bi-calendar-week text-success"></i> Termine-Report (wöchentlich)</h1>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="zhl-uebergabe-admin.php"><i class="bi bi-truck"></i> Übergabe-Matrix</a>
      <a class="btn btn-outline-secondary btn-sm" href="zhl-typeinfo-admin.php"><i class="bi bi-info-circle"></i> Geräte-Infos</a>
      <a class="btn btn-outline-secondary btn-sm" href="zhl-dashboard.php"><i class="bi bi-grid"></i> Dashboard</a>
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
    <b>Was macht dieser Report?</b> Jeden <b>Montag früh (08:00)</b> geht eine Übersicht an den Empfänger:
    wie viele buchbare Termine je Kategorie (Einführung in Medien, Einführung ins Videostudio, Übergabe Medien)
    für die <b>laufende Woche + die nächsten 3</b> vorhanden sind, wer anbietet und wie die Woche abgedeckt ist.
    Unterschreitet eine Woche das Mindestziel, wird das deutlich markiert.
  </div>

  <form method="post" action="zhl-einfuehrung-report-admin.php" class="card shadow-sm mb-3">
    <input type="hidden" name="<?= FormKeys::CSRF_TOKEN ?>" value="<?= $h($csrf) ?>">
    <div class="card-body">
      <h2 class="h6 mb-3">Mindestziel pro Woche &amp; Kategorie</h2>
      <div class="row g-3 align-items-end">
        <div class="col-sm-3">
          <label class="form-label small">Mindest-Tage / Woche</label>
          <input class="form-control" type="number" name="min_days" min="0" max="14" value="<?= (int)$minDays ?>">
        </div>
        <div class="col-sm-3">
          <label class="form-label small">Mindest-Termine / Woche</label>
          <input class="form-control" type="number" name="min_appts" min="0" max="100" value="<?= (int)$minAppts ?>">
        </div>
        <div class="col-sm-6">
          <label class="form-label small">Empfänger der Mail</label>
          <input class="form-control" type="email" name="recipient" value="<?= $h($recipient) ?>" placeholder="zhlmedien@uni-bayreuth.de">
        </div>
      </div>
      <div class="form-text mt-2">Gilt je Kategorie: Eine Woche ist „zu wenig", wenn die Tage <em>oder</em> die Termine darunter liegen.</div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between">
      <div class="d-flex gap-2">
        <button class="btn btn-outline-success" type="submit" name="action" value="preview"><i class="bi bi-eye"></i> Vorschau (Live-Daten)</button>
        <button class="btn btn-outline-primary" type="submit" name="action" value="test"><i class="bi bi-envelope"></i> Testmail an mich</button>
      </div>
      <button class="btn btn-success" type="submit" name="action" value="save"><i class="bi bi-save"></i> Speichern</button>
    </div>
  </form>

  <?php if ($previewHtml !== null): ?>
    <h2 class="h6 mb-2"><i class="bi bi-eye text-success"></i> Vorschau (echte Terminplaner-Daten)</h2>
    <iframe class="preview-frame" srcdoc="<?= $h($previewHtml) ?>"></iframe>
  <?php endif; ?>

</div>
<script src="assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
        <?php
    }
}

$page = new ZhlEinfuehrungReportAdminPage();
$page->PageLoad();

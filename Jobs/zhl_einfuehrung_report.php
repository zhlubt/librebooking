<?php

/**
 * ZHL — wöchentlicher Verfügbarkeits-Report für Einführungs-/Übergabetermine.
 *
 * Schickt montags früh (ab 08:00 Europe/Berlin, einmal pro Montag) eine HTML-Übersicht an den
 * konfigurierten Empfänger (Default zhlmedien@uni-bayreuth.de): wie viele buchbare Termine je
 * Kategorie für die laufende Woche + die nächsten 3 vorhanden sind, gegen das (admin-pflegbare)
 * Mindestziel, wer anbietet, plus Abdeckungs-Heatmap.
 *
 * Läuft über den ZHL-Cron-Runner (Web/zhl-cron.php, alle 5 Min). Der Versand-Guard hier sorgt für
 * „genau einmal montags". CLI-only (JobCop). Manuell testbar:
 *   php -f Jobs/zhl_einfuehrung_report.php -- --force            (sofort an den echten Empfänger)
 *   php -f Jobs/zhl_einfuehrung_report.php -- --force --to=me@x  (sofort an eine Test-Adresse)
 *
 * --force umgeht Wochentag/Uhrzeit/last-sent-Guard und setzt den last-sent-Marker NICHT
 * (verhindert nicht den echten Montags-Versand).
 */

define('ROOT_DIR', __DIR__ . '/../');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Jobs/JobCop.php');
require_once(ROOT_DIR . 'lib/Email/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlSettings.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlEinfuehrungReport.php');
require_once(ROOT_DIR . 'Presenters/ZhlEinfuehrungReportEmail.php');

JobCop::EnsureCommandLine();

$argv = $_SERVER['argv'] ?? [];
$force = in_array('--force', $argv, true);
$toOverride = '';
foreach ($argv as $a) {
    if (strpos($a, '--to=') === 0) {
        $toOverride = trim(substr($a, 5));
    }
}

$tz = new DateTimeZone('Europe/Berlin');
$now = new DateTimeImmutable('now', $tz);
$today = $now->format('Y-m-d');

echo 'zhl_einfuehrung_report ' . $now->format('Y-m-d H:i') . " (Europe/Berlin)\n";

// --- Versand-Guard: montags ab 08:00, genau einmal pro Montag (außer --force). ---
if (!$force) {
    if ((int)$now->format('N') !== 1 || (int)$now->format('G') < 8) {
        echo "skip: nicht Montag >= 08:00\n";
        return;
    }
    if (ZhlSettings::Get('einf_report_last_sent', '') === $today) {
        echo "skip: heute bereits versendet\n";
        return;
    }
}

$report = ZhlEinfuehrungReport::Build(4);
$html = ZhlEinfuehrungReport::RenderHtml($report);

$recipient = $toOverride !== '' ? $toOverride : (string)$report['recipient'];
if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    echo 'abort: ungültiger Empfänger (' . $recipient . ")\n";
    return;
}

$statusTag = !empty($report['weeksUnder']) ? ' (Achtung: Lücken)' : ' (ok)';
$subject = 'ZHL Einführungs-/Übergabetermine — ' . $report['windowLabel'] . $statusTag;

$emailEnabled = Configuration::Instance()->GetKey(ConfigKeys::EMAIL_ENABLED, new BooleanConverter());
if (!$emailEnabled) {
    echo "abort: E-Mail-Versand ist in der Konfiguration deaktiviert (EMAIL_ENABLED)\n";
    return;
}

try {
    ServiceLocator::GetEmailService()->Send(new ZhlEinfuehrungReportEmail([new EmailAddress($recipient)], $subject, $html));
} catch (Throwable $e) {
    Log::Error('ZHL-Einführungs-Report: Versand fehlgeschlagen (%s): %s', $recipient, $e);
    echo 'error: Versand fehlgeschlagen: ' . $e->getMessage() . "\n";
    return;
}

if (!$force) {
    ZhlSettings::Set('einf_report_last_sent', $today);
}

echo 'sent: an ' . $recipient . ' · ' . $report['windowLabel']
    . ' · Wochen unter Ziel: ' . (count($report['weeksUnder']) ?: 0)
    . ' · Anbieter: ' . count($report['offerers'])
    . ($force ? ' · (force, last_sent unverändert)' : '') . "\n";

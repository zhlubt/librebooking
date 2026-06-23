<?php
/**
 * ZHL Web-Cron-Runner.
 *
 * Der Live-Container hat keinen Cron. Dieser token-geschützte Endpunkt führt die
 * LibreBooking-Jobs als CLI-Prozesse aus (die Jobs verlangen via JobCop CLI, daher
 * exec statt include). Ein EXTERNER Scheduler (launchd / Gaming-PC / cron-Dienst)
 * ruft diese URL z.B. alle 5 Minuten auf:
 *
 *   curl -fsS "https://buchung.zhl-ubt.de/Web/zhl-cron.php?token=<SECRET>"
 *
 * Token: aus config/zhl-cron.php ($zhlCronToken) ODER Env ZHL_CRON_TOKEN.
 * Upgrade-sicher: eigene ZHL-Datei, kein Core-Edit.
 */

declare(strict_types=1);

define('ZHL_ROOT', dirname(__DIR__));

// --- Token laden (nicht im Repo) ---
$expected = getenv('ZHL_CRON_TOKEN') ?: '';
$tokenFile = ZHL_ROOT . '/config/zhl-cron.php';
if ($expected === '' && is_readable($tokenFile)) {
    $zhlCronToken = '';
    require $tokenFile;          // setzt $zhlCronToken
    $expected = (string)$zhlCronToken;
}

$provided = (string)($_GET['token'] ?? $_SERVER['HTTP_X_ZHL_CRON_TOKEN'] ?? '');

header('Content-Type: text/plain; charset=utf-8');

if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo "forbidden\n";
    exit;
}

// --- Auszuführende Jobs (feste Liste, KEIN User-Input in exec) ---
$jobs = [
    'sendreminders.php',
    'sendwaitlist.php',
    'sendmissedcheckin.php',
    'sendseriesend.php',
    'autorelease.php',
    'zhl_overdue.php',   // ZHL Phase C: Overdue-/Rückgabe-Eskalation (F34)
    // 'sessioncleanup.php', 'deleteolddata.php'  -> seltener, separat planen
];

$php = PHP_BINARY ?: 'php';
$ranAt = gmdate('Y-m-d H:i:s') . ' UTC';
echo "zhl-cron $ranAt\n";

foreach ($jobs as $job) {
    $script = ZHL_ROOT . '/Jobs/' . $job;
    if (!is_file($script)) {
        echo "- $job: MISSING\n";
        continue;
    }
    $cmd = escapeshellarg($php) . ' -f ' . escapeshellarg($script) . ' 2>&1';
    $out = [];
    $code = 0;
    @exec($cmd, $out, $code);
    echo '- ' . $job . ': exit ' . $code . (count($out) ? ' | ' . trim(end($out)) : '') . "\n";
}

echo "done\n";

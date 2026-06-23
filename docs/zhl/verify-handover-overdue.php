<?php
/**
 * Verifiziert die Logik von Jobs/zhl_overdue.php (Phase C) gegen die lokale Dev-DB —
 * Erkennung überfälliger Rückgaben + Eskalationsstufe — ohne Mailversand. Spiegelt die
 * Job-Queries/Schwellen und prüft die Entscheidungsfälle. Seedet temporär, räumt auf.
 *
 * Lauf:  php docs/zhl/verify-handover-overdue.php
 */

declare(strict_types=1);

require __DIR__ . '/../../Web/zhl-handover-lib.php';

const GRACE_HOURS = 24;
const STAGE_DAYS = [1, 3, 7];

$pdo = zhl_handover_db();
$pass = 0;
$fail = 0;
function check(string $label, bool $cond): void
{
    global $pass, $fail;
    echo ($cond ? '  PASS  ' : '  FAIL  ') . $label . "\n";
    $cond ? $pass++ : $fail++;
}

// Stufe wie im Job: höchste Schwelle, die erreicht ist.
function targetStage(int $daysOverdue): int
{
    $stage = 0;
    foreach (STAGE_DAYS as $i => $threshold) {
        if ($daysOverdue >= $threshold) {
            $stage = $i + 1;
        }
    }
    return $stage;
}

// --- Seed: drei Rückgaben mit unterschiedlicher Überfälligkeit ---
$tok = 'ovTEST' . substr(bin2hex(random_bytes(4)), 0, 8);
$mk = function (string $suffix, string $interval) use ($pdo, $tok) {
    $pdo->prepare(
        "INSERT INTO zhl_booking_handover (handover_token, type, reference_number, resource_id, status, scheduled_start_utc, scheduled_end_utc, created_at, updated_at)
         VALUES (?, 'return', ?, 5, 'confirmed', UTC_TIMESTAMP() - INTERVAL $interval, UTC_TIMESTAMP() - INTERVAL $interval, UTC_TIMESTAMP(), UTC_TIMESTAMP())"
    )->execute([$tok . $suffix, 'OVREF-' . $suffix]);
    return (int)$pdo->lastInsertId();
};
$idGrace = $mk('grace', '12 HOUR');  // innerhalb Toleranz (12h < 24h)
$idS1 = $mk('s1', '2 DAY');          // 2 Tage → Stufe 1
$idS3 = $mk('s3', '8 DAY');          // 8 Tage → Stufe 3
// Ein bereits erledigter (done) darf NIE auftauchen:
$idDone = $mk('done', '9 DAY');
$pdo->prepare("UPDATE zhl_booking_handover SET status='done' WHERE id=?")->execute([$idDone]);

// --- Detection-Query wie im Job ---
$detect = $pdo->prepare(
    "SELECT id, scheduled_end_utc FROM zhl_booking_handover
     WHERE type='return' AND status IN ('requested','confirmed')
       AND scheduled_end_utc IS NOT NULL
       AND scheduled_end_utc < (UTC_TIMESTAMP() - INTERVAL ? HOUR)
       AND handover_token LIKE ?"
);
$detect->execute([GRACE_HOURS, $tok . '%']);
$detected = [];
foreach ($detect->fetchAll() as $r) {
    $days = (int)floor((time() - strtotime($r['scheduled_end_utc'] . ' UTC')) / 86400);
    $detected[(int)$r['id']] = targetStage($days);
}

echo "Overdue-Eskalation Verifikation\n";
check('innerhalb Toleranz (12h) NICHT erkannt', !isset($detected[$idGrace]));
check('erledigte Rückgabe NICHT erkannt', !isset($detected[$idDone]));
check('2 Tage überfällig -> erkannt, Stufe 1', ($detected[$idS1] ?? -1) === 1);
check('8 Tage überfällig -> erkannt, Stufe 3', ($detected[$idS3] ?? -1) === 3);

// --- Versand-Protokoll + Doppel-Schutz (uq_handover_stage) ---
$now = gmdate('Y-m-d H:i:s');
$pdo->prepare('INSERT INTO zhl_overdue_notice (handover_id, handover_token, stage, sent_at) VALUES (?, ?, 3, ?)')
    ->execute([$idS3, $tok . 's3', $now]);
$dup = false;
try {
    $pdo->prepare('INSERT INTO zhl_overdue_notice (handover_id, handover_token, stage, sent_at) VALUES (?, ?, 3, ?)')
        ->execute([$idS3, $tok . 's3', $now]);
} catch (Throwable $e) {
    $dup = true;
}
check('gleiche Stufe doppelt -> durch Unique verhindert', $dup === true);

// maxSent=3, targetStage=3 → nichts mehr zu senden
$maxSent = (int)$pdo->query("SELECT MAX(stage) FROM zhl_overdue_notice WHERE handover_id=$idS3")->fetchColumn();
check('Stufe 3 bereits versandt -> kein erneuter Versand', targetStage(8) <= $maxSent);

// --- Cleanup ---
$pdo->prepare('DELETE FROM zhl_overdue_notice WHERE handover_token LIKE ?')->execute([$tok . '%']);
$pdo->prepare('DELETE FROM zhl_booking_handover WHERE handover_token LIKE ?')->execute([$tok . '%']);

echo "\n$pass PASS / $fail FAIL\n";
exit($fail === 0 ? 0 : 1);

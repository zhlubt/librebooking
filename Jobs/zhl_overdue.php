<?php
/**
 * ZHL Rückgabe-Erinnerungen / Mahnungen (Mail-Umsetzung 2026-06-27).
 *
 * Zwei Pässe über die Rückgaben (zhl_booking_handover, type='return', noch nicht 'done'):
 *
 *  A) VORTAG-ERINNERUNG (Stufe 1, freundlich, AUTOMATISCH): Rückgabe ist morgen → Mail sofort raus,
 *     in zhl_rueckgabe_mahnung als status='sent' protokolliert (Unique handover+stage = einmalig).
 *
 *  B) ÜBERFÄLLIG (Stufe 2 „deutlich", Stufe 3 „letzte"): wird NICHT automatisch versandt, sondern als
 *     status='pending' in die Freigabe-Queue eingereiht. Ein Admin gibt jede Mahnung einzeln frei
 *     (zhl-mahnungen-admin.php) — das Gerät könnte längst zurück sein, nur unbestätigt.
 *
 * Läuft über den ZHL-Cron-Runner (Web/zhl-cron.php). CLI-only (JobCop). Cron-frei testbar:
 *   php -f Jobs/zhl_overdue.php
 */

define('ROOT_DIR', __DIR__ . '/../');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Jobs/JobCop.php');
require_once(ROOT_DIR . 'lib/Email/namespace.php');
require_once(ROOT_DIR . 'Presenters/ZhlReturnMail.php');

JobCop::EnsureCommandLine();

// --- Konfiguration (ZHL-eigen) ---
const ZHL_RETURN_GRACE_HOURS = 24;     // Toleranz nach geplantem Rückgabe-Ende, bevor „überfällig"
const ZHL_RETURN_DEUTLICH_DAYS = 2;    // Tage überfällig → Stufe 2 (deutlich) einreihen
const ZHL_RETURN_LETZTE_DAYS = 6;      // Tage überfällig → Stufe 3 (letzte) einreihen
const ZHL_RETURN_CONTACT = 'zhlmedien@uni-bayreuth.de';

/** Empfänger (user) zu einer Rückgabe über das Handover-Token auflösen. @return array|null */
function zhl_return_recipient($db, $token)
{
    if (empty($token)) {
        return null;
    }
    $cmd = new AdHocCommand(
        'SELECT u.user_id, u.email, u.fname, u.lname, u.language ' .
        'FROM zhl_handover_token t JOIN users u ON u.user_id = t.user_id ' .
        'WHERE t.handover_token = @token'
    );
    $cmd->AddParameter(new Parameter('@token', $token));
    $reader = $db->Query($cmd);
    $row = $reader->GetRow() ?: null;
    $reader->Free();
    return ($row && !empty($row['email'])) ? $row : null;
}

Log::Debug('Running zhl_overdue.php (Rückgabe-Erinnerungen)');

try {
    $emailEnabled = Configuration::Instance()->GetKey(ConfigKeys::EMAIL_ENABLED, new BooleanConverter());
    $db = ServiceLocator::GetDatabase();
    $now = Date::Now();
    $tzName = Configuration::Instance()->GetKey(ConfigKeys::DEFAULT_TIMEZONE) ?: 'Europe/Berlin';

    // ===================== A) VORTAG-ERINNERUNG (automatisch) =====================
    // Rückgaben, deren geplantes Ende MORGEN (lokaler Tag) liegt.
    $tomorrowStartLocal = $now->ToTimezone($tzName)->AddDays(1)->GetDate(); // morgen 00:00 lokal
    $tomorrowStartUtc = $tomorrowStartLocal->ToUtc();
    $tomorrowEndUtc = $tomorrowStartLocal->AddDays(1)->ToUtc();             // übermorgen 00:00 lokal

    $vorCmd = new AdHocCommand(
        "SELECT h.id, h.handover_token, h.reference_number, h.resource_id, h.scheduled_end_utc, " .
        "r.name AS resource_name FROM zhl_booking_handover h " .
        "LEFT JOIN resources r ON r.resource_id = h.resource_id " .
        "WHERE h.type = 'return' AND h.status IN ('requested','confirmed') " .
        "AND h.scheduled_end_utc IS NOT NULL " .
        "AND h.scheduled_end_utc >= @from AND h.scheduled_end_utc < @to"
    );
    $vorCmd->AddParameter(new Parameter('@from', $tomorrowStartUtc->ToDatabase()));
    $vorCmd->AddParameter(new Parameter('@to', $tomorrowEndUtc->ToDatabase()));
    $vorRows = [];
    $reader = $db->Query($vorCmd);
    while ($row = $reader->GetRow()) {
        $vorRows[] = $row;
    }
    $reader->Free();

    foreach ($vorRows as $row) {
        $handoverId = (int)$row['id'];
        if (zhl_mahnung_exists($db, $handoverId, ZhlReturnMail::STAGE_REMINDER)) {
            continue;
        }
        $user = zhl_return_recipient($db, $row['handover_token']);
        if (!$user) {
            continue;
        }
        $resourceName = $row['resource_name'] ?: 'Gerät';
        $name = trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? ''));
        $dueLabel = Date::Parse((string)$row['scheduled_end_utc'], 'UTC')->ToTimezone($tzName)->Format('d.m.Y');

        // Stufe 1 atomar beanspruchen (status='sent'); Duplicate-Key → parallel schon gesendet.
        if (!zhl_mahnung_claim($db, $handoverId, ZhlReturnMail::STAGE_REMINDER, $row, $user, $resourceName, 'sent', $now)) {
            continue;
        }
        if ($emailEnabled) {
            try {
                ServiceLocator::GetEmailService()->Send(new ZhlReturnMail(
                    new EmailAddress($user['email'], new FullName($name, '')),
                    ZhlReturnMail::STAGE_REMINDER,
                    $name,
                    $resourceName,
                    $dueLabel,
                    ZHL_RETURN_CONTACT,
                    $user['language'] ?? null
                ));
            } catch (Exception $mailEx) {
                Log::Error('zhl_overdue: Vortag-Mail fehlgeschlagen (handover %s): %s', $handoverId, $mailEx->getMessage());
                zhl_mahnung_unclaim($db, $handoverId, ZhlReturnMail::STAGE_REMINDER);
                continue;
            }
        }
        Log::Debug('zhl_overdue: Vortag-Erinnerung an %s (handover %s)', $user['email'], $handoverId);
    }

    // ===================== B) ÜBERFÄLLIG → FREIGABE-QUEUE (kein Auto-Versand) =====================
    $cutoff = $now->AddHours(-ZHL_RETURN_GRACE_HOURS)->ToDatabase();
    $ovCmd = new AdHocCommand(
        "SELECT h.id, h.handover_token, h.reference_number, h.resource_id, h.scheduled_end_utc, " .
        "r.name AS resource_name FROM zhl_booking_handover h " .
        "LEFT JOIN resources r ON r.resource_id = h.resource_id " .
        "WHERE h.type = 'return' AND h.status IN ('requested','confirmed') " .
        "AND h.scheduled_end_utc IS NOT NULL AND h.scheduled_end_utc < @cutoff"
    );
    $ovCmd->AddParameter(new Parameter('@cutoff', $cutoff));
    $ovRows = [];
    $reader = $db->Query($ovCmd);
    while ($row = $reader->GetRow()) {
        $ovRows[] = $row;
    }
    $reader->Free();

    $nowTs = (new DateTime($now->ToDatabase(), new DateTimeZone('UTC')))->getTimestamp();
    foreach ($ovRows as $row) {
        $handoverId = (int)$row['id'];
        $dueTs = (new DateTime($row['scheduled_end_utc'], new DateTimeZone('UTC')))->getTimestamp();
        $daysOverdue = (int)floor(($nowTs - $dueTs) / 86400);

        // Höchste erreichte überfällige Stufe (2 deutlich, 3 letzte). Mitte entfällt.
        $targetStage = 0;
        if ($daysOverdue >= ZHL_RETURN_DEUTLICH_DAYS) {
            $targetStage = ZhlReturnMail::STAGE_OVERDUE;
        }
        if ($daysOverdue >= ZHL_RETURN_LETZTE_DAYS) {
            $targetStage = ZhlReturnMail::STAGE_FINAL;
        }
        if ($targetStage === 0 || zhl_mahnung_exists($db, $handoverId, $targetStage)) {
            continue;
        }
        $user = zhl_return_recipient($db, $row['handover_token']);
        if (!$user) {
            continue;
        }
        $resourceName = $row['resource_name'] ?: 'Gerät';
        // Als 'pending' einreihen (KEIN Versand) — Admin gibt frei.
        if (zhl_mahnung_claim($db, $handoverId, $targetStage, $row, $user, $resourceName, 'pending', $now)) {
            // „Höchste erreichte Stufe": eine bereits offene NIEDRIGERE Stufe (z. B. Stufe 2) wird durch die
            // höhere ersetzt → nicht doppelt anzeigen/versenden. Bereits gesendete Stufen bleiben unberührt.
            $sup = new AdHocCommand(
                "UPDATE zhl_rueckgabe_mahnung SET status = 'dismissed' " .
                "WHERE handover_id = @hid AND status = 'pending' AND stage < @stage"
            );
            $sup->AddParameter(new Parameter('@hid', $handoverId));
            $sup->AddParameter(new Parameter('@stage', $targetStage));
            $db->Execute($sup);
        }
        Log::Debug('zhl_overdue: Stufe %s zur Freigabe eingereiht (handover %s, %s Tage überfällig)',
            $targetStage, $handoverId, $daysOverdue);
    }
} catch (Exception $ex) {
    Log::Error('Error running zhl_overdue.php: %s', $ex);
}

/** Existiert für (handover,stage) schon eine Zeile (jeglicher Status)? */
function zhl_mahnung_exists($db, int $handoverId, int $stage): bool
{
    $cmd = new AdHocCommand('SELECT 1 FROM zhl_rueckgabe_mahnung WHERE handover_id = @hid AND stage = @stage');
    $cmd->AddParameter(new Parameter('@hid', $handoverId));
    $cmd->AddParameter(new Parameter('@stage', $stage));
    $reader = $db->Query($cmd);
    $row = $reader->GetRow();
    $reader->Free();
    // GetRow() liefert bei leerem Ergebnis NULL (nicht false) → auf echten Treffer prüfen.
    return is_array($row);
}

/** (handover,stage) atomar einreihen (Unique). @return bool false bei Duplicate-Key (parallel). */
function zhl_mahnung_claim($db, int $handoverId, int $stage, array $row, array $user, string $resourceName, string $status, $now): bool
{
    $name = trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? ''));
    $cmd = new AdHocCommand(
        'INSERT INTO zhl_rueckgabe_mahnung ' .
        '(handover_id, stage, reference_number, resource_name, recipient_email, recipient_name, recipient_user_id, ' .
        'language, due_utc, status, created_at, sent_at) ' .
        'VALUES (@hid, @stage, @ref, @res, @email, @name, @uid, @lang, @due, @status, @created, @sent)'
    );
    $cmd->AddParameter(new Parameter('@hid', $handoverId));
    $cmd->AddParameter(new Parameter('@stage', $stage));
    $cmd->AddParameter(new Parameter('@ref', $row['reference_number'] ?? null));
    $cmd->AddParameter(new Parameter('@res', mb_substr($resourceName, 0, 190)));
    $cmd->AddParameter(new Parameter('@email', mb_substr((string)$user['email'], 0, 190)));
    $cmd->AddParameter(new Parameter('@name', mb_substr($name, 0, 190)));
    $cmd->AddParameter(new Parameter('@uid', (int)$user['user_id']));
    $cmd->AddParameter(new Parameter('@lang', $user['language'] ?? null));
    $cmd->AddParameter(new Parameter('@due', $row['scheduled_end_utc'] ?? null));
    $cmd->AddParameter(new Parameter('@status', $status));
    $cmd->AddParameter(new Parameter('@created', $now->ToDatabase()));
    $cmd->AddParameter(new Parameter('@sent', $status === 'sent' ? $now->ToDatabase() : null));
    try {
        $db->Execute($cmd);
        return true;
    } catch (Exception $e) {
        return false; // Duplicate-Key = bereits beansprucht
    }
}

/** Anspruch zurücknehmen (Mailversand fehlgeschlagen → Retry im nächsten Lauf). */
function zhl_mahnung_unclaim($db, int $handoverId, int $stage): void
{
    $cmd = new AdHocCommand('DELETE FROM zhl_rueckgabe_mahnung WHERE handover_id = @hid AND stage = @stage');
    $cmd->AddParameter(new Parameter('@hid', $handoverId));
    $cmd->AddParameter(new Parameter('@stage', $stage));
    $db->Execute($cmd);
}

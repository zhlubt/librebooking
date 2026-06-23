<?php
/**
 * ZHL Übergabe-Modul Phase C — Overdue-/Rückgabe-Eskalation (F34).
 *
 * Findet überfällige RÜCKGABEN (zhl_booking_handover type='return', noch nicht 'done',
 * geplantes Ende + Toleranz überschritten) und eskaliert in mehreren Stufen per E-Mail.
 * Jede Stufe wird in zhl_overdue_notice protokolliert (Unique handover_id+stage) → keine
 * Doppel-Mails; pro Lauf wird höchstens die nächste fällige Stufe versandt.
 *
 * Läuft über den ZHL-Cron-Runner (Web/zhl-cron.php) — der Container hat keinen Cron.
 * CLI-only (JobCop). Optionales User-Sperren in der Schlussstufe ist standardmäßig AUS.
 *
 * Cron-frei testbar: Migration 005 + ein überfälliger return-Datensatz, dann
 *   php -f Jobs/zhl_overdue.php
 */

define('ROOT_DIR', __DIR__ . '/../');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Jobs/JobCop.php');
require_once(ROOT_DIR . 'lib/Email/namespace.php');

JobCop::EnsureCommandLine();

// --- Konfiguration (bewusst im Job, ZHL-eigen) ---
const ZHL_OVERDUE_GRACE_HOURS = 24;          // Toleranz nach geplantem Rückgabe-Ende
const ZHL_OVERDUE_STAGE_DAYS = [1, 3, 7];    // Tage-überfällig-Schwellen je Stufe (1..3)
const ZHL_OVERDUE_LOCK_USER = false;         // Schlussstufe sperrt den User? (Default: nein)

/**
 * Einfache ZHL-Eskalations-Mail (ohne Smarty-Template, plain HTML).
 */
class ZhlOverdueEmail extends EmailMessage
{
    private $zhlEmail;
    private $zhlName;
    private $zhlResource;
    private $zhlDue;
    private $zhlStage;
    private $zhlFinalStage;

    public function __construct($email, $name, $resourceName, $dueDate, $stage, $finalStage, $language = null)
    {
        $this->zhlEmail = $email;
        $this->zhlName = $name;
        $this->zhlResource = $resourceName;
        $this->zhlDue = $dueDate;
        $this->zhlStage = $stage;
        $this->zhlFinalStage = $finalStage;
        parent::__construct($language);
    }

    public function To()
    {
        return new EmailAddress($this->zhlEmail, new FullName($this->zhlName, ''));
    }

    public function Subject()
    {
        $prefix = $this->zhlStage >= $this->zhlFinalStage ? 'LETZTE MAHNUNG' : 'Erinnerung';
        return sprintf('[%s] Überfällige Rückgabe: %s', $prefix, $this->zhlResource);
    }

    public function Body()
    {
        $name = htmlspecialchars((string)$this->zhlName, ENT_QUOTES, 'UTF-8');
        $res = htmlspecialchars((string)$this->zhlResource, ENT_QUOTES, 'UTF-8');
        $due = htmlspecialchars((string)$this->zhlDue, ENT_QUOTES, 'UTF-8');
        $final = $this->zhlStage >= $this->zhlFinalStage
            ? '<p><strong>Dies ist die letzte Mahnung.</strong> Bitte geben Sie das Gerät umgehend zurück, '
              . 'sonst kann Ihr Ausleih-Konto gesperrt werden.</p>'
            : '<p>Bitte geben Sie das Gerät zeitnah zurück oder melden Sie sich beim ZHL-Team.</p>';

        return '<p>Hallo ' . $name . ',</p>'
            . '<p>die Rückgabe des Geräts <strong>' . $res . '</strong> ist seit dem '
            . $due . ' überfällig (Mahnstufe ' . (int)$this->zhlStage . ').</p>'
            . $final
            . '<p>Mit freundlichen Grüßen<br>ZHL Medienausleihe</p>';
    }
}

Log::Debug('Running zhl_overdue.php');

try {
    $emailEnabled = Configuration::Instance()->GetKey(ConfigKeys::EMAIL_ENABLED, new BooleanConverter());
    $db = ServiceLocator::GetDatabase();
    $now = Date::Now();
    $finalStage = count(ZHL_OVERDUE_STAGE_DAYS);

    // Überfällige Rückgaben: geplantes Ende + Toleranz überschritten, noch nicht erledigt.
    $cmd = new AdHocCommand(
        "SELECT h.id, h.handover_token, h.reference_number, h.resource_id, h.scheduled_end_utc, " .
        "r.name AS resource_name " .
        "FROM zhl_booking_handover h " .
        "LEFT JOIN resources r ON r.resource_id = h.resource_id " .
        "WHERE h.type = 'return' AND h.status IN ('requested','confirmed') " .
        "AND h.scheduled_end_utc IS NOT NULL " .
        "AND h.scheduled_end_utc < @cutoff"
    );
    $cmd->AddParameter(new Parameter('@cutoff', $now->AddHours(-ZHL_OVERDUE_GRACE_HOURS)->ToDatabase()));
    $reader = $db->Query($cmd);

    $rows = [];
    while ($row = $reader->GetRow()) {
        $rows[] = $row;
    }
    $reader->Free();

    foreach ($rows as $row) {
        $handoverId = (int)$row['id'];
        $dueUtc = new DateTime($row['scheduled_end_utc'], new DateTimeZone('UTC'));
        $nowUtc = new DateTime($now->ToDatabase(), new DateTimeZone('UTC'));
        $daysOverdue = (int)floor(($nowUtc->getTimestamp() - $dueUtc->getTimestamp()) / 86400);

        // Zielstufe = höchste Stufe, deren Schwelle erreicht ist.
        $targetStage = 0;
        foreach (ZHL_OVERDUE_STAGE_DAYS as $i => $threshold) {
            if ($daysOverdue >= $threshold) {
                $targetStage = $i + 1;
            }
        }
        if ($targetStage === 0) {
            continue;
        }

        // Bereits versandte Stufen?
        $sentCmd = new AdHocCommand('SELECT MAX(stage) AS max_stage FROM zhl_overdue_notice WHERE handover_id = @hid');
        $sentCmd->AddParameter(new Parameter('@hid', $handoverId));
        $sentReader = $db->Query($sentCmd);
        $sentRow = $sentReader->GetRow();
        $sentReader->Free();
        $maxSent = $sentRow && $sentRow['max_stage'] !== null ? (int)$sentRow['max_stage'] : 0;

        // Zeitbasiert eskalieren: die zur AKTUELLEN Überfälligkeit passende Stufe senden
        // (überspringt verpasste Stufen bei spät entdeckten Fällen — kein 1→2→3-Spam in
        // Folge-Läufen). Schon versandte Stufe → nichts tun.
        if ($targetStage <= $maxSent) {
            continue;
        }
        $nextStage = $targetStage;

        // Empfänger über das Token auflösen (Assistent legt es immer an).
        $user = null;
        if (!empty($row['handover_token'])) {
            $uCmd = new AdHocCommand(
                'SELECT u.user_id, u.email, u.fname, u.lname, u.language ' .
                'FROM zhl_handover_token t JOIN users u ON u.user_id = t.user_id ' .
                'WHERE t.handover_token = @token'
            );
            $uCmd->AddParameter(new Parameter('@token', $row['handover_token']));
            $uReader = $db->Query($uCmd);
            $user = $uReader->GetRow() ?: null;
            $uReader->Free();
        }
        if (!$user || empty($user['email'])) {
            Log::Error('zhl_overdue: kein Empfänger für handover %s', $handoverId);
            continue;
        }

        $resourceName = $row['resource_name'] ?: 'Gerät';
        $fullName = trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? ''));

        // Versand pro Datensatz absichern: schlägt eine Mail fehl, blockiert sie weder die
        // anderen noch wird die Stufe als „versandt" protokolliert (→ Retry im nächsten Lauf).
        if ($emailEnabled) {
            try {
                ServiceLocator::GetEmailService()->Send(new ZhlOverdueEmail(
                    $user['email'],
                    $fullName,
                    $resourceName,
                    $row['scheduled_end_utc'],
                    $nextStage,
                    $finalStage,
                    $user['language'] ?? null
                ));
            } catch (Exception $mailEx) {
                Log::Error('zhl_overdue: Mailversand fehlgeschlagen (handover %s): %s', $handoverId, $mailEx->getMessage());
                continue;
            }
        }
        Log::Debug('zhl_overdue: Stufe %s an %s (handover %s, %s Tage überfällig)',
            $nextStage, $user['email'], $handoverId, $daysOverdue);

        // Versand protokollieren (Unique handover_id+stage verhindert Doppel).
        $ins = new AdHocCommand(
            'INSERT INTO zhl_overdue_notice (handover_id, handover_token, reference_number, stage, recipient_email, sent_at) ' .
            'VALUES (@hid, @token, @ref, @stage, @email, @sent)'
        );
        $ins->AddParameter(new Parameter('@hid', $handoverId));
        $ins->AddParameter(new Parameter('@token', $row['handover_token']));
        $ins->AddParameter(new Parameter('@ref', $row['reference_number']));
        $ins->AddParameter(new Parameter('@stage', $nextStage));
        $ins->AddParameter(new Parameter('@email', $user['email']));
        $ins->AddParameter(new Parameter('@sent', $now->ToDatabase()));
        $db->Execute($ins);

        // Optional: Schlussstufe sperrt den User (Default AUS).
        if (ZHL_OVERDUE_LOCK_USER && $nextStage >= $finalStage) {
            $lock = new AdHocCommand('UPDATE users SET status_id = 2 WHERE user_id = @uid'); // 2 = inaktiv
            $lock->AddParameter(new Parameter('@uid', (int)$user['user_id']));
            $db->Execute($lock);
            Log::Debug('zhl_overdue: User %s gesperrt (Schlussstufe)', $user['user_id']);
        }
    }
} catch (Exception $ex) {
    Log::Error('Error running zhl_overdue.php: %s', $ex);
}

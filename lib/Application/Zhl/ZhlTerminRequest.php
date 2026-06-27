<?php

/**
 * ZHL B — Wunschtermin-Anfragen (zhl_termin_request).
 *
 * Anfrage-only: hält das Gerät NICHT hart. Erfasst Wunsch-Zeitraum + Nachricht, benachrichtigt das
 * Medien-Team und lässt den Admin die Buchung per 1-Klick (im Namen des Nutzers) eintragen oder
 * ablehnen. Nutzer können ihre offene Anfrage stornieren.
 *
 * Alle Schreib-/Lesezugriffe über AdHocCommand mit Parametern (kein String-Interpolat). WICHTIG:
 * keine Parameternamen, die Präfix eines anderen sind (LibreBooking ersetzt per String-Replace).
 *
 * Status: open | booked | declined | cancelled.
 */
class ZhlTerminRequest
{
    /**
     * Empfänger-Adresse des Medien-Teams. Wiederverwendung der (test-sicheren) Hauspost-Config:
     * `medien_email` zeigt auf Staging auf die Test-Adresse, im Echtbetrieb auf zhlmedien@…
     */
    public static function RecipientEmail(): string
    {
        $f = ROOT_DIR . 'config/zhl-hauspost.php';
        if (!is_readable($f)) {
            $f = ROOT_DIR . 'config/zhl-hauspost.example.php';
        }
        $c = is_readable($f) ? (require $f) : [];
        $mail = is_array($c) ? trim((string)($c['medien_email'] ?? '')) : '';
        return $mail;
    }

    /**
     * Neue Anfrage anlegen.
     * @param array{user_id:int,kind:string,resource_id:?int,bundle_id:?int,label:string,
     *              desired_start:?string,desired_end:?string,project_title:string,message:?string} $d
     * @return int neue id (0 bei Fehler)
     */
    public static function Create($db, array $d): int
    {
        try {
            $cmd = new AdHocCommand(
                'INSERT INTO zhl_termin_request ' .
                '(user_id, kind, resource_id, bundle_id, label, desired_start, desired_end, project_title, message, status, created_at) ' .
                'VALUES (@uid, @kind, @resid, @bunid, @label, @dstart, @dend, @ptitle, @msg, @stat, @created)'
            );
            $cmd->AddParameter(new Parameter('@uid', (int)$d['user_id']));
            $cmd->AddParameter(new Parameter('@kind', ($d['kind'] === 'bundle' ? 'bundle' : 'single')));
            $cmd->AddParameter(new Parameter('@resid', isset($d['resource_id']) && $d['resource_id'] ? (int)$d['resource_id'] : null));
            $cmd->AddParameter(new Parameter('@bunid', isset($d['bundle_id']) && $d['bundle_id'] ? (int)$d['bundle_id'] : null));
            $cmd->AddParameter(new Parameter('@label', mb_substr((string)$d['label'], 0, 190)));
            $cmd->AddParameter(new Parameter('@dstart', $d['desired_start'] ?? null));
            $cmd->AddParameter(new Parameter('@dend', $d['desired_end'] ?? null));
            $cmd->AddParameter(new Parameter('@ptitle', mb_substr((string)($d['project_title'] ?? ''), 0, 190)));
            $cmd->AddParameter(new Parameter('@msg', ($d['message'] ?? null) !== null ? mb_substr((string)$d['message'], 0, 4000) : null));
            $cmd->AddParameter(new Parameter('@stat', 'open'));
            $cmd->AddParameter(new Parameter('@created', gmdate('Y-m-d H:i:s')));
            // ExecuteInsert führt aus UND liefert die Auto-Increment-Id (Database hat KEIN LastInsertId()).
            return (int)$db->ExecuteInsert($cmd);
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: Create fehlgeschlagen: %s', $e);
            return 0;
        }
    }

    /** Einzelne Anfrage. @return array|null */
    public static function Get($db, int $id): ?array
    {
        try {
            $cmd = new AdHocCommand('SELECT * FROM zhl_termin_request WHERE id = @rid');
            $cmd->AddParameter(new Parameter('@rid', $id));
            $reader = $db->Query($cmd);
            $row = $reader->GetRow();
            $reader->Free();
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: Get(%d) fehlgeschlagen: %s', $id, $e);
            return null;
        }
    }

    /**
     * Offene Anfragen (für die Admin-Liste), mit Nutzer-Name/Mail.
     * @return array[]
     */
    public static function ListOpen($db): array
    {
        $out = [];
        try {
            $reader = $db->Query(new AdHocCommand(
                'SELECT t.*, u.fname, u.lname, u.email FROM zhl_termin_request t ' .
                'LEFT JOIN users u ON u.user_id = t.user_id ' .
                "WHERE t.status = 'open' ORDER BY t.created_at ASC"
            ));
            while ($row = $reader->GetRow()) {
                $out[] = $row;
            }
            $reader->Free();
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: ListOpen fehlgeschlagen: %s', $e);
        }
        return $out;
    }

    /**
     * Offene Anfragen EINES Nutzers (für „Meine Buchungen" + Stornieren).
     * @return array[]
     */
    public static function ListOpenForUser($db, int $userId): array
    {
        $out = [];
        try {
            $cmd = new AdHocCommand(
                "SELECT * FROM zhl_termin_request WHERE user_id = @uid AND status = 'open' ORDER BY created_at DESC"
            );
            $cmd->AddParameter(new Parameter('@uid', $userId));
            $reader = $db->Query($cmd);
            while ($row = $reader->GetRow()) {
                $out[] = $row;
            }
            $reader->Free();
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: ListOpenForUser(%d) fehlgeschlagen: %s', $userId, $e);
        }
        return $out;
    }

    /**
     * Status setzen (gebucht/abgelehnt/storniert). Übergänge gelten IMMER nur aus status='open'
     * (verhindert Doppel-Bearbeitung/Race nach einem vorherigen Get()). `$onlyIfOpenForUser` > 0
     * verlangt zusätzlich Eigentum dieses Nutzers (sicheres Nutzer-Storno).
     * @return bool true, wenn der Command ohne Fehler lief (DB-Status ist durch das open-Gate korrekt)
     */
    public static function SetStatus($db, int $id, string $status, ?int $adminId = null, ?string $note = null, ?string $ref = null, int $onlyIfOpenForUser = 0): bool
    {
        if (!in_array($status, ['booked', 'declined', 'cancelled'], true)) {
            return false;
        }
        try {
            // Nur aus 'open' heraus umschaltbar — eine bereits bearbeitete/zurückgezogene Anfrage bleibt unberührt.
            $sql = 'UPDATE zhl_termin_request SET status = @stat, handled_at = @hat, handled_by = @hby, ' .
                "admin_note = @note, reference_number = @ref WHERE id = @rid AND status = 'open'";
            if ($onlyIfOpenForUser > 0) {
                $sql .= ' AND user_id = @owner';
            }
            $cmd = new AdHocCommand($sql);
            $cmd->AddParameter(new Parameter('@stat', $status));
            $cmd->AddParameter(new Parameter('@hat', gmdate('Y-m-d H:i:s')));
            $cmd->AddParameter(new Parameter('@hby', $adminId !== null ? (int)$adminId : null));
            $cmd->AddParameter(new Parameter('@note', $note !== null ? mb_substr($note, 0, 500) : null));
            $cmd->AddParameter(new Parameter('@ref', $ref));
            $cmd->AddParameter(new Parameter('@rid', $id));
            if ($onlyIfOpenForUser > 0) {
                $cmd->AddParameter(new Parameter('@owner', $onlyIfOpenForUser));
            }
            $db->Execute($cmd);
            return true;
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: SetStatus(%d,%s) fehlgeschlagen: %s', $id, $status, $e);
            return false;
        }
    }

    /** Anzahl offener Anfragen (Admin-Badge). */
    public static function CountOpen($db): int
    {
        try {
            $reader = $db->Query(new AdHocCommand("SELECT COUNT(*) AS n FROM zhl_termin_request WHERE status = 'open'"));
            $row = $reader->GetRow();
            $reader->Free();
            return (int)($row['n'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

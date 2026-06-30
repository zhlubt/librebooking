<?php

/**
 * ZHL — Ausleihdauer-Ausnahme (zhl_dauer_ausnahme). SPEC-AUSLEIHDAUER-LIMIT.
 *
 * Begründete Sonderfreigabe für eine Ausleihe, die das Standard-Limit (Nutzungsdauer/Puffer)
 * überschreitet. Ablauf: Nutzer stellt Anfrage (open) → Admin genehmigt (approved + Token + Ablauf)
 * oder lehnt ab (declined) → Nutzer bucht selbst per Token-Link; beim Buchen wird der Token atomar
 * eingelöst (used). Der Token hebt NUR das Dauer-Limit auf — die native Verfügbarkeits-/Konflikt-
 * prüfung bleibt unberührt.
 *
 * Single-winner ohne affected-rows (mysqli trennt nach jedem Execute): Conditional-Update + Nonce,
 * Read-after bestimmt den Gewinner — gleiche Technik wie ZhlTerminRequest::ClaimOffer.
 *
 * Alle Zugriffe über AdHocCommand mit Parametern. Keine Parameternamen, die Präfix eines anderen sind.
 */
class ZhlDauerAusnahme
{
    /**
     * Neue Anfrage anlegen.
     * @param array{user_id:int,resource_id:int,requested_begin_utc:string,requested_end_utc:string,
     *              nutzung_tage:int,puffer_vor_tage:int,puffer_nach_tage:int,reason:string} $d
     * @return int neue id (0 bei Fehler)
     */
    public static function Create($db, array $d): int
    {
        try {
            $cmd = new AdHocCommand(
                'INSERT INTO zhl_dauer_ausnahme ' .
                '(user_id, resource_id, requested_begin_utc, requested_end_utc, nutzung_tage, ' .
                ' puffer_vor_tage, puffer_nach_tage, reason, status, created_at) ' .
                "VALUES (@uid, @resid, @rbeg, @rend, @ntage, @pvor, @pnach, @reason, 'open', @created)"
            );
            $cmd->AddParameter(new Parameter('@uid', (int)$d['user_id']));
            $cmd->AddParameter(new Parameter('@resid', (int)$d['resource_id']));
            $cmd->AddParameter(new Parameter('@rbeg', (string)$d['requested_begin_utc']));
            $cmd->AddParameter(new Parameter('@rend', (string)$d['requested_end_utc']));
            $cmd->AddParameter(new Parameter('@ntage', (int)$d['nutzung_tage']));
            $cmd->AddParameter(new Parameter('@pvor', (int)($d['puffer_vor_tage'] ?? 0)));
            $cmd->AddParameter(new Parameter('@pnach', (int)($d['puffer_nach_tage'] ?? 0)));
            $cmd->AddParameter(new Parameter('@reason', mb_substr((string)$d['reason'], 0, 4000)));
            $cmd->AddParameter(new Parameter('@created', gmdate('Y-m-d H:i:s')));
            return (int)$db->ExecuteInsert($cmd);
        } catch (Throwable $e) {
            Log::Error('ZHL-DauerAusnahme: Create fehlgeschlagen: %s', $e);
            return 0;
        }
    }

    /** Einzelne Anfrage. @return array|null */
    public static function Get($db, int $id): ?array
    {
        try {
            $cmd = new AdHocCommand('SELECT * FROM zhl_dauer_ausnahme WHERE id = @aid');
            $cmd->AddParameter(new Parameter('@aid', $id));
            $reader = $db->Query($cmd);
            $row = $reader->GetRow();
            $reader->Free();
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            Log::Error('ZHL-DauerAusnahme: Get(%d) fehlgeschlagen: %s', $id, $e);
            return null;
        }
    }

    /** Offene Anfragen für die Admin-Liste (mit Nutzer- und Gerätename). @return array[] */
    public static function ListOpen($db): array
    {
        $out = [];
        try {
            $reader = $db->Query(new AdHocCommand(
                'SELECT a.*, u.fname, u.lname, u.email, r.name AS resource_name ' .
                'FROM zhl_dauer_ausnahme a ' .
                'LEFT JOIN users u ON u.user_id = a.user_id ' .
                'LEFT JOIN resources r ON r.resource_id = a.resource_id ' .
                "WHERE a.status = 'open' ORDER BY a.created_at ASC"
            ));
            while ($row = $reader->GetRow()) {
                $out[] = $row;
            }
            $reader->Free();
        } catch (Throwable $e) {
            Log::Error('ZHL-DauerAusnahme: ListOpen fehlgeschlagen: %s', $e);
        }
        return $out;
    }

    /** Anzahl offener Anfragen (Admin-Badge). */
    public static function CountOpen($db): int
    {
        try {
            $reader = $db->Query(new AdHocCommand("SELECT COUNT(*) AS n FROM zhl_dauer_ausnahme WHERE status = 'open'"));
            $row = $reader->GetRow();
            $reader->Free();
            return (int)($row['n'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** Anfrage per login-freiem Token (inkl. Nutzer-Mail/Sprache/TZ + Gerätename). @return array|null */
    public static function GetByToken($db, string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        try {
            $cmd = new AdHocCommand(
                'SELECT a.*, u.fname, u.lname, u.email, u.language, u.timezone, r.name AS resource_name ' .
                'FROM zhl_dauer_ausnahme a ' .
                'LEFT JOIN users u ON u.user_id = a.user_id ' .
                'LEFT JOIN resources r ON r.resource_id = a.resource_id ' .
                'WHERE a.grant_token = @tokval'
            );
            $cmd->AddParameter(new Parameter('@tokval', $token));
            $reader = $db->Query($cmd);
            $row = $reader->GetRow();
            $reader->Free();
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            Log::Error('ZHL-DauerAusnahme: GetByToken fehlgeschlagen: %s', $e);
            return null;
        }
    }

    /**
     * Genehmigen: Token + Ablauf setzen, Status → approved (nur aus 'open'). Optional engere Grenzen über
     * approved_begin/end (Default: wie angefragt). @return string|null der Grant-Token (für die Mail).
     */
    public static function Approve($db, int $id, int $adminId, int $gueltigTage, ?string $note): ?string
    {
        $before = self::Get($db, $id);
        if ($before === null || (string)($before['status'] ?? '') !== 'open') {
            return null;
        }
        try {
            $token = bin2hex(random_bytes(20)); // 160 Bit
            $until = gmdate('Y-m-d H:i:s', time() + max(1, $gueltigTage) * 86400);
            $cmd = new AdHocCommand(
                "UPDATE zhl_dauer_ausnahme SET status = 'approved', grant_token = @tokval, " .
                'approved_until_utc = @until, handled_by = @hby, handled_at = @hat, admin_note = @note ' .
                "WHERE id = @aid AND status = 'open'"
            );
            $cmd->AddParameter(new Parameter('@tokval', $token));
            $cmd->AddParameter(new Parameter('@until', $until));
            $cmd->AddParameter(new Parameter('@hby', $adminId));
            $cmd->AddParameter(new Parameter('@hat', gmdate('Y-m-d H:i:s')));
            $cmd->AddParameter(new Parameter('@note', $note !== null && $note !== '' ? mb_substr($note, 0, 500) : null));
            $cmd->AddParameter(new Parameter('@aid', $id));
            $db->Execute($cmd);
            // Read-after: nur wenn unser Token wirklich gesetzt wurde, haben wir gewonnen.
            $fresh = self::Get($db, $id);
            if ($fresh !== null && (string)($fresh['grant_token'] ?? '') === $token && (string)($fresh['status'] ?? '') === 'approved') {
                return $token;
            }
            return null;
        } catch (Throwable $e) {
            Log::Error('ZHL-DauerAusnahme: Approve(%d) fehlgeschlagen: %s', $id, $e);
            return null;
        }
    }

    /** Ablehnen (nur aus 'open'). @return bool true, wenn tatsächlich abgelehnt. */
    public static function Decline($db, int $id, int $adminId, ?string $note): bool
    {
        $before = self::Get($db, $id);
        if ($before === null || (string)($before['status'] ?? '') !== 'open') {
            return false;
        }
        try {
            $cmd = new AdHocCommand(
                "UPDATE zhl_dauer_ausnahme SET status = 'declined', handled_by = @hby, handled_at = @hat, " .
                "admin_note = @note WHERE id = @aid AND status = 'open'"
            );
            $cmd->AddParameter(new Parameter('@hby', $adminId));
            $cmd->AddParameter(new Parameter('@hat', gmdate('Y-m-d H:i:s')));
            $cmd->AddParameter(new Parameter('@note', $note !== null && $note !== '' ? mb_substr($note, 0, 500) : 'Abgelehnt.'));
            $cmd->AddParameter(new Parameter('@aid', $id));
            $db->Execute($cmd);
            return true;
        } catch (Throwable $e) {
            Log::Error('ZHL-DauerAusnahme: Decline(%d) fehlgeschlagen: %s', $id, $e);
            return false;
        }
    }

    /**
     * Prüft, ob der Token jetzt für (user, resource, Nutzungsfenster) gilt: approved, nicht abgelaufen,
     * gebunden an genau diesen Nutzer + diese Ressource, und das Fenster ⊆ genehmigtem (requested) Fenster.
     * Misst NICHT erneut die Tage — die Fenster-Bindung deckt die Dauer implizit ab (Teilfenster erlaubt).
     */
    public static function IsValidFor($db, string $token, int $userId, int $resourceId, string $beginUtc, string $endUtc): bool
    {
        if ($token === '') {
            return false;
        }
        try {
            $row = self::GetByToken($db, $token);
            if ($row === null) {
                return false;
            }
            if ((string)($row['status'] ?? '') !== 'approved') {
                return false;
            }
            if ((int)($row['user_id'] ?? 0) !== $userId || (int)($row['resource_id'] ?? 0) !== $resourceId) {
                return false;
            }
            $until = (string)($row['approved_until_utc'] ?? '');
            if ($until !== '' && strcmp($until, gmdate('Y-m-d H:i:s')) < 0) {
                return false; // abgelaufen
            }
            // Fenster ⊆ genehmigt: gebuchter Beginn >= genehmigter Beginn, gebuchtes Ende <= genehmigtes Ende.
            $rbeg = (string)($row['requested_begin_utc'] ?? '');
            $rend = (string)($row['requested_end_utc'] ?? '');
            if ($rbeg === '' || $rend === '') {
                return false;
            }
            return strcmp($beginUtc, $rbeg) >= 0 && strcmp($endUtc, $rend) <= 0;
        } catch (Throwable $e) {
            Log::Error('ZHL-DauerAusnahme: IsValidFor fehlgeschlagen: %s', $e);
            return false;
        }
    }

    /**
     * Atomar einlösen (single-winner, VOR dem Reserve-Save): approved → used mit unserer Nonce.
     * Read-after: gewonnen genau dann, wenn claim_nonce unseren Wert trägt.
     * @return bool true NUR für den Aufruf, der den Grant tatsächlich eingelöst hat.
     */
    public static function ClaimGrant($db, string $token, int $userId, int $resourceId, string $nonce): bool
    {
        try {
            $cmd = new AdHocCommand(
                "UPDATE zhl_dauer_ausnahme SET status = 'used', claim_nonce = @nonceval, used_at = @uat " .
                "WHERE grant_token = @tokval AND status = 'approved' AND user_id = @uid AND resource_id = @resid"
            );
            $cmd->AddParameter(new Parameter('@nonceval', $nonce));
            $cmd->AddParameter(new Parameter('@uat', gmdate('Y-m-d H:i:s')));
            $cmd->AddParameter(new Parameter('@tokval', $token));
            $cmd->AddParameter(new Parameter('@uid', $userId));
            $cmd->AddParameter(new Parameter('@resid', $resourceId));
            $db->Execute($cmd);
        } catch (Throwable $e) {
            Log::Error('ZHL-DauerAusnahme: ClaimGrant fehlgeschlagen: %s', $e);
            return false;
        }
        $row = self::GetByToken($db, $token);
        return $row !== null && (string)($row['status'] ?? '') === 'used' && (string)($row['claim_nonce'] ?? '') === $nonce;
    }

    /**
     * Den eigenen (per Nonce identifizierten) Claim wieder freigeben — nach fehlgeschlagenem Save.
     * Gibt NUR den eigenen Claim frei, nie einen fremden.
     */
    public static function ReleaseGrant($db, string $token, string $nonce): void
    {
        try {
            $cmd = new AdHocCommand(
                "UPDATE zhl_dauer_ausnahme SET status = 'approved', claim_nonce = NULL, used_at = NULL " .
                "WHERE grant_token = @tokval AND claim_nonce = @nonceval AND status = 'used'"
            );
            $cmd->AddParameter(new Parameter('@tokval', $token));
            $cmd->AddParameter(new Parameter('@nonceval', $nonce));
            $db->Execute($cmd);
        } catch (Throwable $e) {
            Log::Error('ZHL-DauerAusnahme: ReleaseGrant fehlgeschlagen: %s', $e);
        }
    }

    /** Reservierungsnummer am eingelösten Grant nachtragen (best effort, Bookkeeping). */
    public static function SetUsedReference($db, string $token, string $nonce, string $ref): void
    {
        try {
            $cmd = new AdHocCommand(
                'UPDATE zhl_dauer_ausnahme SET used_reference_number = @refval ' .
                "WHERE grant_token = @tokval AND claim_nonce = @nonceval AND status = 'used'"
            );
            $cmd->AddParameter(new Parameter('@refval', mb_substr($ref, 0, 40)));
            $cmd->AddParameter(new Parameter('@tokval', $token));
            $cmd->AddParameter(new Parameter('@nonceval', $nonce));
            $db->Execute($cmd);
        } catch (Throwable $e) {
            Log::Error('ZHL-DauerAusnahme: SetUsedReference fehlgeschlagen: %s', $e);
        }
    }
}

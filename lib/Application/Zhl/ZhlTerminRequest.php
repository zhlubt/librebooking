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
                "SELECT * FROM zhl_termin_request WHERE user_id = @uid AND status IN ('open','offered') ORDER BY created_at DESC"
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

    /** Anzahl offener (noch zu bearbeitender) Anfragen für das Admin-Badge: open ODER offered. */
    public static function CountOpen($db): int
    {
        try {
            $reader = $db->Query(new AdHocCommand("SELECT COUNT(*) AS n FROM zhl_termin_request WHERE status IN ('open','offered')"));
            $row = $reader->GetRow();
            $reader->Free();
            return (int)($row['n'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    // =============================================================================================
    // SPEC-EINFUEHRUNG-AUSHANDLUNG — Aushandlung: Angebote, Token, Auswahl, Storno
    // =============================================================================================

    /** Anfragen, die der Admin bearbeiten soll: open (neu) ODER offered (Angebote raus, wartet). */
    public static function ListActionable($db): array
    {
        $out = [];
        try {
            $reader = $db->Query(new AdHocCommand(
                'SELECT t.*, u.fname, u.lname, u.email FROM zhl_termin_request t ' .
                'LEFT JOIN users u ON u.user_id = t.user_id ' .
                "WHERE t.status IN ('open','offered') ORDER BY t.created_at ASC"
            ));
            while ($row = $reader->GetRow()) {
                $out[] = $row;
            }
            $reader->Free();
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: ListActionable fehlgeschlagen: %s', $e);
        }
        return $out;
    }

    /** Token erzeugen, falls noch keiner gesetzt ist. @return string|null der (vorhandene/neue) Token. */
    public static function EnsureToken($db, int $id): ?string
    {
        $req = self::Get($db, $id);
        if ($req === null) {
            return null;
        }
        $tok = trim((string)($req['accept_token'] ?? ''));
        if ($tok !== '') {
            return $tok;
        }
        try {
            $tok = bin2hex(random_bytes(20)); // 160 Bit
            $cmd = new AdHocCommand('UPDATE zhl_termin_request SET accept_token = @tokval WHERE id = @reqid AND accept_token IS NULL');
            $cmd->AddParameter(new Parameter('@tokval', $tok));
            $cmd->AddParameter(new Parameter('@reqid', $id));
            $db->Execute($cmd);
            // Read-after: falls parallel ein anderer Token gesetzt wurde, gewinnt der DB-Stand.
            $fresh = self::Get($db, $id);
            return $fresh !== null ? (string)($fresh['accept_token'] ?? '') : null;
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: EnsureToken(%d) fehlgeschlagen: %s', $id, $e);
            return null;
        }
    }

    /** Anfrage per login-freiem Token. @return array|null (inkl. Nutzer-Name/Mail). */
    public static function GetByToken($db, string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        try {
            $cmd = new AdHocCommand(
                'SELECT t.*, u.fname, u.lname, u.email, u.language, u.timezone FROM zhl_termin_request t ' .
                'LEFT JOIN users u ON u.user_id = t.user_id WHERE t.accept_token = @tokval'
            );
            $cmd->AddParameter(new Parameter('@tokval', $token));
            $reader = $db->Query($cmd);
            $row = $reader->GetRow();
            $reader->Free();
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: GetByToken fehlgeschlagen: %s', $e);
            return null;
        }
    }

    /**
     * Termin-Angebot anlegen.
     * @param array{request_id:int,instructor_uid:int,instructor_name:string,created_by_uid:int,
     *              start_utc:string,end_utc:string,note:?string} $d
     */
    public static function AddOffer($db, array $d): int
    {
        try {
            $cmd = new AdHocCommand(
                'INSERT INTO zhl_termin_offer ' .
                '(request_id, instructor_uid, instructor_name, created_by_uid, start_utc, end_utc, note, status, ics_sequence, created_at) ' .
                "VALUES (@reqid, @insuid, @insname, @cruid, @startts, @endts, @notetxt, 'open', 0, @createdts)"
            );
            $cmd->AddParameter(new Parameter('@reqid', (int)$d['request_id']));
            $cmd->AddParameter(new Parameter('@insuid', (int)$d['instructor_uid']));
            $cmd->AddParameter(new Parameter('@insname', mb_substr((string)$d['instructor_name'], 0, 190)));
            $cmd->AddParameter(new Parameter('@cruid', (int)$d['created_by_uid']));
            $cmd->AddParameter(new Parameter('@startts', (string)$d['start_utc']));
            $cmd->AddParameter(new Parameter('@endts', (string)$d['end_utc']));
            $cmd->AddParameter(new Parameter('@notetxt', ($d['note'] ?? null) !== null && trim((string)$d['note']) !== '' ? mb_substr((string)$d['note'], 0, 500) : null));
            $cmd->AddParameter(new Parameter('@createdts', gmdate('Y-m-d H:i:s')));
            return (int)$db->ExecuteInsert($cmd);
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: AddOffer fehlgeschlagen: %s', $e);
            return 0;
        }
    }

    /** Einzelnes Angebot (inkl. Instructor-Mail für ICS). @return array|null */
    public static function GetOffer($db, int $offerId): ?array
    {
        try {
            $cmd = new AdHocCommand(
                'SELECT o.*, u.email AS instructor_email, u.language AS instructor_language ' .
                'FROM zhl_termin_offer o LEFT JOIN users u ON u.user_id = o.instructor_uid WHERE o.id = @offid'
            );
            $cmd->AddParameter(new Parameter('@offid', $offerId));
            $reader = $db->Query($cmd);
            $row = $reader->GetRow();
            $reader->Free();
            return $row === false ? null : $row;
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: GetOffer(%d) fehlgeschlagen: %s', $offerId, $e);
            return null;
        }
    }

    /**
     * Angebote einer Anfrage. $onlyStatus z. B. 'open' für die Auswahlseite.
     * @return array[]
     */
    public static function ListOffers($db, int $requestId, ?string $onlyStatus = null): array
    {
        $out = [];
        try {
            $sql = 'SELECT o.*, u.email AS instructor_email FROM zhl_termin_offer o ' .
                'LEFT JOIN users u ON u.user_id = o.instructor_uid WHERE o.request_id = @reqid';
            if ($onlyStatus !== null) {
                $sql .= ' AND o.status = @statval';
            }
            $sql .= ' ORDER BY o.start_utc ASC, o.id ASC';
            $cmd = new AdHocCommand($sql);
            $cmd->AddParameter(new Parameter('@reqid', $requestId));
            if ($onlyStatus !== null) {
                $cmd->AddParameter(new Parameter('@statval', $onlyStatus));
            }
            $reader = $db->Query($cmd);
            while ($row = $reader->GetRow()) {
                $out[] = $row;
            }
            $reader->Free();
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: ListOffers(%d) fehlgeschlagen: %s', $requestId, $e);
        }
        return $out;
    }

    /** Anzahl offener (wählbarer) Angebote einer Anfrage. */
    public static function CountOpenOffers($db, int $requestId): int
    {
        try {
            $cmd = new AdHocCommand("SELECT COUNT(*) AS n FROM zhl_termin_offer WHERE request_id = @reqid AND status = 'open'");
            $cmd->AddParameter(new Parameter('@reqid', $requestId));
            $reader = $db->Query($cmd);
            $row = $reader->GetRow();
            $reader->Free();
            return (int)($row['n'] ?? 0);
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** Angebot zurückziehen (nur aus 'open'). */
    public static function WithdrawOffer($db, int $offerId, int $requestId): void
    {
        try {
            $cmd = new AdHocCommand(
                "UPDATE zhl_termin_offer SET status = 'withdrawn', withdrawn_at = @wts " .
                "WHERE id = @offid AND request_id = @reqid AND status = 'open'"
            );
            $cmd->AddParameter(new Parameter('@wts', gmdate('Y-m-d H:i:s')));
            $cmd->AddParameter(new Parameter('@offid', $offerId));
            $cmd->AddParameter(new Parameter('@reqid', $requestId));
            $db->Execute($cmd);
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: WithdrawOffer(%d) fehlgeschlagen: %s', $offerId, $e);
        }
    }

    /** Request auf 'offered' setzen (nach „Angebote senden"); nur aus open/offered. */
    public static function MarkOffered($db, int $requestId): void
    {
        try {
            $cmd = new AdHocCommand("UPDATE zhl_termin_request SET status = 'offered' WHERE id = @reqid AND status IN ('open','offered')");
            $cmd->AddParameter(new Parameter('@reqid', $requestId));
            $db->Execute($cmd);
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: MarkOffered(%d) fehlgeschlagen: %s', $requestId, $e);
        }
    }

    /**
     * GEWINNER-CLAIM für die Bestätigung (genau einmal, ohne affected-rows):
     * Das Angebot wird nur aus 'open' → 'chosen' geschaltet UND dabei mit unserem zufälligen $icsUid
     * (Nonce + zugleich finale iCalendar-UID) markiert. Nur die eine UPDATE, deren WHERE noch greift,
     * setzt unseren Wert; der Gewinner liest seinen eigenen $icsUid zurück.
     * @return bool true NUR für den Aufruf, der das Angebot tatsächlich gewonnen hat.
     */
    public static function ClaimOffer($db, int $requestId, int $offerId, string $icsUid): bool
    {
        try {
            $cmd = new AdHocCommand(
                "UPDATE zhl_termin_offer SET status = 'chosen', chosen_at = @cts, ics_uid = @icsu, ics_sequence = 0 " .
                "WHERE id = @offid AND request_id = @reqid AND status = 'open'"
            );
            $cmd->AddParameter(new Parameter('@cts', gmdate('Y-m-d H:i:s')));
            $cmd->AddParameter(new Parameter('@icsu', $icsUid));
            $cmd->AddParameter(new Parameter('@offid', $offerId));
            $cmd->AddParameter(new Parameter('@reqid', $requestId));
            $db->Execute($cmd);
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: ClaimOffer(%d) fehlgeschlagen: %s', $offerId, $e);
            return false;
        }
        $o = self::GetOffer($db, $offerId);
        return $o !== null && ($o['status'] ?? '') === 'chosen' && (string)($o['ics_uid'] ?? '') === $icsUid;
    }

    /**
     * REQUEST-Gate für die Bestätigung (genau ein Gewinner pro ANFRAGE, auch wenn parallel zwei
     * VERSCHIEDENE Angebote bestätigt werden): nur aus 'offered' UND solange noch kein Angebot gewählt
     * ist. Nach dem (per-Angebot atomaren) ClaimOffer aufrufen. Read-after bestimmt den Gewinner über
     * chosen_offer_id.
     * @return bool true NUR für den Aufruf, dessen Angebot die Anfrage bekommen hat.
     */
    public static function ClaimRequest($db, int $requestId, int $offerId): bool
    {
        try {
            $cmd = new AdHocCommand(
                "UPDATE zhl_termin_request SET status = 'confirmed', chosen_offer_id = @offid, confirmed_at = @cts " .
                "WHERE id = @reqid AND status = 'offered' AND chosen_offer_id IS NULL"
            );
            $cmd->AddParameter(new Parameter('@offid', $offerId));
            $cmd->AddParameter(new Parameter('@cts', gmdate('Y-m-d H:i:s')));
            $cmd->AddParameter(new Parameter('@reqid', $requestId));
            $db->Execute($cmd);
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: ClaimRequest(%d) fehlgeschlagen: %s', $requestId, $e);
            return false;
        }
        $r = self::Get($db, $requestId);
        return $r !== null && ($r['status'] ?? '') === 'confirmed' && (int)($r['chosen_offer_id'] ?? 0) === $offerId;
    }

    /** Ein Angebot zurückziehen (z. B. wenn es zwar den Offer-Claim, aber den Request-Claim verlor). */
    public static function WithdrawChosenOffer($db, int $offerId, int $requestId): void
    {
        try {
            $cmd = new AdHocCommand("UPDATE zhl_termin_offer SET status = 'withdrawn', withdrawn_at = @wts, ics_uid = NULL WHERE id = @offid AND request_id = @reqid AND status = 'chosen'");
            $cmd->AddParameter(new Parameter('@wts', gmdate('Y-m-d H:i:s')));
            $cmd->AddParameter(new Parameter('@offid', $offerId));
            $cmd->AddParameter(new Parameter('@reqid', $requestId));
            $db->Execute($cmd);
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: WithdrawChosenOffer(%d) fehlgeschlagen: %s', $offerId, $e);
        }
    }

    /**
     * Bestätigung rückabwickeln (nur der Gewinner ruft das, z. B. bei Reservierungs-Konflikt):
     * Angebot zurück auf 'open' (UID/chosen_at gelöscht), Request zurück auf 'offered'.
     */
    public static function UndoConfirm($db, int $requestId, int $offerId): void
    {
        try {
            $c1 = new AdHocCommand("UPDATE zhl_termin_offer SET status = 'open', chosen_at = NULL, ics_uid = NULL WHERE id = @offid AND request_id = @reqid AND status = 'chosen'");
            $c1->AddParameter(new Parameter('@offid', $offerId));
            $c1->AddParameter(new Parameter('@reqid', $requestId));
            $db->Execute($c1);
            // Nur den eigenen, gerade bestätigten Stand zurückdrehen (chosen_offer_id-gegatet),
            // damit ein paralleler Submit eine fremde erfolgreiche Bestätigung nicht aufhebt.
            $c2 = new AdHocCommand("UPDATE zhl_termin_request SET status = 'offered', chosen_offer_id = NULL, confirmed_at = NULL WHERE id = @reqid AND status = 'confirmed' AND chosen_offer_id = @offid");
            $c2->AddParameter(new Parameter('@reqid', $requestId));
            $c2->AddParameter(new Parameter('@offid', $offerId));
            $db->Execute($c2);
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: UndoConfirm(%d) fehlgeschlagen: %s', $requestId, $e);
        }
    }

    /** Übrige noch offenen Angebote zurückziehen (nachdem eines gewählt wurde). */
    public static function WithdrawOtherOpenOffers($db, int $requestId, int $keepOfferId): void
    {
        try {
            $cmd = new AdHocCommand("UPDATE zhl_termin_offer SET status = 'withdrawn', withdrawn_at = @wts WHERE request_id = @reqid AND status = 'open' AND id <> @keepid");
            $cmd->AddParameter(new Parameter('@wts', gmdate('Y-m-d H:i:s')));
            $cmd->AddParameter(new Parameter('@reqid', $requestId));
            $cmd->AddParameter(new Parameter('@keepid', $keepOfferId));
            $db->Execute($cmd);
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: WithdrawOtherOpenOffers(%d) fehlgeschlagen: %s', $requestId, $e);
        }
    }

    /** Referenz der nativen Einführungs-Reservierung am Request speichern (für späteren Storno). */
    public static function SetReservationRef($db, int $requestId, string $ref): void
    {
        try {
            $cmd = new AdHocCommand('UPDATE zhl_termin_request SET einf_reservation_ref = @resref WHERE id = @reqid');
            $cmd->AddParameter(new Parameter('@resref', mb_substr($ref, 0, 32)));
            $cmd->AddParameter(new Parameter('@reqid', $requestId));
            $db->Execute($cmd);
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: SetReservationRef(%d) fehlgeschlagen: %s', $requestId, $e);
        }
    }

    /**
     * Nutzer zieht eine NOCH NICHT bestätigte Anfrage zurück (aus 'offered' oder 'open'):
     * Request → 'cancelled', alle offenen Angebote → 'withdrawn'. Kein ICS (nichts bestätigt).
     */
    public static function WithdrawRequest($db, int $requestId, int $onlyForUser = 0): void
    {
        try {
            $sql = "UPDATE zhl_termin_request SET status = 'cancelled', handled_at = @hts WHERE id = @reqid AND status IN ('open','offered')";
            if ($onlyForUser > 0) {
                $sql .= ' AND user_id = @ownerid';
            }
            $c1 = new AdHocCommand($sql);
            $c1->AddParameter(new Parameter('@hts', gmdate('Y-m-d H:i:s')));
            $c1->AddParameter(new Parameter('@reqid', $requestId));
            if ($onlyForUser > 0) {
                $c1->AddParameter(new Parameter('@ownerid', $onlyForUser));
            }
            $db->Execute($c1);
            $c2 = new AdHocCommand("UPDATE zhl_termin_offer SET status = 'withdrawn', withdrawn_at = @wts WHERE request_id = @reqid AND status = 'open'");
            $c2->AddParameter(new Parameter('@wts', gmdate('Y-m-d H:i:s')));
            $c2->AddParameter(new Parameter('@reqid', $requestId));
            $db->Execute($c2);
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: WithdrawRequest(%d) fehlgeschlagen: %s', $requestId, $e);
        }
    }

    /**
     * Admin lehnt die Anfrage ab (Gerät nicht verfügbar). Aus 'open' ODER 'offered'; zieht offene
     * Angebote zurück. @return bool true, wenn die Anfrage tatsächlich abgelehnt wurde (sonst war sie
     * schon abgeschlossen → keine Mail schicken).
     */
    public static function DeclineRequest($db, int $requestId, int $adminId, ?string $note): bool
    {
        $before = self::Get($db, $requestId);
        if ($before === null || !in_array((string)($before['status'] ?? ''), ['open', 'offered'], true)) {
            return false;
        }
        try {
            $c1 = new AdHocCommand("UPDATE zhl_termin_request SET status = 'declined', handled_at = @hts, handled_by = @hby, admin_note = @note WHERE id = @reqid AND status IN ('open','offered')");
            $c1->AddParameter(new Parameter('@hts', gmdate('Y-m-d H:i:s')));
            $c1->AddParameter(new Parameter('@hby', $adminId));
            $c1->AddParameter(new Parameter('@note', $note !== null && $note !== '' ? mb_substr($note, 0, 500) : 'Abgelehnt.'));
            $c1->AddParameter(new Parameter('@reqid', $requestId));
            $db->Execute($c1);
            $c2 = new AdHocCommand("UPDATE zhl_termin_offer SET status = 'withdrawn', withdrawn_at = @wts WHERE request_id = @reqid AND status = 'open'");
            $c2->AddParameter(new Parameter('@wts', gmdate('Y-m-d H:i:s')));
            $c2->AddParameter(new Parameter('@reqid', $requestId));
            $db->Execute($c2);
            return true;
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: DeclineRequest(%d) fehlgeschlagen: %s', $requestId, $e);
            return false;
        }
    }

    /**
     * Bestätigten Termin stornieren (nur aus 'confirmed'): Request → 'cancelled', gewähltes Angebot
     * → 'withdrawn' + ics_sequence=1 (für CANCEL-ICS mit gleicher UID). Best effort, ohne
     * affected-rows; ein extrem seltener simultaner Doppel-Storno führt höchstens zu einer doppelten
     * (idempotenten) CANCEL-Mail.
     */
    public static function CancelConfirmed($db, int $requestId): void
    {
        try {
            $c1 = new AdHocCommand("UPDATE zhl_termin_request SET status = 'cancelled', handled_at = @hts WHERE id = @reqid AND status = 'confirmed'");
            $c1->AddParameter(new Parameter('@hts', gmdate('Y-m-d H:i:s')));
            $c1->AddParameter(new Parameter('@reqid', $requestId));
            $db->Execute($c1);
            $c2 = new AdHocCommand("UPDATE zhl_termin_offer SET status = 'withdrawn', withdrawn_at = @wts, ics_sequence = 1 WHERE request_id = @reqid AND status = 'chosen'");
            $c2->AddParameter(new Parameter('@wts', gmdate('Y-m-d H:i:s')));
            $c2->AddParameter(new Parameter('@reqid', $requestId));
            $db->Execute($c2);
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: CancelConfirmed(%d) fehlgeschlagen: %s', $requestId, $e);
        }
    }

    /**
     * Nutzer für die Ressource als „eingeführt" markieren (SPEC §: nach Termin-Zusage sofort buchbar).
     * Gewährt das/die zur Ressource gehörende(n) Zertifikat(e) (zhl_cert_grant, unbegrenzt) und
     * aktualisiert die Projektion zhl_certificate für genau diesen Nutzer. Idempotent (UNIQUE/UPSERT).
     * @return bool true, wenn ein Zertifikatstyp existierte und gewährt wurde (sonst false = Gerät ohne
     *              Zertifikats-Einführung → Aufrufer kann das vermerken).
     */
    public static function GrantCertificateForResource($db, int $userId, int $resourceId, int $grantedBy = 0): bool
    {
        try {
            $cmd = new AdHocCommand(
                'SELECT ctr.cert_type_id FROM zhl_cert_type_resource ctr ' .
                'JOIN zhl_cert_type t ON t.id = ctr.cert_type_id AND t.active = 1 ' .
                'WHERE ctr.resource_id = @resid'
            );
            $cmd->AddParameter(new Parameter('@resid', $resourceId));
            $reader = $db->Query($cmd);
            $typeIds = [];
            while ($row = $reader->GetRow()) {
                $typeIds[] = (int)$row['cert_type_id'];
            }
            $reader->Free();
            if (empty($typeIds)) {
                return false;
            }
            $now = gmdate('Y-m-d H:i:s');
            foreach ($typeIds as $tid) {
                $ins = new AdHocCommand(
                    'INSERT INTO zhl_cert_grant (user_id, cert_type_id, granted_at, expires_at, granted_by) ' .
                    'VALUES (@uid, @ctid, @gat, NULL, @gby) ' .
                    'ON DUPLICATE KEY UPDATE expires_at = NULL'
                );
                $ins->AddParameter(new Parameter('@uid', $userId));
                $ins->AddParameter(new Parameter('@ctid', $tid));
                $ins->AddParameter(new Parameter('@gat', $now));
                $ins->AddParameter(new Parameter('@gby', $grantedBy > 0 ? $grantedBy : null));
                $db->Execute($ins);
            }
            // Projektion zhl_certificate für DIESEN Nutzer neu ableiten (wie zhl-cert-confirm rebuildProjection,
            // aber user-gescoped statt global).
            $proj = new AdHocCommand(
                'INSERT INTO zhl_certificate (user_id, resource_id, granted_at, expires_at) ' .
                'SELECT g.user_id, ctr.resource_id, MIN(g.granted_at), ' .
                '  CASE WHEN SUM(g.expires_at IS NULL) > 0 THEN NULL ELSE MAX(g.expires_at) END ' .
                'FROM zhl_cert_grant g ' .
                'JOIN zhl_cert_type_resource ctr ON ctr.cert_type_id = g.cert_type_id ' .
                'JOIN zhl_cert_type t ON t.id = g.cert_type_id AND t.active = 1 ' .
                'WHERE g.user_id = @uid ' .
                'GROUP BY g.user_id, ctr.resource_id ' .
                'ON DUPLICATE KEY UPDATE granted_at = VALUES(granted_at), expires_at = VALUES(expires_at)'
            );
            $proj->AddParameter(new Parameter('@uid', $userId));
            $db->Execute($proj);
            return true;
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: GrantCertificateForResource(u=%d,res=%d) fehlgeschlagen: %s', $userId, $resourceId, $e);
            return false;
        }
    }

    /**
     * Aktive Application-Admins (für das „Einführung macht"-Dropdown).
     * @return array[] [{user_id, fname, lname, email}]
     */
    public static function ListAdmins($db): array
    {
        $out = [];
        try {
            $cmd = new AdHocCommand(
                'SELECT u.user_id, u.fname, u.lname, u.email FROM users u ' .
                'WHERE u.status_id = 1 AND u.user_id IN (' .
                '  SELECT ug.user_id FROM user_groups ug ' .
                '  INNER JOIN group_roles gr ON ug.group_id = gr.group_id ' .
                '  INNER JOIN roles r ON r.role_id = gr.role_id AND r.role_level = @rolelvl' .
                ') GROUP BY u.user_id ORDER BY u.lname ASC, u.fname ASC'
            );
            $cmd->AddParameter(new Parameter('@rolelvl', RoleLevel::APPLICATION_ADMIN));
            $reader = $db->Query($cmd);
            while ($row = $reader->GetRow()) {
                $out[] = $row;
            }
            $reader->Free();
        } catch (Throwable $e) {
            Log::Error('ZHL-TerminRequest: ListAdmins fehlgeschlagen: %s', $e);
        }
        return $out;
    }
}

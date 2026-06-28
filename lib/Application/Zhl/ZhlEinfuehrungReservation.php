<?php

/**
 * ZHL — native Reservierung der Ressource FÜR die Einführungs-Stunde (SPEC-EINFUEHRUNG-AUSHANDLUNG §6).
 *
 * Nur für stundenweise/einführungs-blockende Geräte (zhl_uebergabe.booking_mode = 'slot', z. B. Studio):
 * bei der Bestätigung eines ausgehandelten Termins wird die Ressource für die EINE Einführungs-Stunde
 * reserviert (Owner = Anfragender), damit das Studio in dieser Stunde belegt ist. Die Geräte-LEIHE bleibt
 * separat (Nutzer bucht selbst ab Einführungs-Ende).
 *
 * Akteur-Session = der Instructor (ist ein Application-Admin) → umgeht Mindestfristen/Pflicht-Slot,
 * analog zum bisherigen „Als Admin buchen". Owner der Reservierung ist der Anfragende.
 *
 * WICHTIG: Diese Reservierung läuft aus einer LOGIN-FREIEN Seite mit konstruierter Admin-Session.
 * Vor Produktiv-Vertrauen auf media smoke-testen (Reservierung legt an, Storno entfernt sie, keine
 * Doppelbuchung). Bei JEDEM Fehler liefert Reserve() error != null → der Aufrufer macht die
 * Bestätigung rückgängig (kein bestätigter Termin ohne gesicherte Stunde).
 */
class ZhlEinfuehrungReservation
{
    /**
     * @return array{skipped:bool, ref:?string, error:?string}
     *   skipped=true: Gerät ist nicht blockend → keine Reservierung nötig (kein Fehler).
     *   error!=null: Reservierung fehlgeschlagen/abgelehnt → Bestätigung rückabwickeln.
     */
    public static function Reserve($db, array $request, array $offer): array
    {
        $resourceId = (int)($request['resource_id'] ?? 0);
        if ($resourceId <= 0 || ($request['kind'] ?? 'single') !== 'single') {
            return ['skipped' => true, 'ref' => null, 'error' => null];
        }
        if (!self::isSlotMode($db, $resourceId)) {
            return ['skipped' => true, 'ref' => null, 'error' => null];
        }
        $sess = self::buildAdminSession($db, (int)$offer['instructor_uid']);
        if ($sess === null) {
            return ['skipped' => false, 'ref' => null, 'error' => 'Einweiser-Session nicht verfügbar'];
        }
        $tz = $sess->Timezone !== '' ? $sess->Timezone : 'Europe/Berlin';
        try {
            $b = Date::Parse((string)$offer['start_utc'], 'UTC')->ToTimezone($tz);
            $e = Date::Parse((string)$offer['end_utc'], 'UTC')->ToTimezone($tz);
        } catch (Throwable $x) {
            return ['skipped' => false, 'ref' => null, 'error' => 'Einführungs-Zeit nicht lesbar'];
        }
        require_once(ROOT_DIR . 'Presenters/Reservation/ReservationPresenterFactory.php');
        require_once(ROOT_DIR . 'Presenters/ZhlReservationFacade.php');
        $label = (string)($request['label'] ?? 'Gerät');
        $facade = new ZhlReservationFacade(
            (int)$request['user_id'],
            $resourceId,
            'Einführung ' . $label,
            'Pflicht-Einführung (das Gerät ist für diese Stunde reserviert) — Terminwunsch #' . (int)$request['id'],
            $b->Format('Y-m-d'),
            $b->Format('H:i'),
            $e->Format('Y-m-d'),
            $e->Format('H:i'),
            [] // wie der bisherige Admin-Buchpfad: ohne Zusatz-Attribute
        );
        try {
            $factory = new ReservationPresenterFactory();
            $presenter = $factory->Create($facade, $sess);
            $series = $presenter->BuildReservation();
            $presenter->HandleReservation($series);
        } catch (Throwable $x) {
            Log::Error('ZHL-Einf-Reservierung: Handler-Fehler (req=%s): %s', (int)$request['id'], $x);
            return ['skipped' => false, 'ref' => null, 'error' => 'Reservierung fehlgeschlagen: ' . $x->getMessage()];
        }
        if (!$facade->WasSaved()) {
            $errs = $facade->GetErrors();
            return ['skipped' => false, 'ref' => null, 'error' => empty($errs) ? 'Stunde belegt/abgelehnt' : implode(' · ', $errs)];
        }
        return ['skipped' => false, 'ref' => (string)$facade->ReferenceNumber(), 'error' => null];
    }

    /** Native Einführungs-Reservierung wieder absagen (Storno). @return bool true wenn weg/erfolgreich. */
    public static function Cancel($db, string $ref, int $actorUid): bool
    {
        if (trim($ref) === '') {
            return true;
        }
        $sess = self::buildAdminSession($db, $actorUid);
        if ($sess === null) {
            return false;
        }
        try {
            require_once(ROOT_DIR . 'Domain/Access/namespace.php');
            $repo = new ReservationRepository();
            $existing = $repo->LoadByReferenceNumber($ref);
            if ($existing === null) {
                return true; // schon weg
            }
            $existing->ApplyChangesTo(SeriesUpdateScope::FullSeries);
            $existing->Delete($sess);
            $repo->Delete($existing);
            return true;
        } catch (Throwable $x) {
            Log::Error('ZHL-Einf-Reservierung: Storno fehlgeschlagen (ref=%s): %s', $ref, $x);
            return false;
        }
    }

    private static function isSlotMode($db, int $resourceId): bool
    {
        try {
            $cmd = new AdHocCommand('SELECT booking_mode FROM zhl_uebergabe WHERE resource_id = @resid LIMIT 1');
            $cmd->AddParameter(new Parameter('@resid', $resourceId));
            $reader = $db->Query($cmd);
            $row = $reader->GetRow();
            $reader->Free();
            return $row !== false && (string)($row['booking_mode'] ?? 'day') === 'slot';
        } catch (Throwable $x) {
            return false;
        }
    }

    /** Admin-Session (Akteur, umgeht Fristen) aus einem Admin-User bauen. */
    private static function buildAdminSession($db, int $uid): ?UserSession
    {
        if ($uid <= 0) {
            return null;
        }
        try {
            $cmd = new AdHocCommand('SELECT fname, lname, email, timezone, language FROM users WHERE user_id = @uid');
            $cmd->AddParameter(new Parameter('@uid', $uid));
            $reader = $db->Query($cmd);
            $row = $reader->GetRow();
            $reader->Free();
            if ($row === false) {
                return null;
            }
            $s = new UserSession($uid);
            $s->FirstName = (string)($row['fname'] ?? '');
            $s->LastName = (string)($row['lname'] ?? '');
            $s->Email = (string)($row['email'] ?? '');
            $s->Timezone = ((string)($row['timezone'] ?? '')) !== '' ? (string)$row['timezone'] : 'Europe/Berlin';
            $s->LanguageCode = (string)($row['language'] ?? '');
            $s->IsAdmin = true; // Instructor ist via ListAdmins als Application-Admin verifiziert
            return $s;
        } catch (Throwable $x) {
            Log::Error('ZHL-Einf-Reservierung: buildAdminSession(%d) fehlgeschlagen: %s', $uid, $x);
            return null;
        }
    }
}

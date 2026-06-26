<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'Presenters/ZhlTerminplaner.php');

/**
 * Presenter Ausleih-Detail. Lädt eine einzelne Buchung des angemeldeten Nutzers
 * über dieselbe permissionsichere Quelle wie „Meine Buchungen"
 * (ReservationViewRepository::GetReservations, OWNER), gefiltert auf die
 * gewünschte reference_number. Geräteliste = konsolidierte Ressourcennamen;
 * Abholung/Rückgabe (falls vorhanden) aus zhl_booking_handover. Read-only.
 */
class ZhlBookingDetailPresenter
{
    private const WINDOW_PAST_DAYS = 730;
    private const WINDOW_FUTURE_DAYS = 730;

    /** @var IZhlBookingDetailPage */
    private $page;

    /** @var IReservationViewRepository */
    private $repository;

    public function __construct(IZhlBookingDetailPage $page, ?IReservationViewRepository $repository = null)
    {
        $this->page = $page;
        $this->repository = $repository ?? new ReservationViewRepository();
    }

    public function PageLoad(UserSession $user)
    {
        $ref = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
        $this->render($user, $ref, '');
    }

    /**
     * Detailseite rendern (GET sowie Re-Render nach fehlgeschlagenem Storno mit Fehlerhinweis).
     * @param string $flashError optionaler Fehlertext oben auf der Seite
     */
    private function render(UserSession $user, string $ref, string $flashError)
    {
        if ($ref === '') {
            $this->page->RedirectToBookings();
            return;
        }

        $tz = $user->Timezone;
        $now = Date::Now();
        $match = $this->findOwnReservation($user, $ref, $now);
        if ($match === null) {
            // Nicht gefunden oder nicht eigene Buchung → zurück zur Übersicht.
            $this->page->RedirectToBookings();
            return;
        }

        // Storno nur, solange die Reservierung (inkl. evtl. Abholtag) noch nicht begonnen hat UND die
        // Abholung noch nicht ansteht/lief — identische Bedingung wie im POST-Handler HandleCancel.
        $canCancel = $match->StartDate->GreaterThan($now)
            && ($this->pickupStarted(ServiceLocator::GetDatabase(), $ref, $now) === false);

        $startLocal = $match->StartDate->ToTimezone($tz);
        $endLocal = $match->EndDate->ToTimezone($tz);

        if ($match->EndDate->LessThan($now)) {
            $state = 'muted';
            $statusLabel = 'Abgeschlossen';
        } elseif ($match->StartDate->GreaterThan($now)) {
            $state = 'ok';
            $statusLabel = 'Bestätigt';
        } else {
            $state = 'warn';
            $statusLabel = 'Läuft';
        }

        $resourceNames = [];
        if (is_array($match->ResourceNames) && count($match->ResourceNames) > 0) {
            $resourceNames = $match->ResourceNames;
        } elseif (!empty($match->ResourceName)) {
            $resourceNames = [$match->ResourceName];
        }
        $deviceCount = count($resourceNames);

        $title = trim((string)$match->Title);
        if ($title === '') {
            $title = $deviceCount > 0 ? $resourceNames[0] : 'Buchung';
        }

        // Geräte mit resource_id + Einführungsstatus laden (für die Verknüpfung „welche
        // Einführung gehört zu welchem Gerät"). Fällt die DB-Quelle aus, bleiben die
        // konsolidierten Namen ohne Status erhalten.
        $devices = $this->loadDevicesWithEinf($ref, (int)$user->UserId, $tz);
        if (empty($devices)) {
            foreach ($resourceNames as $name) {
                $devices[] = ['name' => (string)$name, 'einfLabel' => '', 'einfState' => ''];
            }
        } else {
            $deviceCount = count($devices);
        }

        $createdLabel = '';
        if ($match->CreatedDate instanceof Date && !($match->CreatedDate instanceof NullDate)) {
            $createdLabel = $match->CreatedDate->ToTimezone($tz)->Format('d.m.Y');
        }

        $handover = $this->loadHandover($ref, $tz);

        $this->page->BindDetail([
            'ref' => $ref,
            'title' => $title,
            'description' => trim((string)$match->Description),
            'state' => $state,
            'statusLabel' => $statusLabel,
            'deviceCount' => $deviceCount,
            'isBundle' => $deviceCount > 1,
            'periodLabel' => $startLocal->Format('d.m.Y, H:i') . ' – ' . $endLocal->Format('d.m.Y, H:i') . ' Uhr',
            'createdLabel' => $createdLabel,
            'devices' => $devices,
            'pickupLabel' => $handover['pickup'],
            'returnLabel' => $handover['return'],
            'hasHandover' => ($handover['pickup'] !== '' || $handover['return'] !== ''),
            'canCancel' => $canCancel,
            'flashError' => $flashError,
        ]);
    }

    /** Eigene Reservierung (OWNER-Sicht) zur reference_number finden, sonst null. */
    private function findOwnReservation(UserSession $user, string $ref, Date $now)
    {
        $start = $now->AddDays(-self::WINDOW_PAST_DAYS);
        $end = $now->AddDays(self::WINDOW_FUTURE_DAYS);
        $reservations = $this->repository->GetReservations(
            $start,
            $end,
            $user->UserId,
            ReservationUserLevel::OWNER,
            ReservationViewRepository::ALL_SCHEDULES,
            ReservationViewRepository::ALL_RESOURCES,
            true
        );
        foreach ($reservations as $r) {
            if ((string)$r->ReferenceNumber === $ref) {
                return $r;
            }
        }
        return null;
    }

    /**
     * POST: Ausleihe stornieren. Reihenfolge bewusst:
     * 1) Terminplaner-booking_ids + Geräte-IDs AUSLESEN (vor dem Löschen),
     * 2) native Reservierung löschen (Kern-Aktion — schlägt das fehl, bleibt alles andere unberührt),
     * 3) Terminplaner-Slots (Einführung/Abholung) stornieren (best effort → Warnungen),
     * 4) lokale Übergabe-Zeilen löschen + offene Zertifikats-Bestätigung verwerfen.
     */
    public function HandleCancel(UserSession $user)
    {
        $ref = isset($_POST['id']) ? trim((string)$_POST['id']) : '';
        if ($ref === '') {
            $this->page->RedirectToBookings();
            return;
        }

        $now = Date::Now();
        $match = $this->findOwnReservation($user, $ref, $now);
        if ($match === null) {
            $this->page->RedirectToBookings();
            return;
        }

        // Policy (fail-closed): nur stornierbar, solange die Reservierung noch nicht begonnen hat UND die
        // Abholung noch nicht ansteht/lief. Die Abhol-Zeit kann am selben Tag VOR dem Reservierungsbeginn
        // liegen → separater Pickup-Check (Codex-Fund). Lässt sich der Pickup-Status nicht ermitteln
        // (null), wird NICHT storniert (kein Fail-open).
        $db = ServiceLocator::GetDatabase();
        $pickupStarted = $this->pickupStarted($db, $ref, $now);
        if (!$match->StartDate->GreaterThan($now) || $pickupStarted !== false) {
            $msg = ($pickupStarted === null)
                ? 'Die Stornierung konnte gerade nicht geprüft werden. Bitte versuche es in einem Moment erneut.'
                : 'Diese Ausleihe hat bereits begonnen (oder die Abholung steht unmittelbar an) und kann nicht mehr selbst storniert werden. Bitte wende dich an das ZHL-Team.';
            $this->render($user, $ref, $msg);
            return;
        }

        // (1) Übergabe-Schnappschuss lesen, BEVOR die Reservierung weg ist (für die TP-Stornos). Kann er
        //     NICHT gelesen werden (null), brechen wir VOR dem Löschen ab — sonst würden die Terminplaner-
        //     Termine ohne Storno verwaisen (Codex-Fund: fail-closed statt „alles löschen").
        $handover = $this->loadHandoverRows($db, $ref); // [{id, type, booking_id, resource_id}] | null
        if ($handover === null) {
            $this->render($user, $ref, 'Die Stornierung konnte gerade nicht vorbereitet werden. Bitte versuche es in einem Moment erneut.');
            return;
        }

        // (2) Native Reservierung löschen (Kern-Aktion). Scheitert sie → Abbruch, nichts sonst verändert.
        try {
            $resRepo = new ReservationRepository();
            $existing = $resRepo->LoadByReferenceNumber($ref);
            if ($existing === null) {
                $this->render($user, $ref, 'Die Reservierung konnte nicht geladen werden. Bitte erneut versuchen oder das ZHL-Team kontaktieren.');
                return;
            }
            $existing->ApplyChangesTo(SeriesUpdateScope::FullSeries);
            $existing->Delete($user);
            $resRepo->Delete($existing);
        } catch (Exception $ex) {
            Log::Error('ZHL-Storno: Reservierung löschen fehlgeschlagen (ref=%s): %s', $ref, $ex);
            $this->render($user, $ref, 'Die Ausleihe konnte nicht storniert werden. Bitte erneut versuchen oder das ZHL-Team kontaktieren.');
            return;
        }

        // (3) Terminplaner-Slots stornieren (best effort). Eine fehlgeschlagene Zeile wird NICHT lokal
        //     gelöscht — so bleibt ihre terminplaner_booking_id für die manuelle Nacharbeit erhalten.
        $warnings = [];
        $failedRowIds = [];
        foreach ($handover as $h) {
            if ($h['booking_id'] === '') {
                continue; // Rückgabe-Zeile: kein Terminplaner-Slot
            }
            $resp = ZhlTerminplaner::Request('POST', '/api/cancel_slot.php', [], [
                'booking_id' => $h['booking_id'],
                'reason' => 'Ausleihe vom Nutzer storniert',
            ]);
            if (!$resp || (($resp['status'] ?? '') !== 'ok')) {
                $failedRowIds[] = $h['id'];
                $label = $h['type'] === 'einf' ? 'Einführungstermin' : 'Abholtermin';
                $warnings[] = $label . ' konnte nicht automatisch abgesagt werden — das ZHL-Team wird informiert.';
                Log::Error('ZHL-Storno: Terminplaner-cancel fehlgeschlagen (ref=%s, booking=%s, type=%s)', $ref, $h['booking_id'], $h['type']);
            }
        }

        // (4) Aufräumen: Übergabe-Zeilen löschen (außer fehlgeschlagene). Eine offene zhl_cert_confirmation
        //     wird bewusst NICHT automatisch verworfen: sie trägt keinen Buchungsbezug, ein Match nur über
        //     user+resource könnte die offene Einführung einer ANDEREN Buchung treffen (Codex-Fund). Der
        //     substanzielle Teil — der Einführungs-TERMIN — ist oben via cancel_slot storniert; eine
        //     verbleibende pending-Bestätigung ist harmlos (der Einweiser bekam eine CANCEL-Einladung).
        $this->cleanupLocal($db, $ref, $failedRowIds);

        $this->page->RedirectAfterCancel(implode(' ', $warnings));
    }

    /**
     * Übergabe-Zeilen dieser Reservierung als Schnappschuss (vor dem Löschen).
     * @return array[]|null [{id:int, type:string, booking_id:string, resource_id:int}] — null bei DB-Fehler
     *         (Aufrufer bricht dann VOR dem Löschen ab, damit Terminplaner-Termine nicht verwaisen).
     */
    private function loadHandoverRows($db, string $ref): ?array
    {
        try {
            $out = [];
            $cmd = new AdHocCommand('SELECT id, type, terminplaner_booking_id, resource_id FROM zhl_booking_handover WHERE reference_number = @ref');
            $cmd->AddParameter(new Parameter('@ref', $ref));
            $reader = $db->Query($cmd);
            while ($row = $reader->GetRow()) {
                $out[] = [
                    'id' => (int)$row['id'],
                    'type' => (string)$row['type'],
                    'booking_id' => ($row['terminplaner_booking_id'] !== null && $row['terminplaner_booking_id'] !== '') ? (string)$row['terminplaner_booking_id'] : '',
                    'resource_id' => $row['resource_id'] !== null ? (int)$row['resource_id'] : 0,
                ];
            }
            $reader->Free();
            return $out;
        } catch (Exception $e) {
            Log::Error('ZHL-Storno: Übergabe-Zeilen nicht lesbar (ref=%s): %s', $ref, $e->getMessage());
            return null;
        }
    }

    /**
     * Steht eine Abholung dieser Buchung bereits an / hat begonnen (scheduled_start_utc <= jetzt)?
     * @return bool|null true=ja, false=nein, null=unbekannt (DB-Fehler) → Aufrufer behandelt fail-closed.
     */
    private function pickupStarted($db, string $ref, Date $now): ?bool
    {
        try {
            $nowUtc = $now->ToTimezone('UTC')->Format('Y-m-d H:i:s');
            $cmd = new AdHocCommand("SELECT COUNT(*) AS n FROM zhl_booking_handover WHERE reference_number = @ref AND type = 'pickup' AND scheduled_start_utc IS NOT NULL AND scheduled_start_utc <= @now");
            $cmd->AddParameter(new Parameter('@ref', $ref));
            $cmd->AddParameter(new Parameter('@now', $nowUtc));
            $reader = $db->Query($cmd);
            $row = $reader->GetRow();
            $reader->Free();
            return $row && (int)$row['n'] > 0;
        } catch (Exception $e) {
            Log::Error('ZHL-Storno: Pickup-Status nicht prüfbar (ref=%s): %s', $ref, $e->getMessage());
            return null; // fail-closed: Aufrufer storniert dann NICHT
        }
    }

    /**
     * Übergabe-Zeilen der Buchung löschen — außer fehlgeschlagenen (deren terminplaner_booking_id für die
     * manuelle Nacharbeit erhalten bleibt). Best effort. zhl_cert_confirmation wird bewusst nicht angefasst.
     * @param int[] $failedRowIds Übergabe-Zeilen, deren Terminplaner-Storno fehlschlug → BEHALTEN
     */
    private function cleanupLocal($db, string $ref, array $failedRowIds): void
    {
        try {
            if (empty($failedRowIds)) {
                $del = new AdHocCommand('DELETE FROM zhl_booking_handover WHERE reference_number = @ref');
            } else {
                $keep = implode(',', array_map('intval', $failedRowIds));
                $del = new AdHocCommand('DELETE FROM zhl_booking_handover WHERE reference_number = @ref AND id NOT IN (' . $keep . ')');
            }
            $del->AddParameter(new Parameter('@ref', $ref));
            $db->Execute($del);
        } catch (Exception $e) {
            Log::Error('ZHL-Storno: Übergabe-Zeilen löschen fehlgeschlagen (ref=%s): %s', $ref, $e->getMessage());
        }
    }

    /**
     * Geräte dieser Buchung (resource_id + Name) inkl. Einführungsstatus je Gerät:
     *   - certified: Nutzer hält das Zertifikat (zhl_certificate, nicht abgelaufen) → Einführung absolviert
     *   - pending:   offene Bestätigung (zhl_cert_confirmation) → Einführungstermin gebucht
     *   - sonst nach Pflicht (zhl_uebergabe.einfuehrung): notwendig/moeglich/keine
     * Best effort — fällt etwas aus, gibt die Methode [] zurück (Aufrufer nutzt Namens-Fallback).
     *
     * @return array[] [{resourceId, name, einf, certified, pending, einfDate, einfLabel, einfState}]
     */
    private function loadDevicesWithEinf(string $ref, int $userId, $tz): array
    {
        $devices = [];
        try {
            $db = ServiceLocator::GetDatabase();

            $cmd = new AdHocCommand(
                // Kein DISTINCT: die Dedup passiert über $devices[$rid] in PHP; mit DISTINCT
                // würde MySQL 8 das ORDER BY auf die nicht-selektierte resource_level_id ablehnen.
                'SELECT rr.resource_id, r.name FROM reservation_instances ri ' .
                'JOIN reservation_resources rr ON rr.series_id = ri.series_id ' .
                'JOIN resources r ON r.resource_id = rr.resource_id ' .
                'WHERE ri.reference_number = @ref ORDER BY rr.resource_level_id, r.name'
            );
            $cmd->AddParameter(new Parameter('@ref', $ref));
            $reader = $db->Query($cmd);
            while ($row = $reader->GetRow()) {
                $rid = (int)$row['resource_id'];
                $devices[$rid] = [
                    'resourceId' => $rid,
                    'name' => (string)$row['name'],
                    'einf' => 'keine',
                    'certified' => false,
                    'pending' => false,
                    'einfDate' => '',
                ];
            }
            $reader->Free();

            if (empty($devices)) {
                return [];
            }
            $in = implode(',', array_map('intval', array_keys($devices)));

            // Einführungspflicht je Gerät.
            $reader = $db->Query(new AdHocCommand('SELECT resource_id, einfuehrung FROM zhl_uebergabe WHERE resource_id IN (' . $in . ')'));
            while ($row = $reader->GetRow()) {
                $rid = (int)$row['resource_id'];
                if (isset($devices[$rid])) {
                    $devices[$rid]['einf'] = (string)$row['einfuehrung'];
                }
            }
            $reader->Free();

            // Bereits zertifiziert (Einführung absolviert, nicht abgelaufen).
            $cmd = new AdHocCommand('SELECT resource_id FROM zhl_certificate WHERE user_id = @u AND resource_id IN (' . $in . ') AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())');
            $cmd->AddParameter(new Parameter('@u', $userId));
            $reader = $db->Query($cmd);
            while ($row = $reader->GetRow()) {
                $rid = (int)$row['resource_id'];
                if (isset($devices[$rid])) {
                    $devices[$rid]['certified'] = true;
                }
            }
            $reader->Free();

            // Offene Einführungs-Bestätigung (Termin gebucht, Einweiser-Bestätigung ausstehend).
            $cmd = new AdHocCommand("SELECT resource_id FROM zhl_cert_confirmation WHERE user_id = @u AND status = 'pending' AND resource_id IN (" . $in . ')');
            $cmd->AddParameter(new Parameter('@u', $userId));
            $reader = $db->Query($cmd);
            while ($row = $reader->GetRow()) {
                $rid = (int)$row['resource_id'];
                if (isset($devices[$rid])) {
                    $devices[$rid]['pending'] = true;
                }
            }
            $reader->Free();

            // Lokal gespeicherter Einführungstermin (mit Datum) je Gerät für diese Buchung.
            $cmd = new AdHocCommand("SELECT resource_id, scheduled_start_utc FROM zhl_booking_handover WHERE reference_number = @ref AND type = 'einf' AND resource_id IN (" . $in . ') ORDER BY id ASC');
            $cmd->AddParameter(new Parameter('@ref', $ref));
            $reader = $db->Query($cmd);
            while ($row = $reader->GetRow()) {
                $rid = (int)$row['resource_id'];
                // ORDER BY id ASC + nur setzen, wenn noch leer → früheste Zeile je Gerät gewinnt.
                if (isset($devices[$rid]) && $devices[$rid]['einfDate'] === '' && !empty($row['scheduled_start_utc'])) {
                    $devices[$rid]['einfDate'] = Date::Parse((string)$row['scheduled_start_utc'], 'UTC')->ToTimezone($tz)->Format('d.m.Y, H:i');
                }
            }
            $reader->Free();
        } catch (Exception $e) {
            Log::Debug('ZHL-Detail: Geräte/Einführungsstatus nicht ladbar: %s', $e->getMessage());
            return [];
        }

        $out = [];
        foreach ($devices as $d) {
            if ($d['certified']) {
                $d['einfLabel'] = 'Einführung absolviert';
                $d['einfState'] = 'ok';
            } elseif ($d['einfDate'] !== '') {
                $d['einfLabel'] = 'Einführungstermin: ' . $d['einfDate'] . ' Uhr';
                $d['einfState'] = 'info';
            } elseif ($d['pending']) {
                $d['einfLabel'] = 'Einführungstermin gebucht – Bestätigung ausstehend';
                $d['einfState'] = 'warn';
            } elseif ($d['einf'] === 'notwendig') {
                $d['einfLabel'] = 'Einführung erforderlich';
                $d['einfState'] = 'warn';
            } elseif ($d['einf'] === 'moeglich') {
                $d['einfLabel'] = 'Kurze Besprechung empfohlen';
                $d['einfState'] = 'info';
            } else {
                $d['einfLabel'] = 'Keine Einführung nötig';
                $d['einfState'] = 'muted';
            }
            $out[] = $d;
        }
        return $out;
    }

    /**
     * Abhol-/Rückgabezeiten aus zhl_booking_handover (falls vorhanden). Best effort.
     * @return array{pickup:string, return:string}
     */
    private function loadHandover(string $ref, $tz): array
    {
        $out = ['pickup' => '', 'return' => ''];
        try {
            $db = ServiceLocator::GetDatabase();
            $cmd = new AdHocCommand(
                'SELECT type, scheduled_start_utc FROM zhl_booking_handover ' .
                'WHERE reference_number = @ref ORDER BY id ASC'
            );
            $cmd->AddParameter(new Parameter('@ref', $ref));
            $reader = $db->Query($cmd);
            while ($row = $reader->GetRow()) {
                if (empty($row['scheduled_start_utc'])) {
                    continue;
                }
                $when = Date::Parse((string)$row['scheduled_start_utc'], 'UTC')->ToTimezone($tz)->Format('d.m.Y, H:i') . ' Uhr';
                if ((string)$row['type'] === 'pickup') {
                    $out['pickup'] = $when;
                } elseif ((string)$row['type'] === 'return') {
                    $out['return'] = $when;
                }
            }
            $reader->Free();
        } catch (Exception $e) {
            // Übergabe-Modul optional.
            return $out;
        }
        return $out;
    }
}

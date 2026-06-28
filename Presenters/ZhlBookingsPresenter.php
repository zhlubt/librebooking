<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Zhl/ZhlTerminRequest.php');

/**
 * Presenter „Meine Buchungen". Liest die eigenen Reservierungen des angemeldeten
 * Nutzers über dieselbe permissionsichere Quelle wie der persönliche Kalender
 * (ReservationViewRepository::GetReservations mit userLevel OWNER), konsolidiert
 * mehrteilige Buchungen nach reference_number und gruppiert sie nach Zeitbezug.
 * Read-only — keine Schreibzugriffe.
 */
class ZhlBookingsPresenter
{
    private const WINDOW_PAST_DAYS = 365;
    private const WINDOW_FUTURE_DAYS = 365;

    /** @var IZhlBookingsPage */
    private $page;

    /** @var IReservationViewRepository */
    private $repository;

    public function __construct(IZhlBookingsPage $page, ?IReservationViewRepository $repository = null)
    {
        $this->page = $page;
        $this->repository = $repository ?? new ReservationViewRepository();
    }

    public function PageLoad(UserSession $user)
    {
        $tz = $user->Timezone;
        $now = Date::Now();
        $start = $now->AddDays(-self::WINDOW_PAST_DAYS);
        $end = $now->AddDays(self::WINDOW_FUTURE_DAYS);

        // Gleiche Quelle wie PersonalCalendarPresenter::BindEvents: OWNER-Sicht,
        // nach reference_number konsolidiert (mehrere Geräte einer Buchung = eine Zeile).
        $reservations = $this->repository->GetReservations(
            $start,
            $end,
            $user->UserId,
            ReservationUserLevel::OWNER,
            ReservationViewRepository::ALL_SCHEDULES,
            ReservationViewRepository::ALL_RESOURCES,
            true
        );

        $current = [];
        $upcoming = [];
        $past = [];

        // Abholzeiten (falls leicht verfügbar) je reference_number aus dem Übergabe-Modul.
        $pickups = $this->loadPickupTimes($reservations, $tz);

        foreach ($reservations as $r) {
            $row = $this->toRow($r, $tz, $now, $pickups);
            if ($row['group'] === 'current') {
                $current[] = $row;
            } elseif ($row['group'] === 'upcoming') {
                $upcoming[] = $row;
            } else {
                $past[] = $row;
            }
        }

        // Stornierte Buchungen (eigene Historie) aus zhl_cancelled_booking — die native Reservierung
        // ist beim Stornieren gelöscht worden, daher separate Quelle. Bereits absteigend (neueste zuerst).
        $cancelled = $this->loadCancelled($user->UserId, $tz, $now);

        // B: offene Wunschtermin-Anfragen des Nutzers (noch nicht gebucht/abgelehnt/storniert).
        $openRequests = $this->loadOpenRequests((int)$user->UserId, $tz);

        // Anstehend aufsteigend, Laufend aufsteigend, Vergangen absteigend (neueste zuerst).
        usort($current, fn($a, $b) => strcmp($a['startSort'], $b['startSort']));
        usort($upcoming, fn($a, $b) => strcmp($a['startSort'], $b['startSort']));
        usort($past, fn($a, $b) => strcmp($b['startSort'], $a['startSort']));

        // Standard-Tab = erste nicht-leere Gruppe: Laufende sind selten, die
        // meisten eigenen Buchungen liegen in „Anstehend". Sonst öffnet die Seite
        // auf einem leeren „Aktuell"-Tab und wirkt leer, obwohl Buchungen da sind.
        if (count($current) > 0) {
            $defaultGroup = 'current';
        } elseif (count($upcoming) > 0) {
            $defaultGroup = 'upcoming';
        } elseif (count($past) > 0) {
            $defaultGroup = 'past';
        } elseif (count($cancelled) > 0) {
            $defaultGroup = 'cancelled';
        } else {
            $defaultGroup = 'past';
        }

        $this->page->BindBookings([
            'current' => $current,
            'upcoming' => $upcoming,
            'past' => $past,
            'cancelled' => $cancelled,
            'currentCount' => count($current),
            'upcomingCount' => count($upcoming),
            'pastCount' => count($past),
            'cancelledCount' => count($cancelled),
            'defaultGroup' => $defaultGroup,
            'hasAny' => (count($current) + count($upcoming) + count($past) + count($cancelled)) > 0,
            'openRequests' => $openRequests,
            'openRequestCount' => count($openRequests),
        ]);
    }

    /**
     * Offene Wunschtermin-Anfragen des Nutzers, aufbereitet für die Anzeige (Label, Wunsch-Zeitraum
     * lokal, Datum der Anfrage). Best effort — fehlt die Tabelle, leere Liste.
     * @return array[]
     */
    private function loadOpenRequests(int $userId, $tz): array
    {
        $out = [];
        $fmt = function ($utc) use ($tz): string {
            if ($utc === null || $utc === '') {
                return '';
            }
            try {
                return Date::Parse((string)$utc, 'UTC')->ToTimezone($tz)->Format('d.m.Y');
            } catch (Exception $e) {
                return '';
            }
        };
        foreach (ZhlTerminRequest::ListOpenForUser(ServiceLocator::GetDatabase(), $userId) as $r) {
            $out[] = [
                'id' => (int)$r['id'],
                'label' => (string)$r['label'],
                'isBundle' => ($r['kind'] ?? 'single') === 'bundle',
                'fromLabel' => $fmt($r['desired_start'] ?? null),
                'toLabel' => $fmt($r['desired_end'] ?? null),
                'projectTitle' => (string)($r['project_title'] ?? ''),
                'createdLabel' => $fmt($r['created_at'] ?? null),
                'status' => (string)($r['status'] ?? 'open'),
                'isOffered' => ((string)($r['status'] ?? '')) === 'offered',
                'token' => (string)($r['accept_token'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Eigene stornierte Buchungen aus zhl_cancelled_booking, neueste zuerst, gleiche Zeilenform wie toRow.
     * Fenster wie „Vergangen": nur innerhalb der letzten WINDOW_PAST_DAYS storniert. Best effort —
     * fehlt die Tabelle, kommt eine leere Liste zurück (Seite funktioniert ohne den Tab weiter).
     * @return array[]
     */
    private function loadCancelled(int $userId, $tz, Date $now): array
    {
        $rows = [];
        $sinceUtc = $now->AddDays(-self::WINDOW_PAST_DAYS)->ToTimezone('UTC')->Format('Y-m-d H:i:s');
        try {
            $db = ServiceLocator::GetDatabase();
            $cmd = new AdHocCommand(
                'SELECT reference_number, title, resource_names, device_count, start_utc, end_utc, cancelled_at ' .
                'FROM zhl_cancelled_booking WHERE user_id = @uid AND cancelled_at >= @since ' .
                'ORDER BY cancelled_at DESC'
            );
            $cmd->AddParameter(new Parameter('@uid', $userId));
            $cmd->AddParameter(new Parameter('@since', $sinceUtc));
            $reader = $db->Query($cmd);
            while ($row = $reader->GetRow()) {
                $rows[] = $this->toCancelledRow($row, $tz);
            }
            $reader->Free();
        } catch (Exception $e) {
            return [];
        }
        return $rows;
    }

    /**
     * Eine Zeile aus zhl_cancelled_booking in die Anzeigeform bringen (analog zu toRow).
     * @param array $row
     * @return array
     */
    private function toCancelledRow(array $row, $tz): array
    {
        $deviceCount = (int)($row['device_count'] ?? 0);
        if ($deviceCount <= 1) {
            $itemsLabel = 'Einzelgerät';
            $kind = 'device';
        } else {
            $itemsLabel = $deviceCount . ' Geräte';
            $kind = 'bundle';
        }

        // Zeitraum aus den gespeicherten UTC-Eckdaten rekonstruieren (gleiches Format wie toRow).
        $range = '';
        if (!empty($row['start_utc']) && !empty($row['end_utc'])) {
            $startLocal = Date::Parse((string)$row['start_utc'], 'UTC')->ToTimezone($tz);
            $endLocal = Date::Parse((string)$row['end_utc'], 'UTC')->ToTimezone($tz);
            if ($startLocal->Format('Y-m-d') === $endLocal->Format('Y-m-d')) {
                $range = $startLocal->Format('d.m.Y') . ', ' . $startLocal->Format('H:i') . '–' . $endLocal->Format('H:i') . ' Uhr';
            } else {
                $range = $startLocal->Format('d.m.') . ' – ' . $endLocal->Format('d.m.Y');
            }
        }

        // „Storniert am …" als Zusatzinfo (steht an der Stelle, an der sonst die Abholzeit stünde).
        $cancelledLabel = '';
        if (!empty($row['cancelled_at'])) {
            $when = Date::Parse((string)$row['cancelled_at'], 'UTC')->ToTimezone($tz);
            $cancelledLabel = 'Storniert am ' . $when->Format('d.m.Y');
        }

        $title = trim((string)($row['title'] ?? ''));
        if ($title === '') {
            $title = 'Buchung';
        }

        return [
            'ref' => (string)($row['reference_number'] ?? ''),
            'kind' => $kind,
            'title' => $title,
            'range' => $range,
            'items' => $itemsLabel,
            'deviceCount' => $deviceCount,
            'pickup' => $cancelledLabel,
            'state' => 'err',
            'label' => 'Storniert',
            'group' => 'cancelled',
            'startSort' => (string)($row['cancelled_at'] ?? ''),
        ];
    }

    /**
     * @param ReservationItemView $r
     * @return array
     */
    private function toRow($r, $tz, Date $now, array $pickups): array
    {
        $startLocal = $r->StartDate->ToTimezone($tz);
        $endLocal = $r->EndDate->ToTimezone($tz);

        // Gruppe nach Zeitbezug bestimmen.
        if ($r->EndDate->LessThan($now)) {
            $group = 'past';
            $state = 'muted';
            $label = 'Abgeschlossen';
        } elseif ($r->StartDate->GreaterThan($now)) {
            $group = 'upcoming';
            $state = 'ok';
            $label = 'Bestätigt';
        } else {
            $group = 'current';
            $state = 'warn';
            $label = 'Läuft';
        }

        // Gerätezahl/-namen: bei konsolidierten Buchungen liegen mehrere Namen vor.
        $resourceNames = [];
        if (is_array($r->ResourceNames) && count($r->ResourceNames) > 0) {
            $resourceNames = $r->ResourceNames;
        } elseif (!empty($r->ResourceName)) {
            $resourceNames = [$r->ResourceName];
        }
        $deviceCount = count($resourceNames);
        if ($deviceCount <= 1) {
            $itemsLabel = 'Einzelgerät';
            $kind = 'device';
        } else {
            $itemsLabel = $deviceCount . ' Geräte';
            $kind = 'bundle';
        }

        // Titel: Reservierungstitel, sonst erstes Gerät.
        $title = trim((string)$r->Title);
        if ($title === '') {
            $title = $deviceCount > 0 ? $resourceNames[0] : 'Buchung';
        }

        // Datumsbereich (Sie-Form, deutsch).
        if ($startLocal->Format('Y-m-d') === $endLocal->Format('Y-m-d')) {
            $range = $startLocal->Format('d.m.Y') . ', ' . $startLocal->Format('H:i') . '–' . $endLocal->Format('H:i') . ' Uhr';
        } else {
            $range = $startLocal->Format('d.m.') . ' – ' . $endLocal->Format('d.m.Y');
        }

        $ref = (string)$r->ReferenceNumber;
        $pickup = $pickups[$ref] ?? '';

        return [
            'ref' => $ref,
            'kind' => $kind,
            'title' => $title,
            'range' => $range,
            'items' => $itemsLabel,
            'deviceCount' => $deviceCount,
            'pickup' => $pickup,
            'state' => $state,
            'label' => $label,
            'group' => $group,
            'startSort' => $startLocal->Format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Abholzeiten je reference_number aus zhl_booking_handover (pickup-Zeile), falls vorhanden.
     * Best effort: fehlt die Tabelle/Spalte, wird leer zurückgegeben (kein Fehler).
     * @param ReservationItemView[] $reservations
     * @return array<string,string> reference_number => Abhol-Label
     */
    private function loadPickupTimes(array $reservations, $tz): array
    {
        $refs = [];
        foreach ($reservations as $r) {
            $ref = trim((string)$r->ReferenceNumber);
            if ($ref !== '') {
                $refs[$ref] = true;
            }
        }
        if (empty($refs)) {
            return [];
        }

        $out = [];
        try {
            $db = ServiceLocator::GetDatabase();
            foreach (array_keys($refs) as $ref) {
                $cmd = new AdHocCommand(
                    'SELECT scheduled_start_utc FROM zhl_booking_handover ' .
                    "WHERE reference_number = @ref AND type = 'pickup' " .
                    'ORDER BY id DESC LIMIT 1'
                );
                $cmd->AddParameter(new Parameter('@ref', $ref));
                $reader = $db->Query($cmd);
                $row = $reader->GetRow();
                $reader->Free();
                if ($row && !empty($row['scheduled_start_utc'])) {
                    $when = Date::Parse((string)$row['scheduled_start_utc'], 'UTC')->ToTimezone($tz);
                    $out[$ref] = 'Abholung ' . $when->Format('d.m.Y, H:i') . ' Uhr';
                }
            }
        } catch (Exception $e) {
            // Übergabe-Infos sind optional — ohne sie funktioniert die Übersicht trotzdem.
            return $out;
        }
        return $out;
    }
}

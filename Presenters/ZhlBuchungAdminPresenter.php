<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Web/zhl-handover-lib.php');
require_once(ROOT_DIR . 'Web/zhl-audit-lib.php');

/**
 * Presenter „Buchungsakte" — Admin-READ-ONLY-Sicht auf eine (auch fremde) Buchung:
 * Eckdaten + Ausleihende:r, Geräte mit Einführungs-/Zertifikatsstatus (Logik gespiegelt
 * aus ZhlBookingDetailPresenter::loadDevicesWithEinf, dort Owner-only), Übergabetermine
 * aus zhl_booking_handover. Für bereits stornierte Buchungen wird der Schnappschuss aus
 * zhl_cancelled_booking gezeigt (native Reservierung ist dann hart gelöscht).
 * Einzige Aktion ist der bestehende „Ausleihe beenden"-Pfad (zhl_handover_end_loan_now).
 */
class ZhlBuchungAdminPresenter
{
    /** @var IZhlBuchungAdminPage */
    private $page;

    public function __construct(IZhlBuchungAdminPage $page)
    {
        $this->page = $page;
    }

    /** @return bool false, wenn die Referenz weder als Buchung noch als Storno existiert (404). */
    public function PageLoad(UserSession $user, string $ref): bool
    {
        $tz = $user->Timezone;
        $pdo = zhl_handover_db();

        $st = $pdo->prepare(
            'SELECT ri.reference_number, ri.start_date, ri.end_date, ri.series_id,
                    rs.title, rs.description, rs.date_created, rs.status_id AS series_status,
                    u.user_id, u.fname, u.lname, u.email, u.phone, u.organization
             FROM reservation_instances ri
             JOIN reservation_series rs ON rs.series_id = ri.series_id
             JOIN users u ON u.user_id = rs.owner_id
             WHERE ri.reference_number = ?'
        );
        $st->execute([$ref]);
        $booking = $st->fetch();

        if (!is_array($booking)) {
            return $this->loadCancelledSnapshot($pdo, $ref, $tz);
        }

        $ownerId = (int)$booking['user_id'];
        $borrower = trim((string)$booking['fname'] . ' ' . (string)$booking['lname']);
        if ($borrower === '') {
            $borrower = (string)$booking['email'];
        }

        // Rückgabe-Status (done) + Ausleih-Zustand wie in der Geräteakte.
        $st = $pdo->prepare(
            "SELECT MAX(updated_at) FROM zhl_booking_handover
             WHERE type = 'return' AND status = 'done' AND reference_number = ?"
        );
        $st->execute([$ref]);
        $doneAt = $st->fetchColumn() ?: null;

        $nowUtc = gmdate('Y-m-d H:i:s');
        $startUtc = (string)$booking['start_date'];
        $endUtc = (string)$booking['end_date'];
        if ($startUtc > $nowUtc) {
            $stateLabel = 'geplant';
            $badge = 'badge-info';
        } elseif ($endUtc > $nowUtc) {
            $stateLabel = 'aktiv';
            $badge = 'badge-warn';
        } else {
            $stateLabel = $doneAt !== null ? 'zurückgegeben' : 'beendet';
            $badge = 'badge-ok';
        }

        $this->page->BindBuchung([
            'mode' => 'booking',
            'ref' => $ref,
            'title' => trim((string)$booking['title']),
            'beschreibung' => trim((string)($booking['description'] ?? '')),
            'startLabel' => $this->fmtLocal($startUtc, $tz),
            'endLabel' => $this->fmtLocal($endUtc, $tz),
            'createdLabel' => $this->fmtLocal((string)$booking['date_created'], $tz),
            'stateLabel' => $stateLabel,
            'badgeClass' => $badge,
            'returnedLabel' => $doneAt !== null ? $this->fmtLocal((string)$doneAt, $tz) : '',
            'borrower' => $borrower,
            'email' => (string)$booking['email'],
            'phone' => trim((string)($booking['phone'] ?? '')),
            'organization' => trim((string)($booking['organization'] ?? '')),
            'devices' => $this->loadDevices($pdo, $ref, $ownerId, $tz),
            'termine' => $this->loadTermine($pdo, $ref, $tz),
        ]);
        return true;
    }

    /** Storno-Schnappschuss aus zhl_cancelled_booking (Reservierung selbst ist hart gelöscht). */
    private function loadCancelledSnapshot(PDO $pdo, string $ref, string $tz): bool
    {
        try {
            $st = $pdo->prepare(
                'SELECT cb.title, cb.resource_names, cb.device_count, cb.start_utc, cb.end_utc,
                        cb.cancelled_at, u.fname, u.lname, u.email
                 FROM zhl_cancelled_booking cb
                 LEFT JOIN users u ON u.user_id = cb.user_id
                 WHERE cb.reference_number = ?
                 ORDER BY cb.cancelled_at DESC LIMIT 1'
            );
            $st->execute([$ref]);
            $row = $st->fetch();
        } catch (Throwable $e) {
            return false;
        }
        if (!is_array($row)) {
            return false;
        }

        $borrower = trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? ''));
        if ($borrower === '') {
            $borrower = (string)($row['email'] ?? '');
        }
        $names = array_filter(array_map('trim', preg_split('/\r?\n/', (string)$row['resource_names']) ?: []));

        $this->page->BindBuchung([
            'mode' => 'cancelled',
            'ref' => $ref,
            'title' => trim((string)$row['title']),
            'startLabel' => $this->fmtLocal((string)($row['start_utc'] ?? ''), $tz),
            'endLabel' => $this->fmtLocal((string)($row['end_utc'] ?? ''), $tz),
            'cancelledLabel' => $this->fmtLocal((string)$row['cancelled_at'], $tz),
            'borrower' => $borrower,
            'email' => (string)($row['email'] ?? ''),
            'deviceNames' => implode(', ', $names),
        ]);
        return true;
    }

    /**
     * Geräte der Buchung mit Einführungs-/Zertifikatsstatus des AUSLEIHENDEN (nicht des
     * Admins!). Status-Priorität wie auf der Owner-Detailseite: zertifiziert → lokaler
     * Einführungstermin → pending-Bestätigung → Pflicht laut zhl_uebergabe.
     */
    private function loadDevices(PDO $pdo, string $ref, int $ownerId, string $tz): array
    {
        $st = $pdo->prepare(
            'SELECT rr.resource_id, r.name FROM reservation_instances ri
             JOIN reservation_resources rr ON rr.series_id = ri.series_id
             JOIN resources r ON r.resource_id = rr.resource_id
             WHERE ri.reference_number = ? ORDER BY rr.resource_level_id, r.name'
        );
        $st->execute([$ref]);

        $devices = [];
        foreach ($st->fetchAll() as $row) {
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
        if (!$devices) {
            return [];
        }
        $in = implode(',', array_map('intval', array_keys($devices)));

        $q = $pdo->query("SELECT resource_id, einfuehrung FROM zhl_uebergabe WHERE resource_id IN ($in)");
        foreach ($q->fetchAll() as $row) {
            $rid = (int)$row['resource_id'];
            if (isset($devices[$rid])) {
                $devices[$rid]['einf'] = (string)$row['einfuehrung'];
            }
        }

        $st = $pdo->prepare(
            "SELECT resource_id FROM zhl_certificate
             WHERE user_id = ? AND resource_id IN ($in)
               AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())"
        );
        $st->execute([$ownerId]);
        foreach ($st->fetchAll() as $row) {
            $rid = (int)$row['resource_id'];
            if (isset($devices[$rid])) {
                $devices[$rid]['certified'] = true;
            }
        }

        $st = $pdo->prepare(
            "SELECT resource_id FROM zhl_cert_confirmation
             WHERE user_id = ? AND status = 'pending' AND resource_id IN ($in)"
        );
        $st->execute([$ownerId]);
        foreach ($st->fetchAll() as $row) {
            $rid = (int)$row['resource_id'];
            if (isset($devices[$rid])) {
                $devices[$rid]['pending'] = true;
            }
        }

        $st = $pdo->prepare(
            "SELECT resource_id, scheduled_start_utc FROM zhl_booking_handover
             WHERE reference_number = ? AND type = 'einf' AND resource_id IN ($in) ORDER BY id ASC"
        );
        $st->execute([$ref]);
        foreach ($st->fetchAll() as $row) {
            $rid = (int)$row['resource_id'];
            if (isset($devices[$rid]) && $devices[$rid]['einfDate'] === '' && !empty($row['scheduled_start_utc'])) {
                $devices[$rid]['einfDate'] = $this->fmtLocal((string)$row['scheduled_start_utc'], $tz);
            }
        }

        foreach ($devices as &$d) {
            if ($d['certified']) {
                $d['einfLabel'] = 'Einführung absolviert';
                $d['einfBadge'] = 'badge-ok';
            } elseif ($d['einfDate'] !== '') {
                $d['einfLabel'] = 'Einführungstermin: ' . $d['einfDate'] . ' Uhr';
                $d['einfBadge'] = 'badge-info';
            } elseif ($d['pending']) {
                $d['einfLabel'] = 'Termin gebucht – Bestätigung ausstehend';
                $d['einfBadge'] = 'badge-info';
            } else {
                [$d['einfLabel'], $d['einfBadge']] = match ($d['einf']) {
                    'notwendig' => ['Einführung erforderlich', 'badge-warn'],
                    'moeglich' => ['Einführung möglich', 'badge-info'],
                    default => ['keine Einführung nötig', 'badge-ok'],
                };
            }
        }
        unset($d);

        return array_values($devices);
    }

    /** Alle Übergabetermine (einf/pickup/return) der Buchung, inkl. Zuständigen-Namen. */
    private function loadTermine(PDO $pdo, string $ref, string $tz): array
    {
        $st = $pdo->prepare(
            "SELECT h.type, h.resource_id, r.name AS resource_name, h.scheduled_start_utc,
                    h.status, h.staff_member_id, h.staff_role
             FROM zhl_booking_handover h
             LEFT JOIN resources r ON r.resource_id = h.resource_id
             WHERE h.reference_number = ?
             ORDER BY FIELD(h.type, 'einf', 'pickup', 'return'), h.id"
        );
        $st->execute([$ref]);
        $rows = $st->fetchAll();

        $staffNames = [];
        foreach ($rows as $r) {
            if (!empty($r['staff_member_id'])) {
                $staffNames = zhl_handover_staff_names(zhl_handover_type_labels());
                break;
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $sid = (int)($r['staff_member_id'] ?? 0);
            if ($sid && isset($staffNames[$sid])) {
                $staff = $staffNames[$sid]['name'];
            } else {
                $staff = $r['staff_role'] === 'primary' ? 'Hilfskraft' : ($r['staff_role'] === 'backup' ? 'Team' : '');
            }
            $out[] = [
                'typeLabel' => match ((string)$r['type']) {
                    'pickup' => 'Abholung',
                    'return' => 'Rückgabe',
                    'einf' => 'Einführung',
                    default => (string)$r['type'],
                },
                'resourceName' => (string)($r['resource_name'] ?? ''),
                'terminLabel' => !empty($r['scheduled_start_utc']) ? $this->fmtLocal((string)$r['scheduled_start_utc'], $tz) : '',
                'statusLabel' => match ((string)$r['status']) {
                    'requested' => 'angefragt',
                    'confirmed' => 'terminiert',
                    'done' => 'erledigt',
                    'cancelled' => 'storniert',
                    default => (string)$r['status'],
                },
                'statusBadge' => match ((string)$r['status']) {
                    'done' => 'badge-ok',
                    'confirmed' => 'badge-info',
                    'cancelled' => 'badge-warn',
                    default => 'badge-warn',
                },
                'staff' => $staff,
            ];
        }
        return $out;
    }

    /** Admin-Aktion: laufende Ausleihe dieser Buchung sofort beenden (wie Ausleihen-Liste). */
    public function HandleEndLoan(UserSession $user, string $ref): void
    {
        if ($ref === '') {
            $this->page->RedirectAfterEndLoan($ref, 'error');
            return;
        }
        try {
            $pdo = zhl_handover_db();
            $st = $pdo->prepare('SELECT COUNT(*) FROM reservation_instances WHERE reference_number = ?');
            $st->execute([$ref]);
            if ((int)$st->fetchColumn() === 0) {
                $this->page->RedirectAfterEndLoan($ref, 'error');
                return;
            }

            $res = zhl_handover_end_loan_now($ref);
            zhl_audit_log(array_merge(zhl_audit_actor($user), [
                'action' => 'ausleihe.end_loan_now',
                'entity_type' => 'reservation',
                'entity_id' => $ref,
                'reference_number' => $ref,
                'detail' => array_merge(is_array($res) ? $res : [], ['via' => 'buchungsakte']),
            ]));
            $this->page->RedirectAfterEndLoan($ref, 'ok');
        } catch (Throwable $e) {
            Log::Error('ZHL-Buchungsakte: Ausleihe beenden fehlgeschlagen (ref=%s): %s', $ref, $e->getMessage());
            $this->page->RedirectAfterEndLoan($ref, 'error');
        }
    }

    private function fmtLocal(string $utc, string $tz): string
    {
        if ($utc === '') {
            return '';
        }
        try {
            return Date::Parse($utc, 'UTC')->ToTimezone($tz)->Format('d.m.Y, H:i');
        } catch (Throwable $e) {
            return $utc;
        }
    }
}

interface IZhlBuchungAdminPage
{
    public function BindBuchung(array $vm);

    public function RedirectAfterEndLoan(string $ref, string $status);
}

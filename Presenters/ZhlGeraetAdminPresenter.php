<?php

require_once(ROOT_DIR . 'lib/Config/namespace.php');
require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Database/namespace.php');
require_once(ROOT_DIR . 'Domain/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Web/zhl-handover-lib.php');
require_once(ROOT_DIR . 'Web/zhl-audit-lib.php');

/**
 * Presenter „Geräteakte" — Admin-Detailsicht EINES Geräts: Stammdaten (inkl. Übergabe-
 * Konfiguration, Zertifikatspflicht, Bundle-Zugehörigkeit), aktuelle Ausleihe und die
 * komplette Ausleih-Historie (wer davor/danach). Verlinkt aus den Gerätenamen der
 * Ausleihen-/Rückgaben-Listen ([[ZhlLoanOverview]] devicesFull). Rein lesend bis auf
 * den bestehenden „Ausleihe beenden"-Pfad (zhl_handover_end_loan_now).
 */
class ZhlGeraetAdminPresenter
{
    private const HISTORY_LIMIT = 100;
    private const CANCELLED_LIMIT = 25;

    /** @var IZhlGeraetAdminPage */
    private $page;

    public function __construct(IZhlGeraetAdminPage $page)
    {
        $this->page = $page;
    }

    /** @return bool false, wenn es das Gerät nicht gibt (Seite zeigt dann 404). */
    public function PageLoad(UserSession $user, int $rid): bool
    {
        $tz = $user->Timezone;
        $pdo = zhl_handover_db();

        $st = $pdo->prepare('SELECT * FROM resources WHERE resource_id = ?');
        $st->execute([$rid]);
        $resource = $st->fetch();
        if (!is_array($resource)) {
            return false;
        }

        $scheduleName = '';
        if (!empty($resource['schedule_id'])) {
            $st = $pdo->prepare('SELECT name FROM schedules WHERE schedule_id = ?');
            $st->execute([(int)$resource['schedule_id']]);
            $scheduleName = (string)($st->fetchColumn() ?: '');
        }

        $st = $pdo->prepare(
            "SELECT v.attribute_value FROM custom_attribute_values v
             JOIN custom_attributes a ON a.custom_attribute_id = v.custom_attribute_id
             WHERE a.display_label = 'Geräte-Typ' AND a.attribute_category = 4 AND v.entity_id = ?"
        );
        $st->execute([$rid]);
        $typeLabel = trim((string)($st->fetchColumn() ?: ''));

        $st = $pdo->prepare('SELECT * FROM zhl_uebergabe WHERE resource_id = ?');
        $st->execute([$rid]);
        $uebergabe = $st->fetch();
        $uebergabe = is_array($uebergabe) ? $uebergabe : [];

        $st = $pdo->prepare(
            'SELECT t.name FROM zhl_cert_type_resource ctr
             JOIN zhl_cert_type t ON t.id = ctr.cert_type_id
             WHERE ctr.resource_id = ? AND t.active = 1 ORDER BY t.sort_order, t.name'
        );
        $st->execute([$rid]);
        $certNames = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);

        // Bundle-Zugehörigkeit: konkret gepinnt (specific_resource_id) ODER über den Geräte-Typ.
        $st = $pdo->prepare(
            'SELECT DISTINCT b.name FROM zhl_bundle_item i
             JOIN zhl_bundle b ON b.id = i.bundle_id
             WHERE b.active = 1 AND (i.specific_resource_id = :rid OR (:typ <> \'\' AND i.type_label = :typ))
             ORDER BY b.name'
        );
        $st->execute([':rid' => $rid, ':typ' => $typeLabel]);
        $bundleNames = array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);

        [$history, $current, $historyLimited] = $this->loadHistory($pdo, $rid, $tz);
        $cancelled = $this->loadCancelled($pdo, (string)$resource['name'], $tz);

        $statusId = (int)($resource['status_id'] ?? 1);
        $statusMap = [
            0 => ['versteckt', 'badge-warn'],
            1 => ['aktiv', 'badge-ok'],
            2 => ['nicht verfügbar', 'badge-warn'],
        ];
        [$statusLabel, $statusBadge] = $statusMap[$statusId] ?? ['unbekannt', 'badge-info'];

        $this->page->BindGeraet([
            'rid' => $rid,
            'name' => (string)$resource['name'],
            'statusLabel' => $statusLabel,
            'statusBadge' => $statusBadge,
            'scheduleName' => $scheduleName,
            'typeLabel' => $typeLabel,
            'standort' => trim((string)($resource['location'] ?? '')),
            'beschreibung' => trim((string)($resource['description'] ?? '')),
            'notizen' => trim((string)($resource['notes'] ?? '')),
            'abholungLabel' => $this->abholungLabel($uebergabe['abholung'] ?? null),
            'abholort' => trim((string)($uebergabe['abholort'] ?? '')),
            'rueckgabeLabel' => $this->rueckgabeLabel($uebergabe['rueckgabe'] ?? null),
            'rueckgabeort' => trim((string)($uebergabe['rueckgabeort'] ?? '')),
            'einfuehrungLabel' => $this->einfuehrungLabel($uebergabe['einfuehrung'] ?? null),
            'einfuehrungTyp' => trim((string)($uebergabe['einfuehrung_typ'] ?? '')),
            'certLabel' => implode(', ', $certNames),
            'bundleLabel' => implode(', ', $bundleNames),
            'current' => $current,
            'history' => $history,
            'historyLimited' => $historyLimited,
            'cancelled' => $cancelled,
        ]);
        return true;
    }

    /**
     * Alle Reservierungen des Geräts, neueste zuerst. Rückgabe-Status kommt aus
     * zhl_booking_handover (type=return, done); „aktiv" ist die Zeile, deren
     * Zeitfenster jetzt läuft (dank Return-Shortening ist end_date bei vorzeitiger
     * Rückgabe bereits verkürzt, ein zurückgegebenes Gerät zählt also nicht als aktiv).
     *
     * @return array{0: array<int,array>, 1: ?array, 2: bool} [Historie, aktuelle Ausleihe, limitiert?]
     */
    private function loadHistory(PDO $pdo, int $rid, string $tz): array
    {
        $st = $pdo->prepare(
            'SELECT ri.reference_number, ri.start_date, ri.end_date, rs.title, rs.status_id AS series_status,
                    u.fname, u.lname, u.email,
                    (SELECT COUNT(DISTINCT rr2.resource_id) FROM reservation_resources rr2
                      WHERE rr2.series_id = ri.series_id) AS device_count
             FROM reservation_instances ri
             JOIN reservation_series rs ON rs.series_id = ri.series_id
             JOIN reservation_resources rr ON rr.series_id = ri.series_id AND rr.resource_id = :rid
             JOIN users u ON u.user_id = rs.owner_id
             WHERE rs.status_id <> 2
             ORDER BY ri.start_date DESC
             LIMIT ' . (self::HISTORY_LIMIT + 1)
        );
        $st->execute([':rid' => $rid]);
        $rows = $st->fetchAll();
        $historyLimited = count($rows) > self::HISTORY_LIMIT;
        if ($historyLimited) {
            $rows = array_slice($rows, 0, self::HISTORY_LIMIT);
        }

        $refs = array_values(array_unique(array_map(static fn ($r) => (string)$r['reference_number'], $rows)));
        $returnDone = [];
        if ($refs) {
            $in = implode(',', array_fill(0, count($refs), '?'));
            $st = $pdo->prepare(
                "SELECT reference_number, MAX(updated_at) AS done_at FROM zhl_booking_handover
                 WHERE type = 'return' AND status = 'done' AND reference_number IN ($in)
                 GROUP BY reference_number"
            );
            $st->execute($refs);
            foreach ($st->fetchAll() as $r) {
                $returnDone[(string)$r['reference_number']] = (string)$r['done_at'];
            }
        }

        $nowUtc = gmdate('Y-m-d H:i:s');
        $history = [];
        $current = null;
        foreach ($rows as $r) {
            $ref = (string)$r['reference_number'];
            $startUtc = (string)$r['start_date'];
            $endUtc = (string)$r['end_date'];
            $borrower = trim((string)$r['fname'] . ' ' . (string)$r['lname']);
            if ($borrower === '') {
                $borrower = (string)$r['email'];
            }

            $doneAt = $returnDone[$ref] ?? null;
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

            $row = [
                'ref' => $ref,
                'startLabel' => $this->fmtLocal($startUtc, $tz),
                'endLabel' => $this->fmtLocal($endUtc, $tz),
                'borrower' => $borrower,
                'email' => (string)$r['email'],
                'title' => trim((string)$r['title']),
                'deviceCount' => (int)$r['device_count'],
                'stateLabel' => $stateLabel,
                'badgeClass' => $badge,
                'returnedLabel' => $doneAt !== null ? $this->fmtLocal($doneAt, $tz) : '',
            ];
            $history[] = $row;
            if ($current === null && $stateLabel === 'aktiv') {
                $current = $row;
            }
        }
        return [$history, $current, $historyLimited];
    }

    /**
     * Stornierte Buchungen werden nativ HART gelöscht und leben nur als Schnappschuss in
     * zhl_cancelled_booking weiter — dort gibt es KEINE resource_id, nur "\n"-verbundene
     * Gerätenamen. Vorfilter per LIKE, dann exakter Zeilen-Match in PHP (verhindert
     * Präfix-Treffer wie „DJI mic 1" in „DJI mic 10").
     */
    private function loadCancelled(PDO $pdo, string $resourceName, string $tz): array
    {
        try {
            $st = $pdo->prepare(
                'SELECT cb.reference_number, cb.title, cb.resource_names, cb.start_utc, cb.end_utc,
                        cb.cancelled_at, u.fname, u.lname, u.email
                 FROM zhl_cancelled_booking cb
                 LEFT JOIN users u ON u.user_id = cb.user_id
                 WHERE cb.resource_names LIKE ?
                 ORDER BY cb.cancelled_at DESC
                 LIMIT ' . (self::CANCELLED_LIMIT * 4)
            );
            $st->execute(['%' . $resourceName . '%']);
        } catch (Throwable $e) {
            return []; // Anzeige-Bonus — ohne Tabelle/Fehler einfach leer.
        }

        $out = [];
        foreach ($st->fetchAll() as $r) {
            $names = preg_split('/\r?\n/', (string)$r['resource_names']) ?: [];
            if (!in_array($resourceName, array_map('trim', $names), true)) {
                continue;
            }
            $borrower = trim((string)($r['fname'] ?? '') . ' ' . (string)($r['lname'] ?? ''));
            if ($borrower === '') {
                $borrower = (string)($r['email'] ?? '');
            }
            $out[] = [
                'ref' => (string)$r['reference_number'],
                'title' => trim((string)$r['title']),
                'startLabel' => $this->fmtLocal((string)($r['start_utc'] ?? ''), $tz),
                'endLabel' => $this->fmtLocal((string)($r['end_utc'] ?? ''), $tz),
                'cancelledLabel' => $this->fmtLocal((string)$r['cancelled_at'], $tz),
                'borrower' => $borrower,
            ];
            if (count($out) >= self::CANCELLED_LIMIT) {
                break;
            }
        }
        return $out;
    }

    /**
     * Admin-Aktion aus der Geräteakte: laufende Ausleihe sofort beenden (gleiche Pipeline
     * wie in der Ausleihen-Liste). Der ref-Parameter wird gegen das Gerät verifiziert,
     * damit über die Akte nicht fremde Reservierungen beendet werden können.
     */
    public function HandleEndLoan(UserSession $user, int $rid): void
    {
        $ref = isset($_POST['ref']) ? trim((string)$_POST['ref']) : '';
        if ($ref === '' || $rid <= 0) {
            $this->page->RedirectAfterEndLoan($rid, 'error');
            return;
        }
        try {
            $pdo = zhl_handover_db();
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM reservation_instances ri
                 JOIN reservation_resources rr ON rr.series_id = ri.series_id
                 WHERE ri.reference_number = ? AND rr.resource_id = ?'
            );
            $st->execute([$ref, $rid]);
            if ((int)$st->fetchColumn() === 0) {
                $this->page->RedirectAfterEndLoan($rid, 'error');
                return;
            }

            $res = zhl_handover_end_loan_now($ref);
            zhl_audit_log(array_merge(zhl_audit_actor($user), [
                'action' => 'ausleihe.end_loan_now',
                'entity_type' => 'reservation',
                'entity_id' => $ref,
                'reference_number' => $ref,
                'detail' => array_merge(is_array($res) ? $res : [], ['via' => 'geraeteakte', 'resource_id' => $rid]),
            ]));
            $this->page->RedirectAfterEndLoan($rid, 'ok');
        } catch (Throwable $e) {
            Log::Error('ZHL-Geräteakte: Ausleihe beenden fehlgeschlagen (ref=%s, rid=%d): %s', $ref, $rid, $e->getMessage());
            $this->page->RedirectAfterEndLoan($rid, 'error');
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

    private function abholungLabel(?string $v): string
    {
        return match ($v) {
            'nicht_noetig' => 'keine Abholung nötig',
            'abholen' => 'Abholen',
            'abholen_persoenlich' => 'persönliche Übergabe',
            'ablageort' => 'Ablageort/Hauspost',
            default => '—',
        };
    }

    private function rueckgabeLabel(?string $v): string
    {
        return match ($v) {
            'nicht_noetig' => 'keine Rückgabe nötig',
            'abgeben' => 'Abgeben',
            'abgeben_persoenlich' => 'persönliche Rückgabe (Termin)',
            default => '—',
        };
    }

    private function einfuehrungLabel(?string $v): string
    {
        return match ($v) {
            'keine' => 'keine nötig',
            'moeglich' => 'möglich (empfohlen)',
            'notwendig' => 'Pflicht',
            default => '—',
        };
    }
}

interface IZhlGeraetAdminPage
{
    public function BindGeraet(array $vm);

    public function RedirectAfterEndLoan(int $rid, string $status);
}

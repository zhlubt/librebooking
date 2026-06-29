<?php

/**
 * Task F — READ-ONLY-Verifikation der Stornierung „Aufnahme 30.6.–7.7."
 * über media (zhl_media) UND meet (zhl_meet).
 *
 * AUSSCHLIESSLICH SELECT/SHOW. Schreibt NICHTS. Im Container ausführen:
 *     ssh zhl-media 'cd /var/www/html && php' < docs/zhl/verify-storno-30-06.php   (media-Teil)
 *     ssh zhl-meet  'php'                      < docs/zhl/verify-storno-30-06.php   (meet-Teil)
 *
 * Das Skript erkennt anhand des Schemas selbst, welchen Teil es ausführt.
 *
 * BEFUNDE der Untersuchung 2026-06-29 (in die Konstanten eingeflossen):
 *  - Der Buchende ist user 24 (paul.doelle@uni-bayreuth.de); user 312 existiert NICHT
 *    (alter Harness-Wert war falsch → alles leer).
 *  - Die stornierte Aufnahme hat reference_number 6a412bff17204082463658, series 1406,
 *    Titel „aufnahme" (klein), Videostudio (resource 21), 30.6. 07:00–7.7. 15:00.
 *  - media zhl_booking_handover enthält KEINE Zeile zu dieser ref → es war kein
 *    Terminplaner-Termin (Abholung/Einführung/Rückgabe) an die Aufnahme gekoppelt;
 *    auf meet ist daher nichts zu stornieren (vacuously consistent).
 *
 * Vorgehen media (ref-verankert, status-bewusst):
 *  [1] Storno-Schnappschuss zhl_cancelled_booking (user + Fenster + Titel) → liefert die ref(s).
 *  [2] native reservation_series/_instances zu jeder ref MUSS status_id=2 (Deleted) sein;
 *      ein status_id=1 (Created) wäre der BUG „nicht storniert".
 *  [3] zhl_booking_handover zu den ref(s) = 0 (aufgeräumt); etwaige terminplaner_booking_id
 *      werden als verknüpfte meet-Termine ausgewiesen.
 *  [4] pending cert_confirmation (Info — Storno räumt sie bewusst nicht).
 * Vorgehen meet:
 *  - Die in [3] gefundenen meet-Booking-IDs (LINKED_MEET_BOOKING_IDS) MÜSSEN status='cancelled'
 *    sein. Ist die Liste leer (wie hier), gilt: keine verknüpften Termine → konsistent.
 */

const TARGET_USER = 24;
const WINDOW_START = '2026-06-30';
const WINDOW_END = '2026-07-07';
const TITLE_NEEDLE = 'Aufnahme'; // case-insensitiv; '' = kein Titelfilter

// Aus media-[3] ermittelte, an die Storno-Buchung gekoppelte Terminplaner-Buchungs-IDs.
// Leer = es existieren keine verknüpften meet-Termine (Befund 2026-06-29).
const LINKED_MEET_BOOKING_IDS = [];

const ST_CREATED = 1; // reservation_statuses: 1=Created (lebt)
const ST_DELETED = 2; //                      2=Deleted (storniert/soft-deleted)

function out(string $s): void
{
    echo $s . "\n";
}

function titleMark(string $title): string
{
    if (TITLE_NEEDLE === '') {
        return '';
    }
    return (stripos($title, TITLE_NEEDLE) !== false) ? ' [MATCH „' . TITLE_NEEDLE . '"]' : ' [anderer Titel]';
}

/** media (LibreBooking): config/config.php → ['settings']['database']; Host unter 'hostspec'. */
function loadMediaPdo(): ?PDO
{
    foreach (['/var/www/html/config/config.php', __DIR__ . '/../../config/config.php'] as $p) {
        if (is_file($p)) {
            $conf = include $p;
            if (isset($conf['settings']['database'])) {
                $db = $conf['settings']['database'];
                $host = $db['hostspec'] ?? $db['hostname'] ?? $db['host'] ?? 'localhost';
                $name = $db['name'] ?? $db['database'] ?? '';
                return new PDO(
                    "mysql:host={$host};dbname={$name};charset=utf8mb4",
                    $db['user'] ?? '',
                    $db['password'] ?? '',
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
                );
            }
        }
    }
    return null;
}

/** meet (Terminplaner): /var/www/private/includes/db.php → get_db(). */
function loadMeetPdo(): ?PDO
{
    foreach (['/var/www/private/includes/db.php', __DIR__ . '/db.php'] as $p) {
        if (is_file($p)) {
            require_once $p;
            if (function_exists('get_db')) {
                return get_db();
            }
            if (function_exists('get_db_connection')) {
                return get_db_connection();
            }
        }
    }
    return null;
}

/** Welches Schema liegt vor? Gegen die tatsächlichen Tabellen prüfen, nicht nur per Pfad. */
function detectKind(PDO $pdo): string
{
    $tables = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $tables[strtolower((string)$t)] = true;
    }
    if (isset($tables['reservation_series']) && isset($tables['zhl_cancelled_booking'])) {
        return 'media';
    }
    if (isset($tables['bookings']) && !isset($tables['reservation_series'])) {
        return 'meet';
    }
    return 'unknown';
}

$pdo = loadMediaPdo() ?? loadMeetPdo();
if (!$pdo instanceof PDO) {
    throw new RuntimeException('Keine bekannte DB-Konfiguration gefunden (weder media config.php noch meet includes/db.php).');
}
$kind = detectKind($pdo);
out('=== Task-F READ-ONLY Verifikation — ' . strtoupper($kind) . ' (Schema-bestätigt) ===');

if ($kind === 'media') {
    // [1] Storno-Schnappschuss → ref(s)
    $st = $pdo->prepare("SELECT id, reference_number, title, device_count, resource_names, start_utc, end_utc, cancelled_at
                           FROM zhl_cancelled_booking
                          WHERE user_id = :uid AND start_utc < :wend AND end_utc > :wstart
                          ORDER BY cancelled_at DESC");
    $st->execute([':uid' => TARGET_USER, ':wstart' => WINDOW_START . ' 00:00:00', ':wend' => WINDOW_END . ' 23:59:59']);
    $snap = $st->fetchAll();
    $matchRefs = [];
    out('[1] Storno-Schnappschüsse (zhl_cancelled_booking) user ' . TARGET_USER . ': ' . count($snap) . ' (Erwartung >=1)');
    foreach ($snap as $r) {
        $isMatch = (TITLE_NEEDLE === '' || stripos((string)$r['title'], TITLE_NEEDLE) !== false);
        if ($isMatch) {
            $matchRefs[] = $r['reference_number'];
        }
        out('    ref=' . $r['reference_number'] . ' "' . $r['title'] . '"' . titleMark((string)$r['title'])
            . ' geräte=' . $r['device_count'] . ' [' . $r['resource_names'] . ']'
            . ' ' . $r['start_utc'] . '..' . $r['end_utc'] . ' storniert ' . $r['cancelled_at']);
    }
    out('    → Titel-Treffer „' . TITLE_NEEDLE . '": ' . count($matchRefs) . ' (= gesuchte Storno-Buchung).');

    $createdBug = 0;
    $deletedOk = 0;
    $linkedMeetIds = [];
    $handoverRemain = 0;

    if ($matchRefs) {
        $in = implode(',', array_fill(0, count($matchRefs), '?'));

        // [2] native series zu den ref(s): MUSS Deleted sein
        $st = $pdo->prepare("SELECT s.series_id, s.owner_id, s.title, s.status_id, i.reference_number, i.start_date, i.end_date
                               FROM reservation_series s
                               JOIN reservation_instances i ON i.series_id = s.series_id
                              WHERE i.reference_number IN ($in)");
        $st->execute($matchRefs);
        $rows = $st->fetchAll();
        out('[2] Native reservation_series zu den Storno-refs: ' . count($rows) . ' (jede MUSS status=Deleted sein)');
        foreach ($rows as $r) {
            $isDeleted = (int)$r['status_id'] === ST_DELETED;
            if ($isDeleted) {
                $deletedOk++;
            }
            if ((int)$r['status_id'] === ST_CREATED) {
                $createdBug++;
            }
            out('    series=' . $r['series_id'] . ' owner=' . $r['owner_id'] . ' "' . $r['title'] . '"'
                . ' status_id=' . $r['status_id'] . ($isDeleted ? ' [Deleted ✓]' : ' [NICHT Deleted ✗]')
                . ' ' . $r['start_date'] . '..' . $r['end_date']);
        }

        // [3] handover zu den ref(s): erwartet 0; verknüpfte meet-IDs einsammeln
        $st = $pdo->prepare("SELECT reference_number, type, status, terminplaner_booking_id, resource_id
                               FROM zhl_booking_handover WHERE reference_number IN ($in)");
        $st->execute($matchRefs);
        $ho = $st->fetchAll();
        $handoverRemain = count($ho);
        out('[3] Verbliebene zhl_booking_handover-Zeilen zu den Storno-refs: ' . $handoverRemain . ' (Erwartung 0)');
        foreach ($ho as $r) {
            if (!empty($r['terminplaner_booking_id'])) {
                $linkedMeetIds[] = $r['terminplaner_booking_id'];
            }
            out('    ref=' . $r['reference_number'] . ' type=' . $r['type'] . ' status=' . $r['status']
                . ' tp_booking=' . ($r['terminplaner_booking_id'] ?? '-') . ' res=' . ($r['resource_id'] ?? '-'));
        }
        out('    → verknüpfte meet-Booking-IDs: ' . ($linkedMeetIds ? implode(', ', $linkedMeetIds) : '(keine)')
            . ' — diese im meet-Lauf als status=cancelled prüfen.');

        // [4] pending cert_confirmation (Info)
        $st = $pdo->prepare("SELECT resource_id, status, created_at FROM zhl_cert_confirmation
                              WHERE user_id = :uid AND status = 'pending'");
        $st->execute([':uid' => TARGET_USER]);
        $cc = $st->fetchAll();
        out('[4] Offene cert_confirmation (pending) user ' . TARGET_USER . ': ' . count($cc) . ' (Info; Storno räumt sie bewusst nicht)');
        foreach ($cc as $r) {
            out('    res=' . $r['resource_id'] . ' status=' . $r['status'] . ' seit ' . $r['created_at']);
        }
    }

    $ok = count($matchRefs) >= 1 && $createdBug === 0 && $deletedOk === count($matchRefs) && $handoverRemain === 0;
    out('FAZIT media: ' . ($ok
        ? 'KONSISTENT (Schnappschuss da, native Deleted, keine Created-Reste, handover aufgeräumt).'
        : 'PRÜFEN — siehe Zeilen oben (Created-Reste=' . $createdBug . ', handover-Reste=' . $handoverRemain . ').'));
} elseif ($kind === 'meet') {
    $linked = LINKED_MEET_BOOKING_IDS;
    out('[meet] An die Storno-Buchung gekoppelte Termin-IDs (aus media-[3]): ' . ($linked ? implode(', ', $linked) : '(keine)'));
    if (!$linked) {
        out('       → Keine verknüpften meet-Termine; auf meet ist nichts zu stornieren.');
        // Sicherheitsnetz: gibt es überhaupt eine CONFIRMED Buchung von Paul im Fenster,
        // die fälschlich mit der Aufnahme zu tun haben könnte? Read-only Übersicht.
        $st = $pdo->prepare("SELECT id, meeting_type_id, status, start_utc, guest_email, note
                               FROM bookings
                              WHERE guest_email LIKE :mail AND start_utc >= :a AND start_utc < :b
                              ORDER BY start_utc");
        $st->execute([':mail' => '%paul.doelle@uni-bayreuth.de%', ':a' => WINDOW_START . ' 00:00:00', ':b' => '2026-07-08 00:00:00']);
        $rows = $st->fetchAll();
        out('       Info — Paul-Buchungen im Fenster (NICHT zwingend storno-bezogen): ' . count($rows));
        foreach ($rows as $r) {
            out('         id=' . $r['id'] . ' type=' . $r['meeting_type_id'] . ' status=' . $r['status']
                . ' start=' . $r['start_utc'] . ' note="' . trim((string)$r['note']) . '"');
        }
        out('FAZIT meet: KONSISTENT (keine an die Storno-Buchung gekoppelten Termine).');
    } else {
        $in = implode(',', array_fill(0, count($linked), '?'));
        $st = $pdo->prepare("SELECT id, meeting_type_id, status, start_utc, cancelled_at FROM bookings WHERE id IN ($in)");
        $st->execute($linked);
        $rows = $st->fetchAll();
        $active = 0;
        $found = [];
        foreach ($rows as $r) {
            $found[$r['id']] = true;
            if ((string)$r['status'] !== 'cancelled') {
                $active++;
            }
            out('    id=' . $r['id'] . ' type=' . $r['meeting_type_id'] . ' status=' . $r['status']
                . ' start=' . $r['start_utc'] . ' cancelled_at=' . ($r['cancelled_at'] ?? '-'));
        }
        $missing = array_diff($linked, array_keys($found));
        foreach ($missing as $m) {
            out('    id=' . $m . ' — NICHT gefunden (evtl. hart gelöscht)');
        }
        out('FAZIT meet: ' . ($active === 0
            ? 'KONSISTENT (alle gekoppelten Termine cancelled).'
            : ('PRÜFEN — ' . $active . ' NICHT stornierte gekoppelte Termine.')));
    }
} else {
    throw new RuntimeException('Schema weder als media noch als meet erkannt.');
}

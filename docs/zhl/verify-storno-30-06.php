<?php

/**
 * Task F — READ-ONLY-Verifikation der Stornierung „Aufnahme 30.6.–7.7." (user 312,
 * paul.doelle@uni-bayreuth.de) über media (zhl_media) UND meet (zhl_meet).
 *
 * AUSSCHLIESSLICH SELECT. Schreibt NICHTS. Im Container ausführen:
 *     ssh zhl-media 'php' < docs/zhl/verify-storno-30-06.php          (media-Teil)
 *     ssh zhl-meet  'php' < docs/zhl/verify-storno-30-06.php          (meet-Teil — anderer DB-Zugang)
 *
 * Das Skript erkennt anhand der verfügbaren DB selbst, welchen Teil es ausführt:
 *  - media: native Reservierung (reservation_series/_instances/_resources) zur Buchung weg?
 *           zhl_booking_handover zur reference_number leer? Storno-Schnappschuss in
 *           zhl_cancelled_booking vorhanden? cert_confirmation-Reste?
 *  - meet:  zugehörige Terminplaner-Termine (Abholung/Einführung/Rückgabe) status='cancelled'?
 *           verwaiste/aktive Slots zur Buchung?
 *
 * Da die reference_number lokal nicht bekannt ist, wird die Buchung über user 312 + Zeitraum
 * 30.6.–7.7.2026 + Titel-Heuristik „Aufnahme" gesucht (in zhl_cancelled_booking UND ggf. noch
 * lebenden Reservierungen — Letzteres wäre ein BUG-Befund: dann ist NICHT storniert).
 */

const TARGET_USER = 312;
const WINDOW_START = '2026-06-30';
const WINDOW_END = '2026-07-07';
// Titel-Heuristik: die stornierte Buchung hiess „Aufnahme …". Damit NICHT ein anderer Storno desselben
// Users im selben Fenster faelschlich als Treffer zaehlt, wird jede Fundzeile gegen dieses Muster
// markiert (MATCH/andere). Leerer Wert = kein Titelfilter.
const TITLE_NEEDLE = 'Aufnahme';

function pdoFrom(array $db): PDO
{
    // LibreBooking speichert den DB-Host unter 'hostspec' (Wert i.d.R. 'mariadb', nur im Container
    // auflösbar); Terminplaner/andere unter 'hostname'/'host'. Reihenfolge entsprechend.
    $host = $db['hostspec'] ?? $db['hostname'] ?? $db['host'] ?? 'localhost';
    $name = $db['name'] ?? $db['database'] ?? '';
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    return new PDO($dsn, $db['user'] ?? '', $db['password'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function loadConfig(): array
{
    // media (LibreBooking): config/config.php → ['settings']['database']
    foreach (['/var/www/html/config/config.php', __DIR__ . '/../../config/config.php'] as $p) {
        if (is_file($p)) {
            $conf = include $p;
            if (isset($conf['settings']['database'])) {
                return ['kind' => 'media', 'db' => $conf['settings']['database']];
            }
        }
    }
    // meet (Terminplaner): db.php / get_db_connection — wir lesen die Konstanten direkt.
    foreach (['/var/www/html/db.php', '/var/www/html/config.php', __DIR__ . '/db.php'] as $p) {
        if (is_file($p)) {
            // db.php definiert i. d. R. DB_HOST/DB_NAME/DB_USER/DB_PASS oder gibt eine PDO zurück.
            $ret = @include $p;
            if ($ret instanceof PDO) {
                return ['kind' => 'meet', 'pdo' => $ret];
            }
            if (defined('DB_NAME')) {
                return ['kind' => 'meet', 'db' => [
                    'hostname' => defined('DB_HOST') ? DB_HOST : 'localhost',
                    'name' => DB_NAME,
                    'user' => defined('DB_USER') ? DB_USER : '',
                    'password' => defined('DB_PASS') ? DB_PASS : '',
                ]];
            }
        }
    }
    throw new RuntimeException('Keine bekannte DB-Konfiguration gefunden (weder media config.php noch meet db.php).');
}

function out(string $s): void
{
    echo $s . "\n";
}

$cfg = loadConfig();
$pdo = $cfg['pdo'] ?? pdoFrom($cfg['db']);

// Robuste DB-Erkennung NICHT allein per Pfad: gegen das tatsächliche Schema gegenprüfen, damit ein
// meet-Container mit zufällig vorhandener config.php nicht als media fehlklassifiziert wird.
$tables = [];
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $tables[strtolower((string)$t)] = true;
}
$looksMedia = isset($tables['reservation_series']) && isset($tables['zhl_cancelled_booking']);
$looksMeet = isset($tables['bookings']) && !isset($tables['reservation_series']);
if ($looksMedia) {
    $cfg['kind'] = 'media';
} elseif ($looksMeet) {
    $cfg['kind'] = 'meet';
}

out('=== Task-F READ-ONLY Verifikation — ' . strtoupper($cfg['kind']) . ' (Schema-bestätigt) ===');

/** Titel gegen die Heuristik markieren. */
function titleMark(string $title): string
{
    if (TITLE_NEEDLE === '') {
        return '';
    }
    return (stripos($title, TITLE_NEEDLE) !== false) ? ' [MATCH „' . TITLE_NEEDLE . '"]' : ' [anderer Titel]';
}

if ($cfg['kind'] === 'media') {
    // 1) Noch lebende Reservierungen von user 312 im Fenster? (jede Zeile = BUG: nicht storniert)
    // Eigentümer hängt an reservation_series.owner_id (reservation_users hat KEINE series_id).
    $sql = "SELECT s.series_id, i.reference_number, s.title, i.start_date, i.end_date
              FROM reservation_series s
              JOIN reservation_instances i ON i.series_id = s.series_id
             WHERE s.owner_id = :uid
               AND i.start_date < :wend AND i.end_date > :wstart";
    $st = $pdo->prepare($sql);
    $st->execute([':uid' => TARGET_USER, ':wstart' => WINDOW_START . ' 00:00:00', ':wend' => WINDOW_END . ' 23:59:59']);
    $live = $st->fetchAll();
    out('[1] Lebende Reservierungen user ' . TARGET_USER . ' im Fenster: ' . count($live) . ' (Erwartung 0)');
    foreach ($live as $r) {
        out('    BUG? series=' . $r['series_id'] . ' ref=' . $r['reference_number'] . ' "' . $r['title'] . '"' . titleMark((string)$r['title']) . ' ' . $r['start_date'] . '..' . $r['end_date']);
    }

    // 2) Storno-Schnappschuss vorhanden?
    $st = $pdo->prepare("SELECT id, reference_number, title, device_count, start_utc, end_utc, cancelled_at
                           FROM zhl_cancelled_booking
                          WHERE user_id = :uid AND start_utc < :wend AND end_utc > :wstart
                          ORDER BY cancelled_at DESC");
    $st->execute([':uid' => TARGET_USER, ':wstart' => WINDOW_START . ' 00:00:00', ':wend' => WINDOW_END . ' 23:59:59']);
    $snap = $st->fetchAll();
    out('[2] Storno-Schnappschüsse (zhl_cancelled_booking): ' . count($snap) . ' (Erwartung >=1)');
    $refs = [];
    $matchRefs = [];
    foreach ($snap as $r) {
        $refs[] = $r['reference_number'];
        if (TITLE_NEEDLE === '' || stripos((string)$r['title'], TITLE_NEEDLE) !== false) {
            $matchRefs[] = $r['reference_number'];
        }
        out('    ref=' . $r['reference_number'] . ' "' . $r['title'] . '"' . titleMark((string)$r['title']) . ' geräte=' . $r['device_count']
            . ' ' . $r['start_utc'] . '..' . $r['end_utc'] . ' storniert ' . $r['cancelled_at']);
    }
    out('    → Titel-Treffer „' . TITLE_NEEDLE . '": ' . count($matchRefs) . ' von ' . count($snap)
        . ' (das ist die gesuchte Storno-Buchung; andere sind separate Stornos desselben Users).');

    // 3) Übergabe-Zeilen zu den gefundenen refs aufgeräumt?
    if ($refs) {
        $in = implode(',', array_fill(0, count($refs), '?'));
        $st = $pdo->prepare("SELECT reference_number, type, status, terminplaner_booking_id, resource_id
                               FROM zhl_booking_handover WHERE reference_number IN ($in)");
        $st->execute($refs);
        $ho = $st->fetchAll();
        out('[3] Verbliebene zhl_booking_handover-Zeilen zu den Storno-refs: ' . count($ho) . ' (Erwartung 0, außer TP-Storno schlug fehl)');
        foreach ($ho as $r) {
            out('    ref=' . $r['reference_number'] . ' type=' . $r['type'] . ' status=' . $r['status']
                . ' tp_booking=' . ($r['terminplaner_booking_id'] ?? '-') . ' res=' . ($r['resource_id'] ?? '-'));
        }

        // 4) Offene cert_confirmation (pending) des Users (bewusst NICHT vom Storno gelöscht — nur Info).
        $st = $pdo->prepare("SELECT resource_id, status, created_at FROM zhl_cert_confirmation
                              WHERE user_id = :uid AND status = 'pending'");
        $st->execute([':uid' => TARGET_USER]);
        $cc = $st->fetchAll();
        out('[4] Offene cert_confirmation (pending) user ' . TARGET_USER . ': ' . count($cc) . ' (Info; Storno räumt sie bewusst nicht)');
        foreach ($cc as $r) {
            out('    res=' . $r['resource_id'] . ' status=' . $r['status'] . ' seit ' . $r['created_at']);
        }
    }
    out('FAZIT media: ' . (count($live) === 0 && count($snap) >= 1 ? 'KONSISTENT (keine lebende Reservierung, Schnappschuss da).' : 'PRÜFEN — siehe Zeilen oben.'));
} else {
    // meet: Terminplaner-Slots/Buchungen zur Buchung. Member 2 = Paul/Team-Einweiser; die Gast-Buchungen
    // tragen E-Mail paul.doelle@uni-bayreuth.de. Wir suchen Buchungen im Fenster und prüfen den Status.
    // Tabellen-/Spaltennamen heuristisch (bookings.status, .start, .guest_email/.email).
    $cols = $pdo->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
    out('[meet] bookings-Spalten: ' . implode(', ', $cols));
    $emailCol = in_array('guest_email', $cols, true) ? 'guest_email' : (in_array('email', $cols, true) ? 'email' : null);
    $startCol = in_array('start_utc', $cols, true) ? 'start_utc' : (in_array('start', $cols, true) ? 'start' : (in_array('start_time', $cols, true) ? 'start_time' : null));
    if ($emailCol === null || $startCol === null) {
        out('  Spalten für E-Mail/Start nicht erkannt — bitte SHOW COLUMNS prüfen und Query anpassen.');
    } else {
        $st = $pdo->prepare("SELECT id, meeting_type, status, $startCol AS s, $emailCol AS mail
                               FROM bookings
                              WHERE $emailCol LIKE :mail AND $startCol >= :a AND $startCol < :b
                              ORDER BY $startCol");
        $st->execute([':mail' => '%paul.doelle@uni-bayreuth.de%', ':a' => WINDOW_START . ' 00:00:00', ':b' => '2026-07-08 00:00:00']);
        $rows = $st->fetchAll();
        out('[meet] Buchungen paul.doelle im Fenster: ' . count($rows));
        $active = 0;
        foreach ($rows as $r) {
            $isCancelled = (string)$r['status'] === 'cancelled';
            if (!$isCancelled) {
                $active++;
            }
            out('    id=' . $r['id'] . ' type=' . $r['meeting_type'] . ' status=' . $r['status'] . ' start=' . $r['s']);
        }
        out('    Präzise Verknüpfung: die hier gelisteten booking-IDs gegen die media-Ausgabe [3]');
        out('    (Spalte tp_booking) abgleichen — so ist eindeutig, welche Termine zur Storno-Buchung gehören.');
        out('FAZIT meet: ' . ($active === 0 ? 'KONSISTENT (alle zugehörigen Termine cancelled).' : ('PRÜFEN — ' . $active . ' NICHT stornierte Termine.')));
    }
}

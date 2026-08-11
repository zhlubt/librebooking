<?php
/**
 * ZHL — iCalendar-Feed (.ics) zum Abonnieren in Outlook.
 *
 * Liefert als ein VCALENDAR:
 *   • geplante Übergaben, Rückgaben und Einführungen (zhl_booking_handover)
 *   • Videostudio-Buchungen (echte Reservierungen des Studios selbst)
 * Andere Geräte-Ausleihen kommen bewusst NICHT in den Kalender (zu viele Termine).
 *
 * Auth über ?key=<secret> gegen config/zhl-calendar.php['key'] (Outlook abonniert die
 * URL ohne Login → der Schlüssel ist das Bearer-Token). Bewusst leichtgewichtig: kein
 * Framework-/Session-Bootstrap, nur die ZHL-Lib (raw PDO) + Config. Read-only.
 *
 * In den DESCRIPTION-Feldern steht alles Nötige (Vorgang, Gerät, Ausleihende:r, E-Mail,
 * Status, Buchungslink), damit das ZHL-Team direkt aus dem Kalender handeln kann.
 */

declare(strict_types=1);

require_once(__DIR__ . '/zhl-handover-lib.php');

// ----------------------------------------------------------------------------
// 1) Auth: Schlüssel aus config/zhl-calendar.php gegen ?key= prüfen.
// ----------------------------------------------------------------------------
$confFile = dirname(__DIR__) . '/config/zhl-calendar.php';
$conf = is_readable($confFile) ? require $confFile : [];
$conf = is_array($conf) ? $conf : [];
$secret = (string)($conf['key'] ?? '');
$given = isset($_GET['key']) ? (string)$_GET['key'] : '';

if ($secret === '' || $secret === 'REPLACE_WITH_LONG_RANDOM_SECRET' || strlen($secret) < 16 || !hash_equals($secret, $given)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden — gültiger ?key= erforderlich.';
    exit;
}

// ----------------------------------------------------------------------------
// 2) Basis-URL (für Buchungslinks) + Kalender-Metadaten.
// ----------------------------------------------------------------------------
$pastDays = max(0, (int)($conf['past_days'] ?? 30));
$futureDays = max(1, (int)($conf['future_days'] ?? 365));
$calName = (string)($conf['calendar_name'] ?? 'ZHL Medien — Übergaben & Studio');

// script.url direkt aus config.php lesen (kein Framework nötig).
$cfg = require dirname(__DIR__) . '/config/config.php';
$scriptUrl = rtrim((string)($cfg['settings']['script.url'] ?? ''), '/');
$host = parse_url($scriptUrl, PHP_URL_HOST) ?: 'media.zhl-ubt.de';
// Buchungsakte (Admin-Sicht, zeigt auch FREMDE Buchungen) — zhl-booking-detail.php wäre
// Owner-only und liefe für das ZHL-Team beim Klick aus dem Kalender ins Leere.
$detailBase = $scriptUrl !== '' ? $scriptUrl . '/zhl-buchung-admin.php?ref=' : '';

// ----------------------------------------------------------------------------
// 3) Zeitfenster (UTC) bestimmen.
// ----------------------------------------------------------------------------
$utc = new DateTimeZone('UTC');
$now = new DateTime('now', $utc);
$startUtc = (clone $now)->modify('-' . $pastDays . ' days')->format('Y-m-d H:i:s');
$endUtc = (clone $now)->modify('+' . $futureDays . ' days')->format('Y-m-d H:i:s');

// DB-Fehler kontrolliert behandeln: ein abonnierter Feed darf keine 500-Details leaken.
try {
    $handovers = zhl_handover_planned($startUtc, $endUtc);   // alle Typen
    $studio = zhl_studio_reservations($startUtc, $endUtc);
} catch (Throwable $e) {
    error_log('zhl-calendar: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Kalender momentan nicht verfügbar.';
    exit;
}

// ----------------------------------------------------------------------------
// 3b) Anreicherung je Buchung: Eckdaten (Zeitraum/Vorhaben/Telefon/Einrichtung) und
//     die KOMPLETTE Geräteliste inkl. Übergabe-Konfiguration — damit der Kalender-
//     eintrag „wer wann was WIE abholt/zurückgibt" vollständig beantwortet.
//     Best effort: schlägt die Anreicherung fehl, bleibt der Feed mit Basisdaten nutzbar.
// ----------------------------------------------------------------------------
$berlin = new DateTimeZone('Europe/Berlin');
$fmtLocal = static function (?string $utcStr) use ($utc, $berlin): string {
    if (!$utcStr) {
        return '';
    }
    try {
        return (new DateTime($utcStr, $utc))->setTimezone($berlin)->format('d.m.Y H:i');
    } catch (Throwable $e) {
        return '';
    }
};

$bookingInfo = [];     // ref => {start_date, end_date, title, phone, organization}
$bookingDevices = [];  // ref => [{name, abholung, abholort, rueckgabe, rueckgabeort, einfuehrung}]
try {
    $refs = array_values(array_unique(array_filter(array_map(
        static fn ($r) => (string)($r['reference_number'] ?? ''),
        $handovers
    ))));
    if ($refs) {
        $pdo = zhl_handover_db();
        $in = implode(',', array_fill(0, count($refs), '?'));

        $st = $pdo->prepare(
            "SELECT ri.reference_number, ri.start_date, ri.end_date, rs.title,
                    u.phone, u.organization
             FROM reservation_instances ri
             JOIN reservation_series rs ON rs.series_id = ri.series_id
             JOIN users u ON u.user_id = rs.owner_id
             WHERE ri.reference_number IN ($in)"
        );
        $st->execute($refs);
        foreach ($st->fetchAll() as $b) {
            $bookingInfo[(string)$b['reference_number']] = $b;
        }

        $st = $pdo->prepare(
            "SELECT ri.reference_number, r.name,
                    ue.abholung, ue.abholort, ue.rueckgabe, ue.rueckgabeort, ue.einfuehrung
             FROM reservation_instances ri
             JOIN reservation_resources rr ON rr.series_id = ri.series_id
             JOIN resources r ON r.resource_id = rr.resource_id
             LEFT JOIN zhl_uebergabe ue ON ue.resource_id = rr.resource_id
             WHERE ri.reference_number IN ($in)
             ORDER BY r.name"
        );
        $st->execute($refs);
        foreach ($st->fetchAll() as $d) {
            $bookingDevices[(string)$d['reference_number']][] = $d;
        }
    }
} catch (Throwable $e) {
    error_log('zhl-calendar enrich: ' . $e->getMessage());
}

$abholArt = ['nicht_noetig' => 'keine Abholung nötig', 'abholen' => 'Abholen', 'abholen_persoenlich' => 'persönliche Übergabe', 'ablageort' => 'Ablageort/Hauspost'];
$rueckArt = ['nicht_noetig' => 'keine Rückgabe nötig', 'abgeben' => 'Abgeben', 'abgeben_persoenlich' => 'persönliche Rückgabe (Termin)'];
$einfArt = ['moeglich' => 'Einführung möglich', 'notwendig' => 'Einführung PFLICHT'];

// ----------------------------------------------------------------------------
// 4) iCal-Helfer.
// ----------------------------------------------------------------------------
$icsEscape = static function (string $s): string {
    $s = str_replace('\\', '\\\\', $s);
    $s = str_replace(';', '\\;', $s);
    $s = str_replace(',', '\\,', $s);
    $s = str_replace(["\r\n", "\n", "\r"], '\\n', $s);
    return $s;
};

// UTC-„Y-m-d H:i:s" → iCal UTC-Stempel „YYYYMMDDTHHMMSSZ".
$icsStamp = static function (?string $utcStr) use ($utc): ?string {
    if (!$utcStr) {
        return null;
    }
    try {
        return (new DateTime($utcStr, $utc))->format('Ymd\THis\Z');
    } catch (Throwable $e) {
        return null;
    }
};

// Zeilenfaltung auf 73 OKTETTEN (RFC 5545 zählt Oktette, nicht Zeichen), Fortsetzung
// mit führendem Space. Gefaltet wird nur an UTF-8-Zeichengrenzen (strlen je Zeichen),
// damit kein Mehrbytezeichen zerschnitten wird.
$icsFold = static function (string $line): string {
    if (strlen($line) <= 73) {
        return $line;
    }
    $chunks = [];
    $buf = '';
    $bufBytes = 0;
    foreach (mb_str_split($line, 1, 'UTF-8') as $ch) {
        $chBytes = strlen($ch);
        if ($bufBytes + $chBytes > 73) {
            $chunks[] = $buf;
            $buf = ' '; // Fortsetzungszeile beginnt mit einem Space
            $bufBytes = 1;
        }
        $buf .= $ch;
        $bufBytes += $chBytes;
    }
    if ($buf !== '') {
        $chunks[] = $buf;
    }
    return implode("\r\n", $chunks);
};

$dtstamp = gmdate('Ymd\THis\Z');

$lines = [];
$lines[] = 'BEGIN:VCALENDAR';
$lines[] = 'VERSION:2.0';
$lines[] = 'PRODID:-//ZHL//Medienausleihe Termine//DE';
$lines[] = 'CALSCALE:GREGORIAN';
$lines[] = 'METHOD:PUBLISH';
$lines[] = $icsFold('X-WR-CALNAME:' . $icsEscape($calName));
$lines[] = 'X-WR-TIMEZONE:Europe/Berlin';

$typeLabel = ['pickup' => 'Abholung', 'return' => 'Rückgabe', 'einf' => 'Einführung'];
$statusMap = ['requested' => 'TENTATIVE', 'confirmed' => 'CONFIRMED', 'done' => 'CONFIRMED'];
$statusLabel = ['requested' => 'angefragt', 'confirmed' => 'terminiert', 'done' => 'erledigt'];

$addEvent = function (array $event) use (&$lines, $icsEscape, $icsFold) {
    $lines[] = 'BEGIN:VEVENT';
    $lines[] = 'UID:' . $event['uid'];
    $lines[] = 'DTSTAMP:' . $event['dtstamp'];
    $lines[] = 'DTSTART:' . $event['start'];
    $lines[] = 'DTEND:' . $event['end'];
    $lines[] = $icsFold('SUMMARY:' . $icsEscape($event['summary']));
    if (!empty($event['location'])) {
        $lines[] = $icsFold('LOCATION:' . $icsEscape($event['location']));
    }
    if (!empty($event['description'])) {
        $lines[] = $icsFold('DESCRIPTION:' . $icsEscape($event['description']));
    }
    if (!empty($event['url'])) {
        $lines[] = $icsFold('URL:' . $icsEscape($event['url']));
    }
    if (!empty($event['status'])) {
        $lines[] = 'STATUS:' . $event['status'];
    }
    $lines[] = 'END:VEVENT';
};

// --- Übergaben / Rückgaben / Einführungen ---
foreach ($handovers as $r) {
    $start = $icsStamp($r['scheduled_start_utc'] ?? null);
    if ($start === null) {
        continue; // ohne Startzeit kein Kalendereintrag
    }
    $end = $icsStamp($r['scheduled_end_utc'] ?? null);
    if ($end === null) {
        // 30-Min-Default, falls kein Ende terminiert wurde.
        try {
            $end = (new DateTime((string)$r['scheduled_start_utc'], $utc))->modify('+30 minutes')->format('Ymd\THis\Z');
        } catch (Throwable $e) {
            $end = $start;
        }
    }

    $t = (string)$r['type'];
    $tl = $typeLabel[$t] ?? $t;
    $resourceName = (string)($r['resource_name'] ?? 'Gerät');
    $name = trim((string)($r['borrower_name'] ?? ''));
    $email = trim((string)($r['borrower_email'] ?? ''));
    if ($name === '') {
        $name = $email !== '' ? $email : 'unbekannt';
    }
    $ref = (string)($r['reference_number'] ?? '');
    $st = (string)$r['status'];
    $ort = trim((string)($r['rueckgabeort'] ?? ''));

    $info = $ref !== '' ? ($bookingInfo[$ref] ?? null) : null;
    $devices = $ref !== '' ? ($bookingDevices[$ref] ?? []) : [];

    // Je Gerät die für den Vorgang relevante Art + Ort (+ Einführungspflicht bei Abholung).
    $deviceLines = [];
    $firstAbholort = '';
    foreach ($devices as $d) {
        $bits = [];
        if ($t === 'return') {
            $art = $rueckArt[(string)($d['rueckgabe'] ?? '')] ?? '';
            if ($art !== '') {
                $bits[] = 'Rückgabe: ' . $art . (!empty($d['rueckgabeort']) ? ' (' . $d['rueckgabeort'] . ')' : '');
            }
        } elseif ($t === 'einf') {
            $ein = $einfArt[(string)($d['einfuehrung'] ?? '')] ?? '';
            if ($ein !== '') {
                $bits[] = $ein;
            }
        } else {
            $art = $abholArt[(string)($d['abholung'] ?? '')] ?? '';
            if ($art !== '') {
                $bits[] = 'Abholung: ' . $art . (!empty($d['abholort']) ? ' (' . $d['abholort'] . ')' : '');
            }
            $ein = $einfArt[(string)($d['einfuehrung'] ?? '')] ?? '';
            if ($ein !== '') {
                $bits[] = $ein;
            }
            if ($firstAbholort === '' && !empty($d['abholort'])) {
                $firstAbholort = (string)$d['abholort'];
            }
        }
        $deviceLines[] = '• ' . (string)$d['name'] . ($bits ? ' — ' . implode(' · ', $bits) : '');
    }

    // Titel: bei Buchungs-weiten Terminen (resource_id NULL) das erste echte Gerät statt
    // „Gerät"; bei Bundles die Anzahl der weiteren Geräte anhängen.
    $sumDevice = $resourceName;
    if (empty($r['resource_id']) && $devices) {
        $sumDevice = (string)$devices[0]['name'];
    }
    if (count($devices) > 1) {
        $sumDevice .= ' +' . (count($devices) - 1);
    }

    $descParts = ['Vorgang: ' . $tl . ($devices ? ' — ' . count($devices) . ' Gerät' . (count($devices) !== 1 ? 'e' : '') : '')];
    $descParts[] = 'Ausleihende:r: ' . $name;
    if ($email !== '') {
        $descParts[] = 'E-Mail: ' . $email;
    }
    if ($info && trim((string)($info['phone'] ?? '')) !== '') {
        $descParts[] = 'Telefon: ' . trim((string)$info['phone']);
    }
    if ($info && trim((string)($info['organization'] ?? '')) !== '') {
        $descParts[] = 'Einrichtung: ' . trim((string)$info['organization']);
    }
    if ($info && trim((string)($info['title'] ?? '')) !== '') {
        $descParts[] = 'Vorhaben: ' . trim((string)$info['title']);
    }
    if ($info) {
        $von = $fmtLocal((string)$info['start_date']);
        $bis = $fmtLocal((string)$info['end_date']);
        if ($von !== '' && $bis !== '') {
            $descParts[] = 'Ausleihzeitraum: ' . $von . ' – ' . $bis . ' Uhr';
        }
    }
    if ($deviceLines) {
        $descParts[] = 'Geräte:';
        foreach ($deviceLines as $dl) {
            $descParts[] = $dl;
        }
    } else {
        $descParts[] = 'Gerät: ' . $resourceName;
        if ($t === 'return' && $ort !== '') {
            $descParts[] = 'Rückgabeort: ' . $ort;
        }
    }
    $descParts[] = 'Status: ' . ($statusLabel[$st] ?? $st);
    if ($ref !== '') {
        $descParts[] = 'Buchung: ' . $ref;
        if ($detailBase !== '') {
            $descParts[] = 'Alle Details: ' . $detailBase . rawurlencode($ref);
        }
    }

    $addEvent([
        'uid' => 'zhl-handover-' . (int)$r['id'] . '@' . $host,
        'dtstamp' => $dtstamp,
        'start' => $start,
        'end' => $end,
        'summary' => $tl . ': ' . $sumDevice . ' — ' . $name,
        'location' => ($t === 'return') ? $ort : $firstAbholort,
        'description' => implode("\n", $descParts),
        'url' => ($ref !== '' && $detailBase !== '') ? $detailBase . rawurlencode($ref) : '',
        'status' => $statusMap[$st] ?? 'CONFIRMED',
    ]);
}

// --- Videostudio-Buchungen ---
foreach ($studio as $s) {
    $start = $icsStamp($s['start_date'] ?? null);
    $end = $icsStamp($s['end_date'] ?? null);
    if ($start === null || $end === null) {
        continue;
    }
    $title = trim((string)($s['title'] ?? ''));
    $owner = trim((string)($s['owner_name'] ?? ''));
    $email = trim((string)($s['owner_email'] ?? ''));
    if ($owner === '') {
        $owner = $email !== '' ? $email : 'unbekannt';
    }
    $ref = (string)($s['reference_number'] ?? '');
    $pending = ((int)($s['status_id'] ?? 1) === 3); // 3 = Pending (Genehmigung offen)

    $summary = 'Videostudio: ' . ($title !== '' ? $title : $owner);
    if ($pending) {
        $summary .= ' (Genehmigung offen)';
    }

    $descParts = [
        'Videostudio-Buchung',
        'Bucher:in: ' . $owner,
    ];
    if ($email !== '') {
        $descParts[] = 'E-Mail: ' . $email;
    }
    if ($title !== '') {
        $descParts[] = 'Titel: ' . $title;
    }
    $rawDesc = trim(strip_tags((string)($s['description'] ?? '')));
    if ($rawDesc !== '') {
        $descParts[] = 'Hinweis: ' . mb_substr($rawDesc, 0, 500);
    }
    if ($ref !== '') {
        $descParts[] = 'Buchung: ' . $ref;
        if ($detailBase !== '') {
            $descParts[] = $detailBase . rawurlencode($ref);
        }
    }

    $addEvent([
        'uid' => 'zhl-studio-' . (int)$s['reservation_instance_id'] . '@' . $host,
        'dtstamp' => $dtstamp,
        'start' => $start,
        'end' => $end,
        'summary' => $summary,
        'location' => (string)($s['resource_name'] ?? 'ZHL Videostudio'),
        'description' => implode("\n", $descParts),
        'url' => ($ref !== '' && $detailBase !== '') ? $detailBase . rawurlencode($ref) : '',
        'status' => $pending ? 'TENTATIVE' : 'CONFIRMED',
    ]);
}

$lines[] = 'END:VCALENDAR';

// ----------------------------------------------------------------------------
// 5) Ausgabe (CRLF, gefaltete Zeilen sind bereits eingebettet).
// ----------------------------------------------------------------------------
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="zhl-medien-termine.ics"');
header('Cache-Control: no-cache, max-age=300');
echo implode("\r\n", $lines) . "\r\n";

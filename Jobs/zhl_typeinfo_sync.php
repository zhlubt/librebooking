<?php

/**
 * ZHL Phase 5 — Sync der Tutorial-/Info-URLs nach zhl_type_info.
 *
 * Pro Geräte-Typ (type_label) wird die kanonische Tutorial-URL (DE) hinterlegt. Quelle der
 * Wahrheit sind die kanonischen Astro-Tutorials (type_label im Frontmatter), ausgeliefert unter
 * media.zhl-ubt.de/Web/tutorials/<slug>/. Sub-Seiten mit nicht-matchendem Label werden ignoriert
 * (Invariante: je Buchungs-Geräte-Typ genau EINE kanonische Info-Seite).
 *
 * Wirkung: zhl-start.php (Karte „Anleitung ansehen"), Buchungs-Erfolgsseite + Bestätigungs-Mail
 * (ZhlTypeInfo::ForReference/EmailBlock) und das (i) am Bundle zeigen dann den Link.
 *
 * Aufruf (im Container, App-Wurzel = /var/www/html):
 *   php Jobs/zhl_typeinfo_sync.php           # DRY-RUN: Ist-Zustand (= Backup) + geplante Änderungen, schreibt NICHT
 *   php Jobs/zhl_typeinfo_sync.php --apply    # schreibt (Transaktion, idempotenter UPSERT)
 *
 * Idempotent über UNIQUE(type_label): erneuter Lauf ändert nur, was sich geändert hat.
 */

declare(strict_types=1);

const TUT_BASE = 'https://media.zhl-ubt.de/Web/tutorials/';
const INFO_TEXT = 'Online-Anleitung mit Schritt-für-Schritt-Erklärung.';

// type_label (muss EXAKT dem Geräte-Typ-Attributwert in der DB entsprechen) → Tutorial-Slug.
$MAP = [
    'Profi-Kamera'                    => 'blackmagic-pocket-6k',
    'Einfache Allround-Kamera'        => 'sony-zv1',
    'Funkmikrofon (mit zwei Sendern)' => 'djimic',
    'Drohne'                          => 'djimini4pro',
    '360-Grad-Kamera'                 => 'insta360x4',
    'Schnitt-/VR-PC'                  => 'legion7pc',
    'Videostudio'                     => 'videostudio',
    'Moderationsmaterial'             => 'moderationsmaterial',
];

$apply = in_array('--apply', $argv, true);

$conf = @require __DIR__ . '/../config/config.php';
$db = is_array($conf) ? ($conf['settings']['database'] ?? []) : [];
$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['hostspec'] ?? '127.0.0.1', $db['name'] ?? ''),
    $db['user'] ?? '',
    $db['password'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// 1) Ist-Zustand als Backup-Ausgabe (beim Lauf in eine Datei umlenken).
echo '-- zhl_type_info BACKUP (' . date('Y-m-d H:i:s') . ")\n";
$cur = $pdo->query('SELECT id, type_label, info_url, info_text, active, updated_at FROM zhl_type_info ORDER BY type_label')->fetchAll(PDO::FETCH_ASSOC);
if (!$cur) {
    echo "-- (Tabelle leer)\n";
}
foreach ($cur as $r) {
    printf(
        "INSERT INTO zhl_type_info (id,type_label,info_url,info_text,active,updated_at) VALUES (%d,%s,%s,%s,%d,%s);\n",
        $r['id'],
        $pdo->quote($r['type_label']),
        $pdo->quote((string)$r['info_url']),
        $r['info_text'] === null ? 'NULL' : $pdo->quote($r['info_text']),
        (int)$r['active'],
        $r['updated_at'] === null ? 'NULL' : $pdo->quote($r['updated_at'])
    );
}

// 2) Validierung: jeder geplante type_label muss als Geräte-Typ in der Live-DB existieren,
//    sonst greift der Link nirgends (stiller Fehlschlag) → laut machen.
$liveTypes = [];
$q = $pdo->query("SELECT DISTINCT cav.attribute_value AS t FROM custom_attribute_values cav
    JOIN custom_attributes ca ON ca.custom_attribute_id = cav.custom_attribute_id
    WHERE ca.display_label = 'Geräte-Typ' AND ca.attribute_category = 4");
foreach ($q as $row) {
    $liveTypes[(string)$row['t']] = true;
}

echo "\n-- Geplante Einträge:\n";
$plan = [];
foreach ($MAP as $label => $slug) {
    $url = TUT_BASE . $slug . '/';
    $exists = isset($liveTypes[$label]);
    printf("%-34s %-22s %s\n", $label, $exists ? '[Typ live OK]' : '[!! Typ NICHT in DB]', $url);
    if ($exists) {
        $plan[$label] = $url;
    }
}

if (!$apply) {
    echo "\nDRY-RUN — nichts geschrieben. Zum Schreiben mit --apply ausführen.\n";
    exit(0);
}

// 3) Schreiben: Transaktion + idempotenter UPSERT (UNIQUE type_label).
$pdo->beginTransaction();
try {
    $st = $pdo->prepare('INSERT INTO zhl_type_info (type_label, info_url, info_text, active, updated_at)
        VALUES (:l, :u, :t, 1, NOW())
        ON DUPLICATE KEY UPDATE info_url = VALUES(info_url), info_text = VALUES(info_text), active = 1, updated_at = NOW()');
    $n = 0;
    foreach ($plan as $label => $url) {
        $st->execute([':l' => $label, ':u' => $url, ':t' => INFO_TEXT]);
        $n++;
    }
    $pdo->commit();
    echo "\n-- $n Einträge upserted (commit ok).\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'FEHLER, rollback: ' . $e->getMessage() . "\n");
    exit(1);
}

// 4) Smoke: aktiver Endzustand mit Link.
echo "-- Endzustand (aktive Einträge mit info_url):\n";
foreach ($pdo->query("SELECT type_label, info_url, active FROM zhl_type_info WHERE info_url <> '' ORDER BY type_label") as $r) {
    printf("  %-34s %s (active=%d)\n", $r['type_label'], $r['info_url'], (int)$r['active']);
}

<?php
/**
 * ZHL: Öffentliche Startseite der Medienausleihe.
 *
 * Eigenständige, upgrade-sichere ZHL-Datei (kein LibreBooking-Core berührt).
 * Führt Nutzende klar ein: "Bundle buchen" vs. "Geräte einzeln buchen".
 * Liest nur lesend ein paar Kennzahlen direkt aus der DB (Anzeige).
 * Optik: ZHL-Studio-Look, UBT-Grün #009260, System-Font, Linien-Icons.
 * Zweisprachig DE/EN über data-en-Attribute + kleiner JS-Umschalter. Sie-Form.
 */

declare(strict_types=1);

$conf = @require __DIR__ . '/../config/config.php';
$db = is_array($conf) ? ($conf['settings']['database'] ?? []) : [];

// Kennzahlen (rein lesend, still degradierend wenn DB nicht erreichbar).
$stats = ['devices' => null, 'categories' => null, 'loans' => null, 'users' => null];
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['hostspec'] ?? '127.0.0.1', $db['name'] ?? ''),
        $db['user'] ?? '',
        $db['password'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stats['devices']    = (int)$pdo->query('SELECT COUNT(*) FROM resources')->fetchColumn();
    $stats['categories'] = (int)$pdo->query('SELECT COUNT(*) FROM schedules')->fetchColumn();
    $stats['loans']      = (int)$pdo->query('SELECT COUNT(*) FROM reservation_series')->fetchColumn();
    $stats['users']      = (int)$pdo->query('SELECT COUNT(DISTINCT owner_id) FROM reservation_series')->fetchColumn();
} catch (Throwable $e) {
    // Anzeige ohne Zahlen ("–"), Seite bleibt voll funktionsfähig.
}

/** Zahl deutsch formatiert, mit Fallback "–". */
function num(?int $n): string { return $n === null ? '–' : number_format($n, 0, ',', '.'); }

/** HTML-Escape (UTF-8). */
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Schwierigkeits-Label DE/EN. */
function diffLabel(string $d): string { return ['einfach' => 'Einfach', 'fortgeschritten' => 'Fortgeschritten', 'profi' => 'Profi'][$d] ?? ucfirst($d); }
function diffLabelEn(string $d): string { return ['einfach' => 'Easy', 'fortgeschritten' => 'Advanced', 'profi' => 'Pro'][$d] ?? ucfirst($d); }

/** Einweisungs-Label DE/EN (leer = keine). */
function einwLabel(string $l): string { return ['empfehlenswert' => 'Einführung empfehlenswert', 'zwingend' => 'Einführung zwingend', 'beratung' => 'Beratung vorab'][$l] ?? ''; }
function einwLabelEn(string $l): string { return ['empfehlenswert' => 'Induction recommended', 'zwingend' => 'Induction required', 'beratung' => 'Prior consultation'][$l] ?? ''; }

/**
 * Modell-Basisname: blendet Durchnummerierungen aus, damit baugleiche Geräte zu
 * "Modell ×N" zusammenfallen (z. B. "Meta Quest 2_1/_2/_3" → "Meta Quest 2").
 */
function modelBase(string $n): string {
    $b = $n;
    $b = preg_replace('/\s*[-–]\s*Version\s*\d+\s*$/iu', '', $b);
    $b = preg_replace('/\s*Nr\.?\s*\d+\s*$/iu', '', $b);
    $b = preg_replace('/[\s_]+\d+\s*$/u', '', $b);
    $b = trim($b);
    return $b === '' ? trim($n) : $b;
}

/** Linien-Icon (SVG) je Geräte-Typ; robust per Schlüsselwort, mit Fallback. */
function typeIcon(string $t): string {
    $k = mb_strtolower($t, 'UTF-8');
    $w = static fn(string $inner): string => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
    $has = static fn(string $needle): bool => mb_strpos($k, $needle) !== false;
    if ($has('360') || $has('teleskop')) {
        return $w('<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>');
    }
    if ($has('brille')) {
        return $w('<rect x="2" y="8" width="20" height="8" rx="4"/><path d="M12 8v8"/>');
    }
    if ($has('drohne')) {
        return $w('<circle cx="6" cy="6" r="3"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="18" r="3"/><path d="M8.5 8.5l7 7M15.5 8.5l-7 7"/>');
    }
    if ($has('stativ')) {
        return $w('<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M12 6v6M12 12l-6 9M12 12l6 9M9 21h6"/>');
    }
    if ($has('gimbal')) {
        return $w('<rect x="9" y="2" width="6" height="5" rx="1"/><path d="M12 7v5M8 12h8M10 12v8M14 12v8"/>');
    }
    if ($has('objektiv')) {
        return $w('<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><path d="M12 3v6M21 12h-6M12 21v-6M3 12h6"/>');
    }
    if ($has('mikrofon') || $has('mic')) {
        return $w('<rect x="9" y="2" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3M8 21h8"/>');
    }
    if ($has('moderation')) {
        return $w('<rect x="3" y="4" width="18" height="12" rx="1"/><path d="M12 16v5M8 21h8"/>');
    }
    if ($has('pc') || $has('schnitt')) {
        return $w('<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>');
    }
    if ($has('smartphone') || $has('handy')) {
        return $w('<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/>');
    }
    if ($has('studio')) {
        return $w('<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>');
    }
    if ($has('kamera')) {
        return $w('<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>');
    }
    return $w('<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/>');
}

// Katalog-Daten (Bundles + Geräte nach Typ), rein lesend & self-updating aus der DB.
// Degradiert still zu leeren Listen, falls die DB nicht erreichbar ist.
$bundles = [];
$mediaTypes = [];
$typeInfo = [];   // type_label → Tutorial/Info-URL (zhl_type_info), leer wenn keine
try {
    if (!isset($pdo)) {
        $pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['hostspec'] ?? '127.0.0.1', $db['name'] ?? ''),
            $db['user'] ?? '',
            $db['password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    $bRows = $pdo->query('SELECT id, name, use_case, difficulty, hint, einweisung_level FROM zhl_bundle WHERE active = 1 ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    if ($bRows) {
        $ids = implode(',', array_map('intval', array_column($bRows, 'id')));
        $items = $pdo->query("SELECT bundle_id, type_label, quantity, required, note, meta FROM zhl_bundle_item WHERE bundle_id IN ($ids) ORDER BY bundle_id, sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
        $byBundle = [];
        foreach ($items as $it) { $byBundle[$it['bundle_id']][] = $it; }
        foreach ($bRows as &$b) { $b['items'] = $byBundle[$b['id']] ?? []; }
        unset($b);
        $bundles = $bRows;
    }

    // Je Geräte-Typ die einzelnen Geräte (mit resource_id + schedule_id), damit man direkt buchen kann.
    $mRows = $pdo->query("SELECT cav.attribute_value AS typ, r.name AS name, r.resource_id AS rid, r.schedule_id AS sid
        FROM custom_attribute_values cav
        JOIN custom_attributes ca ON ca.custom_attribute_id = cav.custom_attribute_id AND ca.display_label = 'Geräte-Typ'
        JOIN resources r ON r.resource_id = cav.entity_id
        WHERE r.status_id = 1
        ORDER BY cav.attribute_value, r.sort_order, r.name")->fetchAll(PDO::FETCH_ASSOC);
    $grouped = [];
    foreach ($mRows as $r) { $grouped[$r['typ']][] = ['name' => $r['name'], 'rid' => (int)$r['rid'], 'sid' => (int)$r['sid']]; }
    foreach ($grouped as $typ => $units) {
        // Baugleiche Geräte zu "Modell ×N" zusammenfassen; ein repräsentatives Gerät trägt den Buchungs-Link
        // (zhl-book.php weicht via Pool-Fallback ohnehin auf eine freie gleichtypige Einheit aus).
        $models = [];
        foreach ($units as $u) {
            $base = modelBase($u['name']);
            if (!isset($models[$base])) { $models[$base] = ['count' => 0, 'rid' => $u['rid'], 'sid' => $u['sid']]; }
            $models[$base]['count']++;
        }
        $mediaTypes[] = ['typ' => $typ, 'count' => count($units), 'models' => $models];
    }
    usort($mediaTypes, static fn($a, $b) => $b['count'] <=> $a['count'] ?: strcmp($a['typ'], $b['typ']));

    // Tutorials/Anleitungen je Geräte-Typ (zhl_type_info); fehlt die Tabelle, bleibt die Liste leer.
    try {
        $tiRows = $pdo->query("SELECT type_label, info_url FROM zhl_type_info WHERE active = 1 AND info_url <> ''")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tiRows as $r) { $typeInfo[trim((string)$r['type_label'])] = trim((string)$r['info_url']); }
    } catch (Throwable $e) {
        // keine Tutorials → einfach keine "Anleitung"-Links
    }
} catch (Throwable $e) {
    // Katalog bleibt leer; die Seite zeigt dann nur die statischen Abschnitte.
}

// Kuratierte Filter-Kategorien — spiegelt ZhlDashboardPresenter::categoryMap() (der In-App-Filter),
// damit die Startseite dieselben Kategorien wie die Buchungs-Oberfläche zeigt. Reine Anzeige-Logik.
$catMap = [
    ['key' => 'mikro', 'label' => 'Mikrofone', 'label_en' => 'Microphones', 'types' => ['Funkmikrofon (mit zwei Sendern)', 'Podcast-Mikrofon', 'Podcast-Mikrofon (Shure)', 'Podcast-Mikrofon (Yeti)']],
    ['key' => 'videostudio', 'label' => 'Videostudio', 'label_en' => 'Video studio', 'types' => ['Videostudio']],
    ['key' => 'smartphone', 'label' => 'Smartphone-Video-Kit', 'label_en' => 'Smartphone video kit', 'types' => ['Smartphone-Video-Kit']],
    ['key' => 'kamera', 'label' => 'Kameras', 'label_en' => 'Cameras', 'types' => ['Profi-Kamera', 'Einfache Allround-Kamera', 'Objektiv', 'Gimbal', 'Stativ', 'Kleines Kamerastativ', 'Richtmikrofon']],
    ['key' => 'moderation', 'label' => 'Moderationsmaterial', 'label_en' => 'Facilitation materials', 'types' => ['Moderationsmaterial']],
    ['key' => 'schnitt', 'label' => 'Schnittcomputer', 'label_en' => 'Editing computer', 'types' => ['Schnitt-/VR-PC']],
    ['key' => 'immersive', 'label' => 'Immersive Medien (VR / AR / 3D)', 'label_en' => 'Immersive media (VR / AR / 3D)', 'types' => ['VR-Brille', 'AR-Brille', '360-Grad-Kamera', 'Teleskopstange (360°-Kamera)']],
    ['key' => 'drohne', 'label' => 'Drohne', 'label_en' => 'Drone', 'types' => ['Drohne']],
];
$typeToCat = [];
foreach ($catMap as $c) { foreach ($c['types'] as $t) { $typeToCat[$t] = $c['key']; } }

// Jedem Geräte-Typ seine Kategorie zuordnen (für den clientseitigen Filter) + Geräte je Kategorie zählen.
$catCounts = [];
foreach ($mediaTypes as &$mt) {
    $mt['cat'] = $typeToCat[$mt['typ']] ?? 'weitere';
    $catCounts[$mt['cat']] = ($catCounts[$mt['cat']] ?? 0) + $mt['count'];
}
unset($mt);

// Sichtbare Filter-Buttons: nur Kategorien mit Treffern, in Map-Reihenfolge, „Weitere" ans Ende.
$categories = [];
foreach ($catMap as $c) {
    if (!empty($catCounts[$c['key']])) {
        $categories[] = ['key' => $c['key'], 'label' => $c['label'], 'label_en' => $c['label_en'], 'count' => $catCounts[$c['key']]];
    }
}
if (!empty($catCounts['weitere'])) {
    $categories[] = ['key' => 'weitere', 'label' => 'Weitere Geräte', 'label_en' => 'Other devices', 'count' => $catCounts['weitere']];
}
$totalDevices = array_sum(array_column($mediaTypes, 'count'));

// Vorbelegtes Startdatum für die Direkt-Buchung: heute + 1 Woche (Planungs-Vorlauf / Mindest-Vorlaufzeit).
// zhl-book.php nimmt dieses rd als Default-Start; sid setzt den passenden Schedule (wie im Dashboard-Link).
$bookDate = date('Y-m-d', strtotime('+7 days'));
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Medienausleihe ZHL</title>
<style>
  :root {
    --green:#009260; --green-dark:#00744c;
    --grad:linear-gradient(135deg,#009260 0%,#007a50 100%);
    --grad-hero:linear-gradient(150deg,#007a50 0%,#009260 55%,#00a86d 100%);
    --ink:#1f2a25; --ink-2:#3f4d46; --muted:#6b7a72;
    --line:#e3eae6; --bg:#f5f8f6; --bg-soft:#eef3f0; --card:#fff;
    --foot:#14201b;
    --r-sm:10px; --r:14px; --r-lg:20px;
    --shadow:0 2px 8px rgba(20,40,30,.06);
    --shadow-lg:0 16px 40px rgba(0,116,76,.14);
    --maxw:1140px; --ease:cubic-bezier(.2,.7,.2,1);
  }
  * { box-sizing:border-box; }
  html { scroll-behavior:smooth; }
  body { margin:0; background:var(--bg); color:var(--ink-2);
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,Cantarell,sans-serif;
    line-height:1.55; -webkit-font-smoothing:antialiased; }
  a { color:inherit; text-decoration:none; }
  h1,h2,h3 { color:var(--ink); font-weight:650; letter-spacing:-.02em; margin:0; }
  .wrap { max-width:var(--maxw); margin:0 auto; padding:0 24px; }
  .eyebrow { font-size:13px; font-weight:600; letter-spacing:.04em; color:var(--green-dark); margin:0 0 12px; }
  .lead { color:var(--muted); font-size:clamp(16px,1.4vw,18px); }

  header { position:sticky; top:0; z-index:50; background:var(--grad); color:#fff; box-shadow:0 2px 14px rgba(0,80,52,.18); }
  .nav { display:flex; align-items:center; gap:24px; height:68px; }
  .logo { display:flex; align-items:center; gap:12px; }
  .logo img { height:34px; width:auto; filter:brightness(0) invert(1); }
  .nav-links { display:flex; gap:4px; margin-left:auto; }
  .nav-links a { padding:8px 14px; border-radius:var(--r-sm); font-size:15px; font-weight:500; color:rgba(255,255,255,.9); transition:background .15s var(--ease),color .15s var(--ease); }
  .nav-links a:hover { background:rgba(255,255,255,.14); color:#fff; }
  .nav-links a.active { background:rgba(255,255,255,.18); color:#fff; font-weight:600; }
  .nav-cta { background:#fff; color:var(--green-dark) !important; font-weight:600; padding:9px 18px; border-radius:var(--r-sm); font-size:15px; transition:transform .12s var(--ease),box-shadow .15s var(--ease); }
  .nav-cta:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(0,40,25,.25); }
  .lang-btn { background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.4); color:#fff; font:inherit; font-size:13px; font-weight:600; padding:7px 12px; border-radius:var(--r-sm); cursor:pointer; transition:background .15s var(--ease); }
  .lang-btn:hover { background:rgba(255,255,255,.24); }
  @media (max-width:900px){ .nav-links { display:none; } }

  .hero { position:relative; overflow:hidden; background:var(--grad-hero); color:#fff; padding:clamp(40px,5vw,64px) 0 clamp(44px,5.5vw,72px); }
  .hero .motif { position:absolute; inset:0; opacity:.14; pointer-events:none; }
  .hero .motif svg { position:absolute; }
  .hero-inner { position:relative; max-width:760px; }
  .hero h1 { color:#fff; font-size:clamp(34px,5vw,52px); line-height:1.06; }
  .hero p { color:rgba(255,255,255,.92); font-size:clamp(17px,2vw,21px); max-width:600px; margin:18px 0 32px; }
  .hero-cta { display:flex; gap:14px; flex-wrap:wrap; }
  .btn { display:inline-flex; align-items:center; gap:9px; cursor:pointer; padding:13px 24px; border-radius:var(--r-sm); font-size:16px; font-weight:600; border:none; transition:transform .12s var(--ease),box-shadow .15s var(--ease),background .15s var(--ease); }
  .btn svg { width:19px; height:19px; }
  .btn-light { background:#fff; color:var(--green-dark); box-shadow:0 6px 18px rgba(0,40,25,.18); }
  .btn-light:hover { transform:translateY(-2px); box-shadow:0 12px 26px rgba(0,40,25,.26); }
  .btn-ghost { background:rgba(255,255,255,.10); color:#fff; border:1.5px solid rgba(255,255,255,.55); }
  .btn-ghost:hover { background:rgba(255,255,255,.20); }

  .notice { background:#fff; border-bottom:1px solid var(--line); padding:16px 0; }
  .notice .wrap { display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
  .notice .pill { display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:11px; background:#eaf6ef; color:var(--green-dark); flex:none; }
  .notice .pill svg { width:20px; height:20px; }
  .notice b { color:var(--ink); }
  .notice .grow { flex:1; min-width:200px; }
  .notice a.mini { font-size:14px; font-weight:600; color:var(--green-dark); border:1px solid var(--line); padding:8px 14px; border-radius:var(--r-sm); white-space:nowrap; }
  .notice a.mini:hover { background:var(--bg-soft); }

  section.block { padding:clamp(56px,7vw,92px) 0; }
  .sec-head { max-width:660px; margin:0 auto clamp(36px,4vw,52px); text-align:center; }
  .sec-head h2 { font-size:clamp(26px,3.4vw,38px); }
  .sec-head p { margin:12px 0 0; }

  .decide { background:var(--card); }
  .choices { display:grid; grid-template-columns:1fr 1fr; gap:22px; }
  @media (max-width:760px){ .choices { grid-template-columns:1fr; } }
  .choice { position:relative; display:flex; flex-direction:column; border:1px solid var(--line); border-radius:var(--r-lg); background:var(--card); padding:30px 30px 26px; box-shadow:var(--shadow); transition:transform .18s var(--ease),box-shadow .18s var(--ease),border-color .18s var(--ease); }
  .choice:hover { transform:translateY(-4px); box-shadow:var(--shadow-lg); border-color:#bfe3d2; }
  .choice .ic { width:56px; height:56px; border-radius:16px; background:var(--grad); color:#fff; display:flex; align-items:center; justify-content:center; margin-bottom:20px; box-shadow:0 8px 18px rgba(0,146,96,.28); }
  .choice .ic svg { width:28px; height:28px; }
  .choice h3 { font-size:22px; }
  .choice .when { font-size:13px; font-weight:600; color:var(--green-dark); margin:6px 0 10px; }
  .choice p { margin:0 0 18px; color:var(--muted); font-size:15.5px; }
  .choice ul { list-style:none; margin:0 0 22px; padding:0; display:flex; flex-direction:column; gap:9px; }
  .choice li { display:flex; align-items:flex-start; gap:10px; font-size:14.5px; color:var(--ink-2); }
  .choice li svg { width:18px; height:18px; color:var(--green); flex:none; margin-top:2px; }
  .choice .go { margin-top:auto; display:inline-flex; align-items:center; gap:8px; align-self:flex-start; font-weight:600; font-size:15.5px; color:#fff; background:var(--grad); padding:11px 20px; border-radius:var(--r-sm); box-shadow:0 4px 12px rgba(0,146,96,.25); transition:transform .12s var(--ease),box-shadow .12s var(--ease); }
  .choice .go:hover { transform:translateX(2px); box-shadow:0 7px 18px rgba(0,146,96,.34); }
  .choice .go svg { width:18px; height:18px; }

  .third { margin-top:22px; display:flex; align-items:center; gap:16px; flex-wrap:wrap; border:1px dashed var(--line); border-radius:var(--r); background:var(--bg-soft); padding:18px 24px; }
  .third .ic2 { width:42px; height:42px; border-radius:12px; background:#fff; border:1px solid var(--line); display:flex; align-items:center; justify-content:center; color:var(--green-dark); flex:none; }
  .third .ic2 svg { width:20px; height:20px; }
  .third .grow { flex:1; min-width:200px; }
  .third b { color:var(--ink); }
  .third .muted { color:var(--muted); font-size:14.5px; }
  .third a.go2 { font-weight:600; color:var(--green-dark); display:inline-flex; align-items:center; gap:6px; }
  .third a.go2 svg { width:16px; height:16px; }

  .steps { display:grid; grid-template-columns:repeat(4,1fr); gap:18px; }
  @media (max-width:880px){ .steps { grid-template-columns:repeat(2,1fr); } }
  @media (max-width:480px){ .steps { grid-template-columns:1fr; } }
  .step { background:var(--card); border:1px solid var(--line); border-radius:var(--r); padding:26px 22px; text-align:center; box-shadow:var(--shadow); }
  .step .num { width:42px; height:42px; border-radius:50%; background:var(--grad); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:17px; margin:0 auto 14px; box-shadow:0 6px 14px rgba(0,146,96,.28); }
  .step .sic { color:var(--green-dark); margin-bottom:8px; }
  .step .sic svg { width:26px; height:26px; }
  .step h3 { font-size:17px; margin-bottom:6px; }
  .step p { font-size:14px; color:var(--muted); margin:0; }

  .stats { background:var(--grad-hero); color:#fff; }
  .stats .sec-head h2 { color:#fff; }
  .stats .sec-head p { color:rgba(255,255,255,.85); }
  .stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:18px; }
  @media (max-width:760px){ .stat-grid { grid-template-columns:repeat(2,1fr); } }
  .stat { background:rgba(255,255,255,.10); border:1px solid rgba(255,255,255,.18); border-radius:var(--r); padding:26px 18px; text-align:center; }
  .stat .sic { opacity:.9; margin-bottom:10px; }
  .stat .sic svg { width:26px; height:26px; }
  .stat .big { font-size:clamp(28px,4vw,40px); font-weight:700; line-height:1; color:#fff; }
  .stat .lab { margin-top:8px; font-size:13px; color:rgba(255,255,255,.82); letter-spacing:.02em; }

  .cats { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; }
  .cat { background:var(--card); border:1px solid var(--line); border-radius:var(--r); padding:26px; box-shadow:var(--shadow); transition:transform .16s var(--ease),box-shadow .16s var(--ease); }
  .cat:hover { transform:translateY(-3px); box-shadow:var(--shadow-lg); }
  .cat .ic { width:50px; height:50px; border-radius:14px; background:#eaf6ef; color:var(--green-dark); display:flex; align-items:center; justify-content:center; margin-bottom:16px; }
  .cat .ic svg { width:25px; height:25px; }
  .cat h3 { font-size:18px; margin-bottom:6px; }
  .cat p { font-size:14.5px; color:var(--muted); margin:0 0 12px; }
  .cat .count { font-size:13px; font-weight:600; color:var(--green-dark); }

  .cta { background:var(--grad); color:#fff; text-align:center; }
  .cta h2 { color:#fff; font-size:clamp(26px,3.4vw,36px); }
  .cta p { color:rgba(255,255,255,.9); margin:12px auto 28px; max-width:520px; }

  footer { background:var(--foot); color:#aab8b1; }
  .foot-grid { display:grid; grid-template-columns:1.6fr 1fr 1fr 1.2fr; gap:36px; padding:56px 0 36px; }
  @media (max-width:820px){ .foot-grid { grid-template-columns:1fr 1fr; gap:28px; } }
  @media (max-width:480px){ .foot-grid { grid-template-columns:1fr; } }
  .foot-brand img.zhl { height:30px; filter:brightness(0) invert(1); opacity:.92; margin-bottom:14px; }
  .foot-brand p { font-size:14px; color:#8fa099; max-width:280px; margin:0 0 18px; }
  .foot-uni { display:inline-flex; align-items:center; gap:10px; background:#fff; border-radius:10px; padding:8px 12px; }
  .foot-uni img { height:30px; width:auto; display:block; }
  footer h4 { color:#fff; font-size:13px; font-weight:600; letter-spacing:.04em; margin:0 0 16px; }
  footer h4::after { content:""; display:block; width:26px; height:2px; background:var(--green); margin-top:8px; border-radius:2px; }
  .foot-links { display:flex; flex-direction:column; gap:11px; }
  .foot-links a { font-size:14px; color:#aab8b1; display:inline-flex; align-items:center; gap:9px; transition:color .15s var(--ease); }
  .foot-links a:hover { color:#fff; }
  .foot-links a svg { width:16px; height:16px; color:var(--green); }
  .foot-contact { font-size:14px; color:#aab8b1; }
  .foot-contact a { color:#aab8b1; }
  .foot-contact a:hover { color:#fff; }
  .foot-contact .row { display:flex; align-items:flex-start; gap:10px; margin-bottom:10px; }
  .foot-contact .row svg { width:16px; height:16px; color:var(--green); flex:none; margin-top:3px; }
  .foot-bottom { border-top:1px solid rgba(255,255,255,.08); padding:20px 0; text-align:center; font-size:13px; color:#7a8a83; line-height:1.9; }
  .foot-bottom a { color:#9fb3ab; text-decoration:underline; }
  .foot-bottom a:hover { color:#fff; }

  /* Katalog: Bundles */
  .btn-primary { border:none; cursor:pointer; background:var(--grad); color:#fff; font-weight:600; box-shadow:0 6px 16px rgba(0,146,96,.28); }
  .btn-primary:hover { transform:translateY(-1px); box-shadow:0 10px 22px rgba(0,146,96,.36); }
  .sets-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:20px; }
  .set-card { position:relative; background:var(--card); border:1px solid var(--line); border-radius:var(--r-lg); padding:24px; box-shadow:var(--shadow); display:flex; flex-direction:column; transition:transform .12s var(--ease),box-shadow .15s var(--ease); }
  .set-card:hover { transform:translateY(-3px); box-shadow:var(--shadow-lg); }
  .set-card h3 { font-size:18px; margin:0 0 4px; padding-right:96px; }
  .set-card .uc { color:var(--muted); font-size:14px; margin:0 0 16px; }
  .badge { position:absolute; top:22px; right:22px; font-size:11px; font-weight:700; letter-spacing:.03em; padding:4px 10px; border-radius:999px; text-transform:uppercase; }
  .badge.einfach { background:#e6f7ef; color:#067a4b; }
  .badge.fortgeschritten { background:#fff4e0; color:#a86a12; }
  .badge.profi { background:#fde8e8; color:#b3261e; }
  .set-card .contains { font-size:11.5px; font-weight:700; color:var(--ink); letter-spacing:.05em; text-transform:uppercase; margin:2px 0 10px; }
  .set-card ul.items { list-style:none; margin:0 0 16px; padding:0; display:flex; flex-direction:column; gap:8px; }
  .set-card ul.items li { display:flex; gap:9px; align-items:flex-start; font-size:14px; color:var(--ink-2); }
  .set-card ul.items li svg { width:16px; height:16px; stroke:var(--green); flex:none; margin-top:2px; }
  .set-card ul.items li.muted { color:var(--muted); }
  .set-card ul.items li.muted svg { stroke:var(--muted); }
  .set-card ul.items li .q { color:var(--muted); font-weight:600; }
  .set-card .hint { font-size:13px; color:var(--muted); margin:auto 0 0; padding-top:14px; border-top:1px solid var(--line); }
  .set-card .einw { display:inline-flex; align-items:center; gap:7px; font-size:12.5px; font-weight:600; padding:6px 11px; border-radius:999px; background:var(--bg-soft); color:var(--ink-2); margin-top:14px; align-self:flex-start; }
  .set-card .einw svg { width:15px; height:15px; flex:none; }
  .set-card .einw.zwingend { background:#fde8e8; color:#b3261e; }
  .set-card .einw.empfehlenswert, .set-card .einw.beratung { background:#fff4e0; color:#a86a12; }

  /* Katalog: Geräte-Typen */
  .types-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(244px,1fr)); gap:16px; }
  .type-card { background:var(--card); border:1px solid var(--line); border-radius:var(--r); padding:18px; box-shadow:var(--shadow); transition:transform .12s var(--ease),box-shadow .15s var(--ease); }
  .type-card:hover { transform:translateY(-2px); box-shadow:var(--shadow-lg); }
  .type-card .th { display:flex; align-items:center; gap:12px; margin-bottom:11px; }
  .type-card .ti { width:42px; height:42px; border-radius:11px; background:var(--bg-soft); display:flex; align-items:center; justify-content:center; flex:none; }
  .type-card .ti svg { width:22px; height:22px; stroke:var(--green-dark); }
  .type-card h3 { font-size:15px; line-height:1.22; }
  .type-card .cnt { font-size:12.5px; font-weight:700; color:var(--green-dark); background:#e6f7ef; padding:3px 9px; border-radius:999px; margin-left:auto; flex:none; white-space:nowrap; }
  .type-card .models { display:flex; flex-wrap:wrap; gap:7px; }

  /* Kategorie-Filter über "Alle Geräte einzeln buchbar" */
  .cat-filter { display:flex; flex-wrap:wrap; gap:9px; justify-content:center; max-width:920px; margin:0 auto 16px; }
  .cf { display:inline-flex; align-items:center; gap:8px; cursor:pointer; font:inherit; font-size:14px; font-weight:600; color:var(--ink-2); background:var(--card); border:1px solid var(--line); padding:8px 14px; border-radius:999px; transition:background .15s var(--ease),color .15s var(--ease),border-color .15s var(--ease),box-shadow .15s var(--ease); }
  .cf:hover { border-color:#bfe3d2; color:var(--green-dark); }
  .cf.active { background:var(--grad); color:#fff; border-color:transparent; box-shadow:0 4px 12px rgba(0,146,96,.25); }
  .cf .cf-c { font-size:12px; font-weight:700; min-width:20px; height:20px; padding:0 6px; display:inline-flex; align-items:center; justify-content:center; border-radius:999px; background:#e6f7ef; color:var(--green-dark); }
  .cf.active .cf-c { background:rgba(255,255,255,.25); color:#fff; }
  .cat-hint { text-align:center; font-size:13.5px; color:var(--muted); margin:0 auto 30px; max-width:620px; }

  /* Anklickbare Geräte (direkt buchen) + Tutorial-Link */
  .model-link { display:inline-flex; align-items:center; gap:5px; font-size:13px; color:var(--ink-2); background:var(--bg-soft); border:1px solid var(--line); padding:5px 11px; border-radius:999px; transition:transform .13s var(--ease),background .13s var(--ease),color .13s var(--ease),border-color .13s var(--ease),box-shadow .13s var(--ease); }
  .model-link:hover { background:var(--green); border-color:var(--green); color:#fff; transform:translateY(-1px); box-shadow:0 4px 10px rgba(0,146,96,.25); }
  .model-link .q { font-weight:700; opacity:.7; }
  .model-link .arr { width:13px; height:13px; opacity:0; margin-left:-3px; transition:opacity .13s var(--ease),margin .13s var(--ease); }
  .model-link:hover .arr { opacity:1; margin-left:0; }
  .tut-link { display:inline-flex; align-items:center; gap:6px; margin-top:13px; font-size:13px; font-weight:600; color:var(--green-dark); }
  .tut-link svg { width:15px; height:15px; flex:none; }
  .tut-link:hover { text-decoration:underline; }
</style>
</head>
<body>

<header>
  <div class="wrap nav">
    <a class="logo" href="#top" aria-label="ZHL"><img src="img/ZHL-Logo-Text_Side-Green-Background.png" alt="Zentrum für Hochschullehre"></a>
    <nav class="nav-links">
      <a href="#top" class="active" data-en="Home">Start</a>
      <a href="#buchen" data-en="Book">Buchen</a>
      <a href="#sets" data-en="Bundles">Bundles</a>
      <a href="#kategorien" data-en="Equipment">Ausstattung</a>
      <a href="#ablauf" data-en="How it works">Ablauf</a>
    </nav>
    <a class="nav-cta" href="zhl-login.php" data-en="Sign in">Anmelden</a>
    <button class="lang-btn" data-lang-btn onclick="toggleLang()">EN</button>
  </div>
</header>

<div class="hero" id="top">
  <div class="motif" aria-hidden="true">
    <svg width="420" height="320" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1" style="top:-30px; right:-40px;"><rect x="2" y="6" width="14" height="12" rx="2"/><path d="M16 9l5-3v12l-5-3"/></svg>
    <svg width="240" height="240" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1" style="bottom:-50px; left:6%;"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
    <svg width="170" height="170" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1" style="top:40%; right:14%;"><rect x="6" y="2" width="12" height="20" rx="6"/><line x1="12" y1="6" x2="12" y2="10"/></svg>
  </div>
  <div class="wrap hero-inner">
    <p class="eyebrow" style="color:rgba(255,255,255,.85)" data-en="Media lending · Centre for University Teaching">Medienausleihe · Zentrum für Hochschullehre</p>
    <h1 data-en="Professional media for teaching">Professionelle Medien für die Lehre</h1>
    <p data-en="The ZHL provides members of the University of Bayreuth with high-quality media and video production equipment. Reserve online, pick up on site.">Das ZHL stellt Mitgliedern der Universität Bayreuth hochwertige Medien- und Videoproduktionstechnik bereit. Online reservieren, vor Ort abholen.</p>
    <div class="hero-cta">
      <a class="btn btn-light" href="#buchen">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span data-en="Book now">Jetzt buchen</span>
      </a>
      <a class="btn btn-ghost" href="#ablauf" data-en="How it works">So funktioniert's</a>
    </div>
  </div>
</div>

<div class="notice">
  <div class="wrap">
    <span class="pill"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
    <span class="grow" data-en="Sign in only with your <b>@uni-bayreuth.de</b> address. You set your <b>own password</b> here, not the central university password.">Anmeldung nur mit Ihrer <b>@uni-bayreuth.de</b>-Adresse. Sie legen sich hier ein <b>eigenes Passwort</b> an, nicht das zentrale Uni-Passwort.</span>
    <a class="mini" href="register.php" data-en="Create account">Konto anlegen</a>
    <a class="mini" href="zhl-login.php" data-en="Sign in">Anmelden</a>
  </div>
</div>

<section class="block decide" id="buchen">
  <div class="wrap">
    <div class="sec-head">
      <p class="eyebrow" data-en="Two ways to book">Zwei Wege zur Buchung</p>
      <h2 data-en="How would you like to book?">Wie möchten Sie buchen?</h2>
      <p class="lead" data-en="Have a specific project in mind? Take a ready-made set. Know exactly which device you need? Book it individually.">Sie haben ein konkretes Vorhaben? Nehmen Sie ein fertiges Set. Sie wissen genau, welches Gerät Sie brauchen? Buchen Sie es einzeln.</p>
    </div>

    <div class="choices">
      <div class="choice">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>
        <h3 data-en="Book a bundle">Bundle buchen</h3>
        <p class="when" data-en="Recommended for a specific project">Empfohlen für ein konkretes Vorhaben</p>
        <p data-en="A coordinated complete set for your purpose, e.g. podcast recording, video shoot or VR workshop. Everything fits together, in a single booking.">Ein abgestimmtes Komplett-Set für Ihren Zweck, z. B. Podcast-Aufnahme, Videodreh oder VR-Workshop. Alles passt zusammen, in einer Buchung.</p>
        <ul>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> <span data-en="Devices that work together">Aufeinander abgestimmte Geräte</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> <span data-en="Nothing important forgotten">Nichts Wichtiges vergessen</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> <span data-en="Ready to go quickly">Schnell startklar</span></li>
        </ul>
        <a class="go" href="#sets">
          <span data-en="View bundles">Bundles ansehen</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </div>

      <div class="choice">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></div>
        <h3 data-en="Book individual devices">Geräte einzeln buchen</h3>
        <p class="when" data-en="When you know exactly what you need">Wenn Sie genau wissen, was Sie brauchen</p>
        <p data-en="Select individual devices: a specific camera, a wireless microphone, a VR headset. You immediately see what is available in your chosen period.">Wählen Sie gezielt einzelne Geräte: eine bestimmte Kamera, ein Funkmikrofon, eine VR-Brille. Sie sehen sofort, was im gewünschten Zeitraum frei ist.</p>
        <ul>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> <span data-en="Filter by category &amp; period">Nach Kategorie &amp; Zeitraum filtern</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> <span data-en="Availability at a glance">Verfügbarkeit auf einen Blick</span></li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> <span data-en="Full freedom of choice">Volle Freiheit bei der Auswahl</span></li>
        </ul>
        <a class="go" href="#kategorien">
          <span data-en="Browse devices">Geräte durchsuchen</span>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </div>
    </div>

    <div class="third">
      <span class="ic2"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg></span>
      <span class="grow" data-en="<b>Already booked?</b> <span class=&quot;muted&quot;>View your dates, manage pickup &amp; return.</span>"><b>Schon gebucht?</b> <span class="muted">Termine einsehen, Abholung &amp; Rückgabe verwalten.</span></span>
      <a class="go2" href="zhl-dashboard.php?view=meine">
        <span data-en="My bookings">Meine Buchungen</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
      </a>
    </div>
  </div>
</section>

<section class="block" id="sets" style="background:var(--bg-soft)">
  <div class="wrap">
    <div class="sec-head">
      <p class="eyebrow" data-en="Ready-made sets">Fertige Sets</p>
      <h2 data-en="Our bundles at a glance">Unsere Bundles im Überblick</h2>
      <p class="lead" data-en="Coordinated complete sets for typical projects — everything that belongs together, in a single booking. No account needed to browse.">Abgestimmte Komplett-Sets für typische Vorhaben — alles, was zusammengehört, in einer Buchung. Zum Stöbern ist kein Konto nötig.</p>
    </div>
    <?php if ($bundles): ?>
    <div class="sets-grid">
      <?php foreach ($bundles as $b): ?>
      <div class="set-card">
        <span class="badge <?= e($b['difficulty']) ?>" data-en="<?= e(diffLabelEn($b['difficulty'])) ?>"><?= e(diffLabel($b['difficulty'])) ?></span>
        <h3><?= e($b['name']) ?></h3>
        <?php if (!empty($b['use_case'])): ?><p class="uc"><?= e($b['use_case']) ?></p><?php endif; ?>
        <p class="contains" data-en="Includes">Enthält</p>
        <ul class="items">
          <?php foreach ($b['items'] as $it):
              $qty = (int)$it['quantity'];
              $isAccessory = ($qty === 0);
              $isOptional = ((int)$it['required'] === 0) && !$isAccessory;
              $muted = $isAccessory || $isOptional;
              $extra = trim((string)($it['note'] !== '' && $it['note'] !== null ? $it['note'] : ($it['meta'] ?? '')));
          ?>
          <li class="<?= $muted ? 'muted' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <span><?php if ($qty > 1): ?><span class="q"><?= $qty ?>×</span> <?php endif; ?><?= e($it['type_label']) ?><?php if ($isOptional): ?> <span class="q" data-en="(optional)">(optional)</span><?php endif; ?><?php if ($extra !== ''): ?> <span class="q">– <?= e($extra) ?></span><?php endif; ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php $lvl = (string)$b['einweisung_level']; if ($lvl !== '' && $lvl !== 'keine' && einwLabel($lvl) !== ''): ?>
        <span class="einw <?= e($lvl) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          <span data-en="<?= e(einwLabelEn($lvl)) ?>"><?= e(einwLabel($lvl)) ?></span>
        </span>
        <?php endif; ?>
        <?php if (!empty($b['hint'])): ?><p class="hint"><?= e($b['hint']) ?></p><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center; margin-top:28px">
      <a class="btn btn-primary" href="zhl-assistant.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/></svg>
        <span data-en="Configure &amp; book a set (sign-in required)">Set konfigurieren &amp; buchen (Anmeldung nötig)</span>
      </a>
    </div>
    <?php else: ?>
    <p class="lead" style="text-align:center" data-en="The bundle overview is currently unavailable.">Die Bundle-Übersicht ist gerade nicht verfügbar.</p>
    <?php endif; ?>
  </div>
</section>

<section class="block" id="ablauf">
  <div class="wrap">
    <div class="sec-head">
      <p class="eyebrow" data-en="In four steps">In vier Schritten</p>
      <h2 data-en="How it works">So funktioniert's</h2>
      <p class="lead" data-en="From reservation to return, all planned online.">Von der Reservierung bis zur Rückgabe, alles online geplant.</p>
    </div>
    <div class="steps">
      <div class="step">
        <div class="num">1</div>
        <div class="sic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></div>
        <h3 data-en="Sign in">Anmelden</h3>
        <p data-en="Log in with your University of Bayreuth account.">Mit Ihrer Uni-Bayreuth-Kennung einloggen.</p>
      </div>
      <div class="step">
        <div class="num">2</div>
        <div class="sic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
        <h3 data-en="Choose">Wählen</h3>
        <p data-en="Pick a bundle or a single device and a period.">Bundle oder Einzelgerät und Zeitraum aussuchen.</p>
      </div>
      <div class="step">
        <div class="num">3</div>
        <div class="sic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
        <h3 data-en="Reserve">Reservieren</h3>
        <p data-en="Confirm the booking, the pickup time is arranged.">Buchung bestätigen, Abholtermin wird abgestimmt.</p>
      </div>
      <div class="step">
        <div class="num">4</div>
        <div class="sic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></div>
        <h3 data-en="Pick up &amp; return">Abholen &amp; zurück</h3>
        <p data-en="Collect on site, return on time.">Vor Ort übernehmen, fristgerecht zurückbringen.</p>
      </div>
    </div>
  </div>
</section>

<section class="block stats">
  <div class="wrap">
    <div class="sec-head">
      <h2 data-en="The lending service at a glance">Die Ausleihe auf einen Blick</h2>
      <p data-en="What the ZHL has ready for your projects.">Was im ZHL für Ihre Projekte bereitsteht.</p>
    </div>
    <div class="stat-grid">
      <div class="stat">
        <div class="sic"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="14" height="12" rx="2"/><path d="M16 9l5-3v12l-5-3"/></svg></div>
        <div class="big"><?= num($stats['devices']) ?></div>
        <div class="lab" data-en="Devices to borrow">Geräte zum Ausleihen</div>
      </div>
      <div class="stat">
        <div class="sic"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
        <div class="big"><?= num($stats['loans']) ?></div>
        <div class="lab" data-en="Bookings so far">Ausleihen bisher</div>
      </div>
      <div class="stat">
        <div class="sic"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        <div class="big"><?= num($stats['users']) ?></div>
        <div class="lab" data-en="Active users">Aktive Nutzende</div>
      </div>
      <div class="stat">
        <div class="sic"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg></div>
        <div class="big" data-en="1 week">1 Woche</div>
        <div class="lab" data-en="Plan lead time">Planungs-Vorlauf einplanen</div>
      </div>
    </div>
  </div>
</section>

<section class="block" id="kategorien">
  <div class="wrap">
    <div class="sec-head">
      <p class="eyebrow" data-en="Equipment">Ausstattung</p>
      <h2 data-en="All devices for individual booking">Alle Geräte einzeln buchbar</h2>
      <p class="lead" data-en="Every device type the ZHL lends — always up to date, straight from our inventory. No account needed to browse.">Alle Geräte-Typen, die das ZHL verleiht — immer aktuell, direkt aus unserem Bestand. Zum Stöbern ist kein Konto nötig.</p>
    </div>
    <?php if ($mediaTypes): ?>
    <?php if ($categories): ?>
    <div class="cat-filter">
      <button type="button" class="cf active" data-filter="all"><span data-en="All">Alle</span><span class="cf-c"><?= (int)$totalDevices ?></span></button>
      <?php foreach ($categories as $c): ?>
      <button type="button" class="cf" data-filter="<?= e($c['key']) ?>"><span data-en="<?= e($c['label_en']) ?>"><?= e($c['label']) ?></span><span class="cf-c"><?= (int)$c['count'] ?></span></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <p class="cat-hint" data-en="Click a device to book it directly (sign-in required). Where a guide exists, you can open it right here.">Klicken Sie ein Gerät an, um es direkt zu buchen (Anmeldung nötig). Wo es eine Anleitung gibt, können Sie sie gleich hier öffnen.</p>
    <div class="types-grid">
      <?php foreach ($mediaTypes as $t): ?>
      <div class="type-card" data-cat="<?= e($t['cat']) ?>">
        <div class="th">
          <span class="ti"><?= typeIcon($t['typ']) ?></span>
          <h3><?= e($t['typ']) ?></h3>
          <span class="cnt"><?= (int)$t['count'] ?>&nbsp;<span data-en="<?= $t['count'] === 1 ? 'item' : 'items' ?>"><?= $t['count'] === 1 ? 'Gerät' : 'Stück' ?></span></span>
        </div>
        <div class="models">
          <?php foreach ($t['models'] as $model => $m): ?>
          <a class="model-link" href="zhl-book.php?rid=<?= (int)$m['rid'] ?>&amp;sid=<?= (int)$m['sid'] ?>&amp;rd=<?= e($bookDate) ?>" title="Jetzt buchen / Book now">
            <span><?= e($model) ?></span><?php if ($m['count'] > 1): ?> <span class="q">×<?= (int)$m['count'] ?></span><?php endif; ?>
            <svg class="arr" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </a>
          <?php endforeach; ?>
        </div>
        <?php if (isset($typeInfo[$t['typ']])): ?>
        <a class="tut-link" href="<?= e($typeInfo[$t['typ']]) ?>" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
          <span data-en="View guide">Anleitung ansehen</span>
        </a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="lead" style="text-align:center" data-en="The device overview is currently unavailable.">Die Geräte-Übersicht ist gerade nicht verfügbar.</p>
    <?php endif; ?>
  </div>
</section>

<section class="block cta">
  <div class="wrap">
    <h2 data-en="Ready for your next project?">Bereit für Ihr nächstes Projekt?</h2>
    <p data-en="Sign in with your University of Bayreuth account and reserve in minutes.">Melden Sie sich mit Ihrer Uni-Bayreuth-Kennung an und reservieren Sie in wenigen Minuten.</p>
    <a class="btn btn-light" href="zhl-login.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
      <span data-en="Sign in &amp; book">Anmelden &amp; buchen</span>
    </a>
  </div>
</section>

<footer>
  <div class="wrap foot-grid">
    <div class="foot-brand">
      <img class="zhl" src="img/ZHL-Logo-Text_Side-Green-Background.png" alt="Zentrum für Hochschullehre">
      <p data-en="Media lending by the Centre for University Teaching at the University of Bayreuth.">Medienausleihe des Zentrums für Hochschullehre an der Universität Bayreuth.</p>
      <span class="foot-uni"><img src="img/Uni_Bayreuth_Logo_XL.JPG" alt="Universität Bayreuth"></span>
    </div>
    <div>
      <h4 data-en="Quick links">Schnellzugriff</h4>
      <div class="foot-links">
        <a href="zhl-assistant.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg> <span data-en="Book bundles">Bundles buchen</span></a>
        <a href="zhl-dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg> <span data-en="Individual devices">Geräte einzeln</span></a>
        <a href="zhl-dashboard.php?view=meine"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <span data-en="My bookings">Meine Buchungen</span></a>
        <a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg> <span data-en="Video studio">Videostudio</span></a>
      </div>
    </div>
    <div>
      <h4 data-en="Help">Hilfe</h4>
      <div class="foot-links">
        <a href="#ablauf"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> <span data-en="How it works">So funktioniert's</span></a>
        <a href="register.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg> <span data-en="Create account">Konto anlegen</span></a>
        <a href="zhl-login.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg> <span data-en="Sign in">Anmelden</span></a>
      </div>
    </div>
    <div>
      <h4 data-en="Contact">Kontakt</h4>
      <div class="foot-contact">
        <div class="row">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <a href="mailto:zhlmedien@uni-bayreuth.de">zhlmedien@uni-bayreuth.de</a>
        </div>
        <div class="row">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          <span>Bürocenter Bayreuth Süd<br>Haus 4, Raum 4.2.38<br>Nürnberger Str. 38, 95447 Bayreuth</span>
        </div>
      </div>
    </div>
  </div>
  <div class="foot-bottom">© 2026 · Zentrum für Hochschullehre · Universität Bayreuth<br>
    <a href="https://www.uni-bayreuth.de/impressum" target="_blank" rel="noopener" data-en="Legal notice">Impressum</a> · <a href="https://www.zhl.uni-bayreuth.de/de/_service/datenschutzerklaerung/index.html" target="_blank" rel="noopener" data-en="Privacy policy">Datenschutz</a>
  </div>
</footer>

<script>
  var TITLE = { de: "Medienausleihe ZHL", en: "Media Lending ZHL" };
  var cur = "de";
  function applyLang(l){
    document.documentElement.lang = l;
    document.title = TITLE[l];
    document.querySelectorAll("[data-en]").forEach(function(el){
      if (el.dataset.de === undefined) el.dataset.de = el.innerHTML;
      el.innerHTML = (l === "en") ? el.dataset.en : el.dataset.de;
    });
    document.querySelectorAll("[data-lang-btn]").forEach(function(b){ b.textContent = (l === "en") ? "DE" : "EN"; });
    try { localStorage.setItem("zhlLang", l); } catch(e){}
    cur = l;
  }
  function toggleLang(){ applyLang(cur === "de" ? "en" : "de"); }
  (function(){ try { var s = localStorage.getItem("zhlLang"); if (s === "en") applyLang("en"); } catch(e){} })();

  // Kategorie-Filter für "Alle Geräte einzeln buchbar" — rein clientseitig, kein Reload.
  (function(){
    var btns = document.querySelectorAll(".cf");
    if (!btns.length) return;
    var cards = document.querySelectorAll("#kategorien .type-card");
    btns.forEach(function(b){
      b.addEventListener("click", function(){
        var f = b.getAttribute("data-filter");
        btns.forEach(function(x){ x.classList.toggle("active", x === b); });
        cards.forEach(function(c){
          c.style.display = (f === "all" || c.getAttribute("data-cat") === f) ? "" : "none";
        });
      });
    });
  })();
</script>
<!-- ZHL: "Seite in Entwicklung"-Badge + Problem-Melder (Übergangszeit) -->
<script src="scripts/zhl-feedback.js" data-endpoint="zhl-feedback-submit.php"></script>
</body>
</html>

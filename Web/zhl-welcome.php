<?php
/**
 * ZHL: Öffentliche Landing-Page (Prototyp).
 *
 * Einfacher, einladender Einstieg statt direkt der komplexen Reservierungsmatrix.
 * Zeigt die Ausleih-Kategorien als Kacheln und führt per CTA zu Login/Registrierung.
 * Bewusst eigenständig gehalten (eindeutig ZHL-eigene Datei) -> upgrade-sicher.
 */

declare(strict_types=1);

$conf = require __DIR__ . '/../config/config.php';
$db = $conf['settings']['database'] ?? [];
$appTitle = $conf['settings']['app.title'] ?? 'Medienausleihe ZHL';

// Kategorien (Schedules) + Ressourcenanzahl direkt lesen (nur Anzeige).
$categories = [];
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['hostspec'] ?? '127.0.0.1', $db['name'] ?? ''),
        $db['user'] ?? '',
        $db['password'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $sql = 'SELECT s.name, COUNT(r.resource_id) AS n
            FROM schedules s
            LEFT JOIN resources r ON r.schedule_id = s.schedule_id
            GROUP BY s.schedule_id
            ORDER BY n DESC';
    $categories = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Im Prototyp still: ohne DB einfach keine Kacheln.
    $categories = [];
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Kachel-Icons je Kategorie (Stichwort-Heuristik, rein kosmetisch).
// Minimalistische Lucide-Stil Linien-Icons statt Emojis.
function tile_icon(string $name): string {
    $n = mb_strtolower($name);
    $svg = static fn(string $p): string =>
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
    if (str_contains($n, 'vr') || str_contains($n, 'immersiv') || str_contains($n, '360'))
        return $svg('<circle cx="6" cy="15" r="4"/><circle cx="18" cy="15" r="4"/><path d="M14 15a2 2 0 0 0-2-2 2 2 0 0 0-2 2"/><path d="M2.5 13 5 7c.7-1.3 1.4-2 3-2"/><path d="M21.5 13 19 7c-.7-1.3-1.5-2-3-2"/>');
    if (str_contains($n, 'drohne'))
        return $svg('<rect x="9" y="9" width="6" height="6" rx="1"/><circle cx="5" cy="5" r="2.5"/><circle cx="19" cy="5" r="2.5"/><circle cx="5" cy="19" r="2.5"/><circle cx="19" cy="19" r="2.5"/><line x1="9" y1="9" x2="6.8" y2="6.8"/><line x1="15" y1="9" x2="17.2" y2="6.8"/><line x1="9" y1="15" x2="6.8" y2="17.2"/><line x1="15" y1="15" x2="17.2" y2="17.2"/>');
    if (str_contains($n, 'video') || str_contains($n, 'studio'))
        return $svg('<path d="m22 8-6 4 6 4V8z"/><rect x="2" y="6" width="14" height="12" rx="2"/>');
    if (str_contains($n, 'moderation'))
        return $svg('<path d="M2 3h20"/><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"/><path d="m7 21 5-5 5 5"/>');
    if (str_contains($n, 'technik') || str_contains($n, 'verleih'))
        return $svg('<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>');
    return $svg('<path d="M16.5 9.4 7.5 4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($appTitle) ?> — Medienausleihe ZHL</title>
<style>
  :root { --green:#009260; --green-dark:#00744c; --ink:#1c2b24; --muted:#5b6b63; --bg:#f4f7f5; }
  * { box-sizing:border-box; }
  body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
         color:var(--ink); background:var(--bg); line-height:1.5; }
  a { color:inherit; }
  .topbar { display:flex; align-items:center; justify-content:space-between; padding:18px 24px;
            background:#fff; border-bottom:1px solid #e6ece9; }
  .brand { font-weight:700; font-size:18px; color:var(--green-dark); letter-spacing:.2px; }
  .brand span { color:var(--muted); font-weight:500; }
  .btn { display:inline-block; padding:10px 18px; border-radius:8px; text-decoration:none; font-weight:600;
         font-size:15px; transition:transform .05s ease, background .15s ease; }
  .btn:active { transform:translateY(1px); }
  .btn-primary { background:var(--green); color:#fff; }
  .btn-primary:hover { background:var(--green-dark); }
  .btn-ghost { background:transparent; color:var(--green-dark); border:1.5px solid var(--green); }
  .hero { max-width:980px; margin:0 auto; padding:72px 24px 40px; text-align:center; }
  .hero h1 { font-size:clamp(30px,5vw,46px); margin:0 0 14px; letter-spacing:-.5px; }
  .hero p { font-size:clamp(16px,2.4vw,20px); color:var(--muted); margin:0 auto 28px; max-width:620px; }
  .cta-row { display:flex; gap:14px; justify-content:center; flex-wrap:wrap; }
  .section { max-width:980px; margin:0 auto; padding:24px; }
  .section h2 { font-size:22px; margin:0 0 18px; }
  .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:16px; }
  .tile { background:#fff; border:1px solid #e6ece9; border-radius:14px; padding:22px;
          display:flex; flex-direction:column; gap:8px; transition:box-shadow .15s ease, transform .1s ease; }
  .tile:hover { box-shadow:0 8px 24px rgba(0,116,76,.10); transform:translateY(-2px); }
  .tile .ic { color:var(--green); }
  .tile .ic svg { width:30px; height:30px; }
  .tile .nm { font-weight:650; font-size:17px; }
  .tile .ct { color:var(--muted); font-size:14px; }
  .how { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; }
  .step { background:#fff; border-radius:14px; border:1px solid #e6ece9; padding:20px; }
  .step .num { width:30px; height:30px; border-radius:50%; background:var(--green); color:#fff;
               display:flex; align-items:center; justify-content:center; font-weight:700; margin-bottom:10px; }
  footer { text-align:center; color:var(--muted); font-size:14px; padding:40px 24px; }
</style>
</head>
<body>
  <div class="topbar">
    <div class="brand">ZHL <span>· Medienausleihe Uni Bayreuth</span></div>
    <div>
      <a class="btn btn-ghost" href="register.php">Registrieren</a>
      <a class="btn btn-primary" href="index.php">Anmelden</a>
    </div>
  </div>

  <section class="hero">
    <h1>Medientechnik einfach ausleihen</h1>
    <p>Kameras, VR-Brillen, Drohne, Videostudio und mehr — online reservieren,
       vor Ort abholen. Schnell, planbar, ohne Papierkram.</p>
    <div class="cta-row">
      <a class="btn btn-primary" href="index.php">Jetzt anmelden &amp; buchen</a>
      <a class="btn btn-ghost" href="register.php">Neues Konto anlegen</a>
    </div>
  </section>

  <?php if ($categories): ?>
  <section class="section">
    <h2>Was kannst du ausleihen?</h2>
    <div class="grid">
      <?php foreach ($categories as $c): ?>
        <a class="tile" href="index.php" style="text-decoration:none">
          <div class="ic"><?= tile_icon($c['name']) ?></div>
          <div class="nm"><?= e($c['name']) ?></div>
          <div class="ct"><?= (int)$c['n'] ?> Geräte verfügbar</div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="section">
    <h2>So funktioniert's</h2>
    <div class="how">
      <div class="step"><div class="num">1</div><strong>Anmelden</strong><div class="ct">Mit deiner Uni-Bayreuth-Kennung registrieren.</div></div>
      <div class="step"><div class="num">2</div><strong>Gerät wählen</strong><div class="ct">Kategorie und Zeitraum aussuchen, Verfügbarkeit sehen.</div></div>
      <div class="step"><div class="num">3</div><strong>Reservieren</strong><div class="ct">Buchung bestätigen — Abholung wird abgestimmt.</div></div>
      <div class="step"><div class="num">4</div><strong>Abholen &amp; zurückgeben</strong><div class="ct">Vor Ort übernehmen, fristgerecht zurückbringen.</div></div>
    </div>
  </section>

  <footer>
    ZHL — Zentrum für Hochschullehre, Universität Bayreuth ·
    <a href="index.php">Zum Buchungssystem</a><br>
    <a href="https://www.uni-bayreuth.de/impressum" target="_blank" rel="noopener" data-en="Legal notice">Impressum</a> ·
    <a href="https://www.zhl.uni-bayreuth.de/de/_service/datenschutzerklaerung/index.html" target="_blank" rel="noopener" data-en="Privacy policy">Datenschutz</a>
  </footer>
</body>
</html>

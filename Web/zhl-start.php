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
  .foot-bottom { border-top:1px solid rgba(255,255,255,.08); padding:20px 0; text-align:center; font-size:13px; color:#7a8a83; }
</style>
</head>
<body>

<header>
  <div class="wrap nav">
    <a class="logo" href="#top" aria-label="ZHL"><img src="img/ZHL-Logo-Text_Side-Green-Background.png" alt="Zentrum für Hochschullehre"></a>
    <nav class="nav-links">
      <a href="#top" class="active" data-en="Home">Start</a>
      <a href="#buchen" data-en="Book">Buchen</a>
      <a href="#ablauf" data-en="How it works">Ablauf</a>
      <a href="#kategorien" data-en="Equipment">Ausstattung</a>
      <a href="#" data-en="Video studio">Videostudio</a>
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
        <a class="go" href="zhl-book.php">
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
        <a class="go" href="zhl-dashboard.php">
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
      <h2 data-en="What can you borrow?">Was können Sie ausleihen?</h2>
      <p class="lead" data-en="From wireless mics to VR headsets, tailored to teaching and research.">Vom Funkmikrofon bis zur VR-Brille, abgestimmt auf Lehre und Forschung.</p>
    </div>
    <div class="cats">
      <div class="cat">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12a10 10 0 0 0 20 0 10 10 0 0 0-20 0z"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/></svg></div>
        <h3 data-en="Immersive media">Immersive Medien</h3>
        <p data-en="VR &amp; AR headsets, 360° cameras for immersive learning scenarios.">VR- &amp; AR-Brillen, 360-Grad-Kameras für immersive Lernszenarien.</p>
        <span class="count" data-en="20 devices">20 Geräte</span>
      </div>
      <div class="cat">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="14" height="12" rx="2"/><path d="M16 9l5-3v12l-5-3"/></svg></div>
        <h3 data-en="Media equipment">Medientechnik-Verleih</h3>
        <p data-en="Cameras, lenses, microphones, gimbals, editing PCs and more.">Kameras, Objektive, Mikrofone, Gimbals, Schnitt-PCs und mehr.</p>
        <span class="count" data-en="26 devices">26 Geräte</span>
      </div>
      <div class="cat">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h18v12H3z"/><line x1="12" y1="16" x2="12" y2="21"/><line x1="8" y1="21" x2="16" y2="21"/></svg></div>
        <h3 data-en="Facilitation materials">Analoge Moderation</h3>
        <p data-en="Facilitation materials and tools for workshops and seminars.">Moderationsmaterial und Werkzeuge für Workshops und Seminare.</p>
        <span class="count" data-en="7 devices">7 Geräte</span>
      </div>
      <div class="cat">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="18" r="3"/><path d="M8.5 8.5l7 7M15.5 8.5l-7 7"/></svg></div>
        <h3 data-en="Drone">Drohne</h3>
        <p data-en="Aerial footage for research and teaching videos.">Luftaufnahmen für Forschung und Lehrvideos.</p>
        <span class="count" data-en="1 device">1 Gerät</span>
      </div>
      <div class="cat">
        <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg></div>
        <h3 data-en="Video studio">Videostudio</h3>
        <p data-en="Greenscreen, teleprompter and livestreaming, set up on site.">Greenscreen, Teleprompter und Livestreaming, fertig eingerichtet vor Ort.</p>
        <span class="count" data-en="1 studio">1 Studio</span>
      </div>
    </div>
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
        <a href="zhl-book.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg> <span data-en="Book bundles">Bundles buchen</span></a>
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
  <div class="foot-bottom">© 2026 · Zentrum für Hochschullehre · Universität Bayreuth</div>
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
</script>
</body>
</html>

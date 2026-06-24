<?php
/**
 * ZHL: Ausleih-Detail — eine einzelne Buchung (Prototyp, read-only).
 * Geteilte Hülle; Daten noch Platzhalter (später aus reservation_series + _resources).
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/zhl-nav.php';
require_once __DIR__ . '/includes/zhl-footer.php';

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
$id = isset($_GET['id']) ? preg_replace('/[^A-Za-z0-9\-]/', '', (string)$_GET['id']) : 'A-2041';

// --- Platzhalter ---
$devices = [
    ['n' => 'Rode Wireless GO II', 't' => 'Funkmikrofon', 't_en' => 'Wireless mic', 's' => 'ok', 'sl' => 'Eingeplant', 'sl_en' => 'Reserved'],
    ['n' => 'Shure SM7B', 't' => 'Podcast-Mikrofon', 't_en' => 'Podcast mic', 's' => 'ok', 'sl' => 'Eingeplant', 'sl_en' => 'Reserved'],
    ['n' => 'Zoom PodTrak P4', 't' => 'Audio-Interface', 't_en' => 'Audio interface', 's' => 'ok', 'sl' => 'Eingeplant', 'sl_en' => 'Reserved'],
    ['n' => 'Elgato Key Light', 't' => 'Beleuchtung', 't_en' => 'Lighting', 's' => 'ok', 'sl' => 'Eingeplant', 'sl_en' => 'Reserved'],
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ausleihe <?= e($id) ?> – Medienausleihe ZHL</title>
<link rel="stylesheet" href="css/zhl-landing.css">
<script>window.ZHL_TITLE = { de: "Ausleihe <?= e($id) ?> – Medienausleihe ZHL", en: "Booking <?= e($id) ?> – Media Lending ZHL" };</script>
</head>
<body>
<?php zhl_nav('bookings', true); ?>

<main class="app-main">
  <div class="wrap-app">

    <div class="crumb">
      <a href="zhl-bookings.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg> <span data-en="My bookings">Meine Buchungen</span></a>
    </div>

    <div class="page-head">
      <span class="ph-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></span>
      <div class="ph-text">
        <h1>Podcast-Set</h1>
        <div class="ph-sub"><span data-en="Booking">Ausleihe</span> <?= e($id) ?> · <span data-en="Bundle of 6 devices">Bundle aus 6 Geräten</span></div>
      </div>
      <div class="ph-actions">
        <a class="btn btn-light" href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <span data-en="Add to calendar">Zum Kalender</span></a>
        <a class="btn btn-danger" href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> <span data-en="Cancel">Stornieren</span></a>
      </div>
    </div>

    <div class="grid-2">
      <!-- Details -->
      <div class="card">
        <div class="card-head">
          <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
          <h2 data-en="Details">Details</h2>
        </div>
        <div class="card-body">
          <dl class="dl">
            <div><dt data-en="Status">Status</dt><dd><span class="badge badge-ok" data-en="Confirmed">Bestätigt</span></dd></div>
            <div><dt data-en="Period">Zeitraum</dt><dd>24.06. – 26.06.2026</dd></div>
            <div><dt data-en="Type">Art</dt><dd data-en="Bundle (6 devices)">Bundle (6 Geräte)</dd></div>
            <div><dt data-en="Booking number">Buchungsnummer</dt><dd><?= e($id) ?></dd></div>
            <div class="last"><dt data-en="Booked on">Gebucht am</dt><dd>17.06.2026</dd></div>
          </dl>
        </div>
      </div>

      <!-- Abholung & Rückgabe -->
      <div class="card">
        <div class="card-head">
          <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
          <h2 data-en="Pickup &amp; return">Abholung &amp; Rückgabe</h2>
        </div>
        <div class="card-body">
          <dl class="dl">
            <div><dt data-en="Pickup">Abholung</dt><dd>25.06.2026, 10:00</dd></div>
            <div><dt data-en="Return by">Rückgabe bis</dt><dd>26.06.2026, 16:00</dd></div>
            <div><dt data-en="Location">Ort</dt><dd>ZHL, Raum 4.2.38</dd></div>
            <div class="last"><dt data-en="Contact">Ansprechpartner</dt><dd>Paul Dölle</dd></div>
          </dl>
          <div class="info-box" style="margin-top:16px">
            <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg></span>
            <span data-en="Please bring your university ID for pickup.">Bitte bringen Sie zur Abholung Ihren Uni-Ausweis mit.</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Enthaltene Geräte -->
    <div class="card">
      <div class="card-head">
        <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
        <h2 data-en="Included devices">Enthaltene Geräte</h2>
        <span class="ch-right muted"><?= count($devices) ?> <span data-en="of 6">von 6</span></span>
      </div>
      <table class="ztable">
        <thead><tr><th data-en="Device">Gerät</th><th data-en="Type">Typ</th><th data-en="Status">Status</th></tr></thead>
        <tbody>
        <?php foreach ($devices as $d): ?>
          <tr>
            <td style="color:var(--text);font-weight:500"><?= e($d['n']) ?></td>
            <td data-en="<?= e($d['t_en']) ?>"><?= e($d['t']) ?></td>
            <td><span class="badge badge-<?= e($d['s']) ?>" data-en="<?= e($d['sl_en']) ?>"><?= e($d['sl']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>
</main>

<?php zhl_footer(); ?>
<script src="scripts/zhl-landing.js"></script>
</body>
</html>

<?php
/**
 * ZHL: "Meine Buchungen" — Übersicht der eigenen Ausleihen (Prototyp).
 * Nutzt die geteilte Hülle (zhl-nav/zhl-footer/zhl-landing.css).
 * Daten hier noch Platzhalter; später aus reservation_series des Nutzers.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/zhl-nav.php';
require_once __DIR__ . '/includes/zhl-footer.php';

// --- Platzhalterdaten (später: eigene Reservierungen aus der DB) ---
$bookings = [
    ['id' => 'A-2041', 'kind' => 'bundle', 'title' => 'Podcast-Set', 'title_en' => 'Podcast set',
     'range' => '24.06. – 26.06.2026', 'items' => '6 Geräte', 'items_en' => '6 devices',
     'pickup' => 'Abholung morgen, 10:00', 'pickup_en' => 'Pickup tomorrow, 10:00',
     'state' => 'ok', 'label' => 'Bestätigt', 'label_en' => 'Confirmed', 'group' => 'upcoming'],
    ['id' => 'A-2033', 'kind' => 'device', 'title' => 'Blackmagic Pocket Cam 6K Pro', 'title_en' => 'Blackmagic Pocket Cam 6K Pro',
     'range' => '18.06. – 20.06.2026', 'items' => 'Einzelgerät', 'items_en' => 'Single device',
     'pickup' => 'Aktuell ausgeliehen', 'pickup_en' => 'Currently on loan',
     'state' => 'warn', 'label' => 'Läuft', 'label_en' => 'Active', 'group' => 'current'],
    ['id' => 'A-1998', 'kind' => 'bundle', 'title' => 'VR-Workshop-Set', 'title_en' => 'VR workshop set',
     'range' => '02.06. – 04.06.2026', 'items' => '12 Geräte', 'items_en' => '12 devices',
     'pickup' => 'Zurückgegeben am 04.06.', 'pickup_en' => 'Returned on 04.06.',
     'state' => 'muted', 'label' => 'Abgeschlossen', 'label_en' => 'Completed', 'group' => 'past'],
    ['id' => 'A-1974', 'kind' => 'device', 'title' => 'Funkmikrofon-Strecke', 'title_en' => 'Wireless mic kit',
     'range' => '20.05. – 21.05.2026', 'items' => 'Einzelgerät', 'items_en' => 'Single device',
     'pickup' => 'Zurückgegeben am 21.05.', 'pickup_en' => 'Returned on 21.05.',
     'state' => 'muted', 'label' => 'Abgeschlossen', 'label_en' => 'Completed', 'group' => 'past'],
];
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
$icons = [
    'bundle' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
    'device' => '<rect x="2" y="6" width="14" height="12" rx="2"/><path d="M16 9l5-3v12l-5-3"/>',
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Meine Buchungen – Medienausleihe ZHL</title>
<link rel="stylesheet" href="css/zhl-landing.css">
<script>window.ZHL_TITLE = { de: "Meine Buchungen – Medienausleihe ZHL", en: "My bookings – Media Lending ZHL" };</script>
</head>
<body>
<?php zhl_nav('bookings', true); ?>

<main class="app-main">
  <div class="wrap-app">

    <div class="page-head">
      <span class="ph-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
      <div class="ph-text">
        <h1 data-en="My bookings">Meine Buchungen</h1>
        <div class="ph-sub" data-en="Your reservations, pickups and returns at a glance.">Ihre Reservierungen, Abholungen und Rückgaben auf einen Blick.</div>
      </div>
      <div class="ph-actions">
        <a class="btn btn-light" href="zhl-dashboard.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          <span data-en="New booking">Neu buchen</span>
        </a>
      </div>
    </div>

    <div class="seg" role="tablist">
      <button class="active" data-en="Current">Aktuell <span class="cnt">1</span></button>
      <button data-en="Upcoming">Anstehend <span class="cnt">1</span></button>
      <button data-en="Past">Vergangen <span class="cnt">2</span></button>
    </div>

    <?php foreach ($bookings as $bk): ?>
      <div class="booking">
        <span class="b-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $icons[$bk['kind']] ?></svg></span>
        <div class="b-main">
          <div class="b-title"><?= e($bk['title']) ?></div>
          <div class="b-meta">
            <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><?= e($bk['range']) ?></span>
            <span data-en="<?= e($bk['items_en']) ?>"><?= e($bk['items']) ?></span>
            <span data-en="<?= e($bk['pickup_en']) ?>"><?= e($bk['pickup']) ?></span>
          </div>
        </div>
        <div class="b-right">
          <span class="badge badge-<?= e($bk['state']) ?>" data-en="<?= e($bk['label_en']) ?>"><?= e($bk['label']) ?></span>
          <a class="b-link" href="zhl-booking-detail.php?id=<?= e($bk['id']) ?>">
            <span data-en="Details">Details</span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </a>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="info-box" style="margin-top:18px">
      <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
      <span data-en="Need to cancel or change a booking? Open the booking and use <b>Cancel</b>, or contact us at least one day before pickup.">Eine Buchung stornieren oder ändern? Öffnen Sie die Buchung und nutzen Sie <b>Stornieren</b>, oder melden Sie sich spätestens einen Tag vor Abholung bei uns.</span>
    </div>

  </div>
</main>

<?php zhl_footer(); ?>
<script src="scripts/zhl-landing.js"></script>
</body>
</html>

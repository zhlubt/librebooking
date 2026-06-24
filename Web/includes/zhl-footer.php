<?php
/**
 * ZHL: Geteilter dunkler Footer (Studio-Pendant: public-footer.php).
 * zhl_footer($base) — $base = Pfad-Präfix zu Web/ (z. B. '../').
 */

if (!function_exists('zhl_footer')) {
    function zhl_footer(string $base = ''): void
    {
        $b = $base;
        ?>
<footer class="zhl-foot">
  <div class="wrap foot-grid">
    <div class="foot-brand">
      <img class="zhl" src="<?= $b ?>img/ZHL-Logo-Text_Side-Green-Background.png" alt="Zentrum für Hochschullehre">
      <p data-en="Media lending by the Centre for University Teaching at the University of Bayreuth.">Medienausleihe des Zentrums für Hochschullehre an der Universität Bayreuth.</p>
      <span class="foot-uni"><img src="<?= $b ?>img/Uni_Bayreuth_Logo_XL.JPG" alt="Universität Bayreuth"></span>
    </div>
    <div>
      <h4 data-en="Quick links">Schnellzugriff</h4>
      <div class="foot-links">
        <a href="<?= $b ?>zhl-book.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg> <span data-en="Book bundles">Bundles buchen</span></a>
        <a href="<?= $b ?>zhl-dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg> <span data-en="Individual devices">Geräte einzeln</span></a>
        <a href="<?= $b ?>zhl-bookings.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <span data-en="My bookings">Meine Buchungen</span></a>
        <a href="<?= $b ?>zhl-account.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <span data-en="Account">Konto</span></a>
      </div>
    </div>
    <div>
      <h4 data-en="Help">Hilfe</h4>
      <div class="foot-links">
        <a href="<?= $b ?>zhl-start.php#ablauf"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> <span data-en="How it works">So funktioniert's</span></a>
        <a href="<?= $b ?>register.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg> <span data-en="Create account">Konto anlegen</span></a>
        <a href="mailto:zhlmedien@uni-bayreuth.de"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> <span data-en="Contact support">Support kontaktieren</span></a>
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
<?php
    }
}

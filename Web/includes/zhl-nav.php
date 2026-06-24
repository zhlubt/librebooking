<?php
/**
 * ZHL: Geteilte grüne Sticky-Navigation (Studio-Pendant: public-nav.php).
 *
 * zhl_nav($active, $loggedIn, $base)
 *   $active   = aktiver Schlüssel: home|dashboard|bundles|bookings|account
 *   $loggedIn = true → App-Nav (Geräte/Bundles/Meine Buchungen/Konto/Abmelden)
 *               false → öffentliche Nav (Start/Buchen/Ablauf/Ausstattung/Anmelden)
 *   $base     = Pfad-Präfix zu Web/ (z. B. '../' wenn aus Unterordner aufgerufen)
 *
 * Zweisprachig über data-en + scripts/zhl-landing.js (gemeinsamer Toggle).
 */

if (!function_exists('zhl_nav')) {
    function zhl_nav(string $active = '', bool $loggedIn = true, string $base = ''): void
    {
        $b = $base;
        $a = static fn(string $k): string => $active === $k ? ' class="active"' : '';
        ?>
<header class="zhl-head">
  <div class="wrap nav">
    <a class="logo" href="<?= $b ?><?= $loggedIn ? 'zhl-dashboard.php' : 'zhl-start.php' ?>" aria-label="ZHL">
      <img src="<?= $b ?>img/ZHL-Logo-Text_Side-Green-Background.png" alt="Zentrum für Hochschullehre">
    </a>
    <?php if ($loggedIn): ?>
      <nav class="nav-links">
        <a href="<?= $b ?>zhl-dashboard.php"<?= $a('dashboard') ?> data-en="Devices">Geräte</a>
        <a href="<?= $b ?>zhl-book.php"<?= $a('bundles') ?> data-en="Bundles">Bundles</a>
        <a href="<?= $b ?>zhl-bookings.php"<?= $a('bookings') ?> data-en="My bookings">Meine Buchungen</a>
        <a href="<?= $b ?>zhl-account.php"<?= $a('account') ?> data-en="Account">Konto</a>
      </nav>
      <div class="nav-right">
        <a class="nav-ghost" href="<?= $b ?>logout.php" data-en="Sign out">Abmelden</a>
        <button class="lang-btn" data-lang-btn onclick="toggleLang()">EN</button>
      </div>
    <?php else: ?>
      <nav class="nav-links">
        <a href="<?= $b ?>zhl-start.php"<?= $a('home') ?> data-en="Home">Start</a>
        <a href="<?= $b ?>zhl-start.php#buchen" data-en="Book">Buchen</a>
        <a href="<?= $b ?>zhl-start.php#ablauf" data-en="How it works">Ablauf</a>
        <a href="<?= $b ?>zhl-start.php#kategorien" data-en="Equipment">Ausstattung</a>
      </nav>
      <div class="nav-right">
        <a class="nav-cta" href="<?= $b ?>zhl-login.php" data-en="Sign in">Anmelden</a>
        <button class="lang-btn" data-lang-btn onclick="toggleLang()">EN</button>
      </div>
    <?php endif; ?>
  </div>
</header>
<?php
    }
}

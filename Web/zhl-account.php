<?php
/**
 * ZHL: "Mein Konto" — eigene Kontodaten (Prototyp).
 * Geteilte Hülle; Felder noch Platzhalter (später aus dem Nutzerprofil).
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/zhl-nav.php';
require_once __DIR__ . '/includes/zhl-footer.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mein Konto – Medienausleihe ZHL</title>
<link rel="stylesheet" href="css/zhl-landing.css">
<script>window.ZHL_TITLE = { de: "Mein Konto – Medienausleihe ZHL", en: "My account – Media Lending ZHL" };</script>
</head>
<body>
<?php zhl_nav('account', true); ?>

<main class="app-main">
  <div class="wrap-app">

    <div class="page-head">
      <span class="ph-icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
      <div class="ph-text">
        <h1 data-en="My account">Mein Konto</h1>
        <div class="ph-sub" data-en="Manage your contact details and password.">Verwalten Sie Ihre Kontaktdaten und Ihr Passwort.</div>
      </div>
    </div>

    <!-- Persönliche Daten -->
    <form class="card" onsubmit="return false">
      <div class="card-head">
        <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
        <h2 data-en="Personal details">Persönliche Daten</h2>
      </div>
      <div class="card-body">
        <div class="field-row">
          <div class="field"><label data-en="First name">Vorname</label><input type="text" value="Maria"></div>
          <div class="field"><label data-en="Last name">Nachname</label><input type="text" value="Beispiel"></div>
        </div>
        <div class="field">
          <label data-en="University of Bayreuth email">Uni-Bayreuth-Mailadresse</label>
          <input type="email" value="maria.beispiel@uni-bayreuth.de" readonly>
          <div class="hint" data-en="Your email is your login and cannot be changed here.">Ihre E-Mail ist Ihr Login und kann hier nicht geändert werden.</div>
        </div>
        <div class="field-row">
          <div class="field"><label data-en="Phone (optional)">Telefon (optional)</label><input type="text" value="" placeholder="0921 …"></div>
          <div class="field"><label data-en="Department / chair">Einrichtung / Lehrstuhl</label><input type="text" value="" placeholder="z. B. Lehrstuhl für …"></div>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> <span data-en="Save changes">Änderungen speichern</span></button>
        </div>
      </div>
    </form>

    <div class="grid-2">
      <!-- Passwort -->
      <form class="card" onsubmit="return false">
        <div class="card-head">
          <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
          <h2 data-en="Change password">Passwort ändern</h2>
        </div>
        <div class="card-body">
          <div class="field"><label data-en="Current password">Aktuelles Passwort</label><input type="password" autocomplete="current-password"></div>
          <div class="field"><label data-en="New password">Neues Passwort</label><input type="password" autocomplete="new-password"></div>
          <div class="field"><label data-en="Confirm new password">Neues Passwort bestätigen</label><input type="password" autocomplete="new-password"></div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit"><span data-en="Update password">Passwort aktualisieren</span></button>
          </div>
        </div>
      </form>

      <!-- Einstellungen -->
      <form class="card" onsubmit="return false">
        <div class="card-head">
          <span class="ch-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
          <h2 data-en="Preferences">Einstellungen</h2>
        </div>
        <div class="card-body">
          <div class="field">
            <label data-en="Language">Sprache</label>
            <select><option>Deutsch</option><option>English</option></select>
          </div>
          <div class="field" style="margin-bottom:8px">
            <label class="check"><input type="checkbox" checked> <span data-en="Email reminders before pickup and return">E-Mail-Erinnerungen vor Abholung und Rückgabe</span></label>
          </div>
          <div class="field" style="margin-bottom:0">
            <label class="check"><input type="checkbox" checked> <span data-en="Booking confirmations by email">Buchungsbestätigungen per E-Mail</span></label>
          </div>
          <div class="form-actions" style="margin-top:18px">
            <button class="btn btn-primary" type="submit"><span data-en="Save preferences">Einstellungen speichern</span></button>
          </div>
        </div>
      </form>
    </div>

    <div class="info-box">
      <span class="ib-ic"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></span>
      <span data-en="This account is only for media lending. The central university login (bt account) is separate.">Dieses Konto gilt nur für die Medienausleihe. Die zentrale Uni-Anmeldung (bt-Kennung) ist davon getrennt.</span>
    </div>

  </div>
</main>

<?php zhl_footer(); ?>
<script src="scripts/zhl-landing.js"></script>
</body>
</html>

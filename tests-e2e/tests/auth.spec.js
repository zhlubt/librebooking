// F1/F2/F12 — Login je Rolle, Dashboard erreichbar.
const { test } = require('@playwright/test');
const { ROLES, login, expect } = require('./helpers');

for (const [key, role] of Object.entries(ROLES)) {
  test(`Login als ${role.label} (${role.email})`, async ({ page }) => {
    await login(page, role.email);
    // Nach Login landet man auf dem Dashboard
    await expect(page).toHaveURL(/dashboard\.php/);
    // Logout-Link / Benutzermenü vorhanden => eingeloggt
    const body = await page.content();
    expect(body.toLowerCase()).toMatch(/logout|abmelden/);
    // Keine Login-Fehlermeldung
    await expect(page.locator('#loginError')).toHaveCount(0);
  });
}

test('Falsches Passwort wird abgelehnt', async ({ page }) => {
  await login(page, ROLES.user.email, 'falsch-falsch');
  // dashboard.php verlangt Auth -> ohne gültige Session Redirect auf die Login-Seite
  expect(new URL(page.url()).pathname).not.toContain('dashboard');
});

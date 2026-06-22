// F2 — Rollenbasierte Zugriffskontrolle: Admin-Seiten nur für Admins.
const { test } = require('@playwright/test');
const { ROLES, login, expect } = require('./helpers');

const ADMIN_PAGE = 'admin/manage_users.php';

test('Application Admin sieht die Benutzerverwaltung', async ({ page }) => {
  await login(page, ROLES.appAdmin.email);
  await page.goto(ADMIN_PAGE, { waitUntil: 'domcontentloaded' });
  await expect(page).toHaveURL(/manage_users\.php/);
  // Die echte Verwaltung hat das "Benutzer hinzufügen"-Formular
  await expect(page.locator('#addUserForm')).toHaveCount(1);
});

test('Regulärer User wird von der Benutzerverwaltung ausgesperrt', async ({ page }) => {
  await login(page, ROLES.user.email);
  // Zugriff verweigert -> kein Admin-Formular erreichbar (LibreBooking zeigt eine
  // Fehlerseite statt der Verwaltung; siehe ISSUES.md #2 — 500 statt 403).
  await page.goto(ADMIN_PAGE, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#addUserForm')).toHaveCount(0);
});

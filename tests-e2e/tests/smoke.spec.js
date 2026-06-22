// Smoke-Test: Kernseiten laden ohne PHP-Fehler (je passende Rolle).
const { test } = require('@playwright/test');
const { ROLES, login, expect } = require('./helpers');

// Seiten, die der Voll-Admin erreichen muss
const ADMIN_PAGES = [
  ['Dashboard', '/dashboard.php'],
  ['Schedule', '/schedule.php'],
  ['Kalender', '/calendar.php'],
  ['Reservierungen', '/reservations.php'],
  ['Admin: Ressourcen', '/admin/manage_resources.php'],
  ['Admin: User', '/admin/manage_users.php'],
  ['Admin: Schedules', '/admin/manage_schedules.php'],
  ['Admin: Quotas', '/admin/manage_quotas.php'],
  ['Admin: Gruppen', '/admin/manage_groups.php'],
];

// Seiten für den normalen User
const USER_PAGES = [
  ['Dashboard', '/dashboard.php'],
  ['Schedule', '/schedule.php'],
  ['Kalender', '/calendar.php'],
];

async function checkPage(page, label, url) {
  const resp = await page.goto(url, { waitUntil: 'domcontentloaded' });
  expect(resp, `${label}: keine Antwort`).toBeTruthy();
  expect(resp.status(), `${label}: HTTP-Status`).toBeLessThan(500);
  const body = (await page.content()).toLowerCase();
  expect(body, `${label}: PHP-Fehler auf Seite`).not.toMatch(/fatal error|parse error|uncaught|call to a member function/);
}

test('Admin: alle Kern- und Adminseiten laden fehlerfrei', async ({ page }) => {
  await login(page, ROLES.appAdmin.email);
  for (const [label, url] of ADMIN_PAGES) {
    await checkPage(page, label, url);
  }
});

test('User: Kernseiten laden fehlerfrei', async ({ page }) => {
  await login(page, ROLES.user.email);
  for (const [label, url] of USER_PAGES) {
    await checkPage(page, label, url);
  }
});

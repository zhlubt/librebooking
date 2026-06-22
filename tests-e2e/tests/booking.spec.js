// F11/F12 — Buchungs-Einstieg: Reservierungsformular für eine Ressource lädt.
// Als Application Admin (umgeht den Permission-Gate), Ressource 5 / Schedule 2.
const { test } = require('@playwright/test');
const { ROLES, login, expect } = require('./helpers');

test('Reservierungsformular für eine Ressource lädt mit Speichern-Button', async ({ page }) => {
  await login(page, ROLES.appAdmin.email);
  await page.goto('reservation.php?rid=5&sid=2', { waitUntil: 'domcontentloaded' });
  const body = (await page.content()).toLowerCase();
  expect(body, 'PHP-Fehler im Reservierungsformular').not.toMatch(/fatal error|parse error|uncaught/);
  // Titel-/Datumsfeld + Speichern-Button vorhanden => Buchungs-UI funktioniert
  await expect(page.locator('#reservationTitle')).toHaveCount(1);
  await expect(page.locator('#BeginDate')).toHaveCount(1);
  await expect(page.locator('.btnCreate').first()).toHaveCount(1);
});

test('Schedule-Seite zeigt Ressourcen', async ({ page }) => {
  await login(page, ROLES.appAdmin.email);
  await page.goto('schedule.php', { waitUntil: 'domcontentloaded' });
  const body = (await page.content()).toLowerCase();
  expect(body).not.toMatch(/fatal error|parse error|uncaught/);
  // mind. eine bekannte Ressource aus der Live-Kopie taucht auf
  expect(body).toMatch(/meta quest|resource|ressource/);
});

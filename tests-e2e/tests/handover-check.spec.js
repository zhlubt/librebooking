// Übergabe-Checkliste (Phase B): QR-Protokoll, Admin-only.
// Fixtures: docs/zhl/seed-phase-b.sql einspielen (zhl_booking_handover token=phaseBtest1234,
// ref=PHASEB-REF, resource=5 confirmed + 2 OPTIONALE Accessories auf Ressource 5).
const { test } = require('@playwright/test');
const { ROLES, login, expect } = require('./helpers');

const QS = 'ref=PHASEB-REF&token=phaseBtest1234&type=pickup&resource=5';
const CHECK = `zhl-handover-check.php?${QS}`;
const QR = `zhl-handover-qr.php?${QS}`;

test('Normaler User -> 403 (Checkliste ist Team-only)', async ({ page }) => {
  await login(page, ROLES.user.email);
  const resp = await page.goto(CHECK, { waitUntil: 'domcontentloaded' });
  expect(resp.status()).toBe(403);
});

test('Admin -> Checkliste rendert mit Zubehör + Gesamtzustand', async ({ page }) => {
  await login(page, ROLES.appAdmin.email);
  await page.goto(CHECK, { waitUntil: 'domcontentloaded' });
  const html = await page.content();
  expect(html).toContain('Übergabe-Protokoll');
  // Strukturiertes Zubehör aus dem Seed sichtbar
  expect(html).toContain('TEST Ladekabel');
  expect(html).toContain('TEST Akku');
  // Gesamtzustand-Auswahl + Signaturfeld
  expect(await page.locator('input[name="overall_condition"]').count()).toBeGreaterThan(0);
  expect(await page.locator('input[name="signature_name"]').count()).toBe(1);
});

test('Admin -> Protokoll absenden markiert Übergabe als erledigt', async ({ page }) => {
  await login(page, ROLES.appAdmin.email);
  await page.goto(CHECK, { waitUntil: 'domcontentloaded' });
  await page.click('label[for="oc-minor"]'); // Bootstrap btn-check: Label klicken statt verstecktes Radio
  await page.fill('textarea[name="condition_note"]', 'E2E: kleiner Kratzer');
  await page.fill('input[name="signature_name"]', 'E2E Tester');
  // ein Accessory auf "fehlt" setzen
  await page.locator('select[name^="state["]').first().selectOption('missing').catch(() => {});
  await page.click('button[type="submit"]');
  await page.waitForLoadState('domcontentloaded');
  expect(await page.content()).toContain('Protokoll gespeichert');
});

test('Admin -> QR-Endpunkt liefert ein PNG', async ({ page }) => {
  await login(page, ROLES.appAdmin.email);
  const resp = await page.goto(QR, { waitUntil: 'domcontentloaded' });
  expect(resp.status()).toBe(200);
  expect(resp.headers()['content-type']).toContain('image/png');
});

// Übergabe-Assistent (zhl-handover-select.php) — Auth-Bindung.
// Codex-Finding: die Seite muss login-gebunden sein und Token an den User binden.
const { test } = require('@playwright/test');
const { ROLES, login, expect } = require('./helpers');

const PAGE = 'zhl-handover-select.php';

test('Nicht eingeloggt -> Redirect auf Login (kein offener Zugriff)', async ({ page }) => {
  const resp = await page.goto(PAGE, { waitUntil: 'domcontentloaded' });
  // Landet auf der Login-Seite (index.php), nicht auf dem Assistenten.
  expect(page.url()).toMatch(/index\.php/);
  expect(await page.content()).not.toContain('Übergabe-Termin wählen');
});

test('Eingeloggt -> Assistent rendert mit Token + CSRF-Sync-Formular', async ({ page }) => {
  await login(page, ROLES.user.email);
  await page.goto(PAGE, { waitUntil: 'domcontentloaded' });
  const html = await page.content();
  expect(html).toContain('Übergabe-Termin wählen');
  // 32-stelliges Token wird erzeugt und angezeigt.
  expect(html).toMatch(/token-box">[a-f0-9]{32}/);
  // Sync ist POST + CSRF (kein GET-Seiteneffekt mehr).
  expect(await page.locator('form[method="POST"] input[name="action"][value="sync"]').count()).toBeGreaterThan(0);
  expect(await page.locator('form[method="POST"] input[name="CSRF_TOKEN"]').count()).toBeGreaterThan(0);
});

test('Fremdes Token eines anderen Kontos -> 403', async ({ page, browser }) => {
  // User A erzeugt ein Token.
  await login(page, ROLES.user.email);
  await page.goto(PAGE, { waitUntil: 'domcontentloaded' });
  const token = (await page.content()).match(/token-box">([a-f0-9]{32})/)[1];

  // User B (eigener Context) versucht, A's Token zu öffnen -> 403.
  const ctxB = await browser.newContext();
  const pageB = await ctxB.newPage();
  await login(pageB, ROLES.cert.email);
  const resp = await pageB.goto(`${PAGE}?token=${token}`, { waitUntil: 'domcontentloaded' });
  expect(resp.status()).toBe(403);
  await ctxB.close();
});

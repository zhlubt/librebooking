// ZHL-Branding — css.extension.file (UBT-Grün) wird angewendet.
const { test } = require('@playwright/test');
const { expect } = require('./helpers');

test('Login-Button trägt das UBT-Grün (Theme geladen)', async ({ page }) => {
  await page.goto('index.php', { waitUntil: 'domcontentloaded' });
  const btn = page.locator('#login button[type="submit"]').first();
  const bg = await btn.evaluate(el => getComputedStyle(el).backgroundColor);
  // #009260 == rgb(0, 146, 96)
  expect(bg).toBe('rgb(0, 146, 96)');
});

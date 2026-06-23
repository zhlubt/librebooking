// UX — ZHL lang-overrides: „Ressource" -> „Gerät" (de_de) ohne Sprachdatei zu patchen.
const { test } = require('@playwright/test');
const { ROLES, login, expect } = require('./helpers');

test('Begriff „Gerät" ersetzt „Ressource" (manage_quotas nutzt AllResources)', async ({ page }) => {
  await login(page, ROLES.appAdmin.email);
  await page.goto('admin/manage_quotas.php', { waitUntil: 'domcontentloaded' });
  const body = await page.content();
  expect(body).toContain('Alle Geräte');
  expect(body).not.toContain('Alle Ressourcen');
});

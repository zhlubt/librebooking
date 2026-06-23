// F40 Stufe 2 — Zertifikat-Ablauf (Permission-Plugin ZhlCertificate, cron-frei).
// Gleiche Cert-Gruppe, aber unterschiedliche Zertifikat-Gültigkeit -> anderes Ergebnis.
const { test } = require('@playwright/test');
const { ROLES, login, attemptBooking, expect } = require('./helpers');

const RESTRICTED_RID = 53;  // zertifikatspflichtig (zhl_certificate_required)
const SCHEDULE_SID = 5;
const PERMISSION_ERROR = /do not have permission|keine berechtigung|not allowed/i;

test('Gültiges Zertifikat: eingewiesener User darf buchen', async ({ page }) => {
  await login(page, ROLES.cert.email); // certuser: gültiges Zertifikat
  const result = await attemptBooking(page, RESTRICTED_RID, SCHEDULE_SID);
  expect(result).not.toMatch(PERMISSION_ERROR);
});

test('Abgelaufenes Zertifikat: trotz Gruppen-Mitgliedschaft gesperrt', async ({ page }) => {
  await login(page, 'certexpired@zhl.local'); // in Cert-Gruppe, aber Zertifikat abgelaufen
  const result = await attemptBooking(page, RESTRICTED_RID, SCHEDULE_SID);
  // Das Plugin erzwingt den Ablauf zusätzlich zum Gruppen-Gate (Stufe 1).
  expect(result).toMatch(PERMISSION_ERROR);
});

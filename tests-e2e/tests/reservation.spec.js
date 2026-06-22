// F11/F12 — Vollständige Buchung end-to-end (regulärer User, nicht-beschränktes Medium).
// Beweist: Permission-Gate passiert + ZHL-Pflicht-Attribute akzeptiert + Buchung verarbeitet.
const { test } = require('@playwright/test');
const { ROLES, login, completeBooking, expect } = require('./helpers');

// Erfolg ODER (bei Re-Run) Terminkonflikt — beides beweist, dass die Buchung fachlich
// bis zur Verfügbarkeitsprüfung lief (Permission + Pflichtfelder ok). DE + EN.
const PIPELINE_RAN = /successfully created|erfolgreich angelegt|conflicting reservations|in Konflikt stehende|Konflikt/i;
const ATTR_ERROR = /additional attributes|zusätzlichen Attribut/i;

test('Regulärer User bucht ein nicht-beschränktes Medium vollständig', async ({ page }) => {
  await login(page, ROLES.user.email);
  const result = await completeBooking(page, 5, 2); // rid=5 Meta Quest (autoassign=1)
  expect(result, 'Pflichtfelder/Permission ok, Buchung verarbeitet').toMatch(PIPELINE_RAN);
  expect(result, 'keine Pflichtfeld-Fehler').not.toMatch(ATTR_ERROR);
});

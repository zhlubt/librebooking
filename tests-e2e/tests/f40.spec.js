// F40 — Einweisungs-/Berechtigungspflicht (Gruppen-Gate, Stufe 1, ohne Code).
// Beschränkte Ressource: rid=53 (Lenovo Legion Gaming-PC, autoassign=0) auf sid=5.
// Nur Mitglieder der Gruppe "Eingewiesen: Gaming-PC" dürfen buchen.
const { test } = require('@playwright/test');
const { ROLES, login, attemptBooking, expect } = require('./helpers');

const RESTRICTED_RID = 53;
const SCHEDULE_SID = 5;
const PERMISSION_ERROR = /do not have permission|keine berechtigung|not allowed/i;

test('Nicht eingewiesener User wird beim beschränkten Medium vom Gate gestoppt', async ({ page }) => {
  await login(page, ROLES.user.email);
  const result = await attemptBooking(page, RESTRICTED_RID, SCHEDULE_SID);
  // Permission-Gate greift -> klare Berechtigungs-Fehlermeldung
  expect(result).toMatch(PERMISSION_ERROR);
});

test('Eingewiesener User (Cert-Gruppe) kommt am Gate vorbei', async ({ page }) => {
  await login(page, ROLES.cert.email);
  const result = await attemptBooking(page, RESTRICTED_RID, SCHEDULE_SID);
  // Kein Berechtigungsfehler mehr (Buchung wird erlaubt; ggf. scheitert sie nur an
  // weiteren Pflichtfeldern wie Custom-Attributen — das Gate ist passiert).
  expect(result).not.toMatch(PERMISSION_ERROR);
});

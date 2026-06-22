// Gemeinsame Helfer: Rollen + Login gegen LibreBooking.
const { expect } = require('@playwright/test');

const PASSWORD = 'zhltest123';

const ROLES = {
  appAdmin:      { email: 'admin@zhl.local',         label: 'Application Admin' },
  groupAdmin:    { email: 'groupadmin@zhl.local',    label: 'Group Admin' },
  resourceAdmin: { email: 'resourceadmin@zhl.local', label: 'Resource Admin' },
  scheduleAdmin: { email: 'scheduleadmin@zhl.local', label: 'Schedule Admin' },
  user:          { email: 'user@zhl.local',          label: 'Regular User' },
  cert:          { email: 'certuser@zhl.local',      label: 'Eingewiesener User (F40)' },
};

// Loggt einen User ein. Wartet auf den Seitenwechsel (kein networkidle -> robuster).
async function login(page, email, password = PASSWORD) {
  // Login-Formular rendern (prüft, dass die Login-Seite da ist) ...
  await page.goto('index.php', { waitUntil: 'domcontentloaded' });
  await page.fill('#email', email);
  await page.fill('#password', password);
  // ... aber per API-POST absenden: setzt die Session im selben Browser-Context
  // und umgeht den fehlerhaften App-Homepage-Redirect (siehe ISSUES.md, #1).
  await page.request.post('index.php', {
    form: { email, password, login: 'Log In' },
    maxRedirects: 0,
  }).catch(() => {});
  // Dashboard explizit ansteuern -> verifiziert die authentifizierte Session.
  await page.goto('dashboard.php', { waitUntil: 'domcontentloaded' });
}

async function logout(page) {
  await page.goto('index.php?logout=true').catch(() => {});
}

// Versucht eine Buchung und gibt die Antwort von reservation_save.php zurück.
// Das Permission-Gate (F40) schlägt hier zu: nicht-berechtigte User bekommen
// eine "permission"-Fehlermeldung, berechtigte kommen daran vorbei.
async function attemptBooking(page, rid, sid) {
  await page.goto(`reservation.php?rid=${rid}&sid=${sid}`, { waitUntil: 'domcontentloaded' });
  await page.fill('#reservationTitle', 'E2E F40');
  if (await page.locator('#BeginPeriod option').count() > 1) {
    await page.selectOption('#BeginPeriod', { index: 1 }).catch(() => {});
    await page.selectOption('#EndPeriod', { index: 2 }).catch(() => {});
  }
  const respPromise = page.waitForResponse(
    r => r.url().includes('reservation_save'), { timeout: 12000 }
  );
  await page.locator('.btnCreate').first().click();
  const resp = await respPromise;
  return resp.text();
}

// Führt eine VOLLSTÄNDIGE Buchung durch (inkl. ZHL-Pflicht-Attribute) und gibt die
// Antwort von reservation_save.php zurück. ZHL verlangt aktuell bei jeder Reservierung:
//  - psiattribute5 (Checkbox): "Ich besitze eine Haftpflichtversicherung"
//  - psiattribute6 (Text):     "3 Terminvorschläge für Abholung"
async function completeBooking(page, rid, sid) {
  await page.goto(`reservation.php?rid=${rid}&sid=${sid}`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1200); // Custom-Attribute laden per JS nach
  await page.fill('#reservationTitle', 'E2E Vollbuchung');
  if (await page.locator('#BeginPeriod option').count() > 1) {
    await page.selectOption('#BeginPeriod', { index: 1 }).catch(() => {});
    await page.selectOption('#EndPeriod', { index: 2 }).catch(() => {});
  }
  await page.check('#psiattribute5').catch(() => {});
  await page.fill('#psiattribute6', 'Mo 10:00, Di 14:00, Mi 09:00').catch(() => {});
  const respPromise = page.waitForResponse(
    r => r.url().includes('reservation_save'), { timeout: 12000 }
  );
  await page.locator('.btnCreate').first().click();
  const resp = await respPromise;
  return resp.text();
}

module.exports = { ROLES, PASSWORD, login, logout, attemptBooking, completeBooking, expect };

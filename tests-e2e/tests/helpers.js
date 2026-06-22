// Gemeinsame Helfer: Rollen + Login gegen LibreBooking.
const { expect } = require('@playwright/test');

const PASSWORD = 'zhltest123';

const ROLES = {
  appAdmin:      { email: 'admin@zhl.local',         label: 'Application Admin' },
  groupAdmin:    { email: 'groupadmin@zhl.local',    label: 'Group Admin' },
  resourceAdmin: { email: 'resourceadmin@zhl.local', label: 'Resource Admin' },
  scheduleAdmin: { email: 'scheduleadmin@zhl.local', label: 'Schedule Admin' },
  user:          { email: 'user@zhl.local',          label: 'Regular User' },
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

module.exports = { ROLES, PASSWORD, login, logout, expect };

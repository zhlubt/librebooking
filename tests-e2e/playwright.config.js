// ZHL E2E — Playwright config gegen die lokale LibreBooking-Instanz.
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests',
  timeout: 30000,
  expect: { timeout: 7000 },
  // php -S ist single-threaded -> sequenziell testen, sonst Timeouts.
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    // App wird unter /Web/ serviert (wie Produktion) -> Trailing-Slash wichtig,
    // damit relative Pfade ('schedule.php') korrekt unter /Web/ aufgelöst werden.
    baseURL: process.env.ZHL_BASE_URL || 'http://127.0.0.1:8080/Web/',
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    locale: 'de-DE',
    navigationTimeout: 20000,
    actionTimeout: 10000,
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
});

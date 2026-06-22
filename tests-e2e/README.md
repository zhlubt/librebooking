# ZHL E2E-Tests (Playwright)

Browser-Tests gegen die **lokale** LibreBooking-Instanz — verifizieren, dass die
Funktionen wirklich laufen (Login je Rolle, Zugriffskontrolle, Kernseiten, Buchungs-UI).

## Voraussetzungen
1. Lokale DB-Kopie + App laufen (siehe `docs/zhl/LOCAL-DEV.md`):
   - DB-Container `zhl-mariadb` mit importierter Live-Kopie.
   - App unter **`http://127.0.0.1:8080/Web/`** (docroot = Repo-Wurzel,
     `config.php: script.url = http://127.0.0.1:8080/Web`):
     ```bash
     php -S 127.0.0.1:8080            # docroot = Repo-Wurzel
     ```
2. Test-User angelegt: `./docs/zhl/seed-test-users.sh` (Logins in
   `docs/zhl/CREDENTIALS-LOCAL.md`, Passwort `zhltest123`).

## Ausführen
```bash
cd tests-e2e
npm install            # einmalig
npx playwright install chromium   # einmalig
npm test               # alle Tests
npm run report         # HTML-Report öffnen
```
Andere Basis-URL: `ZHL_BASE_URL=http://host/Web/ npm test`.

## Abdeckung
| Spec | Prüft |
|---|---|
| `auth.spec.js` | Login je Rolle (5) + Dashboard; falsches Passwort abgelehnt |
| `access-control.spec.js` | Admin sieht Benutzerverwaltung; regulärer User ausgesperrt |
| `smoke.spec.js` | Alle Kern-/Adminseiten laden fehlerfrei (kein PHP-Fatal) |
| `booking.spec.js` | Reservierungsformular + Schedule laden mit Ressourcen |

## Hinweise
- `php -S` ist single-threaded → Config nutzt **1 Worker** + `domcontentloaded`.
- Login erfolgt per API-POST im selben Context (umgeht Issue #1, siehe `docs/zhl/ISSUES.md`).
- Neue Tests: Helfer aus `tests/helpers.js` nutzen (`login(page, ROLES.x.email)`).

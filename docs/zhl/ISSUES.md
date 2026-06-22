# Beobachtete Probleme (lokal verifiziert)

Beim Aufsetzen der lokalen Instanz + E2E-Tests gefunden. Kein Live-Eingriff.

## #1 — Post-Login-Redirect baut `//schedule.php` (Konfig, gelöst)
**Symptom:** Nach Login leitet die App auf `schedule.php` weiter; der Browser löste das
zu `http://schedule.php/` auf (`net::ERR_NAME_NOT_RESOLVED`). Ursache: `schedule.php`
sendete `Location: //schedule.php` (protokoll-relativ) — gebaut von
`URIScriptValidator` aus `script.url`. Bei **leerem Pfad** (`script.url=http://host:8080`)
entsteht `//schedule.php`.
**Lösung (lokal):** `script.url` **mit Pfad** setzen wie in Produktion
(`http://127.0.0.1:8080/Web`) und die App unter `/Web/` servieren (docroot = Repo-Wurzel).
→ `schedule.php` liefert dann 200. **Kein LibreBooking-Bug** (Live mit `…/Web` ist korrekt).
**Konsequenz:** Lokale Instanz immer unter `http://127.0.0.1:8080/Web/` aufrufen.

## #2 — Unautorisierter Admin-Zugriff liefert HTTP 500 statt 403
**Symptom:** Ein regulärer User auf `admin/manage_users.php` bekommt eine **500**-
Fehlerseite („Fehler"), keinen sauberen 403/Redirect. **Sicherheit ist ok** (keine
Admin-Daten/Funktionen erreichbar), aber die Fehlerbehandlung ist unschön.
**Status:** Beobachtung; ggf. Upstream melden oder im ZHL-Overlay abfangen. Kein Blocker.

## Betriebs-Gotcha (kein App-Problem)
- `php -S` ist single-threaded → beim echten Browser entweder Blockaden (1 Worker) oder
  Verbindungsabbrüche (`PHP_CLI_SERVER_WORKERS` ist auf macOS instabil). Für E2E daher:
  **1 Worker** + Navigationen mit `waitUntil:'domcontentloaded'` + Login per API-POST
  (siehe `tests-e2e/`). Für realistischen Betrieb echten Apache/nginx nutzen.

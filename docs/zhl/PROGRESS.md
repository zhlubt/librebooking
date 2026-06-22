# Roter Faden — ZHL Buchungssystem

> Lebendiger Tracker. Hier steht jederzeit: wo wir stehen, was als Nächstes kommt,
> welche Entscheidungen offen sind. Bei jeder Sitzung oben kurz fortschreiben.

**Stand:** 2026-06-22 · **Aktueller Fokus:** Fundament steht, erste Features starten.

## Nordstern
LibreBooking modernisieren (nicht neu bauen): aktuelle Version + **neue, einfachere
Frontpage/UX** + mehr Konfiguration. Details: [STRATEGY.md](STRATEGY.md).

## Status der Bausteine
| Baustein | Status | Notiz |
|---|---|---|
| Repo/Fork `zhlubt/librebooking` | ✅ | Branch `zhl-main` auf v5.1.0, Upstream-Remote, Default-Branch |
| Dev-Workflow (Branch+PR) | ✅ | [WORKFLOW.md](WORKFLOW.md) |
| Read-only Live-Code-Kopie | ✅ | `../zhl-buchungssystem-LIVE-COPY/live-mirror/` |
| Live-DB-Dump | ✅ | `../zhl-buchungssystem-LIVE-COPY/zhl_live_dump.sql` (61 Tab., 290 User) |
| Lokale Umgebung (5.1.0 + DB-Kopie) | ✅ | MariaDB:3306 (Docker `zhl-mariadb`), App `http://127.0.0.1:8080/Web` |
| Upgrade-Rehearsal 4.0.0→5.1.0 | ✅ | DB-Kopie läuft fehlerfrei unter 5.1.0; 4.0-Upgrade = No-op + Versionsstempel |
| Altes Repo `zhl-buchungssystem` | ✅ | Archiv (`archive/custom-rewrite-v1`), PR #1 geschlossen |
| Deutsche Default-Sprache | ⏳ | Config `default.language=de_de` (Quick-Win) |
| Frontpage-Prototyp | ⏳ | Branch `feat/landing-page` |
| Agentischer Feature-Review-Prozess | ✅ läuft | [AGENTIC-PLAN.md](AGENTIC-PLAN.md); 1. Runde durch |
| Feature-Verifikation (40 Features am Code) | ✅ | 4 Scout-Agenten; [FEATURES.md](FEATURES.md) belegt; [WORKPACKAGES.md](WORKPACKAGES.md) |
| Produktiv-Upgrade | ⛔ offen | Wartungsfenster + finales Backup nötig |

## Verifizierte Eckdaten
- Live: LibreBooking **4.0.0** → Ziel **5.1.0**; Hosting SFTP:2222 + SSH:22 (Container-Shell `zhl-buchung`), MariaDB 11.4 `zhl_buchung` (intern).
- Lokaler Login-Test: User 3 `zhlmedien@uni-bayreuth.de` / `zhltest123` (**nur lokale Wegwerf-Kopie**, Passwort dort gesetzt).

## Nächste Schritte
1. `feat/landing-page`: deutsche Default-Sprache + Frontpage-Prototyp → PR gegen `zhl-main`.
2. Agentischen Feature-Review-Prozess aufsetzen ([AGENTIC-PLAN.md](AGENTIC-PLAN.md)) inkl. **Codex-Gegencheck**.
3. Produktiv-Upgrade planen (Wartungsfenster).

## Offene Entscheidungen
- Umfang UX-Vereinfachung (welche Ansichten für Standard-User verstecken?).
- Wartungsfenster fürs Produktiv-Upgrade (UP-1 **vor** produktiver Config/UX).
- **Cron fehlt (verifiziert!):** Im App-Container läuft **kein Cron** (kein cron-Binary,
  keine LB-Job-Crontab). `sendreminders/sendwaitlist/sendmissedcheckin` werden aktuell
  **nicht ausgeführt** → Reminder/Waitlist (QW-3/QW-4) brauchen erst einen **Job-Runner**
  (Host-Cron, Sidecar-Container oder Web-Cron per URL). **Klärung mit Hoster nötig.**
- F40-Betriebsprozess: Wer trägt Zertifikats-Gruppen ein/entzieht; Umgang mit bestehenden
  Buchungen bei Entzug/Ablauf (Gate wirkt nicht rückwirkend).

## Test-Zugänge & E2E
- Lokale Test-Logins (alle Rollen, Passwort `zhltest123`): `admin@/groupadmin@/resourceadmin@/scheduleadmin@/user@zhl.local` — Doku `CREDENTIALS-LOCAL.md` (gitignored), Seed `seed-test-users.sh`.
- **Playwright-E2E** (`tests-e2e/`, 12 Tests grün): Login je Rolle, Zugriffskontrolle, Smoke aller Kernseiten, Buchungs-UI. App lokal unter **`http://127.0.0.1:8080/Web/`**.
- Funde: siehe [ISSUES.md](ISSUES.md) (#1 script.url-Pfad/Redirect, #2 500 bei unautor. Admin-Zugriff).

## Changelog
- 2026-06-22: Fork angelegt, lokale Umgebung + DB-Kopie aufgebaut, Upgrade-Rehearsal erfolgreich, altes Repo archiviert.
- 2026-06-22: Agentischen Prozess + kanonische FEATURES.md erstellt; **Codex-Gate gelaufen** (Findings eingearbeitet: F6/F20/F22/F26/F28/F33/F35/F36 korrigiert, Querschnitt-Risiken ergänzt). PR #1 (Landing-Page) offen.
- 2026-06-22: **Neuer Feature-Request F40** (Einweisungs-/Berechtigungspflicht) aufgenommen — Gating nativ (Resource-Permissions), Badge/Zertifikat-Lifecycle = Custom.
- 2026-06-22: Lokaler Server auf docroot=`Web/` umgestellt → App unter `http://127.0.0.1:8080/` (vorher CSS-Stolperfalle ohne Trailing-Slash).
- 2026-06-22: **Codex-Gate Runde 2**: FEATURES.md bestätigt; STRATEGY §5 auf FEATURES.md umgestellt (alte Tabelle war zu optimistisch); WORKPACKAGES-Reihenfolge korrigiert (UP-1 zuerst; QW-4≠F34; CM-3 vor CM-2); F40-Caveats ergänzt (Gate nicht rückwirkend, Admins exempt); Cron-bei-SFTP-only als offene Frage.
- 2026-06-22: **Feature-Verifikations-Runde** (4 Scout-Agenten am Code): alle 40 Features belegt. Schlüsselbefunde: QR+Check-in/out und Resource-Permission-Gating sind **nativ**; echte Custom-Lücken: Personal-Übergabe-Slots (F16/17), Übergabe-Flag (F8), QR-Checkliste (F10), Accessory-Zustand (F30), Audit (F37), DSGVO (F38), Overdue-Eskalation (F34), Fuzzy-Suche (F25). F40-Konzept ([F40-KONZEPT.md](F40-KONZEPT.md)) + Arbeitspakete ([WORKPACKAGES.md](WORKPACKAGES.md)) erstellt.

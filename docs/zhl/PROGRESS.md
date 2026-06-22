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
| Agentischer Feature-Review-Prozess | ⏳ | siehe [AGENTIC-PLAN.md](AGENTIC-PLAN.md) |
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
- Wartungsfenster fürs Produktiv-Upgrade.

## Changelog
- 2026-06-22: Fork angelegt, lokale Umgebung + DB-Kopie aufgebaut, Upgrade-Rehearsal erfolgreich, altes Repo archiviert.

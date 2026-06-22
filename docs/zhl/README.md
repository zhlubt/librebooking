# ZHL Buchungssystem — Fork von LibreBooking

Dies ist der **ZHL-Fork von LibreBooking** für die Medienausleihe der Uni Bayreuth
(live: https://buchung.zhl-ubt.de). Ziel: aktuelles LibreBooking + **neue Frontpage /
einfachere UX** + mehr Konfiguration.

> Der gesamte ZHL-spezifische Inhalt liegt unter `docs/zhl/`. Alles andere ist
> LibreBooking-Upstream.

## Doku
- **[STRATEGY.md](STRATEGY.md)** — Richtung, 4-Säulen-Roadmap, Mapping aller 39 Wunsch-Features auf LibreBooking
- **[WORKFLOW.md](WORKFLOW.md)** — Branch+PR-Modell, Upstream-Merges, Commit-Konventionen
- **[LOCAL-DEV.md](LOCAL-DEV.md)** — lokal aufsetzen (LibreBooking 5.1.0 + Kopie der Live-DB)
- **[UPGRADE-RUNBOOK.md](UPGRADE-RUNBOOK.md)** — Live-Upgrade 4.0.0 → 5.1.0

## Branches
- `zhl-main` — unser Arbeits-Branch (Basis: Upstream-Tag **v5.1.0**). Alle ZHL-Arbeit
  per Feature-Branch + PR **gegen `zhl-main`**.
- `develop` / Tags — Upstream-LibreBooking (Remote `upstream`), nur zum Mergen.

## Eckdaten Live (verifiziert 2026-06-22)
| | |
|---|---|
| URL | https://buchung.zhl-ubt.de (LibreBooking 4.0.0 → Ziel 5.1.0) |
| Hosting | dockerisiert; SFTP Port 2222 (Datei-Deploy) **+** SSH Port 22 (`zhl-buchung`, Container-Shell) |
| DB | MariaDB 11.4 `zhl_buchung`, intern (Host `mariadb`); 61 Tabellen |
| Daten (Stand Dump) | 290 User, 59 Ressourcen, 1187 Reservierungs-Serien |
| Customizing | minimal (Theme `default`, Default-Sprache `en_us`) |

Zugangsdaten liegen **außerhalb des Repos** (lokale `.env`), niemals committen.

## Live-Kopie & DB-Dump
- Read-only Code-Kopie: `../zhl-buchungssystem-LIVE-COPY/live-mirror/`
- DB-Dump (read-only via SSH/PHP gezogen): `../zhl-buchungssystem-LIVE-COPY/zhl_live_dump.sql`
  (Import: siehe LOCAL-DEV.md)

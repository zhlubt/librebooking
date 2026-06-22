# ZHL Buchungssystem — Strategie auf Basis von LibreBooking

> Stand: 2026-06-22. Dieses Dokument löst die bisherige Annahme einer Eigen-
> neuentwicklung ab. **Neue Grundlage: LibreBooking** (das bereits live laufende
> System wird modernisiert, nicht ersetzt.)

## 1. Ausgangslage (Ist-Stand, verifiziert)

| Punkt | Wert |
|---|---|
| Live-URL | https://buchung.zhl-ubt.de (Login-Titel „Reservierungen") |
| Live-Software | **LibreBooking 4.0.0** (GPLv3), dockerisiert |
| Neueste Version | **LibreBooking 5.1.0** (07.06.2026) |
| Hosting | Managed, **nur SFTP** (Port 2222), kein Shell-Zugriff |
| Datenbank | MariaDB `zhl_buchung` (intern, Host `mariadb`, Creds via Docker-Secrets) |
| Deploy bisher | FTP-Deploy aus VS Code (`.ftp-deploy-sync-state.json` vorhanden) |
| Customizing | **Minimal** — Theme `default`, kein `css.extension.file`, Default-Sprache `en_us` |
| Read-only-Kopie | `../zhl-buchungssystem-LIVE-COPY/live-mirror/` (2527 Dateien, Code) |

**Kernproblem laut Auftrag:** LibreBooking ist „zu komplex zu bedienen", es fehlen
Konfigurationsoptionen, und es braucht eine neue Frontpage / bessere UX.

## 2. Zentrale Erkenntnis

LibreBooking deckt **den Großteil der ursprünglich gewünschten 39 Features bereits
nativ ab** (siehe Mapping §5). Die ursprüngliche Wunschliste wurde für einen Neubau
geschrieben — auf LibreBooking-Basis ist vieles **reine Konfiguration**, nicht
Entwicklung. Der echte Arbeitsschwerpunkt ist:

1. **UX/Frontpage** (das, was LibreBooking nicht gut macht), und
2. **wenige echte Funktionslücken** (QR-Verifikation, Personal-Übergabe-Timeslots).

## 3. Architekturprinzip: Fork + Upstream-Merge

Dieses Repo ist ein **Fork von `LibreBooking/librebooking`**. Unser Arbeits-Branch
`zhl-main` basiert auf Tag **v5.1.0**. Wir dürfen Core/Templates direkt anpassen
(nötig für die UX-Ziele), halten aber zwei Prinzipien ein, damit Upstream-Upgrades
einfach bleiben:

1. **Änderungen bündeln, wo möglich** in eindeutig ZHL-eigene Dateien/Bereiche
   (eigene Plugins, `docs/zhl/`, neue Templates) statt verstreuter Core-Edits.
2. **Wo Core-Edit nötig ist:** klein halten, im Commit begründen, mit `// ZHL:`
   markieren — erleichtert spätere Merge-Konflikte.

Bevorzugte Mechanismen je nach Aufgabe (vom schonendsten zum invasivsten):

| Mechanismus | Wofür |
|---|---|
| `config.php` | Features ein-/ausschalten (viel von Säule 2) |
| `config/lang-overrides.php` (Per-String-Override) | Deutsche Standardsprache, Begriffe vereinfachen — ohne `lang/` zu patchen |
| `css.extension.file` + `plugins/Styling` | Branding, Optik |
| eigene Plugins (Pre/PostReservation, PostRegistration, Authorization) | Workflows ohne Core-Patch |
| Smarty-Templates (`tpl/`) / Core (markiert `// ZHL:`) | Neue Frontpage, UX-Vereinfachung, QR-Verifikation |

Tabu (laut Upstream-`CLAUDE.md`): `lib/external/` nie anfassen; DB-Änderungen nur als
neue Skripte unter `database_schema/upgrades/`, nie `create-schema.sql` editieren.

**Upstream-Updates** holen wir per `git fetch upstream --tags` + `git merge <tag>`
in `zhl-main`.

## 4. Die vier Säulen (Roadmap)

### Säule 1 — Upgrade 4.0.0 → 5.1.0 (zuerst, niedriges Risiko)
LibreBooking hatte zwischen 4.0 und 5.1 **keine Breaking-DB-Changes** (Schema-Upgrades
reichen nur bis „4.0"). Gewinn u.a.: **deutsche Lokalisierungs-Fixes** (5.0.3),
**PHP-8.5-Kompatibilität** (5.0.3, relevant da lokal PHP 8.5), Security-Hardening
(5.1.0), Accessibility (5.0.x). Einziger nennenswerter Config-Change: LDAP host/port →
URI (irrelevant, da Self-Registration genutzt).

Vorgehen siehe [UPGRADE-RUNBOOK.md](UPGRADE-RUNBOOK.md) (Rehearsal lokal → Backup → Deploy → Verify).

### Säule 2 — Konfiguration freischalten („mehr Optionen")
Viele Wünsche sind bereits vorhandene Schalter, aktuell aus:

- `default.language` → **`de_de`** (sofortiger UX-Gewinn; Paket ist installiert)
- `reminders.enabled` → an (Start-/End-Reminder, F21/F23)
- Reservierungs-Quotas pro Ressource/Gruppe (F32)
- Approval/Moderation **pro Ressource** (F14, bereits granular vorhanden)
- Check-in/Check-out (F10-Teil), Waitlist (`allow.wait.list`, F36)
- Resource-Accessories mit Mengen (deckt Teile von F30 ab)

### Säule 3 — UX / Frontpage (Hauptdifferenzierung)
- **Neue öffentliche Landing-Page** (F27): klares, einfaches Einstiegsbild statt
  direkt der komplexen Reservierungsmatrix; Kategorien/Ressourcen als Kacheln,
  klare CTAs (Anmelden / Buchen).
- **Vereinfachte Buchungsansicht** für Standard-User (Komplexität verstecken):
  per Styling-Plugin + CSS-Overlay; Admin behält Vollansicht.
- **Branding** ZHL/UBT (Farben, Logo, Begriffe).
- Mobile bereits gegeben (LibreBooking ist responsiv).

### Säule 4 — Echte Funktionslücken (Custom, upgrade-sicher)
Nur was LibreBooking nicht kann, gezielt ergänzen:

- **QR-Code-Verifikation + Übergabe-Checkliste** (F10/F30) — eigene Seite/Plugin.
- **Personal-Übergabe-Timeslots** (Manager-Required-Items, F8/F16/F17) — Übergabe-
  Terminfindung mit Personal; LibreBooking kennt Ressourcen-, aber keine
  Personal-Verfügbarkeit für Aushändigung. → Custom.
- **Erweiterte Suche** (Fuzzy/Synonyme, F25) — falls nach UX-Phase noch nötig.

## 5. Feature-Mapping

> Die kanonische, **am Code belegte** Feature-Liste (40 Features inkl. F40, mit
> Status/Beleg/nächstem Schritt) ist **[FEATURES.md](FEATURES.md)** — durch Scout-
> Agenten + **Codex-Gate** verifiziert. Diese ältere Tabelle wurde dorthin überführt.

Kurz-Quintessenz (nach Verifikation): ~18 ✅ nativ · ~7 🟦 Konfig · ~6 🟨 teilweise ·
~9 🔧 Custom. Custom-Schwerpunkte: Personal-Übergabe-Slots (F16/F17), Übergabe-Flag (F8),
QR-Checkliste (F10), Accessory-Zustand (F30), Audit (F37), DSGVO (F38), Overdue-Eskalation
(F34), Fuzzy-Suche (F25), Zertifikat-Lifecycle (F40). Arbeitspakete: [WORKPACKAGES.md](WORKPACKAGES.md).

## 6. Empfohlene Reihenfolge

1. **Lokales LibreBooking 5.1.0 + Kopie der Live-DB** lauffähig machen (Rehearsal-Umgebung).
2. **Upgrade-Runbook** an Kopie testen (4.0.0 → 5.1.0).
3. **Konfiguration** (Säule 2) an der Kopie durchspielen, dokumentieren.
4. **UX/Frontpage-Prototyp** (Säule 3) lokal bauen, abstimmen.
5. **Produktiv-Upgrade** in Wartungsfenster + Rollback-Plan.
6. **Frontpage + Custom-Lücken** iterativ ausliefern (je 1 Branch/PR).

## 7. Offene Punkte / Entscheidungen

- **DB-Dump der Live-DB:** Kein Shell + interne MariaDB → Dump nur via phpMyAdmin
  (falls vorhanden) oder einmaligem Export-Skript per SFTP (schreibt temporär auf
  Live). Methode bestätigen.
- Wartungsfenster fürs Produktiv-Upgrade.
- Umfang der UX-Vereinfachung (welche Ansichten für Standard-User verstecken).

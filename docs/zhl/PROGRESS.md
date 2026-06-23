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
- **Cron-Runner gebaut** (`Web/zhl-cron.php`, lokal verifiziert): token-geschützter Web-Endpunkt
  führt die LB-Jobs aus. **Offen:** (a) wo der externe Scheduler läuft (Gaming-PC/Mac/Dienst),
  (b) Deploy-Zeitpunkt auf Live. Siehe [CRON-RUNBOOK.md](CRON-RUNBOOK.md).
- F40-Betriebsprozess: Wer trägt Zertifikats-Gruppen ein/entzieht; Umgang mit bestehenden
  Buchungen bei Entzug/Ablauf (Gate wirkt nicht rückwirkend).

## Test-Zugänge & E2E
- Lokale Test-Logins (alle Rollen, Passwort `zhltest123`): `admin@/groupadmin@/resourceadmin@/scheduleadmin@/user@zhl.local` — Doku `CREDENTIALS-LOCAL.md` (gitignored), Seed `seed-test-users.sh`.
- **Playwright-E2E** (`tests-e2e/`, 12 Tests grün): Login je Rolle, Zugriffskontrolle, Smoke aller Kernseiten, Buchungs-UI. App lokal unter **`http://127.0.0.1:8080/Web/`**.
- Funde: siehe [ISSUES.md](ISSUES.md) (#1 script.url-Pfad/Redirect, #2 500 bei unautor. Admin-Zugriff).

## Changelog
- 2026-06-23: **Übergabe-Modul Phase C gebaut** (Overdue-/Rückgabe-Eskalation, F34). Migration 005
  (`zhl_overdue_notice`, Unique handover_id+stage). `Jobs/zhl_overdue.php` (CLI/JobCop): überfällige
  Rückgaben (return, nicht done, Ende+24h überschritten) → mehrstufige Mahn-Mails (Stufen 1/3/7 Tage,
  zeitbasiert), Empfänger via Token, Versand pro Datensatz abgesichert, optional User-Sperre (Default AUS).
  In `Web/zhl-cron.php` eingetragen. Verifiziert: `verify-handover-overdue.php` **6/6** + realer Job-Lauf
  (8 Tage → Stufe 3, Mail+Notice). Damit ist das Übergabe-Modul A+B+C komplett.
- 2026-06-23: **media.zhl-ubt.de Staging LIVE** (kompletter Stack + Phase A/B deployt); CSS-Fix
  (`rsync --exclude=vendor` killte versehentlich `Web/assets/vendor`); `.htaccess`-HTTPS-Force-Loop behoben.
- 2026-06-23: **Übergabe-Modul Phase B gebaut** (QR-Checkliste + Zustand, F10/F30). Migration 004
  (check_item.label + check.overall_condition). `Web/zhl-handover-check.php` (SecurePage, Admin-only):
  Zubehör-Checkliste (ok/fehlt/beschädigt) + ad-hoc + Gesamtzustand + Unterschrift → schreibt
  `zhl_handover_check(_item)`, setzt Übergabe `done`. `Web/zhl-handover-qr.php` (BaconQrCode → Checkliste,
  NICHT Reservierung). `Web/zhl-handover-admin.php` (Betriebsübersicht, schließt Codex-Lücke).
  E2E `handover-check.spec.js` **4/4**, Fixtures `seed-phase-b.sql`. **Suite 28/28** — dabei Buchungstests
  robust gemacht (helpers `setFutureDate` via flatpickr → keine Tageszeit-Abhängigkeit mehr).
- 2026-06-23: **Übergabe-Modul Phase A-Rest** (Auth + Verknüpfung). **Auth-Bindung** (Codex #1):
  `zhl-handover-select.php` jetzt `SecurePage` (Login-Redirect), Token an User gebunden
  (`zhl_handover_token`, Migration 003), fremde Token → 403, Sync nur POST+CSRF; Gate prüft
  zusätzlich Token-Eigentümer == `$series->UserId()`. **PostReservation** `ZhlHandoverLink`
  trägt `reference_number`/`series_id`/`instance_id` nach dem Speichern in die Übergabe-Datensätze
  nach. Verifiziert: E2E `handover-auth.spec.js` 3/3 (live: unauth→302, auth→200+Token, fremd→403),
  `verify-handover-sql.php` **10/10**. Alt-Attribut-Migrationsplan dokumentiert.
- 2026-06-23: **Übergabe-Modul Phase A gebaut** (Token-Handshake). terminplaner_ubt additiv
  erweitert (`handover_role`, `is_handover`, read-only `api/handover_slots.php` +
  `api/handover_lookup.php`, `hue`-Durchreichung in book/member). LibreBooking: Migration
  `002_zhl_handover.sql`, PreReservation-Plugin **`ZhlHandover`** (Gate blockt Speichern ohne
  bestätigte Abholung+Rückgabe für `handover_required`-Geräte), `Web/zhl-handover-{select,notify,lib}.php`,
  `config/zhl-handover.example.php`. **PULL** statt PUSH (Prod-Schreibpfad von terminplaner unberührt).
  Gate-SQL lokal verifiziert (`verify-handover-sql.php` 5/5). Runbook [HANDOVER-RUNBOOK.md](HANDOVER-RUNBOOK.md).
  Offen: Live-Cross-App-Test, PostReservation-`reference_number`-Verknüpfung, Auth-Härtung, Alt-Attribut-Migration.
- 2026-06-23: **F40 Stufe 2** (PR #3, gemergt): Permission-Plugin `ZhlCertificate` erzwingt Zertifikat-Ablauf beim Buchen (cron-frei). E2E: certuser(gültig) bucht, certexpired(abgelaufen, gleiche Gruppe) gesperrt. **Wichtig:** 5.1.0 validiert Plugin-Namen gegen `choices`-Whitelist → minimaler markierter Core-Edit in `ConfigKeys.php` nötig. Suite **21/21**.
- 2026-06-23: **Übergabe-Modul-Spec** (`SPEC-UEBERGABE.md`, Codex-gegengeprüft) — bündelt F8/F10/F16/F17/F19/F30/F34; Hooks korrigiert (PreReservation für Slot-Pflicht), eigene QR-Seite, Instanz-/Ressourcen-Datenmodell.
- 2026-06-23: **UX**: lang-overrides „Ressource→Gerät" (`config/lang-overrides.php`); Landing-Wiring-Weg dokumentiert (Domain-Wurzel, App unter /Web).
- 2026-06-23: **Vollständige E2E-Buchung** grün (`reservation.spec.js`): regulärer User bucht nicht-beschränktes Medium end-to-end. Suite jetzt **18/18**. Befund: ZHL verlangt bei jeder Buchung Pflicht-Attribute „Haftpflicht" (Checkbox) + „3 Terminvorschläge für Abholung" (Text) — manueller Workaround für die Abhol-Koordination (→ Input fürs Übergabe-Modul F8/F16).
- 2026-06-23: **PRs #1+#2 gemergt** → `zhl-main` hat Landing-Page (`Web/zhl-welcome.php`) + **ZHL-Branding** (`Web/css/zhl-theme.css`, UBT-Grün, via `css.extension.file`; Login-Button rgb(0,146,96), Test `branding.spec.js`). E2E 17/18 grün.
- 2026-06-23: **F40 Stufe 1 durchgestochen** (Gruppen-Gate, nur Config): Gruppe „Eingewiesen: Gaming-PC" → Geräte 53-56 freigegeben; `certuser@zhl.local` Mitglied. Browser-Beweis (Playwright `f40.spec.js`, 16/16 grün): Nicht-Cert-User „do not have permission", Cert-User kommt durch. Runbook `F40-RUNBOOK.md`. Erkenntnis: „beschränkt" = `resources.autoassign=0` (nur 5 Geräte).
- 2026-06-22: Fork angelegt, lokale Umgebung + DB-Kopie aufgebaut, Upgrade-Rehearsal erfolgreich, altes Repo archiviert.
- 2026-06-22: Agentischen Prozess + kanonische FEATURES.md erstellt; **Codex-Gate gelaufen** (Findings eingearbeitet: F6/F20/F22/F26/F28/F33/F35/F36 korrigiert, Querschnitt-Risiken ergänzt). PR #1 (Landing-Page) offen.
- 2026-06-22: **Neuer Feature-Request F40** (Einweisungs-/Berechtigungspflicht) aufgenommen — Gating nativ (Resource-Permissions), Badge/Zertifikat-Lifecycle = Custom.
- 2026-06-22: Lokaler Server auf docroot=`Web/` umgestellt → App unter `http://127.0.0.1:8080/` (vorher CSS-Stolperfalle ohne Trailing-Slash).
- 2026-06-22: **Codex-Gate Runde 2**: FEATURES.md bestätigt; STRATEGY §5 auf FEATURES.md umgestellt (alte Tabelle war zu optimistisch); WORKPACKAGES-Reihenfolge korrigiert (UP-1 zuerst; QW-4≠F34; CM-3 vor CM-2); F40-Caveats ergänzt (Gate nicht rückwirkend, Admins exempt); Cron-bei-SFTP-only als offene Frage.
- 2026-06-22: **Feature-Verifikations-Runde** (4 Scout-Agenten am Code): alle 40 Features belegt. Schlüsselbefunde: QR+Check-in/out und Resource-Permission-Gating sind **nativ**; echte Custom-Lücken: Personal-Übergabe-Slots (F16/17), Übergabe-Flag (F8), QR-Checkliste (F10), Accessory-Zustand (F30), Audit (F37), DSGVO (F38), Overdue-Eskalation (F34), Fuzzy-Suche (F25). F40-Konzept ([F40-KONZEPT.md](F40-KONZEPT.md)) + Arbeitspakete ([WORKPACKAGES.md](WORKPACKAGES.md)) erstellt.

# F40 Runbook — Einweisungs-/Berechtigungspflicht (Stufe 1, ohne Code)

> An der lokalen Kopie verifiziert (Playwright `f40.spec.js` grün). Reines Config über
> Gruppen + Resource-Permissions — überlebt LibreBooking-Upgrades. Hintergrund:
> [F40-KONZEPT.md](F40-KONZEPT.md).

## Ergebnis (bewiesen)
- Beschränktes Medium (Gaming-PC, `autoassign=0`): **nicht eingewiesener User** bekommt
  beim Buchen *„You do not have permission to access one or more of the requested
  resources."* — **eingewiesener User** (Cert-Gruppe) kommt am Gate vorbei.

## Schritte im Admin-UI (so macht es ZHL produktiv)
1. **Ressource beschränken:** Ressource → „Automatically grant permissions to new
   users" **aus** (= `autoassign=0`). (Die 5 betroffenen Geräte sind das bereits.)
2. **Einweisungs-Gruppe anlegen:** Admin → Groups → „Eingewiesen: <Gerät>".
3. **Gruppe berechtigen:** der Gruppe für die beschränkte(n) Ressource(n) **Full**-
   Permission geben (Group → Permissions).
4. **Einweisungstermin als buchbare Ressource** (optional, Self-Service): Ressource
   „Einweisung <Gerät>" auf einem Schedule „Einweisungen", für alle buchbar, ggf.
   `RequiresApproval`.
5. **Nach absolvierter Einweisung:** User der Einweisungs-Gruppe hinzufügen → darf sofort
   buchen. Entfernen → sofort gesperrt (wirkt bei Buchung/Änderung, **nicht** rückwirkend;
   siehe Caveats in F40-KONZEPT).

## Reproduzierbar an der Kopie
`./docs/zhl/seed-test-users.sh` legt die Gruppe „Eingewiesen: Gaming-PC", die Freigabe
für 53–56 und `certuser@zhl.local` (Mitglied) an. Test: `cd tests-e2e && npx playwright
test f40.spec.js`.

## Stufe 2 — Zertifikat-Ablauf (umgesetzt, cron-frei)
Permission-Plugin **`ZhlCertificate`** prüft bei zertifikatspflichtigen Geräten zusätzlich
zum Gruppen-Gate einen **gültigen (nicht abgelaufenen)** Eintrag in `zhl_certificate`.
Der Ablauf wird **beim Buchen** erzwungen → **kein Cron nötig**.

- Migration: `docs/zhl/migrations/001_zhl_certificate.sql` (Tabellen `zhl_certificate`,
  `zhl_certificate_required`).
- Plugin: `plugins/Permission/ZhlCertificate/ZhlCertificate.php` (Decorator über `PermissionService`).
- **Core-Edit (markiert `// ZHL:`):** `lib/Config/ConfigKeys.php` — Plugin-Name in die
  `plugins.permission`-`choices` aufgenommen (5.1.0 validiert Plugin-Namen gegen eine
  Whitelist; ohne diesen Eintrag wird das Plugin verworfen). Bei Upstream-Merge: hier prüfen.
- Aktivieren: `config.php` → `'plugins' => ['permission' => 'ZhlCertificate']`.
- Bewiesen (Playwright `f40-stufe2.spec.js`): `certuser` (gültig) bucht; `certexpired`
  (gleiche Gruppe, **abgelaufen**) wird gesperrt.

**Betrieb:** Zertifikat ausstellen = Zeile in `zhl_certificate` (user, resource, expires_at).
Verlängern = `expires_at` setzen. Entziehen = löschen / `expires_at` in Vergangenheit.
(Admin-UI dafür = optionales Folge-WP; aktuell per SQL/Skript.)

## Offen (Folge-WP)
„Zugang anfragen"-Flow (PostReservation-Plugin beim Buchen des Einweisungstermins) +
Admin-UI für Zertifikate.

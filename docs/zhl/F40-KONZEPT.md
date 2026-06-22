# F40 — Einweisungs-/Berechtigungspflicht (Konzept)

> Ziel: Bestimmte Medien sind nur buchbar, nachdem ein User eine **persönliche
> Einweisung** (Termin/Seminar) absolviert und eine **Erlaubnis/Zertifikat** erhalten
> hat. Code-verifiziert gegen LibreBooking 5.1.0. Geht durchs Codex-Gate vor Umsetzung.

## Befund (am Code belegt)
LibreBookings natives Permission-Modell trägt das Gate vollständig:
- Buchung läuft durch `PermissionValidationRule::Validate()` →
  `PermissionService::CanBookResource()` (`lib/Application/Authorization/PermissionService.php:72`),
  das gegen die **bookable-Liste** prüft (`Domain/Access/ScheduleUserRepository.php:212`),
  gespeist u.a. aus `group_resource_permissions`.
- **Entfernt man den User aus der berechtigten Gruppe, ist die Ressource sofort nicht
  mehr buchbar.** Genau der gewünschte Gate.
- Permission-Zuweisung pro Gruppe (Full/View/none) rein über die Admin-UI
  (`lib/Application/Admin/ResourcePermissionService.php`).

## „Beschränkt" = `resources.autoassign` (verifiziert an Live-Daten)
- `autoassign=1` → Ressource wird **bei Registrierung jedem neuen User automatisch
  freigegeben** (= nicht beschränkt). Aktuell **54** Ressourcen.
- `autoassign=0` → **kein** Auto-Recht; nur explizit Berechtigte (User/Gruppe) buchen
  (= beschränkt, F40). Aktuell **5** (Funkmikrofone, 4 Gaming-PCs) — die natürlichen
  Einweisungs-Kandidaten.
- Rechte liegen in `user_resource_permissions` (per-User, 13365 Zeilen live) bzw.
  `group_resource_permissions` (per-Gruppe, aktuell leer).
- **Wichtig:** Per-SQL angelegte User (Tests) bekommen die Auto-Rechte NICHT — daher
  weist `seed-test-users.sh` dem Test-User die `autoassign=1`-Ressourcen explizit zu.

## Lösung — nativ (Config + Gruppen, KEIN Code)
1. Pro einweisungspflichtigem Medium eine **Zertifikats-Gruppe** anlegen
   (z.B. „Cert: Lasercutter"). Ressource → diese Gruppe `Full`, „Alle" → `none`.
   → Hartes Gate über `group_resource_permissions`.
2. **Einweisungstermine als eigene buchbare Ressourcen** auf einem Schedule
   „Einweisungen" (für alle buchbar) → Self-Service-Terminvereinbarung.
   Optional `RequiresApproval`, damit ZHL den Termin bestätigt.
3. Manuelles Freischalten: ZHL trägt den User nach der Einweisung in die
   Zertifikats-Gruppe ein → sofort buchbar. (Für „läuft nie ab" genügt das komplett.)

## Lösung — Custom (so dünn wie möglich, upgrade-sicher als Plugin/Job)
Nur nötig, wenn Zertifikate **ablaufen** sollen oder ein automatischer Anfrage-Flow
gewünscht ist (Gruppen kennen kein „gültig bis"):
4. Eigene Tabelle `zhl_certificate(user_id, group_id, granted_at, expires_at, revoked)`
   + **Cron-Job** (`Jobs/`), der abgelaufene Zertifikate findet und den User aus der
   Zertifikats-Gruppe entfernt (Auto-Entzug). Keine Kern-Datei geändert.
5. **„Zugang anfragen"-Flow** als `PostReservation`-Plugin: Buchung der
   Einweisungstermin-Ressource → Plugin mailt ZHL bzw. legt `pending` in
   `zhl_certificate` an. Nach Einweisung trägt ZHL in die Gruppe ein + setzt
   `expires_at`.
6. *Optional* `plugins/Permission/ZhlCert`-Dekorator für Echtzeit-Ablaufprüfung
   (`CanBookResource` zusätzlich gegen `expires_at`). Nur additiv; das Gruppen-Gate
   bleibt primär (robuster).

## Caveats (Codex-Gate)
- Das Gruppen-Gate wirkt **bei Buchung/Änderung**, **nicht rückwirkend**: bestehende
  Reservierungen werden durch Gruppenentzug/Ablauf **nicht automatisch storniert** →
  bei Bedarf Custom-Job, der laufende/künftige Buchungen behandelt.
- **Admins sind vom Permission-Check ausgenommen** — Gate gilt für normale User.
- Ablauf/Gültigkeit ist nicht nativ (siehe Custom-Teil).
- **Betriebsprozess klären:** Wer trägt Gruppenmitgliedschaft ein/entzieht sie?

## Empfehlung / Reihenfolge
- **Stufe 1 (jetzt, ohne Code):** Gruppen-Gate (1) + Einweisung-als-Ressource (2)
  konfigurieren und an der lokalen Kopie durchspielen.
- **Stufe 2 (Custom-PR):** Zertifikat-Lifecycle (4) + Anfrage-Plugin (5), wenn
  Ablauf/Automatik gebraucht wird.

# Übergabe-Modul Phase A — Setup & Deploy

> Gebaut 2026-06-23. Strukturierte Abhol-/Rückgabe-Termine statt Freitext-Vorschlägen.
> Slots liefert **terminplaner_ubt** (Google-iCal); LibreBooking speichert nur die
> Verknüpfung + erzwingt sie beim Buchen. Lokal SQL-verifiziert (`verify-handover-sql.php`,
> 5/5). Cross-App-Flow braucht Live-Verifikation vor Prod (terminplaner läuft lokal nicht).

## Architektur (Token-Handshake)
Henne-Ei (Reservierung braucht Übergabe, Übergabe braucht Reservierung) wird über ein
**handover_token** gelöst:
1. Nutzer öffnet **`Web/zhl-handover-select.php`** → Token wird erzeugt.
2. Er bucht **Abholung + Rückgabe** über terminplaner (Links auf `member.php` tragen
   `hue=<token>&hue_type=pickup|return` → Marker `[HUE:<token>:<typ>]` landet in der Notiz).
3. „Status aktualisieren" zieht die Buchungen via **`api/handover_lookup.php`** und schreibt
   sie bestätigt nach `zhl_booking_handover` (Transaktion + Unique-Constraint).
4. Nutzer trägt das Token ins Reservierungs-Attribut **`handover_token`** ein.
5. **PreReservation-Plugin `ZhlHandover`** blockt das Speichern, bis Abholung **und**
   Rückgabe bestätigt sind — aber nur für Geräte mit Attribut **`handover_required`** (F8).

Bewusst **PULL** (LibreBooking zieht aus terminplaner): der produktive Schreibpfad von
terminplaner (`confirm_booking`) bleibt unangetastet — nur additive Migration + read-only APIs.

## terminplaner_ubt (Repo-los, Deploy via dessen `deploy.sh`)
Geänderte/neue Dateien:
- `migrate.php` — `team_members.handover_role ENUM('primary','backup')` + `meeting_types.is_handover`.
- `api/handover_slots.php` (neu, read-only) — verfügbare Slots, **primary vor backup**.
- `api/handover_lookup.php` (neu, read-only) — Buchungen je Token + Typ (aus Marker geparst).
- `book.php` / `member.php` — strikt validiertes `hue`/`hue_type` durchreichen (nur additiv).

Setup dort:
1. Deploy, dann **`migrate.php`** aufrufen (legt die zwei Spalten an; Re-Run harmlos).
2. Im Admin je Hilfskraft/Team-Mitglied einen **Meeting-Type „Medienübergabe"** mit
   `is_handover=1` anlegen und **`handover_role`** setzen (Hilfskraft=`primary`, Team=`backup`).
3. API-Key = `settings.crossbook_api_key` (read-only Endpunkte nutzen ihn).

## LibreBooking (Fork `zhl-main`)
1. **Migration** `docs/zhl/migrations/002_zhl_handover.sql` einspielen (zhl_booking_handover
   + zhl_handover_check(_item)).
2. **Config** `config/zhl-handover.example.php` → `config/zhl-handover.php` kopieren,
   `terminplaner_base_url` + `terminplaner_api_key` (= crossbook_api_key) eintragen
   (gitignored, NIE committen).
3. **Custom-Attribute** im Admin anlegen:
   - `handover_required` — Kategorie **Ressource**, Checkbox; bei übergabepflichtigen Geräten setzen.
   - `handover_token` — Kategorie **Reservierung**, Text; Anzeige „Übergabe-Token".
4. **Plugin aktivieren:** config `plugins.prereservation = 'ZhlHandover'`
   (Name steht in der `choices`-Whitelist in `lib/Config/ConfigKeys.php` — 5.1.0-Pflicht).
5. Optional: terminplaner/Cron ruft `Web/zhl-handover-notify.php?token=…` (X-API-Key) zum
   Auto-Abgleich; sonst genügt der „Status aktualisieren"-Button.

## Codex-Gate (2026-06-23) — Befunde eingearbeitet
Codex bestätigte: Queries schema-kompatibel, `php -l` sauber, PreReservation ist der richtige
Hook. **Sofort gefixt:** Fehlermeldung als `string[]` statt String (Template iteriert `$Errors`);
`zhl-handover-notify.php` nur noch Header-Auth (kein `?key=`-Leak in Logs); Unique-Constraint-
Scope „pro Reservierung, Kapazität 1" explizit dokumentiert. **Volle Findings:** `codex-findings.md`.

## Offen / vor Prod zu prüfen (ehrlich)
- **HÖCHSTES RISIKO (Codex): Auth-Bindung von `zhl-handover-select.php`.** Aktuell auth-light
  (Sync per GET, keine Login-/Owner-Bindung). Token ist zwar 128-bit-zufällig (nicht ratbar),
  aber wer es kennt, kann Status sehen/syncen. Vor Prod: an LB-Login + Reservation-Owner binden,
  Sync auf POST+CSRF. Das serverseitige Gate (Plugin) ist davon unberührt und bleibt wirksam.
- **Live-Cross-App-Test**: terminplaner lokal nicht lauffähig → Slot-Liste, Buchung mit Marker,
  Lookup und Sync end-to-end auf einer Test-/Staging-Instanz verifizieren.
- **Auth-Härtung** von `zhl-handover-select.php` (aktuell auth-light wie `zhl-welcome.php`;
  Server-Gate ist das Plugin, aber die Seite sollte an den LB-Login gebunden werden).
- **Reference-Verknüpfung**: PostReservation-Schritt, der `reference_number` nach dem Speichern
  in `zhl_booking_handover` nachträgt (Phase A liefert das Gate; Verknüpfung ist Folge-Task).
- **Migration der Alt-Attribute** „Haftpflicht" + „3 Terminvorschläge" → schrittweise durch
  `handover_token` ersetzen (Bestandsbuchungen nicht brechen).

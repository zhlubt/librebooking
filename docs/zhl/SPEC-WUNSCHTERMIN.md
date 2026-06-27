# SPEC-WUNSCHTERMIN — Wunschtermin-Anfrage (Feature B)

**Stand:** 2026-06-27 · gebaut & live verifiziert auf media.zhl-ubt.de (Staging)

## Problem
Wenn ein Nutzer ein Gerät/Bundle nicht buchen kann (kein freier Abhol-/Einführungs-Slot,
Gerät bis zum letzten buchbaren Termin verliehen …), brach er bisher ab oder schrieb eine Ad-hoc-Mail.

## Entscheidungen (mit dem Nutzer abgestimmt)
- **Anfrage-only**: die Anfrage hält das Gerät NICHT hart. „Reserviert" = vorgemerkt, nicht gesperrt.
- **Admin bucht per 1-Klick** im Namen des Anfragenden (umgeht Pflicht-Slot/Mindestfrist) — oder lehnt ab.
- **Formular**: Wunsch-Zeitraum (von/bis) + Projekt-Titel + Freitext-Nachricht.
- **Mindestfristen** bleiben für den normalen Buchungsweg; nur der Admin trägt kurzfristig ein.
- **Mail** ans Medien-Team test-sicher über die vorhandene Hauspost-Config `medien_email`
  (Staging → ki-lehre@uni-bayreuth.de; Echtbetrieb → zhlmedien@uni-bayreuth.de).

## Datenmodell
`zhl_termin_request` (Migration 024): user_id, kind(single|bundle), resource_id|bundle_id, label,
desired_start/desired_end (UTC), project_title, message, status(open|booked|declined|cancelled),
admin_note, reference_number, created_at, handled_at, handled_by.

## Fluss
1. **Nutzer** öffnet `zhl-termin-anfrage.php?rid=<id>` (bzw. `?bundle=<id>`) — verlinkt aus der
   Buchungsseite (prominent, wenn `blocked`; sonst als Fußnote). Formular → Anfrage anlegen +
   Mail ans Team (CC Nutzer) → Bestätigung.
2. **„Meine Buchungen"** zeigt offene Anfragen mit **Zurückziehen** (gated: `status='open' AND user_id`).
3. **Admin** (`zhl-termin-anfrage-admin.php`, Dashboard → „📅 Anfragen"): Liste offener Anfragen.
   - *Als Admin buchen* (nur Einzelgerät): `ZhlReservationFacade(requester_uid, …)` mit Admin-Session
     → Reservierung im Namen des Anfragenden (09:00–17:00 im Wunsch-Zeitraum) → status=booked + Ref +
     Mail an Nutzer. Bundle → Deep-Link zur manuellen Bundle-Buchung.
   - *Ablehnen* (mit Grund) → status=declined + Mail an Nutzer.

## Sicherheit / Robustheit
- Statusübergänge nur aus `status='open'` (kein Doppel-Buchen/Race nach Get()).
- Nutzer-Storno zusätzlich auf `user_id` gegated (kein Stornieren fremder Anfragen — live getestet).
- CSRF auf allen POST-Forms; Admin-Seite Admin-Rollen-gated; Ausgabe escaped.
- Mails in try/catch (kippen nie eine Aktion). `Create()` nutzt `ExecuteInsert()` (Database hat kein LastInsertId).

## Verifiziert (live, end-to-end)
Anfrage anlegen → erscheint in „Meine Buchungen" + DB → Admin bucht (Reservierung Owner=Anfragender,
09–17 Uhr) → Nutzer-Storno → Admin-Ablehnung → Gate (308 kann 309s Anfrage nicht stornieren). Testdaten entfernt.

## Offen / später
- 1-Klick-Buchen auch für Bundles (derzeit manueller Deep-Link).
- Optional: Buchungs-Zeitfenster im Admin vor dem Buchen feinjustieren (statt fix 09–17 Uhr).

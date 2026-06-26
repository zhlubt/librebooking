# SPEC-CANCEL (D) — Stornierung einer Medien-Ausleihe

Status: in Arbeit (2026-06-25). Vom Nutzer beauftragt: „Button + ganze Logik-Pipeline des
Stornierens; dann muss auch die Ausleihe, die Einführung und die Rückgabe storniert werden."

## Ziel
Auf der Buchungs-Detailseite (`zhl-booking-detail.php`, lädt per `reference_number`, OWNER-Sicht)
einen **„Ausleihe stornieren"-Button**, der in EINER Aktion:
1. die native LibreBooking-Reservierung löscht (Gerät(e) werden frei),
2. den **Einführungstermin** im Terminplaner storniert (falls gebucht),
3. den **Abholtermin** im Terminplaner storniert (falls gebucht),
4. die lokalen `zhl_booking_handover`-Zeilen (pickup/einf/return) entfernt,
5. eine offene `zhl_cert_confirmation` (pending) für diese Einführung verwirft.
(Die Rückgabe ist kein eigener Terminplaner-Slot — nur eine handover-Zeile → entfällt mit 4.)

## Terminplaner-Seite (terminplaner_ubt)
**Neu: `api/cancel_slot.php`** (Gegenstück zu `book_slot.php`). X-API-Key-Auth, POST
`{booking_id, reason?}`. Nutzt denselben Storno-Pfad wie das Admin-Panel
(`admin.php?action=cancel_booking`): `bookings.status='cancelled'`, sequence++, CANCEL-.ics an
Gast+Team, Cross-Book-Peers (TX/TO) den Slot freigeben. **Idempotent** (bereits storniert / nicht
gefunden → 200 `already_cancelled:true`), damit die Pipeline gefahrlos erneut versuchen kann.

## LibreBooking-Seite
**Ownership:** unverändert über die OWNER-Sicht — die Detailseite lädt nur eigene Buchungen; ein
fremdes `ref` landet ohnehin im Redirect. Storno serverseitig erneut gegen die geladene eigene
Reservierung geprüft.

**Cancel-Policy:** Storno nur erlaubt, solange die Reservierung **noch nicht begonnen** hat
(reservierter Beginn = Abholtag > jetzt). Laufende/vergangene Ausleihen → kein Button, Server lehnt
ab (Gerät ist physisch draußen → manueller Prozess).

**Reihenfolge (kritisch):**
1. Handover-Zeilen zu `reference_number` AUSLESEN (für die Terminplaner-booking_ids) — VOR dem Löschen.
2. **Native Reservierung löschen** (`ReservationRepository::LoadByReferenceNumber` → `ExistingReservationSeries::Delete($user)` → `ReservationRepository::Delete`). Schlägt das fehl → Abbruch, klare Meldung, NICHTS sonst verändert (das Gerät-Freigeben ist die Kern-Aktion).
3. **Terminplaner-Slots stornieren** (best effort): je handover-Zeile mit `terminplaner_booking_id` →
   `tpRequest('POST','/api/cancel_slot.php',[],['booking_id'=>…,'reason'=>'Ausleihe storniert'])`.
   Fehler ⇒ Warnung sammeln (Termin bleibt ggf. stehen, Team sieht es), Storno bleibt gültig.
4. **Lokale Zeilen aufräumen:** `zhl_booking_handover` zu `reference_number` löschen; offene
   `zhl_cert_confirmation` (user + Einführungs-Gerät, status='pending') → `status='rejected'`.
5. Redirect zur Übersicht mit Erfolgsmeldung (+ etwaige Warnungen).

**CSRF/POST:** wie `ZhlBookPage` — `ZhlBookingDetailPage` bekommt einen POST-Zweig
(`action=cancel`), Token-Schutz analog der bestehenden ZHL-Seiten.

## Bekannte Grenzen
- TOCTOU/Race wie üblich vorbestehend.
- cert_confirmation hat keine `reference_number` → Match über user + (cert_type des Einführungs-
  Geräts) + status='pending'. Best effort; im Zweifel bleibt eine pending-Zeile stehen (harmlos).
- Bundle: mehrere Geräte hängen an EINER Reservierung (primary+additional) → ein Delete entfernt alle.
  Terminplaner-Einführung/Abholung sind je Buchung (nicht je Gerät) → eine cancel_slot-Runde genügt.

## Verifikation
- cancel_slot.php: idempotenter Storno gegen eine Test-Buchung (already_cancelled-Pfad).
- LibreBooking: Storno einer zukünftigen Test-Buchung → Reservierung weg, Gerät wieder frei,
  Terminplaner-Buchung cancelled, handover-Zeilen weg, cert_confirmation rejected. Codex-Review vor Deploy.

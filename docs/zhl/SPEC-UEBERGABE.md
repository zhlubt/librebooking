# Spec — ZHL Übergabe-Modul (Personal-Ausgabe & -Rücknahme)

> Bündelt die echten Custom-Lücken F8/F10/F16/F17/F19/F30/F34 in EIN Modul.
> Status: Entwurf (geht durchs Codex-Gate, bevor Code entsteht). Kein Code = keine
> Festlegung; Datenmodell & Reihenfolge sind Diskussionsgrundlage.

## 1. Problem & heutiger Ist-Zustand
Bestimmte Medien (Kameras, Gaming-PCs, Funkmikro) werden **persönlich vom ZHL-Personal
ausgegeben und zurückgenommen**. LibreBooking kennt **keine Personal-Verfügbarkeit** und
keinen Übergabe-Workflow (verifiziert: kein staff/shift/slot/handover im Schema).

**Heutiger Workaround (an Live-Daten gefunden):** Pflicht-Custom-Attribute bei jeder
Buchung — „Ich besitze eine Haftpflichtversicherung" (Checkbox) + „3 Terminvorschläge
für Abholung" (Freitext). Das Personal stimmt den Termin danach **manuell per Mail** ab.
Das Modul soll diesen manuellen Schritt strukturieren.

## 2. Ziel
1. **Strukturierte Abhol-/Rückgabe-Termine** statt Freitext-Vorschlägen: User wählt aus
   echten, vom Personal freigegebenen Slots (F16/F17).
2. **QR-verifizierte Übergabe** mit Checkliste (Zubehör) + Zustandserfassung (F10/F30).
3. **Overdue-Erkennung** für nicht zurückgegebene Geräte + Eskalation (F34).
4. **Selbstbedienung vs. Personal-Übergabe** pro Ressource steuerbar (F8).

## 3. Was NATIV bleibt (wiederverwenden, nicht nachbauen)
| Baustein | Native LB-Funktion |
|---|---|
| QR-Code je Ressource | `ManageResourcesPresenter::PrintQRCode` (BaconQrCode) |
| Check-in/Check-out (Zeitstempel) | `enable_check_in`, `Ajax/ReservationCheckinPage` |
| Zubehör mit Menge je Ressource | `accessories` + `resource_accessories` |
| Genehmigung pro Ressource | `RequiresApprovalRule` |
| Reminder-Mails | `Jobs/sendreminders.php` (braucht Cron-Runner!) |
| Berechtigung (Einweisung) | F40 (group_resource_permissions) |

## 4. Was CUSTOM gebaut wird
Alle ZHL-Tabellen mit Präfix `zhl_`, eigene Pages `Web/zhl-*.php` + eigene Templates,
Jobs in `Jobs/zhl_*`, eigene Migration in `docs/zhl/migrations/`.

> **Core-Edit-Ehrlichkeit (Codex-Gate):** „Kein Core-Edit" gilt **nur**, wenn wir mit
> **externen ZHL-Seiten** arbeiten (Slot-Wahl/Checkliste als eigene Seiten vor/nach der
> Reservierung). Für *nahtlose* Integration in den LibreBooking-Buchungsdialog oder das
> native QR-Ziel ist ein Template-/Page-Edit nötig. Entscheidung pro Feature treffen.

### 4.0 Die richtigen Hook-Punkte (Codex-korrigiert)
- **`plugins.prereservation`** — VALIDIEREN/BLOCKIEREN **vor** dem Speichern (Slot-Pflicht,
  Slot-Kapazität, Status, Race-Checks). *Pflicht für die Slot-Logik* — PostReservation ist
  zu spät (Buchung wäre schon angelegt).
- **`plugins.postreservation`** — Nachlauf: Datensatz anlegen/benachrichtigen. Bietet
  Add/Update/Delete/Approve **und `CreatePostCheckinService`/`CreatePostCheckoutService`**
  (für Übergabeprotokoll relevanter als „bei Buchung").
- **QR:** der native Router (`Pages/ResourceQRRouterPage.php`) führt auf die **Reservierung**,
  nicht auf eine Übergabe-Checkliste → wir erzeugen **eigene ZHL-QRs/URLs** auf
  `Web/zhl-handover-check.php` (eigene Auth/CSRF/Permission), statt den Core-Router zu patchen.

### 4.1 Datenmodell (mit terminplaner-Anbindung)
> **Personal-Slots werden NICHT neu modelliert** — die liefert `terminplaner_ubt`
> (Google-iCal je Team-Mitglied, siehe §4.2). LibreBooking-Seite speichert nur die
> Verknüpfung zur dort gewählten Übergabe + das Protokoll.
```
zhl_booking_handover   -- Übergabe je RESSOURCE/INSTANZ, nicht nur Reservierung
  id, series_id, reservation_instance_id, resource_id, reference_number,
  type ENUM('pickup','return'),
  terminplaner_booking_id VARCHAR(20),   -- Buchung in terminplaner_ubt.bookings.id
  staff_member_id INT,                   -- terminplaner team_members.id (wer übergibt)
  staff_role ENUM('primary','backup'),   -- Hilfskraft=primary, ZHL-Team=backup
  scheduled_start_utc DATETIME, scheduled_end_utc DATETIME,
  status ENUM('requested','confirmed','done'), created_at, updated_at,
  UNIQUE(reservation_instance_id, resource_id, type)   -- gegen Doppelbelegung

zhl_handover_check     -- Kopf der QR-Verifikation (Aus-/Rückgabe)
  id, series_id, reservation_instance_id, resource_id, type,
  checked_by_user_id, condition_note TEXT, signature_name, created_at
zhl_handover_check_item -- NORMALISIERT statt JSON (Audit/Reporting)
  id, check_id FK, accessory_id, state ENUM('ok','missing','damaged'), note

-- F8 (Übergabe nötig?): als Resource-Custom-Attribute (ENTSCHIEDEN), nativ/upgrade-sicher.
```
> **Codex-Korrekturen drin:** pro Ressource/Instanz (Serien + Multi-Resource), UTC-Datetimes,
> normalisierte Checklisten-Items, Unique-Constraint + Transaktion gegen Races (ENTSCHIEDEN).

### 4.2 Personal-Verfügbarkeit über terminplaner_ubt (F16/F17)
**Wir bauen keine neue Slot-Engine — wir nutzen die bestehende App
`~/Documents/vsc/vscode/terminplaner_ubt`.** Dort gibt jedes ZHL-Team-Mitglied seine
freien Übergabe-Slots über einen **Google-Kalender (iCal-Feed, `team_members.ical_url`)**
an (Events mit Prefix `FREI`; `BÜRO/NURBÜRO` = vor-Ort-Filter). Buchung erzeugt einen
Eintrag in `bookings` + iCal-Mail.

**Rollen (vom ZHL gefordert):**
- **Studentische Hilfskraft** = *primär* für Medienübergabe → ihre Slots werden zuerst angeboten.
- **ZHL-Team** = *Backup* (kann vor Ort einspringen) → nur wenn keine Hilfskraft-Slots passen.

**Nötige Erweiterung in terminplaner_ubt** (kleines Feature dort, kein LibreBooking-Core):
- `team_members.handover_role ENUM('primary','backup')` (oder per `meeting_type`), plus
  einen Meeting-Type „Medienübergabe".
- Slot-Auswahl bietet **primary** zuerst, **backup** nur als Alternative.

**Anbindung LibreBooking → terminplaner:**
- Übergabepflichtige Geräte: Resource-Custom-Attribute `handover_required` (F8, entschieden).
- **Slot-Auswahl als externe ZHL-Seite** nach der Reservierung (kein Core/Template-Edit):
  Nutzer wird zu terminplaner geleitet (Kontext: reservation_instance + resource), wählt
  **Pickup- UND Return-Slot** (Return sofort wählen — entschieden, kein „später").
- terminplaner meldet die Buchung via API (`api/notify_booking.php`) zurück → Eintrag in
  `zhl_booking_handover` (mit `terminplaner_booking_id`, `staff_member_id`, `staff_role`).
- **PreReservation-Plugin** erzwingt: bei `handover_required` muss die Übergabe terminiert
  sein, sonst blockt es das Speichern; Kapazität/Race über Unique-Constraint + Transaktion.

### 4.3 QR-Checkliste (F10/F30)
- **Eigene ZHL-QRs/URLs** auf `Web/zhl-handover-check.php` (nicht der native QR-Router, der
  auf die Reservierung zeigt). Eigene Auth/CSRF/Permission.
- Seite lädt das Ressourcen-Zubehör über `Domain/Access/AccessoryRepository` (nicht über die
  Check-in-Ajax-Seite) → Häkchen ok/fehlt/beschädigt + Zustandsnotiz →
  `zhl_handover_check` + `zhl_handover_check_item`. Optional Accessory um `condition`/
  `serial_number` erweitern (F30). Optional zusätzlich nativen Check-in/out auslösen.

### 4.4 Overdue (F34)
- Job `Jobs/zhl_overdue.php` (analog `sendmissedcheckin`): nutzt
  `reservation_instances.checkout_date` + ZHL-return-check + **Grace Period** → Reservierungen
  mit überschrittener Rückgabe ohne Return-Check → Eskalations-Mail (mehrstufig) + optional
  User-Sperre. **Voraussetzung: Cron-Runner** (offen, siehe PROGRESS).

### 4.5 Trigger / Reihenfolge
- **PreReservation-Plugin** (blockt): erzwingt Slot-Wahl + Kapazität für übergabepflichtige Geräte.
- **PostReservation/PostCheckout-Plugin** (Nachlauf): `zhl_booking_handover` anlegen/aktualisieren,
  Personal benachrichtigen, Übergabeprotokoll verknüpfen.

## 5. Phasen
- **A — terminplaner-Anbindung** ersetzt die Freitext-Attribute (F16/F17/F8): Übergabe-
  Slot-Wahl (Pickup+Return) über terminplaner_ubt + Rollen (Hilfskraft primär/Team Backup) +
  Rückmeldung in `zhl_booking_handover`. **GEBAUT + live verifiziert.**
- **B — QR-Checkliste + Zustand** (F10/F30). **GEBAUT (2026-06-23), siehe §8.**
- **C — Overdue/Eskalation** (F34, braucht Cron-Runner). **GEBAUT (2026-06-23), siehe §9.**

## 6. Entscheidungen (ZHL, 2026-06-23)
- **F8:** Resource-Custom-Attribute ✅
- **Slot-Engine:** terminplaner_ubt (Google-iCal) ✅ — *die frühere „Slots pro Personal/Gruppe/
  Schedule + Kapazität"-Frage entfällt damit* (Erklärung unten).
- **Return-Slot:** sofort mitwählen ✅ (kein „später/deferred")
- **Rollen:** Hilfskraft = primär, ZHL-Team = Backup ✅
- **Zugriff** (Slots/Checklisten/Eskalationen): alle Admins = ganzes ZHL-Team, **nicht** User ✅
- **Migration:** alte Pflicht-Attribute (Haftpflicht, 3 Terminvorschläge) schrittweise durch
  Slot-Wahl ersetzen ✅
- **Race-Conditions:** Unique-Constraint + Transaktion ✅
- **Cron-Runner:** einrichten ✅ (eigener Task, Voraussetzung für Phase C/Reminder)

### Erklärung „Slots pro Personal/Gruppe/Schedule + Kapazität"
Die ursprüngliche Frage war, *woran* ein Übergabe-Slot hängt: an einer **Person** (dieser
Mitarbeiter ist Di 14–15 Uhr verfügbar), an einer **Ressourcengruppe** (Slot gilt für „alle
Kameras") oder an einem **Schedule**; und ob ein Slot **mehrere** Übergaben gleichzeitig
fasst (Kapazität). **Mit terminplaner_ubt ist das beantwortet:** Slots hängen an der
**Person** (deren Google-Kalender), und **ein Slot = ein Termin** (Kapazität 1, der Kalender
verhindert Doppelbelegung). Kein eigenes Kapazitätsmodell nötig.

> Codex-Gate (`codex-findings.md`) eingearbeitet: PreReservation-Hook, eigene QR-Seite,
> Instanz-/Ressourcenbezug, normalisierte Checklisten, ehrliche Core-Edit-Abgrenzung.

## 7. Phase A — GEBAUT (2026-06-23)
Umsetzung des Token-Handshake (löst Henne-Ei). **Detail-Runbook: [HANDOVER-RUNBOOK.md](HANDOVER-RUNBOOK.md).**
Lokal SQL-verifiziert (`verify-handover-sql.php` 5/5); Cross-App-Flow vor Prod live testen.

**terminplaner_ubt** (additiv, Schreibpfad unberührt):
- `migrate.php`: `team_members.handover_role` + `meeting_types.is_handover`.
- `api/handover_slots.php` (read-only, primary vor backup), `api/handover_lookup.php`
  (Buchungen je Token, Typ aus Marker `[HUE:<token>:<typ>]`).
- `book.php`/`member.php`: strikt validiertes `hue`/`hue_type` durchreichen.

**LibreBooking** (Fork `zhl-main`):
- Migration `migrations/002_zhl_handover.sql` (zhl_booking_handover token-keyed + check-Tabellen).
- Plugin `plugins/PreReservation/ZhlHandover/` (Gate: blockt Speichern ohne bestätigte
  Abholung+Rückgabe, nur für `handover_required`-Geräte). Whitelist-Eintrag in `ConfigKeys.php`.
- `Web/zhl-handover-select.php` (Assistent), `Web/zhl-handover-notify.php` (Push-Trigger),
  `Web/zhl-handover-lib.php` (Pull + transaktionaler Upsert), `config/zhl-handover.example.php`.

**Bewusste Entscheidung:** PULL statt PUSH — LibreBooking zieht aus terminplaner, dessen
produktiver Buchungs-Schreibpfad bleibt unverändert (CLAUDE.md-Disziplin: Prod nicht destabilisieren).

**Phase A-Rest GEBAUT (2026-06-23):**
- **Auth-Bindung** (Codex #1): `zhl-handover-select.php` ist `SecurePage`; Token an User
  gebunden (`zhl_handover_token`, Migration 003), fremde Token → 403, Sync nur POST+CSRF;
  Gate prüft zusätzlich Token-Eigentümer == `$series->UserId()`. E2E 3/3 + SQL 10/10, live verifiziert.
- **PostReservation-Verknüpfung**: `plugins/PostReservation/ZhlHandoverLink` trägt nach dem
  Speichern `reference_number`/`series_id`/`reservation_instance_id` in `zhl_booking_handover` +
  `zhl_handover_token` nach (fehlertolerant).
- **Alt-Attribut-Migration**: Plan dokumentiert (HANDOVER-RUNBOOK, „schrittweise").

**Offen vor Prod:** Live-Cross-App-Test (terminplaner lokal nicht lauffähig); lokale Vendor-Assets
für die Assistent-Seite statt CDN (Minor).

## 8. Phase B — GEBAUT (2026-06-23): QR-Checkliste + Zustand (F10/F30)
QR-verifizierte Aus-/Rückgabe mit Zubehör-Checkliste + Zustandserfassung. **Nur fürs ZHL-Team**
(Admin-Rollen), nicht für User (Entscheidung §6). Schließt zugleich Codex' „keine Admin-/
Betriebsansicht"-Lücke.
- Migration `migrations/004_zhl_handover_check_ext.sql`: `zhl_handover_check_item.label`
  (Snapshot/ad-hoc) + `zhl_handover_check.overall_condition`.
- **`Web/zhl-handover-check.php`** (SecurePage, Admin-only): lädt das Ressourcen-Zubehör
  (`resource_accessories ⋈ accessories`) als Checkliste (ok/fehlt/beschädigt + Notiz), erlaubt
  ad-hoc-Positionen, erfasst Gesamtzustand + Zustandsnotiz + Unterschrift; schreibt
  `zhl_handover_check` (+ `_item`) in einer Transaktion und setzt die Übergabe auf `done`.
  **Eigene Seite, NICHT** der native QR-Router (der auf die Reservierung zeigt).
- **`Web/zhl-handover-qr.php`** (Admin-only): erzeugt ZHL-eigenen QR (BaconQrCode `GDLibRenderer`)
  → zeigt auf die Checkliste, nicht auf die Reservierung.
- **`Web/zhl-handover-admin.php`** (Admin-only): Betriebsübersicht offener/terminierter/erledigter
  Übergaben mit Links zu Checkliste + QR (Status-Filter).
- Lokale Vendor-Assets (kein CDN). E2E `tests-e2e/handover-check.spec.js` **4/4** (User→403,
  Render+Zubehör, Absenden→`done`, QR→PNG); Fixtures `seed-phase-b.sql`. **Suite 28/28.**

**Offen (Phase B-Rest, optional):** Foto-Anhang zum Zustand; nativen Check-in/out zusätzlich
auslösen; Seriennummern je Komponente (F30 tiefer).

## 9. Phase C — GEBAUT (2026-06-23): Overdue-/Rückgabe-Eskalation (F34)
Findet überfällige RÜCKGABEN (`zhl_booking_handover` type='return', noch nicht `done`,
geplantes Ende + Toleranz überschritten) und eskaliert mehrstufig per E-Mail.
- Migration `005_zhl_overdue_notice.sql`: protokolliert versandte Eskalationsstufen
  (Unique `handover_id+stage` → keine Doppel-Mails).
- **`Jobs/zhl_overdue.php`** (CLI, JobCop): Toleranz 24 h, Stufen bei 1/3/7 Tagen überfällig.
  **Zeitbasiert** — bei spät entdeckten Fällen wird direkt die passende Stufe gesendet (kein
  1→2→3-Spam). Empfänger über `zhl_handover_token.user_id`. Mailversand pro Datensatz
  abgesichert (Fehlschlag blockiert weder Batch noch protokolliert „versandt" → Retry).
  **Optionales User-Sperren** in der Schlussstufe (`ZHL_OVERDUE_LOCK_USER`, Default AUS).
- In den Cron-Runner `Web/zhl-cron.php` eingetragen (Container hat keinen Cron).
- Verifiziert: `verify-handover-overdue.php` **6/6** (Toleranz, done-Ausschluss, Stufen 1/3,
  Doppel-Schutz) + realer Job-Lauf end-to-end (8 Tage → Stufe 3, Mail, Notice protokolliert).

**Offen (Phase C-Rest, optional):** Eskalations-Mails als Smarty-Template + Übersetzung
(aktuell Inline-HTML); ZHL-Team-CC; Konfig-UI für Toleranz/Stufen.

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

### 4.1 Datenmodell (Codex-überarbeitet)
```
zhl_handover_slot      -- vom Personal freigegebene Übergabe-Fenster
  id, staff_user_id, scope ENUM('resource','resource_group','schedule'), scope_id,
  type ENUM('pickup','return'), start_datetime DATETIME, end_datetime DATETIME,
  timezone, capacity INT, status ENUM('open','full','cancelled')
  -- UTC-Konvention wie LB; Scope eindeutig (nicht schedule_id|resource_id?)

zhl_booking_handover   -- Übergabe je RESSOURCE/INSTANZ, nicht nur Reservierung
  id, series_id, reservation_instance_id, resource_id, reference_number,
  type ENUM('pickup','return'), slot_id FK zhl_handover_slot,
  status ENUM('requested','confirmed','done','deferred'), created_at, updated_at,
  UNIQUE(reservation_instance_id, resource_id, type)   -- verhindert Doppelbelegung

zhl_handover_check     -- Kopf der QR-Verifikation
  id, series_id, reservation_instance_id, resource_id, slot_id,
  type, checked_by_user_id, condition_note TEXT, signature_name, created_at
zhl_handover_check_item -- NORMALISIERT statt accessories_json (Audit/Reporting)
  id, check_id FK, accessory_id, state ENUM('ok','missing','damaged'), note

-- F8 (Übergabe nötig?): bevorzugt als Resource-Custom-Attribute (nativ, upgrade-sicher);
-- eigene Tabelle nur bei komplexeren Regeln.
```
> **Codex-Korrekturen:** Übergabe ist pro Ressource/Instanz (Serien + Multi-Resource!),
> nicht pro `reference_number`. `start/end_datetime` + UTC statt `date/start_time`.
> Checklisten-Items **normalisiert** (nicht JSON) für „was fehlt/beschädigt"-Auswertung.
> Slot-Kapazität braucht **Transaktion + Unique-Constraint** gegen Races.

### 4.2 Personal-Verfügbarkeit (F16/F17)
- Personal pflegt Slots in `zhl_handover_slot` (eigene Admin-Page `Web/zhl-handover-admin.php`;
  Zugriff: eigene Staff-Gruppe).
- **Slot-Auswahl NICHT im nativen Buchungsdialog** (das wäre Core/Template-Edit, Codex).
  Stattdessen **externe ZHL-Seite** direkt nach der Reservierung (`Web/zhl-pickup.php`):
  User wählt Pickup-Slot (+ Return-Slot oder „später" → Reminder). Ein
  **PreReservation-Plugin** erzwingt für übergabepflichtige Geräte, dass ein Slot gewählt
  wurde (blockt sonst), und prüft Kapazität (Transaktion).

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
- **A — Slot-Auswahl** ersetzt Freitext-Attribute (F16/F17/F8). Größter Nutzen, mittlerer Aufwand.
- **B — QR-Checkliste + Zustand** (F10/F30).
- **C — Overdue/Eskalation** (F34, abhängig vom Cron-Runner).

## 6. Offene Entscheidungen (für ZHL)
- F8 via Resource-Custom-Attribute (bevorzugt) **oder** eigene Tabelle?
- Slots pro **Personal** / **Ressourcengruppe** / **Schedule**? Kapazität je Slot?
- Return-Slot sofort wählen oder „später" (wie heute deferred)?
- **Welche Gruppen** dürfen Slots pflegen / Checklisten abschließen / Eskalationen sehen?
- **Migrationsplan** für laufende Buchungen + die alten Pflicht-Attribute (Haftpflicht,
  3 Terminvorschläge) → schrittweise durch Slot-Wahl ersetzen.
- **Race-Conditions** bei Slot-Kapazität: Unique-Constraint + Transaktion (festgelegt).
- Cron-Runner für Reminder/Overdue (Hoster) — Blocker für Phase C.

> Diese Spec wurde durch das **Codex-Gate** geprüft (`codex-findings.md`); die Befunde
> (PreReservation-Hook, eigene QR-Seite, Instanz-/Ressourcenbezug, normalisierte
> Checklisten, ehrliche Core-Edit-Abgrenzung) sind eingearbeitet.

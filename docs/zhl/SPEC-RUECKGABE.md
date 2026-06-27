# SPEC-RUECKGABE — Buchbarer Rückgabetermin (Übergabe „nach hinten")

Status: in Arbeit (2026-06-27). Vom Nutzer beauftragt: „Es fehlt uns noch die Möglichkeit, die
Rückgabe zu buchen. Dafür muss bei einigen Geräten eine persönliche Übergabe erfolgen. Dafür müssen
wir die Logik, die Ausleihe nach vorne zur Abholung zu verlängern, nun auch nach hinten zur Rückgabe
erweitern."

Entscheidungen (Nutzer 2026-06-27):
- **Terminplaner-Termintyp „Übergabe Medien" wird wiederverwendet** (KEIN eigener „Rückgabe Medien"-Typ).
  Abholung und Rückgabe ziehen aus demselben Slot-Pool/Typ. → **Kein** zusätzliches Terminplaner-Setup,
  **kein** neuer Config-Key — der bestehende `handover_type_label` ('Übergabe Medien') gilt für beide
  Richtungen.
- **Rückgabetermin ist Pflicht** für Geräte mit persönlicher Rückgabe (symmetrisch zur Pflicht-Abholung) —
  ABER ohne Sackgasse: solange das parallele „Termin anfragen"-Feature (AK-9) noch nicht steht, darf der
  „kein Slot frei"-Fall nicht endgültig blockieren, sondern muss als Andock-Punkt offen bleiben.
- **Rückgabe am Ausleih-Endtag selbst** ist erwünscht (AK-8): liegt am letzten Nutzungstag ein freier
  „Übergabe Medien"-Slot, wird er als Rückgabe vorgeschlagen.
- Vorgehen: **spec-first** (diese Datei), Review, dann Implementierung + Migration + Deploy auf media (Staging).

Nach Codex-Review (2026-06-27, `codex-findings-rueckgabe.md`) ergänzte Festlegungen:
- **Rückgabezeit = Slot-START** (der Termin, zu dem zurückgegeben wird). `end_utc` ist nur Persistenz/
  Anzeige. Filter daher auf `start_utc >= returnFloorUtc`.
- **`returnFloorUtc` = `max(Tagesanfang Endtag, Ausleihstart-Anker)`** — verhindert bei 1-Tages-Ausleihen,
  dass ein Rückgabe-Slot VOR der Abholung/dem Nutzungsbeginn angeboten wird. Multi-Day → Endtag-00:00
  gewinnt; Same-Day → Start-Anker (Tagesmodus 09:00-Proxy) gewinnt.
- **`$reservEndDate/$reservEndTime` müssen NEU eingeführt** und an Pool (`pickFreeUnit`), `ZhlReservationFacade`
  und die `loanEndUtc`-Berechnung durchgereicht werden — analog zu `$reservBeginDate/$reservBeginTime`.
  Die Spec-Behauptung „Pool nutzt ohnehin reservEndDate/Time" war falsch (Code nutzt `$endDate/$endTime`).
- **Kein `rueckgabeort`-Snapshot**: `persistHandover` schreibt keinen Ort; `zhl-handover-lib` joint live auf
  `zhl_uebergabe`. Bleibt so (kein Snapshot nötig); Spec-Wording korrigiert.
- **Bundle (`zhl-bundle-book.php`) ist NICHT in diesem Ticket** — eigene `persistHandover`-Kopie + RETURN-
  Fallback ([ZhlBundleBookPresenter.php:1391](../../Presenters/ZhlBundleBookPresenter.php)); explizit Folge-Ticket,
  KEIN AK für Bundles hier.
- **AJAX `end` ist optional**: fehlt er, bleibt die Response abwärtskompatibel (Return = `null` bzw. aus
  `start` abgeleitet) — initialer Render/alte Clients dürfen nicht blockieren.

## Ausgangslage (was schon existiert)
- `persistHandover()` (Signatur bei [ZhlBookPresenter.php:1672](../../Presenters/ZhlBookPresenter.php), RETURN-Insert
  ab :1697) legt bereits eine `type='return'`-Zeile an — aber stumpf auf `loanEndUtc` verankert, **ohne**
  Terminplaner-Slot.
- Staff-Rückgabepfad ist **fertig**: Geräte-QR → `zhl-resource-return.php` → offene Rückgabe →
  `zhl-handover-check.php` (type=return) schreibt Protokoll, setzt `status='done'`.
- `zhl_uebergabe.rueckgabeort` existiert (016) als Default-Rückgabeort (Snapshot in die return-Zeile).
- Abhol-Mechanik als Vorlage: `fetchHandoverSlots` (Filter Slot-Ende ≤ Ausleihstart), Reservierungs-
  Verlängerung nach vorn ([ZhlBookPresenter.php:341-366](../../Presenters/ZhlBookPresenter.php)),
  Picker im Template ([tpl/zhl-book.tpl:187-220](../../tpl/zhl-book.tpl)), `pickupMandatory`-Gate,
  Phase-C-Buchung via `book_slot.php`.

## Ziel
Beim Buchen eines Geräts mit persönlicher Rückgabe wählt der Ausleihende **einen Rückgabetermin**
aus echten Terminplaner-Slots (Typ „Übergabe Medien"), die **am/nach dem Ausleihende** liegen. Die
native Reservierung wird **bis zum Rückgabetag verlängert** (Gerät bleibt blockiert, bis es physisch
zurück ist). Der gewählte Slot wird im Terminplaner gebucht und in die bestehende `type='return'`-
Zeile geschrieben (echte Zeiten + `terminplaner_booking_id` + `staff_member_id` statt loanEnd).

## Datenmodell (Migration `025_zhl_rueckgabe.sql`)
Spiegelbild zu `abholung`/`abholort`:
```sql
ALTER TABLE zhl_uebergabe
  ADD COLUMN IF NOT EXISTS rueckgabe VARCHAR(24) NOT NULL DEFAULT 'abgeben';
-- rueckgabe: nicht_noetig | abgeben | abgeben_persoenlich (Pflicht-Termin)
-- rueckgabeort existiert bereits (016) und dient als Default/Snapshot.
```
`lookupUebergabe()` um `rueckgabe` erweitern (Default `'abgeben'`).
Neuer Helper `returnApplies($ueb)` analog `pickupApplies()`:
`return $ueb['rueckgabe'] === 'abgeben_persoenlich';` (nur der persönliche Fall braucht einen Slot;
`abgeben`/`nicht_noetig` = wie heute Ablageort/loanEnd, kein Picker).

## Config (`config/zhl-handover.php` + `.example.php`)
**Kein neuer Key.** Rückgabe nutzt denselben `handover_type_label` ('Übergabe Medien') wie die Abholung.
**Kein Terminplaner-Setup nötig** — Typ + Slots existieren bereits. `fetchReturnSlots` bekommt
`handover_type_label` übergeben (identisch zu `fetchHandoverSlots`), nur die Zeit-Filterung ist invertiert.

## Presenter (`ZhlBookPresenter.php`)
1. **`fetchReturnSlots($typeLabel, $memberId, $returnFloorUtc, $tz)`** — Klon von `fetchHandoverSlots`
   mit `handover_type_label` ('Übergabe Medien'). Filter auf **Slot-START**: Slot zeigen, wenn
   **`startUtc >= returnFloorUtc`** (`end_utc` nur für Persistenz/Anzeige, anders als bei der Abholung,
   die primär auf `end_utc <= loanStart` filtert — bewusste fachliche Abweichung). `tp_member_id` aus
   `zhl_uebergabe` wiederverwenden.
   **`returnFloorUtc` (AK-2 + AK-8 + 1-Tages-Schutz):**
   `returnFloorUtc = max(Tagesanfang Endtag [endDate 00:00 lokal→UTC], Ausleihstart-Anker [Tagesmodus:
   beginDate 09:00-Proxy, Slotmodus: echter Start])`.
   - Multi-Day → Endtag-00:00 gewinnt → Same-Day-Rückgabe am Endtag möglich (AK-8).
   - Same-Day (beginDate==endDate) → Start-Anker gewinnt → kein Rückgabe-Slot VOR der Abholung (Codex-Fix).
2. **`AjaxSlots`** liefert zusätzlich `'return' => [...days...]`, gefiltert gegen `returnFloorUtc`.
   `end`-Param **optional** (Abwärtskompatibilität): fehlt er, wird `return` = `null` (bzw. aus `start`
   abgeleitet) und die alte Response (`pickup`/`einf`) bleibt unverändert — initialer Render/alte Clients
   blockieren nicht ([Codex: :81, :86, :108](../../Presenters/ZhlBookPresenter.php)).
   **JS-Trigger** ([tpl/zhl-book.tpl:407,444,524,537](../../tpl/zhl-book.tpl)): `loadSlots()` um `end,endTime`
   erweitern; Monatsmodus `applyRange(lo,hi)` übergibt `hi`, Wochenraster übergibt die End-Zelle `sE`.
   Heute lädt JS nur mit Start → Return-Slots aktualisieren sich sonst nie.
3. **`HandlePost` — Phase A (auflösen, nicht buchen):** `returnPlan` analog `pickupPlan`
   (`return_slot` aus POST, gegen `fetchReturnSlots` kanonisch auflösen, IDs prüfen).
   Pflicht-Gate: `returnMandatory = returnApplies($ueb) && !$user->IsAdmin` → ohne `return_slot`
   Fehlermeldung „Für dieses Gerät ist eine persönliche Rückgabe Pflicht — bitte einen
   Rückgabetermin wählen." Bei Hauspost entfällt der Rückgabetermin (Rückversand per Post).
   **AK-9 (Andock-Punkt „Termin anfragen"):** Wenn `returnApplies` aber **kein** Slot frei ist, darf das
   NICHT in eine Sackgasse laufen. Der „kein Slot frei"-Zustand wird als eigener, klar markierter Zweig
   modelliert (eigene Methode/Flag `$returnNoSlots`), sodass das parallele „Termin anfragen"-Feature dort
   später andocken kann (Anfrage-Button statt Hard-Block). Bis dahin: klare Meldung, Submit blockiert —
   aber die Stelle ist bewusst isoliert, nicht in die Pflicht-Validierung verwoben.
4. **Reservierung nach hinten verlängern** (Spiegel von :341-366) — **`$reservEndDate/$reservEndTime` NEU einführen**:
   ```
   $reservEndDate = $endDate; $reservEndTime = $endTime;   // Default = Nutzungsende
   $returnDay = Slot-Tag(returnPlan.start_utc)
   if ($returnDay > $endDate) { $reservEndDate = $returnDay; $reservEndTime = scheduleDayBounds(...)['end']; }
   ```
   Dann **überall** statt `$endDate/$endTime` die Reserv-Variablen verwenden, wo das Reservierungs-ENDE
   gemeint ist (Codex-Beleg):
   - Pool-Fenster `pickFreeUnit` ([:375-376](../../Presenters/ZhlBookPresenter.php)) → `$reservEndDate/$reservEndTime`.
   - `ZhlReservationFacade`-Konstruktor ([:393](../../Presenters/ZhlBookPresenter.php)) → verlängertes Ende.
   - `loanEndUtc`-Berechnung in Phase C ([:442](../../Presenters/ZhlBookPresenter.php), :471) → bewusst definieren
     (die RETURN-Zeile bekommt bei gewähltem Slot ohnehin die echten Slot-Zeiten; der loanEnd-Fallback nur
     ohne Slot).
   **Achtung:** `$beginTime/$endTime` bleiben für die fachliche Nutzungszeit; nur das *reservierte* Fenster
   wird gedehnt. Alle weiteren `$endDate/$endTime`-Verwendungen auditieren (Codex).
5. **Phase C (nach erfolgreichem Save buchen):** `returnPlan` via `book_slot.php` buchen
   (`note: 'ZHL Rückgabe — ' . $titel`). Erfolg → die **return-Zeile** mit echten Slot-Zeiten +
   `terminplaner_booking_id` + `staff_member_id` schreiben statt loanEnd.
6. **`persistHandover`** (bei :1672) erweitern: optionaler `?array $returnSlot = null`. Ist er gesetzt, bekommt die
   return-Zeile dessen `start/end/booking_id/staff_member_id/staff_role='primary'`; sonst Verhalten wie
   heute (loanEnd, kein Slot). **Kein `rueckgabeort`-Schreiben** — der Ort kommt weiter per Live-Join aus
   `zhl_uebergabe` ([handover-lib:301](../../Web/zhl-handover-lib.php)); nichts zu ändern.

## Template (`tpl/zhl-book.tpl`) + JS
- Neue Sektion **„Rückgabetermin wählen"** unter der Abhol-/Einführungs-Sektion, sichtbar nur wenn
  `$Return.applies` und nicht Hauspost. Markup = Klon des Pickup-Pickers (`zhl-pickup-day`-Chips +
  `zhl-pickup-pill`-Zeit-Pills, `name="return_slot"`, `required` wenn Pflicht).
- Hinweistext: „Nur Termine **am oder nach** deinem Ausleihende. **Bis zum Rückgabetag bleibt das
  Gerät auf dich gebucht.**"
- JS: bestehende `AjaxSlots`-Anbindung um `return`-Tag/Zeit-Rendering erweitern; Submit-Gate
  (`setSubmitBlocked`) auch auf fehlenden Pflicht-Rückgabe-Slot.

## Admin (`zhl-uebergabe-admin.php` + tpl)
Feld `rueckgabe` (Select: nicht_noetig | abgeben | abgeben_persoenlich) neben `abholung`; `rueckgabeort`
ist schon editierbar (016). Speichern in `zhl_uebergabe`.

## Cross-Dependencies (NICHT vergessen)
- **SPEC-CANCEL:** Storno-Schleife läuft laut Codex **bereits generisch** über ALLE handover-Zeilen und
  storniert jede mit `terminplaner_booking_id` ([ZhlBookingDetailPresenter.php:262-272](../../Presenters/ZhlBookingDetailPresenter.php),
  Load ohne Type-Filter :351-355). → Rückgabe-Slot wird **automatisch** mit-storniert (AK-6), sobald die
  return-Zeile eine `booking_id` trägt. **Nur kosmetisch zu fixen:** der Kommentar „Rückgabe-Zeile: kein
  Terminplaner-Slot" (:266) und das Warnlabel, das Return fälschlich „Abholtermin" nennt → `return =>
  Rückgabetermin` ergänzen. SPEC-CANCEL.md:14 entsprechend nachziehen (Nice-to-have, separat).
- **Cancel-Policy:** Reservierungsende ist jetzt ggf. der Rückgabetag. Storno-Erlaubnis („noch nicht
  begonnen") bezieht sich auf den **Beginn** (Abholtag) → unverändert.
- **Mail-Templates** ([MAIL-TEMPLATES-ENTWURF.md](MAIL-TEMPLATES-ENTWURF.md)): Bestätigungsmail um den
  Rückgabetermin ergänzen (analog Abhol-/Einführungstermin).
- **Bundle-Buchung** (`zhl-bundle-book.php`): **NICHT in diesem Ticket** (eigene `persistHandover`-Kopie +
  RETURN-Fallback, [ZhlBundleBookPresenter.php:1391-1414](../../Presenters/ZhlBundleBookPresenter.php)) →
  bewusstes Folge-Ticket, kein AK hier.

## Akzeptanzkriterien (EARS)
- **AK-1** WHEN ein Gerät `rueckgabe='abgeben_persoenlich'` hat AND der Nutzer kein Admin ist, THEN
  zeigt die Buchungsseite einen Pflicht-Rückgabe-Picker, und ein Submit ohne gewählten Slot wird
  serverseitig abgelehnt.
- **AK-2** WHEN der Nutzer einen Rückgabe-Slot wählt, THEN werden nur Slots angeboten, deren Start
  **≥ `returnFloorUtc`** liegt (= `max(Endtag-Anfang, Ausleihstart-Anker)`, s. Presenter-Teil); ändert der
  Nutzer das End-Datum, aktualisiert sich die Slot-Liste.
- **AK-3** WHEN ein Rückgabetermin nach dem Ausleihende liegt, THEN reicht die native Reservierung
  bis zum Rückgabetag (Gerät in der Lücke Nutzungsende→Rückgabe NICHT frei).
- **AK-4** WHEN die Buchung gespeichert ist, THEN ist der Slot im Terminplaner gebucht, und die
  `type='return'`-Zeile trägt echte Slot-Zeiten + `terminplaner_booking_id` + `staff_member_id`.
- **AK-5** WHEN kein Rückgabe-Slot frei ist (Typ ohne Slots), THEN klare Meldung + Submit blockiert,
  **kein** Crash/500.
- **AK-6** WHEN eine Buchung mit Rückgabetermin storniert wird, THEN wird auch der Rückgabe-Slot im
  Terminplaner storniert (`cancel_slot.php`).
- **AK-7** WHEN das Gerät `rueckgabe` ∈ {`abgeben`,`nicht_noetig`} hat, THEN bleibt das heutige
  Verhalten (return-Zeile auf loanEnd, kein Picker) unverändert.
- **AK-8** WHEN am letzten Tag des geplanten Nutzungszeitraums ein „Übergabe Medien"-Slot frei ist,
  THEN wird die Rückgabe AN DIESEM Tag angeboten (Same-Day am Endtag = zulässig; Floor = Tagesanfang
  des Endtags).
- **AK-9** WHEN `returnApplies` gilt, aber kein Rückgabe-Slot frei ist, THEN bleibt die Stelle als
  isolierter Andock-Punkt für das parallele „Termin anfragen"-Feature offen (kein in die Pflicht-
  Validierung verwobener Hard-Block).

## Tasks
1. Migration `025_zhl_rueckgabe.sql` (+ idempotent ON-DUPLICATE-Apply, Seed für Pocket 6K res 22 als Testgerät).
2. ~~Config-Key~~ — entfällt: Rückgabe nutzt bestehenden `handover_type_label` ('Übergabe Medien').
3. `lookupUebergabe`/`returnApplies` + `fetchReturnSlots` (Floor = Tagesanfang Endtag, AK-8).
4. `AjaxSlots` (+`end`-Param, return-days) + JS-Trigger auf End-Datum.
5. `HandlePost`: returnPlan auflösen, returnMandatory-Gate (+`$returnNoSlots`-Andockpunkt AK-9),
   **`$reservEndDate/$reservEndTime` einführen + an Pool/Facade/loanEndUtc durchreichen**, Phase-C-Buchung.
6. `persistHandover($returnSlot)`-Erweiterung (Signatur :1672).
7. Template-Sektion + JS-Rendering (`loadSlots` um `end,endTime`).
8. Admin-Feld `rueckgabe` (Select + Insert/Select in `zhl-uebergabe-admin.php`).
9. Storno: kosmetisch Kommentar (:266) + Warnlabel `return => Rückgabetermin` ([ZhlBookingDetailPresenter.php](../../Presenters/ZhlBookingDetailPresenter.php));
   funktional läuft return dank generischer Schleife bereits mit.
10. Mail-Template-Ergänzung (Folge, wenn Mails aktiv).

## Verifikation (vor/nach Deploy auf media-Staging)
- DB-Backup vor Migration (`docs/zhl/db_dump.php` / SFTP-Dump) — Pflicht.
- `fetchReturnSlots` gegen den bestehenden Typ „Übergabe Medien": Slots ab Tagesanfang des Endtags
  (inkl. Same-Day-Slot am Endtag, AK-8); nichts vor dem Endtag.
- End-to-end auf Staging mit Pocket 6K (res 22, testweise `abgeben_persoenlich`): Buchung →
  Rückgabe-Slot gewählt → native Reservierung reicht bis Rückgabetag (im Kalender geprüft) →
  Terminplaner zeigt gebuchten Slot → `zhl_booking_handover` return-Zeile mit booking_id.
- Storno derselben Buchung → Rückgabe-Slot im Terminplaner cancelled.
- Codex-Cross-Review vor Prod-Deploy.

## Bekannte Grenzen
- Same-Day-Rückgabe am Endtag ist hier **gewünscht** (AK-8) und über den Tagesanfang-Floor gelöst —
  im Gegensatz zur Abhol-Seite, die am Ausleih-Starttag wegen des 09:00-Ankers Same-Day-Slots noch
  ausblendet (separat notierte Abhol-Limitierung, nicht Teil dieses Tickets).
- „Termin anfragen" (AK-9) ist hier nur als Andock-Punkt vorbereitet, **nicht** implementiert — das
  parallele Feature liefert die Anfrage-Logik; diese Spec hält die „kein Slot frei"-Stelle isoliert.
- TOCTOU/Race beim Slot (Slot zwischen Auswahl und Buchung weg) → bestehender Phase-C-Warnpfad.
- Hauspost-Rückversand (Rückgabe per Post) ist kein Terminplaner-Slot → eigener Pfad, hier außen vor.

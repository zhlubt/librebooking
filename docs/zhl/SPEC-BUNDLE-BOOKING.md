# SPEC — Bundle-Buchung (atomar, mit Alternativen, Phasen & Transport-Maßen)

Ziel: Ein ganzes Bundle in einem Flow buchen — alle Geräte für den Aufnahme-Zeitraum, plus
**zeitversetzte** Folge-Geräte (Schnitt-PC: erst NACH der Aufnahme, 3–7 Tage länger), plus
**Alternativen** (Stativ ODER Gimbal), plus **Sub-Item-Infos** (Transportkiste mit Maßen für die
Auto-Transportplanung). Staging `media.zhl-ubt.de`. Plan + Code von Codex geprüft.

## Bestand (verifiziert)
- `zhl_bundle` (id,name,use_case,difficulty,description,hint,einweisung_*,offer_schnitt,active,sort_order).
- `zhl_bundle_item` (bundle_id, **type_label**, quantity, required, note, sort_order). Items referenzieren
  einen Geräte-TYP über das Label des Custom-Attributs „Geräte-Typ" (`custom_attributes.custom_attribute_id=15`).
- Geräte tragen ihren Typ in `custom_attribute_values` (entity_id=resource_id, custom_attribute_id=15).
  Beispiele: Pocket 6K = res 22 (Typ „Kamera"); Gimbals res 68/69 („Gimbal"); Stativ res 70 („Stativ");
  DJI-Funkmics res 45/46/47/52/57/58/59/60/61 („Funkmikrofon").
- `ZhlBundleService::GetBundles($typeFreeMap)` berechnet nur Verfügbarkeit (read-only), bucht NICHT.
- Buchung heute nur Einzelgerät: `ZhlBookPresenter` nimmt nur `rid`. Bundle-CTA zeigt ins Leere.
- **Facade kann Mehrfach-Ressourcen**: native `ReservationSeries` nutzt Primär-Ressource
  (`GetResourceId`) + Zusatz-Ressourcen (`GetResources()` → `SetAdditionalResources`/`AllResourceIds`).
  `ZhlReservationFacade::GetResources()` gibt aktuell `[]` → muss Zusatz-IDs liefern.

## Schema (Migration 014, idempotent via information_schema-Guard)
`ALTER TABLE zhl_bundle_item ADD COLUMN IF NOT EXISTS`:
- `alt_group VARCHAR(40) NULL` — Items mit gleichem `alt_group` (≠NULL) im selben Bundle = **Alternativen**
  („Stativ ODER Gimbal"); der Nutzer wählt genau eins.
- `phase VARCHAR(8) NOT NULL DEFAULT 'main'` — `main` = Aufnahme-Zeitraum; `after` = Folge-Phase
  (Schnitt-PC), eigener Zeitraum NACH dem Aufnahme-Ende.
- `meta VARCHAR(255) NULL` — Freitext-Info (z. B. Transportkiste-Maße „80×40×35 cm, 12 kg").
- `specific_resource_id SMALLINT UNSIGNED NULL` — optional ein KONKRETES Gerät statt Typ-Auflösung
  (z. B. genau die eine Pocket-6K-Transportkiste). NULL = über `type_label` auflösen.

## Auflösung Typ → Geräte (Resolver)
`resolveBundle($bundle, $beginUtc, $endUtc)`:
- Je Item: wenn `specific_resource_id` gesetzt → dieses Gerät; sonst alle Ressourcen mit
  `Geräte-Typ = type_label` (Attr 15), die im Zeitraum FREI sind (ResourceAvailability), die ersten
  `quantity` nehmen. Sichtbar/zuweisbar nur Ressourcen, die der User buchen darf (loadResource-Filter).
- Alternativen: pro `alt_group` nur die gewählte Option auflösen.
- Ergebnis: Liste `{item, resources[], satisfied:bool}`. Bundle buchbar wenn alle Pflicht-Items (required=1,
  nach Alternativ-Wahl) erfüllt sind.

## Buchungs-Flow (neue Seite)
`Web/zhl-bundle-book.php` → `Pages/ZhlBundleBookPage` (SecurePage, ZHL-Chrome) → `ZhlBundleBookPresenter`
→ `tpl/zhl-bundle-book.tpl`. Dashboard/Assistant-Bundle-CTA → `zhl-bundle-book.php?bid=<id>`.
GET: Bundle + Komposition zeigen:
- **Projekttitel** (Pflicht, wie Einzelbuchung).
- **Aufnahme-Zeitraum** über das **Monats-Kalender-Raster** (gleiche Optik/JS wie Einzelbuchung,
  monthGrid; Verfügbarkeit = Schnittmenge aller Pflicht-Geräte, vereinfacht: Tag frei, wenn genug
  Einheiten je Pflichttyp frei sind — v1: prüfe Verfügbarkeit beim Absenden serverseitig, Kalender zeigt
  grob die Kamera-Verfügbarkeit als Leitgerät).
- **Alternativen**: je `alt_group` Radio („Stativ" / „Gimbal" + konkretes Modell).
- **Folge-Phase Schnitt-PC** (phase='after'): Checkbox „Danach schneiden?" + Dauer (3–7 Tage); Zeitraum =
  [Aufnahme-Ende … +Dauer]. Optional.
- **Transport-Infos**: `meta` je Item anzeigen (Maße der Transportkiste → Auto-Transportplanung).
- **Übergabe/Einführung**: v1 = EIN Abhol-Termin für das ganze Bundle (C1-Mechanik, wenn irgendein Item
  Abholung braucht) + Einführungs-Gate, wenn ein Item Einführung erfordert und der User kein Zertifikat hat.
  *Codex-Frage:* reicht ein gemeinsamer Übergabetermin fürs Bundle, oder pro Phase einer (Aufnahme +
  Schnitt-PC getrennt)? Vorschlag: ein Abholtermin für die Aufnahme-Phase; Schnitt-PC ohne Abholung
  (Schlüsseltresor/PC-Raum) bzw. eigener falls nötig.

POST (atomar, Reihenfolge: validieren → externe Buchungen → native Reservierungen):
1. Projekttitel + Zeitraum + Alternativ-Wahl validieren; Bundle serverseitig auflösen (alle Pflicht-Items
   frei?). Wenn nicht erfüllbar → Fehler mit Angabe, welcher Typ fehlt.
2. Ggf. Abholtermin buchen (C1: book_slot → zhl_handover_token + pickup/return-Zeilen) + Einführung (falls nötig).
3. **Main-Phase**: EINE native Reservierung über die Facade — Primär-Ressource = erstes aufgelöstes Gerät,
   `GetResources()` = die übrigen aufgelösten Main-Geräte (alle gleicher Zeitraum). Titel = Projekttitel,
   Description = „[ZHL-Bundle: <name>] …".
4. **After-Phase** (falls gewählt): ZWEITE native Reservierung für die Schnitt-PC-Geräte über
   [Aufnahme-Ende … +Dauer]. Verknüpfung über Projekttitel + Description-Hinweis
   („Folge-Buchung zu <main-Referenznummer>").
5. Erfolg: beide Referenznummern + Übergabe-Info zeigen. Bei Teil-Fehler (Phase 2 schlägt fehl, Phase 1 ok):
   klare Meldung, dass die Aufnahme gebucht ist, der Schnitt-PC aber separat nachgebucht werden muss
   (kein Rollback der bereits gespeicherten nativen Reservierung — analog C1-Warnhinweis).

## Facade-Erweiterung
`ZhlReservationFacade.__construct(..., array $additionalResourceIds = [])` + `GetResources()` liefert diese.
*Codex-Frage:* erwartet der native Initializer in `GetResources()` reine int-IDs oder Objekte? Gegen
`NewReservationInitializer`/`ReservationComponentBinder` prüfen und exakt das Format liefern.

## Pocket-6K-Bundle (Seed, Migration/Update)
Bundle 4 „Profi-Film (BM Pocket 6K)" auf die echte Komposition bringen:
- Kamera: **specific_resource_id=22** (genau die Pocket 6K), required, phase main.
- Objektiv ×? (Typ „Objektiv" — Geräte anlegen/vorhanden? falls kein Resource-Typ „Objektiv" existiert,
  als `meta`/Hinweis führen statt buchbar — *Codex/Bestand prüfen*).
- Ladegeräte (Typ vorhanden? sonst als Zubehör/`meta`-Hinweis).
- **Transportkiste** mit `meta` = Maße (z. B. „85×45×40 cm" — echte Maße vom Nutzer nachtragen), als
  Hinweis/Sub-Item (buchbar nur wenn als Ressource existiert).
- **Stativ ODER Gimbal**: zwei Items, `alt_group='halterung'`, required (genau eins wählen).
- Funkmikrofone (Typ „Funkmikrofon", quantity nach Bedarf).
- Schnitt-/VR-PC: phase='after', required=0.
Hinweis: Objektive/Ladegeräte/Transportkiste sind evtl. KEINE eigenen LibreBooking-Ressourcen → dann als
Info/`meta` listen (Transport-/Packliste), nicht als buchbare Position. Bestand vor dem Seed prüfen.

## Risiken / Test
- Mehrfach-Ressourcen-Buchung gegen native Validierung (Konflikte/Vorlauf/Rechte je Ressource) testen —
  Facade-`GetResources`-Format ist der kritische Punkt.
- Teil-Buchung (after-Phase scheitert) sauber kommunizieren, kein Rollback nativer Reservierungen.
- Verfügbarkeits-Kalender fürs Bundle ist v1 vereinfacht (Leitgerät) — serverseitige Voll-Prüfung beim POST.
- Migration idempotent; Seed nur idempotent (UPDATE/INSERT … ON DUPLICATE).
- DB-Backup vor Deploy; `tpl_c` leeren.

## Codex-Entscheidungen (übernommen)
- `GetResources()` = **`list<int>`** (reine Ressourcen-IDs); Save-Pfad = `ReservationSavePresenter` (LoadById
  + `ReservationSeries::AddResource`). Facade normalisiert: int-cast, dedupe, Primär-ID entfernen, >0.
- **Gemischte Schedules = Blocker** (SchedulePeriodRule/Verfügbarkeit/Quota nutzen nur den Primär-Schedule):
  **v1-Regel — alle Ressourcen einer Phase MÜSSEN denselben `schedule_id` haben.** Resolver lehnt eine
  Phase ab, die über mehrere Schedules streut; Begin/End aus dem Layout dieses einen Schedules (scheduleDayBounds).
- **Nicht „atomar" nennen** — DB-Persistenz ist nicht transaktional. Eine native Multi-Resource-Serie
  validiert-vor-Persistenz (Validierungsfehler ⇒ nichts gebucht), aber Mid-Persistenz-Fehler kann eine
  unvollständige Serie hinterlassen. Sprache: „in EINER Reservierung". **Doppel-Submit-Schutz** ergänzen.
- **Resolver teilt global disjunkt zu** (kein Gerät erfüllt zwei Items); Mengen aggregieren; beim POST
  neu auflösen; native Konfliktprüfung ist der Backstop (TOCTOU-Restfenster akzeptiert = natives Verhalten).
- **Ein Abholtermin für die Main-Phase**; After-Phase (Schnitt-PC) eigenständig bewerten (eigener Abholtermin
  nur wenn das Gerät es laut Konfig braucht — NICHT hart „PC ohne Abholung"). Einführung/Zertifikat für
  ALLE Items prüfen, die es brauchen, nicht nur das Primärgerät.
- **Packliste vs. Ressource:** Objektive/Stativ/Gimbal = echte Ressourcen (Typ vorhanden) → buchbar.
  Ladegeräte/Transportkiste = `meta`/Packliste (keine eigene LB-Ressource) → NUR Anzeige, KEINE
  synthetischen Ressourcen-IDs. Vor Seed Bestand prüfen.
- Weitere Pflichten: `max_resources_per_reservation` VOR externen Buchungen prüfen; `specific_resource_id`
  validieren (existiert/aktiv/buchbar/erlaubt/passende Phase); Terminplaner-Buchung bei nativem Save-Fehler
  kompensieren (cancel), wo die API es kann, sonst sichtbar/erkennbar lassen.
- **After-Phase explizit nicht-atomare Folge-Buchung:** beide Phasen VOR dem Speichern von Phase 1
  vorvalidieren; Phase 1 buchen; Phase 2 buchen; scheitert Phase 2 → klare Meldung „Aufnahme gebucht,
  Schnitt-PC bitte separat nachbuchen" (kein Rollback). Der Nutzer wünscht die Verknüpfung ausdrücklich.

## Reihenfolge
1. Facade-Erweiterung + Resolver + Schema 014.
2. Presenter/Page/Template (Kalender + Alternativen + After-Phase + Transport-Infos).
3. Pocket-6K-Seed (nur was wirklich als Ressource existiert; Rest als Packliste/meta).
4. Codex-Review Code → Deploy → Smoke.

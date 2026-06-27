# SPEC-LOAN-RASTER (Schritt 2)

**Stand:** 2026-06-27 · **Status:** Entwurf (Schedule-Umstellung = prod-riskant, Staging-first)

## Ziel
Einheitlicher Verleih-Buchungsmodus mit **Übergabe-Ankern 09:00 / 15:00** (statt 24-Std-Standard
bzw. 2h/6h-Raster), und — wie beim Studio — **60-Min-Einführungen, die das Gerät vor Ort
reservieren**. Gilt für **alle Verleih-Geräte außer dem Videostudio** (res 21 bleibt `slot`).

## Befund aus Live-Dump (2026-06-22)
| Layout | Schedule | aktuell | 9/15 als Grenze? |
|---|---|---|---|
| 20 | Videostudio (8) | 07–21 stündlich | ja — **bleibt** |
| 10 | Medientechnik Verleih (5, default) | 2h (08–22) | nein |
| 11 | Immersive Medien (2) | 2h (08–22) | nein |
| 17 | Drohne (7) | 3h (08,11,14,17) | nein |
| 13 | Analoge Moderation (6) | 24h (00–00) | nein |

→ Kein Verleih-Layout hat heute 09:00/15:00 als Periodengrenze. Anker **und** 60-Min-Einführung
erfordern eine Schedule-Umstellung auf stündliche Blöcke.

## Maßgeblich: Ausleih- & Rückgabefristen
Der gesamte Ablauf muss **innerhalb der Ausleih-/Rückgabefristen** funktionieren (Nutzer-Vorgabe
2026-06-27): Einführung **vor** Ausleihbeginn, Rückgabe **spätestens zur Rückgabefrist**, und die
Geräte-Reservierung deckt das Fenster `[Übergabe @Anker → Rückgabe @Anker]` lückenlos ab. → Das
spricht für **Variante Y** (Geräte-Reservierung physisch von Übergabe bis Rückgabe), nicht nur
Übergabe-/Rückgabetermine bei tagesbasierter Reservierung. Final zu bestätigen vor `anker`-Bau.

## Modell
- **Dauer in Tagen (N)** + **Übergabetermin** (Terminplaner „Übergabe Medien"). Der Übergabe-Slot
  liegt auf einem **Anker (09:00 oder 15:00)**.
- Geräte-Reservierung = `[Übergabetag @Anker → Rückgabetag @Anker]`, Rückgabetag = Übergabetag + N.
  Rückgabe-Uhrzeit = Übergabe-Uhrzeit → die nächste Person übernimmt zum selben Anker.
- **Einführung (Pflicht):** wie Studio — eigener 60-Min-Terminplaner-Termin („Einführung in Medien",
  member 2) UND eine native 60-Min-Geräte-Reservierung für diese Stunde (Gerät vor Ort & frei).

## Umsetzung

### (1) Schedule-Umstellung — Layouts 10/11/13/17 → stündlich 00:00–24:00, alle reservierbar
Permissives stündliches Raster; die 9/15-Beschränkung macht die **App** (`anker`-Modus), nicht das
Schedule. Datensicher: 09:00/15:00 werden Grenzen, 60-Min-Einführung wird gültig, und bestehende
(stundengenaue) Reservierungen — inkl. 24-Std-„Analoge Moderation" — bleiben gültig, weil 00–24 voll
reservierbar bleibt. **Riskant (Core-Tabelle `time_blocks`, betrifft alle Geräte dieser Schedules):
erst auf Staging (media.zhl-ubt.de) testen, dann mit Backup auf prod.** Bevorzugt über die
LibreBooking-Admin-UI (Manage Schedules → Layout) oder geprüftes `time_blocks`-DELETE+INSERT.

### (2) Neuer `booking_mode = 'anker'` (App, ZhlBookPresenter)
- Picker: Tage-Auswahl (N) + Übergabe-/Rückgabe-Slot (9/15-Anker, wie bestehender pickup/return-Picker).
- HandlePost: Reservierungs-Begin/End aus Übergabe-Anker + N Tagen ableiten (analog `slot`-Zweig,
  aber Anker statt frei geklickter Spanne). Parallel zur bestehenden `day`/`slot`-Logik.

### (3) Einführung-vor-Ort generalisieren (explizites Flag statt `booking_mode`-Proxy)
- Migration: `zhl_uebergabe.einf_blockt_geraet TINYINT NOT NULL DEFAULT 0`.
- Gate in [ZhlBookPresenter](../../Presenters/ZhlBookPresenter.php) von `booking_mode==='slot'` auf
  `einf_blockt_geraet==1` umstellen (3 Stellen: AjaxSlots-Filter, Phase-A-Filter, Phase-C-Reservierung).
- Flag = 1 für res 21 (Studio, ersetzt den Proxy) + alle Verleih-Geräte mit Pflicht-Einführung —
  **letztere erst setzen, nachdem deren Schedule stündlich ist** (sonst scheitert die 60-Min-Reservierung).

## Reihenfolge / Risiko
1. Migration `einf_blockt_geraet` + Gate-Umstellung (Code) — Studio=1, Verhalten unverändert.
2. `anker`-Modus implementieren (Code) — noch nicht aktiviert.
3. Schedule-Umstellung auf **Staging** testen (Buchung 9→9 über N Tage, 60-Min-Einführung).
4. Pro Verleih-Schedule: Layout stündlich (prod, Backup), `booking_mode='anker'` + `einf_blockt_geraet=1`
   setzen, Smoke-Test. Schrittweise je Schedule ausrollbar.

## Voraussetzungen (prod, Terminplaner)
- „Übergabe Medien": Slots nur 09:00 & 15:00 (Dauer/Verfügbarkeit entsprechend).
- „Einführung in Medien": 60 Min, volle Stunde (wie SPEC-STUDIO-EINFUEHRUNG).

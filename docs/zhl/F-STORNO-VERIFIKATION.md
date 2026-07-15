# Task F — READ-ONLY Storno-Verifikation „Aufnahme 30.6.–7.7."

**Datum:** 2026-06-29 · **Modus:** ausschließlich READ-ONLY (nur SELECT/SHOW, keine Writes)
**Harness:** [`verify-storno-30-06.php`](verify-storno-30-06.php)
**Ausgeführt auf:** media (`zhl_media`, Container `aebea51094db`) und meet (`zhl_meet`, Container `d9a0104797cb`)

> Diese Datei ersetzt den früheren „nicht live ausgeführt"-Platzhalter: Der Live-Read wurde
> am 2026-06-29 über `sshpass -f` (Passwort-Datei, nie im KI-Kontext) auf beiden Containern
> tatsächlich ausgeführt.

## Ergebnis

**KONSISTENT.** Die Aufnahme-Buchung ist auf media vollständig storniert; auf meet
existieren keine an sie gekoppelten Termine.

## Die stornierte Buchung (Ground Truth)

| Feld | Wert |
|---|---|
| reference_number | `6a412bff17204082463658` |
| Titel | `aufnahme` (klein geschrieben) |
| Nutzer | **user 24** = `paul.doelle@uni-bayreuth.de` |
| Gerät | ZHL Videostudio (resource 21), 1 Gerät |
| Zeitraum | 2026-06-30 07:00 – 2026-07-07 15:00 |
| series_id (native) | 1406 |
| storniert am | 2026-06-28 14:19:36 |

> **Korrektur am Harness:** Der ursprüngliche Wert `TARGET_USER = 312` war falsch —
> user 312 existiert nicht, daher lief der erste Durchlauf komplett leer. Der Buchende
> ist **user 24**. Zusätzlich wurde Schritt [1] ref-verankert und status-bewusst gemacht
> (sonst hätte die soft-deletete Reservierung bzw. unabhängige Buchungen fälschlich
> als „nicht storniert" gegolten).

## media (`zhl_media`)

- **[1] Storno-Schnappschuss** `zhl_cancelled_booking`: **1** Treffer (Erwartung ≥1) —
  ref `6a412bff…`, „aufnahme", Videostudio, 30.6.–7.7., storniert 2026-06-28 14:19:36. ✓
- **[2] Native Reservierung** `reservation_series` 1406: **status_id = 2 (Deleted)** ✓
  (`reservation_statuses`: 1=Created, 2=Deleted, 3=Pending; `last_modified` = `cancelled_at`).
  Keine `Created`-Reste zur ref.
- **[3] `zhl_booking_handover` zur ref: 0** Zeilen (Erwartung 0) — aufgeräumt, und es war
  **kein** Terminplaner-Termin (Abholung/Einführung/Rückgabe) an die Aufnahme gekoppelt. ✓
- **[4] `pending` cert_confirmation** für user 24: **0** (Info; Storno räumt sie bewusst nicht). ✓

**FAZIT media: KONSISTENT** (Schnappschuss da, native Deleted, keine Created-Reste, handover aufgeräumt).

## meet (`zhl_meet`)

- An die Storno-Buchung gekoppelte Termin-IDs (aus media-[3]): **keine** → auf meet ist
  nichts zu stornieren (vacuously consistent). ✓
- Sicherheitsnetz: Paul-Buchungen im Fenster = 1 (`id=629F7401`, meeting_type 36, confirmed,
  Start 2026-06-30 06:00, erstellt 2026-06-29). Das ist eine **separate, neue** Buchung
  (nach dem Storno angelegt, keine handover-Kopplung), **nicht** storno-bezogen.

**FAZIT meet: KONSISTENT** (keine an die Storno-Buchung gekoppelten Termine).

## Nebenbefunde (kein Defekt, nur zur Einordnung)

Diese lebenden Reservierungen von user 24 fallen ins Zeitfenster, gehören aber **nicht**
zur stornierten Aufnahme und bleiben korrekt unberührt:

- **series 1408** „Einführung ZHL Videostudio", 2026-07-04 08:00–09:00, status Created —
  eigenständige native Einführung für resource 21, **ohne** handover/meet-Link. Eine
  Einführung ist nutzer-/gerätetyp-bezogen und unabhängig von einer einzelnen Aufnahme;
  sie korrekt stehen zu lassen ist gewolltes Verhalten.
- **series 1357** „Hanno Hoch Videoschnitt …", bis 2026-07-01, status Created — separater Vorgang.

## Reproduktion

```bash
sshpass -f ~/.ssh/zhl-media.pass ssh zhl-media 'cd /var/www/html && php -d display_errors=1' < docs/zhl/verify-storno-30-06.php
sshpass -f ~/.ssh/zhl-meet.pass  ssh zhl-meet  'php -d display_errors=1'                      < docs/zhl/verify-storno-30-06.php
```

# SPEC-STUDIO-EINFUEHRUNG (Schritt 1)

**Stand:** 2026-06-27 · **Status:** in Umsetzung · scope: nur Videostudio (res 21)

## Problem

Beim Videostudio (`res 21`, `booking_mode='slot'`, Stunden-Raster 07–21) ist eine
Einführung zwingend (`einfuehrung='notwendig'`, `einfuehrung_typ='Einführung ins Videostudio'`,
`tp_member_id=2`). Die Einführung wird heute **nur** als Terminplaner-Slot beim Einweisenden
(meet.zhl-ubt.de) gebucht — **entkoppelt von der Studio-Verfügbarkeit**. Folge: Man kann eine
Einführung legen, obwohl das Studio in dieser Stunde schon belegt ist. Die Einführung findet
aber IM Studio statt, also muss das Studio für diese Zeit frei UND reserviert sein.

## Anforderung

Die Einführung ist **zwei deckungsgleiche Buchungen**:
1. **Terminplaner-Slot** beim Einweisenden (Personal muss Zeit haben) — *bleibt unverändert*.
2. **Native Studio-Reservierung** für dieselbe Stunde (Gerät muss vor Ort & frei sein) — *neu*.

Dauer = **60 Min** (eine Studio-Periode; bewusst, 1a aus der Abstimmung). Voraussetzung:
Der Terminplaner-Typ „Einführung ins Videostudio" hat Dauer 60 Min und liegt auf der vollen
Stunde, damit sich Slot und Studio-Periode decken (`generate_time_slots` steppt um die Typ-Dauer).

## Lösung (minimal, kein Schema-Eingriff)

Gating-Proxy für „Einführung reserviert das Gerät selbst" = **`booking_mode === 'slot'`**
(aktuell ausschließlich das Studio). Schritt 2 ersetzt diesen Proxy durch ein explizites
Gerät-Flag, sobald weitere Geräte das Gerät-vor-Ort-Modell brauchen.

### A) Slot-Angebot filtern (`AjaxSlots`)
Im Slot-Modus + Einführung nötig: nach `fetchEinfuehrungSlots(...)` die Slots auf jene
filtern, deren Fenster `[start_utc, end_utc)` im Geräte-Pool frei ist
(`pickFreeUnit(poolIds, …) !== null`). `days`/`earliestLabel` aus den gefilterten Slots
neu berechnen. So werden nur Termine angeboten, an denen Personal UND Studio frei sind.

### B) Studio-Reservierung anlegen (`HandlePost`, Phase C)
Nach erfolgreicher Terminplaner-Einführungs-Buchung (`$einfPlan`-Erfolgszweig) und nur bei
`booking_mode==='slot'`: eine native 60-Min-Reservierung auf der freien Pool-Einheit für
`[start_utc, end_utc]` anlegen (`ZhlReservationFacade` → nativer Handler, volle Validierung
bleibt letzte Instanz). Titel „Einführung Videostudio", Beschreibung verweist auf die Ref der
Hauptbuchung. Best-effort wie der Rest von Phase C: scheitert es (Race → Studio inzwischen
belegt, Vorlauf, Periodengrenze), bleibt die Hauptbuchung bestehen + Warnhinweis ans Team.

## Nicht-Ziele (Schritt 2)
- 9–15 / 15–9-Uhr-Raster als eigener Buchungsmodus.
- Gerät-vor-Ort-Einführung für Nicht-Studio-Geräte (`booking_mode='day'`) — braucht erst das Raster.
- Explizites Gerät-Flag statt `booking_mode`-Proxy.

## Verifikation
- `php -l` (kein lokales DB-/Smoke-Setup vorhanden — Prod = MariaDB nur via SFTP).
- Vor Deploy: Prod-DB-Backup. Nach Deploy: Smoke-Test als Nicht-Admin — Studio buchen,
  Einführungs-Picker zeigt nur freie Studio-Stunden, nach Buchung existiert eine
  Studio-Reservierung „Einführung Videostudio" zur gewählten Stunde.

# D2 — Re-Ausleih-Gate: Bau-Bewertung (2026-06-28)

**Auftrag (Task E):** Bewerten, ob das Re-Ausleih-Gate (Gerät gesperrt, solange eine offene
`return`-Zeile mit `status≠'done'` und `scheduled_end_utc < now` existiert) jetzt sicher baubar ist.
Ein False-Positive bräche **alle** Buchungen → besondere Vorsicht.

## Entscheidung: NICHT bauen. Restbedarf + Testplan dokumentiert.

Begründung anhand des **echten Live-Schemas** (`docs/zhl/backups/media_zhl_media_pre-fixups_20260628.sql`,
Stand heute) und des Worktree-Codes:

### Blocker 1 — Mass-False-Positive ist strukturell garantiert
Jede normale, abgeschlossene Ausleihe legt eine `return`-Zeile mit `status='confirmed'` und
`scheduled_end_utc = Ausleihende` an (SPEC-CANCEL/C1-Design; bestätigt in `ZhlBookPresenter`/
`ZhlBundleBookPresenter` über `persistHandover`). Auf `'done'` wird sie **ausschließlich** durch eine
manuelle Medienmanager-Bestätigung über `Web/zhl-handover-check.php?type=return` gesetzt (Zeile 164ff,
`UPDATE … SET status='done'`).

Dieser Rückgabe-Bestätigungs-Workflow (Block D der SPEC-UX-2026-06-24) ist **nicht operativ ausgerollt**
(keine Medienmanager-Tagesseite, kein etabliertes Scan-/Bestätigungs-Verfahren in Benutzung). Ginge das
Gate jetzt live, würde **jedes Gerät mit irgendeiner vergangenen Ausleihe**, deren Rückgabe nie manuell
bestätigt wurde, dauerhaft gesperrt. Das ist genau der Fall „False-Positive bräche alle Buchungen".

Read-only nicht abschließend messbar: der heutige Dump enthält **keine** `zhl_booking_handover`-Zeilen
(Testdaten wurden pre-deploy bereinigt). Die Anzahl real betroffener Geräte ließe sich nur mit
Live-DB-Read (hier nicht verfügbar) quantifizieren — das strukturelle Risiko besteht unabhängig davon.

### Blocker 2 — Schema-Vorbedingungen aus dem eigenen Codex-Beschluss fehlen
SPEC-UX-2026-06-24 (Codex-Entscheidungen) verlangt **vor D2/D3**:
- Unique-Key `(handover_token, type)` → `(handover_token, type, resource_id)` migrieren (ressourcengenaue
  Bestätigung, nötig für Bundles/Mehrfachgeräte). Live-Schema hat weiterhin `UNIQUE KEY uq_token_type
  (handover_token, type)` — **nicht migriert**.
- Index `(resource_id, type, status, scheduled_end_utc)` für die Gate-Abfrage. Live-Schema hat nur
  `KEY idx_status (status)` — **fehlt**.

Ohne diese ist das Gate für Multi-Resource-Bundles nicht korrekt und die Abfrage über alle
`AllResources()` läuft ungünstig.

### Blocker 3 — Admin-Ausnahme nicht verdrahtet
Der Codex-Beschluss verlangt eine Admin-Ausnahme „über die handelnde Session (Factory muss Session
reichen)". `ZhlHandoverValidation::__construct()` bekommt heute nur den dekorierten Service, **keine
Session** — die Validierungs-Decorator-Kette müsste erweitert werden, damit die Regel `IsAdmin` kennt.
Das ist eigene, nicht-triviale Plumbing-Arbeit, kein reiner Härtungs-Schritt.

### Blocker 4 — Kein Backfill/Cleanup für Alt-Rückgaben
Selbst mit korrektem Schema bräuchte es eine einmalige Bereinigung/Backfill aller offenen
`return`-Zeilen (vor Gate-Aktivierung auf `'done'` setzen bzw. einen Stichtag definieren), sonst greift
Blocker 1 sofort. Das ist nicht spezifiziert.

## Was bereits sicher steht (Bausteine)
- `status`-ENUM enthält `cancelled` (Mig 024) → ein nativer Storno, der Handover-Zeilen **löscht**
  (SPEC-CANCEL Schritt 4), hinterlässt keine Dauer-Sperre. Gut.
- `Web/zhl-handover-check.php` setzt `return`→`done` (Gate-Öffner existiert).
- PreReservation-Plugin-Gerüst `plugins/PreReservation/ZhlHandover` vorhanden; `EvaluateHandoverRule`
  (token-basiertes Übergabe-Gate) ist das Muster für eine **zweite** Regel.

## Sicherer Bau-Pfad (Reihenfolge, wenn D2 später gebaut wird)
1. **Migration (vor D2):** Unique-Key auf `(handover_token, type, resource_id)` migrieren (mit
   Backfill ressourcengenau) + Index `(resource_id, type, status, scheduled_end_utc)` anlegen.
   `information_schema`-Guard / `MODIFY`, idempotent.
2. **Backfill:** alle bestehenden offenen `return`-Zeilen (`status IN ('requested','confirmed')`,
   `scheduled_end_utc < now`) per Stichtag auf `'done'` setzen ODER einen `gate_active_from`-Zeitpunkt
   einführen, ab dem das Gate nur neuere Vorgänge berücksichtigt. → verhindert Mass-False-Positive.
3. **Block D operativ:** Medienmanager-Rückgabe-Bestätigung (Tagesseite/QR) ausrollen, BEVOR das Gate
   blockt — sonst gibt es keinen praktikablen Weg, das Gate wieder zu öffnen.
4. **Session in die Validator-Kette reichen** (Admin-Ausnahme).
5. **Zweite PreReservation-Regel** (getrennt von `EvaluateHandoverRule`), Prädikat exakt:
   `type='return' AND status IN ('requested','confirmed') AND scheduled_end_utc <= UTC_TIMESTAMP()
   AND resource_id IS NOT NULL`, geprüft über alle `AllResources()`; `IsAdmin` ⇒ kein Block;
   kein Handover für die Ressource ⇒ kein Block.

## Testplan (vor Aktivierung Pflicht)
- **GT-Read (Live, read-only):** Zähle offene `return`-Zeilen mit `scheduled_end_utc < now` und
  `status≠'done'` je `resource_id`. Erwartung nach Backfill: 0. Das ist die Mass-False-Positive-Probe.
- **Negativ:** Gerät OHNE Handover-Historie → buchbar (kein Block). Admin → nie geblockt.
- **Positiv:** Gerät mit künstlich injizierter offener `return`-Zeile (`confirmed`, past end) → Buchung
  geblockt mit klarer Meldung; nach `zhl-handover-check.php?type=return`→`done` → wieder buchbar.
- **Bundle/Multi-Resource:** Reservierung mit 2 Geräten, eines mit offener Rückgabe → korrekt geblockt;
  ressourcengenau (nicht das ganze Bundle wegen eines anderen Geräts).
- **Storno:** native Stornierung schließt/löscht Handover-Zeilen → keine Rest-Sperre.
- **Idempotenz/Index:** Migration zweimal laufen lassen; EXPLAIN der Gate-Abfrage nutzt den neuen Index.

## Fazit
D2 ist **noch nicht sicher baubar**. Es hängt an (a) der operativen Rückgabe-Bestätigung (Block D),
(b) zwei Schema-Migrationen + Backfill und (c) Admin-Session-Plumbing. Vor diesen Schritten würde eine
Aktivierung den Buchungsbetrieb breit brechen. Empfehlung: D2 erst nach Block-D-Rollout + Migration +
Backfill bauen, streng nach obigem Testplan.

# SPEC-POOL-FALLBACK — Geräte-Pool-Verfügbarkeit & automatische Ausweichlogik

Status: in Arbeit (2026-06-25)
Betroffen: Einzelgeräte-Buchung (`zhl-book.php` / `ZhlBookPresenter`). Bundle-Pfad
(`ZhlBundleResolver`) macht bereits Pool-Fallback (wählt erste N freie Einheiten je Typ).

## Problem (vom Nutzer gemeldet, „E")
Mehrere physische Geräte teilen sich einen **Geräte-Typ** (= Pool), z. B. 3 Smartphone-Kits
(res48/49/50). Bei der Einzelbuchung wird die in der Geräteliste angeklickte **eine** Ressource
(`resourceId` im POST/GET) hart gepinnt:
- Der Kalender im Buchungsformular zeigt nur die Belegung **dieser einen** Einheit.
  → Ist res48 nach einem Datum komplett belegt, sieht der Nutzer keine freien Tage mehr,
    obwohl res49/res50 frei wären. („eigentlich dürfte ich gar nichts auswählen können".)
- Beim Absenden lehnt die native Verfügbarkeitsprüfung die Buchung von res48 ab → Fehlermeldung,
  ohne auf res49/res50 auszuweichen. („auf eines der anderen ausweichen … nicht händisch".)

## Ziel
Die Einzelbuchung wird **typ-/poolbewusst**:
1. **Kalender (Anzeige):** Ein Tag (Tagesmodus) bzw. ein 2h-Slot (Slotmodus) gilt als **frei**,
   wenn **mindestens eine** Einheit des Geräte-Typs in diesem Fenster frei ist.
2. **Zuteilung (Commit):** Beim Speichern wird die konkrete Einheit automatisch gewählt —
   **bevorzugt die angeklickte** Einheit, sonst die erste freie gleich­typige Einheit.
   Ist **keine** Einheit frei → klare deutsche Fehlermeldung statt nativem Konflikt.

## Nicht-Ziele / bewusste Grenzen
- Keine Änderung der Geräteliste/Dashboard-UX (weiter „Gerät anklicken"); der Klick wählt
  ab jetzt faktisch den **Typ** mit der angeklickten Einheit als Präferenz.
- Pool nur über Einheiten **gleichen Geräte-Typs UND gleichen Schedules** (alle Verleih-Ressourcen
  liegen auf schedule_id=5 → Kalender-Layout/Grenzen identisch, Zuteilung unbedenklich).
- Übergabe-/Einführungs-/Zertifikats-Logik ist **typ-konsistent** (gleiche `zhl_uebergabe`-Regeln,
  Zertifikate decken den Typ ab) → die Einheit-Substitution ändert sie nicht. Die teure
  Terminplaner-/Cert-Auflösung läuft weiter gegen die angeklickte `$rid`; nur der **physische
  reservierte resource_id** kann abweichen.

## Design
Neue private Helfer in `ZhlBookPresenter`:
- `poolResourceIds(UserSession $user, $resource): int[]`
  - `$type = lookupType(db, rid)`. Kein Typ → `[$rid]` (Verhalten exakt wie bisher).
  - Sonst: alle vom Nutzer buchbaren Ressourcen (`ResourceService::GetAllResources(false,$user)`,
    nicht HIDDEN) mit gleichem Geräte-Typ **und** gleichem `ScheduleId` wie `$resource`.
  - Reihenfolge: `$rid` zuerst (Präferenz), Rest aufsteigend. Pro Request memoisiert.
- `splitItemsByResource(array $items): array` — Reservierungs-/Blackout-Items nach `resource_id` gruppieren.
- `poolDayFree(array $byResource, int[] $poolIds, Date $dayStart, Date $dayEnd): bool`
  - frei, wenn ∃ Einheit ohne überlappendes Item (sonst „alle belegt").
- `pickFreeUnit(int[] $poolIds, Date $begin, Date $end): ?int` — erste freie Einheit (Präferenz zuerst).

Geänderte Kalender-Bauer (alle vier): `GetItemsBetween(..., [$rid])` → `GetItemsBetween(..., $poolIds)`,
danach `splitItemsByResource` + „frei wenn irgendeine Einheit frei" (`poolDayFree`-Logik). Betrifft
`availableDays`, `freeSlots`, `weekGrid`, `monthGrid`.

Geänderter Commit (`HandlePost`, direkt vor dem Facade-Aufruf): finale Fensterzeiten
`[$reservBeginDate $reservBeginTime, $endDate $endTime]` → `pickFreeUnit($poolIds, …)`.
- Treffer → `$assignedRid` (statt `$rid`) in die `ZhlReservationFacade`. Bei Abweichung
  transparenter Hinweis in der Beschreibung („· Einheit automatisch zugewiesen").
- Kein Treffer → `bindForm(... ['Im gewählten Zeitraum ist kein Gerät dieses Typs frei. Bitte
  einen anderen Zeitraum wählen.'] ...)`.

## Codex-Review (2026-06-25) — adressiert
- **CanBook statt nur Zugriff:** Pool filtert auf `$r->CanBook` (nicht nur sichtbar) → keine
  Zuteilung einer nicht-buchbaren Einheit.
- **Äquivalenz-Invariante (wichtigster Befund):** Übergabe/Cert/Einführung werden vorab gegen die
  angeklickte `$rid` aufgelöst. Der Pool nimmt deshalb NUR Einheiten mit identischer Übergabe-Signatur
  UND identischem Einführungs-Gate-Status (`uebergabeSignature` + `einfGateSatisfied`) auf — so kann
  eine Substitution keine Pflicht-Einführung umgehen.
- **maxConcurrent=1:** Pool nur über Einzelbelegungs-Ressourcen (sonst weicht die „belegt"-Logik von
  der nativen ab). Verleih-Ressourcen sind alle =1.

### Bekannte Grenzen (bewusst akzeptiert, Staging)
- **TOCTOU-Race:** `pickFreeUnit` prüft, danach validiert+speichert der native Handler erneut. Es gibt
  keinen Row-Lock — zwei exakt gleichzeitige Anfragen könnten dieselbe Einheit doppelt buchen. Das ist
  eine **vorbestehende** LibreBooking-Eigenschaft (gilt auch für jede native Buchung); der native
  Re-Check verkleinert das Fenster, schließt es aber nicht. Kein Regressionsrisiko durch diese Änderung.
- **Buffer-Zeiten:** `unitBusy` testet rohe Überlappung. Verleih-Ressourcen haben keine Pufferzeiten;
  bei gepufferten Ressourcen könnte der Kalender frei zeigen, was der native Save ablehnt (Backstop greift).

## Tests / Verifikation
- Pool mit 3 Einheiten, 1 frei: Kalender zeigt Tag frei; Commit weist die freie Einheit zu.
- Pool, angeklickte Einheit frei: bekommt genau diese (keine unnötige Substitution).
- Pool, alle belegt: Tag im Kalender belegt; Commit → klare Fehlermeldung, keine Reservierung.
- Singleton-Typ / kein Typ: identisches Verhalten wie vorher (Regressionsschutz).
- Authentifizierter Render-Smoke-Test + Codex-Review vor Deploy.

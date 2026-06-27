| Punkt | Befund | Beleg (Pfad:Zeile) | Empfehlung |
|---|---|---|---|
| Code-Anker | Funktionsnamen stimmen größtenteils, aber `persistHandover` liegt bei `1672`, nicht bei `1697`; `1697` ist nur der RETURN-Kommentar. Signatur ist aktuell ohne `$returnSlot`. | `Presenters/ZhlBookPresenter.php:69`, `:112`, `:1475`, `:1530`, `:1672`, `:1697`, `:1836`, `:1841` | Spec präzisieren: `persistHandover(..., string $loanEndUtc): bool` bei `:1672`; RETURN-Insert ab `:1697`. |
| Verlängerung nach vorn | Anchor `341-366` stimmt exakt für Abhol-/Zusammen-Verlängerung. | `Presenters/ZhlBookPresenter.php:341` | OK. |
| Pickup-Picker | Template-Anker `187-220` stimmt; Picker nutzt `name="pickup_slot"` und `zhl-pickup-day`/`zhl-pickup-pill`. | `tpl/zhl-book.tpl:187`, `:198`, `:204`, `:205` | OK als Klon-Vorlage. |
| Filter-Inversion | Spec sagt `return: startUtc >= returnFloorUtc`. Gegen echte Abholung ist das nicht streng spiegelbildlich: Pickup filtert primär `end_utc <= loanStart`, nur ohne `end_utc` per `start`. | `Presenters/ZhlBookPresenter.php:1557`, `:1558`, `:1561`, `:1562` | Für Rückgabe explizit entscheiden: Rückgabezeit = Slot-Start, dann `start >= floor` ist fachlich ok; aber Spec sollte sagen, dass `end_utc` nur Persistenz/Anzeige ist. |
| Filter-Widerspruch | AK-2 sagt Slots `>= Ausleihende`, AK-8/Presenter-Teil sagt Tagesanfang des Endtags. Das widerspricht sich im Tagesmodus. | `docs/zhl/SPEC-RUECKGABE.md:60`, `:122`, `:134` | AK-2 ändern zu `>= Rückgabe-Floor`; Floor je Modus definieren: Tagesmodus Endtag 00:00, Slotmodus tatsächliches Enddatum/-zeit. |
| 1-Tages-Ausleihe | Risiko real: Bei Tagesmodus wird Pickup gegen Startzeit-Proxy `09:00` gefiltert, Return-Floor laut Spec aber `00:00`; dadurch könnten Rückgabe-Slots vor Abholung am selben Tag angeboten werden. | `Presenters/ZhlBookPresenter.php:87`, `:91`, `docs/zhl/SPEC-RUECKGABE.md:60` | Same-Day nur ab `max(Tagesanfang Endtag, pickupPlan.end_utc/start_utc oder reservBegin)` anbieten, mindestens bei `beginDate == endDay`. |
| Reservierungsende | Spec behauptet Pool `pickFreeUnit` nutze ohnehin `reservEndDate/Time`; im Code existieren diese Variablen noch nicht. Pool und Facade nutzen aktuell `$endDate/$endTime`. | `Presenters/ZhlBookPresenter.php:375`, `:376`, `:393` | Neue `$reservEndDate/$reservEndTime` vor Pool-Zuteilung berechnen und an Pool, Facade und `loanEndUtc`-Berechnung durchreichen. |
| Native Prüfung | Native Verfügbarkeit schlägt über `ZhlReservationFacade` durch, aber nur wenn dessen Konstruktor das verlängerte Ende bekommt. | `Presenters/ZhlBookPresenter.php:393` | Keine separate native Änderung nötig, aber alle späteren `$endDate/$endTime`-Verwendungen auditieren. |
| Phase C / `loanEndUtc` | Nach Phase C wird `loanEndUtc` aktuell aus ursprünglichem `$endDate/$endTime` gebaut und in RETURN gespeichert. | `Presenters/ZhlBookPresenter.php:442`, `:471`, `:474`, `:1702`, `:1703` | Bei Return-Slot echte Slotzeiten in RETURN-Zeile schreiben; für Fallback weiter ursprüngliches/verlängertes Ende bewusst definieren. |
| AjaxSlots `end` | Server kennt nur `start`/`time`; Response hat nur `pickup` und `einf`. | `Presenters/ZhlBookPresenter.php:81`, `:86`, `:108` | `end` optional machen. Fehlt `end`, alte Response kompatibel halten und Return entweder `null` oder aus `start` ableiten. |
| JS-Trigger | JS lädt Slots nur mit Start: Monatsmodus über `lo`, Wochenmodus über Start-Zelle. Endtag/-zeit wird nicht übergeben. | `tpl/zhl-book.tpl:407`, `:444`, `:524`, `:537` | `loadSlots(start,time,end,endTime)` erweitern; `applyRange(lo, hi)` und Wochenraster-Ende `sE` übergeben. |
| Pflicht-Gate / AK-9 | Isolierbar, aber Rückgabe muss neben bestehendem `pickupMandatory`, Hauspost und `combined` sauber getrennt bleiben. Hauspost wird aktuell früh validiert und soll Return überspringen. | `Presenters/ZhlBookPresenter.php:240`, `:249`, `tpl/zhl-book.tpl:476` | Eigenen `$returnMandatory`, `$returnNoSlots`, `returnBlocked` im JS; nicht in Pickup-/Combined-State einmischen. |
| Storno-Schleife | Die Schleife ist tatsächlich generisch über alle Handover-Zeilen und storniert jede Zeile mit `booking_id`. Kommentar `Rückgabe-Zeile: kein Terminplaner-Slot` wird nach Rückgabe-Slot aber falsch. Warnlabel nennt Return fälschlich „Abholtermin“. | `Presenters/ZhlBookingDetailPresenter.php:262`, `:263`, `:266`, `:272` | Funktional läuft Return mit, sobald `terminplaner_booking_id` gesetzt ist; Kommentar und Label um `return => Rückgabetermin` ergänzen. |
| Storno-Load | Handover-Zeilen werden ohne Type-Filter geladen, also `return` ist dabei. | `Presenters/ZhlBookingDetailPresenter.php:351`, `:355` | OK. |
| SPEC-CANCEL Drift | SPEC-CANCEL behauptet noch „Rückgabe ist kein eigener Terminplaner-Slot“ und `cert_confirmation` werde verworfen; Code verwirft sie bewusst nicht. | `docs/zhl/SPEC-CANCEL.md:13`, `:14`, `Presenters/ZhlBookingDetailPresenter.php:278`, `:280` | SPEC-CANCEL mit Ist-Code synchronisieren. |
| Migration 025 | `025` ist frei; höchste vorhandene Migration ist `024_zhl_cancelled_booking.sql`. | `docs/zhl/migrations/024_zhl_cancelled_booking.sql`, Dateiliste | `025_zhl_rueckgabe.sql` passt. |
| Migration-Muster | `ADD COLUMN IF NOT EXISTS` passt zum lokalen Muster; 016 nutzt genau dieses Muster plus `MODIFY` für ENUM. | `docs/zhl/migrations/016_zhl_rueckgabeort.sql:13`, `:16`, `:21`; `docs/zhl/migrations/020_zhl_bundle_alt_mode.sql:8` | Für `rueckgabe` ok; Seed mit `ON DUPLICATE KEY UPDATE` idempotent halten. |
| Admin | Admin pflegt aktuell `abholung`, `einfuehrung`, `hauspost`, `abholort`, `rueckgabeort`, aber kein `rueckgabe`. | `Web/zhl-uebergabe-admin.php:46`, `:72`, `:73`, `:199`, `:203` | Spec-Task Admin ist nötig; Insert/Select/UI um `rueckgabe` erweitern. |
| Bundle | Spec nennt Bundle nur als Prüfung/Folge; Code hat eigene `persistHandover`-Kopie und RETURN-Fallback. | `Presenters/ZhlBundleBookPresenter.php:1391`, `:1414` | Vor Bau klären, ob Bundle in Scope ist. Sonst explizit „nicht in diesem Ticket“ und kein AK für Bundles. |
| Rückgabeort-Snapshot | Spec sagt „Snapshot bleibt unverändert (016-Logik)“, aber aktuelles `persistHandover` schreibt keinen `rueckgabeort`; `zhl-handover-lib` joint live auf `zhl_uebergabe`. | `Presenters/ZhlBookPresenter.php:1698`, `Web/zhl-handover-lib.php:301`, `:303` | Spec korrigieren: derzeit kein Snapshot in `zhl_booking_handover`, sondern Live-Join. Oder Migration/Spalte explizit ergänzen. |

**Blocker**

- Die Rückwärtsverlängerung ist in der Spec an der entscheidenden Stelle falsch behauptet: Pool und Facade nutzen aktuell `$endDate/$endTime`, nicht `reservEndDate/Time`.
- AK-2 und AK-8 widersprechen sich; ohne saubere Floor-Definition drohen Same-Day-Slots vor Abholung.
- `rueckgabeort`-„Snapshot“ ist laut Code nicht vorhanden.

**Sollte vor Bau geklärt werden**

- Gilt Rückgabezeit fachlich als Slot-Start oder Slot-Ende? Danach Filterregel finalisieren.
- Bundle-Scope: gleicher Rückgabe-Picker jetzt mitbauen oder bewusst ausklammern.
- Hauspost-Rückgabe: Spec sagt entfällt, bestehender Hauspost-Pfad hat aber eigene `rueckhol_fenster`; Abgrenzung festhalten.
- Fallback, wenn `end` im AJAX fehlt: alte Clients/initiale Render sollten nicht blockieren.

**Nice-to-have**

- SPEC-CANCEL aktualisieren, weil sie beim Storno und `cert_confirmation` vom Ist-Code abweicht.
- Storno-Warnlabel für `return` sauber benennen.
- Tests gezielt für 1-Tages-Ausleihe, Endtag-Same-Day und Return-Slot-Storno ergänzen.
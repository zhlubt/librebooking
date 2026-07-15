| Stelle | Befund | Schwere (blocker/major/minor/ok) | Empfehlung |
|---|---|---:|---|
| Task E1 `collectAltChoices` / AJAX | `$_REQUEST` passt für POST + GET-AJAX; Template sendet `alt_*` bei Radio-Änderung nach. | ok | Beibehalten. |
| Task E1 choice vs. auto | `choice` wird bei gültiger Wahl auf gewählten `type_label` reduziert; `auto` bleibt gruppenweit als Alternativmenge erhalten. Das ist konsistent mit UI/Resolver-Grundidee. | ok | Keine Änderung nötig. |
| Task E1 ungültige Wahl | Ungültige `alt_*` wird nicht als ungültig erkannt, sondern filtert alle Optionen der Gruppe weg. Ergebnis: eher zu locker/ungefiltert, nicht zu streng; POST-Resolver lehnt später ab. Kein False-Negative für legitime Slots, aber kein sauberer Mirror des Resolvers. | minor | Ungültige Wahl explizit gegen Gruppenoptionen prüfen; bei ungültig wie “fehlend” behandeln oder AJAX-Fehler liefern. |
| Task E1 fehlende Wahl | Fehlende Wahl lässt alle Optionen als Alternativen stehen. Das verschärft nicht und bricht Altclients/Kalender nicht. | ok | So vertretbar. |
| Task E1 Kalenderpfad | `monthGridForBundle()` ruft `bundleRequirements()` ohne `$altChoices`; dank Default bricht nichts. Kalender kann bei choice-Gruppen optimistischer sein als die konkret gewählte Option, aber finale AJAX/POST-Prüfung fängt das ab. | ok | Akzeptieren oder später Kalender an Auswahl koppeln, falls UX stört. |
| Task E2 `ReservationHandler::Handle` | `Persist()` bleibt außerhalb des neuen Notify-Catchs; Persistenzfehler werden weiter geworfen. Nach Commit wird Notify best-effort behandelt. Semantik für Doppel-Submit-Vermeidung ist korrekt. | ok | Beibehalten. |
| Task E2 Upstream-Divergenz | Änderung wirkt global auf alle Reservation-Actions, nicht nur ZHL. Allerdings schluckt `ReservationNotificationService` Einzel-Notification-Exceptions bereits selbst; neu abgefangen werden v. a. service-weite `Exception`s nach Persist. | minor | Fork-Divergenz bewusst dokumentieren; kein Blocker. |
| Task E3 Pool-TOCTOU-Hinweis | Nur zusätzlicher Nutzerhinweis bei `WasSaved=false` und Pool > 1; keine Persistenz-/Validierungslogik geändert. | ok | Harmlos. |
| Task D Harness | Lädt echte `ZhlBundleResolver`/`ZhlBundleService` und testet den Shure→Yeti-Auto-Fallback lokal mit 9/9 PASS. DB-`Execute()` wirft. | ok | Für den genannten Fall ausreichend. |
| Task D Harness-Abdeckung | Stubs spiegeln nur den typbasierten Shure/Yeti-Fall; keine Abdeckung für `specific_resource_id`, Same-Schedule-Kanten, gemischte Gruppen oder echte DB-Typmap-Details. | minor | Nicht als allgemeiner Resolver-Test verkaufen; Scope im Doc eng halten. |
| Task F Harness Schreibzugriffe | SQL-Pfade sind `SELECT`/`SHOW COLUMNS`; keine `INSERT/UPDATE/DELETE/Execute`. | ok | Read-only im SQL-Sinn. |
| Task F DB-Erkennung | Erkennung ist pfadbasiert und prüft media vor meet. Falls im meet-Container ebenfalls `/var/www/html/config/config.php` existiert, kann falsch klassifiziert werden. Includes können außerdem theoretisch Side Effects haben. | minor | Robuster über Schema-Probe (`SHOW TABLES`/bekannte Tabellen) statt Pfadpriorität erkennen. |
| Task F Zielgenauigkeit | Doku sagt Titel-Heuristik „Aufnahme“, der Code filtert aber nicht nach Titel. Dadurch kann ein anderer Storno desselben Users im Fenster als Erfolg zählen. | major | Titel-/Reference-Eingrenzung ergänzen oder Ergebnis als manuell zu verifizierende Kandidatenliste ausgeben. |
| D2 Nicht-bauen-Entscheidung | Fachlich begründet: Mass-False-Positive-Risiko, fehlende Schema-Migration/Index, fehlendes Admin-Session-Plumbing, fehlender Backfill/operativer Rückgabeprozess. | ok | Entscheidung ist vertretbar; erst nach Migration, Backfill und operativem Rückgabeprozess bauen. |

**Gesamturteil**

Kein Blocker in den Task-E-Codeänderungen. E1/E2/E3 sind semantisch tragfähig; E1 hat nur eine kleine Unsauberkeit bei manipulierten ungültigen `alt_*`-Werten, die nicht zu falscher Slot-Entfernung führt.

Größter Befund ist Task F: Das Harness ist read-only, aber als Verifikationsbeweis nicht robust genug, weil der dokumentierte Titelfilter fehlt und die DB-Erkennung heuristisch ist. D2 ist überzeugend als “noch nicht bauen” begründet.
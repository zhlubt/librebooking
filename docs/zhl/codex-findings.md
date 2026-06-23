| Feature/Plan | Befund | Beleg (Pfad) | Empfehlung |
|---|---|---|---|
| F1 Registrierung/Profile ✅🟦 | Im Kern korrekt: Domain-Restrict, Aktivierung, ToS existieren. „ToS-Accept-Zeitpunkt Custom-Mini“ wirkt zu pessimistisch, da `users.terms_date_accepted` genutzt wird. | `lib/Config/ConfigKeys.php:1012`, `:1414`; `database_schema/create-schema.sql`; `lib/Database/Commands/Queries.php:991` | ToS-Persistenz vor Custom nochmal prüfen; wahrscheinlich Config/Bestand reicht. |
| F2 Rollen/Rechte ✅ | Korrekt: Rollen und Admin-Level nativ. | `database_schema/create-schema.sql:74`, `:90`; `Domain/Values/RoleLevel.php` | Keine Core-Anpassung nötig. |
| F3 User-Verwaltung ✅ | Korrekt. | `Pages/Admin/ManageUsersPage.php`; `Presenters/Admin/ManageUsersPresenter.php`; `lib/Application/User/ManageUsersService.php` | Nativ nutzen. |
| F4 Item/Inventar-CRUD ✅ | Korrekt als Ressourcen-CRUD. | `Pages/Admin/ManageResourcesPage.php`; `Presenters/Admin/ManageResourcesPresenter.php`; `Domain/BookableResource.php` | Nativ nutzen. |
| F5 Custom-Attribute ✅ | Korrekt für User/Resource/ResourceType/Reservation. | `WebServices/Controllers/AttributeSaveController.php:186`; `lib/Application/Attributes/AttributeService.php` | Für ZHL-Flags bevorzugt Custom Attribute statt Core-Spalten, wenn keine harte Query-Performance nötig ist. |
| F7 Status/Verfügbarkeit ✅ | Korrekt: Resource-Status + Blackouts. | `database_schema/create-schema.sql:357`; `Pages/Admin/ManageBlackoutsPage.php`; `Pages/Admin/ManageResourceStatusPage.php` | Nativ nutzen. |
| F10 QR-Verifikation ✅🔧 | Status ist missverständlich: QR und Check-in/out nativ, echte Übergabe-Verifikation/Checkliste/Zustand nicht. | `Presenters/Admin/ManageResourcesPresenter.php:891`; `Pages/ResourceQRRouterPage.php:13`; `Presenters/Reservation/ReservationCheckinPresenter.php:52` | In Liste als „QR nativ, Verifikation Custom“ führen. Eigene ZHL-Seite ist richtig. |
| F11 Multi-Item-Buchung ✅ | Korrekt: Additional Resources existieren. | `Domain/ReservationSeries.php:136`; `lib/Application/Reservation/ReservationComponentBinder.php:196` | Nativ nutzen. |
| F12 Buchungs-Dashboard ✅ | Korrekt. | `Pages/DashboardPage.php`; `Presenters/DashboardPresenter.php`; `Presenters/Dashboard/*ReservationsPresenter.php` | Nativ nutzen, nur UX vereinfachen. |
| F13 Kalenderübersicht ✅ | Korrekt. | `Pages/CalendarPage.php`; `Presenters/Calendar/CalendarPresenter.php`; `Pages/ViewCalendarPage.php` | Nativ nutzen. |
| F14 Approval-Workflow ✅ | Korrekt pro Ressource. | `lib/Application/Reservation/Validation/RequiresApprovalRule.php:24`; `database_schema/create-schema.sql:197` | Nativ nutzen. |
| F15 Vorlaufzeiten ✅ | Korrekt für Add/Update/Delete und Max Notice. | `lib/Application/Reservation/Validation/ResourceMinimumNoticeRuleAdd.php:28`; `ResourceMinimumNoticeRuleUpdate.php`; `ResourceMinimumNoticeRuleDelete.php` | Nativ nutzen. |
| F18 Blackout-Daten ✅ | Korrekt. | `Pages/Admin/ManageBlackoutsPage.php`; `lib/Application/Reservation/ManageBlackoutsService.php`; `database_schema/create-schema.sql:357` | Nativ nutzen. |
| F20 iCal-Feed ✅🟦 | Korrekt, aber Subscription-Key/Allow-Flags sind Config/Admin-Thema. | `lib/Config/ConfigKeys.php:1085`; `lib/Application/Schedule/CalendarSubscriptionValidator.php:46`; `Presenters/Admin/ManageResourcesPresenter.php:527` | Config prüfen, öffentliche Feeds datenschutzseitig absichern. |
| F21 E-Mail-Benachrichtigungen ✅ | Korrekt für Standard-Events. | `lib/Application/Reservation/Notification/PostReservationFactory.php:48`; `lib/Email/Messages/ReservationCreatedEmail.php` | Nativ nutzen; Sonderfälle per Plugin/Custom-Mail. |
| F22 Mailtemplates DE / per-Item ✅🟦/🔧 | Korrekt: sprach-/custom-template nativ, per Resource nicht nativ. | `lib/Common/SmartyPage.php:89`; `lib/Email/EmailMessage.php:42` | DE-Templates über `*-custom.tpl`; per Item nicht als nativ verkaufen. |
| F26 Mehrsprachigkeit ✅🟦 | Korrekt. | `config/config.dist.php:47`; `lib/Config/ConfigKeys.php:69`; `lang/de_de.php` | Config statt Code. |
| F28 REST-API ✅🟦 | Korrekt, nativ vorhanden und per Config schaltbar. | `WebServices/`; `lib/Config/ConfigKeys.php:1793` | Nur aktivieren, wenn Auth/Token/Exposure geklärt sind. |
| F29 Analytics/Reports ✅ | Reports korrekt; Analytics eher Config/Tracking-ID, nicht „Reports“. | `Pages/Reports/GenerateReportPage.php`; `Presenters/Reports/GenerateReportPresenter.php`; `lib/Config/ConfigKeys.php:1372` | Trennen: Reports nativ, Analytics Config/extern. |
| F31 Max. Buchungsdauer ✅ | Korrekt. | `lib/Application/Reservation/Validation/ResourceMaximumDurationRule.php:19` | Nativ nutzen. |
| F32 User-Buchungslimits ✅ | Korrekt über Quotas. | `Pages/Admin/ManageQuotasPage.php`; `Presenters/Admin/ManageQuotasPresenter.php`; `database_schema/create-schema.sql:412` | Nativ nutzen. |
| F33 Storno-Fristen ✅ | Zu optimistisch: Mindestfristen existieren, aber kein echter Storno-Workflow. | `lib/Application/Reservation/Validation/ResourceMinimumNoticeRuleDelete.php`; `docs/zhl/FEATURES.md:41` | Status auf 🟨 ändern, wenn ZHL einen Storno-Prozess/Staff-Notification meint. |
| F39 Mobile-Responsive ✅ | Plausibel: Bootstrap 5 ist gebündelt; echte mobile UX trotzdem testen. | `Web/assets/vendor/bootstrap/5.3.3/`; Templates unter `tpl/` | Nativ nicht blind als UX-Erfüllung werten; Smoke-Test mobil. |
| F40 Einweisungs-/Berechtigungspflicht ✅✅ | Funktional gebaut, aber nicht nativ: Custom Permission-Plugin plus ConfigKeys-Core-Edit. | `plugins/Permission/ZhlCertificate/ZhlCertificate.php`; `lib/Config/ConfigKeys.php:1725`; `config/config.dist.php:725` | Status als „Custom auf nativer Permission-Extension“ führen; Core-Edit klein halten. |
| STRATEGY: „wenige echte Funktionslücken“ | Inzwischen zu optimistisch: Übergabe-Modul, Overdue, F40, Audit, DSGVO, Suche sind mehrere Custom-Stränge. | `docs/zhl/STRATEGY.md:91`; `docs/zhl/FEATURES.md:50` | Strategie aktualisieren: Custom-Overlay ist ein eigener Produktbereich, nicht Randarbeit. |
| STRATEGY: DB-Änderungen nur `database_schema/upgrades/` | Widerspruch zur ZHL-Praxis: Migrationen liegen unter `docs/zhl/migrations/`. | `docs/zhl/STRATEGY.md:55`; `docs/zhl/migrations/002_zhl_handover.sql` | Entweder Strategie anpassen oder ZHL-Migrationen in Upstream-konformes Upgrade-Verzeichnis spiegeln. |
| Overdue Job-Pattern | Grundsätzlich korrekt: `ROOT_DIR`, `JobCop`, `ServiceLocator`, `AdHocCommand`, Parameter, `Query`/`Execute`. | `Jobs/zhl_overdue.php:17`, `:22`, `:85`, `:90`, `:99`, `:194`; `Jobs/sendmissedcheckin.php` | Pattern ok; zusätzlich Abschluss-Log und early return bei disabled email erwägen. |
| `ZhlOverdueEmail extends EmailMessage` | Korrekt: eigene Property-Namen kollidieren nicht, `parent::__construct()` wird gerufen, `To/Subject/Body` implementiert. | `Jobs/zhl_overdue.php:32`; `lib/Email/EmailMessage.php:8`, `:20` | Ok; besser per Smarty-Template für Lokalisierung/Branding. |
| Overdue Detection Query | Korrekt für `type='return'`, nicht `done`, `scheduled_end_utc + 24h`. | `Jobs/zhl_overdue.php:89` | Ok; optional `reference_number IS NOT NULL` prüfen, falls Pre-Reservation-Reste nicht mahnen sollen. |
| Eskalationslogik | Korrekt zeitbasiert: sendet höchste fällige Stufe, wenn `targetStage > maxSent`; überspringt verpasste Stufen bewusst. | `Jobs/zhl_overdue.php:114`, `:126`, `:136` | Ok, aber fachlich bestätigen: übersprungene Stufen bedeuten weniger Mails, nicht „1/3/7 alle“. |
| Mail-Fehler-Handling | Pro Item abgefangen; Stufe wird bei Fehler nicht protokolliert. | `Jobs/zhl_overdue.php:164` | Ok. Spam-Risiko bei dauerhaftem Mailfehler bleibt als Retry pro Lauf. |
| Zeitzone/UTC | Im Kern sauber: `Date::Now()->ToDatabase()` ist UTC; `scheduled_end_utc` wird als UTC gelesen. | `Jobs/zhl_overdue.php:99`, `:110`; `lib/Common/Date.php:194` | Ok; Anzeige in Mail ist aktuell UTC, für Nutzer besser lokale Zeitzone formatieren. |
| User-Sperre `status_id=2` | Falsch. `2` ist Awaiting Activation, `3` ist Inactive. Aktuell nur wegen `ZHL_OVERDUE_LOCK_USER=false` nicht akut. | `Jobs/zhl_overdue.php:198`; `Domain/Values/AccountStatus.php:10`; `database_schema/create-data.sql:1` | Vor Aktivierung zwingend auf `AccountStatus::INACTIVE` bzw. `3` ändern, keine Magic Number. |
| Migration 005 | Grundidee sinnvoll, Unique `handover_id+stage` verhindert Doppel je Stufe. | `docs/zhl/migrations/005_zhl_overdue_notice.sql:6`, `:14` | `handover_id` sollte `NOT NULL` sein oder zusätzlich Unique auf Token/Reference, sonst erlaubt MySQL mehrere `NULL`-Duplikate. FK/Index auf `handover_id` erwägen. |
| `Web/zhl-cron.php` | Einbindung ok und kein User-Input im Jobpfad; Token mit `hash_equals`. | `Web/zhl-cron.php:21`, `:33`, `:40`, `:60` | Ok; aber Web-Cron kann parallel laufen und alle Jobs öffentlich triggern, Rate/Locking ergänzen. |
| Race Condition Overdue | Lücke: `SELECT MAX(stage)` und später `INSERT` sind nicht atomar; parallele Cron-Läufe können Duplicate-Key-Exception werfen und den äußeren Job abbrechen. | `Jobs/zhl_overdue.php:126`, `:184`; `docs/zhl/migrations/005_zhl_overdue_notice.sql:14` | Insert atomar machen (`INSERT IGNORE`/Transaktion/Lock) und Duplicate pro Item abfangen. |

## Fehlt

- STRATEGY sollte das Übergabe-Modul als eigenes ZHL-Subsystem führen: Tabellen, Cron, Security, Admin-UI, Betrieb, Tests, Rollback.
- Overdue braucht Admin-Transparenz: Liste versandter Mahnstufen, manueller Reset/Stop, Audit-Kommentar.
- Konfigurierbarkeit fehlt: Grace Hours, Stage Days, Locking, Empfänger/CC sollten nicht hart im Job stehen.
- Datenschutz/Betrieb fehlen als Gates vor Live: öffentliche Cron-URL, API/ICS-Exposure, Mailinhalt, Logging personenbezogener Daten.
- Tests fehlen für Overdue: Stage-Berechnung, Duplicate/Parallel-Lauf, Mailfehler, `status_id`, UTC-Grenzen.

## Risiken

- Größtes Korrektheitsrisiko: Auto-Sperre würde mit `status_id=2` den falschen Status setzen.
- Größtes Betriebsrisiko: parallele `zhl-cron.php`-Aufrufe können Overdue-Duplicate-Key-Fehler erzeugen; äußerer Catch beendet dann potenziell den Restlauf.
- Spam-Risiko: Bei Mailfehler wird nicht protokolliert, daher Retry bei jedem Cronlauf; sinnvoll, aber braucht Backoff oder Fehlerstatus.
- Upgrade-Risiko: ZHL-Migrationen liegen außerhalb des LibreBooking-Upgradepfads; Runbook muss garantieren, dass sie in richtiger Reihenfolge laufen.
- Core-Risiko: F40 hat einen ConfigKeys-Core-Edit für Plugin-Choices. Wenn möglich, Plugin-Auswahl ohne Core-Whitelist oder mit klar markiertem Mini-Patch halten.
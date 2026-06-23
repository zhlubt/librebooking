| Feature/Plan | Befund | Beleg (Pfad) | Empfehlung |
|---|---|---|---|
| F1 Registrierung/Profile | Im Kern korrekt, aber nicht rein „nativ": Domain/Activation sind Config, ToS-Accept-Zeitpunkt pro User fehlt. | `config/config.dist.php:445`, `:618`; `database_schema/upgrades/2.7/schema.sql:92` | Als `✅🟦 + Custom-Mini` lassen. |
| F2 Rollen/Rechte | Korrekt nativ: Rollen/Application/Group/Resource/Schedule Admin vorhanden. | `database_schema/create-data.sql:3`; `Presenters/Admin/ManageGroupsPresenter.php` | Passt. |
| F3 User-Verwaltung | Korrekt nativ. | `Pages/Admin/ManageUsersPage.php`; `Presenters/Admin/ManageUsersPresenter.php` | Passt. |
| F4 Item/Inventar-CRUD | Korrekt nativ über Resources, aber „Inventar" meint nur Ressourcen, keine Seriennummern/Zustand. | `Pages/Admin/ManageResourcesPage.php`; `Presenters/Admin/ManageResourcesPresenter.php` | Status beibehalten, Scope klarer nennen. |
| F5 Custom-Attribute | Korrekt nativ. Kategorien Reservation/User/Resource/Type stimmen. | `Domain/CustomAttribute.php:12` | Passt. |
| F7 Status/Verfügbarkeit | Korrekt nativ. Resource-Status + Blackouts vorhanden. | `lib/Application/Reservation/ResourceStatusFilter.php`; `Pages/Admin/ManageBlackoutsPage.php` | Passt. |
| F10 QR-Verifikation | Zu optimistisch: QR-Code und Check-in/out nativ, aber Verifikation/Checkliste/Zustand sind Custom. | `Presenters/Admin/ManageResourcesPresenter.php:891`; `Pages/ResourceQRRouterPage.php`; `Pages/Ajax/ReservationCheckinPage.php` | Nicht als voll `✅` formulieren, sondern `✅ QR/Check-in, 🔧 Checkliste`. |
| F11 Multi-Item-Buchung | Korrekt nativ über Additional Resources. | `Domain/ReservationSeries.php:161`; `Presenters/Reservation/ReservationUpdatePresenter.php:97` | Passt. |
| F12 Buchungs-Dashboard | Korrekt nativ. | `Pages/DashboardPage.php`; `Presenters/DashboardPresenter.php` | Passt. |
| F13 Kalenderübersicht | Korrekt nativ. | `Pages/CalendarPage.php`; `Presenters/Calendar/CalendarCommon.php:259` | Passt. |
| F14 Approval-Workflow | Korrekt nativ pro Ressource. | `database_schema/create-schema.sql:197`; `lib/Application/Reservation/Validation/RequiresApprovalRule.php` | Passt. |
| F15 Vorlaufzeiten | Korrekt nativ für Add/Update/Delete-Minimum und Max Notice. | `database_schema/upgrades/2.7/schema.sql:117`; `:120`; `:123` | Passt. |
| F18 Blackout-Daten | Korrekt nativ. | `database_schema/create-schema.sql:359`; `Pages/Admin/ManageBlackoutsPage.php` | Passt. |
| F20 iCal-Feed | Korrekt, aber Config/Key abhängig. | `config/config.dist.php:482`; `lib/Config/ConfigKeys.php:1085` | `✅🟦` ist passend. |
| F21 E-Mail-Benachrichtigungen | Korrekt nativ für Reservierungsereignisse. | `lib/Application/Reservation/Notification/AddReservationNotificationService.php`; `UpdateReservationNotificationService.php` | Passt. |
| F22 Mailtemplates DE/per Item | Teilweise zu optimistisch: Sprache/Custom-TPL nativ, per Resource nicht nativ. | `lib/Common/SmartyPage.php`; `lib/Email/Messages/ReservationEmailTemplateContext.php` | Status nicht als voll nativ lesen: `✅ Sprache, 🔧 per Resource`. |
| F26 Mehrsprachigkeit | Korrekt nativ/config. Default ist im Dist aber `en_us`. | `config/config.dist.php:47`; `lang/de_de.php` | Prod-Config explizit dokumentieren. |
| F28 REST-API | Korrekt nativ, default aus. | `WebServices/`; `lib/Config/ConfigKeys.php:1793` | `✅🟦` passt. |
| F29 Analytics/Reports | Reports nativ; Analytics ist nur Google-Tracking-Config, keine Produkt-/Nutzungsanalyse im ZHL-Sinn. | `Pages/Reports/GenerateReportPage.php`; `lib/Application/Reporting/ReportingService.php`; `config/config.dist.php:108` | Feature ggf. in Reports vs Analytics trennen. |
| F31 Max. Buchungsdauer | Korrekt nativ pro Ressource. | `database_schema/create-schema.sql:194` | Passt. |
| F32 User-Buchungslimits | Korrekt nativ über Quotas. | `database_schema/create-schema.sql:411`; `Pages/Admin/ManageQuotasPage.php` | Passt. |
| F33 Storno-Fristen | Korrekt, aber nur Mindestfrist/Rule, kein Storno-Workflow. | `database_schema/upgrades/2.7/schema.sql:123`; `ResourceMinimumNoticeRule*` | Empfehlung in FEATURES stimmt. |
| F39 Mobile-Responsive | Plausibel nativ über Bootstrap 5, aber nicht codebelegt als UX-Qualität. | `tpl/`; Bootstrap-Nutzung breit im Repo | Mobiltest als Pflicht behalten. |
| F40 Einweisungs-/Berechtigungspflicht | Nicht nativ. ZHL-Custom-Plugin ist umgesetzt; Status `✅✅` ist als „fertig" ok, aber nicht als „nativ". | `plugins/Permission/ZhlCertificate/ZhlCertificate.php`; `lib/Config/ConfigKeys.php:1725` | In FEATURES klar `🔧 umgesetzt` statt `✅ nativ` nennen. |
| STRATEGY Säule 1 | „Keine Breaking-DB-Changes" ist riskant formuliert; Repo enthält viele Upgrades bis 4.0, aber Live 4.0 -> 5.1 muss trotzdem gegen echte DB rehearsed werden. | `database_schema/upgrades/`; `docs/zhl/UPGRADE-RUNBOOK.md` | Aussage abschwächen: Schema-Upgrade prüfen, nicht voraussetzen. |
| STRATEGY Core-Edits | `ConfigKeys.php` wurde für Plugin-Choices gepatcht. Das ist klein, aber upstream-anfällig. | `lib/Config/ConfigKeys.php:1725`, `:1750` | Prüfen, ob Config-Validation Plugin-Namen auch ohne Core-Whitelist erlauben kann; sonst markiert lassen. |
| Handover Plugin-Interface | Formal korrekt: `IPreReservationFactory` und `IReservationValidationService` Signaturen passen; Constructor passt zu `PluginManager::LoadPlugin`. | `plugins/PreReservation/ZhlHandover/ZhlHandover.php:18`; `lib/Application/Reservation/Validation/PreReservationFactory.php:3`; `lib/Common/PluginManager.php:229` | Passt. |
| Handover Add/Update Gate | Greift vor Persistenz für Add und Update. | `ZhlHandover.php:29`, `:35`; `ReservationValidationFactory.php:30`, `:36`; `ReservationHandler.php:91` | Passt. |
| Handover Resource-Ermittlung | `AllResources()` ist korrekt für Haupt- und Zusatzressourcen. | `ZhlHandoverValidation.php:49`; `Domain/ReservationSeries.php:161` | Passt. |
| Handover Attribute | Kategorien 1/4 stimmen; `GetAttributeValue()` liest Reservation-Attributwerte aus der Series. | `Domain/CustomAttribute.php:14`, `:17`; `ReservationSeries.php:609`; `ZhlHandoverValidation.php:61` | Passt. |
| Handover SQL custom_attribute_values | Query ist schema-kompatibel: `entity_id`, `custom_attribute_id`, `attribute_category`. | `ZhlHandoverValidation.php:100`; `lib/Database/Commands/Queries.php:36`, `:532` | Passt. |
| Handover Fehlerresultat | Mögliche Schwäche: `ReservationValidationResult(false, $message)` übergibt String statt Array. Upstream-Beispiele machen das auch, Template nutzt aber `{foreach from=$Errors}`. | `ZhlHandoverValidation.php:69`; `tpl/Ajax/reservation/save_failed.tpl:9` | Robuster: `new ReservationValidationResult(false, [$message])`. |
| Handover Upsert/Race | `UNIQUE (handover_token,type)` verhindert doppelte Pickup/Return-Zeilen, aber nicht Multi-Resource/Instance-Semantik; Transaktion pro Zeile ist ok, aber nicht atomar für beide Typen. | `002_zhl_handover.sql:27`; `Web/zhl-handover-lib.php:108` | Für Phase A ok; für echte Geräteübergabe Constraint um `resource_id`/`reservation_instance_id` erweitern oder bewusst „pro Reservierung" dokumentieren. |
| Handover Select Security | Kritisch offen: kein `SecurePage`, keine Session-Bindung, Sync per GET ohne CSRF; Kommentar nennt TODO selbst. | `Web/zhl-handover-select.php:19`, `:33` | Vor produktivem Einsatz an Login/Reservation-Owner binden, Sync auf POST+CSRF. |
| Handover Token-Handling | Token ist stark zufällig, aber wer Token kennt, kann Status sehen und syncen. Keine Bindung an User/Reservation. | `Web/zhl-handover-select.php:26`; `Web/zhl-handover-lib.php:152` | Token zusätzlich serverseitig an User/Reservation-Intent binden. |
| Handover Notify Security | API-Key-Prüfung mit `hash_equals` ok; GET-Key fallback leakt Secret in Logs/URLs. | `Web/zhl-handover-notify.php:21` | Nur Header erlauben, GET-`key` entfernen, Rate-Limit/Logging ergänzen. |
| Handover Config-Fallback | Fallback auf `.example.php` kann Fehlkonfiguration kaschieren. | `Web/zhl-handover-lib.php:23` | In Prod hart fehlschlagen, wenn echte Config fehlt. |
| Handover externe Assets | Bootstrap CDN in ZHL-Webdatei widerspricht Repo-Hinweis „lokal gebündelte Assets bevorzugen". | `Web/zhl-handover-select.php:65` | Lokale Vendor-Assets nutzen. |

**Fehlt**

- PostReservation-Verknüpfung für `reference_number`, `series_id`, `reservation_instance_id`, `resource_id`; Migration sieht Felder vor, Phase A füllt sie nicht.
- Tests für Handover-Plugin im PHPUnit-Stil, nicht nur SQL-Verifikation.
- Explizite Strategie für Bestandsbuchungen ohne Token und für Updates bestehender übergabepflichtiger Buchungen.
- Admin-/Betriebs-UI für offene/bestätigte/done Übergaben, sonst bleibt `zhl_booking_handover` unsichtbar.
- Datenschutz/Audit konkretisieren: wer hat welches Token erzeugt, wer hat synchronisiert, wer hat Übergabe bestätigt.

**Risiken**

- Größtes Phase-A-Risiko ist nicht das PreReservation-Gate, sondern die offene Hilfsseite `zhl-handover-select.php`: ohne Loginbindung kann sie als öffentlicher Token-/Sync-Endpunkt genutzt werden.
- `uq_token_type` ist für „eine Reservierung, zwei Termine" ausreichend, aber nicht für Multi-Resource oder instanzgenaue Übergaben. Das kollidiert mit der eigenen SPEC.
- Core-Edits in `ConfigKeys.php` sind klein, aber bei Upstream-Merges konfliktträchtig; als ZHL-Core-Edit sauber markiert lassen.
- Mehrere `✅` in FEATURES bedeuten faktisch „vorhandener Baustein", nicht „ZHL-Anforderung vollständig erfüllt" - besonders F10, F22, F29, F40.
- PHP-Syntax der geprüften Handover-Dateien ist ok: `php -l` ohne Fehler.
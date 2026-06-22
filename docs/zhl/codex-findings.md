| Feature/Plan | Befund | Beleg (Pfad) | Empfehlung |
|---|---|---|---|
| F1 Registrierung/Profile | Nativ stimmt; Self-Registration und Profilfelder sind konfigurierbar. | `config/config.dist.php:419`, `lib/Config/ConfigKeys.php:938` | Als 🟦 markieren, wenn Live-Config gemeint ist. |
| F2 Rollen/Rechte | Nativ stimmt. Gruppenrollen plus Resource/Schedule/Admin-Logik vorhanden. | `database_schema/create-schema.sql:70`, `database_schema/create-data.sql:2`, `database_schema/upgrades/2.1/data.sql:1`, `Presenters/DashboardPresenter.php:34` | Behalten. |
| F3 User-Verwaltung | Nativ stimmt. | `Pages/Admin/ManageUsersPage.php:177`, `Presenters/Admin/ManageUsersPresenter.php:144` | Behalten. |
| F4 Item/Inventar-CRUD | Nativ stimmt: Resources sind das Inventar. | `Pages/Admin/ManageResourcesPage.php:466`, `Presenters/Admin/ManageResourcesPresenter.php:93`, `database_schema/create-schema.sql:183` | Behalten. |
| F5 Custom-Attribute | Nativ stimmt für User/Resource/Resource-Type/Reservation. | `Domain/CustomAttribute.php:3`, `WebServices/Controllers/AttributeSaveController.php:186` | Behalten; Reichweite dokumentieren. |
| F6 Kategorien | Zu optimistisch als ✅: Resource Groups und Resource Types existieren, aber keine frei modellierbare Kategorie-/Kachel-UX. | `Pages/Admin/ManageResourceGroupsPage.php:64`, `Pages/Admin/ManageResourceTypesPage.php:54`, `Domain/ResourceGroup.php:140` | Auf 🟨/🟦 setzen; Frontpage-Kategorien separat planen. |
| F7 Status/Verfügbarkeit | Nativ stimmt: Resource-Status, Blackouts, aktive/verborgene Ressourcen. | `Pages/Admin/ManageResourceStatusPage.php:17`, `Pages/Admin/ManageBlackoutsPage.php:251`, `database_schema/create-schema.sql:191` | Behalten. |
| F11 Multi-Item-Buchung | Nativ stimmt: Additional Resources pro Reservierung. | `tpl/Reservation/create.tpl:208`, `Domain/ReservationSeries.php:131`, `database_schema/create-schema.sql:340` | Behalten. |
| F12 Buchungs-Dashboard | Nativ stimmt. | `Pages/DashboardPage.php`, `Presenters/DashboardPresenter.php:34` | Behalten. |
| F13 Kalenderübersicht | Nativ stimmt; FullCalendar vorhanden. | `Pages/CalendarPage.php`, `Web/scripts/calendar.js:82`, `tpl/javascript-includes.tpl:73` | Behalten. |
| F14 Approval-Workflow | Nativ stimmt pro Ressource. | `database_schema/create-schema.sql:197`, `Domain/BookableResource.php:962`, `lib/Application/Reservation/Validation/RequiresApprovalRule.php:26` | Behalten. |
| F15 Vorlaufzeiten | Nativ stimmt; je Ressource für Add/Update/Delete und Max Notice. | `database_schema/upgrades/2.7/schema.sql:117`, `Presenters/Admin/ManageResourcesPresenter.php:290`, `Domain/BookableResource.php:1035` | Behalten. |
| F18 Blackout-Daten | Nativ stimmt. | `Pages/Admin/ManageBlackoutsPage.php:251`, `Domain/Blackout.php:186`, `lib/Application/Reservation/ManageBlackoutsService.php` | Behalten. |
| F20 iCal-Feed | Nativ stimmt, aber Subscription muss pro Schedule/Resource erlaubt sein. | `Pages/Export/CalendarSubscriptionPage.php`, `Presenters/CalendarSubscriptionPresenter.php:35`, `Domain/Schedule.php:160`, `Domain/BookableResource.php:1271` | Als ✅/🟦 präzisieren. |
| F21 E-Mail-Benachrichtigungen | Nativ stimmt für create/update/delete/approve. | `lib/Application/Reservation/Notification/AddReservationNotificationService.php:10`, `UpdateReservationNotificationService.php:10`, `DeleteReservationNotificationService.php:11`, `ApproveReservationNotificationService.php:10` | Behalten. |
| F26 Mehrsprachigkeit | Nativ stimmt, aber „Default umgestellt“ ist im Repo nicht belegt; `config.dist.php` bleibt `en_us`. | `config/config.dist.php:47`, `lang/de_de.php`, `lang/de_de/ReservationCreated.tpl` | Als 🟦 markieren: Config-Änderung nötig. |
| F28 REST-API | Nativ stimmt, aber default aus. | `lib/Config/ConfigKeys.php:1790`, `config/config.dist.php:746`, `WebServices/ReservationsWebService.php` | Als ✅/🟦 statt rein ✅ führen. |
| F29 Analytics/Reports | Nativ stimmt, Zugriff aber konfigurierbar/rollenabhängig. | `Pages/Reports/GenerateReportPage.php:117`, `Presenters/Reports/GenerateReportPresenter.php:50`, `lib/Config/ConfigKeys.php:925` | Behalten; Rollen klären. |
| F31 Max. Buchungsdauer | Nativ stimmt je Ressource. | `database_schema/create-schema.sql:192`, `database_schema/create-schema.sql:194`, `lib/Application/Reservation/Validation/ResourceMaximumDurationRule.php:3` | Behalten. |
| F32 User-Buchungslimits | Nativ stimmt über Quotas. | `database_schema/create-schema.sql:411`, `Pages/Admin/ManageQuotasPage.php:99`, `Domain/Quota.php:201` | Behalten. |
| F33 Storno-Fristen | Nativ stimmt für Mindestfrist vor Delete; kein komplexer Storno-Workflow. | `database_schema/upgrades/2.7/schema.sql:123`, `Presenters/Admin/ManageResourcesPresenter.php:292`, `Domain/BookableResource.php:1083` | Als ✅ nur für Fristen, nicht Workflow. |
| F35 Verlängerungen | Zu optimistisch als vollwertiges Feature: Reservierung ändern ist nativ, expliziter Verlängerungsprozess nicht. | `Presenters/Reservation/ReservationUpdatePresenter.php:76`, `Domain/ExistingReservationSeries.php:308` | Auf 🟨 setzen, falls ZHL echte Verlängerungslogik meint. |
| F36 Waitlist | Capability nativ, aber default aus; nicht „sofort nativ aktiv“. | `config/config.dist.php:341`, `lib/Config/ConfigKeys.php:755`, `database_schema/upgrades/2.6/schema.sql:202` | Als 🟦 markieren. |
| F39 Mobile-Responsive | Nativ plausibel: Bootstrap 5 und responsive DataTables eingebunden. | `tpl/globalheader.tpl:39`, `tpl/globalheader.tpl:46`, `Web/assets/vendor/bootstrap/5.3.3/metadata.json` | Behalten, aber UX mobil testen. |
| Strategie: Core/Templates direkt anpassen | Riskant formuliert. Viele UX/Branding-Punkte gehen über Config, `lang-overrides`, `css.extension.file`, Styling-Plugin. | `config/config.dist.php:47`, `config/lang-overrides.example.php`, `Pages/StylingPluginPage.php` | Core-Edits nur nach Plugin/CSS/Config-Ausschluss. |
| Strategie: Webhooks via Plugin | Stimmt als Custom-Pfad, nicht nativ. PostReservation-Plugin kann Hooks auslösen. | `config/config.dist.php:735`, `plugins/PostReservation/PostReservationExample/PostReservationExample.php:19` | F24 klar 🔧/Plugin nennen. |
| Strategie: E-Mail-Templates | Global/dateibasiert, nicht per Item. | `Presenters/Admin/ManageEmailTemplatesPresenter.php:36`, `Presenters/Admin/ManageEmailTemplatesPresenter.php:134` | F22 nicht als Per-Item verkaufen; Custom nötig für resource-spezifische Templates. |
| Strategie: Upgrade 4.0 → 5.1 keine DB-Breaks | Im Repo plausibel: Upgrade-Verzeichnisse enden bei 4.0; 5.1-Changelog vorhanden. Trotzdem Live-DB-Rehearsal Pflicht. | `database_schema/upgrades/4.0/data.sql:1`, `CHANGELOG.md:6` | Nicht „niedriges Risiko“ ohne Dump/Restore-Test formulieren. |

**Fehlt**

- Betrieb/Deploy: SFTP-only, Schreibrechte für `tpl_c/`, `uploads/`, Attachment-Pfad und Smarty-Cache als eigenes Risiko aufnehmen.
- Security/Datenschutz: Anhänge, Rich-Text, öffentliche Kalenderfeeds, API-Aktivierung und Rollenmodell explizit in die Strategie.
- Datenmodell für ZHL-Übergabe: Personalverfügbarkeit, Übergabeprotokoll, QR-Scan, Checkliste, Verantwortliche und Eskalation fehlen als klares Custom-Modul.
- Suche: vorhandene Suche ist SQL-LIKE auf Titel/Beschreibung/Referenz, keine Synonyme/Fuzzy-Suche.

**Risiken**

- Mehrere ✅ sind eigentlich „nativ vorhanden, aber per Config aus“: F20, F26, F28, F36.
- F6, F22, F35 sind zu optimistisch und sollten vor Umsetzung abgewertet werden.
- Core-Edits für Frontpage/UX erhöhen Merge-Konflikte; zuerst `config.php`, `lang-overrides`, `css.extension.file`, Styling-Plugin und PostReservation-Plugin ausschöpfen.
- Upgrade nicht nur Code tauschen: alte Live-Config gegen neue `config.dist.php` diffen, Upload-/Cache-Pfade sichern, DB-Dump-Restore real proben.
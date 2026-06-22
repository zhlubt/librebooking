| Feature/Plan | Befund | Beleg (Pfad) | Empfehlung |
|---|---|---|---|
| F1 Registrierung/Profile | Weitgehend richtig: Domain-Restrict + Aktivierung nativ; ToS-Akzeptanzzeitpunkt pro User nicht nativ belegt. | `lib/Config/ConfigKeys.php:1012`, `lib/Config/ConfigKeys.php:1414`, `database_schema/upgrades/2.2/schema.sql:30`, `database_schema/upgrades/2.7/schema.sql:93` | ✅🟦 lassen, ToS-Zeitpunkt als Custom-Mini ausweisen. |
| F2 Rollen/Rechte | Richtig. Rollen/Gruppen/Resource-Permissions nativ. | `database_schema/create-schema.sql:104`, `database_schema/create-schema.sql:236`, `lib/Application/Admin/ResourcePermissionService.php` | ✅ lassen. |
| F3 User-Verwaltung | Richtig. | `Pages/Admin/ManageUsersPage.php`, `Presenters/Admin/ManageUsersPresenter.php` | ✅ lassen. |
| F4 Item/Inventar-CRUD | Richtig als LibreBooking-Resource-CRUD. | `Pages/Admin/ManageResourcesPage.php`, `Presenters/Admin/ManageResourcesPresenter.php` | ✅ lassen. |
| F5 Custom-Attribute | Richtig. | `Domain/CustomAttribute.php`, `Domain/Access/AttributeRepository.php`, `WebServices/AttributesWebService.php` | ✅ lassen. |
| F6 Kategorien | FEATURES korrekt vorsichtig; STRATEGY zu optimistisch (`✅`). Resource Groups/Types sind keine freie Frontpage-Kachel-UX. | `Domain/ResourceGroup.php`, `Domain/ResourceType.php` | STRATEGY auf 🟨🟦 ändern. |
| F7 Status/Verfügbarkeit | Richtig. Resource-Status + Blackouts nativ. | `Domain/Values/ResourceStatus.php`, `Domain/Blackout.php`, `database_schema/create-schema.sql:357` | ✅ lassen. |
| F8 Item-Typen/Übergabe | FEATURES richtig: Übergabe-Flag/Flow nicht nativ. | `Domain/BookableResource.php:1375`, kein `handover` im Code/Schema | Custom-Modul; ggf. erst Custom Attribute als Übergangsflag. |
| F9 Medien/Doku | FEATURES richtig, STRATEGY zu optimistisch: Bilder nativ, Ressourcen-Doku-Anhänge nicht. Attachments hängen an Reservierungen; MIME nur Extension. | `Domain/BookableResource.php:698`, `database_schema/upgrades/2.2/schema.sql:44`, `lib/Application/Reservation/Validation/ReservationAttachmentRule.php:24`, `lib/Common/Validators/FileTypeValidator.php:34` | `resource_attachments` + `finfo`/Max-Size als Custom einplanen. |
| F10 QR-Verifikation | FEATURES stimmt: QR + Check-in/out nativ, Checkliste fehlt. STRATEGY falsch, wenn “QR → Custom” behauptet wird. | `Presenters/Admin/ManageResourcesPresenter.php:891`, `Pages/ResourceQRRouterPage.php:13`, `Presenters/Reservation/ReservationCheckinPresenter.php:52` | QR als nativ markieren; nur Verifikations-Checkliste custom. |
| F11 Multi-Item-Buchung | Richtig. Additional resources gehen über `AllResourceIds()`/Ressourcenliste. | `lib/Application/Reservation/Validation/PermissionValidationRule.php:29`, `Presenters/Reservation/ReservationUpdatePresenter.php` | ✅ lassen. |
| F12 Buchungs-Dashboard | Richtig. | `Presenters/DashboardPresenter.php`, `Presenters/Dashboard/UpcomingReservationsPresenter.php` | ✅ lassen. |
| F13 Kalenderübersicht | Richtig. | `Pages/SchedulePage.php`, `Presenters/Schedule/SchedulePresenter.php`, `Pages/CalendarPage.php` | ✅ lassen. |
| F14 Approval-Workflow | Richtig pro Ressource. | `Domain/BookableResource.php`, `lib/Application/Reservation/Validation/RequiresApprovalRule.php` | ✅ lassen. |
| F15 Vorlaufzeiten | Richtig als Resource-Regeln. | `Domain/BookableResource.php:704`, `lib/Application/Reservation/Validation/ResourceMaximumNoticeRule.php` | ✅ lassen. |
| F16/F17 Personal-Übergabe-Timeslots | FEATURES richtig: kein Staff-/Schicht-/Slot-Modell. | kein `staff/shift/handover` im Schema; nur `reservation_users` | Custom nötig. |
| F18 Blackout-Daten | Richtig. | `Domain/Blackout.php`, `database_schema/create-schema.sql:357` | ✅ lassen. |
| F19 Timeslot-Storno Staff→Peer | Richtig als Custom. Waitlist ist user/resource-getrieben, kein Staff-Takeover. | `database_schema/upgrades/2.6/schema.sql:203`, `Jobs/sendwaitlist.php` | An CM-1 koppeln. |
| F20 iCal-Feed | Richtig, aber Freigabe/Privacy prüfen. | `Presenters/CalendarSubscriptionPresenter.php`, `Pages/Export/CalendarSubscriptionPage.php` | ✅🟦 lassen; öffentliche Feeds als Risiko aufnehmen. |
| F21 E-Mail-Benachrichtigungen | Richtig. | `lib/Application/Reservation/Notification/PostReservationFactory.php:48` | ✅ lassen. |
| F22 Mailtemplates DE/per-Item | FEATURES stimmt: Sprach-/Custom-Templates nativ, per Resource nicht. | `lib/Email/EmailMessage.php:42`, `lib/Common/SmartyPage.php:89`, `lib/Common/SmartyPage.php:118`, `Presenters/Admin/ManageEmailTemplatesPresenter.php:99` | Per-Resource explizit 🔧 lassen. |
| F23 Reminder-Timing | FEATURES stimmt: global/UI-Reminder nativ, nicht pro Ressource/erzwungen. | `config/config.dist.php:370`, `lib/Application/Reservation/NewReservationInitializer.php:128`, `Jobs/sendreminders.php:4` | QW okay; “Pflicht-Reminder”/per Ressource custom. |
| F24 Webhooks | Richtig: Hook-Punkt nativ, HTTP-Webhook selbst nicht. | `config/config.dist.php:734`, `lib/Common/PluginManager.php:133`, `plugins/PostReservation/PostReservationExample/PostReservationExample.php:91` | Kleines Plugin statt Core-Edit. |
| F25 Robuste Suche | Richtig: LIKE-Suche, kein Fuzzy/Fulltext/Resource-Autocomplete. | `lib/Database/SqlFilter.php:238`, `Pages/Ajax/AutoCompletePage.php:14`, `Pages/Ajax/AutoCompletePage.php:227` | 🔧 lassen. |
| F26 Mehrsprachigkeit | Richtig. | `config/config.dist.php:47`, `config/lang-overrides.example.php` | Config/lang-overrides statt `lang/` patchen. |
| F27 Public Homepage | Custom/UX, nicht LibreBooking-nativ. | `Web/zhl-welcome.php` laut FEATURES | Als ZHL-eigene Datei/Redirect halten. |
| F28 REST-API | Richtig, Default aus. | `lib/Config/ConfigKeys.php:1790`, `WebServices/` | Nur gezielt aktivieren; API-Gruppen nutzen. |
| F29 Analytics/Reports | Richtig als Reports-Modul. | `Pages/Reports/`, `Presenters/Reports/` | ✅ lassen; Rollen klären. |
| F30 Komponenten/Sub-Objekte | FEATURES stimmt: Accessories nativ nur Name/Menge/min/max, kein Zustand/Seriennummer. | `Domain/Accessory.php:31`, `Domain/Accessory.php:157`, `database_schema/upgrades/2.6/schema.sql:29`, `lib/Application/Reservation/Validation/AccessoryResourceRule.php:64` | CM-3 vor CM-2 oder gemeinsam liefern. |
| F31 Max. Buchungsdauer | Richtig. | `Domain/BookableResource.php`, Resource-Duration-Regeln | ✅ lassen. |
| F32 User-Buchungslimits | Richtig: Quotas. | `Domain/Quota.php`, `database_schema/create-schema.sql:418` | ✅ lassen. |
| F33 Storno-Fristen | Nativ nur Fristen/Regeln, kein Storno-Workflow. | `Domain/BookableResource.php`, Delete-Min-Notice | In STRATEGY nicht als Workflow verkaufen. |
| F34 Overdue-Handling | FEATURES richtig: Checkout-Missing kann gesucht werden, Cron mailt nur missed check-in. Keine Eskalation/Auto-Sperre. | `Jobs/sendmissedcheckin.php:29`, `lib/Database/Commands/Queries.php:1328`, `Domain/Access/ReservationViewRepository.php:369` | QW-4 nicht als F34-Lösung formulieren. CM-5 bleibt nötig. |
| F35 Verlängerungen | “Reservierung ändern” nativ, kein eigener Verlängerungsprozess. | `Pages/Reservation/ExistingReservationPage.php`, `Presenters/Reservation/ReservationUpdatePresenter.php` | 🟨 statt rein ✅, falls eigener Flow gewünscht. |
| F36 Waitlist | Richtig, Config default aus. | `config/config.dist.php:341`, `ReservationHandler.php:113`, `database_schema/upgrades/2.6/schema.sql:203` | QW sinnvoll; Cron `sendwaitlist.php` mit einplanen. |
| F37 Audit-Log | FEATURES richtig: kein systemweites Audit. Payment/Credit-Logs und Monolog ersetzen Audit nicht. | `config/config.dist.php:209`, `lib/Common/Logging/Log.php`, `database_schema/upgrades/2.7/schema.sql:42` | STRATEGY von 🟡 auf 🔧 korrigieren. |
| F38 DSGVO | FEATURES richtig: Privacy-Config ja, Export/Anonymisierung/Consent nicht nativ. User-Delete ist Hard Delete. | `config/config.dist.php:534`, `lib/Database/Commands/Queries.php:296` | STRATEGY von 🟡 auf 🔧 korrigieren. |
| F39 Mobile-Responsive | Plausibel richtig. | Bootstrap-basierte Templates/CSS | Trotzdem reale Mobile-Smoke-Tests einplanen. |
| F40 Einweisungs-/Berechtigungspflicht | Gruppen-Gate ist tragfähig für neue/ändernde Buchungen normaler User. Caveats: Admins sind ausgenommen; bestehende Reservierungen werden durch Gruppenentzug nicht automatisch storniert; Ablauf/Lifecycle ist nicht nativ. | `PermissionService.php:72`, `PermissionValidationRule.php:31`, `PreReservationFactory.php:175`, `ScheduleUserRepository.php:212`, `database_schema/upgrades/2.7/schema.sql:148` | Konzept Stufe 1 ok; ergänzen: “wirkt bei Buchung/Änderung, nicht rückwirkend”. Ablauf per Job/Plugin. |
| STRATEGY Feature-Mapping | Zu optimistisch gegenüber FEATURES.md: F6, F9, F10, F34, F37, F38 und Custom-Anzahl sind falsch/alt. | `docs/zhl/STRATEGY.md` vs. obige Codebelege | STRATEGY aus FEATURES.md neu ableiten. |
| WORKPACKAGES Reihenfolge | Widerspruch: Strategie sagt Upgrade zuerst, WORKPACKAGES stellt UP-1 nach Config/UX/Custom. Außerdem CM-2 nutzt F30, CM-3 erweitert F30 erst danach. | `docs/zhl/WORKPACKAGES.md` | UP-1/Rehearsal vor produktiven Config-/UX-PRs; CM-3 vor/mit CM-2; CM-4 Stufe 1 als Config-Runbook, nicht Custom-PR. |

**Fehlt**

- Explizite Betriebs-Tasks: Cron für `sendreminders.php`, `sendwaitlist.php`, `sendmissedcheckin.php`; bei SFTP-only muss klar sein, wer Cron/Job-Ausführung kontrolliert.
- Berechtigungskonzept für API/iCal/public views: `api.enabled`, Calendar-Feeds und `privacy.*` können unbeabsichtigt Daten exponieren.
- Datenmodell-Entscheidung für ZHL-Übergabe-Modul: eine gebündelte Spezifikation für F8/F10/F16/F17/F19/F30/F34 fehlt noch.
- F40-Betriebsprozess: Wer trägt Gruppenmitgliedschaft ein, wer entzieht sie, was passiert mit bestehenden Reservierungen nach Entzug/Ablauf?

**Risiken**

- Upgrade-Reihenfolge ist riskant, wenn erst UX/Config gebaut wird und danach 4.0.0 → 5.1.0 produktiv kommt. Erst Rehearsal/Upgrade, dann UX.
- SFTP-only macht Rollback, DB-Dump, Cache-Cleanup, Upload-Pfade und Cron-Verifikation zum Hauptbetriebsrisiko.
- Core-Edits für Branding/UX sollten weitgehend durch `css.extension.file`, Styling-Plugin, `lang-overrides.php`, eigene Pages und Plugins ersetzt werden.
- F40 ist als Gruppen-Gate brauchbar, aber nicht als vollständiges Zertifikatssystem. Für Ablauf, Audit, Entzug und rückwirkende Reservierungsbehandlung braucht es Custom-Logik.
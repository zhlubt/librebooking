| Feature/Plan | Befund | Beleg (Pfad) | Empfehlung |
|---|---|---|---|
| F1 Registrierung/Profile ✅🟦 | Größtenteils korrekt: Domain-Restrict und Mail-Aktivierung sind Config; ToS existiert, aber User-Accept-Zeitpunkt bleibt nicht als echtes Profil-Feature belegt. | `lib/Config/ConfigKeys.php:1012`, `:1414`; `database_schema/upgrades/2.2/schema.sql`; `database_schema/upgrades/2.7/schema.sql` | Status eher `✅🟦 + kleine Custom-Lücke` lassen. |
| F2 Rollen/Rechte ✅ | Korrekt nativ. Admin-Rollen werden als Session-Rollen genutzt. | `Pages/Page.php`; `Pages/SecurePage.php` | Passt. |
| F3 User-Verwaltung ✅ | Korrekt nativ. | `Pages/Admin/ManageUsersPage.php`; `Presenters/Admin/ManageUsersPresenter.php` | Passt. |
| F4 Item/Inventar-CRUD ✅ | Korrekt nativ als Ressourcenverwaltung. | `Pages/Admin/ManageResourcesPage.php`; `Presenters/Admin/ManageResourcesPresenter.php` | Passt. |
| F5 Custom-Attribute ✅ | Korrekt nativ für mehrere Kategorien. | `Domain/CustomAttribute.php`; `Pages/Admin/ManageAttributesPage.php` | Passt; Magic-Number-Kommentare in ZHL-Code später durch Konstanten ersetzen. |
| F7 Status/Verfügbarkeit ✅ | Korrekt nativ: Ressourcenstatus, Blackouts, Availability-Service. | `WebServices/ResourcesWebService.php`; `lib/Application/Reservation/ResourceAvailability.php` | Passt. |
| F10 QR-Verifikation ✅🔧 | Zu optimistisch, wenn „Verifikation“ mehr als QR-Routing meint. QR + Check-in/out nativ, Checkliste/Zustand Custom. | `Pages/ResourceQRRouterPage.php`; `Pages/Ajax/ReservationCheckinPage.php`; `Presenters/Admin/ManageResourcesPresenter.php` | Als `🟨🔧` formulieren: QR nativ, ZHL-Verifikation Custom. |
| F11 Multi-Item-Buchung ✅ | Korrekt nativ über Additional Resources. | `Domain/ReservationSeries.php:136`, `:161`; `lib/Application/Reservation/ReservationComponentBinder.php` | Passt. |
| F12 Buchungs-Dashboard ✅ | Korrekt nativ. | `Pages/DashboardPage.php`; `Presenters/DashboardPresenter.php` | Passt. |
| F13 Kalenderübersicht ✅ | Korrekt nativ. | `Pages/SchedulePage.php`; `Pages/ViewCalendarPage.php`; `Presenters/Schedule/SchedulePageBuilder.php` | Passt. |
| F14 Approval-Workflow ✅ | Korrekt nativ pro Ressource. | `lib/Application/Reservation/Validation/RequiresApprovalRule.php`; `database_schema/create-schema.sql:197` | Passt. |
| F15 Vorlaufzeiten ✅ | Korrekt nativ pro Ressource. | `lib/Application/Reservation/Validation/ResourceMinimumNoticeRuleAdd.php`; `ResourceMaximumNoticeRule.php` | Passt. |
| F18 Blackout-Daten ✅ | Korrekt nativ. | `Pages/Admin/ManageBlackoutsPage.php`; `lib/Application/Reservation/ManageBlackoutsService.php` | Passt. |
| F20 iCal-Feed ✅🟦 | Korrekt, aber aktivierungs-/Key-abhängig. | `Pages/Export/CalendarSubscriptionPage.php`; `lib/Application/Schedule/CalendarSubscriptionUrl.php`; `ConfigKeys.php:1085` | Als Config-Risiko im Betrieb führen. |
| F21 E-Mail-Benachrichtigungen ✅ | Korrekt nativ. | `lib/Application/Reservation/Notification/PostReservationFactory.php`; `lib/Email/Messages/ReservationEmailMessage.php` | Passt. |
| F22 Mailtemplates DE/per Item ✅🟦/🔧 | Korrekt differenziert: Sprache/custom tpl nativ, per Resource nicht nativ. | `lib/Common/SmartyPage.php:89`; `Presenters/Admin/ManageEmailTemplatesPresenter.php:99` | Nicht als pauschal nativ lesen. |
| F26 Mehrsprachigkeit ✅🟦 | Korrekt, `de_de` vorhanden; lokal in `config.php` bereits gesetzt, dist bleibt `en_us`. | `lang/de_de`; `config/config.php:47`; `config/config.dist.php:47` | Produktions-Config explizit dokumentieren. |
| F28 REST-API ✅🟦 | Korrekt nativ, default aus. | `Web/Services/index.php`; `WebServices/`; `lib/Config/ConfigKeys.php:1793` | API nur gezielt aktivieren, Keys/Rollen prüfen. |
| F29 Analytics/Reports ✅ | „Reports“ korrekt, „Analytics“ zu weit. Kein Produkt-/Nutzungsanalytics außer Reporting/Google-Tracking-Config. | `Pages/Reports/GenerateReportPage.php`; `lib/Application/Reporting/`; `ConfigKeys.php:926` | Feature in `Reports` und `Analytics` trennen. |
| F31 Max. Buchungsdauer ✅ | Korrekt nativ. | `lib/Application/Reservation/Validation/ResourceMaximumDurationRule.php`; `Domain/BookableResource.php` | Passt. |
| F32 User-Buchungslimits ✅ | Korrekt nativ über Quotas. | `Pages/Admin/ManageQuotasPage.php`; `Presenters/Admin/ManageQuotasPresenter.php`; `Domain/Quota.php` | Passt. |
| F33 Storno-Fristen ✅ | Korrekt nur als Mindestfrist-Regeln, kein eigener Storno-Workflow. | `ResourceMinimumNoticeRuleDelete.php`; `PreReservationFactory.php` | In FEATURES weiter einschränken: Frist ja, Workflow nein. |
| F36 Waitlist 🟦 | Korrekt nativ per Config, nicht nativ aktiv. | `lib/Config/ConfigKeys.php:755`; `Pages/Ajax/ReservationWaitlistPage.php` | Config-Quick-Win. |
| F39 Mobile-Responsive ✅ | Plausibel nativ über Bootstrap/MobileDetect. | `Pages/Page.php`; Bootstrap-Templates | Nur durch Viewport-Test final belegen. |
| F40 Einweisungs-/Berechtigungspflicht ✅✅ | Nicht nativ. Umgesetzt als ZHL-Custom-Permission-Plugin plus Core-Whitelist-Edit. | `plugins/Permission/ZhlCertificate/ZhlCertificate.php`; `lib/Config/ConfigKeys.php:1724` | In FEATURES als `🔧 umgesetzt`, nicht `✅ nativ`, markieren. |
| STRATEGY „Großteil nativ“ | Im Kern richtig, aber §2/§4 unterschätzt Custom-Lücken: Audit, DSGVO, Overdue, Handover-Checkliste/Zustand sind nicht klein. | `docs/zhl/FEATURES.md`; fehlende native Tabellen/Services in `database_schema/` | Strategie abschwächen: „viele Bausteine nativ“, Custom-Modul bleibt Hauptarbeit. |
| STRATEGY „keine Breaking-DB-Changes“ | Zu sicher formuliert. Repo-Upgrades reichen bis 4.0, aber Live-DB 4.0→5.1 muss rehearsed werden. | `database_schema/upgrades/`; `docs/zhl/UPGRADE-RUNBOOK.md` | „Keine erwarteten Schema-Upgrades nach 4.0, trotzdem Rehearsal zwingend“ schreiben. |
| Handover Select SecurePage | Login-Bindung korrekt; pageDepth/Redirect für `/Web` passt. | `Web/zhl-handover-select.php:27-33`; `Pages/SecurePage.php:7-22` | Passt. |
| Handover Sync POST+CSRF | Korrekt: Sync nur POST und `EnforceCSRFCheck`; Formular trägt Token. | `Web/zhl-handover-select.php:59-63`, `:156-158`; `Pages/Page.php:262-270` | Passt. |
| Handover Token-Ownership UI | Im Normalfall korrekt: fremder Owner → 403, Claim überschreibt nie Owner. Race-Rest: paralleler Erst-Claim ignoriert `false`-Rückgabe und kann einmal Status rendern. | `Web/zhl-handover-select.php:46-56`; `Web/zhl-handover-lib.php:169-181`; `003_zhl_handover_token.sql:7` | Rückgabe von `zhl_handover_claim_token()` prüfen und bei `false` sofort 403. |
| Handover PreReservation owner==series user | Grundlogik stimmt, aber owner `null` ist bewusst erlaubt und damit umgehbar, wenn `zhl_booking_handover` ohne Token-Owner befüllt wird. | `ZhlHandoverValidation.php:73-79`; `Web/zhl-handover-notify.php`; `zhl_handover_sync()` | Owner zwingend verlangen: `ownerId === (int)$series->UserId()`, sonst blocken. |
| Handover PreReservation Add/Update | Interface und Einbindung korrekt, greift vor Persistenz für Add/Update. | `plugins/PreReservation/ZhlHandover/ZhlHandover.php:27-39`; `PluginManager.php:112-124` | Passt, sobald Config aktiv ist. |
| Handover PostReservation | Interface korrekt; Add/Update/Approve dekoriert; Linkdaten aus `CurrentInstance()`, `ReferenceNumber()`, `ReservationId()`, `SeriesId()` sind passend. | `ZhlHandoverLink.php:15-54`; `ZhlHandoverLinkNotification.php:19-66`; `PostReservationFactory.php:3-40` | Custom-Fehler möglichst `Throwable` fangen; sonst ok. |
| Handover PostReservation Aktivierung | Code/Whitelist vorhanden, aber lokale `config.php` aktiviert Pre/PostReservation aktuell nicht. | `ConfigKeys.php:1749-1772`; `config/config.php:731-736` | Deploy-Runbook muss `plugins.prereservation='ZhlHandover'` und `plugins.postreservation='ZhlHandoverLink'` setzen. |
| Handover Migration 002/003 | ZHL-eigene Tabellen ok; aber keine FK von `zhl_booking_handover` zu `zhl_handover_token`, keine FK zu `users`, keine Token-Expiry. | `002_zhl_handover.sql:11-34`; `003_zhl_handover_token.sql:7-12` | FK/Ownership-Invariant oder App-Check härten; Cleanup/Expiry planen. |
| Config-Artefakte | `ConfigKeys.php` enthält ZHL-Choices, `config.dist.php`-Kommentare/Options nicht regeneriert. | `ConfigKeys.php:1753-1771`; `config/config.dist.php:731-736` | `composer config-dist:generate`/Check vor PR, sonst Admin-Config irreführend. |

**Fehlt**

- PHPUnit-Tests für `ZhlHandoverValidation` und `ZhlHandoverLinkNotification`; `verify-handover-sql.php` ersetzt keine Plugin-Tests.
- Admin-/Betriebsansicht für offene, bestätigte, verknüpfte und erledigte Übergaben.
- Handover-Token-Expiry/Cleanup und klare Invalidierung nach Reservierungsabbruch.
- DSGVO-Export/Anonymisierung, systemweites Audit-Log, Overdue-Rückgabe-Eskalation bleiben echte Custom-Themen.
- Klare Trennung in FEATURES zwischen „nativ vorhanden“, „per Config nutzbar“, „ZHL-Custom bereits umgesetzt“.

**Risiken**

- Größte Phase-A-Lücke: ownerlose bestätigte Tokens werden im PreReservation-Gate akzeptiert.
- Handover bleibt wirkungslos, solange Pre/PostReservation-Plugins nicht in Produktiv-Config aktiviert sind.
- Core-Whitelist-Edits in `ConfigKeys.php` sind klein, aber Upstream-Merge-Konfliktpunkte.
- `zhl_handover_config()` fällt auf `.example.php` zurück; in Prod besser hart fehlschlagen.
- Multi-Resource/Instanz-Semantik ist bewusst pro Token/Reservierung vereinfacht; für Phase B muss das Datenmodell nachgeschärft werden.
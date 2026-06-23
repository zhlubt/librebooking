| Feature/Plan | Befund | Beleg (Pfad) | Empfehlung |
|---|---|---|---|
| F1 Registrierung/Profile | Größtenteils korrekt: Domain-Restrict, Aktivierung, ToS existieren; ToS-Accept pro User bleibt Custom. | `config/config.dist.php`, `Presenters/RegistrationPresenter.php`, `database_schema/upgrades/2.2/schema.sql`, `database_schema/upgrades/2.7/schema.sql` | Als `✅🟦 + Mini-Custom` führen, nicht rein nativ. |
| F2 Rollen/Rechte | Korrekt nativ. Admin-/Resource-/Schedule-/Group-Admin sind echte Session/Rollenflags. | `lib/Server/UserSession.php`, `Domain/User.php`, `Pages/SecurePage.php` | OK. |
| F3 User-Verwaltung | Korrekt nativ. | `Web/admin/manage_users.php`, `Presenters/Admin/ManageUsersPresenter.php` | OK. |
| F4 Item/Inventar-CRUD | Zu optimistisch formuliert: Ressourcen-CRUD ja, echtes Inventar/Stock/Seriengeräte nein. | `Web/admin/manage_resources.php`, `Presenters/Admin/ManageResourcesPresenter.php`, `Domain/BookableResource.php` | In “Ressourcen-CRUD nativ; Inventarlogik Custom” präzisieren. |
| F5 Custom-Attribute | Korrekt nativ für User/Resource/ResourceType/Reservation. | `Domain/CustomAttribute.php`, `Presenters/Admin/ManageAttributesPresenter.php`, `database_schema/upgrades/2.2/schema.sql` | OK. |
| F7 Status/Verfügbarkeit | Korrekt: Resource-Status und Blackouts vorhanden. | `database_schema/upgrades/2.5/schema.sql`, `Web/admin/manage_blackouts.php`, `Domain/Blackout.php` | OK. |
| F10 QR-Verifikation | “QR nativ” stimmt nur als Resource-QR-Baustein; ZHL-Verifikationscheckliste/Zustand ist Custom. | `Presenters/Admin/ManageResourcesPresenter.php`, `Pages/ResourceQRRouterPage.php`, `Pages/Ajax/ReservationCheckinPage.php` | Nicht als nativ für ZHL-Ziel verkaufen; `✅ Baustein + 🔧 Checkliste`. |
| F11 Multi-Item-Buchung | Korrekt: zusätzliche Ressourcen je Reservierung existieren. | `Domain/ReservationSeries.php`, `Domain/ReservationResourceView.php` | OK. |
| F12 Buchungs-Dashboard | Korrekt nativ. | `Presenters/DashboardPresenter.php`, `Presenters/Dashboard/*` | OK. |
| F13 Kalenderübersicht | Korrekt nativ. | `Pages/SchedulePage.php`, `Presenters/Schedule/SchedulePresenter.php` | OK. |
| F14 Approval-Workflow | Korrekt nativ pro Ressource. | `Domain/BookableResource.php`, `lib/Application/Reservation/Validation/RequiresApprovalRule.php` | OK. |
| F15 Vorlaufzeiten | Korrekt nativ für Add/Update/Delete/Max Notice je Ressource. | `Domain/BookableResource.php`, `Presenters/Admin/ManageResourcesPresenter.php` | OK. |
| F18 Blackout-Daten | Korrekt nativ. | `Web/admin/manage_blackouts.php`, `Presenters/Admin/ManageBlackoutsPresenter.php`, `Domain/Blackout.php` | OK. |
| F20 iCal-Feed | Korrekt, aber Konfig-/Key-abhängig. | `Pages/Export/AtomSubscriptionPage.php`, `Pages/Export/CalendarExportDisplay.php`, `config/config.dist.php` | `✅🟦` passt. |
| F21 E-Mail-Benachrichtigungen | Korrekt nativ für Standard-Reservation-Events. | `lib/Email/Messages/*Reservation*.php`, `lib/Application/Reservation/Notification/*` | OK; ZHL-Sondermails separat Custom. |
| F22 Mailtemplates DE/per-Item | Sprache/Custom-Templates nativ; per Resource/Item nicht nativ. | `lib/Common/SmartyPage.php`, `lang/de_de/*.tpl` | Status `✅🟦/🔧` passt. |
| F26 Mehrsprachigkeit | Korrekt per Config; `config.dist` bleibt `en_us`, lokale `config.php` ist ZHL-spezifisch. | `config/config.dist.php`, `config/config.php`, `lang/de_de.php` | OK, nicht in Upstream-Default ändern. |
| F28 REST-API | Korrekt nativ, per Config aktivierbar. | `Web/Services/index.php`, `WebServices/*`, `config/config.dist.php` | OK; API-Aktivierung als Sicherheitsentscheidung behandeln. |
| F29 Analytics/Reports | Reports nativ; Analytics nur Google-Tracking-Key, keine ZHL-Fachanalytics. | `Pages/Reports/*`, `Presenters/Reports/*`, `config/config.dist.php` | In “Reports nativ; Analytics begrenzt” ändern. |
| F31 Max. Buchungsdauer | Korrekt nativ je Ressource. | `Domain/BookableResource.php`, `Presenters/Admin/ManageResourcesPresenter.php` | OK. |
| F32 User-Buchungslimits | Korrekt nativ über Quotas. | `Web/admin/manage_quotas.php`, `Presenters/Admin/ManageQuotasPresenter.php`, `Domain/Quota.php` | OK. |
| F33 Storno-Fristen | Zu optimistisch: Fristen ja, aber kein echter Storno-Workflow. | `Domain/BookableResource.php`, `Presenters/Admin/ManageResourcesPresenter.php` | Als `🟨` statt `✅` führen, wenn Workflow gemeint ist. |
| F39 Mobile-Responsive | Plausibel nativ durch Bootstrap 5, aber kein Beleg für ZHL-UX getestet. | `tpl/`, `Web/assets/vendor/bootstrap/5.3.3/` | `✅` nur für Basis-Responsiveness; mobile Handover testen. |
| F40 Einweisungs-/Berechtigungspflicht | Falsch als nativ interpretierbar: Umsetzung ist Custom-Plugin plus Config-Key-Choice-Core-Edit. | `plugins/Permission/ZhlCertificate/ZhlCertificate.php`, `config/config.dist.php`, `lib/Config/ConfigKeys.php` | Status auf `🔧 umgesetzt` ändern; Core-Edit vermeiden, wenn Plugin-Name auch ohne Choice ladbar ist. |
| STRATEGY: “Großteil nativ” | Inhaltlich zu pauschal. Viele Features sind native Bausteine, aber ZHL-Zielverhalten bleibt Custom: Übergabe, Audit, DSGVO, Suche, Overdue. | `docs/zhl/FEATURES.md`, Codebelege oben | Strategie auf “native Basis + gezielte Custom-Module” schärfen. |
| STRATEGY: DB-Änderungen unter `database_schema/upgrades/` | Wird aktuell nicht eingehalten: ZHL-Migrationen liegen unter `docs/zhl/migrations/`. | `docs/zhl/migrations/*.sql` | Entweder bewusst als ZHL-Runbook-Migration dokumentieren oder nach `database_schema/upgrades/` mit Versionsschema überführen. |
| STRATEGY: Config/Plugin statt Core | Teilweise verletzt: `config.dist.php` und `ConfigKeys.php` wurden für ZHL-Plugin-Choices angepasst. | `config/config.dist.php`, `lib/Config/ConfigKeys.php` | Prüfen, ob Plugin-Loading ohne UI-Choice reicht; sonst Core-Edit bewusst markieren und klein halten. |
| Übergabe Check: Auth | `SecurePage` erzwingt Login. Staff-Check erlaubt App-, Resource-, Schedule- und Group-Admins. Das ist “ZHL-Team”, aber nicht strikt Application-Admin-only. | `Web/zhl-handover-check.php`, `Pages/SecurePage.php`, `lib/Server/UserSession.php` | Wenn nur ZHL-Betrieb darf: eigene Gruppe/Permission oder `IsAdmin` plus explizite ZHL-Gruppe, nicht alle Adminrollen pauschal. |
| Übergabe Check: CSRF | POST-Speichern ist CSRF-geschützt. | `Web/zhl-handover-check.php`, `Pages/Page.php` | OK. |
| Übergabe Check: SQL-Injection | Vorbereitete Statements; Query-Parameter werden eingeschränkt/gecastet. | `Web/zhl-handover-check.php`, `Web/zhl-handover-lib.php` | OK. |
| Übergabe Check: Transaktion/Items | Insert Check + strukturierte Accessories + Ad-hoc-Items in einer Transaktion. Edge “keine Accessories” wird über Ad-hoc-Zeile abgefangen. | `Web/zhl-handover-check.php` | OK; zusätzlich mindestens ein Item oder Gesamtzustand serverseitig erzwingen, falls fachlich nötig. |
| Übergabe Check: `done` markieren | Funktioniert, aber Update ist breit: `WHERE type = ? AND (reference_number = ? OR handover_token = ?)`. Bei nicht eindeutigem `reference_number` können mehrere Zeilen gleichen Typs erledigt werden. | `Web/zhl-handover-check.php`, `docs/zhl/migrations/002_zhl_handover.sql` | Primär per `handover_token + type` oder `id` updaten; `reference_number` nur fallback mit `resource_id`/Limit. |
| Übergabe Check: Kontextlos | Speichern ohne `ref`, `token` und `resource` ist möglich; erzeugt ein kaum zuordenbares Protokoll und zeigt trotzdem Erfolg. | `Web/zhl-handover-check.php` | Server-seitig mindestens `token/ref` oder `resource_id` verlangen. |
| Übergabe QR | BaconQrCode-Nutzung ist korrekt für v3.1.1; `Content-Type: image/png` und `no-store` gesetzt. | `Web/zhl-handover-qr.php`, `composer.json`, `composer.lock` | OK; vor Ausgabe keine Notices/Whitespace riskieren. |
| Übergabe QR: Auth | Nur eingeloggte Adminrollen. Kein CSRF nötig für GET-Bild. | `Web/zhl-handover-qr.php`, `Pages/SecurePage.php` | Gleiche Rollenfrage wie oben klären. |
| Übergabe QR: URL-Basis | `GetScriptUrl()` ist LibreBooking-Standard. Wenn `script.url` leer/falsch ist, QR zeigt falsch. | `Web/zhl-handover-qr.php`, `config/config.dist.php`, `lib/Config/Configuration.php` | Deployment-Check für `script.url` ins Runbook. |
| Übergabe Admin: Query | `zhl_handover_list()` ist vorbereitet und Status-Filter allowlisted. | `Web/zhl-handover-admin.php`, `Web/zhl-handover-lib.php` | OK. |
| Übergabe Admin: XSS | Tabellenwerte werden escaped; Badge/Icons sind feste Strings. | `Web/zhl-handover-admin.php` | OK. |
| Übergabe Migration 004 | Technisch passend zu 002, aber nicht idempotent: wiederholtes Ausführen von `ALTER TABLE ADD COLUMN` bricht. | `docs/zhl/migrations/004_zhl_handover_check_ext.sql` | Mit `ADD COLUMN IF NOT EXISTS` oder Runbook “einmalig” absichern. |
| Accessory-Schema | Join ist korrekt: `resource_accessories.resource_id/accessory_id` zu `accessories.accessory_id`; Typen passen grob. | `database_schema/upgrades/2.6/schema.sql`, `database_schema/create-schema.sql`, `Web/zhl-handover-lib.php` | OK; Quantity wird aktuell nur geladen, nicht im Protokoll ausgewertet. |

**Fehlt**

- Handover-Protokolle haben keine Foreign Keys zu `resources`, `users`, `reservation_series`/Instanz; für Upgrade/Reporting/Audit wäre das robuster.
- Kein eigenes Rollenmodell “ZHL-Team”; pauschale Adminrollen können zu breit sein.
- Kein serverseitiger Pflichtkontext für Check-Protokolle.
- Kein Schutz gegen doppelte Protokolle pro Übergabe/Typ; aktuell können mehrere Checks erzeugt werden.
- Keine echte DSGVO-/Audit-/Anonymisierungsstrategie trotz Custom-Tabellen mit Namen/Notizen.

**Risiken**

- `config.dist.php`/`ConfigKeys.php` für ZHL-Plugin-Choices sind unnötige Upstream-Konfliktpunkte, wenn reines Config-Loading reicht.
- ZHL-Migrationen außerhalb `database_schema/upgrades/` laufen am LibreBooking-Upgradeprozess vorbei.
- `done`-Update per `reference_number OR token` ist fachlich riskant bei Mehrfach-/Alt-Datensätzen.
- QR hängt an korrektem `script.url`; falsche Prod-Config erzeugt dauerhaft falsche Codes.
- Phase-B-Code ist eigenständig und upgrade-sicher platziert, nutzt aber raw PDO neben LibreBookings DB-Abstraktion; das ist pragmatisch, aber Test-/Transaktionsverhalten bleibt separat abzusichern.
# Feature-Liste (kanonisch) — Status & Belege

> Eine Zeile pro Feature. Gepflegt über den [AGENTIC-PLAN.md](AGENTIC-PLAN.md)-Prozess.
> Status: ✅ nativ · 🟦 Konfiguration · 🟨 teilweise (Lücke) · 🔧 Custom nötig · ❔ ungeprüft
> „Verifiziert": `session` = in dieser Sitzung am Code/Live belegt · `codex` = Codex-Gate
> ([codex-findings.md](codex-findings.md)) · `scout` = Scout-Agent am Code belegt · `claim` = noch offen.

| # | Feature | Status | Mechanismus / Beleg (Pfad) | Nächster Schritt | Verif. |
|---|---|---|---|---|---|
| F1 | Registrierung/Profile | ✅🟦 | Domain-Restrict `authentication.required.email.domains` (`config.dist.php:618`, `RequiredEmailDomainValidator.php`); E-Mail-Aktivierung `registration.require.email.activation` + Tabelle `account_activation`; ToS Tabelle `terms_of_service` | Domain auf uni-bayreuth.de; Aktivierung an. **Custom-Mini:** ToS-Accept-Zeitpunkt pro User persistieren | scout |
| F2 | Rollen/Rechte | ✅ | groups: Application/Resource/Group/Schedule Admins | — | session |
| F3 | User-Verwaltung | ✅ | ManageUsersPage/Presenter | — | codex |
| F4 | Item/Inventar-CRUD | ✅ | „Resources" (59 live) | — | session |
| F5 | Custom-Attribute | ✅ | Custom Attributes (User/Resource/Type/Reservation) | Reichweite dokumentieren | codex |
| F6 | Kategorien | 🟨🟦 | Resource Groups/Types da, **keine freie Kategorie-/Kachel-UX** | Frontpage-Kategorien separat | codex |
| F7 | Status/Verfügbarkeit | ✅ | Resource-Status + Blackouts | — | codex |
| F8 | Item-Typen / Übergabe | 🔧 | **Kein** Selbstbedienung/Übergabe-Flag (grep 0); nur `requires_approval`, `enable_check_in` | Custom-Spalte `resources.handover_required` o. custom_attribute + Auswertung im Flow | scout |
| F9 | Medien/Doku | 🟨 | Multi-Bild ✅ `resource_images` (`BookableResource.php:698`); **Doku-Anhänge nur an Reservierung** (`reservation_files`), nicht an Ressource; MIME nur per Endung (`FileTypeValidator.php:33`) | Custom: `resource_attachments`-Tabelle; echte MIME-Prüfung (finfo) + Max-Size | scout |
| F10 | QR-Verifikation | ✅🔧 | **QR nativ** (BaconQrCode, `ManageResourcesPresenter.php:891`, `ResourceQRRouterPage.php`) + Check-in/out (`Ajax/ReservationCheckinPage.php`). **Checkliste/Zustand fehlt** (Toggle ohne Erfassung) | Custom-Checklisten-Step in `ReservationCheckinPresenter` (listet F30-Accessories) | scout |
| F11 | Multi-Item-Buchung | ✅ | Additional Resources je Reservierung | — | codex |
| F12 | Buchungs-Dashboard | ✅ | „My Dashboard" | — | session |
| F13 | Kalenderübersicht | ✅ | Schedule/FullCalendar | — | session |
| F14 | Approval-Workflow | ✅ | pro Ressource (RequiresApprovalRule) | — | codex |
| F15 | Vorlaufzeiten | ✅ | je Ressource (Add/Update/Delete, Max Notice) | — | codex |
| F16/F17 | Personal-Übergabe-Timeslots | 🔧 | **Nativ nicht abbildbar**: kein Staff-/Slot-/Schicht-Modell (grep 0 in allen SQL); Personen nur als `reservation_users` | **Custom-Modul** `handover_slots`(staff,resource,start/end,status) + UI + Verknüpfung Reservierung + Mails | scout |
| F18 | Blackout-Daten | ✅ | Blackout Times | — | codex |
| F19 | Timeslot-Storno (Staff→Peer) | 🔧 | Waitlist `reservation_waitlist_requests`/`sendwaitlist.php` ist **user-/ressourcen**-getrieben, kein Staff-Takeover | Custom (PostReservation-Delete-Plugin mailt betroffene Mitarbeiter); hängt an F16/F17 | scout |
| F20 | iCal-Feed | ✅🟦 | Subscription-Feeds; **pro Schedule/Resource erlauben** | Config prüfen | codex |
| F21 | E-Mail-Benachrichtigungen | ✅ | create/update/delete/approve (NotificationServices) | — | codex |
| F22 | Mailtemplates DE / per-Item | ✅🟦/🔧 | **Pro Sprache überschreibbar** nativ via `lang/de_de/<Event>-custom.tpl` (`SmartyPage::FetchLocalized`); **per-Resource = Custom** (Template-Name fest verdrahtet) | DE-`-custom.tpl` anlegen; per-Item via PostReservation-Plugin falls nötig | scout |
| F23 | Reminder-Timing | 🟨 | Global nativ (`reminders.enabled`+start/end), aber **nur Formular-Vorbelegung, nicht erzwungen**; Cron `Jobs/sendreminders.php`; **per-Ressource = Custom** (keine Resource-Spalte) | Config + Cron einrichten; per-Ressource = Custom | scout |
| F24 | Webhooks | 🟨🔧 | Hook nativ: PostReservation-Factory dekoriert Add/Update/Delete/Approve/Checkin (`PostReservationFactory.php`, Example-Plugin); **HTTP-Call selbst = Custom** | Kleines PostReservation-Plugin (curl-POST) schreiben | scout |
| F25 | Robuste Suche | 🔧 | **Nur SQL-LIKE `%term%`** (`SqlFilter.php:238`) über Name/Titel; kein Fuzzy/Synonym/Fulltext; Autocomplete nur User/Group, **nicht Ressourcen** | Custom (Such-Service o. DB-Fulltext) — nicht per Config | scout |
| F26 | Mehrsprachigkeit | ✅🟦 | de_de installiert; Default=de_de = **Config** (lokal gesetzt+verifiziert; config.dist bleibt en_us) | in Prod-config setzen | session+codex |
| F27 | Public Homepage | 🔧→🟦 | **Landing-Prototyp gebaut** (`Web/zhl-welcome.php`) | verdrahten | session |
| F28 | REST-API | ✅🟦 | nativ (WebServices/), **Default aus** (live an) | Config prüfen | session+codex |
| F29 | Analytics/Reports | ✅ | Reports-Modul (rollen-/config-abhängig) | Rollen klären | codex |
| F30 | Komponenten/Sub-Objekte | 🟨🔧 | Accessories nativ mit min/max je Ressource (`resource_accessories`, `AccessoryResourceRule`), **aber nur name+qty — kein Zustand/Seriennummer**, keine Checklisten-Nutzung | Custom: `serial_number`/`condition`; in F10-Checkliste einbinden | scout |
| F31 | Max. Buchungsdauer | ✅ | min/max je Ressource | — | codex |
| F32 | User-Buchungslimits | ✅ | Quotas (ManageQuotas) | — | codex |
| F33 | Storno-Fristen | ✅ | nur **Mindestfristen**, kein Storno-Workflow | — | codex |
| F34 | Overdue-Handling | 🟨🔧 | Check-in/out nativ; **nur Missed-Check-IN** erkannt (`Jobs/sendmissedcheckin.php`, einmalig); **keine** Overdue-Rückgabe-Erkennung, kein Auto-Sperren | Custom: Overdue-Erkennung + Eskalation + Auto-Deaktivierung | scout |
| F35 | Verlängerungen | 🟨 | nur „Reservierung ändern", kein expliziter Verlängerungsprozess | Custom falls nötig | codex |
| F36 | Waitlist | 🟦 | nativ `reservation_waitlist_requests`/`sendwaitlist.php`, **Default aus** (`ALLOW_WAITLIST`) | Config-PR | codex+scout |
| F37 | Audit-Log | 🔧 | **Kein systemweites Audit-Log**; keine audit/activity-Tabelle; `Log.php` = Monolog file (Dev-Logs) | Custom: Audit-Tabelle + Schreib-Hooks + Admin-View | scout |
| F38 | DSGVO | 🔧 | **Kein** User-Export, **keine** Anonymisierung; `DELETE_USER` hart; `privacy.*` nur Sichtbarkeit | Custom: Export, Anonymisierung statt Hard-Delete, Consent | scout |
| F39 | Mobile-Responsive | ✅ | Bootstrap 5 responsiv | mobil testen | codex |
| **F40** | **Einweisungs-/Berechtigungspflicht** | ✅🔧 | **Gating nativ**: `group_resource_permissions` + `PermissionValidationRule`/`PermissionService::CanBookResource` (Gruppe raus → Ressource sofort nicht buchbar). Einweisungstermin als buchbare Ressource nativ. **Custom:** Zertifikat-Lifecycle (Ablauf) | Konzept siehe [F40-KONZEPT.md](F40-KONZEPT.md) | scout |

## Zusammenfassung (nach Scout + Codex-Gate, 2026-06-22)
- ✅ nativ: ~18 · 🟦/✅🟦 Konfig: ~7 · 🟨 teilweise: ~6 · 🔧 Custom: ~9
- **Stärkste vorhandene Bausteine (nativ):** QR-Code + Check-in/out (F10), Resource-Permission-Gating (F40), Quotas/Approval/Blackouts/Vorlaufzeiten, Multi-Bild, Accessories-mit-Mengen, per-Sprache-Mailtemplates.
- **Echte Custom-Lücken (Roadmap-Treiber):** F16/F17 Personal-Übergabe-Slots · F8 Übergabe-Flag · F10 Verifikations-Checkliste · F30 Accessory-Zustand/Seriennummer · F25 Fuzzy-Suche · F37 Audit-Log · F38 DSGVO · F34 Overdue-Eskalation · F40 Zertifikat-Lifecycle.
- **Quick-Wins (Config, je 1 PR):** F26 (erledigt), F23 Reminder, F36 Waitlist, F28/F20, F1 Domain+Aktivierung, F22 DE-Mailtemplates.

## Querschnitt-Themen (in STRATEGY aufnehmen)
- **Betrieb/Deploy:** SFTP-only; Schreibrechte `tpl_c/`, `uploads/`, Attachment-Pfad, Smarty-Cache als Risiko.
- **Security/Datenschutz:** Anhänge (MIME!), Rich-Text, öffentliche Feeds, API-Aktivierung, Rollenmodell absichern.
- **ZHL-Übergabe-Modul** (bündelt F8/F10/F16/F17/F30/F34): Personalverfügbarkeit, Übergabeprotokoll, QR-Checkliste, Zustand, Eskalation — als ein Custom-Modul planen.

> Nächste Verifikations-Runde: Restliche `claim`-frei. Custom-Konzepte (Übergabe-Modul,
> Audit, DSGVO, Suche) jeweils vor Umsetzung durchs **Codex-Gate**.

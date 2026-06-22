# Feature-Liste (kanonisch) — Status & Belege

> Eine Zeile pro Feature. Gepflegt über den [AGENTIC-PLAN.md](AGENTIC-PLAN.md)-Prozess.
> Status: ✅ nativ · 🟦 Konfiguration · 🟨 teilweise (Lücke) · 🔧 Custom nötig · ❔ ungeprüft
> „Verifiziert": `session` = in dieser Sitzung am Live-System/Code belegt; `codex` = vom
> Codex-Gate am Code gegengeprüft ([codex-findings.md](codex-findings.md)); `claim` = aus
> Doku/Kenntnis, **noch nicht** am Code belegt (Scout-Aufgabe).

| # | Feature | Status | Mechanismus / Config-Key | Nächster Schritt | Verifiziert |
|---|---|---|---|---|---|
| F1 | Registrierung/Profile | ✅ | Self-Registration (live aktiv) | — | claim |
| F2 | Rollen/Rechte | ✅ | groups: Application/Resource/Group/Schedule Admins | — | session |
| F3 | User-Verwaltung | ✅ | Admin-UI (ManageUsersPage) | — | codex |
| F4 | Item/Inventar-CRUD | ✅ | „Resources" (59 live) | — | session |
| F5 | Custom-Attribute | ✅ | Custom Attributes (User/Resource/Type/Reservation) | Reichweite dokumentieren | codex |
| F6 | Kategorien | 🟨🟦 | Resource Groups/Types da, aber **keine freie Kategorie-/Kachel-UX** | Frontpage-Kategorien separat planen | codex |
| F7 | Status/Verfügbarkeit | ✅ | Resource-Status + Blackouts | — | codex |
| F8 | Item-Typen / Übergabe | 🟨🔧 | self-service via Config; Personal-Übergabe fehlt | scout + Custom-Konzept | claim |
| F9 | Medien/Doku | 🟦 | Resource-Images/Attachments | scout | claim |
| F10 | QR-Verifikation | 🔧 | Check-in da, QR/Checkliste fehlt | Custom-Konzept | claim |
| F11 | Multi-Item-Buchung | ✅ | Additional Resources je Reservierung | — | codex |
| F12 | Buchungs-Dashboard | ✅ | „My Dashboard" | — | session |
| F13 | Kalenderübersicht | ✅ | Schedule/FullCalendar | — | session |
| F14 | Approval-Workflow | ✅ | pro Ressource (RequiresApprovalRule) | — | codex |
| F15 | Vorlaufzeiten | ✅ | je Ressource (Add/Update/Delete, Max Notice) | — | codex |
| F16/F17 | Personal-Timeslots | 🔧 | echte Lücke (keine Personalverfügbarkeit) | Custom-Konzept | codex |
| F18 | Blackout-Daten | ✅ | Blackout Times | — | codex |
| F19 | Timeslot-Storno | 🔧 | hängt an F16/F17 | nach F16/F17 | claim |
| F20 | iCal-Feed | ✅🟦 | Subscription-Feeds; **pro Schedule/Resource erlauben** | Config prüfen | codex |
| F21 | E-Mail-Benachrichtigungen | ✅ | create/update/delete/approve (NotificationServices) | — | codex |
| F22 | Per-Item-Mailtemplates | 🔧 | Templates **global/dateibasiert, NICHT per Item** → Custom | Custom-Konzept | codex |
| F23 | Reminder-Timing | 🟦 | `reminders.enabled` + start/end | Config-PR | claim |
| F24 | Webhooks | 🔧 | nicht nativ; via PostReservation-Plugin | Plugin-Konzept | codex |
| F25 | Robuste Suche | 🟨🔧 | nur SQL-LIKE auf Titel/Beschr./Ref.; kein Fuzzy/Synonym | Custom | codex |
| F26 | Mehrsprachigkeit | ✅🟦 | de_de installiert; Default=de_de = **Config** (lokal gesetzt+verifiziert; config.dist bleibt en_us) | in Prod-config setzen | session+codex |
| F27 | Public Homepage | 🔧→🟦 | **Landing-Prototyp gebaut** (`Web/zhl-welcome.php`) | verdrahten | session |
| F28 | REST-API | ✅🟦 | nativ (WebServices/), **Default aus** (live an) | Config prüfen | session+codex |
| F29 | Analytics/Reports | ✅ | Reports-Modul (rollen-/config-abhängig) | Rollen klären | codex |
| F30 | Komponenten/Sub-Objekte | 🟨🔧 | Accessories (Menge) da; Checkliste fehlt | scout + Custom | claim |
| F31 | Max. Buchungsdauer | ✅ | min/max je Ressource | — | codex |
| F32 | User-Buchungslimits | ✅ | Quotas (ManageQuotas) | — | codex |
| F33 | Storno-Fristen | ✅ | nur **Mindestfristen**, kein Storno-Workflow | — | codex |
| F34 | Overdue-Handling | 🟨 | Check-in + Reminder; Eskalation? | scout | claim |
| F35 | Verlängerungen | 🟨 | nur „Reservierung ändern", **kein expliziter Verlängerungsprozess** | Custom falls nötig | codex |
| F36 | Waitlist | 🟦 | Capability nativ, **Default aus** (`allow.wait.list`) | Config-PR | codex |
| F37 | Audit-Log | 🟨 | Activity/History teilweise | scout | claim |
| F38 | DSGVO | 🟨 | Export/Löschung teils manuell | scout | claim |
| F39 | Mobile-Responsive | ✅ | Bootstrap 5 responsiv | mobil testen | codex |
| **F40** | **Einweisungs-/Berechtigungspflicht** (Medium nur nach persönl. Einweisung/Zertifikat buchbar) | ✅🔧 | **Gating nativ**: `group_resource_permissions`/`user_resource_permissions` + `PermissionValidationRule` (nur berechtigte Gruppe/User dürfen die Ressource buchen). **Custom:** Badge/Zertifikat-Lifecycle (Ausstellung/Ablauf), Einweisungstermin als buchbare Ressource, „Zugang anfragen"-Flow, Auto-Entzug | Konzept (Codex-Gate) | session |

## Zusammenfassung (nach Codex-Gate 2026-06-22)
- ✅ nativ: ~20 · 🟦 Konfig (auch hybride ✅🟦): ~6 · 🟨 teilweise: ~6 · 🔧 Custom: ~6
- **Custom-Schwerpunkte:** F8/F16/F17 (Personal-Übergabe-Timeslots), F10/F30 (QR + Checkliste), F22 (per-Item-Mails), F24 (Webhooks-Plugin), F25 (Fuzzy-Suche).
- **Quick-Wins (Config, je 1 PR):** F26 (erledigt), F23 Reminder, F36 Waitlist, F28/F20 ggf. an.
- **Codex-Korrekturen:** mehrere „✅" waren real „nativ, aber per Config **aus**" (F20/F26/F28/F36); F22 ist **nicht** per-Item; F6/F35 abgewertet; F24 ist Plugin-Custom, nicht nativ.

## Querschnitt-Themen (von Codex ergänzt — in STRATEGY aufnehmen)
- **Betrieb/Deploy:** SFTP-only; Schreibrechte für `tpl_c/`, `uploads/`, Attachment-Pfad, Smarty-Cache als eigenes Risiko führen.
- **Security/Datenschutz:** Anhänge, Rich-Text, öffentliche Kalenderfeeds, API-Aktivierung, Rollenmodell explizit absichern.
- **ZHL-Übergabe-Datenmodell:** Personalverfügbarkeit, Übergabeprotokoll, QR-Scan, Checkliste, Verantwortliche, Eskalation = klares Custom-Modul (bündelt F8/F10/F16/F17/F30/F34).

> Offene `claim`-Zeilen: per Scout am Code belegen, dann Codex-Gate.

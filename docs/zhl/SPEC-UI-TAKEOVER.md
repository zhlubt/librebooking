# SPEC — LibreBooking-Optik vollständig durch ZHL-Layout ersetzen

Ziel: Endnutzer sehen **nur noch** die ZHL-Oberfläche (grüne Sticky-Nav, dunkler Footer, ZHL-Login,
ZHL-Dashboard als Landing). Native LibreBooking-Chrome verschwindet überall — ohne Admin-Funktionen
oder Login zu brechen. Staging `media.zhl-ubt.de`. Code/Plan von Codex geprüft.

## Architektur (verifiziert)
- Jede Seite rendert `{include globalheader.tpl}` … `{include globalfooter.tpl}`.
- `globalheader.tpl`: Zeilen 1–119 = `<html><head>` + CSS-Load (immer), ab 120 `{if !HideNavBar}`-Block =
  Logo + native Navbar (bis ~434). `globalfooter.tpl`: schließt `#main`, nativer Footer + Lang-JS.
- Gemeinsame Template-Vars aus `Pages/Page.php`-Konstruktor: `$LoggedIn, $CanViewAdmin,
  $CanViewGroupAdmin/ResourceAdmin/ScheduleAdmin, $CanViewReports, $Path, $HomeUrl, $AppTitle,
  $CssExtensionFile, $cssTheme, $AvailableLanguages`.
- Post-Login-Routing: `Presenters/Authentication/LoginRedirector.php` → `HomepageId` →
  `Pages::UrlFromId()`; Default `Pages::DEFAULT_HOMEPAGE_ID = 1 = dashboard.php`
  (`Pages/Pages.php` $_pages; config `default.homepage`).
- Login: `Web/index.php` → `new LoginPage()`; ZHL-Variante existiert: `ZhlLoginPage` + `tpl/zhl-login.tpl`.
- ZHL-Custom-Seiten (zhl-dashboard/book/…): rendern in globalheader/footer mit `HideNavBar=true`.
- ZHL-Nav-Markup-Vorlage: `Web/includes/zhl-nav.php` (`.zhl-head` grüne Nav), Footer: `zhl-footer.php`,
  Styles: `Web/css/zhl-landing.css` (volle Hülle — NICHT global laden, klobbert native Seiten).

## Plan

### 1. Scoped Chrome-CSS (neu) `Web/css/zhl-chrome.css`
Nur Nav + Footer-Styles (aus zhl-landing.css extrahiert: `.zhl-head, .wrap.nav, .logo, .nav-links,
.nav-right, .nav-ghost, .nav-cta, .lang-btn, .zhl-foot, …`) + die nötigen Farbtokens. **Bewusst eng
gescoped**, damit native Seiteninhalte (Tabellen, Admin-Formulare) unberührt bleiben. Global in
globalheader laden (zusätzlich zu `zhl-theme.css`, das die Bootstrap-Inhalte UBT-grün einfärbt).

### 2. `globalheader.tpl` — native Nav → ZHL-Nav
Im `{if !HideNavBar}`-Block den Logo-Block (121–131) + `<nav class="navbar">` (132–~434) ersetzen durch
eine **Smarty-Portierung von zhl-nav.php**:
- Logo (`{$Path}img/…`), Markenname.
- Eingeloggt (`$LoggedIn`): Links **Geräte** (`{$Path}zhl-dashboard.php`), **Meine Buchungen**
  (`{$Path}zhl-bookings.php`), **Konto** (`{$Path}zhl-account.php`).
- **Admin-Erhalt:** wenn `$CanViewAdmin` (bzw. Group/Resource/Schedule-Admin/Reports) → Dropdown
  **„Verwaltung"** mit den nativen Admin-Links (manage_reservations/resources/users/groups/schedules/
  accessories/attributes/announcements, view_resources/schedules, reports) **plus** ZHL-Admin
  (zhl-certificates-admin, zhl-bundles-admin, zhl-handover-admin, später zhl-medienmanager). Damit geht
  KEIN Admin-Zugang verloren — nur optisch im ZHL-Stil.
- **Abmelden** (`{$Path}logout.php`), **Sprach-Toggle** (nutzt vorhandene Lang-Mechanik;
  data-en + zhl-landing.js ODER native `AvailableLanguages` — siehe Codex-Frage).
- Nicht eingeloggt (Login-Seite hat eh kein LoggedIn): minimaler öffentlicher Header oder nichts.
- Aktiv-Markierung über einen `$NavActive`-Hinweis (optional; Page setzt ihn, sonst keiner aktiv).
- HideNavBar-Guard bleibt erhalten (für Sonderfälle wie reine Erfolg-/Druckseiten).

### 3. `globalfooter.tpl` — nativer Footer → ZHL-Footer
Footer-Markup durch ZHL-`.zhl-foot` ersetzen (Smarty-Portierung von zhl-footer.php). **Lang-Switcher-JS
und Analytics-Logik beibehalten** (am Seitenende). `#main`-Div-Schließung beibehalten.

### 4. ZHL-Custom-Seiten zeigen die globale Nav
In `ZhlDashboardPage/ZhlBookPage/ZhlAssistantPage/ZhlCertificatesAdminPage/ZhlBundlesAdminPage`
das `HideNavBar=true` entfernen (oder auf false), damit die globale grüne Nav auch dort erscheint
(Konsistenz). Deren eigener `zhl-dash-head` bleibt als Seitentitel-Block unter der Nav.
*Risiko:* Doppel-Branding (Nav-Logo + dash-head) — optisch prüfen, ggf. dash-head verschlanken.

### 5. Post-Login-Landing → ZHL-Dashboard
Sauberster Weg ohne Bruch nativer Admin-Flows: in `Pages/Pages.php` einen Eintrag für `zhl-dashboard.php`
registrieren (neue ID) und `default.homepage` (config) darauf setzen — ODER `LoginRedirector` so
anpassen, dass der Fallback `zhl-dashboard.php` ist, wenn der User keine eigene Homepage gewählt hat.
*Codex-Frage:* ID-Registry erweitern vs. Redirector-Fallback ändern — was ist robuster (Homepage-Wahl
im Profil, Resume-URL-Redirect bleiben erhalten)?

### 6. Login im ZHL-Look
`Web/index.php` auf `ZhlLoginPage` umstellen (require + `new ZhlLoginPage()`), Login-/Sprachwechsel-
Logik der Basis `LoginPage` bleibt. **Vorab verifizieren**, dass `ZhlLoginPage` + `tpl/zhl-login.tpl`
voll funktionieren (POST-Login, Fehleranzeige, „Konto anlegen", CSRF). Fällt der ZHL-Login durch, native
`login.tpl` stattdessen via globalheader-Chrome reskinnen. **Login-Bruch = Aussperrung → zuerst testen.**

### 7. „Meine Buchungen/Konto" real (Phase 2, eigener Block E)
`zhl-bookings.php`/`zhl-account.php`/`zhl-booking-detail.php` von Standalone-Mock zu echten
`SecurePage`-Seiten (LB-Bootstrap, echte Reservierungen/Userdaten; Storno/Passwort über native Services).
Für diese Übernahme zunächst **Nav-Links zeigen lassen**; bis Phase 2 zeigen die Seiten Platzhalter —
ODER vorerst auf native `my-calendar.php`/`profile.php` verlinken (die jetzt ZHL-Chrome tragen), bis die
echten ZHL-Seiten stehen. *Codex-Frage:* Übergangsverlinkung empfehlenswert?

## Risiken / Testpflicht
- **Login** zuerst (Aussperrung). **Admin-Zugang** (alle nativen Verwaltungslinks erreichbar?).
- Native Seiten (schedule/my-calendar/reservation/profile) rendern mit ZHL-Nav ohne Layout-Bruch?
- Sprach-Toggle funktioniert weiter.
- HideNavBar-Sonderfälle (Erfolg-/Druck-/Embed-Seiten) nicht versehentlich mit Nav versehen.
- `tpl_c` nach .tpl-Änderungen leeren. DB-Backup vor Deploy (Disziplin; hier reiner Code).

## Codex-Entscheidungen (übernommen)
- **Navbar NICHT rausreißen, sondern umstylen + Links umbiegen.** Bootstrap-Navbar-Skelett
  (navbar-expand-lg, navbar-toggler, collapse, Dropdowns, alle `{if}`-Gates, Element-IDs) **behalten**
  → Mobile-Collapse, Admin-Parität, JS-IDs bleiben. Nur die **Nutzer-Items** (Dashboard, MyAccount-,
  Schedule-, Check-Dropdown, Z.142–206) durch saubere ZHL-Links ersetzen: **Geräte**
  (`{$Path}zhl-dashboard.php`), **Meine Buchungen** (`{$Path}my-calendar.php` — bis Phase 2 nativ,
  jetzt grün), **Konto** (`{$Path}profile.php`). Admin-/Responsibility-/Reports-Dropdowns (ab Z.207)
  **unverändert** lassen (alle Gates: CanViewAdmin/Responsibilities/Group/Resource/ScheduleAdmin/
  Reports, PaymentsEnabled, EnableConfigurationPage, ShowNewVersion) + ZHL-Admin-Links
  (zhl-certificates-admin/zhl-bundles-admin/zhl-handover-admin) ergänzen. Grün via `zhl-chrome.css`
  (Navbar bekommt Zusatzklasse `zhl-navbar`, ZHL-Logo).
- **Footer-Guard:** ZHL-Footer + Back-to-Top in `globalfooter.tpl` in denselben
  `{if !HideNavBar}`-Guard hängen (Kiosk/Monitor/QR/Druck dürfen kein großes ZHL-Chrome bekommen).
  `@media print { .zhl-navbar,.zhl-footer,#button-up{display:none!important} }`.
- **Login NICHT umschalten.** `Web/index.php` + `LoginPage` bleiben; `login.tpl` läuft eh durch
  globalheader/footer → bekommt das grüne Chrome automatisch. ZhlLoginPage/zhl-login.tpl ist NICHT
  feature-äquivalent (type=email blockt Username-Login, Lang-Toggle nur client, OAuth/Keycloak/FB,
  Announcements, Captcha-Messages fehlen) → nicht als Entry verwenden.
- **Routing:** `Pages::ZHL_DASHBOARD='zhl-dashboard.php'` ergänzen; in `Pages/Pages.php` $_pages **ID 1**
  von `Pages::DASHBOARD` auf `Pages::ZHL_DASHBOARD` umbiegen; `DEFAULT_HOMEPAGE_ID=1`,
  `default.homepage=1` lassen; `LoginRedirector` NICHT anfassen; `Pages::UrlFromId()` gegen unbekannte
  gespeicherte IDs härten (kein Undefined-Array-Error).
- **CSS strikt namespacen:** keine generischen Selektoren (`.wrap/.logo/.card/.btn/.row/--green`).
  `.zhl-navbar`, `.zhl-navbar__…`, `.zhl-footer`; Custom-Props `--zhl-chrome-*`. Ladeordnung
  Bootstrap→LB→zhl-theme.css→zhl-chrome.css. Chrome bekommt **explizite Farben** (unabhängig von
  data-bs-theme). Dark-Mode-Kontrast der Inhalte testen.
- **Sprache:** native `AvailableLanguages`/Server-Mechanik nutzen, kein `data-en`.
- **Übergangslinks:** bewusst native `my-calendar.php` + `profile.php`/`password.php` (sicher,
  echte Daten) bis Phase 2; die ungesicherten Standalone-Mocks (zhl-bookings/account/detail) NICHT verlinken.

## Reihenfolge
1. zhl-chrome.css + globalheader/footer-Swap + ZHL-Seiten HideNavBar lösen (Kern der Optik-Übernahme).
2. Routing (Login-Landing) + Login-Reskin.
3. Verifizieren (Login/Admin/native Seiten) → Deploy.
4. Phase 2 (echte Daten in bookings/account/detail) separat.

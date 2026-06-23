# Empfohlene config.php-Änderungen (Säule 2: „mehr Optionen")

`config/config.php` enthält Secrets und wird **nicht** versioniert. Diese Datei
dokumentiert die ZHL-Einstellungen, damit sie reproduzierbar und reviewbar sind.
Quick-Wins, die bereits in LibreBooking stecken — nur einschalten.

## Sofort (Teil dieses PRs, lokal verifiziert)
```php
'default.language' => 'de_de',   // war 'en_us' -> komplette UI deutsch
```

## Empfohlen als Nächstes (eigene PRs, je einzeln testen)
```php
// Reminder-E-Mails (F21/F23)
'reservation' => [
    'reminders.enabled' => true,
    'default.start.reminder' => '60',   // Minuten vorher (Vorschlag)
    'default.end.reminder'   => '60',
],

// Buchungslimits / Quotas (F32) -> über Admin-UI je Ressource/Gruppe statt config

// Self-Service vs. Manager-Übergabe (F8): pro Ressource im Admin steuern
```

## Branding (umgesetzt, PR #2)
```php
'css.extension.file' => 'css/zhl-theme.css',   // Datei im Repo: Web/css/zhl-theme.css
'app.title' => 'Medienausleihe ZHL',           // optional (Live nutzt 'Reservierungen')
```
`Web/css/zhl-theme.css` ist versioniert (UBT-Grün-Overlay, upgrade-sicher). Der Config-Key
lebt in `config.php` (nicht im Repo), Wert `css/zhl-theme.css`. Verifiziert: Login-Button
rgb(0,146,96), Test `tests-e2e/tests/branding.spec.js`.

## Deutsche/vereinfachte Begriffe ohne lang/ zu patchen
**Umgesetzt:** `config/lang-overrides.php` (im Repo, trackbar) — de_de: „Ressource"→„Gerät"
für Standard-User-Labels (Admin-Fachbegriffe unangetastet). Verifiziert (manage_quotas
zeigt „Alle Geräte"), Test `tests-e2e/tests/i18n-overrides.spec.js`.

## Landing-Page verdrahten (empfohlener, upgrade-sicherer Weg)
Live läuft die App bereits unter `…/Web`. Daher **die Landing an die Domain-Wurzel**:
`buchung.zhl-ubt.de/` → `zhl-welcome.php` (statisch/PHP), App bleibt unter `/Web`.
Kein Core-Edit, keine Redirect-Schleife, eingeloggte Nutzer gehen direkt auf `/Web`.
Konkret: `zhl-welcome.php` ins Web-Root (oberhalb von `/Web`) legen, CTAs zeigen auf
`/Web/index.php` (Login) und `/Web/register.php`. (Lokal-Prototyp liegt unter
`Web/zhl-welcome.php`.)

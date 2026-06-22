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

## Branding (eigener PR)
```php
'app.title' => 'Medienausleihe ZHL',
'css.extension.file' => 'zhl-theme.css',   // unter Web/css/ ablegen
```

## Deutsche/vereinfachte Begriffe ohne lang/ zu patchen
`config/lang-overrides.php` nutzen (Upstream-Mechanismus), z.B. „Resource" → „Gerät".
Siehe `config/lang-overrides.example.php`.

## Landing-Page aktivieren
Prototyp: `Web/zhl-welcome.php`. Für anonyme Besucher als Einstieg setzen — Optionen:
- Link/Weiterleitung von der Wurzel `index.php` auf `Web/zhl-welcome.php`, oder
- Web-Server-DocumentRoot/Rewrite. (Im nächsten PR sauber verdrahten.)

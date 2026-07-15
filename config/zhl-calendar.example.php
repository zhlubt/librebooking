<?php
/**
 * ZHL Kalender-Feed (.ics) — Konfiguration.
 *
 * Kopiere diese Datei nach config/zhl-calendar.php und setze einen langen, zufälligen
 * Schlüssel. Der Feed (Web/zhl-calendar.php) ist NUR mit ?key=<key> abrufbar — Outlook
 * abonniert die URL ohne Login, daher fungiert der Schlüssel als Bearer-Token.
 * NICHT committen (config/ ist gitignored).
 *
 * Schlüssel erzeugen z. B. mit:  php -r "echo bin2hex(random_bytes(24));"
 */
return [
    // Geheimer Abo-Schlüssel (Pflicht; leer => Feed liefert 403).
    'key' => 'REPLACE_WITH_LONG_RANDOM_SECRET',

    // Optionales Zeitfenster relativ zu heute (Tage). Defaults: 30 Tage zurück, 365 voraus.
    'past_days' => 30,
    'future_days' => 365,

    // Anzeigename des Kalenders in Outlook.
    'calendar_name' => 'ZHL Medien — Übergaben & Studio',
];

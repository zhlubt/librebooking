<?php
/**
 * ZHL Übergabe-Modul — Konfiguration (Vorlage).
 *
 * Kopiere diese Datei nach config/zhl-handover.php und trage die echten Werte ein.
 * config/zhl-handover.php ist gitignored (enthält den API-Key) — NIE committen.
 *
 * Der API-Key ist der 'crossbook_api_key' aus terminplaner_ubt (settings-Tabelle).
 */

return [
    // Basis-URL der terminplaner_ubt-Instanz (ohne abschließenden Slash).
    'terminplaner_base_url' => 'https://meet.zhl-ubt.de',

    // API-Key für die read-only Übergabe-Endpunkte (handover_slots / handover_lookup).
    'terminplaner_api_key' => 'REPLACE_WITH_CROSSBOOK_API_KEY',

    // Timeout (Sekunden) für HTTP-Aufrufe an terminplaner.
    'http_timeout' => 6,
];

<?php

/**
 * Kleiner Server-zu-Server-Client für die Terminplaner-API (meet.zhl-ubt.de).
 * Gemeinsame Basis für Buchung (ZhlBookPresenter hat noch eine eigene private Kopie) und
 * Stornierung (ZhlBookingDetailPresenter). Liest Basis-URL + API-Key aus config/zhl-handover.php.
 */
class ZhlTerminplaner
{
    /** @return array Konfiguration aus config/zhl-handover.php (oder .example.php als Fallback). */
    public static function Config(): array
    {
        $f = ROOT_DIR . 'config/zhl-handover.php';
        if (!is_readable($f)) {
            $f = ROOT_DIR . 'config/zhl-handover.example.php';
        }
        $c = is_readable($f) ? (require $f) : [];
        return is_array($c) ? $c : [];
    }

    /**
     * Ein API-Aufruf (X-API-Key). Gibt das dekodierte JSON-Array zurück oder null bei
     * Konfig-/Netzwerk-/Parsefehler (nie eine Exception — der Aufrufer behandelt null als „nicht ok").
     */
    public static function Request(string $method, string $path, array $query = [], ?array $body = null): ?array
    {
        $c = self::Config();
        $base = rtrim((string)($c['terminplaner_base_url'] ?? ''), '/');
        $key = (string)($c['terminplaner_api_key'] ?? '');
        $timeout = (int)($c['http_timeout'] ?? 6);
        if ($base === '' || $key === '' || $key === 'REPLACE_WITH_CROSSBOOK_API_KEY') {
            return null;
        }
        $url = $base . $path . ($query ? ('?' . http_build_query($query)) : '');
        $header = "X-API-Key: $key\r\nUser-Agent: zhl-cancel/1\r\n";
        $opt = ['method' => $method, 'timeout' => $timeout, 'ignore_errors' => true];
        if ($body !== null) {
            $header = "X-API-Key: $key\r\nContent-Type: application/json\r\nUser-Agent: zhl-cancel/1\r\n";
            $opt['content'] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }
        $opt['header'] = $header;
        $raw = @file_get_contents($url, false, stream_context_create(['http' => $opt]));
        if ($raw === false) {
            return null;
        }
        $d = json_decode($raw, true);
        return is_array($d) ? $d : null;
    }
}

<?php
/**
 * ZHL Übergabe-Modul — gemeinsame Helfer (kein direkter Web-Aufruf).
 *
 * Wird von Web/zhl-handover-select.php (Pull aus terminplaner) und
 * Web/zhl-handover-notify.php (optionaler Push) eingebunden. Hält die
 * Konfigurations-, HTTP- und DB-Logik an EINER Stelle.
 *
 * Bewusst leichtgewichtig (raw PDO aus config.php, wie Web/zhl-welcome.php) —
 * eindeutig ZHL-eigene Datei, upgrade-sicher, kein LibreBooking-Core.
 */

declare(strict_types=1);

if (!defined('ZHL_HANDOVER_LIB')) {
    define('ZHL_HANDOVER_LIB', 1);
}

/** Konfiguration laden (config/zhl-handover.php, sonst .example als Fallback). */
function zhl_handover_config(): array
{
    $root = dirname(__DIR__);
    $file = $root . '/config/zhl-handover.php';
    if (!is_readable($file)) {
        $file = $root . '/config/zhl-handover.example.php';
    }
    $conf = require $file;
    return is_array($conf) ? $conf : [];
}

/** PDO auf die LibreBooking-DB (aus config/config.php). */
function zhl_handover_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $conf = require dirname(__DIR__) . '/config/config.php';
    $db = $conf['settings']['database'] ?? [];
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['hostspec'] ?? '127.0.0.1', $db['name'] ?? ''),
        $db['user'] ?? '',
        $db['password'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    return $pdo;
}

/** GET an einen terminplaner-Endpunkt; gibt dekodiertes JSON oder null zurück. */
function zhl_handover_get(string $path, array $query): ?array
{
    $conf = zhl_handover_config();
    $base = rtrim((string)($conf['terminplaner_base_url'] ?? ''), '/');
    $key = (string)($conf['terminplaner_api_key'] ?? '');
    $timeout = (int)($conf['http_timeout'] ?? 6);
    if ($base === '' || $key === '' || $key === 'REPLACE_WITH_CROSSBOOK_API_KEY') {
        return null;
    }

    $url = $base . $path . '?' . http_build_query($query);
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "X-API-Key: $key\r\nUser-Agent: zhl-handover/1 (buchung.zhl-ubt.de)\r\n",
        'timeout' => $timeout,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/** Token-Format prüfen. */
function zhl_handover_valid_token(string $token): bool
{
    return (bool)preg_match('/^[A-Za-z0-9]{8,64}$/', $token);
}

/**
 * Übergabe-Buchungen für ein Token aus terminplaner ziehen und in
 * zhl_booking_handover als 'confirmed' upserten (Transaktion + Unique-Constraint
 * gegen Doppelbelegung). Gibt die bestätigten Typen zurück, z.B. ['pickup','return'].
 *
 * @return array{pickup: ?array, return: ?array} bestätigte Übergaben je Typ
 */
function zhl_handover_sync(string $token): array
{
    $result = ['pickup' => null, 'return' => null];
    if (!zhl_handover_valid_token($token)) {
        return $result;
    }

    $lookup = zhl_handover_get('/api/handover_lookup.php', ['token' => $token]);
    if (!$lookup || ($lookup['status'] ?? '') !== 'ok') {
        return $result;
    }

    $pdo = zhl_handover_db();
    $now = gmdate('Y-m-d H:i:s');

    foreach ($lookup['bookings'] ?? [] as $b) {
        $type = $b['handover_type'] ?? null;
        if (!in_array($type, ['pickup', 'return'], true)) {
            continue; // ohne klaren Typ nicht eintragen
        }
        // Upsert auf (handover_token, type) — Unique-Constraint verhindert Doppel.
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO zhl_booking_handover
                    (handover_token, type, terminplaner_booking_id, staff_member_id,
                     staff_role, scheduled_start_utc, scheduled_end_utc, status,
                     created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?)
                 ON DUPLICATE KEY UPDATE
                     terminplaner_booking_id = VALUES(terminplaner_booking_id),
                     staff_member_id = VALUES(staff_member_id),
                     staff_role = VALUES(staff_role),
                     scheduled_start_utc = VALUES(scheduled_start_utc),
                     scheduled_end_utc = VALUES(scheduled_end_utc),
                     status = 'confirmed',
                     updated_at = VALUES(updated_at)"
            );
            $stmt->execute([
                $token,
                $type,
                $b['booking_id'] ?? null,
                isset($b['staff_member_id']) ? (int)$b['staff_member_id'] : null,
                in_array($b['staff_role'] ?? '', ['primary', 'backup'], true) ? $b['staff_role'] : null,
                $b['start_utc'] ?? null,
                $b['end_utc'] ?? null,
                $now,
                $now,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('zhl_handover_sync: ' . $e->getMessage());
            continue;
        }
        $result[$type] = $b;
    }

    return $result;
}

/** User-ID, der ein Token erzeugt hat, oder null (Token noch nicht vergeben). */
function zhl_handover_token_owner(string $token): ?int
{
    if (!zhl_handover_valid_token($token)) {
        return null;
    }
    $stmt = zhl_handover_db()->prepare('SELECT user_id FROM zhl_handover_token WHERE handover_token = ?');
    $stmt->execute([$token]);
    $v = $stmt->fetchColumn();
    return $v === false ? null : (int)$v;
}

/**
 * Token für einen User beanspruchen (idempotent). Gibt true zurück, wenn das Token
 * danach diesem User gehört (frisch beansprucht ODER ihm bereits gehörend), false,
 * wenn es einem anderen User gehört.
 */
function zhl_handover_claim_token(string $token, int $userId, ?string $ref): bool
{
    if (!zhl_handover_valid_token($token) || $userId <= 0) {
        return false;
    }
    $pdo = zhl_handover_db();
    $stmt = $pdo->prepare(
        'INSERT INTO zhl_handover_token (handover_token, user_id, reference_number, created_at)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE handover_token = handover_token' // no-op: nie Eigentümer überschreiben
    );
    $stmt->execute([$token, $userId, $ref, gmdate('Y-m-d H:i:s')]);
    return zhl_handover_token_owner($token) === $userId;
}

/** Aktueller Bestätigungsstatus eines Tokens aus der DB (ohne terminplaner-Aufruf). */
function zhl_handover_status(string $token): array
{
    $status = ['pickup' => false, 'return' => false];
    if (!zhl_handover_valid_token($token)) {
        return $status;
    }
    $pdo = zhl_handover_db();
    $stmt = $pdo->prepare(
        "SELECT type FROM zhl_booking_handover
         WHERE handover_token = ? AND status IN ('confirmed','done')"
    );
    $stmt->execute([$token]);
    foreach ($stmt->fetchAll() as $row) {
        if (isset($status[$row['type']])) {
            $status[$row['type']] = true;
        }
    }
    return $status;
}

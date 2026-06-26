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
        // Fallback nur als Struktur-Quelle; der Platzhalter-Key lässt jeden
        // terminplaner-Aufruf bewusst ins Leere laufen (kein stilles Fehlverhalten).
        error_log('zhl-handover: config/zhl-handover.php fehlt — nutze .example (Modul inaktiv).');
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

/** Strukturiertes Zubehör einer Ressource (resource_accessories ⋈ accessories). */
function zhl_handover_resource_accessories(int $resourceId): array
{
    $stmt = zhl_handover_db()->prepare(
        'SELECT a.accessory_id, a.accessory_name, ra.minimum_quantity, ra.maximum_quantity
         FROM resource_accessories ra JOIN accessories a ON a.accessory_id = ra.accessory_id
         WHERE ra.resource_id = ? ORDER BY a.accessory_name'
    );
    $stmt->execute([$resourceId]);
    return $stmt->fetchAll();
}

/** Übergabe-Datensätze für die Admin-Übersicht (optional gefiltert nach Status). */
function zhl_handover_list(?string $status = null): array
{
    $sql = "SELECT h.*, r.name AS resource_name,
                   EXISTS(SELECT 1 FROM zhl_handover_check c
                          WHERE c.type = h.type
                            AND (c.reference_number = h.reference_number OR c.handover_token = h.handover_token)) AS has_check
            FROM zhl_booking_handover h
            LEFT JOIN resources r ON r.resource_id = h.resource_id";
    $params = [];
    if ($status !== null && in_array($status, ['requested', 'confirmed', 'done'], true)) {
        $sql .= ' WHERE h.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY h.updated_at DESC, h.id DESC LIMIT 200';
    $stmt = zhl_handover_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Ausleihenden-Namen zu einer Übergabe auflösen (Block D, Medienmanager-Tagesseite).
 *
 * Bevorzugt die LB-Reservierung über reference_number (zuverlässigste Quelle, da der
 * Eigentümer der Reservierung der Ausleihende ist):
 *   reference_number → reservation_instances → reservation_series.owner_id → users.
 * Fällt auf das handover_token zurück (zhl_handover_token.user_id → users), falls noch
 * keine reference_number nachgetragen wurde (Token wird beim Buchen vor der Reservierung
 * gewählt). Gibt einen menschenlesbaren Namen oder '' zurück.
 */
function zhl_handover_borrower_name(?string $reference, ?string $token): string
{
    $pdo = zhl_handover_db();

    $fmt = static function ($row): string {
        if (!$row) {
            return '';
        }
        $name = trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));
        if ($name === '') {
            $name = (string)($row['username'] ?? $row['email'] ?? '');
        }
        return $name;
    };

    if ($reference !== null && $reference !== '') {
        $stmt = $pdo->prepare(
            'SELECT u.fname, u.lname, u.username, u.email
             FROM reservation_instances ri
             JOIN reservation_series rs ON rs.series_id = ri.series_id
             JOIN users u ON u.user_id = rs.owner_id
             WHERE ri.reference_number = ?
             LIMIT 1'
        );
        $stmt->execute([$reference]);
        $name = $fmt($stmt->fetch());
        if ($name !== '') {
            return $name;
        }
    }

    if ($token !== null && zhl_handover_valid_token($token)) {
        $stmt = $pdo->prepare(
            'SELECT u.fname, u.lname, u.username, u.email
             FROM zhl_handover_token t
             JOIN users u ON u.user_id = t.user_id
             WHERE t.handover_token = ?
             LIMIT 1'
        );
        $stmt->execute([$token]);
        return $fmt($stmt->fetch());
    }

    return '';
}

/** Default-Rückgabeort eines Geräts (zhl_uebergabe.rueckgabeort) oder '' (Block D). */
function zhl_handover_rueckgabeort(?int $resourceId): string
{
    if (!$resourceId) {
        return '';
    }
    $stmt = zhl_handover_db()->prepare('SELECT rueckgabeort FROM zhl_uebergabe WHERE resource_id = ?');
    $stmt->execute([$resourceId]);
    $v = $stmt->fetchColumn();
    return $v === false || $v === null ? '' : (string)$v;
}

/**
 * Fällige RÜCKGABEN eines Tages (Block D1, Medienmanager-Tagesseite).
 *
 * Alle zhl_booking_handover-Zeilen mit type='return', deren scheduled_end_utc in
 * das (in UTC umgerechnete) Tagesfenster [$startUtc, $endUtc) fällt. Joint das Gerät
 * (resources) und den Default-Rückgabeort (zhl_uebergabe). Storno ('cancelled') wird
 * ausgeblendet. $startUtc/$endUtc sind 'Y-m-d H:i:s'-UTC-Grenzen (halboffenes Intervall).
 *
 * @return array<int,array<string,mixed>>
 */
function zhl_handover_returns_due(string $startUtc, string $endUtc): array
{
    $stmt = zhl_handover_db()->prepare(
        "SELECT h.id, h.handover_token, h.reference_number, h.resource_id, h.status,
                h.scheduled_start_utc, h.scheduled_end_utc,
                r.name AS resource_name,
                u.rueckgabeort
         FROM zhl_booking_handover h
         LEFT JOIN resources r ON r.resource_id = h.resource_id
         LEFT JOIN zhl_uebergabe u ON u.resource_id = h.resource_id
         WHERE h.type = 'return'
           AND h.status <> 'cancelled'
           AND h.scheduled_end_utc >= ?
           AND h.scheduled_end_utc < ?
         ORDER BY u.rueckgabeort IS NULL, u.rueckgabeort, h.scheduled_end_utc, h.id"
    );
    $stmt->execute([$startUtc, $endUtc]);
    return $stmt->fetchAll();
}

/**
 * Aktuell offene Rückgabe eines Geräts (Block D3, Material-QR-Scan-Ziel).
 * = type='return', status IN ('requested','confirmed'), älteste Soll-Rückgabe zuerst.
 * Gibt die Zeile (inkl. resource_name + rueckgabeort) oder null zurück.
 *
 * @return array<string,mixed>|null
 */
function zhl_handover_open_return_for_resource(int $resourceId): ?array
{
    if ($resourceId <= 0) {
        return null;
    }
    $stmt = zhl_handover_db()->prepare(
        "SELECT h.id, h.handover_token, h.reference_number, h.resource_id, h.status,
                h.scheduled_start_utc, h.scheduled_end_utc,
                r.name AS resource_name,
                u.rueckgabeort
         FROM zhl_booking_handover h
         LEFT JOIN resources r ON r.resource_id = h.resource_id
         LEFT JOIN zhl_uebergabe u ON u.resource_id = h.resource_id
         WHERE h.resource_id = ?
           AND h.type = 'return'
           AND h.status IN ('requested','confirmed')
         ORDER BY h.scheduled_end_utc IS NULL, h.scheduled_end_utc, h.id
         LIMIT 1"
    );
    $stmt->execute([$resourceId]);
    $row = $stmt->fetch();
    return $row ?: null;
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

<?php

/**
 * ZHL Audit-Log (F37) — schlanke, robuste Schreib-Helfer für das systemweite Protokoll.
 *
 * Eine Zeile je nennenswerter Aktion in `zhl_audit`. Bewusst additiv (raw PDO aus config.php
 * über zhl_handover_db(), wie der Rest des ZHL-Tooling) und kapselt ALLE Fehler — ein
 * fehlgeschlagenes Audit darf die auslösende Aktion (Buchung, Speichern …) NIE kippen.
 *
 * Aufruf:
 *   require_once __DIR__ . '/zhl-audit-lib.php';   // (Web/-Seiten)
 *   require_once ROOT_DIR . 'Web/zhl-audit-lib.php'; // (Presenter)
 *   zhl_audit_log([
 *     'action' => 'booking.create',
 *     'actor_user_id' => 308, 'actor_name' => 'Paul Dölle',
 *     'entity_type' => 'reservation', 'entity_id' => '42',
 *     'reference_number' => 'abcd1234',
 *     'detail' => ['device' => 'Pocket 6K', 'fulfillment' => 'pickup'],
 *   ]);
 */

require_once __DIR__ . '/zhl-handover-lib.php';

if (!function_exists('zhl_audit_log')) {
    /**
     * Schreibt eine Audit-Zeile. Schlägt nie nach außen durch (try/catch).
     *
     * @param array $e Schlüssel: action (Pflicht), actor_user_id, actor_name, entity_type,
     *                 entity_id, reference_number, detail (string|array → JSON), created_utc (optional).
     */
    function zhl_audit_log(array $e): void
    {
        try {
            $pdo = zhl_handover_db();
            $cut = static fn($v, int $n): ?string => ($v === null || $v === '') ? null : mb_substr((string)$v, 0, $n);

            $detail = $e['detail'] ?? null;
            if (is_array($detail)) {
                $detail = json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } elseif ($detail !== null) {
                $detail = (string)$detail;
            }

            $ip = null;
            if (!empty($_SERVER['REMOTE_ADDR'])) {
                $ip = mb_substr((string)$_SERVER['REMOTE_ADDR'], 0, 45);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO zhl_audit
                   (created_utc, actor_user_id, actor_name, action, entity_type, entity_id, reference_number, detail, ip)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                !empty($e['created_utc']) ? (string)$e['created_utc'] : gmdate('Y-m-d H:i:s'),
                isset($e['actor_user_id']) && $e['actor_user_id'] !== null ? (int)$e['actor_user_id'] : null,
                $cut($e['actor_name'] ?? null, 190),
                $cut($e['action'] ?? 'unknown', 64),
                $cut($e['entity_type'] ?? null, 48),
                $cut($e['entity_id'] ?? null, 64),
                $cut($e['reference_number'] ?? null, 64),
                $detail,
                $ip,
            ]);
        } catch (Throwable $ex) {
            if (class_exists('Log')) {
                Log::Error('zhl_audit_log failed (action=%s): %s', (string)($e['action'] ?? '?'), $ex->getMessage());
            }
        }
    }
}

if (!function_exists('zhl_audit_actor')) {
    /**
     * Bequemer Actor-Extraktor aus einer LibreBooking UserSession.
     * @return array{actor_user_id:?int, actor_name:?string}
     */
    function zhl_audit_actor($user): array
    {
        if ($user === null) {
            return ['actor_user_id' => null, 'actor_name' => null];
        }
        $name = trim((string)($user->FirstName ?? '') . ' ' . (string)($user->LastName ?? ''));
        return [
            'actor_user_id' => isset($user->UserId) ? (int)$user->UserId : null,
            'actor_name' => $name !== '' ? $name : (string)($user->Email ?? ''),
        ];
    }
}

<?php
/**
 * ZHL Übergabe-Modul — Push-Trigger (optional).
 *
 * Erlaubt es terminplaner_ubt (oder einem Cron), nach einer Übergabe-Buchung den
 * Abgleich anzustoßen, ohne dass der Nutzer im Assistenten "Status aktualisieren"
 * klicken muss. Die eigentlichen Daten werden weiterhin PULL-seitig aus terminplaner
 * gezogen (zhl_handover_sync) — dieser Endpunkt löst den Pull nur aus.
 *
 * Auth: X-API-Key == config/zhl-handover.php['terminplaner_api_key'] (gemeinsames Secret).
 * Body/Query: token=<handover-token>
 */

declare(strict_types=1);

require __DIR__ . '/zhl-handover-lib.php';

header('Content-Type: application/json; charset=utf-8');

$conf = zhl_handover_config();
$expected = (string)($conf['terminplaner_api_key'] ?? '');
$provided = (string)($_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? ''));

if ($expected === '' || $expected === 'REPLACE_WITH_CROSSBOOK_API_KEY'
    || !$provided || !hash_equals($expected, $provided)) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$token = (string)($_POST['token'] ?? ($_GET['token'] ?? ''));
if (!zhl_handover_valid_token($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid token']);
    exit;
}

$synced = zhl_handover_sync($token);
echo json_encode([
    'status' => 'ok',
    'token' => $token,
    'confirmed' => [
        'pickup' => $synced['pickup'] !== null,
        'return' => $synced['return'] !== null,
    ],
]);

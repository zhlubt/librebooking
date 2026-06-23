<?php
/**
 * Verifiziert die SQL-Logik des Übergabe-Gates (ZhlHandoverValidation) gegen die
 * lokale Dev-DB — ohne das volle LibreBooking-Framework. Spiegelt exakt die Queries
 * aus dem Plugin und prüft die vier Entscheidungsfälle. Seedet temporär und räumt
 * danach restlos auf.
 *
 * Lauf:  php docs/zhl/verify-handover-sql.php
 */

declare(strict_types=1);

require __DIR__ . '/../../Web/zhl-handover-lib.php';

$pdo = zhl_handover_db();
$pass = 0;
$fail = 0;
function check(string $label, bool $cond): void
{
    global $pass, $fail;
    echo ($cond ? "  PASS  " : "  FAIL  ") . $label . "\n";
    $cond ? $pass++ : $fail++;
}

// Konstanten wie im Plugin.
const CAT_RESOURCE = 4;
const LABEL_REQUIRED = 'handover_required';

// --- Seed ---
$now = gmdate('Y-m-d H:i:s');
$resourceId = (int)$pdo->query('SELECT resource_id FROM resources ORDER BY resource_id LIMIT 1')->fetchColumn();
$otherResourceId = (int)$pdo->query('SELECT resource_id FROM resources ORDER BY resource_id DESC LIMIT 1')->fetchColumn();
$tokenIncomplete = 'TESTtoken' . substr(bin2hex(random_bytes(4)), 0, 8);
$tokenComplete   = 'TESTtoken' . substr(bin2hex(random_bytes(4)), 0, 8);

// handover_required Attribut (Resource-Kategorie)
$pdo->prepare(
    'INSERT INTO custom_attributes (display_label, display_type, attribute_category, is_required)
     VALUES (?, 1, ?, 0)'
)->execute([LABEL_REQUIRED, CAT_RESOURCE]);
$attrId = (int)$pdo->lastInsertId();

// Wert "1" für die eine Ressource
$pdo->prepare(
    'INSERT INTO custom_attribute_values (custom_attribute_id, attribute_value, entity_id, attribute_category)
     VALUES (?, ?, ?, ?)'
)->execute([$attrId, '1', $resourceId, CAT_RESOURCE]);

// Übergabe-Zeilen: token1 nur pickup, token2 pickup+return (alle confirmed)
$ins = $pdo->prepare(
    "INSERT INTO zhl_booking_handover (handover_token, type, status, created_at, updated_at)
     VALUES (?, ?, 'confirmed', ?, ?)"
);
$ins->execute([$tokenIncomplete, 'pickup', $now, $now]);
$ins->execute([$tokenComplete, 'pickup', $now, $now]);
$ins->execute([$tokenComplete, 'return', $now, $now]);

// --- Spiegel-Queries (identisch zum Plugin) ---
$resolve = function (string $label, int $cat) use ($pdo): ?int {
    $s = $pdo->prepare('SELECT custom_attribute_id FROM custom_attributes WHERE display_label = ? AND attribute_category = ? LIMIT 1');
    $s->execute([$label, $cat]);
    $v = $s->fetchColumn();
    return $v === false ? null : (int)$v;
};
$requires = function (int $rid, int $aid) use ($pdo): bool {
    $s = $pdo->prepare('SELECT attribute_value FROM custom_attribute_values WHERE entity_id = ? AND custom_attribute_id = ? AND attribute_category = ? LIMIT 1');
    $s->execute([$rid, $aid, CAT_RESOURCE]);
    $row = $s->fetch();
    if (!$row) {
        return false;
    }
    return in_array(strtolower(trim((string)$row['attribute_value'])), ['1', 'true', 'yes', 'ja', 'on'], true);
};
$confirmedTypes = function (string $token) use ($pdo): array {
    $s = $pdo->prepare("SELECT type FROM zhl_booking_handover WHERE handover_token = ? AND status IN ('confirmed','done')");
    $s->execute([$token]);
    return array_column($s->fetchAll(), 'type');
};

// --- Asserts ---
echo "Übergabe-Gate SQL-Verifikation (Ressource $resourceId pflichtig, $otherResourceId nicht)\n";
check('handover_required-Attribut auflösbar', $resolve(LABEL_REQUIRED, CAT_RESOURCE) === $attrId);
check('pflichtige Ressource erkannt', $requires($resourceId, $attrId) === true);
check('nicht-pflichtige Ressource ignoriert', $requires($otherResourceId, $attrId) === false);

$t1 = $confirmedTypes($tokenIncomplete);
$t2 = $confirmedTypes($tokenComplete);
$gate = fn(array $t) => in_array('pickup', $t, true) && in_array('return', $t, true);
check('nur Abholung -> Gate BLOCKT', $gate($t1) === false);
check('Abholung+Rückgabe -> Gate ERLAUBT', $gate($t2) === true);

// --- Cleanup ---
$pdo->prepare('DELETE FROM custom_attribute_values WHERE custom_attribute_id = ?')->execute([$attrId]);
$pdo->prepare('DELETE FROM custom_attributes WHERE custom_attribute_id = ?')->execute([$attrId]);
$pdo->prepare('DELETE FROM zhl_booking_handover WHERE handover_token IN (?, ?)')->execute([$tokenIncomplete, $tokenComplete]);

echo "\n$pass PASS / $fail FAIL\n";
exit($fail === 0 ? 0 : 1);

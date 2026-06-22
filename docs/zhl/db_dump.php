<?php
// Read-only mysqldump-equivalent for the ZHL LibreBooking MariaDB.
// Runs INSIDE the container (piped via ssh stdin); writes SQL to stdout only.
// Does NOT write anything to the server filesystem or the database.

require_once '/var/www/private/config/db.php';
$pdo = get_db_connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function out(string $s): void { fwrite(STDOUT, $s); }

$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
out("-- ZHL LibreBooking dump (read-only, via PHP)\n");
out("-- database: {$dbName}\n");
out("-- server: " . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n");
out("SET NAMES utf8mb4;\n");
out("SET FOREIGN_KEY_CHECKS=0;\n");
out("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

// Separate base tables from views
$rows = $pdo->query("SHOW FULL TABLES")->fetchAll(PDO::FETCH_NUM);
$tables = [];
$views  = [];
foreach ($rows as $r) {
    if (($r[1] ?? '') === 'VIEW') { $views[] = $r[0]; }
    else { $tables[] = $r[0]; }
}

// Base tables: structure + data
foreach ($tables as $t) {
    $qt = "`" . str_replace("`", "``", $t) . "`";
    out("\n--\n-- Table: {$t}\n--\n");
    out("DROP TABLE IF EXISTS {$qt};\n");
    $create = $pdo->query("SHOW CREATE TABLE {$qt}")->fetch(PDO::FETCH_NUM);
    out($create[1] . ";\n\n");

    // Stream rows unbuffered to keep memory flat
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $stmt = $pdo->query("SELECT * FROM {$qt}");
    $count = 0;
    $batch = [];
    $flush = function() use (&$batch, &$count, $qt) {
        if (!$batch) return;
        out("INSERT INTO {$qt} VALUES\n" . implode(",\n", $batch) . ";\n");
        $batch = [];
    };
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $vals = [];
        foreach ($row as $v) {
            if ($v === null) { $vals[] = 'NULL'; }
            else { $vals[] = $GLOBALS['pdo']->quote((string)$v); }
        }
        $batch[] = '(' . implode(',', $vals) . ')';
        $count++;
        if (count($batch) >= 200) { $flush(); }
    }
    $flush();
    $stmt->closeCursor();
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    out("-- rows: {$count}\n");
}

// Views last (after their underlying tables exist)
foreach ($views as $v) {
    $qv = "`" . str_replace("`", "``", $v) . "`";
    out("\n--\n-- View: {$v}\n--\n");
    out("DROP VIEW IF EXISTS {$qv};\n");
    $create = $pdo->query("SHOW CREATE VIEW {$qv}")->fetch(PDO::FETCH_NUM);
    // $create[1] is the CREATE VIEW statement (index 1 for views)
    out(($create[1] ?? $create[2]) . ";\n");
}

out("\nSET FOREIGN_KEY_CHECKS=1;\n");
out("-- done: " . count($tables) . " tables, " . count($views) . " views\n");

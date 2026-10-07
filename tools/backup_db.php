<?php
/**
 * MySQL dump for Hostinger cron or an admin from CLI:
 *   php tools/backup_db.php
 * Writes database/backups/zpgc_YYYYMMDD_HHMMSS.sql
 */
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/logic/session_config.php';
    require_once dirname(__DIR__) . '/logic/config.php';
    if (function_exists('zpgc_require_cli_or_admin')) {
        zpgc_require_cli_or_admin();
    } else {
        http_response_code(404);
        exit('Not found.');
    }
} else {
    require_once dirname(__DIR__) . '/logic/config.php';
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    fwrite(STDERR, "No database connection.\n");
    exit(1);
}

$outDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($outDir) && !@mkdir($outDir, 0750, true)) {
    fwrite(STDERR, "Cannot create {$outDir}\n");
    exit(1);
}

$file = $outDir . DIRECTORY_SEPARATOR . 'zpgc_' . date('Ymd_His') . '.sql';
$fh = fopen($file, 'wb');
if ($fh === false) {
    fwrite(STDERR, "Cannot write {$file}\n");
    exit(1);
}

$dbName = '';
$n = @$conn->query('SELECT DATABASE() AS d');
if ($n) {
    $dbName = (string) ($n->fetch_assoc()['d'] ?? '');
}

fwrite($fh, "-- ZPGC Services dump\n-- " . date('c') . "\n-- database: {$dbName}\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

$tables = [];
$tq = $conn->query('SHOW TABLES');
if ($tq) {
    while ($row = $tq->fetch_row()) {
        $tables[] = (string) $row[0];
    }
}

foreach ($tables as $table) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        continue;
    }
    $create = $conn->query('SHOW CREATE TABLE `' . $table . '`');
    $cRow = $create ? $create->fetch_assoc() : null;
    $ddl = (string) ($cRow['Create Table'] ?? '');
    fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n{$ddl};\n\n");

    $res = $conn->query('SELECT * FROM `' . $table . '`', MYSQLI_USE_RESULT);
    if (!$res) {
        continue;
    }
    while ($row = $res->fetch_assoc()) {
        $cols = [];
        $vals = [];
        foreach ($row as $col => $val) {
            $cols[] = '`' . str_replace('`', '``', (string) $col) . '`';
            if ($val === null) {
                $vals[] = 'NULL';
            } else {
                $vals[] = "'" . $conn->real_escape_string((string) $val) . "'";
            }
        }
        fwrite($fh, 'INSERT INTO `' . $table . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n");
    }
    $res->free();
    fwrite($fh, "\n");
}

fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
fclose($fh);

$msg = 'Wrote ' . $file . ' (' . filesize($file) . " bytes)\n";
if (PHP_SAPI === 'cli') {
    echo $msg;
} else {
    header('Content-Type: text/plain; charset=UTF-8');
    echo $msg;
}

$keep = glob($outDir . DIRECTORY_SEPARATOR . 'zpgc_*.sql') ?: [];
rsort($keep);
foreach (array_slice($keep, 14) as $old) {
    @unlink($old);
}

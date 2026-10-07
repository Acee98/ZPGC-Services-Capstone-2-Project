<?php
/**
 * Cheap liveness for Azure / Hostinger. Add ?db=1 to ping MySQL.
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$wantDb = isset($_GET['db']);
if (!$wantDb) {
    http_response_code(200);
    echo 'ok';
    exit();
}

require_once __DIR__ . '/logic/config.php';
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    http_response_code(503);
    echo 'db-fail';
    exit();
}
$ping = @$conn->query('SELECT 1');
if (!$ping) {
    http_response_code(503);
    echo 'db-fail';
    exit();
}
http_response_code(200);
echo 'ok-db';

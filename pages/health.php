<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$ok = true;
$db = null;
$t0 = microtime(true);
try {
    require_once __DIR__ . '/../logic/config.php';
    if (isset($conn) && $conn instanceof mysqli) {
        $ping = @$conn->query('SELECT 1');
        $db = $ping ? 'up' : 'down';
        $ok = (bool) $ping;
    } else {
        $db = 'missing';
        $ok = false;
    }
} catch (Throwable $e) {
    $ok = false;
    $db = 'error';
}
$ms = (int) round((microtime(true) - $t0) * 1000);
http_response_code($ok ? 200 : 503);
echo json_encode([
    'ok' => $ok,
    'db' => $db,
    'ms' => $ms,
    't' => time(),
], JSON_UNESCAPED_SLASHES);

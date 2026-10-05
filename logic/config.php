<?php
/**
 * Database connection.
 * Local XAMPP defaults: localhost / root / (empty) / zpgc_services_db
 * Azure / Hostinger: set DB_* environment variables (App Settings or panel).
 *
 * Idempotent: safe to require more than once; reuses one mysqli per request
 * (shared with MySQL session handler to avoid a second Azure SSL handshake).
 */
if (!function_exists('zpgc_runtime_ddl_allowed')) {
    /**
     * When false, PHP skips CREATE/ALTER bootstrap (schema must already match V1.6).
     * Default true for campus demos; set ZPGC_ALLOW_RUNTIME_DDL=0 after migrations.
     */
    function zpgc_runtime_ddl_allowed()
    {
        $raw = getenv('ZPGC_ALLOW_RUNTIME_DDL');
        if ($raw === false || trim((string) $raw) === '') {
            if (isset($_SERVER['ZPGC_ALLOW_RUNTIME_DDL']) && trim((string) $_SERVER['ZPGC_ALLOW_RUNTIME_DDL']) !== '') {
                $raw = $_SERVER['ZPGC_ALLOW_RUNTIME_DDL'];
            } else {
                return true;
            }
        }
        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    $GLOBALS['zpgc_mysqli'] = $conn;
    $GLOBALS['conn'] = $conn;
    return;
}
if (isset($GLOBALS['zpgc_mysqli']) && $GLOBALS['zpgc_mysqli'] instanceof mysqli) {
    $conn = $GLOBALS['zpgc_mysqli'];
    $GLOBALS['conn'] = $conn;
    return;
}

// Azure App Settings often appear in $_SERVER even when getenv() is empty.
$dbEnv = static function ($key, $default = '') {
    $g = getenv($key);
    if ($g !== false && trim((string) $g) !== '') {
        return trim((string) $g);
    }
    if (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') {
        return trim((string) $_SERVER[$key]);
    }
    if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
        return trim((string) $_ENV[$key]);
    }
    return $default;
};

$host = $dbEnv('DB_HOST', 'localhost');
$user = $dbEnv('DB_USER', 'root');
$passwordEnv = getenv('DB_PASSWORD');
$password = $dbEnv('DB_PASSWORD', ($passwordEnv !== false) ? (string) $passwordEnv : '');
$database = $dbEnv('DB_NAME', 'zpgc_services_db');
$port = (int) $dbEnv('DB_PORT', '3306');
$useSsl = in_array(strtolower($dbEnv('DB_SSL', '')), ['1', 'true', 'yes'], true);

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_init();
if ($conn === false) {
    die('Connection failed: could not initialize MySQL client.');
}

if ($useSsl) {
    // Required for Azure Database for MySQL Flexible Server (and most managed MySQL).
    mysqli_ssl_set($conn, null, null, null, null, null);
}

$flags = $useSsl ? MYSQLI_CLIENT_SSL : 0;
$ok = @$conn->real_connect($host, $user, $password, $database, $port, null, $flags);

if (!$ok || $conn->connect_error) {
    $hint = ($host === 'localhost' || $host === '127.0.0.1')
        ? 'Start MySQL in the XAMPP Control Panel, then reload this page.'
        : 'Check DB_HOST / DB_USER / DB_PASSWORD / DB_NAME / DB_SSL in App Settings.';
    die('Connection failed: ' . ($conn->connect_error ?: 'unknown error') . '. ' . $hint);
}

$conn->set_charset('utf8mb4');
$GLOBALS['zpgc_mysqli'] = $conn;
$GLOBALS['conn'] = $conn;

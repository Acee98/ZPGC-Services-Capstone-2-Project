<?php

if (!defined('ZPGC_SESSION_NAME')) {
    define('ZPGC_SESSION_NAME', 'ZPGCSESSID');
}
if (!defined('ZPGC_SESSION_LIFETIME')) {
    // Persist across browser tab discards / Azure recycles longer than a session cookie (0).
    define('ZPGC_SESSION_LIFETIME', 60 * 60 * 8);
}

require_once __DIR__ . '/session_db.php';

if (!function_exists('zpgc_request_is_https')) {
    function zpgc_request_is_https()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        // Azure App Service / reverse proxies terminate TLS and set this header.
        $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($fwd === 'https') {
            return true;
        }
        // Azure often exposes the public host even when the worker sees HTTP.
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (str_ends_with($host, '.azurewebsites.net')) {
            return true;
        }
        return false;
    }
}

if (!function_exists('zpgc_is_cloud_root_host')) {
    function zpgc_is_cloud_root_host()
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return false;
        }
        if (str_ends_with($host, '.azurewebsites.net')) {
            return true;
        }
        // Hostinger / custom domain: treat as site root unless path clearly has a subfolder.
        if (!empty($_SERVER['WEBSITE_SITE_NAME']) || !empty($_SERVER['WEBSITE_HOSTNAME'])) {
            return true;
        }
        return false;
    }
}

if (!function_exists('zpgc_detect_cookie_path')) {
    /**
     * Cookie path must match the folder under htdocs (any clone name).
     * Hardcoding /CP2_V1.6/ breaks login when teammates use another path
     * (e.g. /ZPGC/ZPGC-Services-Capstone-2-Project/) — session cookies
     * never send, so login looks like "click does nothing".
     * Azure / Hostinger at domain root always use "/".
     */
    function zpgc_detect_cookie_path()
    {
        if (zpgc_is_cloud_root_host()) {
            return '/';
        }
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $script = preg_replace('#/+#', '/', $script);
        if (preg_match('#^(.*?)/(?:logic|pages)(?:/|$)#', $script, $m)) {
            $base = $m[1];
            if ($base === '' || $base === '/') {
                return '/';
            }
            return rtrim($base, '/') . '/';
        }
        $parent = dirname(dirname($script));
        if ($parent === '/' || $parent === '.' || $parent === '\\') {
            return '/';
        }
        return rtrim(str_replace('\\', '/', $parent), '/') . '/';
    }
}

if (!defined('ZPGC_COOKIE_PATH')) {
    define('ZPGC_COOKIE_PATH', zpgc_detect_cookie_path());
}

if (!function_exists('zpgc_cookie_options')) {
    function zpgc_cookie_options($expires = null)
    {
        if ($expires === null) {
            $expires = time() + ZPGC_SESSION_LIFETIME;
        }
        return [
            'expires' => (int) $expires,
            'path' => ZPGC_COOKIE_PATH,
            'secure' => zpgc_request_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}

if (!function_exists('zpgc_web_base')) {
    /** App URL prefix: "" on Azure root, "/CP2_V1.6" on local XAMPP. */
    function zpgc_web_base()
    {
        $base = rtrim(ZPGC_COOKIE_PATH, '/');
        return $base === '' || $base === '/' ? '' : $base;
    }
}

if (!function_exists('zpgc_web_path')) {
    function zpgc_web_path($relative)
    {
        return zpgc_web_base() . '/' . ltrim((string) $relative, '/');
    }
}

if (!function_exists('zpgc_expire_cookie_paths')) {
    /** Drop stale session cookies left on wrong paths (local subfolder vs Azure "/"). */
    function zpgc_expire_cookie_paths($name, array $paths)
    {
        $secure = zpgc_request_is_https();
        $paths = array_unique(array_filter($paths));
        foreach ($paths as $path) {
            setcookie((string) $name, '', [
                'expires' => time() - 42000,
                'path' => (string) $path,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}

if (!function_exists('zpgc_write_session_cookie')) {
    function zpgc_write_session_cookie()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $id = session_id();
        if ($id === '') {
            return;
        }
        setcookie(ZPGC_SESSION_NAME, $id, zpgc_cookie_options());
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $https = zpgc_request_is_https();

    // Keep the session alive long enough for normal campus demos and refreshes.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_path', ZPGC_COOKIE_PATH);
    ini_set('session.cookie_secure', $https ? '1' : '0');
    ini_set('session.gc_maxlifetime', (string) ZPGC_SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', (string) ZPGC_SESSION_LIFETIME);

    session_name(ZPGC_SESSION_NAME);

    // One shared DB connection for the request (sessions + app queries).
    if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof mysqli)) {
        require_once __DIR__ . '/config.php';
    }

    // Prefer MySQL sessions on Azure so App Service recycles do not wipe logins.
    // Registration is best-effort; handler itself never fails open()/write().
    $usingDbSessions = false;
    try {
        $usingDbSessions = (bool) zpgc_register_db_sessions();
    } catch (Throwable $e) {
        $usingDbSessions = false;
    }

    // Ensure file sessions have a writable path if DB registration was skipped.
    if (!$usingDbSessions) {
        $tmp = sys_get_temp_dir();
        if (is_string($tmp) && $tmp !== '' && is_dir($tmp) && is_writable($tmp)) {
            ini_set('session.save_path', $tmp);
        }
    }

    // One-time cleanup of leftover PHPSESSID / wrong-path cookies.
    $stalePaths = ['/', ZPGC_COOKIE_PATH];
    if (ZPGC_COOKIE_PATH !== '/') {
        $stalePaths[] = '/CP2_V1.6/';
        $stalePaths[] = '/CP2_V1.5/';
    }
    if (!headers_sent()) {
        if (isset($_COOKIE['PHPSESSID'])) {
            zpgc_expire_cookie_paths('PHPSESSID', $stalePaths);
        }
        foreach ($stalePaths as $p) {
            if ($p !== ZPGC_COOKIE_PATH) {
                zpgc_expire_cookie_paths(ZPGC_SESSION_NAME, [$p]);
            }
        }
    }

    session_set_cookie_params([
        'lifetime' => ZPGC_SESSION_LIFETIME,
        'path' => ZPGC_COOKIE_PATH,
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $started = false;
    try {
        $started = @session_start();
    } catch (Throwable $e) {
        $started = false;
    }

    // Last-resort file sessions if the custom handler still blew up.
    if (!$started && session_status() !== PHP_SESSION_ACTIVE) {
        try {
            ini_set('session.save_handler', 'files');
            $tmp = sys_get_temp_dir();
            if (is_string($tmp) && $tmp !== '' && is_dir($tmp) && is_writable($tmp)) {
                ini_set('session.save_path', $tmp);
            }
            if (class_exists('SessionHandler')) {
                @session_set_save_handler(new SessionHandler(), true);
            }
            $started = @session_start();
        } catch (Throwable $e) {
            $started = false;
        }
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        // Refresh cookie only when missing or session id changed (avoid racing mailbox polls).
        if (
            !headers_sent()
            && (
                !isset($_COOKIE[ZPGC_SESSION_NAME])
                || (string) $_COOKIE[ZPGC_SESSION_NAME] !== (string) session_id()
            )
        ) {
            zpgc_write_session_cookie();
        }

        // Restore theme from cookie after a refresh before any page reads it.
        if (!isset($_SESSION['theme']) && isset($_COOKIE['zpgc_theme'])) {
            $_SESSION['theme'] = $_COOKIE['zpgc_theme'] === 'dark' ? 'dark' : 'light';
        }

        // Touch activity so idle tabs still map to a live session.
        $_SESSION['_last_seen'] = time();
    } elseif (!isset($_SESSION) || !is_array($_SESSION)) {
        // Prevent undefined $_SESSION notices from becoming 500s on Azure.
        $_SESSION = [];
    }
}

require_once __DIR__ . '/csrf.php';

if (!function_exists('zpgc_establish_login_session')) {
    function zpgc_establish_login_session(array $user)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['id'] = (int) ($user['id'] ?? 0);
        $_SESSION['first_name'] = (string) ($user['first_name'] ?? '');
        $_SESSION['last_name'] = (string) ($user['last_name'] ?? '');
        $_SESSION['email'] = (string) ($user['email'] ?? '');
        $_SESSION['role'] = (string) ($user['role'] ?? '');
        $_SESSION['_last_seen'] = time();
        if (!headers_sent()) {
            zpgc_write_session_cookie();
        }
    }
}

function zpgc_current_pages_return()
{
    $script = basename(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    if (!preg_match('/^[a-z0-9_\-]+\.php$/i', $script)) {
        return '';
    }
    $q = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($q !== '' && !preg_match('/^[a-zA-Z0-9_\-=&%.]*$/', $q)) {
        $q = '';
    }
    return $script . ($q !== '' ? '?' . $q : '');
}

function zpgc_store_login_return($pageWithQuery = null)
{
    $page = $pageWithQuery !== null ? (string) $pageWithQuery : zpgc_current_pages_return();
    $page = ltrim(str_replace('\\', '/', $page), '/');
    if (str_starts_with($page, 'pages/')) {
        $page = substr($page, 6);
    }
    if (!preg_match('/^[a-z0-9_\-]+\.php(?:\?[a-zA-Z0-9_\-=&%.]*)?$/i', $page)) {
        return;
    }
    $_SESSION['login_return'] = $page;
}

function zpgc_consume_login_return()
{
    $page = trim((string) ($_SESSION['login_return'] ?? ''));
    unset($_SESSION['login_return']);
    if (!preg_match('/^[a-z0-9_\-]+\.php(?:\?[a-zA-Z0-9_\-=&%.]*)?$/i', $page)) {
        return '';
    }
    return '../pages/' . $page;
}

function zpgc_role_home($role)
{
    $role = strtolower(trim((string) $role));
    if ($role === 'admin') {
        return '../pages/admin.php';
    }
    if ($role === 'techn') {
        return '../pages/techn.php';
    }
    return '../pages/user.php';
}

function require_login()
{
    if (!isset($_SESSION['email']) || trim((string) $_SESSION['email']) === '') {
        zpgc_store_login_return();
        header('Location: ../pages/login_signup.php');
        exit();
    }
}

function require_role($role)
{
    require_login();
    $current = strtolower(trim((string) ($_SESSION['role'] ?? '')));
    $need = strtolower(trim((string) $role));

    $authMail = __DIR__ . '/auth_mail.php';
    if (is_file($authMail)) {
        require_once $authMail;
    }
    if (function_exists('auth_mail_normalize_role')) {
        $current = auth_mail_normalize_role($current);
        $need = auth_mail_normalize_role($need);
        $_SESSION['role'] = $current;
    }
    if ($current !== $need) {
        // Already logged in as another role — send to that role home, not a login loop.
        header('Location: ' . zpgc_role_home($current !== '' ? $current : 'user'));
        exit();
    }

    // Technicians: re-check DB so a deactivated/pending tech cannot keep using an old session.
    if ($need === 'techn' && function_exists('auth_mail_assert_session_still_allowed')) {
        global $conn;
        if ($conn instanceof mysqli) {
            auth_mail_assert_session_still_allowed($conn);
        }
    }
}

function current_user_id($conn)
{
    if (isset($_SESSION['id']) && (int) $_SESSION['id'] > 0) {
        return (int) $_SESSION['id'];
    }
    if (!isset($_SESSION['email'])) {
        return 0;
    }
    $email = $_SESSION['email'];
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return 0;
    }
    $_SESSION['id'] = (int) $row['id'];
    return (int) $row['id'];
}

function current_ui_theme()
{
    $theme = $_SESSION['theme'] ?? ($_COOKIE['zpgc_theme'] ?? 'light');
    $theme = $theme === 'dark' ? 'dark' : 'light';
    $_SESSION['theme'] = $theme;
    return $theme;
}

function save_ui_theme($theme)
{
    $theme = $theme === 'dark' ? 'dark' : 'light';
    $_SESSION['theme'] = $theme;
    setcookie('zpgc_theme', $theme, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => ZPGC_COOKIE_PATH,
        'secure' => zpgc_request_is_https(),
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

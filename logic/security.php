<?php
/**
 * Shared security helpers: headers, rate limits, production hardening.
 * Loaded from session_config.php on every request that starts a session.
 */

require_once __DIR__ . '/rate_limit.php';

if (!function_exists('zpgc_app_log')) {
    function zpgc_app_log($message)
    {
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $line = date('c') . ' ' . str_replace(["\r", "\n"], ' ', (string) $message) . "\n";
        @file_put_contents($dir . DIRECTORY_SEPARATOR . 'app.log', $line, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('zpgc_security_headers')) {
    function zpgc_is_production_host()
    {
        if (!empty($_SERVER['WEBSITE_SITE_NAME']) || !empty($_SERVER['WEBSITE_HOSTNAME'])) {
            return true;
        }
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return $host !== '' && (
            str_ends_with($host, '.azurewebsites.net')
            || (!str_contains($host, 'localhost') && !str_contains($host, '127.0.0.1'))
        );
    }

    function zpgc_security_bootstrap()
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        // Never paint PHP warnings into pages during campus testing.
        if (zpgc_is_production_host()) {
            @ini_set('display_errors', '0');
            @ini_set('display_startup_errors', '0');
            error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
            zpgc_register_error_loggers();
        }

        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        if (zpgc_is_production_host()
            && function_exists('zpgc_request_is_https')
            && !zpgc_request_is_https()
        ) {
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
            if (!preg_match('#/health\.php(\?|$)#', $uri)) {
                $host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
                if ($host !== '') {
                    header('Location: https://' . $host . $uri, true, 301);
                    exit();
                }
            }
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        // Conservative CSP — allow same-origin assets + inline scripts already used by UI.
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "img-src 'self' data: blob:; "
            . "style-src 'self' 'unsafe-inline'; "
            . "script-src 'self' 'unsafe-inline'; "
            . "connect-src 'self'; "
            . "font-src 'self' data:; "
            . "frame-ancestors 'self'; "
            . "base-uri 'self'; "
            . "form-action 'self'"
        );
        if (function_exists('zpgc_request_is_https') && zpgc_request_is_https()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Authenticated HTML should not be cached by shared proxies.
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (preg_match('#/(pages|logic)/#', $uri) && !preg_match('#\.(css|js|png|jpe?g|gif|webp|ico|svg)(\?|$)#i', $uri)) {
            header('Cache-Control: no-store, no-cache, must-revalidate, private');
            header('Pragma: no-cache');
        }
    }

    /**
     * Session bucket rate limit. Returns true when allowed, false when blocked.
     */
    function zpgc_rate_limit($bucket, $maxAttempts, $windowSeconds)
    {
        $bucket = preg_replace('/[^a-z0-9_\-]/i', '', (string) $bucket);
        if ($bucket === '') {
            $bucket = 'default';
        }
        $maxAttempts = max(1, (int) $maxAttempts);
        $windowSeconds = max(30, (int) $windowSeconds);
        $key = '_rl_' . $bucket;
        $now = time();
        $hits = $_SESSION[$key] ?? [];
        if (!is_array($hits)) {
            $hits = [];
        }
        $hits = array_values(array_filter($hits, static function ($t) use ($now, $windowSeconds) {
            return is_int($t) && ($now - $t) < $windowSeconds;
        }));
        if (count($hits) >= $maxAttempts) {
            $_SESSION[$key] = $hits;
            return false;
        }
        if (function_exists('zpgc_rate_limit_ip') && !zpgc_rate_limit_ip($bucket, $maxAttempts, $windowSeconds)) {
            $_SESSION[$key] = $hits;
            return false;
        }
        $hits[] = $now;
        $_SESSION[$key] = $hits;
        return true;
    }

    /** Clear a rate-limit bucket (e.g. after successful login). */
    function zpgc_rate_limit_clear($bucket)
    {
        $bucket = preg_replace('/[^a-z0-9_\-]/i', '', (string) $bucket);
        unset($_SESSION['_rl_' . $bucket]);
        if (function_exists('zpgc_rate_limit_ip_clear')) {
            zpgc_rate_limit_ip_clear($bucket);
        }
    }

    function zpgc_register_error_loggers()
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;
        set_error_handler(static function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            zpgc_app_log("PHP {$severity} {$message} @ {$file}:{$line}");
            return false;
        });
        set_exception_handler(static function ($e) {
            zpgc_app_log('EX ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            if (!headers_sent()) {
                http_response_code(500);
            }
            $page = dirname(__DIR__) . '/pages/error_500.php';
            if (is_file($page)) {
                require $page;
            }
            exit();
        });
        register_shutdown_function(static function () {
            $err = error_get_last();
            if (!$err || !in_array((int) $err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            zpgc_app_log('FATAL ' . $err['message'] . ' @ ' . $err['file'] . ':' . $err['line']);
            if (!headers_sent() && function_exists('zpgc_is_production_host') && zpgc_is_production_host()) {
                http_response_code(500);
                $page = dirname(__DIR__) . '/pages/error_500.php';
                if (is_file($page)) {
                    require $page;
                }
            }
        });
    }

    /**
     * Block web access to CLI/debug scripts. Allow CLI and logged-in admins only.
     */
    function zpgc_require_cli_or_admin()
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE && function_exists('session_start')) {
            // Caller should already have session; fail closed.
        }
        $role = strtolower((string) ($_SESSION['role'] ?? ''));
        if ($role === 'admin') {
            return;
        }
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Not found.';
        exit();
    }
}

// Do not auto-run here — session_config calls zpgc_security_bootstrap() after HTTPS helpers exist.

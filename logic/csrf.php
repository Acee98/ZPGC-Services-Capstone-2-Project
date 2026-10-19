<?php

/**
 * Session CSRF tokens for state-changing POST requests.
 */

if (!function_exists('zpgc_csrf_token')) {
    function zpgc_csrf_token()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    function zpgc_csrf_field()
    {
        $token = htmlspecialchars(zpgc_csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_csrf" value="' . $token . '">';
    }

    function zpgc_csrf_token_from_request()
    {
        if (isset($_POST['_csrf'])) {
            return (string) $_POST['_csrf'];
        }
        return (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }

    /**
     * Reject forged POST/JSON writes. Safe no-op for GET.
     */
    function zpgc_csrf_require()
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }
        $expected = zpgc_csrf_token();
        $got = zpgc_csrf_token_from_request();
        if ($expected === '' || $got === '' || !hash_equals($expected, $got)) {
            http_response_code(403);
            $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
            $isJson = str_contains($accept, 'application/json')
                || str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
            if ($isJson) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => false, 'error' => 'Invalid or missing CSRF token.']);
            } else {
                header('Content-Type: text/plain; charset=UTF-8');
                echo 'Invalid request. Reload the page and try again.';
            }
            exit();
        }
    }

    function zpgc_wants_json()
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest';
    }

    function zpgc_json_send($ok, array $payload = [])
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store');
        }
        echo json_encode(array_merge(['ok' => (bool) $ok], $payload), JSON_UNESCAPED_UNICODE);
        exit();
    }

    function zpgc_finish($redirect, $ok = true, array $payload = [])
    {
        if (zpgc_wants_json()) {
            if (!isset($payload['redirect'])) {
                $payload['redirect'] = $redirect;
            }
            zpgc_json_send($ok, $payload);
        }
        header('Location: ' . $redirect);
        exit();
    }
}

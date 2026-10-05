<?php

if (!function_exists('mail_env_load')) {
    function mail_env_value($key)
    {
        $candidates = [];
        $g = getenv($key);
        if ($g !== false) {
            $candidates[] = $g;
        }
        if (isset($_SERVER[$key])) {
            $candidates[] = $_SERVER[$key];
        }
        if (isset($_ENV[$key])) {
            $candidates[] = $_ENV[$key];
        }
        foreach ($candidates as $raw) {
            $val = trim((string) $raw);
            if ($val !== '') {
                return $val;
            }
        }
        return null;
    }

    function mail_env_load()
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $path = __DIR__ . '/mail.env';
        $defaults = [
            'MAIL_HOST' => 'smtp.gmail.com',
            'MAIL_PORT' => '587',
            'MAIL_ENCRYPTION' => 'tls',
            'MAIL_USERNAME' => '',
            'MAIL_PASSWORD' => '',
            'MAIL_FROM' => '',
            'MAIL_FROM_NAME' => 'ZPGC Services',
            'MAIL_APP_URL' => 'http://localhost/CP2_V1.6',
        ];
        $cached = $defaults;
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                if (array_key_exists($key, $cached)) {
                    $cached[$key] = trim($value);
                }
            }
        }
        // Azure App Settings / Hostinger env vars override mail.env (preferred in production).
        // Must run even when mail.env is absent on the server.
        foreach (array_keys($defaults) as $key) {
            $env = mail_env_value($key);
            if ($env !== null) {
                $cached[$key] = $env;
            }
        }
        if ($cached['MAIL_FROM'] === '') {
            $cached['MAIL_FROM'] = $cached['MAIL_USERNAME'];
        }
        $cached['MAIL_PASSWORD'] = preg_replace('/\s+/', '', $cached['MAIL_PASSWORD']);
        return $cached;
    }

    function mail_public_base_url()
    {
        $cfg = mail_env_load();
        $configured = rtrim((string) ($cfg['MAIL_APP_URL'] ?? ''), '/');
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        // When the app itself is sending mail (login/signup on Azure), prefer the
        // live host so verification links never point at localhost or an old app.
        if (
            $host !== ''
            && !str_contains($host, 'localhost')
            && !str_contains($host, '127.0.0.1')
        ) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                || str_ends_with($host, '.azurewebsites.net');
            return ($https ? 'https' : 'http') . '://' . $host;
        }
        if ($configured !== '') {
            return $configured;
        }
        return 'http://localhost/CP2_V1.6';
    }

    function mail_app_url($path = '')
    {
        $base = rtrim(mail_public_base_url(), '/');
        $path = ltrim((string) $path, '/');
        return $path === '' ? $base : $base . '/' . $path;
    }

    function mail_ready()
    {
        $cfg = mail_env_load();
        return $cfg['MAIL_USERNAME'] !== ''
            && $cfg['MAIL_PASSWORD'] !== ''
            && !str_contains($cfg['MAIL_USERNAME'], 'your.real.gmail')
            && !str_contains($cfg['MAIL_PASSWORD'], 'xxxx');
    }
}

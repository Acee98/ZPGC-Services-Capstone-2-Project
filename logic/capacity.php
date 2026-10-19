<?php

if (!function_exists('capacity_ensure_table')) {
    function capacity_ensure_table(mysqli $conn)
    {
        if (function_exists('zpgc_runtime_ddl_allowed') && !zpgc_runtime_ddl_allowed()) {
            $tbl = $conn->query("SHOW TABLES LIKE 'capacity_checks'");
            return $tbl && $tbl->num_rows > 0;
        }
        $conn->query(
            "CREATE TABLE IF NOT EXISTS capacity_checks (
                id INT NOT NULL AUTO_INCREMENT,
                ran_at DATETIME NOT NULL,
                concurrent INT NOT NULL DEFAULT 0,
                rounds INT NOT NULL DEFAULT 0,
                total_n INT NOT NULL DEFAULT 0,
                ok_n INT NOT NULL DEFAULT 0,
                fail_n INT NOT NULL DEFAULT 0,
                p50_ms INT NOT NULL DEFAULT 0,
                p95_ms INT NOT NULL DEFAULT 0,
                note VARCHAR(255) NOT NULL DEFAULT '',
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $tbl = $conn->query("SHOW TABLES LIKE 'capacity_checks'");
        return $tbl && $tbl->num_rows > 0;
    }

    function capacity_health_url()
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scheme = $https ? 'https' : 'http';
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/pages/admin.php'));
        $base = rtrim(dirname(dirname($script)), '/');
        if ($base === '.' || $base === '\\') {
            $base = '';
        }
        return $scheme . '://' . $host . $base . '/pages/health.php';
    }

    function capacity_percentile(array $ms, $p)
    {
        if ($ms === []) {
            return 0;
        }
        sort($ms, SORT_NUMERIC);
        $idx = (int) floor(($p / 100) * (count($ms) - 1));
        return (int) $ms[$idx];
    }

    /**
     * Concurrent GETs against pages/health.php (no login). Safe for campus demos.
     */
    function capacity_run_check($url, $concurrent = 20, $rounds = 3)
    {
        $concurrent = max(2, min(40, (int) $concurrent));
        $rounds = max(1, min(5, (int) $rounds));
        $times = [];
        $ok = 0;
        $fail = 0;
        if (!function_exists('curl_multi_init')) {
            return [
                'ok' => false,
                'error' => 'cURL is not available on this PHP install.',
                'concurrent' => $concurrent,
                'rounds' => $rounds,
                'total_n' => 0,
                'ok_n' => 0,
                'fail_n' => 0,
                'p50_ms' => 0,
                'p95_ms' => 0,
                'note' => '',
            ];
        }
        for ($r = 0; $r < $rounds; $r++) {
            $mh = curl_multi_init();
            $handles = [];
            for ($i = 0; $i < $concurrent; $i++) {
                $ch = curl_init($url);
                $opts = [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 8,
                    CURLOPT_TIMEOUT => 12,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_USERAGENT => 'ZPGC-capacity-check',
                ];
                $ca = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'cacert.pem';
                if (is_file($ca)) {
                    $opts[CURLOPT_CAINFO] = $ca;
                } else {
                    $opts[CURLOPT_SSL_VERIFYPEER] = false;
                    $opts[CURLOPT_SSL_VERIFYHOST] = 0;
                }
                curl_setopt_array($ch, $opts);
                curl_multi_add_handle($mh, $ch);
                $handles[] = $ch;
            }
            $running = null;
            do {
                $status = curl_multi_exec($mh, $running);
                if ($running) {
                    curl_multi_select($mh, 1.0);
                }
            } while ($running && $status === CURLM_OK);
            foreach ($handles as $ch) {
                $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $ms = (int) round(((float) curl_getinfo($ch, CURLINFO_TOTAL_TIME)) * 1000);
                $times[] = $ms;
                $body = (string) curl_multi_getcontent($ch);
                $parsed = json_decode($body, true);
                $good = $code >= 200 && $code < 400 && is_array($parsed) && !empty($parsed['ok']);
                if ($good) {
                    $ok++;
                } else {
                    $fail++;
                }
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }
            curl_multi_close($mh);
        }
        $total = $ok + $fail;
        return [
            'ok' => $fail === 0 && $ok > 0,
            'error' => '',
            'concurrent' => $concurrent,
            'rounds' => $rounds,
            'total_n' => $total,
            'ok_n' => $ok,
            'fail_n' => $fail,
            'p50_ms' => capacity_percentile($times, 50),
            'p95_ms' => capacity_percentile($times, 95),
            'note' => $url,
        ];
    }

    function capacity_store(mysqli $conn, array $result)
    {
        if (!capacity_ensure_table($conn)) {
            return false;
        }
        $stmt = $conn->prepare(
            'INSERT INTO capacity_checks
             (ran_at, concurrent, rounds, total_n, ok_n, fail_n, p50_ms, p95_ms, note)
             VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return false;
        }
        $note = substr((string) ($result['note'] ?? ''), 0, 255);
        $stmt->bind_param(
            'iiiiiiis',
            $result['concurrent'],
            $result['rounds'],
            $result['total_n'],
            $result['ok_n'],
            $result['fail_n'],
            $result['p50_ms'],
            $result['p95_ms'],
            $note
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

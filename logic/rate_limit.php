<?php
/**
 * Login/OTP stay session-scoped; IP hits persist in MySQL so a new cookie
 * cannot reset the budget (Hostinger / Azure).
 */

if (!function_exists('zpgc_client_ip')) {
    function zpgc_client_ip()
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $azure = !empty($_SERVER['WEBSITE_SITE_NAME']) || !empty($_SERVER['WEBSITE_HOSTNAME']);
        if ($azure) {
            $xff = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
            if ($xff !== '') {
                $first = trim(explode(',', $xff)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }
        return $remote !== '' ? $remote : '0.0.0.0';
    }

    function zpgc_rate_db()
    {
        if (isset($GLOBALS['zpgc_mysqli']) && $GLOBALS['zpgc_mysqli'] instanceof mysqli) {
            return $GLOBALS['zpgc_mysqli'];
        }
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            return $GLOBALS['conn'];
        }
        return null;
    }

    function zpgc_rate_table_ready(mysqli $conn)
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $exists = @$conn->query("SHOW TABLES LIKE 'rate_limits'");
        if ($exists && $exists->num_rows > 0) {
            $ready = true;
            return true;
        }
        $allowDdl = !function_exists('zpgc_runtime_ddl_allowed') || zpgc_runtime_ddl_allowed();
        if (!$allowDdl) {
            $ready = false;
            return false;
        }
        $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            bucket VARCHAR(64) NOT NULL,
            client_key VARCHAR(64) NOT NULL,
            hit_at INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY idx_rl_lookup (bucket, client_key, hit_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $ready = (bool) @$conn->query($sql);
        return $ready;
    }

    function zpgc_rate_limit_ip($bucket, $maxAttempts, $windowSeconds)
    {
        $conn = zpgc_rate_db();
        if (!$conn instanceof mysqli || !zpgc_rate_table_ready($conn)) {
            return true;
        }
        $bucket = preg_replace('/[^a-z0-9_\-]/i', '', (string) $bucket);
        if ($bucket === '') {
            $bucket = 'default';
        }
        $maxAttempts = max(1, (int) $maxAttempts);
        $windowSeconds = max(30, (int) $windowSeconds);
        $key = substr(hash('sha256', zpgc_client_ip()), 0, 64);
        $since = time() - $windowSeconds;

        if (mt_rand(1, 20) === 1) {
            @$conn->query('DELETE FROM rate_limits WHERE hit_at < ' . (time() - 86400));
        }

        $countStmt = $conn->prepare(
            'SELECT COUNT(*) AS c FROM rate_limits WHERE bucket = ? AND client_key = ? AND hit_at > ?'
        );
        if (!$countStmt) {
            return true;
        }
        $countStmt->bind_param('ssi', $bucket, $key, $since);
        $countStmt->execute();
        $row = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();
        $used = (int) ($row['c'] ?? 0);
        if ($used >= $maxAttempts) {
            return false;
        }
        $now = time();
        $ins = $conn->prepare(
            'INSERT INTO rate_limits (bucket, client_key, hit_at) VALUES (?, ?, ?)'
        );
        if ($ins) {
            $ins->bind_param('ssi', $bucket, $key, $now);
            @$ins->execute();
            $ins->close();
        }
        return true;
    }

    function zpgc_rate_limit_ip_clear($bucket)
    {
        $conn = zpgc_rate_db();
        if (!$conn instanceof mysqli) {
            return;
        }
        $bucket = preg_replace('/[^a-z0-9_\-]/i', '', (string) $bucket);
        $key = substr(hash('sha256', zpgc_client_ip()), 0, 64);
        $stmt = @$conn->prepare('DELETE FROM rate_limits WHERE bucket = ? AND client_key = ?');
        if ($stmt) {
            $stmt->bind_param('ss', $bucket, $key);
            @$stmt->execute();
            $stmt->close();
        }
    }
}

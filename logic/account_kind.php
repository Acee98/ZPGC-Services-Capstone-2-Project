<?php

if (!function_exists('account_kind_normalize')) {
    function account_kind_normalize($kind, $role = 'user')
    {
        $role = strtolower(trim((string) $role));
        if ($role !== 'user') {
            return '';
        }
        $kind = strtolower(trim((string) $kind));
        return $kind === 'staff' ? 'staff' : 'student';
    }

    function account_kind_from_email($email, $role = 'user')
    {
        $role = strtolower(trim((string) $role));
        if ($role !== 'user') {
            return '';
        }
        $email = strtolower(trim((string) $email));
        if ($email !== '' && str_ends_with($email, '@tsu.edu.ph') && !str_contains($email, '@student.')) {
            return 'staff';
        }
        return 'student';
    }

    function account_kind_label($role, $kind = '')
    {
        $role = strtolower(trim((string) $role));
        if ($role === 'techn') {
            return 'Technician';
        }
        if ($role === 'admin') {
            return 'Administrator';
        }
        $kind = account_kind_normalize($kind, 'user');
        return $kind === 'staff' ? 'Staff' : 'Student';
    }

    function account_kind_has_column(mysqli $conn)
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        $res = $conn->query("SHOW COLUMNS FROM users LIKE 'account_kind'");
        $has = ($res && $res->num_rows > 0);
        return $has;
    }

    function account_kind_ensure(mysqli $conn)
    {
        static $done = false;
        if ($done) {
            return account_kind_has_column($conn);
        }
        $done = true;
        if (function_exists('zpgc_runtime_ddl_allowed') && zpgc_runtime_ddl_allowed()) {
            if (!account_kind_has_column($conn)) {
                $conn->query(
                    "ALTER TABLE users ADD COLUMN account_kind VARCHAR(16) NOT NULL DEFAULT 'student'"
                );
            }
            $seen = $conn->query("SHOW COLUMNS FROM users LIKE 'last_seen'");
            if (!$seen || $seen->num_rows === 0) {
                $conn->query('ALTER TABLE users ADD COLUMN last_seen DATETIME NULL DEFAULT NULL');
            }
            if (account_kind_has_column($conn)) {
                $conn->query(
                    "UPDATE users SET account_kind = 'staff'
                     WHERE role = 'user'
                       AND LOWER(email) LIKE '%@tsu.edu.ph'
                       AND LOWER(email) NOT LIKE '%@student.%'
                       AND (account_kind = '' OR account_kind = 'student')"
                );
                $conn->query(
                    "UPDATE users SET account_kind = 'student'
                     WHERE role = 'user' AND (account_kind IS NULL OR account_kind = '')"
                );
                $conn->query(
                    "UPDATE users SET account_kind = ''
                     WHERE role IN ('techn','admin') AND account_kind <> ''"
                );
            }
        }
        return account_kind_has_column($conn);
    }

    function account_touch_last_seen(mysqli $conn, $userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return;
        }
        $stamp = (int) ($_SESSION['_last_seen'] ?? 0);
        if ($stamp > 0 && (time() - $stamp) < 60) {
            return;
        }
        $_SESSION['_last_seen'] = time();
        $seen = $conn->query("SHOW COLUMNS FROM users LIKE 'last_seen'");
        if (!$seen || $seen->num_rows === 0) {
            return;
        }
        $stmt = $conn->prepare('UPDATE users SET last_seen = NOW() WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    function account_kind_set(mysqli $conn, $userId, $kind, $role = 'user')
    {
        if (!account_kind_ensure($conn)) {
            return;
        }
        $userId = (int) $userId;
        $kind = account_kind_normalize($kind, $role);
        $stmt = $conn->prepare('UPDATE users SET account_kind = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('si', $kind, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    function account_active_sessions(mysqli $conn, $minutes = 15)
    {
        $minutes = max(1, min(120, (int) $minutes));
        $seen = $conn->query("SHOW COLUMNS FROM users LIKE 'last_seen'");
        if (!$seen || $seen->num_rows === 0) {
            return 0;
        }
        $res = $conn->query(
            "SELECT COUNT(*) AS n FROM users
             WHERE last_seen IS NOT NULL
               AND last_seen >= DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)"
        );
        $row = $res ? $res->fetch_assoc() : null;
        return (int) ($row['n'] ?? 0);
    }
}

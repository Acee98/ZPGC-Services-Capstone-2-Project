<?php
if (!function_exists('current_profile_user')) {
    function current_profile_user(mysqli $conn)
    {
        static $row = null;
        if ($row !== null) {
            return $row;
        }
        $id = current_user_id($conn);
        if ($id <= 0) {
            $row = false;
            return $row;
        }
        if (function_exists('zpgc_runtime_ddl_allowed') && zpgc_runtime_ddl_allowed()) {
            foreach (['phone_verified', 'sms_notify', 'phone'] as $dropCol) {
                $col = $conn->query("SHOW COLUMNS FROM users LIKE '" . $conn->real_escape_string($dropCol) . "'");
                if ($col && $col->num_rows > 0) {
                    $conn->query('ALTER TABLE users DROP COLUMN `' . $dropCol . '`');
                }
            }
        }
        if (is_file(__DIR__ . '/account_kind.php')) {
            require_once __DIR__ . '/account_kind.php';
            account_kind_ensure($conn);
        }
        $stmt = $conn->prepare(
            'SELECT id, first_name, last_name, email, role, email_notify, preferred_language, account_kind
             FROM users WHERE id = ? LIMIT 1'
        );
        if ($stmt === false) {
            $stmt = $conn->prepare(
                'SELECT id, first_name, last_name, email, role FROM users WHERE id = ? LIMIT 1'
            );
        }
        if ($stmt === false) {
            $row = false;
            return $row;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$found) {
            $row = false;
            return $row;
        }
        $role = strtolower(trim((string) ($found['role'] ?? '')));
        $kind = (string) ($found['account_kind'] ?? '');
        if (function_exists('account_kind_label')) {
            $found['account_label'] = account_kind_label($role, $kind);
        } else {
            $labels = [
                'user' => 'User',
                'techn' => 'Technician',
                'admin' => 'Administrator',
            ];
            $found['account_label'] = $labels[$role] ?? ucfirst($role !== '' ? $role : 'User');
        }
        $found['specialty_label'] = '';
        if ($role === 'techn') {
            $specStmt = $conn->prepare(
                'SELECT specialty FROM technician_applications WHERE user_id = ? LIMIT 1'
            );
            if ($specStmt) {
                $specStmt->bind_param('i', $id);
                $specStmt->execute();
                $specRow = $specStmt->get_result()->fetch_assoc();
                $specStmt->close();
                $spec = trim((string) ($specRow['specialty'] ?? ''));
                if ($spec !== '') {
                    $found['specialty_label'] = $spec;
                }
            }
        }
        $row = $found;
        return $row;
    }
}

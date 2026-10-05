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
        $stmt = $conn->prepare(
            'SELECT id, first_name, last_name, email, role, phone, email_notify, sms_notify, preferred_language
             FROM users WHERE id = ? LIMIT 1'
        );
        if ($stmt === false) {
            $row = false;
            return $row;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $found = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $row = $found ?: false;
        return $row;
    }
}

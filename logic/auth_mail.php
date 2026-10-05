<?php

require_once __DIR__ . '/mail_smtp.php';

if (!function_exists('auth_mail_ready')) {
    function auth_mail_ready(mysqli $conn)
    {
        if (function_exists('zpgc_runtime_ddl_allowed') && !zpgc_runtime_ddl_allowed()) {
            return;
        }
        $col = $conn->query("SHOW COLUMNS FROM users LIKE 'email_verified'");
        if (!$col || $col->num_rows === 0) {
            $conn->query(
                'ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER status'
            );
            // Existing active accounts keep working without a new verify click.
            $conn->query("UPDATE users SET email_verified = 1 WHERE status = 'active'");
        }
        $conn->query(
            "CREATE TABLE IF NOT EXISTS auth_tokens (
                id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                purpose VARCHAR(32) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_auth_token_hash (token_hash),
                KEY idx_auth_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    function auth_mail_is_deliverable_email($email)
    {
        $email = strtolower(trim((string) $email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $domain = substr(strrchr($email, '@'), 1);
        if ($domain === false || $domain === '') {
            return false;
        }
        // Block obvious local-only placeholders used in early demos.
        $blocked = ['example.com', 'example.org', 'test.local', 'localhost'];
        if (in_array($domain, $blocked, true)) {
            return false;
        }
        return true;
    }

    function auth_mail_create_token(mysqli $conn, $userId, $purpose, $hoursValid = 24)
    {
        auth_mail_ready($conn);
        $purpose = (string) $purpose;
        $userId = (int) $userId;
        $hoursValid = max(1, (int) $hoursValid);
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $clear = $conn->prepare(
            'UPDATE auth_tokens SET used_at = NOW()
             WHERE user_id = ? AND purpose = ? AND used_at IS NULL'
        );
        $clear->bind_param('is', $userId, $purpose);
        $clear->execute();
        $clear->close();
        // Expiry uses MySQL NOW() so PHP and MySQL clock/timezone cannot disagree.
        $ins = $conn->prepare(
            'INSERT INTO auth_tokens (user_id, purpose, token_hash, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . $hoursValid . ' HOUR))'
        );
        $ins->bind_param('iss', $userId, $purpose, $hash);
        $ins->execute();
        $ins->close();
        return $raw;
    }

    function auth_mail_find_token(mysqli $conn, $purpose, $rawToken)
    {
        auth_mail_ready($conn);
        $rawToken = trim((string) $rawToken);
        // Strip accidental wrapping from mail clients.
        $rawToken = preg_replace('/[^a-f0-9]/i', '', $rawToken);
        if ($rawToken === '' || strlen($rawToken) < 32) {
            return null;
        }
        $hash = hash('sha256', strtolower($rawToken));
        $purpose = (string) $purpose;
        $stmt = $conn->prepare(
            'SELECT id, user_id FROM auth_tokens
             WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->bind_param('ss', $hash, $purpose);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
        ];
    }

    function auth_mail_consume_token(mysqli $conn, $purpose, $rawToken)
    {
        $found = auth_mail_find_token($conn, $purpose, $rawToken);
        if (!$found) {
            return null;
        }
        $id = (int) $found['id'];
        $upd = $conn->prepare('UPDATE auth_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
        $upd->bind_param('i', $id);
        $upd->execute();
        $ok = $upd->affected_rows > 0;
        $upd->close();
        return $ok ? (int) $found['user_id'] : null;
    }

    function auth_mail_link_bodies($name, $intro, $link, $hours)
    {
        $safeName = trim((string) $name);
        $safeLink = (string) $link;
        $plain = "Hello {$safeName},\n\n"
            . "{$intro}\n\n"
            . "<{$safeLink}>\n\n"
            . "If the link is broken, copy and paste the full URL into your browser.\n"
            . "This link expires in {$hours} hours. If you did not request this, ignore this message.\n\n"
            . "ZPGC Services";
        $html = '<p>Hello ' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><a href="' . htmlspecialchars($safeLink, ENT_QUOTES, 'UTF-8') . '">Open ZPGC verification link</a></p>'
            . '<p style="word-break:break-all;font-size:12px;">'
            . htmlspecialchars($safeLink, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>This link expires in ' . (int) $hours
            . ' hours. If you did not request this, ignore this message.</p>'
            . '<p>ZPGC Services</p>';
        return [$plain, $html];
    }

    function auth_mail_send_verify(mysqli $conn, $userId, $email, $firstName)
    {
        $token = auth_mail_create_token($conn, $userId, 'verify_email', 48);
        $link = mail_app_url('pages/verify_email.php?token=' . rawurlencode($token));
        [$plain, $html] = auth_mail_link_bodies(
            $firstName,
            'Confirm this email for your ZPGC Services account:',
            $link,
            48
        );
        return mail_send($email, 'Verify your ZPGC Services email', $plain, $html);
    }

    function auth_mail_send_reset(mysqli $conn, $userId, $email, $firstName)
    {
        $token = auth_mail_create_token($conn, $userId, 'reset_password', 24);
        $link = mail_app_url('pages/reset_password.php?token=' . rawurlencode($token));
        [$plain, $html] = auth_mail_link_bodies(
            $firstName,
            'Reset your ZPGC Services password using this button or URL:',
            $link,
            24
        );
        return mail_send($email, 'Reset your ZPGC Services password', $plain, $html);
    }

    function notify_user_email(mysqli $conn, $userId, $subject, $body)
    {
        auth_mail_ready($conn);
        $userId = (int) $userId;
        if ($userId <= 0 || !mail_ready()) {
            return ['ok' => false, 'error' => 'skipped'];
        }
        $stmt = $conn->prepare(
            'SELECT email, first_name, role, email_notify, email_verified, status
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$user) {
            return ['ok' => false, 'error' => 'no user'];
        }
        if ((int) ($user['email_notify'] ?? 1) !== 1) {
            return ['ok' => false, 'error' => 'notify off'];
        }
        if ((int) ($user['email_verified'] ?? 0) !== 1 && ($user['role'] ?? '') !== 'admin') {
            // Still allow active legacy accounts without the column filled.
            $legacy = ($user['status'] ?? '') === 'active';
            if (!$legacy) {
                return ['ok' => false, 'error' => 'unverified'];
            }
        }
        $greeting = trim((string) ($user['first_name'] ?? ''));
        $text = ($greeting !== '' ? "Hello {$greeting},\n\n" : '') . $body . "\n\nZPGC Services";
        return mail_send($user['email'], $subject, $text);
    }
}

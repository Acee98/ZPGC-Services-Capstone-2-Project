<?php

require_once __DIR__ . '/mail_smtp.php';

if (!function_exists('auth_mail_ready')) {
    function auth_mail_ready(mysqli $conn)
    {
        if (!empty($_SESSION['_zpgc_auth_mail_ready'])) {
            return;
        }
        if (function_exists('zpgc_runtime_ddl_allowed') && !zpgc_runtime_ddl_allowed()) {
            $_SESSION['_zpgc_auth_mail_ready'] = 1;
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
        $_SESSION['_zpgc_auth_mail_ready'] = 1;
    }

    /**
     * Signup / password reset: TSU Outlook only (no personal Gmail, etc.).
     */
    function auth_mail_is_tsu_email($email)
    {
        $email = strtolower(trim((string) $email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (str_ends_with($email, '@student.tsu.edu.ph')) {
            return true;
        }
        if (str_ends_with($email, '@tsu.edu.ph')) {
            return true;
        }
        return false;
    }

    /**
     * Reject made-up / placeholder local-parts that still match @student.tsu.edu.ph.
     * SMTP often accepts these and the UI falsely says "code sent".
     */
    function auth_mail_is_plausible_tsu_mailbox($email)
    {
        $email = strtolower(trim((string) $email));
        if (!auth_mail_is_tsu_email($email)) {
            return false;
        }
        $local = explode('@', $email, 2)[0] ?? '';
        if ($local === '' || strlen($local) < 5 || strlen($local) > 64) {
            return false;
        }
        // Must be normal mailbox characters only.
        if (!preg_match('/^[a-z0-9][a-z0-9._+-]*[a-z0-9]$/', $local) && !preg_match('/^[a-z0-9]{5,64}$/', $local)) {
            return false;
        }
        if (str_contains($local, '..') || str_starts_with($local, '.') || str_ends_with($local, '.')) {
            return false;
        }

        $blockedExact = [
            'test', 'testing', 'tester', 'fake', 'asdf', 'asdfgh', 'qwerty', 'demo', 'sample',
            'example', 'nowhere', 'junk', 'temp', 'temporary', 'admin', 'administrator',
            'root', 'noreply', 'no-reply', 'null', 'void', 'guest', 'user', 'abcde', 'abcdef',
            'xxxxxx', 'student00000', 'student12345', 'firstname.lastname', 'name',
            'cb00000', 'cb00008', 'aa00000', 'test00000', 'fake00000',
        ];
        if (in_array($local, $blockedExact, true)) {
            return false;
        }

        $blockedPrefixes = [
            'fake', 'test', 'asdf', 'qwerty', 'demo', 'sample', 'example', 'temp', 'junk',
            'nowhere', 'xxx', 'aaa', 'abcabc',
        ];
        foreach ($blockedPrefixes as $prefix) {
            if (str_starts_with($local, $prefix)) {
                return false;
            }
        }

        // All identical characters / obvious keyboard spam.
        if (preg_match('/^(.)\1{4,}$/', $local)) {
            return false;
        }
        // Mostly zeros (e.g. xx00000 / cb00008-style invented IDs with zero padding).
        $digits = preg_replace('/\D+/', '', $local);
        if ($digits !== '' && strlen($digits) >= 4 && preg_match('/^0+$/', $digits)) {
            return false;
        }
        if ($digits !== '' && strlen($digits) >= 5 && substr_count($digits, '0') >= (strlen($digits) - 1)) {
            return false;
        }

        return true;
    }

    /** @deprecated Use auth_mail_is_tsu_email */
    function auth_mail_is_deliverable_email($email)
    {
        return auth_mail_is_tsu_email($email);
    }

    function auth_mail_tsu_email_hint()
    {
        return 'Use your real TSU Outlook email only (the inbox you can open): '
            . 'yourname@student.tsu.edu.ph or name@tsu.edu.ph. '
            . 'Made-up or Gmail addresses are blocked — you will never receive a verification code.';
    }

    function auth_mail_delete_user(mysqli $conn, $userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return;
        }
        $tok = $conn->prepare('DELETE FROM auth_tokens WHERE user_id = ?');
        if ($tok) {
            $tok->bind_param('i', $userId);
            $tok->execute();
            $tok->close();
        }
        $del = $conn->prepare(
            "DELETE FROM users WHERE id = ? AND email_verified = 0 AND status = 'inactive' AND role IN ('user','techn')"
        );
        if ($del) {
            $del->bind_param('i', $userId);
            $del->execute();
            $del->close();
        }
    }

    /**
     * Remove abandoned unverified signups (fake emails that never got a code).
     */
    function auth_mail_purge_stale_unverified(mysqli $conn)
    {
        // Simple sweep: inactive + unverified tech/user with no unused unexpired verify token.
        $ids = [];
        $res = $conn->query(
            "SELECT u.id
             FROM users u
             WHERE u.email_verified = 0
               AND u.status = 'inactive'
               AND u.role IN ('user','techn')
               AND NOT EXISTS (
                    SELECT 1 FROM auth_tokens t
                    WHERE t.user_id = u.id
                      AND t.purpose = 'verify_email'
                      AND t.used_at IS NULL
                      AND t.expires_at > NOW()
               )
             ORDER BY u.id ASC
             LIMIT 40"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int) $row['id'];
            }
        }
        foreach ($ids as $id) {
            auth_mail_delete_user($conn, $id);
        }
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

    function auth_mail_create_verify_code(mysqli $conn, $userId, $hoursValid = 48)
    {
        auth_mail_ready($conn);
        $purpose = 'verify_email';
        $userId = (int) $userId;
        $hoursValid = max(1, (int) $hoursValid);
        $code = (string) random_int(100000, 999999);
        $hash = hash('sha256', $code);
        $clear = $conn->prepare(
            'UPDATE auth_tokens SET used_at = NOW()
             WHERE user_id = ? AND purpose = ? AND used_at IS NULL'
        );
        $clear->bind_param('is', $userId, $purpose);
        $clear->execute();
        $clear->close();
        $ins = $conn->prepare(
            'INSERT INTO auth_tokens (user_id, purpose, token_hash, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . $hoursValid . ' HOUR))'
        );
        $ins->bind_param('iss', $userId, $purpose, $hash);
        $ins->execute();
        $ins->close();
        return $code;
    }

    function auth_mail_consume_verify_code(mysqli $conn, $email, $rawCode)
    {
        auth_mail_ready($conn);
        $email = strtolower(trim((string) $email));
        $code = preg_replace('/\D+/', '', (string) $rawCode);
        if ($email === '' || strlen($code) < 6) {
            return null;
        }
        $hash = hash('sha256', $code);
        $purpose = 'verify_email';
        $stmt = $conn->prepare(
            'SELECT t.id AS token_id, u.id AS user_id
             FROM auth_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE LOWER(u.email) = ? AND t.purpose = ? AND t.token_hash = ?
               AND t.used_at IS NULL AND t.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->bind_param('sss', $email, $purpose, $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        $tokenId = (int) $row['token_id'];
        $userId = (int) $row['user_id'];
        $upd = $conn->prepare('UPDATE auth_tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
        $upd->bind_param('i', $tokenId);
        $upd->execute();
        $ok = $upd->affected_rows > 0;
        $upd->close();
        return $ok ? $userId : null;
    }

    /**
     * Technician roles that must stay inactive until an admin Activates them.
     */
    function auth_mail_is_technician_role($role)
    {
        $role = strtolower(trim((string) $role));
        return in_array($role, ['techn', 'technician', 'tech'], true);
    }

    /**
     * Normalize signup/DB role values used by the app.
     */
    function auth_mail_normalize_role($role)
    {
        $role = strtolower(trim((string) $role));
        if (auth_mail_is_technician_role($role)) {
            return 'techn';
        }
        if ($role === 'admin' || $role === 'administrator') {
            return 'admin';
        }
        return 'user';
    }

    /**
     * Decide whether this account may establish a login session.
     *
     * Rules (foolproof):
     * - user: email verified (or legacy active) AND status=active → OK after 6-digit verify
     * - techn: email_verified=1 AND status=active → only after admin Activate
     * - admin: status=active (email_verified optional / legacy)
     *
     * @return array{ok:bool,need_verify:bool,awaiting_admin:bool,message:string}
     */
    function auth_mail_login_gate(array $user)
    {
        $role = auth_mail_normalize_role($user['role'] ?? 'user');
        $status = strtolower(trim((string) ($user['status'] ?? '')));
        $verified = (int) ($user['email_verified'] ?? 0) === 1;

        if ($role === 'admin') {
            if ($status !== 'active') {
                return [
                    'ok' => false,
                    'need_verify' => false,
                    'awaiting_admin' => false,
                    'message' => 'This administrator account is not active.',
                ];
            }
            return ['ok' => true, 'need_verify' => false, 'awaiting_admin' => false, 'message' => ''];
        }

        if ($role === 'techn') {
            // Never treat technicians as "legacy verified" from status alone.
            if (!$verified) {
                return [
                    'ok' => false,
                    'need_verify' => true,
                    'awaiting_admin' => false,
                    'message' => 'Verify your email with the 6-digit code first. After that, an administrator must Activate your technician account.',
                ];
            }
            if ($status !== 'active') {
                return [
                    'ok' => false,
                    'need_verify' => false,
                    'awaiting_admin' => true,
                    'message' => 'Your email is verified. An administrator must Activate your technician account in Utilities before you can log in.',
                ];
            }
            return ['ok' => true, 'need_verify' => false, 'awaiting_admin' => false, 'message' => ''];
        }

        // Regular users: auto-activate after verify; legacy active accounts still work.
        if (!$verified && $status === 'active') {
            $verified = true;
        }
        if (!$verified) {
            return [
                'ok' => false,
                'need_verify' => true,
                'awaiting_admin' => false,
                'message' => 'Your email is not verified yet. Enter the 6-digit code we emailed you.',
            ];
        }
        if ($status !== 'active') {
            return [
                'ok' => false,
                'need_verify' => false,
                'awaiting_admin' => false,
                'message' => 'Your account is not active. Complete email verification or contact an administrator.',
            ];
        }
        return ['ok' => true, 'need_verify' => false, 'awaiting_admin' => false, 'message' => ''];
    }

    /**
     * After a valid verify code/link:
     * - user: email_verified=1 and status=active (auto-activate — no admin needed)
     * - techn: email_verified=1 and status forced inactive until admin Activates
     *
     * @return array{ok:bool,role:string,awaiting_admin:bool}
     */
    function auth_mail_activate_verified_user(mysqli $conn, $userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return ['ok' => false, 'role' => '', 'awaiting_admin' => false];
        }
        $stmt = $conn->prepare('SELECT role, status FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return ['ok' => false, 'role' => '', 'awaiting_admin' => false];
        }
        $role = auth_mail_normalize_role($row['role'] ?? 'user');

        // Keep DB role canonical if an alias was stored.
        if (auth_mail_is_technician_role($row['role'] ?? '') && ($row['role'] ?? '') !== 'techn') {
            $fix = $conn->prepare("UPDATE users SET role = 'techn' WHERE id = ?");
            $fix->bind_param('i', $userId);
            $fix->execute();
            $fix->close();
        }

        if ($role === 'techn') {
            // Explicitly force inactive — never leave a techn active after self-verify.
            $upd = $conn->prepare(
                "UPDATE users SET email_verified = 1, status = 'inactive', role = 'techn' WHERE id = ?"
            );
            $upd->bind_param('i', $userId);
            $upd->execute();
            $upd->close();
            return ['ok' => true, 'role' => 'techn', 'awaiting_admin' => true];
        }

        if ($role === 'admin') {
            // Admins are not created via public signup; still mark verified if they verify.
            $upd = $conn->prepare(
                "UPDATE users SET email_verified = 1 WHERE id = ?"
            );
            $upd->bind_param('i', $userId);
            $upd->execute();
            $upd->close();
            return ['ok' => true, 'role' => 'admin', 'awaiting_admin' => false];
        }

        // Regular user: auto-activate after 6-digit verification.
        $upd = $conn->prepare(
            "UPDATE users SET email_verified = 1, status = 'active', role = 'user' WHERE id = ?"
        );
        $upd->bind_param('i', $userId);
        $upd->execute();
        $upd->close();
        return ['ok' => true, 'role' => 'user', 'awaiting_admin' => false];
    }

    /**
     * Live DB check used by technician pages — kills sessions if deactivated.
     */
    function auth_mail_assert_session_still_allowed(mysqli $conn)
    {
        $id = (int) ($_SESSION['id'] ?? 0);
        if ($id <= 0) {
            return;
        }
        $stmt = $conn->prepare('SELECT role, status, email_verified FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$user) {
            $_SESSION = [];
            header('Location: ../pages/login_signup.php');
            exit();
        }
        $gate = auth_mail_login_gate($user);
        if (!$gate['ok']) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            $_SESSION['login_error'] = $gate['message'] !== ''
                ? $gate['message']
                : 'Your account is no longer allowed to sign in.';
            header('Location: ../pages/login_signup.php');
            exit();
        }
        $_SESSION['role'] = auth_mail_normalize_role($user['role'] ?? ($_SESSION['role'] ?? 'user'));
    }


    function auth_mail_code_bodies($name, $intro, $code, $hours)
    {
        $safeName = trim((string) $name);
        $safeCode = preg_replace('/\D+/', '', (string) $code);
        $plain = "Hello {$safeName},\n\n"
            . "{$intro}\n\n"
            . "Verification code: {$safeCode}\n\n"
            . "Copy and paste this code on the ZPGC verify page. It expires in {$hours} hours.\n"
            . "If you did not sign up, ignore this message.\n\n"
            . "ZPGC Services";
        $html = '<p>Hello ' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p style="font-size:28px;font-weight:700;letter-spacing:0.2em;margin:16px 0;">'
            . htmlspecialchars($safeCode, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>Copy and paste this code on the ZPGC verify page. It expires in ' . (int) $hours
            . ' hours. If you did not sign up, ignore this message.</p>'
            . '<p>ZPGC Services</p>';
        return [$plain, $html];
    }

    function auth_mail_send_verify(mysqli $conn, $userId, $email, $firstName)
    {
        $code = auth_mail_create_verify_code($conn, $userId, 48);
        [$plain, $html] = auth_mail_code_bodies(
            $firstName,
            'Enter this code to verify your ZPGC Services account:',
            $code,
            48
        );
        return mail_send($email, 'Your ZPGC Services verification code', $plain, $html);
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

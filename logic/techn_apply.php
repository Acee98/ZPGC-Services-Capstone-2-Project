<?php

require_once __DIR__ . '/ticket_files.php';
require_once __DIR__ . '/auth_mail.php';

if (!function_exists('techn_apply_specialties')) {
    function techn_apply_specialties()
    {
        return ['Hardware', 'Software', 'Network', 'Account', 'Other'];
    }

    function techn_apply_normalize_specialty($raw)
    {
        $want = strtolower(trim((string) $raw));
        foreach (techn_apply_specialties() as $label) {
            if (strtolower($label) === $want) {
                return $label;
            }
        }
        return '';
    }

    function techn_apply_status_label($status)
    {
        $status = strtolower(trim((string) $status));
        if ($status === 'awaiting_role_change') {
            return 'Awaiting role confirmation';
        }
        if ($status === 'approved') {
            return 'Approved';
        }
        return 'Pending';
    }

    function techn_apply_upload_root()
    {
        static $dir = null;
        if ($dir !== null) {
            return $dir;
        }
        $env = '';
        $g = getenv('ZPGC_UPLOAD_ROOT');
        if ($g !== false && trim((string) $g) !== '') {
            $env = trim((string) $g);
        } elseif (!empty($_SERVER['ZPGC_UPLOAD_ROOT'])) {
            $env = trim((string) $_SERVER['ZPGC_UPLOAD_ROOT']);
        } elseif (!empty($_ENV['ZPGC_UPLOAD_ROOT'])) {
            $env = trim((string) $_ENV['ZPGC_UPLOAD_ROOT']);
        }
        if ($env !== '') {
            $dir = rtrim(str_replace('\\', '/', $env), '/') . '/resumes';
        } elseif (ticket_is_azure_host()) {
            $home = getenv('HOME');
            if ($home === false || trim((string) $home) === '') {
                $home = '/home';
            }
            $dir = rtrim(str_replace('\\', '/', (string) $home), '/') . '/site/uploads/resumes';
        } else {
            $dir = str_replace('\\', '/', dirname(__DIR__)) . '/uploads/resumes';
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    function techn_apply_legacy_root()
    {
        return str_replace('\\', '/', dirname(__DIR__)) . '/uploads/resumes';
    }

    function techn_apply_resume_path($storedName)
    {
        $name = basename((string) $storedName);
        if ($name === '' || $name === '.' || $name === '..') {
            return '';
        }
        foreach ([techn_apply_upload_root(), techn_apply_legacy_root()] as $root) {
            $path = $root . '/' . $name;
            if (is_file($path)) {
                return $path;
            }
        }
        return techn_apply_upload_root() . '/' . $name;
    }

    function techn_apply_unlink_resume($storedName)
    {
        $name = basename((string) $storedName);
        if ($name === '' || $name === '.' || $name === '..') {
            return;
        }
        foreach ([techn_apply_upload_root(), techn_apply_legacy_root()] as $root) {
            $path = $root . '/' . $name;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    function techn_apply_allowed_resume_mime($mime, $ext)
    {
        $mime = strtolower(trim((string) $mime));
        $ext = strtolower(trim((string) $ext));
        $okExt = ['pdf', 'doc', 'docx'];
        if (!in_array($ext, $okExt, true)) {
            return false;
        }
        $okMime = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/octet-stream',
            'application/x-pdf',
        ];
        return $mime === '' || in_array($mime, $okMime, true);
    }

    function techn_apply_store_resume(array $file)
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Upload a PDF, DOC, or DOCX resume.'];
        }
        $err = (int) ($file['error'] ?? 0);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return ['ok' => false, 'error' => 'Resume is too large. Use a PDF, DOC, or DOCX under 5 MB.'];
        }
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Resume upload failed. Try a smaller PDF, DOC, or DOCX file.'];
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'error' => 'Resume upload was not received.'];
        }
        $orig = basename((string) ($file['name'] ?? 'resume'));
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'Resume must be 5 MB or smaller.'];
        }
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $mime = (string) finfo_file($fi, $tmp);
                finfo_close($fi);
            }
        }
        if ($mime === '') {
            $mime = (string) ($file['type'] ?? '');
        }
        if (!techn_apply_allowed_resume_mime($mime, $ext)) {
            return ['ok' => false, 'error' => 'Resume must be a PDF, DOC, or DOCX file.'];
        }
        $stored = 'cv_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = techn_apply_upload_root() . '/' . $stored;
        if (!@move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'error' => 'Could not save the resume on the server.'];
        }
        return [
            'ok' => true,
            'stored' => $stored,
            'original' => $orig,
            'mime' => $mime !== '' ? $mime : 'application/octet-stream',
        ];
    }

    function techn_apply_store_resume_bytes($bytes, $origName)
    {
        $bytes = (string) $bytes;
        $orig = basename((string) $origName);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $size = strlen($bytes);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'Resume must be 5 MB or smaller.'];
        }
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $mime = (string) finfo_buffer($fi, $bytes);
                finfo_close($fi);
            }
        }
        if (!techn_apply_allowed_resume_mime($mime, $ext)) {
            return ['ok' => false, 'error' => 'Resume must be a PDF, DOC, or DOCX file.'];
        }
        $stored = 'cv_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = techn_apply_upload_root() . '/' . $stored;
        if (@file_put_contents($dest, $bytes) === false) {
            return ['ok' => false, 'error' => 'Could not save the resume on the server.'];
        }
        return [
            'ok' => true,
            'stored' => $stored,
            'original' => $orig !== '' ? $orig : 'resume.' . $ext,
            'mime' => $mime !== '' ? $mime : 'application/octet-stream',
        ];
    }

    function techn_apply_ready(mysqli $conn)
    {
        static $done = false;
        if ($done) {
            return;
        }
        if (function_exists('zpgc_runtime_ddl_allowed') && !zpgc_runtime_ddl_allowed()) {
            $done = true;
            return;
        }
        $conn->query(
            "CREATE TABLE IF NOT EXISTS technician_applications (
                id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                specialty VARCHAR(32) NOT NULL,
                proposed_specialty VARCHAR(32) DEFAULT NULL,
                resume_stored_name VARCHAR(255) NOT NULL,
                resume_original_name VARCHAR(255) NOT NULL,
                resume_mime VARCHAR(128) NOT NULL DEFAULT 'application/octet-stream',
                status VARCHAR(32) NOT NULL DEFAULT 'pending',
                role_change_expires_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_tech_app_user (user_id),
                KEY idx_tech_app_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $done = true;
    }

    function techn_apply_get_for_user(mysqli $conn, $userId)
    {
        techn_apply_ready($conn);
        $userId = (int) $userId;
        if ($userId <= 0) {
            return null;
        }
        $stmt = $conn->prepare(
            'SELECT * FROM technician_applications WHERE user_id = ? LIMIT 1'
        );
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    function techn_apply_get(mysqli $conn, $appId)
    {
        techn_apply_ready($conn);
        $appId = (int) $appId;
        if ($appId <= 0) {
            return null;
        }
        $stmt = $conn->prepare(
            'SELECT a.*, u.first_name, u.last_name, u.email, u.status AS user_status, u.role
             FROM technician_applications a
             INNER JOIN users u ON u.id = a.user_id
             WHERE a.id = ? LIMIT 1'
        );
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param('i', $appId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    function techn_apply_list_open(mysqli $conn)
    {
        techn_apply_ready($conn);
        $rows = [];
        $sql = "SELECT a.*, u.first_name, u.last_name, u.email, u.status AS user_status
                FROM technician_applications a
                INNER JOIN users u ON u.id = a.user_id
                WHERE a.status IN ('pending', 'awaiting_role_change')
                ORDER BY a.created_at ASC
                LIMIT 200";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    function techn_apply_clear_role_tokens(mysqli $conn, $userId)
    {
        $userId = (int) $userId;
        $purposes = ['techn_role_change', 'techn_role_accept', 'techn_role_deny'];
        $stmt = $conn->prepare(
            'UPDATE auth_tokens SET used_at = NOW()
             WHERE user_id = ? AND purpose = ? AND used_at IS NULL'
        );
        if (!$stmt) {
            return;
        }
        foreach ($purposes as $purpose) {
            $stmt->bind_param('is', $userId, $purpose);
            $stmt->execute();
        }
        $stmt->close();
    }

    function techn_apply_remove_inactive_technician(mysqli $conn, $userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return ['ok' => false, 'deleted_user' => false];
        }
        $app = techn_apply_get_for_user($conn, $userId);
        if ($app) {
            techn_apply_delete_row($conn, $app);
        } else {
            techn_apply_clear_role_tokens($conn, $userId);
        }
        $stmt = $conn->prepare(
            "SELECT role, status FROM users WHERE id = ? LIMIT 1"
        );
        if ($stmt === false) {
            return ['ok' => true, 'deleted_user' => false];
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $role = strtolower((string) ($user['role'] ?? ''));
        $status = strtolower((string) ($user['status'] ?? ''));
        if ($role !== 'techn' || $status === 'active') {
            return ['ok' => true, 'deleted_user' => false];
        }
        $del = $conn->prepare('DELETE FROM users WHERE id = ? AND role = \'techn\' AND status <> \'active\'');
        if ($del === false) {
            return ['ok' => true, 'deleted_user' => false];
        }
        $del->bind_param('i', $userId);
        $del->execute();
        $gone = $del->affected_rows > 0;
        $del->close();
        return ['ok' => true, 'deleted_user' => $gone];
    }

    function techn_apply_delete_row(mysqli $conn, array $app)
    {
        $id = (int) ($app['id'] ?? 0);
        $userId = (int) ($app['user_id'] ?? 0);
        if ($id <= 0) {
            return false;
        }
        techn_apply_unlink_resume($app['resume_stored_name'] ?? '');
        techn_apply_clear_role_tokens($conn, $userId);
        $stmt = $conn->prepare('DELETE FROM technician_applications WHERE id = ?');
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    function techn_apply_expire_stale(mysqli $conn, $notify = false)
    {
        $result = @$conn->query(
            "SELECT * FROM technician_applications
             WHERE status = 'awaiting_role_change'
               AND role_change_expires_at IS NOT NULL
               AND role_change_expires_at < NOW()"
        );
        if (!$result) {
            return 0;
        }
        $n = 0;
        while ($row = $result->fetch_assoc()) {
            $userId = (int) ($row['user_id'] ?? 0);
            $email = '';
            if ($notify && $userId > 0) {
                $u = $conn->query('SELECT email FROM users WHERE id = ' . $userId);
                $email = $u && ($ur = $u->fetch_assoc()) ? (string) ($ur['email'] ?? '') : '';
            }
            $removed = techn_apply_remove_inactive_technician($conn, $userId);
            if (!empty($removed['ok'])) {
                $n++;
                if ($notify && $email !== '') {
                    mail_send(
                        $email,
                        'ZPGC technician offer expired',
                        'You did not respond to the role-change offer within 24 hours. Your technician application and account were removed. You may create a new account if you wish to apply again.',
                        '<p>You did not respond to the role-change offer within 24 hours. Your technician application and account were removed. You may create a new account if you wish to apply again.</p>'
                    );
                }
            }
        }
        return $n;
    }

    function techn_apply_notify_admins(mysqli $conn, $subject, $body)
    {
        $result = @$conn->query(
            "SELECT id FROM users WHERE role = 'admin' AND status = 'active' LIMIT 20"
        );
        if (!$result) {
            return;
        }
        while ($row = $result->fetch_assoc()) {
            notify_user_email($conn, (int) $row['id'], $subject, $body);
        }
    }

    function techn_apply_send_role_change(mysqli $conn, array $user, $proposed)
    {
        // Application rows use id = application id; the technician is user_id.
        $userId = (int) ($user['user_id'] ?? 0);
        if ($userId <= 0) {
            $userId = (int) ($user['id'] ?? 0);
        }
        $email = (string) ($user['email'] ?? '');
        $first = trim((string) ($user['first_name'] ?? ''));
        $last = trim((string) ($user['last_name'] ?? ''));
        $full = trim($first . ' ' . $last);
        $requested = (string) ($user['specialty'] ?? '');
        if ($userId <= 0 || $email === '') {
            return ['ok' => false, 'error' => 'missing user'];
        }
        auth_mail_ready($conn);
        techn_apply_clear_role_tokens($conn, $userId);
        $offerTok = auth_mail_create_token($conn, $userId, 'techn_role_change', 24);
        $acceptLink = mail_app_url('pages/techn_role_confirm.php?token=' . rawurlencode($offerTok) . '&decision=accept');
        $denyLink = mail_app_url('pages/techn_role_confirm.php?token=' . rawurlencode($offerTok) . '&decision=deny');
        $name = $full !== '' ? $full : $first;
        $plain = "ZPGC Services — Office of the Administrator\n\n"
            . "Dear " . ($name !== '' ? $name : 'Applicant') . ",\n\n"
            . "This letter confirms that your technician application has been reviewed.\n\n"
            . "You applied for the {$requested} role. The administrator offers you the {$proposed} role instead.\n\n"
            . "ACCEPT this assignment (activates your technician account):\n<{$acceptLink}>\n\n"
            . "DECLINE this assignment (withdraws the application and removes your account):\n<{$denyLink}>\n\n"
            . "This offer expires in 24 hours. If you do not respond, the application and account will be removed.\n\n"
            . "Respectfully,\nZPGC Services Administration";
        $html = '<div style="font-family:Georgia,serif;color:#1a1a1a;line-height:1.55;max-width:640px">'
            . '<p style="letter-spacing:0.12em;text-transform:uppercase;font-size:12px;color:#610107;font-weight:700;font-family:Arial,sans-serif">ZPGC Services</p>'
            . '<p><strong>Office of the Administrator</strong></p>'
            . '<p>Dear ' . htmlspecialchars($name !== '' ? $name : 'Applicant', ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>This letter confirms that your technician application has been reviewed.</p>'
            . '<p>You applied for the <strong>' . htmlspecialchars($requested, ENT_QUOTES, 'UTF-8')
            . '</strong> role. The administrator offers you the <strong>'
            . htmlspecialchars((string) $proposed, ENT_QUOTES, 'UTF-8')
            . '</strong> role instead.</p>'
            . '<p>To <strong>accept</strong> this assignment and activate your technician account, use the button below. '
            . 'To <strong>decline</strong>, use the second button. Declining withdraws your application and removes your account from ZPGC Services.</p>'
            . '<p><a href="' . htmlspecialchars($acceptLink, ENT_QUOTES, 'UTF-8')
            . '" style="display:inline-block;background:#610107;color:#fff;padding:10px 18px;text-decoration:none;border-radius:6px;font-family:Arial,sans-serif">Accept role offer</a></p>'
            . '<p><a href="' . htmlspecialchars($denyLink, ENT_QUOTES, 'UTF-8')
            . '" style="display:inline-block;background:#fff;color:#610107;padding:10px 18px;text-decoration:none;border-radius:6px;border:1px solid #c9a3a6;font-family:Arial,sans-serif">Decline role offer</a></p>'
            . '<p>This offer expires in 24 hours. If you do not respond, the application and account will be removed.</p>'
            . '<p>Respectfully,<br>ZPGC Services Administration</p>'
            . '</div>';
        return mail_send($email, 'ZPGC Services — Technician role assignment', $plain, $html);
    }

    function techn_apply_accept_role_change(mysqli $conn, $userId)
    {
        $app = techn_apply_get_for_user($conn, $userId);
        if (!$app) {
            return ['ok' => false, 'error' => 'Application not found.'];
        }
        if (($app['status'] ?? '') !== 'awaiting_role_change') {
            return ['ok' => false, 'error' => 'There is no specialty change waiting for you.'];
        }
        $proposed = techn_apply_normalize_specialty($app['proposed_specialty'] ?? '');
        if ($proposed === '') {
            return ['ok' => false, 'error' => 'The proposed specialty is invalid.'];
        }
        $id = (int) $app['id'];
        $stmt = $conn->prepare(
            "UPDATE technician_applications
             SET specialty = ?, proposed_specialty = NULL, status = 'pending', role_change_expires_at = NULL
             WHERE id = ?"
        );
        $stmt->bind_param('si', $proposed, $id);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'error' => 'Could not save the specialty change.'];
        }
        $activated = techn_apply_approve_account($conn, (int) $userId);
        if (empty($activated['ok'])) {
            return ['ok' => false, 'error' => $activated['error'] ?? 'Could not activate the account.'];
        }
        $name = trim((string) ($app['first_name'] ?? '') . ' ' . (string) ($app['last_name'] ?? ''));
        if ($name === '') {
            $u = $conn->query('SELECT first_name, last_name FROM users WHERE id = ' . (int) $userId);
            if ($u && ($ur = $u->fetch_assoc())) {
                $name = trim((string) ($ur['first_name'] ?? '') . ' ' . (string) ($ur['last_name'] ?? ''));
            }
        }
        techn_apply_notify_admins(
            $conn,
            'Technician accepted role offer',
            ($name !== '' ? $name : 'A technician') . ' accepted the ' . $proposed
            . ' role. The account is now active.'
        );
        return ['ok' => true, 'specialty' => $proposed, 'activated' => true];
    }

    function techn_apply_decline_role_change(mysqli $conn, $userId)
    {
        $userId = (int) $userId;
        $app = techn_apply_get_for_user($conn, $userId);
        if (!$app) {
            return ['ok' => false, 'error' => 'Application not found.'];
        }
        if (($app['status'] ?? '') !== 'awaiting_role_change') {
            return ['ok' => false, 'error' => 'There is no specialty offer waiting for you.'];
        }
        $email = '';
        $u = $conn->query('SELECT email, first_name FROM users WHERE id = ' . $userId);
        if ($u && ($ur = $u->fetch_assoc())) {
            $email = (string) ($ur['email'] ?? '');
        }
        $removed = techn_apply_remove_inactive_technician($conn, $userId);
        if (empty($removed['ok'])) {
            return ['ok' => false, 'error' => 'Could not withdraw the application.'];
        }
        if ($email !== '') {
            mail_send(
                $email,
                'ZPGC technician application withdrawn',
                'You declined the role assignment. Your technician application and account have been removed from ZPGC Services.',
                '<p>You declined the role assignment. Your technician application and account have been removed from ZPGC Services.</p>'
            );
        }
        return ['ok' => true, 'deleted_user' => !empty($removed['deleted_user'])];
    }

    function techn_apply_approve_account(mysqli $conn, $userId)
    {
        $userId = (int) $userId;
        $app = techn_apply_get_for_user($conn, $userId);
        if (!$app) {
            return ['ok' => false, 'error' => 'No application to approve.'];
        }
        $stmt = $conn->prepare(
            "UPDATE users SET status = 'active', email_verified = 1, role = 'techn' WHERE id = ?"
        );
        $stmt->bind_param('i', $userId);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['ok' => false, 'error' => 'Could not activate the account.'];
        }
        $appId = (int) $app['id'];
        $up = $conn->prepare(
            "UPDATE technician_applications
             SET status = 'approved', proposed_specialty = NULL, role_change_expires_at = NULL
             WHERE id = ?"
        );
        $up->bind_param('i', $appId);
        $up->execute();
        $up->close();
        techn_apply_clear_role_tokens($conn, $userId);
        notify_user_email(
            $conn,
            $userId,
            'Your ZPGC technician account is active',
            'An administrator approved your application. You can log in and use the technician dashboard.'
        );
        return ['ok' => true];
    }

    function techn_apply_admin_set_specialty(mysqli $conn, $userId, $specialty)
    {
        techn_apply_ready($conn);
        $userId = (int) $userId;
        $specialty = techn_apply_normalize_specialty($specialty);
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Missing technician account.'];
        }
        if ($specialty === '') {
            return ['ok' => false, 'error' => 'Choose a technical role: Hardware, Software, Network, Account, or Other.'];
        }
        $app = techn_apply_get_for_user($conn, $userId);
        if ($app) {
            $id = (int) $app['id'];
            $stmt = $conn->prepare(
                "UPDATE technician_applications
                 SET specialty = ?, proposed_specialty = NULL, status = 'approved',
                     role_change_expires_at = NULL
                 WHERE id = ?"
            );
            $stmt->bind_param('si', $specialty, $id);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok ? ['ok' => true, 'specialty' => $specialty] : ['ok' => false, 'error' => 'Could not save the technical role.'];
        }
        $stored = 'admin_provisioned';
        $orig = 'Admin created — no resume';
        $mime = 'application/octet-stream';
        $status = 'approved';
        $stmt = $conn->prepare(
            'INSERT INTO technician_applications
             (user_id, specialty, resume_stored_name, resume_original_name, resume_mime, status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssss', $userId, $specialty, $stored, $orig, $mime, $status);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? ['ok' => true, 'specialty' => $specialty] : ['ok' => false, 'error' => 'Could not save the technical role.'];
    }
}

<?php
require_once 'session_config.php';
require_once 'config.php';
zpgc_csrf_require();
require_once 'auth_mail.php';

auth_mail_ready($conn);

if (isset($_POST['login'])) {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = $conn->prepare(
        'SELECT id, first_name, last_name, email, password, role, status, email_verified
        FROM users WHERE LOWER(email) = ?'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $verified = (int) ($user['email_verified'] ?? 0) === 1;
        // Older active accounts created before verification keep working.
        if (!$verified && $user['status'] === 'active') {
            $verified = true;
        }
        if (!$verified) {
            $_SESSION['pending_verify_email'] = (string) $user['email'];
            if (!mail_ready()) {
                $_SESSION['signup_error'] = 'Your email is not verified yet, and mail is not configured. Ask an admin to Activate your account.';
                header('Location: ../pages/verify_pending.php');
                exit();
            }
            // Always send a fresh code here — do not claim one was sent without SMTP.
            $sent = auth_mail_send_verify(
                $conn,
                (int) $user['id'],
                (string) $user['email'],
                (string) ($user['first_name'] ?? '')
            );
            if ($sent['ok']) {
                $_SESSION['signup_success'] = 'Your account is not verified yet. We just emailed a new 6-digit code to '
                    . $user['email'] . '. Check TSU Outlook (Inbox and Junk), then paste it below.';
            } else {
                $_SESSION['signup_error'] = 'Your account is not verified yet, and we could not email a code ('
                    . $sent['error'] . '). Tap Resend, or ask an admin to Activate your account.';
            }
            header('Location: ../pages/verify_pending.php');
            exit();
        }
        if ($user['status'] !== 'active') {
            if ($verified && ($user['role'] ?? '') === 'techn') {
                $_SESSION['login_error'] = 'Your email is verified. An administrator must Activate your technician account in Utilities before you can log in.';
            } else {
                $_SESSION['login_error'] = 'Your account is not active. Complete email verification or contact an administrator.';
            }
            header('Location: ../pages/login_signup.php');
            exit();
        }
        zpgc_establish_login_session($user);

        $returnTo = zpgc_consume_login_return();
        if ($returnTo !== '') {
            // Only honor return URL when the signed-in role may open that page.
            // Admin probe / tools stay admin-only; others keep role home.
            $role = strtolower((string) $user['role']);
            $returnBase = strtolower((string) parse_url($returnTo, PHP_URL_PATH));
            $adminOnly = ['ai_status.php', 'mail_status.php'];
            $needsAdmin = false;
            foreach ($adminOnly as $file) {
                if (str_ends_with($returnBase, '/' . $file) || str_ends_with($returnBase, $file)) {
                    $needsAdmin = true;
                    break;
                }
            }
            if (!$needsAdmin || $role === 'admin') {
                header('Location: ' . $returnTo);
                exit();
            }
        }

        header('Location: ' . zpgc_role_home($user['role'] ?? 'user'));
        exit();
    }
    $_SESSION['login_error'] = 'Incorrect credentials.';
    header('Location: ../pages/login_signup.php');
    exit();
}

if (isset($_POST['signup'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $acceptedTerms = isset($_POST['accept_terms']);

    $allowed = ['user', 'techn'];
    if (!$acceptedTerms) {
        $_SESSION['signup_error'] = 'Accept the Terms of Agreement to create an account.';
        header('Location: ../pages/login_signup.php?form=signup');
        exit();
    }
    if ($first_name === '' || $last_name === '' || strlen($password) < 8) {
        $_SESSION['signup_error'] = 'Fill every field. Password must be at least 8 characters.';
        header('Location: ../pages/login_signup.php?form=signup');
        exit();
    }
    if (!in_array($role, $allowed, true)) {
        $_SESSION['signup_error'] = 'Choose User or Technician.';
        header('Location: ../pages/login_signup.php?form=signup');
        exit();
    }
    if (!auth_mail_is_tsu_email($email)) {
        $_SESSION['signup_error'] = auth_mail_tsu_email_hint();
        header('Location: ../pages/login_signup.php?form=signup');
        exit();
    }
    if (!mail_ready()) {
        $_SESSION['signup_error'] = 'Email sending is not configured yet. Ask an administrator to set MAIL_USERNAME and MAIL_PASSWORD.';
        header('Location: ../pages/login_signup.php?form=signup');
        exit();
    }

    $check = $conn->prepare(
        'SELECT id, first_name, email_verified, status FROM users WHERE email = ? LIMIT 1'
    );
    $check->bind_param('s', $email);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();
    if ($existing) {
        $alreadyVerified = (int) ($existing['email_verified'] ?? 0) === 1
            || ($existing['status'] ?? '') === 'active';
        if ($alreadyVerified) {
            $_SESSION['signup_error'] = 'That email is already registered. Log in or use Forgot Password.';
            header('Location: ../pages/login_signup.php?form=signup');
            exit();
        }
        // Stuck unverified signup: resend a new 6-digit code.
        $resent = auth_mail_send_verify(
            $conn,
            (int) $existing['id'],
            $email,
            (string) ($existing['first_name'] ?? $first_name)
        );
        $_SESSION['pending_verify_email'] = $email;
        if ($resent['ok']) {
            $_SESSION['signup_success'] = 'That email is already signed up but not verified. '
                . 'We sent a new 6-digit code to ' . $email . '. Check your TSU Outlook inbox.';
            header('Location: ../pages/verify_pending.php');
            exit();
        }
        $_SESSION['signup_error'] = 'That email is already signed up but not verified, and email failed ('
            . $resent['error'] . '). Use Resend code, or ask an admin to Activate the account.';
        header('Location: ../pages/verify_pending.php');
        exit();
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $status = 'inactive';
    $verified = 0;
    $stmt = $conn->prepare(
        'INSERT INTO users (first_name, last_name, email, password, role, status, email_verified)
        VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('ssssssi', $first_name, $last_name, $email, $hash, $role, $status, $verified);

    if (!$stmt->execute()) {
        $stmt->close();
        $_SESSION['signup_error'] = 'Could not create that account. Try again.';
        header('Location: ../pages/login_signup.php?form=signup');
        exit();
    }
    $userId = (int) $conn->insert_id;
    $stmt->close();

    $_SESSION['pending_verify_email'] = $email;
    $sent = auth_mail_send_verify($conn, $userId, $email, $first_name);
    if ($sent['ok']) {
        if ($role === 'techn') {
            $_SESSION['signup_success'] = 'Account created. Check ' . $email
                . ' (TSU Outlook Inbox and Junk) for a 6-digit code. After you verify, an administrator must still Activate your technician account.';
        } else {
            $_SESSION['signup_success'] = 'Account created. Check ' . $email
                . ' (TSU Outlook Inbox and Junk) for a 6-digit code, then paste it on the next screen to activate.';
        }
    } else {
        $_SESSION['signup_error'] = 'Account created, but email failed: ' . $sent['error']
            . ' Use Resend below, or ask an admin to Activate you in Utilities.';
    }
    header('Location: ../pages/verify_pending.php');
    exit();
}

if (isset($_POST['resend_verify'])) {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $_SESSION['pending_verify_email'] = $email;
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['signup_error'] = 'Enter the email you used at signup to resend verification.';
        header('Location: ../pages/verify_pending.php');
        exit();
    }
    if (!mail_ready()) {
        $_SESSION['signup_error'] = 'Email is not configured. Ask an admin to Activate your account in Utilities.';
        header('Location: ../pages/verify_pending.php');
        exit();
    }
    $stmt = $conn->prepare(
        'SELECT id, first_name, email_verified, status FROM users WHERE LOWER(email) = ? LIMIT 1'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    // Same generic message whether or not the account exists (avoid account enumeration).
    $generic = 'If that email still needs verification, we sent a new 6-digit code. Check TSU Outlook Inbox and Junk.';
    if (
        $user
        && (int) ($user['email_verified'] ?? 0) !== 1
        && ($user['status'] ?? '') !== 'active'
    ) {
        $sent = auth_mail_send_verify(
            $conn,
            (int) $user['id'],
            $email,
            (string) ($user['first_name'] ?? '')
        );
        if ($sent['ok']) {
            $_SESSION['signup_success'] = $generic;
        } else {
            $_SESSION['signup_error'] = 'Could not send verification email (' . $sent['error']
                . '). Ask an admin to Activate your account in Utilities.';
        }
    } else {
        $_SESSION['signup_success'] = $generic;
    }
    header('Location: ../pages/verify_pending.php');
    exit();
}

if (isset($_POST['verify_code'])) {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $code = trim((string) ($_POST['code'] ?? ''));
    $_SESSION['pending_verify_email'] = $email;
    if ($email === '' || !auth_mail_is_tsu_email($email)) {
        $_SESSION['signup_error'] = auth_mail_tsu_email_hint();
        header('Location: ../pages/verify_pending.php');
        exit();
    }
    if ($code === '') {
        $_SESSION['signup_error'] = 'Enter the 6-digit code from your email.';
        header('Location: ../pages/verify_pending.php');
        exit();
    }
    $userId = auth_mail_consume_verify_code($conn, $email, $code);
    if (!$userId) {
        $_SESSION['signup_error'] = 'That code is invalid or expired. Request a new code below.';
        header('Location: ../pages/verify_pending.php');
        exit();
    }
    $result = auth_mail_activate_verified_user($conn, $userId);
    unset($_SESSION['pending_verify_email']);
    if (!empty($result['awaiting_admin'])) {
        $_SESSION['login_success'] = 'Email verified. Your technician account is waiting for an administrator to Activate it in Utilities. You cannot log in until then.';
    } else {
        $_SESSION['login_success'] = 'Email verified and account activated. You can log in now.';
    }
    header('Location: ../pages/login_signup.php');
    exit();
}

header('Location: ../pages/login_signup.php');
exit();

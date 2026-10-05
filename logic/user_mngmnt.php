<?php
require_once 'session_config.php';
require_once 'config.php';
zpgc_csrf_require();
require_once 'auth_mail.php';

auth_mail_ready($conn);

if (isset($_POST['login'])) {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!zpgc_rate_limit('login', 8, 900)) {
        $_SESSION['login_error'] = 'Too many login attempts. Wait a few minutes, then try again.';
        header('Location: ../pages/login_signup.php');
        exit();
    }

    $stmt = $conn->prepare(
        'SELECT id, first_name, last_name, email, password, role, status, email_verified
        FROM users WHERE LOWER(email) = ? LIMIT 1'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        // Canonicalize technician aliases before gate / session.
        $user['role'] = auth_mail_normalize_role($user['role'] ?? 'user');
        $gate = auth_mail_login_gate($user);

        if (!$gate['ok'] && !empty($gate['need_verify'])) {
            $_SESSION['pending_verify_email'] = (string) $user['email'];
            if (!mail_ready()) {
                $_SESSION['signup_error'] = 'Your email is not verified yet, and mail is not configured. '
                    . (auth_mail_is_technician_role($user['role'])
                        ? 'Ask an admin to Activate your technician account in Utilities.'
                        : 'Ask an admin for help.');
                header('Location: ../pages/verify_pending.php');
                exit();
            }
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
                    . $sent['error'] . '). Tap Resend, or ask an admin for help.';
            }
            header('Location: ../pages/verify_pending.php');
            exit();
        }

        if (!$gate['ok']) {
            $_SESSION['login_error'] = $gate['message'] !== ''
                ? $gate['message']
                : 'Your account cannot log in yet.';
            header('Location: ../pages/login_signup.php');
            exit();
        }

        // Extra hard stop: technicians must never get a session while inactive.
        if (auth_mail_is_technician_role($user['role']) && strtolower((string) $user['status']) !== 'active') {
            $_SESSION['login_error'] = 'Your email is verified. An administrator must Activate your technician account in Utilities before you can log in.';
            header('Location: ../pages/login_signup.php');
            exit();
        }

        zpgc_rate_limit_clear('login');
        zpgc_establish_login_session($user);

        $returnTo = zpgc_consume_login_return();
        if ($returnTo !== '') {
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
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
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
    if (!hash_equals($password, $confirmPassword)) {
        $_SESSION['signup_error'] = 'Password and Confirm password do not match.';
        header('Location: ../pages/login_signup.php?form=signup');
        exit();
    }
    $role = auth_mail_normalize_role($role);
    if (!in_array($role, $allowed, true)) {
        $_SESSION['signup_error'] = 'Choose User or Technician.';
        header('Location: ../pages/login_signup.php?form=signup');
        exit();
    }

    // Rate-limit signup bursts (testers inventing emails).
    if (!zpgc_rate_limit('signup', 5, 3600)) {
        $_SESSION['signup_error'] = 'Too many signup attempts from this browser. Wait a bit, then use your real TSU Outlook email.';
        header('Location: ../pages/login_signup.php?form=signup');
        exit();
    }

    if (!auth_mail_is_tsu_email($email) || !auth_mail_is_plausible_tsu_mailbox($email)) {
        $_SESSION['signup_error'] = auth_mail_tsu_email_hint();
        header('Location: ../pages/login_signup.php?form=signup');
        exit();
    }
    if (!mail_ready()) {
        $_SESSION['signup_error'] = 'Email sending is not configured yet. Ask an administrator to set MAIL_USERNAME and MAIL_PASSWORD.';
        header('Location: ../pages/login_signup.php?form=signup');
        exit();
    }

    auth_mail_purge_stale_unverified($conn);

    $check = $conn->prepare(
        'SELECT id, first_name, email_verified, status, role FROM users WHERE email = ? LIMIT 1'
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
        if ($resent['ok']) {
            $_SESSION['pending_verify_email'] = $email;
            $_SESSION['signup_success'] = 'That email is already signed up but not verified. '
                . 'If ' . $email . ' is a real TSU Outlook mailbox you can open, check Inbox and Junk for a new 6-digit code. '
                . 'Invented emails never receive a code — go back and use your real address.';
            header('Location: ../pages/verify_pending.php');
            exit();
        }
        $_SESSION['signup_error'] = 'Could not email a verification code to ' . $email . ' ('
            . $resent['error'] . '). Use a real TSU Outlook inbox, or ask an admin for help.';
        header('Location: ../pages/login_signup.php?form=signup');
        exit();
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    // Always inactive at signup. Users become active after 6-digit verify;
    // technicians stay inactive until an admin Activates them.
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

    $sent = auth_mail_send_verify($conn, $userId, $email, $first_name);
    if (!$sent['ok']) {
        // Foolproof: never leave a half-created account claiming "check your email".
        auth_mail_delete_user($conn, $userId);
        unset($_SESSION['pending_verify_email']);
        $_SESSION['signup_error'] = 'Could not send the verification code to ' . $email
            . ' (' . $sent['error'] . '). No account was kept. '
            . 'Use your real TSU Outlook email and try again.';
        header('Location: ../pages/login_signup.php?form=signup');
        exit();
    }

    $_SESSION['pending_verify_email'] = $email;
    if ($role === 'techn') {
        $_SESSION['signup_success'] = 'Verification code sent to ' . $email
            . '. Open that real TSU Outlook inbox (and Junk). After the code, an administrator must still Activate your technician account — inventing an email will not work.';
    } else {
        $_SESSION['signup_success'] = 'Verification code sent to ' . $email
            . '. Open that real TSU Outlook inbox (and Junk), then enter the 6-digit code. '
            . 'If you made up this address, go back and sign up with your real TSU email — you will not get a code.';
    }
    header('Location: ../pages/verify_pending.php');
    exit();
}

if (isset($_POST['resend_verify'])) {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $_SESSION['pending_verify_email'] = $email;
    if ($email === '' || !auth_mail_is_tsu_email($email) || !auth_mail_is_plausible_tsu_mailbox($email)) {
        $_SESSION['signup_error'] = auth_mail_tsu_email_hint();
        header('Location: ../pages/login_signup.php?form=signup');
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
            $_SESSION['signup_success'] = 'If ' . $email
                . ' is a real TSU Outlook mailbox, a new 6-digit code was sent (check Inbox and Junk). '
                . 'Made-up addresses never receive mail — sign up again with your real TSU email.';
        } else {
            $_SESSION['signup_error'] = 'Could not send verification email (' . $sent['error']
                . '). Use a real TSU Outlook inbox, or ask an admin for help.';
        }
    } else {
        // Avoid account enumeration, but steer testers away from inventing emails.
        $_SESSION['signup_success'] = 'If that address still needs verification and is a real TSU mailbox, check Inbox/Junk for a code. Invented emails will not work.';
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

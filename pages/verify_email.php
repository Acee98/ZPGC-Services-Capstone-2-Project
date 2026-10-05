<?php
/**
 * Legacy link handler. New signups use a 6-digit code on verify_pending.php.
 * Old emails with ?token= still work and auto-activate the account.
 */
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_once '../logic/auth_mail.php';

auth_mail_ready($conn);
$token = preg_replace('/[^a-f0-9]/i', '', trim((string) ($_GET['token'] ?? '')));

if ($token === '') {
    $_SESSION['login_error'] = 'Use the 6-digit code from your email on the verify page (not this link).';
    header('Location: verify_pending.php');
    exit();
}

$userId = auth_mail_consume_token($conn, 'verify_email', $token);
if ($userId) {
    $result = auth_mail_activate_verified_user($conn, $userId);
    unset($_SESSION['pending_verify_email']);
    if (!empty($result['awaiting_admin'])) {
        $_SESSION['login_success'] = 'Email verified. Your technician account needs an administrator to Activate it in Utilities before login.';
    } else {
        $_SESSION['login_success'] = 'Email verified and account activated. You can log in now.';
    }
    header('Location: login_signup.php');
    exit();
}

$_SESSION['signup_error'] = 'That old verification link is invalid or expired. Request a new 6-digit code below.';
header('Location: verify_pending.php');
exit();

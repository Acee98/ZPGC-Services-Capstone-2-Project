<?php
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_once '../logic/auth_mail.php';

auth_mail_ready($conn);
$token = preg_replace('/[^a-f0-9]/i', '', trim((string) ($_GET['token'] ?? '')));
$userId = auth_mail_consume_token($conn, 'verify_email', $token);
if ($userId) {
    auth_mail_activate_verified_user($conn, $userId);
    unset($_SESSION['pending_verify_email']);
    $_SESSION['login_success'] = 'Email verified and account activated. You can log in now.';
} else {
    $_SESSION['login_error'] = 'That verification link is invalid or expired.';
}
header('Location: login_signup.php');
exit();

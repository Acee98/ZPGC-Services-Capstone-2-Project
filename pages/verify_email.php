<?php
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_once '../logic/auth_mail.php';

auth_mail_ready($conn);
$token = preg_replace('/[^a-f0-9]/i', '', trim((string) ($_GET['token'] ?? '')));
$userId = auth_mail_consume_token($conn, 'verify_email', $token);
if ($userId) {
    $upd = $conn->prepare('UPDATE users SET email_verified = 1 WHERE id = ?');
    $upd->bind_param('i', $userId);
    $upd->execute();
    $upd->close();
    unset($_SESSION['pending_verify_email']);
    $_SESSION['login_success'] = 'Email verified. An administrator can now Activate your account in Utilities, then you can log in.';
} else {
    $_SESSION['login_error'] = 'That verification link is invalid or expired.';
}
header('Location: login_signup.php');
exit();

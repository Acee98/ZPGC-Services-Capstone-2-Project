<?php
require_once 'session_config.php';
require_once 'config.php';
require_once __DIR__ . '/auth_mail.php';
zpgc_csrf_require();
require_login();

$returnRole = $_SESSION['role'] ?? 'user';
$returnPage = 'user.php';
if ($returnRole === 'techn') {
    $returnPage = 'techn.php';
} elseif ($returnRole === 'admin') {
    $returnPage = 'admin.php';
}
$back = '../pages/' . $returnPage . '?tab=profile';

if (!isset($_POST['save_profile'])) {
    zpgc_finish($back, false, ['message' => $_SESSION['profile_error'] ?? 'Invalid profile request.']);
}

$first = trim((string) ($_POST['first_name'] ?? ''));
$last = trim((string) ($_POST['last_name'] ?? ''));
$language = trim((string) ($_POST['preferred_language'] ?? 'English'));
$emailNotify = isset($_POST['email_notify']) ? 1 : 0;
$id = current_user_id($conn);
if ($language !== 'Filipino') {
    $language = 'English';
}

if ($id <= 0 || $first === '' || $last === '') {
    $_SESSION['profile_error'] = 'Name fields are required.';
    zpgc_finish($back, false, ['message' => $_SESSION['profile_error'] ?? '']);
}

$cur = $conn->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
if ($cur === false) {
    $_SESSION['profile_error'] = 'Profile could not be saved. Try again after the database update.';
    zpgc_finish($back, false, ['message' => $_SESSION['profile_error'] ?? '']);
}
$cur->bind_param('i', $id);
$cur->execute();
$curRow = $cur->get_result()->fetch_assoc();
$cur->close();
$email = strtolower(trim((string) ($curRow['email'] ?? '')));
$postedEmail = strtolower(trim((string) ($_POST['email'] ?? $email)));
$emailChanged = ($postedEmail !== '' && $postedEmail !== $email);
if ($emailChanged) {
    $mailErr = auth_mail_signup_email_error($postedEmail);
    if ($mailErr !== '') {
        $_SESSION['profile_error'] = $mailErr;
        zpgc_finish($back, false, ['message' => $mailErr]);
    }
    $dup = $conn->prepare('SELECT id FROM users WHERE LOWER(email) = ? AND id != ? LIMIT 1');
    $dup->bind_param('si', $postedEmail, $id);
    $dup->execute();
    $taken = $dup->get_result()->fetch_assoc();
    $dup->close();
    if ($taken) {
        $_SESSION['profile_error'] = 'That email is already used by another account.';
        zpgc_finish($back, false, ['message' => $_SESSION['profile_error']]);
    }
}

$stmt = $conn->prepare(
    'UPDATE users
     SET first_name = ?, last_name = ?, email_notify = ?, preferred_language = ?
     WHERE id = ?'
);
if ($stmt === false) {
    $stmt = $conn->prepare('UPDATE users SET first_name = ?, last_name = ? WHERE id = ?');
    if ($stmt === false) {
        $_SESSION['profile_error'] = 'Profile could not be saved. Try again after the database update.';
        zpgc_finish($back, false, ['message' => $_SESSION['profile_error']]);
    }
    $stmt->bind_param('ssi', $first, $last, $id);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt->bind_param('ssisi', $first, $last, $emailNotify, $language, $id);
    $stmt->execute();
    $stmt->close();
}

$_SESSION['preferred_language'] = $language;

if ($emailChanged) {
    $mailUp = $conn->prepare('UPDATE users SET email = ?, email_verified = 0 WHERE id = ?');
    if ($mailUp) {
        $mailUp->bind_param('si', $postedEmail, $id);
        $mailUp->execute();
        $mailUp->close();
    }
    $sent = auth_mail_send_verify($conn, $id, $postedEmail, $first);
    $notice = !empty($sent['ok'])
        ? 'We sent a 6-digit code to ' . $postedEmail . '. Enter it to finish changing your email.'
        : 'Email updated. Verification could not be sent automatically — use Resend on the next page.';
    $_SESSION = [
        'pending_verify_email' => $postedEmail,
        'signup_success' => $notice,
        '_csrf' => bin2hex(random_bytes(32)),
    ];
    zpgc_finish('../pages/verify_pending.php', true, [
        'message' => $notice,
        'action' => 'email_changed',
        'redirect' => '../pages/verify_pending.php',
    ]);
}

$_SESSION['profile_success'] = 'Profile saved.';
zpgc_finish($back, true, ['message' => 'Profile saved.', 'action' => 'save_profile']);

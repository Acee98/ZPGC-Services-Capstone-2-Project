<?php
require_once 'session_config.php';
require_once 'config.php';
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
    header('Location: ' . $back);
    exit();
}

$first = trim((string) ($_POST['first_name'] ?? ''));
$last = trim((string) ($_POST['last_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$language = trim((string) ($_POST['preferred_language'] ?? 'English'));
$emailNotify = isset($_POST['email_notify']) ? 1 : 0;
$smsNotify = isset($_POST['sms_notify']) ? 1 : 0;
$id = current_user_id($conn);
if ($language !== 'Filipino') {
    $language = 'English';
}

if ($id <= 0 || $first === '' || $last === '' || $email === '') {
    $_SESSION['profile_error'] = 'Name and e-mail are required.';
    header('Location: ' . $back);
    exit();
}

$check = $conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
if ($check === false) {
    $_SESSION['profile_error'] = 'Profile could not be saved. Try again after the database update.';
    header('Location: ' . $back);
    exit();
}
$check->bind_param('si', $email, $id);
$check->execute();
$taken = $check->get_result()->fetch_assoc();
$check->close();
if ($taken) {
    $_SESSION['profile_error'] = 'That email is already used by another account.';
    header('Location: ' . $back);
    exit();
}

$stmt = $conn->prepare(
    'UPDATE users
     SET first_name = ?, last_name = ?, email = ?, phone = ?, email_notify = ?, sms_notify = ?, preferred_language = ?
     WHERE id = ?'
);
if ($stmt === false) {
    $_SESSION['profile_error'] = 'Profile could not be saved. Try again after the database update.';
    header('Location: ' . $back);
    exit();
}
$stmt->bind_param('ssssiisi', $first, $last, $email, $phone, $emailNotify, $smsNotify, $language, $id);
$stmt->execute();
$stmt->close();
$_SESSION['email'] = $email;

$_SESSION['profile_success'] = 'Profile saved.';
header('Location: ' . $back);
exit();

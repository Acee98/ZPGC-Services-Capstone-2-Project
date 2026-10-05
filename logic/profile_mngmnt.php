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
$phone = trim((string) ($_POST['phone'] ?? ''));
$language = trim((string) ($_POST['preferred_language'] ?? 'English'));
$emailNotify = isset($_POST['email_notify']) ? 1 : 0;
$smsNotify = isset($_POST['sms_notify']) ? 1 : 0;
$id = current_user_id($conn);
if ($language !== 'Filipino') {
    $language = 'English';
}

if ($id <= 0 || $first === '' || $last === '') {
    $_SESSION['profile_error'] = 'Name fields are required.';
    header('Location: ' . $back);
    exit();
}

// Email is identity for TSU auth — do not allow silent change without re-verify.
$cur = $conn->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
if ($cur === false) {
    $_SESSION['profile_error'] = 'Profile could not be saved. Try again after the database update.';
    header('Location: ' . $back);
    exit();
}
$cur->bind_param('i', $id);
$cur->execute();
$curRow = $cur->get_result()->fetch_assoc();
$cur->close();
$email = strtolower(trim((string) ($curRow['email'] ?? '')));
$postedEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
if ($postedEmail !== '' && $email !== '' && $postedEmail !== $email) {
    $_SESSION['profile_error'] = 'Email cannot be changed here. Ask an administrator if you need a different TSU address.';
    header('Location: ' . $back);
    exit();
}

$stmt = $conn->prepare(
    'UPDATE users
     SET first_name = ?, last_name = ?, phone = ?, email_notify = ?, sms_notify = ?, preferred_language = ?
     WHERE id = ?'
);
if ($stmt === false) {
    $stmt = $conn->prepare(
        'UPDATE users SET first_name = ?, last_name = ? WHERE id = ?'
    );
    if ($stmt === false) {
        $_SESSION['profile_error'] = 'Profile could not be saved. Try again after the database update.';
        header('Location: ' . $back);
        exit();
    }
    $stmt->bind_param('ssi', $first, $last, $id);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt->bind_param('ssiissi', $first, $last, $phone, $emailNotify, $smsNotify, $language, $id);
    $stmt->execute();
    $stmt->close();
}

$_SESSION['profile_success'] = 'Profile saved.';
header('Location: ' . $back);
exit();

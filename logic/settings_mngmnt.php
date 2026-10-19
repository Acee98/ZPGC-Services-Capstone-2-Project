<?php
require_once 'session_config.php';
require_once 'config.php';
zpgc_csrf_require();
require_login();

$returnRole = $_SESSION['role'] ?? 'admin';
$returnPage = 'admin.php';
if ($returnRole === 'user') {
    $returnPage = 'user.php';
} elseif ($returnRole === 'techn') {
    $returnPage = 'techn.php';
}

if (isset($_POST['save_appearance'])) {
    $theme = strtolower(trim((string) ($_POST['theme'] ?? 'light'))) === 'dark' ? 'dark' : 'light';
    save_ui_theme($theme);
    zpgc_finish('../pages/' . $returnPage . '?tab=settings', true, [
        'message' => '',
        'action' => 'save_settings',
        'theme' => $theme,
        'flash' => 'ok',
    ]);
}

if (!isset($_POST['change_password'])) {
    zpgc_finish('../pages/' . $returnPage . '?tab=settings', isset($_SESSION['settings_success']), [
        'message' => $_SESSION['settings_success'] ?? $_SESSION['settings_error'] ?? '',
        'action' => 'save_settings',
        'flash' => isset($_SESSION['settings_error']) ? 'error' : 'ok',
    ]);
}

$current = (string) ($_POST['current_password'] ?? '');
$new = (string) ($_POST['new_password'] ?? '');
$confirm = (string) ($_POST['confirm_password'] ?? '');
$email = $_SESSION['email'] ?? '';

if ($email === '' || strlen($new) < 8 || $new !== $confirm) {
    $_SESSION['settings_error'] = 'Use at least 8 characters and make sure the new passwords match.';
    zpgc_finish('../pages/' . $returnPage . '?tab=settings', isset($_SESSION['settings_success']), [
        'message' => $_SESSION['settings_success'] ?? $_SESSION['settings_error'] ?? '',
        'action' => 'save_settings',
        'flash' => isset($_SESSION['settings_error']) ? 'error' : 'ok',
    ]);
}

$stmt = $conn->prepare('SELECT password FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($current, $row['password'])) {
    $_SESSION['settings_error'] = 'Current password is not correct.';
    zpgc_finish('../pages/' . $returnPage . '?tab=settings', isset($_SESSION['settings_success']), [
        'message' => $_SESSION['settings_success'] ?? $_SESSION['settings_error'] ?? '',
        'action' => 'save_settings',
        'flash' => isset($_SESSION['settings_error']) ? 'error' : 'ok',
    ]);
}

$hash = password_hash($new, PASSWORD_DEFAULT);
$upd = $conn->prepare('UPDATE users SET password = ? WHERE email = ?');
$upd->bind_param('ss', $hash, $email);
$upd->execute();
$upd->close();

$_SESSION['settings_success'] = 'Password updated.';
zpgc_finish('../pages/' . $returnPage . '?tab=settings', true, [
    'message' => 'Password updated.',
    'action' => 'save_settings',
]);

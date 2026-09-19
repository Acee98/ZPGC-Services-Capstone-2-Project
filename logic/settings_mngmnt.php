<?php
require_once 'session_config.php';
require_once 'config.php';
require_login();

$returnRole = $_SESSION['role'] ?? 'admin';
$returnPage = 'admin.php';
if ($returnRole === 'user') {
    $returnPage = 'user.php';
} elseif ($returnRole === 'techn') {
    $returnPage = 'techn.php';
}

if (isset($_POST['save_appearance'])) {
    $theme = ($_POST['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
    save_ui_theme($theme);
    header('Location: ../pages/' . $returnPage . '?tab=settings');
    exit();
}

if (!isset($_POST['change_password'])) {
    header('Location: ../pages/' . $returnPage . '?tab=settings');
    exit();
}

$current = (string) ($_POST['current_password'] ?? '');
$new = (string) ($_POST['new_password'] ?? '');
$confirm = (string) ($_POST['confirm_password'] ?? '');
$email = $_SESSION['email'] ?? '';

if ($email === '' || strlen($new) < 8 || $new !== $confirm) {
    header('Location: ../pages/' . $returnPage . '?tab=settings');
    exit();
}

$stmt = $conn->prepare('SELECT password FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($current, $row['password'])) {
    header('Location: ../pages/' . $returnPage . '?tab=settings');
    exit();
}

$hash = password_hash($new, PASSWORD_DEFAULT);
$upd = $conn->prepare('UPDATE users SET password = ? WHERE email = ?');
$upd->bind_param('ss', $hash, $email);
$upd->execute();
$upd->close();

header('Location: ../pages/' . $returnPage . '?tab=settings');
exit();

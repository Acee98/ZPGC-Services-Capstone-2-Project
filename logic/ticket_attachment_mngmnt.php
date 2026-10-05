<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_files.php';

$roleEarly = strtolower((string) ($_SESSION['role'] ?? ''));
$ticketEarly = (int) ($_POST['ticket_id'] ?? 0);
$backEarly = zpgc_web_path('pages/user.php') . '?tab=messages&ticket_id=' . $ticketEarly;
if ($roleEarly === 'techn') {
    $backEarly = zpgc_web_path('pages/techn.php') . '?tab=messages&ticket_id=' . $ticketEarly;
} elseif ($roleEarly === 'admin') {
    $backEarly = zpgc_web_path('pages/admin.php') . '?tab=messages&ticket_id=' . $ticketEarly;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['_csrf']) && empty($_FILES['ticket_image']['name'])) {
    $_SESSION['ticket_flash'] = 'Upload failed (file too large or missing). Use JPG/PNG under 2 MB and pick a ticket first.';
    header('Location: ' . $backEarly);
    exit();
}

zpgc_csrf_require();
require_login();

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$userId = current_user_id($conn);
$role = strtolower((string) ($_SESSION['role'] ?? ''));

$back = zpgc_web_path('pages/user.php') . '?tab=messages&ticket_id=' . $ticketId;
if ($role === 'techn') {
    $back = zpgc_web_path('pages/techn.php') . '?tab=messages&ticket_id=' . $ticketId;
} elseif ($role === 'admin') {
    $back = zpgc_web_path('pages/admin.php') . '?tab=messages&ticket_id=' . $ticketId;
}

if ($ticketId <= 0 || $userId <= 0) {
    $_SESSION['ticket_flash'] = 'Choose a ticket before attaching an image.';
    header('Location: ' . $back);
    exit();
}

$allowed = false;
if ($role === 'user') {
    $allowed = ticket_user_can_touch($conn, $ticketId, $userId);
} elseif ($role === 'techn') {
    $allowed = ticket_techn_can_touch($conn, $ticketId, $userId);
} elseif ($role === 'admin') {
    $allowed = true;
}
if (!$allowed) {
    $_SESSION['ticket_flash'] = 'You cannot attach an image to that ticket.';
    header('Location: ' . $back);
    exit();
}

$error = ticket_save_upload($conn, $ticketId, $userId);
$_SESSION['ticket_flash'] = $error !== '' ? $error : 'Image attached to ticket #' . $ticketId . '.';
header('Location: ' . $back);
exit();

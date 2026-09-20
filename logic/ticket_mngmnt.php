<?php
require_once 'session_config.php';
require_once 'config.php';
require_role('user');

if (isset($_POST['submit-ticket'])) {
    $category = trim($_POST['category'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $pri_raw = trim($_POST['priority'] ?? '');
    $priority = in_array($pri_raw, ['critical', 'moderate', 'low'], true) ? $pri_raw : null;

    $allowedCat = ['hardware', 'software', 'account', 'network', 'other'];
    if (!in_array($category, $allowedCat, true)) {
        header('Location: ../pages/ticket.php');
        exit();
    }

    if (!isset($_SESSION['email'])) {
        header('Location: ../pages/login_signup.php');
        exit();
    }
    $email = $_SESSION['email'];
    $find = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $find->bind_param('s', $email);
    $find->execute();
    $found = $find->get_result()->fetch_assoc();

    if (!$found) {
        header('Location: ../pages/ticket.php');
        exit();
    }
    $user_id = (int) $found['id'];

    if ($priority === null) {
        $stmt = $conn->prepare(
            'INSERT INTO tickets (user_id, subject, description, category) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('isss', $user_id, $subject, $description, $category);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO tickets (user_id, subject, description, category, priority) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issss', $user_id, $subject, $description, $category, $priority);
    }
    $stmt->execute();

    header('Location: ../pages/user.php');
    exit();
}
header('Location: ../pages/ticket.php');
exit();

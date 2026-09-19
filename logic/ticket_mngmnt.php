<?php
require_once 'session_config.php';
require_once 'config.php';
require_role('user');

if (isset($_POST['submit-ticket'])) {
    $category = trim($_POST['category']);
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);

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
    $user_id = $found['id'];
    $stmt = $conn->prepare('INSERT INTO tickets (user_id, subject, description, category) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isss', $user_id, $subject, $description, $category);
    $stmt->execute();

    header('Location: ../pages/user.php');
    exit();
}
header('Location: ../pages/ticket.php');
exit();
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(0, '/CP2_V1.2/');
    session_start();
}

function require_login()
{
    if (!isset($_SESSION['email'])) {
        header('Location: ../pages/login_signup.php');
        exit();
    }
}

function require_role($role)
{
    require_login();
    if (($_SESSION['role'] ?? '') !== $role) {
        header('Location: ../pages/login_signup.php');
        exit();
    }
}

function current_user_id($conn)
{
    if (isset($_SESSION['id'])) {
        return (int) $_SESSION['id'];
    }
    if (!isset($_SESSION['email'])) {
        return 0;
    }
    $email = $_SESSION['email'];
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return 0;
    }
    $_SESSION['id'] = (int) $row['id'];
    return (int) $row['id'];
}

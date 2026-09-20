<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/CP2_V1.5/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
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
    $current = strtolower(trim((string) ($_SESSION['role'] ?? '')));
    $need = strtolower(trim((string) $role));
    if ($current !== $need) {
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

function current_ui_theme()
{
    $theme = $_SESSION['theme'] ?? ($_COOKIE['zpgc_theme'] ?? 'light');
    return $theme === 'dark' ? 'dark' : 'light';
}

function save_ui_theme($theme)
{
    $theme = $theme === 'dark' ? 'dark' : 'light';
    $_SESSION['theme'] = $theme;
    setcookie('zpgc_theme', $theme, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => '/CP2_V1.5/',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

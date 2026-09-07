<?php
session_set_cookie_params(0, 'CP2_V1.1/logic/');
session_start();

require_once 'config.php';

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare(
        'SELECT first_name, last_name, email, password, role, status
        FROM users WHERE email = ?'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] !== 'active') {
            $_SESSION['login_error'] = 'Account is not activated yet.';
            header('Location: ../pages/login_signup.php');
            exit();
        }
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] == 'admin') {
            header('Location: ../pages/admin.php');
        } elseif ($user['role'] == 'techn') {
            header('Location: ../pages/techn.php');
        } else {
            header('Location: ../pages/user.php');
        }
        exit();
    }
    $_SESSION['login_error'] = 'Incorrect credentials.';
    header('Location: ../pages/login_signup.php');
    exit();
}

if (isset($_POST['signup'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    $allowed = array('user', 'admin', 'techn');
    if (!in_array($role, $allowed, true)) {
        header('Location: ../pages/login_signup.php?form=signup');
        exit();
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $status = 'inactive';

    $stmt = $conn->prepare(
        'INSERT INTO users (first_name, last_name, email, password, role, status)
        VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('ssssss', $first_name, $last_name, $email, $hash, $role, $status);

    if ($stmt->execute()) {
        header('Location: ../pages/login_signup.php');
        exit();
    }
    header('Location: ../pages/login_signup.php?form=signup');
}
header('Location: ../pages/login_signup.php');
exit();
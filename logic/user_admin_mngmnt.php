<?php
session_set_cookie_params(0, 'CP2_V1.1/logic/');
session_start();

require_once 'config.php';

if (isset($_POST['set_status'])) {
    $id = (int) $_POST['id'];
    $status = $_POST['status'];

    if ($id <= 0 || $status !== 'active') {
        header('Location: ../pages/admin.php');
        exit();
    }
    $stmt = $conn->prepare('UPDATE users SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();
    $stmt->close();

    header('Location: ../pages/admin.php');
    exit();
}
header('Location: ../pages/admin.php');
exit();
?>
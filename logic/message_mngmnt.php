<?php
require_once 'session_config.php';
require_once 'config.php';
require_login();

function user_can_access_ticket($conn, $ticket_id, $user_id, $role)
{
    $stmt = $conn->prepare(
        'SELECT user_id, assigned_to FROM tickets WHERE id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $ticket_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return false;
    }
    if ($role === 'admin') {
        return true;
    }
    if ($role === 'user') {
        return (int) $row['user_id'] === $user_id;
    }
    if ($role === 'techn') {
        return (int) $row['assigned_to'] === $user_id;
    }
    return false;
}

$me = current_user_id($conn);
$role = $_SESSION['role'] ?? '';

if (isset($_POST['send_message'])) {
    $ticket_id = (int) ($_POST['ticket_id'] ?? 0);
    $body = trim($_POST['body'] ?? '');
    if ($ticket_id > 0 && $body !== '' && user_can_access_ticket($conn, $ticket_id, $me, $role)) {
        $stmt = $conn->prepare(
            'INSERT INTO messages (ticket_id, sender_id, body) VALUES (?, ?, ?)'
        );
        $stmt->bind_param('iis', $ticket_id, $me, $body);
        $stmt->execute();
        $stmt->close();
    }
    $back = '../pages/user.php?tab=messages';
    if ($role === 'admin') {
        $back = '../pages/admin.php?tab=messages';
    } elseif ($role === 'techn') {
        $back = '../pages/techn.php?tab=messages';
    }
    if ($ticket_id > 0) {
        $back .= '&ticket_id=' . $ticket_id;
    }
    header('Location: ' . $back);
    exit();
}
header('Location: ../pages/user.php');
exit();

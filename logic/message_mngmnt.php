<?php
require_once 'session_config.php';
require_once 'config.php';
zpgc_csrf_require();
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
    if ($role === 'admin') {
        $_SESSION['ticket_flash'] = 'Only the user and the assigned technician can send messages.';
        header('Location: ' . zpgc_web_path('pages/admin.php') . '?tab=messages');
        exit();
    }
    $ticket_id = (int) ($_POST['ticket_id'] ?? 0);
    $body = trim($_POST['body'] ?? '');
    if ($ticket_id > 0 && $body !== '' && user_can_access_ticket($conn, $ticket_id, $me, $role)) {
        $stmt = $conn->prepare(
            'INSERT INTO messages (ticket_id, sender_id, body) VALUES (?, ?, ?)'
        );
        if (!$stmt) {
            $_SESSION['ticket_flash'] = 'Could not send that message. Try again.';
        } else {
            $stmt->bind_param('iis', $ticket_id, $me, $body);
            if (!$stmt->execute()) {
                $_SESSION['ticket_flash'] = 'Could not send that message. Try again.';
            }
            $stmt->close();
        }
    } else {
        $_SESSION['ticket_flash'] = $ticket_id <= 0
            ? 'Select a ticket before sending a message.'
            : ($body === '' ? 'Message cannot be empty.' : 'You cannot message that ticket.');
    }
    $back = zpgc_web_path('pages/user.php') . '?tab=messages';
    if ($role === 'admin') {
        $back = zpgc_web_path('pages/admin.php') . '?tab=messages';
    } elseif ($role === 'techn') {
        $back = zpgc_web_path('pages/techn.php') . '?tab=messages';
    }
    if ($ticket_id > 0) {
        $back .= '&ticket_id=' . $ticket_id;
    }
    header('Location: ' . $back);
    exit();
}
header('Location: ' . zpgc_web_path('pages/user.php'));
exit();

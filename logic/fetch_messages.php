<?php
require_once 'session_config.php';
require_once 'config.php';
require_login();
header('Content-Type: application/json');

$me = current_user_id($conn);
$role = $_SESSION['role'] ?? '';
$ticket_id = (int) ($_GET['ticket_id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT user_id, assigned_to FROM tickets WHERE id = ? LIMIT 1'
);
$stmt->bind_param('i', $ticket_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

$allowed = false;
if ($ticket) {
    if ($role === 'admin') {
        $allowed = true;
    } elseif ($role === 'user' && (int) $ticket['user_id'] === $me) {
        $allowed = true;
    } elseif ($role === 'techn' && (int) $ticket['assigned_to'] === $me) {
        $allowed = true;
    }
}

if (!$allowed) {
    echo json_encode(['ok' => false, 'messages' => []]);
    exit();
}

$q = $conn->prepare(
    'SELECT m.id, m.sender_id, m.body, m.created_at, u.first_name, u.last_name
     FROM messages m
     INNER JOIN users u ON u.id = m.sender_id
     WHERE m.ticket_id = ?
     ORDER BY m.id ASC'
);
$q->bind_param('i', $ticket_id);
$q->execute();
$res = $q->get_result();
$messages = [];
while ($row = $res->fetch_assoc()) {
    $messages[] = [
        'id' => (int) $row['id'],
        'sender_id' => (int) $row['sender_id'],
        'mine' => (int) $row['sender_id'] === $me,
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'body' => $row['body'],
        'created_at' => $row['created_at'],
    ];
}
$q->close();
echo json_encode(['ok' => true, 'messages' => $messages]);

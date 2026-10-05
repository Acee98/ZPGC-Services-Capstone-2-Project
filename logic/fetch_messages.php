<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_files.php';
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
    echo json_encode(['ok' => false, 'timeline' => [], 'messages' => [], 'attachments' => []]);
    exit();
}

// Schema ensure is cached inside ticket_files_ready (not every poll).
ticket_files_ready($conn);

$timeline = [];
$messages = [];
$attachments = [];

// One stream ordered by send/upload time only (not by category).
$sql = '(
    SELECT
        \'message\' AS item_type,
        m.id AS item_id,
        m.created_at AS sent_at,
        UNIX_TIMESTAMP(m.created_at) AS sort_ts,
        m.id AS sort_id,
        m.sender_id AS actor_id,
        m.body AS body_text,
        NULL AS file_name,
        u.first_name,
        u.last_name
    FROM messages m
    INNER JOIN users u ON u.id = m.sender_id
    WHERE m.ticket_id = ?
)
UNION ALL
(
    SELECT
        \'attachment\' AS item_type,
        a.id AS item_id,
        a.created_at AS sent_at,
        UNIX_TIMESTAMP(a.created_at) AS sort_ts,
        a.id AS sort_id,
        a.uploaded_by AS actor_id,
        NULL AS body_text,
        a.original_name AS file_name,
        u.first_name,
        u.last_name
    FROM ticket_attachments a
    INNER JOIN users u ON u.id = a.uploaded_by
    WHERE a.ticket_id = ?
)
ORDER BY sort_ts ASC, sort_id ASC';

$q = $conn->prepare($sql);
if (!$q) {
    echo json_encode(['ok' => false, 'timeline' => [], 'error' => 'timeline query failed']);
    exit();
}
$q->bind_param('ii', $ticket_id, $ticket_id);
$q->execute();
$res = $q->get_result();
while ($row = $res->fetch_assoc()) {
    $type = (string) ($row['item_type'] ?? 'message');
    $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
    $sentAt = (string) ($row['sent_at'] ?? '');
    $sortTs = (int) ($row['sort_ts'] ?? 0);
    if ($type === 'attachment') {
        $attId = (int) $row['item_id'];
        $item = [
            'type' => 'attachment',
            'id' => $attId,
            'name' => (string) ($row['file_name'] ?? 'image'),
            'sender' => $name,
            'mine' => (int) ($row['actor_id'] ?? 0) === $me,
            'created_at' => $sentAt,
            'sort_ts' => $sortTs,
            // Server-built URL (works on Azure root and local /CP2_V1.6).
            'url' => zpgc_web_path('logic/ticket_image.php') . '?id=' . $attId . '&size=full',
            'thumb_url' => zpgc_web_path('logic/ticket_image.php') . '?id=' . $attId . '&size=thumb',
        ];
        $attachments[] = $item;
        $timeline[] = $item;
    } else {
        $item = [
            'type' => 'message',
            'id' => (int) $row['item_id'],
            'sender_id' => (int) ($row['actor_id'] ?? 0),
            'mine' => (int) ($row['actor_id'] ?? 0) === $me,
            'name' => $name,
            'body' => (string) ($row['body_text'] ?? ''),
            'created_at' => $sentAt,
            'sort_ts' => $sortTs,
        ];
        $messages[] = $item;
        $timeline[] = $item;
    }
}
$q->close();

echo json_encode([
    'ok' => true,
    'timeline' => $timeline,
    'messages' => $messages,
    'attachments' => $attachments,
]);

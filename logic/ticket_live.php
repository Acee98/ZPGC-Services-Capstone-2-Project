<?php

require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_status.php';
require_once 'ticket_times.php';
require_once 'ticket_retention.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$role = (string) ($_SESSION['role'] ?? '');
if (!in_array($role, ['user', 'techn', 'admin'], true)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit();
}

if (!zpgc_rate_limit('ticket_live', 40, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'rate']);
    exit();
}

$userId = current_user_id($conn);
if ($userId < 1) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit();
}

ticket_retention_maybe_auto_purge($conn);

$cols = 'id, status, priority';
if (ticket_has_column($conn, 'assigned_to')) {
    $cols .= ', assigned_to';
}

$tickets = [];
if ($role === 'user') {
    $stmt = $conn->prepare("SELECT {$cols} FROM tickets WHERE user_id = ? ORDER BY id DESC LIMIT 200");
    $stmt->bind_param('i', $userId);
} elseif ($role === 'techn') {
    $stmt = $conn->prepare("SELECT {$cols} FROM tickets WHERE assigned_to = ? ORDER BY id DESC LIMIT 200");
    $stmt->bind_param('i', $userId);
} else {
    $stmt = $conn->prepare("SELECT {$cols} FROM tickets ORDER BY id DESC LIMIT 300");
}

if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $st = (string) ($row['status'] ?? '');
        $tickets[] = [
            'id' => (int) $row['id'],
            'status' => $st,
            'status_label' => ticket_status_label($st),
            'status_class' => ticket_status_class($st),
            'priority' => strtolower(trim((string) ($row['priority'] ?? ''))),
        ];
    }
    $stmt->close();
}

$counts = [
    'pending' => 0,
    'ongoing' => 0,
    'processing' => 0,
    'awaiting_confirmation' => 0,
    'resolved' => 0,
];
$cntRes = $conn->query('SELECT status, COUNT(*) AS c FROM tickets GROUP BY status');
if ($cntRes) {
    while ($row = $cntRes->fetch_assoc()) {
        $key = (string) ($row['status'] ?? '');
        if (isset($counts[$key])) {
            $counts[$key] = (int) $row['c'];
        }
    }
}

echo json_encode([
    'ok' => true,
    'tickets' => $tickets,
    'counts' => $counts,
], JSON_UNESCAPED_UNICODE);
exit();

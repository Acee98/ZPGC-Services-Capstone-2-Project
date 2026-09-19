<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_status.php';
require_role('techn');

if (!isset($_POST['save_tech_ticket'])) {
    header('Location: ../pages/techn.php?tab=tickets');
    exit();
}

$ticket_id = (int) ($_POST['ticket_id'] ?? 0);
$status = (string) ($_POST['status'] ?? '');
$tech_id = current_user_id($conn);
$allowed = ticket_techn_allowed_statuses();

if ($ticket_id <= 0 || $tech_id <= 0 || !in_array($status, $allowed, true)) {
    header('Location: ../pages/techn.php?tab=tickets');
    exit();
}

$check = $conn->prepare(
    'SELECT id FROM tickets WHERE id = ? AND assigned_to = ? LIMIT 1'
);
$check->bind_param('ii', $ticket_id, $tech_id);
$check->execute();
$owned = $check->get_result()->fetch_assoc();
$check->close();

if (!$owned) {
    header('Location: ../pages/techn.php?tab=tickets');
    exit();
}

$upd = $conn->prepare('UPDATE tickets SET status = ? WHERE id = ? AND assigned_to = ?');
$upd->bind_param('sii', $status, $ticket_id, $tech_id);
$upd->execute();
$upd->close();

header('Location: ../pages/techn.php?tab=tickets');
exit();

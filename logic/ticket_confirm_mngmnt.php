<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_status.php';
require_role('user');

if (!isset($_POST['confirm_ticket'])) {
    header('Location: ../pages/user.php?tab=tickets');
    exit();
}

$ticket_id = (int) ($_POST['ticket_id'] ?? 0);
$decision = (string) ($_POST['decision'] ?? '');
$user_id = current_user_id($conn);

if ($ticket_id <= 0 || $user_id <= 0 || !in_array($decision, ['solved', 'not_solved'], true)) {
    header('Location: ../pages/user.php?tab=tickets');
    exit();
}

$stmt = $conn->prepare(
    'SELECT id, status FROM tickets WHERE id = ? AND user_id = ? LIMIT 1'
);
$stmt->bind_param('ii', $ticket_id, $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !ticket_awaiting_confirmation($row['status'])) {
    $_SESSION['confirm_error'] = 'That ticket is not waiting on your confirmation.';
    header('Location: ../pages/user.php?tab=tickets');
    exit();
}

if ($decision === 'solved') {
    $newStatus = 'resolved';
} else {
    $newStatus = 'ongoing';
}

$upd = $conn->prepare('UPDATE tickets SET status = ? WHERE id = ? AND user_id = ?');
$upd->bind_param('sii', $newStatus, $ticket_id, $user_id);
$upd->execute();
$upd->close();

$_SESSION['confirm_success'] = $decision === 'solved'
    ? 'Ticket marked Solved. Thank you.'
    : 'Ticket sent back as Not Solved Yet.';
header('Location: ../pages/user.php?tab=tickets');
exit();

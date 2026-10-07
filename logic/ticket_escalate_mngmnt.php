<?php

require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_assign.php';
require_once 'ticket_times.php';
require_role('user');
zpgc_csrf_require();

if (!isset($_POST['request_technician']) && !isset($_POST['self_help_solved'])) {
    header('Location: ../pages/user.php?tab=tickets');
    exit();
}

$ticket_id = (int) ($_POST['ticket_id'] ?? 0);
$user_id = current_user_id($conn);

if ($ticket_id <= 0 || $user_id <= 0) {
    header('Location: ../pages/user.php?tab=tickets');
    exit();
}

$stmt = $conn->prepare(
    'SELECT id, priority, assigned_to, status FROM tickets WHERE id = ? AND user_id = ? LIMIT 1'
);
$stmt->bind_param('ii', $ticket_id, $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $_SESSION['confirm_error'] = 'Ticket not found.';
    header('Location: ../pages/user.php?tab=tickets');
    exit();
}

if (($row['status'] ?? '') === 'resolved') {
    $_SESSION['confirm_error'] = 'This ticket is already resolved.';
    header('Location: ../pages/user.php?tab=tickets');
    exit();
}

if (isset($_POST['self_help_solved'])) {
    $resolved = 'resolved';
    $upd = $conn->prepare(
        'UPDATE tickets SET status = ? WHERE id = ? AND user_id = ? AND status <> ?'
    );
    $upd->bind_param('siis', $resolved, $ticket_id, $user_id, $resolved);
    $upd->execute();
    $upd->close();
    // Defer soft-archive until CSAT is submitted (same path as confirm Solved).
    ticket_mark_resolved($conn, $ticket_id, false);
    $_SESSION['rate_ticket_id'] = $ticket_id;
    $_SESSION['confirm_success'] = 'Ticket #' . $ticket_id . ' marked resolved from the troubleshooting steps. Please rate this visit.';
    header('Location: ../pages/user.php?tab=tickets');
    exit();
}

if (!empty($row['assigned_to'])) {
    $_SESSION['confirm_success'] = 'Still not fixed — a technician already has ticket #' . $ticket_id . ' and will follow up.';
    header('Location: ../pages/user.php?tab=tickets');
    exit();
}

$tech = ticket_auto_assign($conn, $ticket_id, 'ongoing');
if ($tech) {
    $_SESSION['confirm_success'] = 'Technician assigned to ticket #' . $ticket_id . '. They will follow up soon.';
} else {
    $_SESSION['confirm_error'] = 'No active technician is available right now. An admin can assign one later.';
}

header('Location: ../pages/user.php?tab=tickets');
exit();

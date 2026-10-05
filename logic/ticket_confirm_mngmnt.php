<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_status.php';
require_once 'ticket_times.php';
require_once 'auth_mail.php';
require_role('user');
zpgc_csrf_require();

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
if ($newStatus === 'resolved') {
    // Defer soft-archive until CSAT is submitted so the survey can always resolve the row.
    ticket_mark_resolved($conn, $ticket_id, false);
    $_SESSION['rate_ticket_id'] = $ticket_id;
}

$_SESSION['confirm_success'] = $decision === 'solved'
    ? 'Ticket marked Solved. Please rate this visit.'
    : 'Ticket sent back as Not Solved Yet.';
if ($decision === 'solved') {
    notify_user_email(
        $conn,
        $user_id,
        'Ticket #' . $ticket_id . ' resolved',
        "You marked ticket #{$ticket_id} as Solved. Thank you."
    );
} else {
    $techStmt = $conn->prepare('SELECT assigned_to FROM tickets WHERE id = ? LIMIT 1');
    $techStmt->bind_param('i', $ticket_id);
    $techStmt->execute();
    $techRow = $techStmt->get_result()->fetch_assoc();
    $techStmt->close();
    $techId = (int) ($techRow['assigned_to'] ?? 0);
    if ($techId > 0) {
        notify_user_email(
            $conn,
            $techId,
            'Ticket #' . $ticket_id . ' reopened',
            "The user marked ticket #{$ticket_id} as Not Solved Yet. Please continue work."
        );
    }
}
header('Location: ../pages/user.php?tab=tickets');
exit();

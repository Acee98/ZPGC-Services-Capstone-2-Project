<?php

require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_assign.php';
require_once 'ticket_times.php';
require_once 'auth_mail.php';
require_role('user');

$ticketsHome = '../pages/user.php?tab=tickets';
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Location: ' . $ticketsHome, true, 303);
    exit();
}

zpgc_csrf_require();

if (!isset($_POST['request_technician']) && !isset($_POST['self_help_solved'])) {
    header('Location: ' . $ticketsHome, true, 303);
    exit();
}

$ticket_id = (int) ($_POST['ticket_id'] ?? 0);
$user_id = current_user_id($conn);

if ($ticket_id <= 0 || $user_id <= 0) {
    $_SESSION['confirm_error'] = 'Could not update that ticket. Sign in again and retry.';
    header('Location: ' . $ticketsHome, true, 303);
    exit();
}

$stmt = $conn->prepare(
    'SELECT id, subject, priority, assigned_to, status FROM tickets WHERE id = ? AND user_id = ? LIMIT 1'
);
$stmt->bind_param('ii', $ticket_id, $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $_SESSION['confirm_error'] = 'Ticket not found.';
    header('Location: ' . $ticketsHome, true, 303);
    exit();
}

if (($row['status'] ?? '') === 'resolved') {
    $_SESSION['confirm_error'] = 'This ticket is already resolved.';
    header('Location: ' . $ticketsHome, true, 303);
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
    ticket_mark_resolved($conn, $ticket_id, false);
    $_SESSION['rate_ticket_id'] = $ticket_id;
    $_SESSION['confirm_success'] = 'Ticket #' . $ticket_id . ' marked resolved from the troubleshooting steps. Please rate this visit.';
    header('Location: ' . $ticketsHome, true, 303);
    exit();
}

$techId = (int) ($row['assigned_to'] ?? 0);
if ($techId <= 0) {
    $assigned = ticket_auto_assign($conn, $ticket_id, 'ongoing');
    $techId = $assigned ? (int) $assigned : 0;
} else {
    $ongoing = 'ongoing';
    $upd = $conn->prepare(
        "UPDATE tickets SET status = ? WHERE id = ? AND status IN ('pending','ongoing')"
    );
    $upd->bind_param('si', $ongoing, $ticket_id);
    $upd->execute();
    $upd->close();
}

if (function_exists('ticket_has_column') && ticket_has_column($conn, 'ai_guidance')) {
    $clear = $conn->prepare('UPDATE tickets SET ai_guidance = NULL WHERE id = ?');
    if ($clear) {
        $clear->bind_param('i', $ticket_id);
        $clear->execute();
        $clear->close();
    }
}

if ($techId > 0) {
    $_SESSION['confirm_success'] = 'Still not fixed. Ticket #' . $ticket_id
        . ' was sent to your technician and the troubleshooting tips were closed.';
} else {
    $_SESSION['confirm_error'] = 'No active technician is available right now. An admin can assign one later.';
}

header('Location: ' . $ticketsHome, true, 303);

if ($techId > 0) {
    $subj = (string) ($row['subject'] ?? '');
    $mailTech = $techId;
    zpgc_after_response(static function () use ($conn, $mailTech, $ticket_id, $subj) {
        notify_user_email(
            $conn,
            $mailTech,
            'Ticket #' . $ticket_id . ' still needs help',
            "The user reported that troubleshooting steps did not fix ticket #{$ticket_id}.\nSubject: {$subj}\nPlease follow up."
        );
    });
}
exit();

<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_status.php';
require_once 'ticket_files.php';
require_once 'auth_mail.php';
require_role('techn');
zpgc_csrf_require();

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
if (!$upd->execute()) {
    $upd->close();
    $_SESSION['ticket_flash'] = 'Could not update ticket #' . $ticket_id . '.';
    header('Location: ../pages/techn.php?tab=tickets');
    exit();
}
$upd->close();

$_SESSION['ticket_flash'] = 'Ticket #' . $ticket_id . ' updated to ' . ticket_status_label($status) . '.';

if ($status === 'awaiting_confirmation') {
    $info = $conn->prepare('SELECT user_id, subject FROM tickets WHERE id = ? LIMIT 1');
    $info->bind_param('i', $ticket_id);
    $info->execute();
    $ticket = $info->get_result()->fetch_assoc();
    $info->close();
    if ($ticket) {
        notify_user_email(
            $conn,
            (int) $ticket['user_id'],
            'Ticket #' . $ticket_id . ' needs your confirmation',
            "Ticket #{$ticket_id} is ready for your confirmation.\nSubject: " . $ticket['subject']
            . "\nOpen Tickets and choose Solved or Not Solved Yet."
        );
    }
}

if (isset($_FILES['ticket_image']) && (int) ($_FILES['ticket_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $error = ticket_save_upload($conn, $ticket_id, $tech_id);
    $_SESSION['ticket_flash'] = $error !== ''
        ? $error
        : ('Ticket #' . $ticket_id . ' updated to ' . ticket_status_label($status) . ' and image attached.');
}

header('Location: ../pages/techn.php?tab=tickets');
exit();

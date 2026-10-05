<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_times.php';
require_once 'ticket_files.php';
require_role('user');
zpgc_csrf_require();

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$userId = current_user_id($conn);
if ($ticketId <= 0 || $userId <= 0) {
    $_SESSION['ticket_flash'] = 'Choose a ticket to delete.';
    header('Location: ../pages/user.php?tab=tickets');
    exit();
}

$owned = $conn->prepare('SELECT id, status FROM tickets WHERE id = ? AND user_id = ? LIMIT 1');
$owned->bind_param('ii', $ticketId, $userId);
$owned->execute();
$found = $owned->get_result()->fetch_assoc();
$owned->close();
if (!$found) {
    $_SESSION['ticket_flash'] = 'That ticket is not yours.';
    header('Location: ../pages/user.php?tab=tickets');
    exit();
}
// Resolved tickets are soft-archived (kept for Performance), not hard-deleted.
if (($found['status'] ?? '') === 'resolved') {
    ticket_mark_archived($conn, $ticketId);
    $_SESSION['ticket_flash'] = 'Ticket #' . $ticketId . ' was archived from your active list.';
    header('Location: ../pages/user.php?tab=tickets');
    exit();
}

$conn->query('DELETE FROM messages WHERE ticket_id = ' . $ticketId);
if ($conn->query("SHOW TABLES LIKE 'ticket_attachments'") && $conn->query("SHOW TABLES LIKE 'ticket_attachments'")->num_rows > 0) {
    $files = $conn->query('SELECT stored_name FROM ticket_attachments WHERE ticket_id = ' . $ticketId);
    if ($files) {
        while ($file = $files->fetch_assoc()) {
            ticket_unlink_stored((string) $file['stored_name']);
        }
    }
    $conn->query('DELETE FROM ticket_attachments WHERE ticket_id = ' . $ticketId);
}
$delete = $conn->prepare('DELETE FROM tickets WHERE id = ? AND user_id = ?');
$delete->bind_param('ii', $ticketId, $userId);
$delete->execute();
$delete->close();

$_SESSION['ticket_flash'] = 'Ticket #' . $ticketId . ' was deleted.';
header('Location: ../pages/user.php?tab=tickets');
exit();

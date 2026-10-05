<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_times.php';
require_role('user');
zpgc_csrf_require();

$labels = [
    5 => 'Very satisfied',
    4 => 'Satisfied',
    3 => 'Not sure',
    2 => 'Not satisfied',
    1 => 'Hate it',
];
$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$score = (int) ($_POST['satisfaction'] ?? 0);
$userId = current_user_id($conn);
if ($ticketId <= 0 || !isset($labels[$score]) || $userId <= 0) {
    $_SESSION['ticket_flash'] = 'Choose a rating from the list.';
    header('Location: ../pages/user.php?tab=tickets');
    exit();
}

if (!ticket_ensure_satisfaction_column($conn)) {
    $_SESSION['ticket_flash'] = 'Satisfaction ratings are unavailable until the database migration is applied.';
    unset($_SESSION['rate_ticket_id']);
    header('Location: ../pages/user.php?tab=tickets');
    exit();
}

$stmt = $conn->prepare(
    "UPDATE tickets SET satisfaction = ? WHERE id = ? AND user_id = ? AND status = 'resolved'
     AND (satisfaction IS NULL OR satisfaction < 1 OR satisfaction > 5)"
);
$stmt->bind_param('iii', $score, $ticketId, $userId);
$stmt->execute();
$saved = $stmt->affected_rows > 0;
$stmt->close();
if ($saved) {
    ticket_mark_archived($conn, $ticketId);
}
$_SESSION['ticket_flash'] = $saved
    ? 'Thanks. Ticket #' . $ticketId . ' was marked ' . $labels[$score] . ' and archived from your active list.'
    : 'Only a resolved ticket that you own can be rated once.';
unset($_SESSION['rate_ticket_id']);
header('Location: ../pages/user.php?tab=tickets');
exit();

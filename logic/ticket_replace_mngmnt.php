<?php
require_once 'session_config.php';
require_once 'config.php';
require_role('techn');
zpgc_csrf_require();

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$techId = current_user_id($conn);
if (!function_exists('zpgc_runtime_ddl_allowed') || zpgc_runtime_ddl_allowed()) {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS replacement_requests (
            id INT(11) NOT NULL AUTO_INCREMENT,
            ticket_id INT(11) NOT NULL,
            techn_id INT(11) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_replace_ticket (ticket_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}
$check = $conn->prepare('SELECT id, subject FROM tickets WHERE id = ? AND assigned_to = ? LIMIT 1');
$check->bind_param('ii', $ticketId, $techId);
$check->execute();
$ticket = $check->get_result()->fetch_assoc();
$check->close();
if ($ticket && is_file(__DIR__ . '/ticket_crypto.php')) {
    require_once __DIR__ . '/ticket_crypto.php';
    ticket_reveal_row($ticket);
}
if (!$ticket) {
    $_SESSION['ticket_flash'] = 'That ticket is not assigned to you.';
    zpgc_finish('../pages/techn.php?tab=tickets', true, ['message' => $_SESSION['ticket_flash'] ?? '', 'action' => 'replace_me']);
}
$exists = $conn->prepare(
    "SELECT id FROM replacement_requests WHERE ticket_id = ? AND techn_id = ? AND status = 'pending' LIMIT 1"
);
if (!$exists) {
    $_SESSION['ticket_flash'] = 'Replacement requests are unavailable (database table missing). Ask an admin to run migrations.';
    zpgc_finish('../pages/techn.php?tab=tickets', true, ['message' => $_SESSION['ticket_flash'] ?? '', 'action' => 'replace_me']);
}
$exists->bind_param('ii', $ticketId, $techId);
$exists->execute();
$already = $exists->get_result()->fetch_assoc();
$exists->close();
if ($already) {
    $_SESSION['ticket_flash'] = 'The admin was already asked to replace you on ticket #' . $ticketId . '.';
    zpgc_finish('../pages/techn.php?tab=tickets', true, ['message' => $_SESSION['ticket_flash'] ?? '', 'action' => 'replace_me']);
}
$insert = $conn->prepare(
    "INSERT INTO replacement_requests (ticket_id, techn_id, status) VALUES (?, ?, 'pending')"
);
if (!$insert) {
    $_SESSION['ticket_flash'] = 'Replacement requests are unavailable (database table missing). Ask an admin to run migrations.';
    zpgc_finish('../pages/techn.php?tab=tickets', true, ['message' => $_SESSION['ticket_flash'] ?? '', 'action' => 'replace_me']);
}
$insert->bind_param('ii', $ticketId, $techId);
if (!$insert->execute()) {
    $insert->close();
    $_SESSION['ticket_flash'] = 'Could not send the replacement request. Try again.';
    zpgc_finish('../pages/techn.php?tab=tickets', true, ['message' => $_SESSION['ticket_flash'] ?? '', 'action' => 'replace_me']);
}
$insert->close();
$_SESSION['ticket_flash'] = 'The admin was notified to assign another technician for ticket #' . $ticketId . '.';
zpgc_finish('../pages/techn.php?tab=tickets', true, [
    'message' => $_SESSION['ticket_flash'],
    'action' => 'replace_me',
    'ticket_id' => $ticketId,
]);

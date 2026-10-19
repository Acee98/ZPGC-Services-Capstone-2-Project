<?php

require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_assign.php';
require_once 'ticket_times.php';
require_once 'auth_mail.php';
require_role('user');

$ticketsHome = '../pages/user.php?tab=tickets';
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    zpgc_finish($ticketsHome, false, ['message' => $_SESSION['confirm_error'] ?? $_SESSION['confirm_success'] ?? '']);
}

zpgc_csrf_require();

if (!isset($_POST['request_technician']) && !isset($_POST['self_help_solved'])) {
    zpgc_finish($ticketsHome, false, ['message' => $_SESSION['confirm_error'] ?? $_SESSION['confirm_success'] ?? '']);
}

$ticket_id = (int) ($_POST['ticket_id'] ?? 0);
$user_id = current_user_id($conn);

if ($ticket_id <= 0 || $user_id <= 0) {
    $_SESSION['confirm_error'] = 'Could not update that ticket. Sign in again and retry.';
    zpgc_finish($ticketsHome, false, ['message' => $_SESSION['confirm_error'] ?? $_SESSION['confirm_success'] ?? '']);
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
    zpgc_finish($ticketsHome, false, ['message' => $_SESSION['confirm_error'] ?? $_SESSION['confirm_success'] ?? '']);
}

if (($row['status'] ?? '') === 'resolved') {
    $_SESSION['confirm_error'] = 'This ticket is already resolved.';
    zpgc_finish($ticketsHome, false, ['message' => $_SESSION['confirm_error'] ?? $_SESSION['confirm_success'] ?? '']);
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
    zpgc_finish($ticketsHome, true, [
        'message' => $_SESSION['confirm_success'],
        'action' => 'self_help_solved',
        'ticket_id' => $ticket_id,
        'status' => 'resolved',
    ]);
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

$ok = $techId > 0;
$payload = [
    'message' => (string) ($_SESSION['confirm_success'] ?? $_SESSION['confirm_error'] ?? ''),
    'action' => 'request_technician',
    'ticket_id' => $ticket_id,
];
$mailFn = null;
if ($techId > 0) {
    $subj = (string) ($row['subject'] ?? '');
    if (is_file(__DIR__ . '/ticket_crypto.php')) {
        require_once __DIR__ . '/ticket_crypto.php';
        $subj = ticket_decrypt_text($subj);
    }
    $mailTech = $techId;
    $mailFn = static function () use ($conn, $mailTech, $ticket_id, $subj) {
        notify_user_email(
            $conn,
            $mailTech,
            'Ticket #' . $ticket_id . ' still needs help',
            "The user reported that troubleshooting steps did not fix ticket #{$ticket_id}.\nSubject: {$subj}\nPlease follow up."
        );
    };
}
if (zpgc_wants_json()) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    echo json_encode(array_merge(['ok' => $ok], $payload), JSON_UNESCAPED_UNICODE);
    if (is_callable($mailFn)) {
        zpgc_after_response($mailFn);
    }
    exit();
}
header('Location: ' . $ticketsHome, true, 303);
if (is_callable($mailFn)) {
    zpgc_after_response($mailFn);
}
exit();

<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_status.php';
require_once 'ticket_times.php';
require_once 'audit_log.php';
require_once 'auth_mail.php';
require_role('admin');
zpgc_csrf_require();

$allowed = ticket_admin_allowed_statuses();

if (isset($_POST['save_ticket'])) {
    $ticket_id = (int) ($_POST['ticket_id'] ?? 0);
    $assigned_raw = $_POST['assigned_to'] ?? '';
    $status = $_POST['status'] ?? 'pending';

    $pri_raw = $_POST['priority'] ?? '';
    $priority = in_array($pri_raw, ['critical', 'moderate', 'low'], true) ? $pri_raw : null;

    if ($ticket_id <= 0 || !in_array($status, $allowed, true)) {
        header('Location: ../pages/admin.php?tab=tickets');
        exit();
    }

    $prevAssigned = 0;
    $prevStmt = $conn->prepare('SELECT assigned_to FROM tickets WHERE id = ? LIMIT 1');
    $prevStmt->bind_param('i', $ticket_id);
    $prevStmt->execute();
    $prevRow = $prevStmt->get_result()->fetch_assoc();
    $prevStmt->close();
    if ($prevRow) {
        $prevAssigned = (int) ($prevRow['assigned_to'] ?? 0);
    }

    if ($assigned_raw === '' || $assigned_raw === '0') {
        $stmt = $conn->prepare(
            'UPDATE tickets SET assigned_to = NULL, status = ?, priority = ? WHERE id = ?'
        );
        $stmt->bind_param('ssi', $status, $priority, $ticket_id);
    } else {
        $assigned_to = (int) $assigned_raw;
        $check = $conn->prepare(
            "SELECT id FROM users WHERE id = ? AND role = 'techn' AND status = 'active' LIMIT 1"
        );
        $check->bind_param('i', $assigned_to);
        $check->execute();
        $ok = $check->get_result()->fetch_assoc();
        $check->close();
        if (!$ok) {
            header('Location: ../pages/admin.php?tab=tickets');
            exit();
        }
        $stmt = $conn->prepare(
            'UPDATE tickets SET assigned_to = ?, status = ?, priority = ? WHERE id = ?'
        );
        $stmt->bind_param('issi', $assigned_to, $status, $priority, $ticket_id);
    }
    $stmt->execute();
    $stmt->close();
    if ($assigned_raw !== '' && $assigned_raw !== '0') {
        ticket_mark_responded($conn, $ticket_id);
    }

    // Any admin save clears pending replace requests for this ticket
    // (reassignment or explicit save after the notice).
    $replaceTable = $conn->query("SHOW TABLES LIKE 'replacement_requests'");
    if ($replaceTable && $replaceTable->num_rows > 0) {
        $clear = $conn->prepare(
            "UPDATE replacement_requests
             SET status = 'resolved'
             WHERE ticket_id = ? AND status = 'pending'"
        );
        if ($clear) {
            $clear->bind_param('i', $ticket_id);
            $clear->execute();
            $clear->close();
        }
    }
    if ($status === 'resolved') {
        // Defer soft-archive so the reporter can still submit CSAT (History / Rate visit).
        ticket_mark_resolved($conn, $ticket_id, false);
    }
    audit_write($conn, 'save_ticket', $ticket_id, 'Saved ticket #' . $ticket_id . ' status ' . $status . '.');

    $info = $conn->prepare('SELECT user_id, assigned_to, subject FROM tickets WHERE id = ? LIMIT 1');
    $info->bind_param('i', $ticket_id);
    $info->execute();
    $ticket = $info->get_result()->fetch_assoc();
    $info->close();
    if ($ticket) {
        $owner = (int) $ticket['user_id'];
        $assignee = (int) ($ticket['assigned_to'] ?? 0);
        $subject = (string) $ticket['subject'];
        if ($status === 'awaiting_confirmation') {
            notify_user_email(
                $conn,
                $owner,
                'Ticket #' . $ticket_id . ' needs your confirmation',
                "Ticket #{$ticket_id} is ready for your confirmation.\nSubject: {$subject}\nOpen Tickets and choose Solved or Not Solved Yet."
            );
        }
        if ($assigned_raw !== '' && $assigned_raw !== '0' && $assignee > 0 && $assignee !== $prevAssigned) {
            notify_user_email(
                $conn,
                $assignee,
                'Ticket #' . $ticket_id . ' assigned to you',
                "An administrator assigned ticket #{$ticket_id} to you.\nSubject: {$subject}"
            );
        }
    }
}

header('Location: ../pages/admin.php?tab=tickets');
exit();

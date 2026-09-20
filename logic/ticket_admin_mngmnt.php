<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_status.php';
require_role('admin');

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
}

header('Location: ../pages/admin.php?tab=tickets');
exit();

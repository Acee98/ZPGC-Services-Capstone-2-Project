<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'ai_classify.php';
require_once 'ticket_assign.php';
require_role('user');

/**
 * Detect optional AI columns without crashing if SQL not applied yet.
 */
function tickets_has_column(mysqli $conn, $column)
{
    static $cache = [];
    if (isset($cache[$column])) {
        return $cache[$column];
    }
    $col = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM tickets LIKE '{$col}'");
    $cache[$column] = ($res && $res->num_rows > 0);
    return $cache[$column];
}

if (isset($_POST['submit-ticket'])) {
    $category = trim($_POST['category'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $pri_raw = trim($_POST['priority'] ?? '');
    $priority = in_array($pri_raw, ['critical', 'moderate', 'low'], true) ? $pri_raw : null;

    $allowedCat = ['hardware', 'software', 'account', 'network', 'other'];
    if (!in_array($category, $allowedCat, true)) {
        header('Location: ../pages/ticket.php');
        exit();
    }

    if (!isset($_SESSION['email'])) {
        header('Location: ../pages/login_signup.php');
        exit();
    }
    $email = $_SESSION['email'];
    $find = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $find->bind_param('s', $email);
    $find->execute();
    $found = $find->get_result()->fetch_assoc();

    if (!$found) {
        header('Location: ../pages/ticket.php');
        exit();
    }
    $user_id = (int) $found['id'];

    if ($priority === null) {
        $stmt = $conn->prepare(
            'INSERT INTO tickets (user_id, subject, description, category) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('isss', $user_id, $subject, $description, $category);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO tickets (user_id, subject, description, category, priority) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issss', $user_id, $subject, $description, $category, $priority);
    }
    $stmt->execute();
    $ticket_id = (int) $conn->insert_id;
    $stmt->close();

    if ($ticket_id > 0) {
        $hasGuidance = tickets_has_column($conn, 'ai_guidance');
        $hasMethod = tickets_has_column($conn, 'ai_method');

        // Low priority → AI self-help first (no technician yet).
        if ($priority === 'low') {
            $tips = ai_troubleshoot_ticket($subject, $description, $category, 'low');
            if ($tips['ok'] && $hasGuidance) {
                $guidance = (string) $tips['guidance_text'];
                $method = (string) ($tips['method'] ?? 'keyword');
                if ($hasMethod) {
                    $upd = $conn->prepare(
                        'UPDATE tickets SET ai_guidance = ?, ai_method = ?, status = ? WHERE id = ?'
                    );
                    $status = 'pending';
                    $upd->bind_param('sssi', $guidance, $method, $status, $ticket_id);
                } else {
                    $upd = $conn->prepare(
                        'UPDATE tickets SET ai_guidance = ?, status = ? WHERE id = ?'
                    );
                    $status = 'pending';
                    $upd->bind_param('ssi', $guidance, $status, $ticket_id);
                }
                $upd->execute();
                $upd->close();
                $_SESSION['ticket_flash'] = 'Ticket #' . $ticket_id
                    . ' submitted. Try the AI troubleshooting tips first. '
                    . 'If they do not help, click Request Technician.';
            } else {
                // Tips unavailable — escalate to a technician immediately.
                $tech = ticket_auto_assign($conn, $ticket_id, 'ongoing');
                $_SESSION['ticket_flash'] = $tech
                    ? ('Ticket #' . $ticket_id . ' submitted and assigned to a technician.')
                    : ('Ticket #' . $ticket_id . ' submitted. No active technician available yet.');
            }
        } elseif ($priority === 'critical' || $priority === 'moderate') {
            // Auto-assign technician for non-low tickets.
            $tech = ticket_auto_assign($conn, $ticket_id, 'ongoing');
            if ($hasMethod) {
                $method = 'auto_assign';
                $upd = $conn->prepare('UPDATE tickets SET ai_method = ? WHERE id = ?');
                $upd->bind_param('si', $method, $ticket_id);
                $upd->execute();
                $upd->close();
            }
            $_SESSION['ticket_flash'] = $tech
                ? ('Ticket #' . $ticket_id . ' submitted and automatically assigned to a technician.')
                : ('Ticket #' . $ticket_id . ' submitted. Waiting for an available technician.');
        } else {
            $_SESSION['ticket_flash'] = 'Ticket #' . $ticket_id . ' submitted.';
        }
    }

    header('Location: ../pages/user.php?tab=tickets');
    exit();
}
header('Location: ../pages/ticket.php');
exit();

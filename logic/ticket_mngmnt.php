<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'ai_classify.php';
require_once 'ticket_assign.php';
require_once 'severity_matrix.php';
require_once 'ticket_subjects.php';
require_once 'auth_mail.php';
require_role('user');
zpgc_csrf_require();

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
    $checked = ticket_subject_from_post(
        $category,
        $_POST['subject'] ?? '',
        $_POST['description'] ?? ''
    );
    if (!$checked['ok']) {
        $_SESSION['ticket_form_error'] = $checked['error'];
        $_SESSION['ticket_form_old'] = [
            'category' => $category,
            'subject' => trim((string) ($_POST['subject'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
        ];
        header('Location: ../pages/ticket.php');
        exit();
    }
    $subject = $checked['subject'];
    $description = $checked['description'];
    unset($_SESSION['ticket_form_old']);
    $ai = ai_classify_ticket($subject, $description);
    $axes = severity_estimate_axes($subject, $description);
    $urgency = $axes['urgency'];
    $impact = $axes['impact'];
    if (($ai['method'] ?? '') === 'openai') {
        $aiUrgency = (int) ($ai['urgency'] ?? 0);
        $aiImpact = (int) ($ai['impact'] ?? 0);
        if ($aiUrgency >= 1 && $aiUrgency <= 3) {
            $urgency = $aiUrgency;
        }
        if ($aiImpact >= 1 && $aiImpact <= 3) {
            $impact = $aiImpact;
        }
    }
    $openSame = severity_identical_open_count($conn, $subject);
    $matrix = severity_apply_matrix($urgency, $impact, $openSame + 1);
    $priority = $matrix['priority'];
    $aiMethod = ($ai['ok'] ?? false) ? (string) ($ai['method'] ?? 'keyword') : 'fallback';
    if (($ai['fallback_reason'] ?? '') === 'quota') {
        $aiMethod = 'kw-quota';
    }

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

    $hasScore = tickets_has_column($conn, 'severity_score')
        && tickets_has_column($conn, 'urgency')
        && tickets_has_column($conn, 'impact_level');
    if ($hasScore) {
        $stmt = $conn->prepare(
            'INSERT INTO tickets (user_id, subject, description, category, priority, urgency, impact_level, severity_score)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if ($stmt) {
            $urg = (int) $matrix['urgency'];
            $imp = (int) $matrix['impact'];
            $score = (int) $matrix['final_score'];
            $stmt->bind_param('issssiii', $user_id, $subject, $description, $category, $priority, $urg, $imp, $score);
        }
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO tickets (user_id, subject, description, category, priority) VALUES (?, ?, ?, ?, ?)'
        );
        if ($stmt) {
            $stmt->bind_param('issss', $user_id, $subject, $description, $category, $priority);
        }
    }
    if (!$stmt) {
        $_SESSION['ticket_form_error'] = 'Could not create the ticket (database schema mismatch). Ask an admin to check the tickets table.';
        $_SESSION['ticket_form_old'] = [
            'category' => $category,
            'subject' => $subject,
            'description' => $description,
        ];
        header('Location: ../pages/ticket.php');
        exit();
    }
    if (!$stmt->execute()) {
        $_SESSION['ticket_form_error'] = 'Could not save the ticket. Please try again.';
        $_SESSION['ticket_form_old'] = [
            'category' => $category,
            'subject' => $subject,
            'description' => $description,
        ];
        $stmt->close();
        header('Location: ../pages/ticket.php');
        exit();
    }
    $ticket_id = (int) $conn->insert_id;
    $stmt->close();

    if ($ticket_id > 0) {
        $hasMethod = tickets_has_column($conn, 'ai_method');
        $hasGuidance = tickets_has_column($conn, 'ai_guidance');
        $priLabel = ucfirst($priority);
        $scoreNote = 'score ' . (int) $matrix['final_score'];
        if (!empty($matrix['escalated'])) {
            $scoreNote .= ', escalated';
        }

        if ($priority === 'low') {
            $tips = ai_troubleshoot_ticket($subject, $description, $category, 'low');
            if ($tips['ok'] && $hasGuidance) {
                $guidance = (string) $tips['guidance_text'];
                if ($hasMethod) {
                    $upd = $conn->prepare(
                        'UPDATE tickets SET ai_guidance = ?, ai_method = ?, status = ? WHERE id = ?'
                    );
                    $pending = 'pending';
                    $upd->bind_param('sssi', $guidance, $aiMethod, $pending, $ticket_id);
                } else {
                    $upd = $conn->prepare(
                        'UPDATE tickets SET ai_guidance = ?, status = ? WHERE id = ?'
                    );
                    $pending = 'pending';
                    $upd->bind_param('ssi', $guidance, $pending, $ticket_id);
                }
                $upd->execute();
                $upd->close();
                $_SESSION['ticket_flash'] = 'Ticket #' . $ticket_id
                    . ' submitted as Low (' . $scoreNote . '). Try the troubleshooting steps first. '
                    . 'If they do not help, request a technician.';
                notify_user_email(
                    $conn,
                    $user_id,
                    'Ticket #' . $ticket_id . ' received',
                    "Your ticket #{$ticket_id} was submitted as Low ({$scoreNote}).\nSubject: {$subject}\nTry the troubleshooting steps in ZPGC Services first."
                );
            } else {
                $tech = ticket_auto_assign($conn, $ticket_id, 'ongoing');
                $_SESSION['ticket_flash'] = $tech
                    ? ('Ticket #' . $ticket_id . ' is Low (' . $scoreNote . ') but tips were unavailable, so a technician was assigned.')
                    : ('Ticket #' . $ticket_id . ' submitted as Low (' . $scoreNote . '). No technician is available yet.');
                notify_user_email(
                    $conn,
                    $user_id,
                    'Ticket #' . $ticket_id . ' received',
                    "Your ticket #{$ticket_id} was submitted as Low ({$scoreNote}).\nSubject: {$subject}"
                );
                if ($tech) {
                    notify_user_email(
                        $conn,
                        (int) $tech,
                        'Ticket #' . $ticket_id . ' assigned to you',
                        "Ticket #{$ticket_id} was assigned to you.\nSubject: {$subject}"
                    );
                }
            }
        } else {
            $tech = ticket_auto_assign($conn, $ticket_id, 'ongoing');
            if ($hasMethod) {
                $upd = $conn->prepare('UPDATE tickets SET ai_method = ? WHERE id = ?');
                $upd->bind_param('si', $aiMethod, $ticket_id);
                $upd->execute();
                $upd->close();
            }
            if ($tech) {
                $_SESSION['ticket_flash'] = 'Ticket #' . $ticket_id
                    . ' submitted. Priority set to ' . $priLabel
                    . ' (' . $scoreNote . ')'
                    . ' and a technician was assigned automatically.';
            } else {
                $_SESSION['ticket_flash'] = 'Ticket #' . $ticket_id
                    . ' submitted with priority ' . $priLabel
                    . ' (' . $scoreNote . '). No active technician is available yet.';
            }
            notify_user_email(
                $conn,
                $user_id,
                'Ticket #' . $ticket_id . ' received',
                "Your ticket #{$ticket_id} was submitted with priority {$priLabel} ({$scoreNote}).\nSubject: {$subject}"
            );
            if ($tech) {
                notify_user_email(
                    $conn,
                    (int) $tech,
                    'Ticket #' . $ticket_id . ' assigned to you',
                    "Ticket #{$ticket_id} was assigned to you.\nSubject: {$subject}"
                );
            }
        }
    } else {
        $_SESSION['ticket_form_error'] = 'Ticket was not created. Please try again.';
        $_SESSION['ticket_form_old'] = [
            'category' => $category,
            'subject' => $subject,
            'description' => $description,
        ];
        header('Location: ../pages/ticket.php');
        exit();
    }

    header('Location: ../pages/user.php?tab=tickets');
    exit();
}
header('Location: ../pages/ticket.php');
exit();

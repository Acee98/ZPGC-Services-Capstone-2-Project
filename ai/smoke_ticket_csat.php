<?php
/**
 * Smoke: ticket create resilience + self-help CSAT prompt path.
 * CLI only.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/logic/session_config.php';
require_once dirname(__DIR__) . '/logic/config.php';
require_once dirname(__DIR__) . '/logic/ticket_times.php';

$pass = 0;
$fail = 0;

function check($label, $ok, $detail = '')
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "[PASS] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    } else {
        $fail++;
        echo "[FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    }
}

$src = file_get_contents(dirname(__DIR__) . '/logic/ticket_escalate_mngmnt.php');
check(
    'self-help defers archive for CSAT',
    str_contains($src, 'ticket_mark_resolved($conn, $ticket_id, false)')
        && str_contains($src, "\$_SESSION['rate_ticket_id']")
);

$adminSrc = file_get_contents(dirname(__DIR__) . '/logic/ticket_admin_mngmnt.php');
check(
    'admin resolve defers archive',
    str_contains($adminSrc, 'ticket_mark_resolved($conn, $ticket_id, false)')
);

$createSrc = file_get_contents(dirname(__DIR__) . '/logic/ticket_mngmnt.php');
check(
    'ticket create checks urgency columns',
    str_contains($createSrc, "tickets_has_column(\$conn, 'urgency')")
        && str_contains($createSrc, 'ticket_form_error')
);
check(
    'ticket create handles execute failure',
    str_contains($createSrc, '!$stmt->execute()')
);

$userId = 1;
$subject = 'Smoke CSAT self-help ' . time();
$desc = 'Automated smoke ticket for satisfaction prompt.';
$cat = 'software';
$pri = 'low';
$stmt = $conn->prepare(
    'INSERT INTO tickets (user_id, subject, description, category, priority, status)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$status = 'pending';
$stmt->bind_param('isssss', $userId, $subject, $desc, $cat, $pri, $status);
$ok = $stmt->execute();
$ticketId = (int) $conn->insert_id;
$stmt->close();
check('insert pending low ticket', $ok && $ticketId > 0, (string) $ticketId);

if ($ticketId > 0) {
    $resolved = 'resolved';
    $upd = $conn->prepare(
        'UPDATE tickets SET status = ? WHERE id = ? AND user_id = ? AND (assigned_to IS NULL OR assigned_to = 0)'
    );
    $upd->bind_param('sii', $resolved, $ticketId, $userId);
    $upd->execute();
    $upd->close();
    ticket_mark_resolved($conn, $ticketId, false);
    $_SESSION['rate_ticket_id'] = $ticketId;

    $row = $conn->query('SELECT status, archived_at, resolved_at FROM tickets WHERE id = ' . $ticketId)->fetch_assoc();
    check('resolved status set', ($row['status'] ?? '') === 'resolved');
    check('not archived yet (CSAT pending)', empty($row['archived_at']), json_encode($row));
    check('rate_ticket_id set', (int) ($_SESSION['rate_ticket_id'] ?? 0) === $ticketId);

    // Cleanup smoke row
    $conn->query('DELETE FROM tickets WHERE id = ' . $ticketId);
    check('cleanup smoke ticket', true, (string) $ticketId);
}

echo PHP_EOL . "Passed {$pass}, failed {$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);

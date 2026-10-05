<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'audit_log.php';
require_once 'ticket_retention.php';
require_role('admin');
zpgc_csrf_require();

$requested = isset($_POST['dispose_archived_tickets']) || isset($_POST['purge_archived_tickets']);
if (!$requested) {
    header('Location: ../pages/admin.php?tab=utilities');
    exit();
}

$result = ticket_retention_purge_batch($conn);
ticket_retention_clear_eligible_cache();
$rules = ticket_retention_rules_summary();

if ($result['deleted'] > 0) {
    $detail = 'Manual disposal removed ' . $result['deleted'] . ' ticket(s)';
    if (!empty($result['ids'])) {
        $detail .= ': #' . implode(', #', array_slice($result['ids'], 0, 12));
    }
    audit_write($conn, 'ticket_disposal_manual', 0, substr($detail, 0, 255));
    $_SESSION['utilities_success'] =
        'Disposed ' . $result['deleted'] . ' archived resolved ticket(s) in this batch'
        . ($result['archived_backfill'] > 0
            ? ' (also auto-archived ' . $result['archived_backfill'] . ' resolved ticket(s) first).'
            : '.');
} else {
    $eligible = ticket_retention_count_eligible($conn);
    $_SESSION['utilities_success'] =
        'No tickets met disposal rules yet. Eligible now: ' . $eligible . '.'
        . ' Rules: rated ≥' . $rules['rated_days'] . ' days archived, unrated ≥'
        . $rules['unrated_days'] . ' days, batch ≤' . $rules['batch'] . '.';
}

header('Location: ../pages/admin.php?tab=utilities');
exit();

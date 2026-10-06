<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'audit_log.php';
require_once 'techn_apply.php';
require_role('admin');
zpgc_csrf_require();

function apply_admin_redirect()
{
    header('Location: ../pages/admin.php?tab=utilities');
    exit();
}

function apply_admin_fail($msg)
{
    $_SESSION['utilities_error'] = $msg;
    apply_admin_redirect();
}

function apply_admin_ok($msg)
{
    $_SESSION['utilities_success'] = $msg;
    apply_admin_redirect();
}

techn_apply_ready($conn);
$appId = (int) ($_POST['app_id'] ?? 0);
$app = techn_apply_get($conn, $appId);
if (!$app) {
    apply_admin_fail('That application is no longer available.');
}

$userId = (int) $app['user_id'];
$name = trim((string) ($app['first_name'] ?? '') . ' ' . (string) ($app['last_name'] ?? ''));

if (isset($_POST['apply_approve'])) {
    $r = techn_apply_approve_account($conn, $userId);
    if (empty($r['ok'])) {
        apply_admin_fail($r['error'] ?? 'Could not approve the application.');
    }
    audit_write($conn, 'techn_apply_approve', $userId, 'Approved technician application and activated account.');
    apply_admin_ok(($name !== '' ? $name : 'Technician') . ' is now active.');
}

if (isset($_POST['apply_reject'])) {
    if (!techn_apply_delete_row($conn, $app)) {
        apply_admin_fail('Could not reject the application.');
    }
    notify_user_email(
        $conn,
        $userId,
        'ZPGC technician application declined',
        'An administrator declined your technician application. It has been removed. You may submit a new application after signing in.'
    );
    audit_write($conn, 'techn_apply_reject', $userId, 'Rejected and removed technician application.');
    apply_admin_ok('Application rejected and removed.');
}

if (isset($_POST['apply_modify'])) {
    $proposed = techn_apply_normalize_specialty($_POST['proposed_specialty'] ?? '');
    if ($proposed === '') {
        apply_admin_fail('Choose a specialty to propose.');
    }
    if (strcasecmp($proposed, (string) $app['specialty']) === 0 && ($app['status'] ?? '') === 'pending') {
        apply_admin_fail('Pick a different specialty than the one already selected.');
    }
    $id = (int) $app['id'];
    $stmt = $conn->prepare(
        "UPDATE technician_applications
         SET proposed_specialty = ?, status = 'awaiting_role_change',
             role_change_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
         WHERE id = ?"
    );
    $stmt->bind_param('si', $proposed, $id);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        apply_admin_fail('Could not save the proposed specialty.');
    }
    $sent = techn_apply_send_role_change($conn, $app, $proposed);
    audit_write(
        $conn,
        'techn_apply_modify',
        $userId,
        'Proposed specialty change to ' . $proposed . ' (24-hour confirmation).'
    );
    if (empty($sent['ok'])) {
        apply_admin_ok(
            'Specialty change saved, but Outlook mail did not send ('
            . (string) ($sent['error'] ?? 'unknown')
            . '). Ask the technician to confirm from their application page.'
        );
    }
    apply_admin_ok('Outlook notification sent. The technician has 24 hours to approve the specialty change.');
}

apply_admin_redirect();

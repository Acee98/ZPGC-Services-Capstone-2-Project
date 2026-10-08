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

if (isset($_POST['apply_approve']) || isset($_POST['apply_decision'])) {
    $assigned = techn_apply_normalize_specialty($_POST['assigned_specialty'] ?? $app['specialty'] ?? '');
    if ($assigned === '') {
        apply_admin_fail('Choose a technician role.');
    }
    $requested = (string) ($app['specialty'] ?? '');
    if (strcasecmp($assigned, $requested) !== 0) {
        $id = (int) $app['id'];
        $stmt = $conn->prepare(
            "UPDATE technician_applications
             SET proposed_specialty = ?, status = 'awaiting_role_change',
                 role_change_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
             WHERE id = ?"
        );
        $stmt->bind_param('si', $assigned, $id);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            apply_admin_fail('Could not save the proposed role.');
        }
        $sent = techn_apply_send_role_change($conn, $app, $assigned);
        audit_write(
            $conn,
            'techn_apply_offer',
            $userId,
            'Offered ' . $assigned . ' instead of ' . $requested . ' (24-hour accept or decline).'
        );
        if (empty($sent['ok'])) {
            apply_admin_ok(
                'Role offer saved, but Outlook mail did not send ('
                . (string) ($sent['error'] ?? 'unknown')
                . '). Ask the technician to accept or decline from the application page.'
            );
        }
        apply_admin_ok(
            'Formal role-change letter sent to Outlook. The technician has 24 hours to accept (activates the account) or decline (removes the account).'
        );
    }
    $r = techn_apply_approve_account($conn, $userId);
    if (empty($r['ok'])) {
        apply_admin_fail($r['error'] ?? 'Could not approve the application.');
    }
    audit_write($conn, 'techn_apply_approve', $userId, 'Approved technician application and activated account.');
    apply_admin_ok(($name !== '' ? $name : 'Technician') . ' is now active as ' . $assigned . '.');
}

if (isset($_POST['apply_reject'])) {
    notify_user_email(
        $conn,
        $userId,
        'ZPGC technician application declined',
        'An administrator declined your technician application. The application and technician account have been removed.'
    );
    $removed = techn_apply_remove_inactive_technician($conn, $userId);
    if (empty($removed['ok'])) {
        apply_admin_fail('Could not reject the application.');
    }
    audit_write($conn, 'techn_apply_reject', $userId, 'Rejected application and deleted technician account.');
    apply_admin_ok('Application rejected. The technician account was removed from Accounts.');
}

if (isset($_POST['apply_modify'])) {
    apply_admin_fail('Use the role dropdown and Approve. The separate Modify action has been removed.');
}

apply_admin_redirect();

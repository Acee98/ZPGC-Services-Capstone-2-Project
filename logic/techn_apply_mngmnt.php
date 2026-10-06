<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'techn_apply.php';
require_login();
zpgc_csrf_require();

$role = function_exists('auth_mail_normalize_role')
    ? auth_mail_normalize_role($_SESSION['role'] ?? '')
    : strtolower((string) ($_SESSION['role'] ?? ''));
if ($role !== 'techn') {
    header('Location: ' . zpgc_role_home($role !== '' ? $role : 'user'));
    exit();
}

$userId = current_user_id($conn);
if ($userId <= 0) {
    header('Location: ../pages/login_signup.php');
    exit();
}

$st = $conn->prepare('SELECT id, status, email_verified, role FROM users WHERE id = ? LIMIT 1');
$st->bind_param('i', $userId);
$st->execute();
$user = $st->get_result()->fetch_assoc();
$st->close();
if (!$user || strtolower((string) $user['status']) === 'active') {
    header('Location: ../pages/techn.php');
    exit();
}

function apply_redirect()
{
    header('Location: ../pages/techn_apply.php');
    exit();
}

function apply_fail($msg)
{
    $_SESSION['apply_error'] = $msg;
    apply_redirect();
}

function apply_ok($msg)
{
    $_SESSION['apply_success'] = $msg;
    apply_redirect();
}

techn_apply_ready($conn);
$app = techn_apply_get_for_user($conn, $userId);

if (isset($_POST['delete_application'])) {
    if (!$app || ($app['status'] ?? '') === 'approved') {
        apply_fail('There is no application to delete.');
    }
    if (!techn_apply_delete_row($conn, $app)) {
        apply_fail('Could not delete the application.');
    }
    apply_ok('Application deleted. You can submit a new one when you are ready.');
}

if (isset($_POST['accept_role_change'])) {
    $r = techn_apply_accept_role_change($conn, $userId);
    if (empty($r['ok'])) {
        apply_fail($r['error'] ?? 'Could not confirm the specialty change.');
    }
    apply_ok('Specialty updated to ' . $r['specialty'] . '. Your application is Pending for the administrator.');
}

if (isset($_POST['submit_application']) || isset($_POST['update_application'])) {
    $isUpdate = isset($_POST['update_application']);
    if ($isUpdate && (!$app || !in_array($app['status'] ?? '', ['pending', 'awaiting_role_change'], true))) {
        apply_fail('There is no open application to update.');
    }
    if (!$isUpdate && $app) {
        apply_fail('You already have an application. Review it below.');
    }
    $specialty = techn_apply_normalize_specialty($_POST['specialty'] ?? '');
    if ($specialty === '') {
        apply_fail('Select a technical role: Hardware, Software, Network, Account, or Other.');
    }

    $stored = $app['resume_stored_name'] ?? '';
    $orig = $app['resume_original_name'] ?? '';
    $mime = $app['resume_mime'] ?? 'application/octet-stream';
    $hasFile = isset($_FILES['resume']) && (int) ($_FILES['resume']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if (!$isUpdate || $hasFile) {
        $up = techn_apply_store_resume($_FILES['resume'] ?? []);
        if (empty($up['ok'])) {
            apply_fail($up['error'] ?? 'Resume upload failed.');
        }
        if ($isUpdate && $stored !== '') {
            techn_apply_unlink_resume($stored);
        }
        $stored = $up['stored'];
        $orig = $up['original'];
        $mime = $up['mime'];
    }
    if ($stored === '') {
        apply_fail('Upload a PDF, DOC, or DOCX resume.');
    }

    if ($isUpdate) {
        $id = (int) $app['id'];
        $stmt = $conn->prepare(
            'UPDATE technician_applications
             SET specialty = ?, resume_stored_name = ?, resume_original_name = ?, resume_mime = ?
             WHERE id = ?'
        );
        $stmt->bind_param('ssssi', $specialty, $stored, $orig, $mime, $id);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            apply_fail('Could not update the application.');
        }
        apply_ok('Application updated. Status remains Pending until an administrator reviews it.');
    }

    $status = 'pending';
    $stmt = $conn->prepare(
        'INSERT INTO technician_applications
         (user_id, specialty, resume_stored_name, resume_original_name, resume_mime, status)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isssss', $userId, $specialty, $stored, $orig, $mime, $status);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        techn_apply_unlink_resume($stored);
        apply_fail('Could not submit the application.');
    }
    $name = trim((string) ($_SESSION['first_name'] ?? '') . ' ' . (string) ($_SESSION['last_name'] ?? ''));
    techn_apply_notify_admins(
        $conn,
        'New technician application',
        ($name !== '' ? $name : 'A technician') . ' submitted a Pending application (' . $specialty
        . '). Review it in Utilities.'
    );
    apply_ok('Application submitted with Pending status. You can review it here until an administrator decides.');
}

apply_redirect();

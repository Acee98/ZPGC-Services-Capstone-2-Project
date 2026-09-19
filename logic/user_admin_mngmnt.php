<?php
require_once 'session_config.php';
require_once 'config.php';
require_role('admin');

$allowedRoles = ['user', 'admin', 'techn'];
$allowedStatuses = ['active', 'inactive'];

function utilities_redirect($extra = '')
{
    $q = $extra !== '' ? '?' . ltrim($extra, '?') : '?tab=utilities';
    if (strpos($q, 'tab=') === false) {
        $q = '?tab=utilities&' . ltrim($q, '?&');
    }
    header('Location: ../pages/admin.php' . $q);
    exit();
}

function utilities_fail($message, $extra = 'tab=utilities')
{
    $_SESSION['utilities_error'] = $message;
    utilities_redirect($extra);
}

function utilities_ok($message)
{
    $_SESSION['utilities_success'] = $message;
    utilities_redirect('tab=utilities');
}

if (isset($_POST['add_user'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $password_plain = $_POST['password'] ?? '';

    if ($first_name === '' || $last_name === '' || $email === ''
        || strlen($password_plain) < 8 || !in_array($role, $allowedRoles, true)) {
        utilities_fail(
            'Please fill in every field with a valid role and an 8+ character password.',
            'tab=utilities&action=add'
        );
    }

    $checkEmail = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $checkEmail->bind_param('s', $email);
    $checkEmail->execute();
    $checkEmail->store_result();
    if ($checkEmail->num_rows > 0) {
        $checkEmail->close();
        utilities_fail('That email is already registered.', 'tab=utilities&action=add');
    }
    $checkEmail->close();

    $password = password_hash($password_plain, PASSWORD_DEFAULT);
    $status = 'active';
    $insert = $conn->prepare(
        'INSERT INTO users (first_name, last_name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insert->bind_param('ssssss', $first_name, $last_name, $email, $password, $role, $status);
    $insert->execute();
    $insert->close();

    utilities_ok("Account for $first_name $last_name created and active.");
}

if (isset($_POST['edit_user'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';

    if ($id <= 0 || $first_name === '' || $last_name === '' || $email === ''
        || !in_array($role, $allowedRoles, true)) {
        utilities_fail('Please fill in every field with a valid role.', 'tab=utilities&edit_id=' . $id);
    }

    $selfCheck = $conn->prepare('SELECT email FROM users WHERE id = ?');
    $selfCheck->bind_param('i', $id);
    $selfCheck->execute();
    $selfCheck->bind_result($current_email);
    $selfCheck->fetch();
    $selfCheck->close();

    if ($role !== 'admin' && $current_email === ($_SESSION['email'] ?? '')) {
        utilities_fail(
            "You can't remove your own admin role while logged in as this account.",
            'tab=utilities&edit_id=' . $id
        );
    }

    $checkEmail = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $checkEmail->bind_param('si', $email, $id);
    $checkEmail->execute();
    $checkEmail->store_result();
    if ($checkEmail->num_rows > 0) {
        $checkEmail->close();
        utilities_fail('That email is already used by another account.', 'tab=utilities&edit_id=' . $id);
    }
    $checkEmail->close();

    $update = $conn->prepare(
        'UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?'
    );
    $update->bind_param('ssssi', $first_name, $last_name, $email, $role, $id);
    if (!$update->execute()) {
        $update->close();
        utilities_fail('Could not update that account.', 'tab=utilities&edit_id=' . $id);
    }
    $update->close();

    utilities_ok('Account updated.');
}

if (isset($_POST['set_status'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($id <= 0 || !in_array($status, $allowedStatuses, true)) {
        utilities_redirect('tab=utilities');
    }

    $selfCheck = $conn->prepare('SELECT email FROM users WHERE id = ?');
    $selfCheck->bind_param('i', $id);
    $selfCheck->execute();
    $selfCheck->bind_result($target_email);
    $selfCheck->fetch();
    $selfCheck->close();

    if ($status === 'inactive' && $target_email === ($_SESSION['email'] ?? '')) {
        utilities_fail("You can't deactivate your own account while logged in as it.");
    }

    $update = $conn->prepare('UPDATE users SET status = ? WHERE id = ?');
    $update->bind_param('si', $status, $id);
    $update->execute();
    $update->close();

    utilities_ok($status === 'active' ? 'Account activated.' : 'Account deactivated.');
}

if (isset($_POST['delete_user'])) {
    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        utilities_redirect('tab=utilities');
    }

    $selfCheck = $conn->prepare('SELECT email FROM users WHERE id = ?');
    $selfCheck->bind_param('i', $id);
    $selfCheck->execute();
    $selfCheck->bind_result($target_email);
    $found = $selfCheck->fetch();
    $selfCheck->close();

    if (!$found) {
        utilities_fail('That account no longer exists.');
    }

    if ($target_email === ($_SESSION['email'] ?? '')) {
        utilities_fail("You can't delete your own account while logged in as it.");
    }

    $delete = $conn->prepare('DELETE FROM users WHERE id = ?');
    $delete->bind_param('i', $id);
    if (!$delete->execute() || $delete->affected_rows === 0) {
        $delete->close();
        utilities_fail('Could not delete that account (it may still own tickets).');
    }
    $delete->close();

    utilities_ok('Account deleted.');
}

utilities_redirect('tab=utilities');

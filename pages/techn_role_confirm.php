<?php
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_once '../logic/techn_apply.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';

techn_apply_ready($conn);
auth_mail_ready($conn);

$row = $token !== '' ? auth_mail_find_token($conn, 'techn_role_change', $token) : null;
if (!$row) {
    $error = 'This confirmation link is invalid or expired. If 24 hours passed, the application was removed.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    zpgc_csrf_require();
    $userId = auth_mail_consume_token($conn, 'techn_role_change', $token);
    if (!$userId) {
        $error = 'This confirmation link is invalid or expired. If 24 hours passed, the application was removed.';
        $row = null;
    } else {
        $r = techn_apply_accept_role_change($conn, (int) $userId);
        if (empty($r['ok'])) {
            $error = $r['error'] ?? 'Could not confirm the specialty change.';
        } else {
            $success = 'Specialty updated to ' . $r['specialty'] . '. Your application is Pending for the administrator.';
            $row = null;
        }
    }
}

function showBox($cls, $msg)
{
    if ($msg === '') {
        return '';
    }
    return '<div class="' . $cls . '">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/login_signup.css?v=1.6.19">
    <title>ZPGC Services | Confirm specialty</title>
</head>
<body>
<div class="container">
    <div class="form-box active">
        <?php if ($success !== '') { ?>
            <h1>Confirmed</h1>
            <?php echo showBox('form-success', $success); ?>
            <p><a href="login_signup.php">Return to login</a></p>
        <?php } elseif ($error !== '') { ?>
            <h1>Link expired</h1>
            <?php echo showBox('form-error', $error); ?>
            <p><a href="login_signup.php">Return to login</a></p>
        <?php } else { ?>
            <form method="post">
                <?php echo zpgc_csrf_field(); ?>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <h1>Confirm specialty</h1>
                <h5>An administrator proposed a new technician specialty. Approving keeps your application Pending. Ignoring this for 24 hours removes the application.</h5>
                <button type="submit">Approve specialty change</button>
                <p><a href="login_signup.php">Cancel</a></p>
            </form>
        <?php } ?>
    </div>
</div>
</body>
</html>

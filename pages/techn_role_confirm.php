<?php
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_once '../logic/techn_apply.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$success = '';
$app = null;
$userId = 0;

techn_apply_ready($conn);
auth_mail_ready($conn);

function techn_role_find($conn, $token)
{
    foreach (['techn_role_accept', 'techn_role_deny', 'techn_role_change'] as $purpose) {
        $row = auth_mail_find_token($conn, $purpose, $token);
        if ($row) {
            $row['purpose'] = $purpose;
            return $row;
        }
    }
    return null;
}

$row = $token !== '' ? techn_role_find($conn, $token) : null;
if (!$row) {
    $error = 'This confirmation link is invalid or expired. If 24 hours passed, the application and account were removed.';
} else {
    $userId = (int) $row['user_id'];
    $app = techn_apply_get_for_user($conn, $userId);
    if (!$app || ($app['status'] ?? '') !== 'awaiting_role_change') {
        $error = 'There is no role offer waiting for this link.';
        $row = null;
    }
}

if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    zpgc_csrf_require();
    $decision = (string) ($_POST['decision'] ?? '');
    $purpose = (string) ($row['purpose'] ?? 'techn_role_accept');
    $consumed = auth_mail_consume_token($conn, $purpose, $token);
    if (!$consumed) {
        $error = 'This confirmation link is invalid or expired. If 24 hours passed, the application and account were removed.';
        $row = null;
    } elseif ($decision === 'decline') {
        $r = techn_apply_decline_role_change($conn, $userId);
        if (empty($r['ok'])) {
            $error = $r['error'] ?? 'Could not decline the offer.';
        } else {
            $success = 'You declined the role assignment. Your technician application and account have been removed.';
            $row = null;
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION = [];
            }
        }
    } else {
        $r = techn_apply_accept_role_change($conn, $userId);
        if (empty($r['ok'])) {
            $error = $r['error'] ?? 'Could not accept the role offer.';
        } else {
            $success = 'You accepted the ' . $r['specialty'] . ' role. Your technician account is now active. You can sign in.';
            $row = null;
        }
    }
}

$uName = '';
$requested = (string) ($app['specialty'] ?? '');
$proposed = (string) ($app['proposed_specialty'] ?? '');
$wantDecline = strtolower((string) ($_GET['decision'] ?? $_POST['decision'] ?? '')) === 'deny'
    || strtolower((string) ($_GET['decision'] ?? $_POST['decision'] ?? '')) === 'decline';
if ($row && $userId > 0) {
    $u = $conn->query('SELECT first_name, last_name FROM users WHERE id = ' . $userId);
    if ($u && ($ur = $u->fetch_assoc())) {
        $uName = trim((string) ($ur['first_name'] ?? '') . ' ' . (string) ($ur['last_name'] ?? ''));
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
    <link rel="stylesheet" href="../css/apply.css?v=1.6.2">
    <title>ZPGC Services | Role assignment</title>
</head>
<body class="apply-page">
<div class="apply-page-wrap">
    <div class="apply-shell">
        <header class="apply-shell-head">
            <p class="apply-kicker">Office of the Administrator</p>
            <h1>Technician role assignment</h1>
            <p>Accept the offered role to activate your account, or decline to withdraw the application.</p>
        </header>
        <div class="apply-current">
        <?php if ($success !== '') { ?>
            <?php echo showBox('ticket-notice-ok', $success); ?>
            <p><a class="apply-btn apply-btn-primary" href="login_signup.php">Return to login</a></p>
        <?php } elseif ($error !== '') { ?>
            <?php echo showBox('ticket-notice-error', $error); ?>
            <p><a class="apply-btn apply-btn-ghost" href="login_signup.php">Return to login</a></p>
        <?php } else { ?>
            <p>Dear <?php echo htmlspecialchars($uName !== '' ? $uName : 'Applicant'); ?>,</p>
            <p>You applied for <strong><?php echo htmlspecialchars($requested); ?></strong>.
                The administrator offers you <strong><?php echo htmlspecialchars($proposed); ?></strong> instead.</p>
            <?php if ($wantDecline) { ?>
            <p>You chose to <strong>decline</strong> the <?php echo htmlspecialchars($proposed); ?> offer. Confirm below to delete your application and technician account.</p>
            <?php } else { ?>
            <p>Accepting activates your technician account. Declining deletes the request and removes your account from ZPGC Services.</p>
            <?php } ?>
            <form method="post" class="apply-actions">
                <?php echo zpgc_csrf_field(); ?>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <?php if (!$wantDecline) { ?>
                <button type="submit" name="decision" value="accept" class="apply-btn apply-btn-primary">Accept and activate</button>
                <?php } ?>
                <button type="submit" name="decision" value="decline" class="apply-btn apply-btn-danger"
                    onclick="return confirm('Decline this offer? Your application and account will be removed.');">Decline and remove account</button>
                <?php if ($wantDecline) { ?>
                <a class="apply-btn apply-btn-ghost" href="?token=<?php echo rawurlencode($token); ?>&amp;decision=accept">Keep the offer instead</a>
                <?php } ?>
            </form>
        <?php } ?>
        </div>
    </div>
</div>
</body>
</html>

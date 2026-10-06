<?php
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_once '../logic/techn_apply.php';
require_login();

$role = function_exists('auth_mail_normalize_role')
    ? auth_mail_normalize_role($_SESSION['role'] ?? '')
    : strtolower((string) ($_SESSION['role'] ?? ''));
if ($role !== 'techn') {
    header('Location: ' . zpgc_role_home($role !== '' ? $role : 'user'));
    exit();
}

$userId = current_user_id($conn);
$st = $conn->prepare('SELECT status, email_verified FROM users WHERE id = ? LIMIT 1');
$st->bind_param('i', $userId);
$st->execute();
$me = $st->get_result()->fetch_assoc();
$st->close();
if (!$me) {
    header('Location: login_signup.php');
    exit();
}
if (strtolower((string) $me['status']) === 'active') {
    unset($_SESSION['techn_applicant']);
    header('Location: techn.php');
    exit();
}
if ((int) ($me['email_verified'] ?? 0) !== 1) {
    $_SESSION['login_error'] = 'Verify your email with the 6-digit code first.';
    header('Location: login_signup.php');
    exit();
}

$_SESSION['techn_applicant'] = 1;
techn_apply_ready($conn);
$app = techn_apply_get_for_user($conn, $userId);
$error = $_SESSION['apply_error'] ?? '';
$success = $_SESSION['apply_success'] ?? '';
unset($_SESSION['apply_error'], $_SESSION['apply_success']);
$specialties = techn_apply_specialties();
$awaiting = $app && ($app['status'] ?? '') === 'awaiting_role_change';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/ticket.css?v=1.6.19">
    <title>ZPGC Services | Technician application</title>
</head>
<body>
<div class="ticket-container">
    <form class="ticket-form" action="../logic/techn_apply_mngmnt.php" method="post" enctype="multipart/form-data">
        <?php echo zpgc_csrf_field(); ?>
        <h1 class="ticket-form-title">Technician application</h1>
        <p class="ticket-hint">Your email is verified. Choose a specialty and upload your resume. The administrator reviews this with Pending status. You can review or delete it here — you cannot activate your own account.</p>
        <?php if ($error !== '') { ?>
        <div class="ticket-notice-error"><?php echo htmlspecialchars($error); ?></div>
        <?php } ?>
        <?php if ($success !== '') { ?>
        <div class="ticket-notice-ok"><?php echo htmlspecialchars($success); ?></div>
        <?php } ?>

        <?php if ($app) { ?>
        <div class="ticket-field">
            <label>Status</label>
            <p class="ticket-hint" style="margin:0;"><?php echo htmlspecialchars(techn_apply_status_label($app['status'] ?? '')); ?></p>
            <?php if ($awaiting) { ?>
            <p class="ticket-hint">Administrator proposed <strong><?php echo htmlspecialchars((string) $app['proposed_specialty']); ?></strong>
                (current: <?php echo htmlspecialchars((string) $app['specialty']); ?>). Confirm within 24 hours or this application is removed.</p>
            <?php } ?>
        </div>
        <div class="ticket-field">
            <label>Resume on file</label>
            <p class="ticket-hint" style="margin:0;">
                <a href="../logic/resume_file.php?id=<?php echo (int) $app['id']; ?>">
                    <?php echo htmlspecialchars((string) $app['resume_original_name']); ?>
                </a>
            </p>
        </div>
        <?php } ?>

        <div class="ticket-field">
            <label for="specialty">Technical role</label>
            <select id="specialty" name="specialty" required>
                <option value="" disabled <?php echo empty($app) ? 'selected' : ''; ?>>Select a role</option>
                <?php foreach ($specialties as $opt) {
                    $sel = $app && strcasecmp((string) $app['specialty'], $opt) === 0 ? 'selected' : '';
                    ?>
                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($opt); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="ticket-field">
            <label for="resume"><?php echo $app ? 'Replace resume (optional)' : 'Resume (PDF, DOC, or DOCX, max 5 MB)'; ?></label>
            <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx,application/pdf" <?php echo $app ? '' : 'required'; ?>>
        </div>

        <div class="ticket-actions">
            <?php if (!$app) { ?>
            <button type="submit" name="submit_application" class="btn-submit-ticket">Submit application</button>
            <?php } else { ?>
            <button type="submit" name="update_application" class="btn-submit-ticket">Save changes</button>
            <?php if ($awaiting) { ?>
            <button type="submit" name="accept_role_change" class="btn-submit-ticket">Approve specialty change</button>
            <?php } ?>
            <button type="submit" name="delete_application" class="btn-cancel-ticket"
                onclick="return confirm('Delete this application? You can submit a new one later.');">Delete application</button>
            <?php } ?>
        </div>
        <p class="ticket-hint"><a href="../logic/logout.php">Sign out</a></p>
    </form>
</div>
</body>
</html>

<?php
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_once '../logic/auth_mail.php';

auth_mail_ready($conn);
$error = $_SESSION['forgot_error'] ?? '';
$success = $_SESSION['forgot_success'] ?? '';
unset($_SESSION['forgot_error'], $_SESSION['forgot_success']);

if (isset($_POST['forgot_password'])) {
    zpgc_csrf_require();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    // Always show the same message so accounts are not revealed.
    $_SESSION['forgot_success'] = 'If that email is registered, a reset link was sent. Check your inbox.';
    if (auth_mail_is_tsu_email($email) && mail_ready()) {
        $stmt = $conn->prepare(
            'SELECT id, first_name, email_verified, status FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($user) {
            $okToReset = ((int) ($user['email_verified'] ?? 0) === 1) || ($user['status'] ?? '') === 'active';
            if ($okToReset) {
                auth_mail_send_reset($conn, (int) $user['id'], $email, $user['first_name'] ?? '');
            }
        }
    }
    header('Location: forgot_password.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/login_signup.css?v=1.6.1">
    <title>ZPGC Services | Forgot Password</title>
</head>
<body>
    <div class="container">
        <div class="logo">
            <a href="landing_page.php"><img src="../images/ZPGC.com2.png" alt="ZPGC"></a>
        </div>
        <div class="form-box active">
            <form action="forgot_password.php" method="post">
<?php echo zpgc_csrf_field(); ?>
                <h1>FORGOT PASSWORD</h1>
                <?php if ($error !== '') { ?>
                <div class="form-error"><?php echo htmlspecialchars($error); ?></div>
                <?php } ?>
                <?php if ($success !== '') { ?>
                <div class="form-success"><?php echo htmlspecialchars($success); ?></div>
                <?php } ?>
                <h5>Enter the email on your account. We will send a reset link if it is registered.</h5>
                <input type="email" name="email" placeholder="Email" required>
                <button type="submit" name="forgot_password" value="1">Send reset link</button>
                <p><a href="login_signup.php">Back to login</a></p>
            </form>
        </div>
    </div>
</body>
</html>

<?php
require_once '../logic/session_config.php';
require_once '../logic/config.php';
require_once '../logic/auth_mail.php';

auth_mail_ready($conn);
$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$token = preg_replace('/[^a-f0-9]/i', '', $token);
$error = '';
$tokenOk = auth_mail_find_token($conn, 'reset_password', $token);

if ($token === '' || !$tokenOk) {
    if (!isset($_POST['reset_password'])) {
        $error = 'That reset link is invalid or expired. Request a new one.';
    }
}

if (isset($_POST['reset_password'])) {
    zpgc_csrf_require();
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    if ($token === '' || !auth_mail_find_token($conn, 'reset_password', $token)) {
        $error = 'That reset link is invalid or expired. Request a new one.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $userId = auth_mail_consume_token($conn, 'reset_password', $token);
        if (!$userId) {
            $error = 'That reset link is invalid or expired. Request a new one.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $upd->bind_param('si', $hash, $userId);
            $upd->execute();
            $upd->close();
            $_SESSION['login_success'] = 'Password updated. You can log in now.';
            header('Location: login_signup.php');
            exit();
        }
    }
}
$canReset = $token !== '' && auth_mail_find_token($conn, 'reset_password', $token);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/login_signup.css?v=1.6.1">
    <title>ZPGC Services | Reset Password</title>
</head>
<body>
    <div class="container">
        <div class="logo">
            <a href="landing_page.php"><img src="../images/ZPGC.com2.png" alt="ZPGC"></a>
        </div>
        <div class="form-box active">
            <form action="reset_password.php" method="post">
<?php echo zpgc_csrf_field(); ?>
                <h1>RESET PASSWORD</h1>
                <?php if ($error !== '') { ?>
                <div class="form-error"><?php echo htmlspecialchars($error); ?></div>
                <?php } ?>
                <h5>Choose a new password for your ZPGC Services account.</h5>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="password" name="password" placeholder="New password" minlength="8" required <?php echo $canReset ? '' : 'disabled'; ?>>
                <input type="password" name="password_confirm" placeholder="Confirm password" minlength="8" required <?php echo $canReset ? '' : 'disabled'; ?>>
                <button type="submit" name="reset_password" value="1" <?php echo $canReset ? '' : 'disabled'; ?>>Save password</button>
                <p><a href="forgot_password.php">Request a new link</a> · <a href="login_signup.php">Login</a></p>
            </form>
        </div>
    </div>
</body>
</html>

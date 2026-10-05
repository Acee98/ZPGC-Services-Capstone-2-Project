<?php
require_once '../logic/session_config.php';

function showError($error) {
    if ($error !== '') {
        return '<div class="form-error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    return '';
}

function showSuccess($message) {
    if ($message !== '') {
        return '<div class="form-success">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    return '';
}

$email = trim((string) ($_SESSION['pending_verify_email'] ?? ''));
$error = $_SESSION['signup_error'] ?? '';
$success = $_SESSION['signup_success'] ?? '';
unset($_SESSION['signup_error'], $_SESSION['signup_success']);

if ($email === '' && $error === '' && $success === '') {
    header('Location: login_signup.php?form=signup');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <link rel="stylesheet" href="../css/login_signup.css?v=1.6.7">
    <title>ZPGC Services | Verify Email</title>
</head>

<body>
    <div class="container">
        <div class="logo">
            <a href="../pages/landing_page.php">
                <img src="../images/ZPGC.com2.png" alt="ZPGC">
            </a>
        </div>

        <div class="form-box active" id="verify-form">
            <form action="../logic/user_mngmnt.php" method="post">
<?php echo zpgc_csrf_field(); ?>
                <h1>VERIFY EMAIL</h1>
                <?php echo showError($error); ?>
                <?php
                $msg = $success;
                if ($msg === '' && $email !== '') {
                    $msg = 'We sent a 6-digit code to ' . $email . '. Open your TSU Outlook, copy the code, and paste it below.';
                }
                echo showSuccess($msg);
                ?>
                <h5>After verification, your account is activated automatically — no admin approval needed.</h5>
                <input type="email" name="email" placeholder="TSU email used at signup" autocomplete="email" required
                    value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="text" name="code" placeholder="6-digit verification code" inputmode="numeric"
                    pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                <button type="submit" name="verify_code">Verify and activate</button>
            </form>
            <form action="../logic/user_mngmnt.php" method="post" style="margin-top:12px;">
<?php echo zpgc_csrf_field(); ?>
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" name="resend_verify">Resend code</button>
                <p><a href="login_signup.php?form=login">Back to Login</a></p>
                <p><a href="login_signup.php?form=signup">Back to Signup</a></p>
            </form>
        </div>
    </div>
</body>

</html>

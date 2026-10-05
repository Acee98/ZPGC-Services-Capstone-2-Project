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
    <link rel="stylesheet" href="../css/login_signup.css?v=1.6.17">
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
                if ($msg === '' && $email !== '' && $error === '') {
                    $msg = 'Enter the 6-digit code emailed to ' . $email
                        . '. If nothing arrived, that address may not be a real TSU mailbox — go back and sign up with your real Outlook email.';
                }
                echo showSuccess($msg);
                ?>
                <h5>Codes only arrive in a real TSU Outlook inbox. Users activate automatically after a valid code. Technicians stay pending until an admin Activates them in Utilities.</h5>
                <input type="email" name="email" placeholder="TSU email used at signup" autocomplete="email" required
                    value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="text" name="code" placeholder="6-digit verification code" inputmode="numeric"
                    pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                <button type="submit" name="verify_code">Verify email</button>
            </form>
            <form action="../logic/user_mngmnt.php" method="post" style="margin-top:12px;">
<?php echo zpgc_csrf_field(); ?>
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" name="resend_verify">Resend code</button>
                <p><a href="login_signup.php?form=login">Back to Login</a></p>
                <p><a href="login_signup.php?form=signup">Use a different (real) email</a></p>
            </form>
        </div>
    </div>
</body>

</html>

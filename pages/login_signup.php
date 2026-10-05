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

$login_error = $_SESSION['login_error'] ?? '';
$login_success = $_SESSION['login_success'] ?? '';
$signup_error = $_SESSION['signup_error'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_success'], $_SESSION['signup_error']);
$openSignup = (($_GET['form'] ?? '') === 'signup') || $signup_error !== '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <link rel="stylesheet" href="../css/login_signup.css?v=1.6.8">
    <title>ZPGC Services | Login/Signup</title>
</head>

<body>
    <div class="container">
        <div class="logo">
            <a href="../pages/landing_page.php">
                <img src="../images/ZPGC.com2.png" alt="ZPGC">
            </a>
        </div>

        <div class="form-box <?php echo $openSignup ? '' : 'active'; ?>" id="login-form">
            <form action="../logic/user_mngmnt.php" method="post">
<?php echo zpgc_csrf_field(); ?>
                <h1>LOGIN</h1>
                <?php echo showError($login_error); ?>
                <?php echo showSuccess($login_success); ?>
                <h5>Enter your credentials to access, create, or track your tickets</h5>
                <input type="email" name="email" placeholder="TSU email (student or staff)" autocomplete="email" required title="Use your @student.tsu.edu.ph or @tsu.edu.ph address">
                <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
                <button type="submit" name="login">Login</button>
                <p><a href="forgot_password.php">Forgot Password?</a></p>
                <p>Don't have an account?<a href="#" onclick="showForm('signup-form')">Signup now!</a></p>
            </form>
        </div>

        <div class="form-box <?php echo $openSignup ? 'active' : ''; ?>" id="signup-form">
            <form action="../logic/user_mngmnt.php" method="post">
<?php echo zpgc_csrf_field(); ?>
                <h1>SIGNUP</h1>
                <?php echo showError($signup_error); ?>
                <h5>Use your TSU Outlook only. We email a 6-digit code — paste it to verify and activate your account.</h5>
                <input type="text" name="first_name" placeholder="First Name" required>
                <input type="text" name="last_name" placeholder="Last Name" required>
                <input type="email" name="email" placeholder="student00000@student.tsu.edu.ph or name@tsu.edu.ph" required>
                <input type="password" name="password" placeholder="Password (8+ characters)" minlength="8" required>
                <select name="role" required>
                    <option value="" disabled selected>Role</option>
                    <option value="user">User</option>
                    <option value="techn">Technician</option>
                </select>
                <label class="terms-check">
                    <input type="checkbox" name="accept_terms" value="1" required>
                    <span>I agree to the <a href="terms.php" target="_blank" rel="noopener">Terms of Agreement</a></span>
                </label>
                <button type="submit" name="signup">Signup</button>
                <p>Already have an account?<a href="#" onclick="showForm('login-form')">Login now!</a></p>
            </form>
        </div>
    </div>
    <script src="../js/script.js?v=1.6.7"></script>
    <?php if ($openSignup) { ?>
    <script>showForm('signup-form');</script>
    <?php } ?>
</body>

</html>

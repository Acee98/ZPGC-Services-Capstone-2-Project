<?php
session_start();
$login_error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/login_signup.css">
    <title>ZPGC Services | Login/Signup</title>
</head>

<body>
    <div class="container">
        <div class="logo">
            <a href="../pages/landing_page.php">
                <img src="../images/ZPGC.com2.png" alt="ZPGC">
            </a>
        </div>

        <div class="form-box active" id="login-form">
            <form action="../logic/user_mngmnt.php" method="post">
                <h1>LOGIN</h1>
                <?php if ($login_error !== '') {echo '<p>' . htmlspecialchars($login_error) . '<p>'; } ?>
                <h5>Enter your credentials to access, create, or track your tickets</h5>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login">Login</button>
                <p>Don't have an account?<a href="#" onclick="showForm('signup-form')">Signup now!</a></p>
            </form>
        </div>

        <div class="form-box" id="signup-form">
            <form action="../logic/user_mngmnt.php" method="post">
                <h1>SIGNUP</h1>
                <h5>Enter credentials to create your ZPGC account</h5>
                <input type="text" name="first_name" placeholder="First Name" required>
                <input type="text" name="last_name" placeholder="Last Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <select name="role" required>
                    <option value="" disabled selected>Role</option>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                    <option value="techn">Technician</option>
                </select>
                <button type="submit" name="signup">Signup</button>
                <p>Already have an account?<a href="#" onclick="showForm('login-form')">Login now!</a></p>
            </form>
        </div>
    </div>
    <script src="../js/script.js"></script>
</body>

</html>
<?php
require_once 'session_config.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $paths = array_unique(['/', ZPGC_COOKIE_PATH]);
    zpgc_expire_cookie_paths(session_name(), $paths);
    zpgc_expire_cookie_paths('PHPSESSID', $paths);
}
session_destroy();
header('Location: ../pages/login_signup.php');
exit();

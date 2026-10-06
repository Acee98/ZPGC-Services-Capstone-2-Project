<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'techn_apply.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$app = techn_apply_get($conn, $id);
if (!$app) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'not found';
    exit();
}

$role = function_exists('auth_mail_normalize_role')
    ? auth_mail_normalize_role($_SESSION['role'] ?? '')
    : strtolower((string) ($_SESSION['role'] ?? ''));
$me = current_user_id($conn);
$allowed = ($role === 'admin') || ($me > 0 && $me === (int) $app['user_id']);
if (!$allowed) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'forbidden';
    exit();
}

$path = techn_apply_resume_path($app['resume_stored_name'] ?? '');
if ($path === '' || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'file missing';
    exit();
}

$download = basename((string) ($app['resume_original_name'] ?? 'resume'));
if ($download === '' || $download === '.' || $download === '..') {
    $download = 'resume';
}
$mime = (string) ($app['resume_mime'] ?? 'application/octet-stream');
if ($mime === '') {
    $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $download) . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . (string) filesize($path));
readfile($path);
exit();

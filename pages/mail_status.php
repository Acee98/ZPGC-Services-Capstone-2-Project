<?php
/**
 * Admin-only mail diagnostics for Azure / XAMPP.
 * Open: /pages/mail_status.php
 * Probe: /pages/mail_status.php?probe=1  (sends a test email to the admin)
 */
require_once __DIR__ . '/../logic/session_config.php';
require_once __DIR__ . '/../logic/config.php';
require_once __DIR__ . '/../logic/mail_smtp.php';
require_role('admin');

header('Content-Type: application/json; charset=utf-8');

$cfg = mail_env_load();
$user = (string) ($cfg['MAIL_USERNAME'] ?? '');
$maskedUser = '';
if ($user !== '') {
    $at = strpos($user, '@');
    $maskedUser = $at === false
        ? substr($user, 0, 2) . '…'
        : substr($user, 0, 2) . '…' . substr($user, $at);
}
$pass = (string) ($cfg['MAIL_PASSWORD'] ?? '');
$passLen = strlen($pass);

$probe = null;
if (isset($_GET['probe']) && $_GET['probe'] === '1') {
    $to = (string) ($_SESSION['email'] ?? $user);
    $probe = mail_send(
        $to,
        'ZPGC Services mail probe',
        "This is a test email from ZPGC Services on Azure.\n\n"
        . 'Host: ' . (string) ($_SERVER['HTTP_HOST'] ?? '') . "\n"
        . 'Time: ' . date('c') . "\n"
    );
    $probe['to'] = $to;
}

echo json_encode([
    'ok' => true,
    'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
    'mail_ready' => mail_ready(),
    'mail_host' => (string) ($cfg['MAIL_HOST'] ?? ''),
    'mail_port' => (string) ($cfg['MAIL_PORT'] ?? ''),
    'mail_encryption' => (string) ($cfg['MAIL_ENCRYPTION'] ?? ''),
    'mail_username_masked' => $maskedUser,
    'mail_password_set' => $passLen > 0,
    'mail_password_length' => $passLen,
    'mail_from' => (string) ($cfg['MAIL_FROM'] ?? ''),
    'mail_app_url' => (string) ($cfg['MAIL_APP_URL'] ?? ''),
    'public_base_url' => mail_public_base_url(),
    'sample_verify_url' => mail_app_url('pages/verify_email.php?token=SAMPLE'),
    'getenv_MAIL_USERNAME' => mail_env_value('MAIL_USERNAME') !== null,
    'server_MAIL_USERNAME' => isset($_SERVER['MAIL_USERNAME']) && trim((string) $_SERVER['MAIL_USERNAME']) !== '',
    'probe' => $probe,
    'hint' => 'Add ?probe=1 to send a test email to your admin inbox. Old emails with localhost/wrong host will not open — resend after deploy. Admin → Activate also verifies email.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

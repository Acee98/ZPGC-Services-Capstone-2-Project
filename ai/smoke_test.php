<?php
/**
 * Local smoke tests — no OpenAI spend. Run: php ai/smoke_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$fail = 0;
$pass = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $fail, $pass;
    if ($ok) {
        $pass++;
        echo "[PASS] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    } else {
        $fail++;
        echo "[FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    }
}

// 1) Critical PHP files lint
$files = [
    'logic/security.php',
    'logic/session_config.php',
    'logic/session_db.php',
    'logic/config.php',
    'logic/csrf.php',
    'logic/auth_mail.php',
    'logic/user_mngmnt.php',
    'logic/user_admin_mngmnt.php',
    'logic/ticket_mngmnt.php',
    'logic/ticket_files.php',
    'logic/ticket_attachment_mngmnt.php',
    'logic/fetch_messages.php',
    'pages/admin.php',
    'pages/user.php',
    'pages/techn.php',
    'pages/login_signup.php',
    'pages/verify_pending.php',
    'js/utilities_filter.js',
    'js/tickets_filter.js',
    'js/behavior.js',
];
foreach ($files as $rel) {
    if (str_ends_with($rel, '.js')) {
        $path = $root . '/' . $rel;
        check("exists {$rel}", is_file($path));
        continue;
    }
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        check("lint {$rel}", false, 'missing');
        continue;
    }
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    check("lint {$rel}", $code === 0, implode(' ', $out));
}

// 2) Auth email rules
require_once $root . '/logic/mail_smtp.php';
require_once $root . '/logic/auth_mail.php';

$shouldPass = [
    'juan.delacruz@student.tsu.edu.ph',
    'maria.santos@tsu.edu.ph',
    'bsit20210008@student.tsu.edu.ph',
    '2021-0001@student.tsu.edu.ph',
    'jdoe@tsu.edu.ph',
];
foreach ($shouldPass as $e) {
    $err = auth_mail_signup_email_error($e);
    check("allow {$e}", $err === '', $err);
}
$shouldFail = [
    'someone@gmail.com',
    'fake@student.tsu.edu.ph',
    'test@student.tsu.edu.ph',
    'not-an-email',
];
foreach ($shouldFail as $e) {
    $err = auth_mail_signup_email_error($e);
    check("block {$e}", $err !== '');
}

// 3) Role normalize + login gate
check('norm techn', auth_mail_normalize_role('technician') === 'techn');
check('norm user', auth_mail_normalize_role('User') === 'user');

$userActive = [
    'role' => 'user',
    'status' => 'active',
    'email_verified' => 1,
];
$g = auth_mail_login_gate($userActive);
check('gate user active', !empty($g['ok']));

$technPending = [
    'role' => 'techn',
    'status' => 'inactive',
    'email_verified' => 1,
];
$g = auth_mail_login_gate($technPending);
check('gate techn awaiting admin', empty($g['ok']) && !empty($g['awaiting_admin']));

$technActive = [
    'role' => 'techn',
    'status' => 'active',
    'email_verified' => 1,
];
$g = auth_mail_login_gate($technActive);
check('gate techn active', !empty($g['ok']));

$technUnverifiedActive = [
    'role' => 'techn',
    'status' => 'active',
    'email_verified' => 0,
];
$g = auth_mail_login_gate($technUnverifiedActive);
check('gate techn no legacy bypass', empty($g['ok']) && !empty($g['need_verify']));

// 4) Paper metrics helper
require_once $root . '/ai/metrics_paper.php';
$conf = [
    'hardware' => ['hardware' => 2, 'software' => 1, 'network' => 0, 'account' => 0, 'other' => 0],
    'software' => ['hardware' => 0, 'software' => 3, 'network' => 0, 'account' => 0, 'other' => 0],
    'network' => ['hardware' => 0, 'software' => 0, 'network' => 1, 'account' => 0, 'other' => 0],
    'account' => ['hardware' => 0, 'software' => 0, 'network' => 0, 'account' => 0, 'other' => 0],
    'other' => ['hardware' => 0, 'software' => 0, 'network' => 0, 'account' => 0, 'other' => 1],
];
$m = zpgc_metrics_from_confusion($conf, ['hardware', 'software', 'network', 'account', 'other']);
check('metrics accuracy', abs($m['accuracy'] - (7 / 8)) < 0.0001, (string) $m['accuracy']);
check('metrics table3 present', isset($m['table3']['accuracy_pct']));

// 5) DB connect (local)
$_SERVER['DB_HOST'] = $_SERVER['DB_HOST'] ?? 'localhost';
try {
    require $root . '/logic/config.php';
    check('db connect', isset($conn) && $conn instanceof mysqli && !$conn->connect_error);
    if (isset($conn) && $conn instanceof mysqli) {
        $tables = ['users', 'tickets', 'messages'];
        foreach ($tables as $t) {
            $r = @$conn->query("SHOW TABLES LIKE '{$t}'");
            check("table {$t}", $r && $r->num_rows > 0);
        }
        $col = @$conn->query("SHOW COLUMNS FROM users LIKE 'email_verified'");
        check('users.email_verified', $col && $col->num_rows > 0);
        // Simulate activate_verified_user logic without writing: prepare statements parse
        $p1 = $conn->prepare("UPDATE users SET email_verified = 1, status = 'inactive', role = 'techn' WHERE id = ?");
        check('prepare techn force inactive', (bool) $p1);
        if ($p1) {
            $p1->close();
        }
        $p2 = $conn->prepare("UPDATE users SET email_verified = 1, status = 'active', role = 'user' WHERE id = ?");
        check('prepare user auto active', (bool) $p2);
        if ($p2) {
            $p2->close();
        }
    }
} catch (Throwable $e) {
    check('db connect', false, $e->getMessage());
}

echo PHP_EOL . "Passed {$pass}, failed {$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);

<?php
/**
 * Technician application helpers (no SMTP required).
 * php ai/smoke_techn_apply.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('CLI only');
}

$root = dirname(__DIR__);
require_once $root . '/logic/session_config.php';
require_once $root . '/logic/config.php';
require_once $root . '/logic/auth_mail.php';
require_once $root . '/logic/techn_apply.php';

$fail = 0;
$pass = 0;
function ok(string $l, bool $c, string $d = ''): void
{
    global $fail, $pass;
    if ($c) {
        $pass++;
        echo "[PASS] {$l}" . ($d !== '' ? " — {$d}" : '') . PHP_EOL;
    } else {
        $fail++;
        echo "[FAIL] {$l}" . ($d !== '' ? " — {$d}" : '') . PHP_EOL;
    }
}

ok('hardware specialty', techn_apply_normalize_specialty('hardware') === 'Hardware');
ok('reject bogus specialty', techn_apply_normalize_specialty('plumber') === '');

techn_apply_ready($conn);
ok('table ready', true);

$stamp = bin2hex(random_bytes(3));
$email = "smoke.apply.{$stamp}@student.tsu.edu.ph";
$hash = password_hash('TestPass123!', PASSWORD_DEFAULT);
$fn = 'Apply';
$ln = 'Smoke';
$role = 'techn';
$status = 'inactive';
$v = 1;
$stmt = $conn->prepare(
    'INSERT INTO users (first_name, last_name, email, password, role, status, email_verified)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('ssssssi', $fn, $ln, $email, $hash, $role, $status, $v);
$stmt->execute();
$userId = (int) $conn->insert_id;
$stmt->close();
ok('insert techn', $userId > 0);

$spec = 'Hardware';
$stored = 'none.pdf';
$orig = 'resume.pdf';
$mime = 'application/pdf';
$appStatus = 'pending';
$ins = $conn->prepare(
    'INSERT INTO technician_applications
     (user_id, specialty, resume_stored_name, resume_original_name, resume_mime, status)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$ins->bind_param('isssss', $userId, $spec, $stored, $orig, $mime, $appStatus);
$okIns = $ins->execute();
$ins->close();
ok('insert pending app', $okIns);

$app = techn_apply_get_for_user($conn, $userId);
ok('get pending app', $app && ($app['status'] ?? '') === 'pending');

$n = techn_apply_expire_stale($conn);
ok('pending not expired', $n === 0);

$id = (int) $app['id'];
$prop = 'Network';
$up = $conn->prepare(
    "UPDATE technician_applications
     SET proposed_specialty = ?, status = 'awaiting_role_change',
         role_change_expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR)
     WHERE id = ?"
);
$up->bind_param('si', $prop, $id);
$up->execute();
$up->close();

$n = techn_apply_expire_stale($conn);
ok('stale role-change removed', $n === 1, (string) $n);
$gone = techn_apply_get_for_user($conn, $userId);
ok('app deleted after timeout', $gone === null);
$still = $conn->query('SELECT status FROM users WHERE id = ' . $userId)->fetch_assoc();
ok('user remains inactive', ($still['status'] ?? '') === 'inactive');

$ins = $conn->prepare(
    'INSERT INTO technician_applications
     (user_id, specialty, resume_stored_name, resume_original_name, resume_mime, status)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$ins->bind_param('isssss', $userId, $spec, $stored, $orig, $mime, $appStatus);
ok('re-apply after delete', $ins->execute());
$ins->close();

$r = techn_apply_approve_account($conn, $userId);
ok('approve activates', !empty($r['ok']));
$st = $conn->query('SELECT status FROM users WHERE id = ' . $userId)->fetch_assoc();
ok('user active after approve', ($st['status'] ?? '') === 'active');
$app = techn_apply_get_for_user($conn, $userId);
ok('app marked approved', ($app['status'] ?? '') === 'approved');

$gate = auth_mail_login_gate([
    'role' => 'techn',
    'status' => 'inactive',
    'email_verified' => 1,
]);
ok('gate applicant_ok', empty($gate['ok']) && !empty($gate['applicant_ok']) && !empty($gate['awaiting_admin']));

$conn->query('DELETE FROM technician_applications WHERE user_id = ' . $userId);
$conn->query('DELETE FROM auth_tokens WHERE user_id = ' . $userId);
$conn->query('DELETE FROM users WHERE id = ' . $userId);
ok('cleanup', true);

echo PHP_EOL . "Passed {$pass}, failed {$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);

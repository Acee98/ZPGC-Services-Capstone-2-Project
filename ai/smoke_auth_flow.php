<?php
/**
 * DB integration smoke: signup→verify activate rules (no SMTP send).
 * php ai/smoke_auth_flow.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/logic/session_config.php';
require_once $root . '/logic/config.php';
require_once $root . '/logic/auth_mail.php';

auth_mail_ready($conn);

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

$stamp = bin2hex(random_bytes(3));
$userEmail = "smoke.user.{$stamp}@student.tsu.edu.ph";
$technEmail = "smoke.techn.{$stamp}@student.tsu.edu.ph";
$hash = password_hash('TestPass123!', PASSWORD_DEFAULT);

function insertInactive(mysqli $conn, string $email, string $role, string $hash): int
{
    $fn = 'Smoke';
    $ln = 'Tester';
    $status = 'inactive';
    $v = 0;
    $stmt = $conn->prepare(
        'INSERT INTO users (first_name, last_name, email, password, role, status, email_verified)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('ssssssi', $fn, $ln, $email, $hash, $role, $status, $v);
    $stmt->execute();
    $id = (int) $conn->insert_id;
    $stmt->close();
    return $id;
}

function cleanup(mysqli $conn, int $id): void
{
    if ($id <= 0) {
        return;
    }
    $conn->query('DELETE FROM auth_tokens WHERE user_id = ' . (int) $id);
    $conn->query('DELETE FROM users WHERE id = ' . (int) $id);
}

$userId = insertInactive($conn, $userEmail, 'user', $hash);
$technId = insertInactive($conn, $technEmail, 'techn', $hash);
ok('insert user', $userId > 0, (string) $userId);
ok('insert techn', $technId > 0, (string) $technId);

$rUser = auth_mail_activate_verified_user($conn, $userId);
ok('user activate ok', !empty($rUser['ok']) && empty($rUser['awaiting_admin']));
$st = $conn->query('SELECT status, email_verified, role FROM users WHERE id = ' . $userId)->fetch_assoc();
ok('user status active', ($st['status'] ?? '') === 'active' && (int) $st['email_verified'] === 1, json_encode($st));

$rTechn = auth_mail_activate_verified_user($conn, $technId);
ok('techn activate awaiting admin', !empty($rTechn['ok']) && !empty($rTechn['awaiting_admin']));
$st = $conn->query('SELECT status, email_verified, role FROM users WHERE id = ' . $technId)->fetch_assoc();
ok(
    'techn forced inactive after verify',
    ($st['status'] ?? '') === 'inactive' && (int) $st['email_verified'] === 1,
    json_encode($st)
);

$gate = auth_mail_login_gate([
    'role' => $st['role'],
    'status' => $st['status'],
    'email_verified' => (int) $st['email_verified'],
]);
ok('techn cannot login yet', empty($gate['ok']) && !empty($gate['awaiting_admin']), json_encode($gate));

$upd = $conn->prepare("UPDATE users SET status = 'active', email_verified = 1 WHERE id = ?");
$upd->bind_param('i', $technId);
$upd->execute();
$upd->close();
$st = $conn->query('SELECT status, email_verified, role FROM users WHERE id = ' . $technId)->fetch_assoc();
$gate = auth_mail_login_gate([
    'role' => $st['role'],
    'status' => $st['status'],
    'email_verified' => (int) $st['email_verified'],
]);
ok('techn can login after admin activate', !empty($gate['ok']), json_encode($gate));
ok('csrf token non-empty', zpgc_csrf_token() !== '');

cleanup($conn, $userId);
cleanup($conn, $technId);
ok('cleanup done', true);

echo PHP_EOL . "Passed {$pass}, failed {$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);

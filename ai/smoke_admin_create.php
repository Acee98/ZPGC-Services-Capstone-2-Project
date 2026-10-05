<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/logic/session_config.php';
require_once dirname(__DIR__) . '/logic/config.php';
require_once dirname(__DIR__) . '/logic/auth_mail.php';

$email = 'admin.created.techn.' . time() . '@tsu.edu.ph';
$hash = password_hash('TestPass123!', PASSWORD_DEFAULT);
$ev = 1;
$st = 'active';
$role = 'techn';
$fn = 'A';
$ln = 'T';
$stmt = $conn->prepare(
    'INSERT INTO users (first_name, last_name, email, password, role, status, email_verified)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('ssssssi', $fn, $ln, $email, $hash, $role, $st, $ev);
$stmt->execute();
$id = (int) $conn->insert_id;
$stmt->close();

$user = $conn->query('SELECT id, role, status, email_verified FROM users WHERE id = ' . $id)->fetch_assoc();
$gate = auth_mail_login_gate($user);
$ok = !empty($gate['ok']);
echo ($ok ? '[PASS]' : '[FAIL]') . ' admin-created techn can login — ' . json_encode($gate) . PHP_EOL;

// Also verify unrepaired active+unverified is blocked, then repaired.
$email2 = 'legacy.unverified.' . time() . '@tsu.edu.ph';
$stmt = $conn->prepare(
    'INSERT INTO users (first_name, last_name, email, password, role, status, email_verified)
     VALUES (?, ?, ?, ?, ?, ?, 0)'
);
$stmt->bind_param('ssssss', $fn, $ln, $email2, $hash, $role, $st);
$stmt->execute();
$id2 = (int) $conn->insert_id;
$stmt->close();
$user2 = $conn->query('SELECT id, role, status, email_verified FROM users WHERE id = ' . $id2)->fetch_assoc();
$gate2 = auth_mail_login_gate($user2);
$blocked = empty($gate2['ok']);
echo ($blocked ? '[PASS]' : '[FAIL]') . ' legacy unverified blocked — ' . json_encode($gate2) . PHP_EOL;

$conn->query('UPDATE users SET email_verified = 1 WHERE id = ' . $id2);
$user3 = $conn->query('SELECT id, role, status, email_verified FROM users WHERE id = ' . $id2)->fetch_assoc();
$gate3 = auth_mail_login_gate($user3);
$fixed = !empty($gate3['ok']);
echo ($fixed ? '[PASS]' : '[FAIL]') . ' utilities repair unlocks login — ' . json_encode($gate3) . PHP_EOL;

$conn->query('DELETE FROM users WHERE id IN (' . $id . ',' . $id2 . ')');
$fail = (!$ok || !$blocked || !$fixed) ? 1 : 0;
exit($fail);

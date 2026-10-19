<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'capacity.php';
require_role('admin');
zpgc_csrf_require();

$concurrent = max(1, min(40, (int) ($_POST['concurrent'] ?? 0)));
$rounds = max(1, min(8, (int) ($_POST['rounds'] ?? 0)));
$total = max(0, min(200, (int) ($_POST['total_n'] ?? 0)));
$okN = max(0, min($total, (int) ($_POST['ok_n'] ?? 0)));
$failN = max(0, $total - $okN);
$p50 = max(0, min(120000, (int) ($_POST['p50_ms'] ?? 0)));
$p95 = max(0, min(120000, (int) ($_POST['p95_ms'] ?? 0)));

if ($total <= 0 || $concurrent <= 0 || $rounds <= 0) {
    zpgc_json_send(false, ['message' => 'The capacity check did not return usable numbers.']);
}

$result = [
    'concurrent' => $concurrent,
    'rounds' => $rounds,
    'total_n' => $total,
    'ok_n' => $okN,
    'fail_n' => $failN,
    'p50_ms' => $p50,
    'p95_ms' => $p95,
    'note' => 'browser',
];
capacity_store($conn, $result);
$ranAt = date('Y-m-d H:i:s');
$last = 'Last run ' . $ranAt . ': ' . $okN . '/' . $total
    . ' requests succeeded · median ' . $p50 . ' ms · p95 ' . $p95 . ' ms.';
$sessions = 0;
if (is_file(__DIR__ . '/account_kind.php')) {
    require_once __DIR__ . '/account_kind.php';
    $sessions = account_active_sessions($conn, 15);
}
$evidence = $sessions . ' active sessions · ' . $okN . '/' . $total
    . ' health checks ok, p95 ' . $p95 . ' ms (' . $ranAt . ')';
$allOk = $failN === 0;
$message = $allOk
    ? ('Capacity check finished: ' . $okN . '/' . $total . ' succeeded. Median ' . $p50 . ' ms, p95 ' . $p95 . ' ms.')
    : ('Capacity check finished: ' . $okN . '/' . $total . ' succeeded (' . $failN . ' timed out). Median ' . $p50 . ' ms, p95 ' . $p95 . ' ms.');

zpgc_json_send(true, [
    'message' => $message,
    'tone' => $allOk ? 'ok' : 'warn',
    'last_run' => $last,
    'evidence' => $evidence,
]);

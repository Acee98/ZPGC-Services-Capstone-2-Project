<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'capacity.php';
require_role('admin');
zpgc_csrf_require();

if (!isset($_POST['run_capacity_check'])) {
    header('Location: ../pages/admin.php?tab=performance');
    exit();
}

$url = capacity_health_url();
$result = capacity_run_check($url, 20, 3);
capacity_store($conn, $result);
if (!empty($result['ok'])) {
    $_SESSION['capacity_flash'] = 'Capacity check finished: '
        . (int) $result['ok_n'] . '/' . (int) $result['total_n']
        . ' requests succeeded. Median '
        . (int) $result['p50_ms'] . ' ms, p95 '
        . (int) $result['p95_ms'] . ' ms ('
        . (int) $result['concurrent'] . ' concurrent × '
        . (int) $result['rounds'] . ' rounds).';
} else {
    $_SESSION['capacity_error'] = ($result['error'] !== '' ? $result['error'] : 'Some health checks failed.')
        . ' ' . (int) $result['ok_n'] . '/' . (int) $result['total_n'] . ' succeeded.';
}
header('Location: ../pages/admin.php?tab=performance');
exit();

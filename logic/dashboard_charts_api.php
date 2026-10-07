<?php

require_once 'session_config.php';
require_once 'config.php';
require_once 'dashboard_stats.php';
require_role('admin');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$range = dashboard_normalize_range($_GET['range'] ?? 'week');
$data = dashboard_chart_data($conn, $range);

echo json_encode([
    'ok' => true,
    'range' => $range,
    'range_label' => dashboard_range_label($range),
    'data' => $data,
], JSON_UNESCAPED_UNICODE);
exit();

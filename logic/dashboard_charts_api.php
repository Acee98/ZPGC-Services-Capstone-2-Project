<?php

require_once 'session_config.php';
require_once 'config.php';
require_once 'dashboard_stats.php';
require_role('admin');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$from = dashboard_parse_ymd($_GET['from'] ?? '');
$to = dashboard_parse_ymd($_GET['to'] ?? '');
$range = dashboard_normalize_range($_GET['range'] ?? 'week');
$data = dashboard_chart_data($conn, $range, $from, $to);

echo json_encode([
    'ok' => true,
    'range' => $data['range'] ?? $range,
    'range_label' => $data['range_label'] ?? dashboard_range_label($range, $from, $to),
    'from' => $data['from'] ?? $from,
    'to' => $data['to'] ?? $to,
    'data' => $data,
], JSON_UNESCAPED_UNICODE);
exit();

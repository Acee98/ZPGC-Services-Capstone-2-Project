<?php
/**
 * Stream a matplotlib PNG for one dashboard chart.
 * GET ?type=report|categories|severity|satisfaction
 */
require_once 'session_config.php';
require_once 'config.php';
require_once 'dashboard_stats.php';
require_role('admin');

$type = strtolower(trim((string) ($_GET['type'] ?? '')));
$allowed = ['report', 'categories', 'severity', 'satisfaction'];
if (!in_array($type, $allowed, true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid chart type';
    exit();
}

$stats = dashboard_chart_data($conn);
$map = [
    'report' => $stats['report'],
    'categories' => $stats['categories'],
    'severity' => $stats['severity'],
    'satisfaction' => $stats['satisfaction'],
];

$body = json_encode([
    'type' => $type,
    'payload' => $map[$type],
]);

$url = 'http://127.0.0.1:5000/charts/png';
$png = false;
$error = '';

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 15,
    ]);
    $png = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno !== 0 || $png === false || $status < 200 || $status >= 300) {
        $error = 'matplotlib chart service unavailable (is Flask running?)';
        $png = false;
    }
} else {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $body,
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);
    $png = @file_get_contents($url, false, $ctx);
    if ($png === false) {
        $error = 'matplotlib chart service unavailable (is Flask running?)';
    }
}

if ($png === false) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo $error !== '' ? $error : 'Chart render failed';
    exit();
}

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
echo $png;
exit();

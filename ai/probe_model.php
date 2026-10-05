<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found.';
    exit;
}
require_once dirname(__DIR__) . '/logic/ai_classify.php';
$model = $argv[1] ?? 'gpt-5-mini';
putenv('OPENAI_MODEL=' . $model);
$_ENV['OPENAI_MODEL'] = $model;
$_SERVER['OPENAI_MODEL'] = $model;
$r = ai_openai_classify('Cannot connect to Wi-Fi', 'Campus wifi drops every few minutes');
echo json_encode([
    'model' => ai_openai_model(),
    'ok' => $r['ok'] ?? false,
    'method' => $r['method'] ?? null,
    'category' => $r['category'] ?? null,
    'priority' => $r['priority'] ?? null,
    'error' => $r['error'] ?? null,
    'fallback_reason' => $r['fallback_reason'] ?? null,
], JSON_PRETTY_PRINT) . PHP_EOL;

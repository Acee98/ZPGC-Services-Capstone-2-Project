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
$chat = ai_openai_chat([
    ['role' => 'system', 'content' => 'Reply with JSON only: {"category":"network","priority":"critical"}'],
    ['role' => 'user', 'content' => 'Wi-Fi down'],
], 300, 0.2);
echo json_encode($chat, JSON_PRETTY_PRINT) . PHP_EOL;

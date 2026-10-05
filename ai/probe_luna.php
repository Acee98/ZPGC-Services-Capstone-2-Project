<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found.';
    exit;
}
require_once dirname(__DIR__) . '/logic/ai_classify.php';
$r = ai_openai_classify('WiFi not working', 'Cannot connect to campus wireless network');
echo json_encode([
    'ok' => $r['ok'] ?? null,
    'method' => $r['method'] ?? null,
    'error' => $r['error'] ?? null,
    'fallback_reason' => $r['fallback_reason'] ?? null,
    'model' => $r['model'] ?? null,
    'category' => $r['category'] ?? null,
    'priority' => $r['priority'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

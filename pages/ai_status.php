<?php
/**
 * Admin-only AI diagnostics for Azure / XAMPP.
 * Open: /pages/ai_status.php while logged in as admin.
 */
require_once __DIR__ . '/../logic/session_config.php';
require_once __DIR__ . '/../logic/config.php';
require_once __DIR__ . '/../logic/ai_classify.php';
require_role('admin');

header('Content-Type: application/json; charset=utf-8');

$key = ai_openai_key();
$masked = '';
if ($key !== '') {
    $masked = substr($key, 0, 7) . '…' . substr($key, -4);
}

$probe = null;
if (isset($_GET['probe']) && $_GET['probe'] === '1') {
    $probe = ai_classify_ticket('WiFi not working in lab', 'Cannot connect to campus wireless network');
    // Never echo raw model dumps with secrets; keep fields useful for debugging.
    if (isset($probe['raw'])) {
        $probe['raw'] = is_string($probe['raw']) ? substr($probe['raw'], 0, 400) : null;
    }
}

echo json_encode([
    'ok' => true,
    'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
    'is_local_host' => ai_is_local_host(),
    'flask_base' => ai_classifier_base(),
    'openai_key_present' => $key !== '',
    'openai_key_masked' => $masked,
    'openai_key_looks_valid' => $key !== '' && (str_starts_with($key, 'sk-') || str_starts_with($key, 'sk-proj-')),
    'openai_model' => ai_openai_model(),
    'openai_api_base' => rtrim(ai_openai_env('OPENAI_API_BASE', 'https://api.openai.com/v1'), '/') ?: 'https://api.openai.com/v1',
    'openai_completions_url' => ai_openai_completions_url(),
    'openai_reasoning_effort' => ai_openai_env('OPENAI_REASONING_EFFORT', 'none'),
    'curl_enabled' => function_exists('curl_init'),
    'getenv_OPENAI_API_KEY' => getenv('OPENAI_API_KEY') !== false && trim((string) getenv('OPENAI_API_KEY')) !== '',
    'server_OPENAI_API_KEY' => isset($_SERVER['OPENAI_API_KEY']) && trim((string) $_SERVER['OPENAI_API_KEY']) !== '',
    'probe' => $probe,
    'hint' => 'Add ?probe=1 to run a live classify call. Expect probe.method = openai when Luna works.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

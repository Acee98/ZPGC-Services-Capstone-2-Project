<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'ai_classify.php';
require_role('user');
zpgc_csrf_require();

if (!zpgc_rate_limit('ai_suggest', 20, 900)) {
    http_response_code(429);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Too many AI requests. Try again in a few minutes.']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    $data = $_POST;
}

$subject = trim((string) ($data['subject'] ?? ''));
$description = trim((string) ($data['description'] ?? ''));

if ($subject === '' && $description === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Enter a subject or description first.']);
    exit();
}

$out = ai_classify_ticket($subject, $description);
echo json_encode($out);
exit();

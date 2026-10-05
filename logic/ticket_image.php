<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'ticket_files.php';

// Image requests must not redirect to HTML login (breaks <img> tags).
if (!isset($_SESSION['email']) || trim((string) $_SESSION['email']) === '') {
    http_response_code(401);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'login required';
    exit();
}

$id = (int) ($_GET['id'] ?? 0);
$size = strtolower(trim((string) ($_GET['size'] ?? 'full')));
if (!in_array($size, ['thumb', 'full'], true)) {
    $size = 'full';
}
$userId = current_user_id($conn);
$role = strtolower((string) ($_SESSION['role'] ?? ''));
ticket_files_ready($conn);

$stmt = $conn->prepare(
    'SELECT a.id, a.stored_name, t.user_id, t.assigned_to
     FROM ticket_attachments a
     INNER JOIN tickets t ON t.id = a.ticket_id
     WHERE a.id = ? LIMIT 1'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'not found';
    exit();
}

$allowed = ($role === 'admin')
    || ($role === 'user' && (int) $row['user_id'] === $userId)
    || ($role === 'techn' && (int) $row['assigned_to'] === $userId);
if (!$allowed) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'forbidden';
    exit();
}

$loaded = ticket_load_attachment_bytes($conn, $id);
if ($loaded === null || empty($loaded['bytes'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'file missing';
    exit();
}

$bytes = $loaded['bytes'];
$mime = $loaded['mime'] !== '' ? $loaded['mime'] : 'application/octet-stream';
$path = (string) ($loaded['path'] ?? '');

// Prefer thumb file when requested and GD cache exists; otherwise original bytes.
if ($size === 'thumb' && $path !== '' && is_file($path)) {
    $thumbPath = ticket_ensure_thumb($path);
    if (is_string($thumbPath) && is_file($thumbPath) && $thumbPath !== $path) {
        $thumbBytes = @file_get_contents($thumbPath);
        if ($thumbBytes !== false && $thumbBytes !== '') {
            $bytes = $thumbBytes;
            $mime = 'image/jpeg';
            $path = $thumbPath;
        }
    }
}

$mtime = ($path !== '' && is_file($path)) ? (int) @filemtime($path) : time();
$etag = '"' . sha1($id . '|' . $size . '|' . strlen($bytes) . '|' . $mtime) . '"';
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=604800');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

$ifNoneMatch = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
$ifModSince = strtotime((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
if ($ifNoneMatch === $etag || ($ifModSince && $ifModSince >= $mtime)) {
    http_response_code(304);
    exit();
}

header('Content-Length: ' . strlen($bytes));
echo $bytes;
exit();

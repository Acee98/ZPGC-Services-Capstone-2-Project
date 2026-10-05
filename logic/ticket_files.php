<?php

if (!function_exists('ticket_is_azure_host')) {
    function ticket_is_azure_host()
    {
        if (!empty($_SERVER['WEBSITE_SITE_NAME']) || !empty($_SERVER['WEBSITE_HOSTNAME'])) {
            return true;
        }
        if (getenv('WEBSITE_SITE_NAME') || getenv('WEBSITE_HOSTNAME')) {
            return true;
        }
        // App Service Linux always has this path even when env vars are filtered.
        if (is_dir('/home/site/wwwroot')) {
            return true;
        }
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return $host !== '' && str_ends_with($host, '.azurewebsites.net');
    }
}

if (!function_exists('ticket_upload_root')) {
    /**
     * Directory for ticket images.
     * Azure: /home/site/uploads/tickets (survives Git deploys).
     * Local: project uploads/tickets.
     */
    function ticket_upload_root()
    {
        static $dir = null;
        if ($dir !== null) {
            return $dir;
        }

        $env = '';
        $g = getenv('ZPGC_UPLOAD_ROOT');
        if ($g !== false && trim((string) $g) !== '') {
            $env = trim((string) $g);
        } elseif (!empty($_SERVER['ZPGC_UPLOAD_ROOT'])) {
            $env = trim((string) $_SERVER['ZPGC_UPLOAD_ROOT']);
        } elseif (!empty($_ENV['ZPGC_UPLOAD_ROOT'])) {
            $env = trim((string) $_ENV['ZPGC_UPLOAD_ROOT']);
        }

        if ($env !== '') {
            $dir = rtrim(str_replace('\\', '/', $env), '/') . '/tickets';
        } elseif (ticket_is_azure_host()) {
            $home = getenv('HOME');
            if ($home === false || trim((string) $home) === '') {
                $home = '/home';
            }
            $dir = rtrim(str_replace('\\', '/', (string) $home), '/') . '/site/uploads/tickets';
        } else {
            $dir = str_replace('\\', '/', dirname(__DIR__)) . '/uploads/tickets';
        }

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    function ticket_upload_legacy_root()
    {
        return str_replace('\\', '/', dirname(__DIR__)) . '/uploads/tickets';
    }

    function ticket_resolve_file($storedName)
    {
        $name = basename((string) $storedName);
        if ($name === '' || $name === '.' || $name === '..') {
            return '';
        }
        $candidates = [
            ticket_upload_root() . '/' . $name,
            ticket_upload_legacy_root() . '/' . $name,
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return $candidates[0];
    }

    function ticket_unlink_stored($storedName)
    {
        $name = basename((string) $storedName);
        if ($name === '' || $name === '.' || $name === '..') {
            return;
        }
        foreach ([ticket_upload_root(), ticket_upload_legacy_root()] as $root) {
            $path = $root . '/' . $name;
            if (is_file($path)) {
                @unlink($path);
            }
            $thumb = ticket_thumb_path($path);
            if (is_file($thumb)) {
                @unlink($thumb);
            }
        }
    }
}

if (!function_exists('ticket_files_ready')) {
    function ticket_files_ready(mysqli $conn)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (function_exists('zpgc_runtime_ddl_allowed') && !zpgc_runtime_ddl_allowed()) {
            $dir = ticket_upload_root();
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            return;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS ticket_attachments (
                id INT(11) NOT NULL AUTO_INCREMENT,
                ticket_id INT(11) NOT NULL,
                uploaded_by INT(11) NOT NULL,
                stored_name VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                file_blob LONGBLOB NULL,
                mime_type VARCHAR(64) NULL,
                PRIMARY KEY (id),
                KEY idx_attach_ticket (ticket_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $col = @$conn->query("SHOW COLUMNS FROM ticket_attachments LIKE 'created_at'");
        if (!$col || $col->num_rows === 0) {
            @$conn->query(
                'ALTER TABLE ticket_attachments
                 ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
            );
        }

        // Durable copy of each image so Azure redeploys cannot wipe mailbox photos.
        $blobCol = @$conn->query("SHOW COLUMNS FROM ticket_attachments LIKE 'file_blob'");
        if (!$blobCol || $blobCol->num_rows === 0) {
            @$conn->query('ALTER TABLE ticket_attachments ADD COLUMN file_blob LONGBLOB NULL');
        }
        $mimeCol = @$conn->query("SHOW COLUMNS FROM ticket_attachments LIKE 'mime_type'");
        if (!$mimeCol || $mimeCol->num_rows === 0) {
            @$conn->query('ALTER TABLE ticket_attachments ADD COLUMN mime_type VARCHAR(64) NULL');
        }

        $dir = ticket_upload_root();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $legacy = ticket_upload_legacy_root();
        if (!is_dir($legacy)) {
            @mkdir($legacy, 0775, true);
        }
    }

    function ticket_files_for(mysqli $conn, array $ticketIds)
    {
        ticket_files_ready($conn);
        $ids = array_values(array_filter(array_map('intval', $ticketIds)));
        if ($ids === []) {
            return [];
        }
        $list = implode(',', $ids);
        $grouped = [];
        $result = $conn->query(
            "SELECT id, ticket_id, original_name FROM ticket_attachments
             WHERE ticket_id IN ($list) ORDER BY id ASC"
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $grouped[(int) $row['ticket_id']][] = $row;
            }
        }
        return $grouped;
    }

    function ticket_user_can_touch(mysqli $conn, $ticketId, $userId)
    {
        $stmt = $conn->prepare('SELECT id FROM tickets WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->bind_param('ii', $ticketId, $userId);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $ok;
    }

    function ticket_techn_can_touch(mysqli $conn, $ticketId, $userId)
    {
        $stmt = $conn->prepare('SELECT id FROM tickets WHERE id = ? AND assigned_to = ? LIMIT 1');
        $stmt->bind_param('ii', $ticketId, $userId);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $ok;
    }

    function ticket_save_upload(mysqli $conn, $ticketId, $userId)
    {
        ticket_files_ready($conn);
        if (!isset($_FILES['ticket_image']) || !is_array($_FILES['ticket_image'])) {
            return 'No image was received. The form did not send the file.';
        }
        $file = $_FILES['ticket_image'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return 'Choose an image first.';
        }
        $err = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            return 'Image is too large. Use JPG or PNG under 2 MB.';
        }
        if ($err !== UPLOAD_ERR_OK) {
            return 'The image could not be uploaded (error ' . $err . '). Try JPG or PNG under 2 MB.';
        }
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            return 'Images must be 2 MB or smaller.';
        }
        $info = @getimagesize((string) $file['tmp_name']);
        if ($info === false) {
            return 'Only image files can be attached.';
        }
        $ext = image_type_to_extension((int) $info[2], false);
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            return 'Only JPG, PNG, or WEBP images can be attached.';
        }

        $bytes = @file_get_contents((string) $file['tmp_name']);
        if ($bytes === false || $bytes === '') {
            return 'The image could not be read.';
        }
        $mime = (string) ($info['mime'] ?? 'application/octet-stream');

        $stored = $ticketId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = ticket_upload_root() . '/' . $stored;
        $savedDisk = @file_put_contents($dest, $bytes);
        if ($savedDisk === false) {
            // Last resort: keep wwwroot copy so local/dev still works.
            $dest = ticket_upload_legacy_root() . '/' . $stored;
            $savedDisk = @file_put_contents($dest, $bytes);
        }
        if ($savedDisk !== false) {
            // Mirror into the other root when possible (Azure durable + wwwroot).
            $other = (strpos($dest, ticket_upload_root()) === 0)
                ? ticket_upload_legacy_root() . '/' . $stored
                : ticket_upload_root() . '/' . $stored;
            if ($other !== $dest && !is_file($other)) {
                @file_put_contents($other, $bytes);
            }
            ticket_ensure_thumb($dest);
        }

        $original = basename((string) ($file['name'] ?? 'image'));
        // Store bytes in MySQL — survives Azure redeploys. Use string bind (reliable ≤2MB).
        $stmt = $conn->prepare(
            'INSERT INTO ticket_attachments
                (ticket_id, uploaded_by, stored_name, original_name, file_blob, mime_type)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return 'The image could not be saved.';
        }
        $stmt->bind_param('iissss', $ticketId, $userId, $stored, $original, $bytes, $mime);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        if (!$ok) {
            // Fallback without blob still keeps the row; disk may serve it.
            $stmt2 = $conn->prepare(
                'INSERT INTO ticket_attachments (ticket_id, uploaded_by, stored_name, original_name)
                 VALUES (?, ?, ?, ?)'
            );
            if ($stmt2) {
                $stmt2->bind_param('iiss', $ticketId, $userId, $stored, $original);
                $ok2 = $stmt2->execute();
                $stmt2->close();
                if ($ok2 && $savedDisk !== false) {
                    return '';
                }
            }
            return 'The image could not be saved' . ($err !== '' ? ' (' . $err . ')' : '') . '.';
        }
        return '';
    }

    /**
     * Load attachment bytes from disk or MySQL blob.
     * @return array{bytes:?string,mime:string,path:string}|null
     */
    function ticket_load_attachment_bytes(mysqli $conn, $attachmentId)
    {
        $attachmentId = (int) $attachmentId;
        if ($attachmentId <= 0) {
            return null;
        }
        $stmt = $conn->prepare(
            'SELECT stored_name, file_blob, mime_type
             FROM ticket_attachments WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $attachmentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }

        $path = ticket_resolve_file((string) $row['stored_name']);
        $mime = trim((string) ($row['mime_type'] ?? ''));
        if ($path !== '' && is_file($path)) {
            $bytes = @file_get_contents($path);
            if ($bytes !== false && $bytes !== '') {
                if ($mime === '') {
                    $info = @getimagesize($path);
                    $mime = is_array($info) ? (string) ($info['mime'] ?? 'application/octet-stream') : 'application/octet-stream';
                }
                // Backfill DB blob when disk still has the file.
                if (!isset($row['file_blob']) || $row['file_blob'] === null || $row['file_blob'] === '') {
                    $up = $conn->prepare('UPDATE ticket_attachments SET file_blob = ?, mime_type = ? WHERE id = ?');
                    if ($up) {
                        $up->bind_param('ssi', $bytes, $mime, $attachmentId);
                        @$up->execute();
                        $up->close();
                    }
                }
                return ['bytes' => $bytes, 'mime' => $mime, 'path' => $path];
            }
        }

        $blob = $row['file_blob'] ?? null;
        if (is_resource($blob)) {
            $blob = stream_get_contents($blob);
        }
        if (is_object($blob) && method_exists($blob, '__toString')) {
            $blob = (string) $blob;
        }
        if (is_string($blob) && $blob !== '') {
            if ($mime === '') {
                $mime = 'application/octet-stream';
            }
            // Restore disk copy for faster later hits.
            if ($path !== '' && !is_file($path)) {
                @file_put_contents($path, $blob);
            }
            return ['bytes' => $blob, 'mime' => $mime, 'path' => $path];
        }

        return null;
    }

    /** Mailbox preview size (CSS shows ~280px; 560 covers retina). */
    function ticket_thumb_max_edge()
    {
        return 560;
    }

    function ticket_thumb_path($fullPath)
    {
        $dir = dirname((string) $fullPath);
        $base = pathinfo((string) $fullPath, PATHINFO_FILENAME);
        return $dir . DIRECTORY_SEPARATOR . $base . '_t.jpg';
    }

    function ticket_gd_load($path, $type)
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }
        switch ((int) $type) {
            case IMAGETYPE_JPEG:
                return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null;
            case IMAGETYPE_PNG:
                return function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null;
            case IMAGETYPE_GIF:
                return function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : null;
            case IMAGETYPE_WEBP:
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
            default:
                return null;
        }
    }

    /**
     * Build a JPEG thumbnail beside the original. Safe no-op if GD missing.
     * @return string thumbnail path when available, otherwise original path
     */
    function ticket_ensure_thumb($fullPath)
    {
        $fullPath = (string) $fullPath;
        if ($fullPath === '' || !is_file($fullPath)) {
            return $fullPath;
        }
        $thumb = ticket_thumb_path($fullPath);
        if (is_file($thumb) && filemtime($thumb) >= filemtime($fullPath)) {
            return $thumb;
        }
        $info = @getimagesize($fullPath);
        if ($info === false) {
            return $fullPath;
        }
        $srcW = (int) $info[0];
        $srcH = (int) $info[1];
        $type = (int) $info[2];
        if ($srcW < 1 || $srcH < 1) {
            return $fullPath;
        }
        $max = ticket_thumb_max_edge();
        $scale = min(1.0, $max / max($srcW, $srcH));
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));
        $src = ticket_gd_load($fullPath, $type);
        if (!$src) {
            return $fullPath;
        }
        $dst = imagecreatetruecolor($dstW, $dstH);
        if ($dst === false) {
            imagedestroy($src);
            return $fullPath;
        }
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        $ok = function_exists('imagejpeg') ? @imagejpeg($dst, $thumb, 78) : false;
        imagedestroy($src);
        imagedestroy($dst);
        return ($ok && is_file($thumb)) ? $thumb : $fullPath;
    }
}

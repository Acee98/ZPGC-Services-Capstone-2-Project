<?php

/**
 * AES-256-GCM for ticket subject, description, and AI guidance at rest.
 * Ciphertext is prefixed Z1: so older plaintext rows still load.
 */

if (!function_exists('ticket_crypto_key_bytes')) {
    function ticket_crypto_key_path()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'ticket.key';
    }

    function ticket_crypto_key_bytes()
    {
        static $key = null;
        if ($key !== null) {
            return $key;
        }
        $fromEnv = getenv('ZPGC_TICKET_KEY');
        if ($fromEnv === false || trim((string) $fromEnv) === '') {
            $fromEnv = (string) ($_SERVER['ZPGC_TICKET_KEY'] ?? $_ENV['ZPGC_TICKET_KEY'] ?? '');
        }
        $fromEnv = trim((string) $fromEnv);
        if ($fromEnv !== '') {
            $raw = ctype_xdigit($fromEnv) && (strlen($fromEnv) === 64)
                ? hex2bin($fromEnv)
                : hash('sha256', $fromEnv, true);
            if (is_string($raw) && strlen($raw) === 32) {
                $key = $raw;
                return $key;
            }
        }
        $path = ticket_crypto_key_path();
        if (is_file($path)) {
            $hex = trim((string) file_get_contents($path));
            $raw = ctype_xdigit($hex) && strlen($hex) === 64 ? hex2bin($hex) : false;
            if (is_string($raw) && strlen($raw) === 32) {
                $key = $raw;
                return $key;
            }
        }
        if (!function_exists('random_bytes')) {
            $key = '';
            return $key;
        }
        $raw = random_bytes(32);
        $ok = @file_put_contents($path, bin2hex($raw), LOCK_EX);
        if ($ok === false) {
            $key = $raw;
            return $key;
        }
        @chmod($path, 0600);
        $key = $raw;
        return $key;
    }

    function ticket_crypto_ready()
    {
        return function_exists('openssl_encrypt')
            && is_string(ticket_crypto_key_bytes())
            && strlen(ticket_crypto_key_bytes()) === 32;
    }

    function ticket_crypto_is_cipher($value)
    {
        return is_string($value) && strncmp($value, 'Z1:', 3) === 0;
    }

    function ticket_encrypt_text($plain)
    {
        $plain = (string) $plain;
        if ($plain === '' || ticket_crypto_is_cipher($plain) || !ticket_crypto_ready()) {
            return $plain;
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt(
            $plain,
            'aes-256-gcm',
            ticket_crypto_key_bytes(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        if ($cipher === false || strlen($tag) !== 16) {
            return $plain;
        }
        return 'Z1:' . base64_encode($iv . $tag . $cipher);
    }

    function ticket_decrypt_text($stored)
    {
        $stored = (string) $stored;
        if ($stored === '' || !ticket_crypto_is_cipher($stored) || !ticket_crypto_ready()) {
            return $stored;
        }
        $raw = base64_decode(substr($stored, 3), true);
        if ($raw === false || strlen($raw) < 29) {
            return $stored;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            ticket_crypto_key_bytes(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        return $plain === false ? $stored : $plain;
    }

    function ticket_subject_hash($subject)
    {
        $key = strtolower(trim((string) $subject));
        $key = preg_replace('/\s+/u', ' ', $key);
        if ($key === '') {
            return '';
        }
        return hash('sha256', $key);
    }

    function ticket_crypto_ensure_columns(mysqli $conn)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (function_exists('zpgc_runtime_ddl_allowed') && !zpgc_runtime_ddl_allowed()) {
            return;
        }
        $subj = $conn->query("SHOW COLUMNS FROM tickets LIKE 'subject'");
        if ($subj && ($info = $subj->fetch_assoc())) {
            $type = strtolower((string) ($info['Type'] ?? ''));
            if (strpos($type, 'varchar') !== false) {
                $conn->query('ALTER TABLE tickets MODIFY subject TEXT NOT NULL');
            }
        }
        $hash = $conn->query("SHOW COLUMNS FROM tickets LIKE 'subject_hash'");
        if (!$hash || $hash->num_rows === 0) {
            $conn->query(
                'ALTER TABLE tickets ADD COLUMN subject_hash CHAR(64) NULL DEFAULT NULL'
            );
        }
    }

    function ticket_reveal_row(array &$row)
    {
        foreach (['subject', 'description', 'ai_guidance'] as $field) {
            if (array_key_exists($field, $row)) {
                $row[$field] = ticket_decrypt_text((string) $row[$field]);
            }
        }
        return $row;
    }

    function ticket_reveal_rows(array &$rows)
    {
        foreach ($rows as &$row) {
            if (is_array($row)) {
                ticket_reveal_row($row);
            }
        }
        unset($row);
        return $rows;
    }

    function ticket_crypto_migrate_batch(mysqli $conn, $limit = 20)
    {
        if (!ticket_crypto_ready()) {
            return 0;
        }
        ticket_crypto_ensure_columns($conn);
        $limit = max(1, min(40, (int) $limit));
        $hasGuidance = false;
        if (function_exists('ticket_has_column')) {
            $hasGuidance = ticket_has_column($conn, 'ai_guidance');
        }
        $cols = 'id, subject, description' . ($hasGuidance ? ', ai_guidance' : '');
        $res = $conn->query(
            "SELECT {$cols} FROM tickets
             WHERE subject NOT LIKE 'Z1:%'
                OR description NOT LIKE 'Z1:%'
                OR subject_hash IS NULL
                OR subject_hash = ''
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        if (!$res) {
            return 0;
        }
        $n = 0;
        while ($row = $res->fetch_assoc()) {
            $id = (int) $row['id'];
            $subjectPlain = ticket_decrypt_text((string) $row['subject']);
            $descPlain = ticket_decrypt_text((string) $row['description']);
            $hash = ticket_subject_hash($subjectPlain);
            $encSub = ticket_encrypt_text($subjectPlain);
            $encDesc = ticket_encrypt_text($descPlain);
            if ($hasGuidance && array_key_exists('ai_guidance', $row)) {
                $encGuide = ticket_encrypt_text(ticket_decrypt_text((string) $row['ai_guidance']));
                $stmt = $conn->prepare(
                    'UPDATE tickets SET subject = ?, description = ?, ai_guidance = ?, subject_hash = ? WHERE id = ?'
                );
                if ($stmt) {
                    $stmt->bind_param('ssssi', $encSub, $encDesc, $encGuide, $hash, $id);
                    $stmt->execute();
                    $stmt->close();
                    $n++;
                }
            } else {
                $stmt = $conn->prepare(
                    'UPDATE tickets SET subject = ?, description = ?, subject_hash = ? WHERE id = ?'
                );
                if ($stmt) {
                    $stmt->bind_param('sssi', $encSub, $encDesc, $hash, $id);
                    $stmt->execute();
                    $stmt->close();
                    $n++;
                }
            }
        }
        return $n;
    }
}

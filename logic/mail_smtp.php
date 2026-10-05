<?php

require_once __DIR__ . '/mail_config.php';

if (!function_exists('mail_send')) {
    function mail_send($to, $subject, $plainBody, $htmlBody = '')
    {
        $cfg = mail_env_load();
        if (!mail_ready()) {
            return ['ok' => false, 'error' => 'Mail is not configured. Fill logic/mail.env.'];
        }
        $to = trim((string) $to);
        $subject = trim((string) $subject);
        $plainBody = (string) $plainBody;
        $htmlBody = (string) $htmlBody;
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Recipient email is invalid.'];
        }

        $host = $cfg['MAIL_HOST'];
        $port = (int) $cfg['MAIL_PORT'];
        $user = $cfg['MAIL_USERNAME'];
        $pass = $cfg['MAIL_PASSWORD'];
        // Gmail accepts mail only from the authenticated mailbox.
        $from = $user;
        $fromName = $cfg['MAIL_FROM_NAME'] !== '' ? $cfg['MAIL_FROM_NAME'] : 'ZPGC Services';

        // Prefer STARTTLS on 587; fall back to implicit SSL on 465 (common Azure/Gmail path).
        $attempts = [
            ['transport' => 'tcp://' . $host . ':' . ($port > 0 ? $port : 587), 'starttls' => true],
            ['transport' => 'ssl://' . $host . ':465', 'starttls' => false],
        ];
        $lastError = 'Could not connect to SMTP.';
        foreach ($attempts as $attempt) {
            $result = mail_send_via_socket(
                $attempt['transport'],
                $attempt['starttls'],
                $user,
                $pass,
                $from,
                $fromName,
                $to,
                $subject,
                $plainBody,
                $htmlBody
            );
            if ($result['ok']) {
                return $result;
            }
            $lastError = $result['error'];
        }
        return ['ok' => false, 'error' => $lastError];
    }

    function mail_send_via_socket(
        $transport,
        $starttls,
        $user,
        $pass,
        $from,
        $fromName,
        $to,
        $subject,
        $plainBody,
        $htmlBody = ''
    ) {
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            $transport,
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT
        );
        if (!$socket) {
            return ['ok' => false, 'error' => 'Could not connect to SMTP ' . $transport . ' (' . $errstr . ').'];
        }
        stream_set_timeout($socket, 20);

        $read = function () use ($socket) {
            $data = '';
            while ($line = fgets($socket, 515)) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $write = function ($cmd) use ($socket) {
            fwrite($socket, $cmd . "\r\n");
        };
        $expect = function ($prefix, $label) use ($read) {
            $resp = $read();
            if (strpos($resp, $prefix) !== 0) {
                throw new RuntimeException($label . ': ' . trim($resp));
            }
            return $resp;
        };

        try {
            $expect('220', 'SMTP greeting');
            $write('EHLO zpgc-services');
            $expect('250', 'EHLO');
            if ($starttls) {
                $write('STARTTLS');
                $expect('220', 'STARTTLS');
                $crypto = defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')
                    ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                    : STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (!stream_socket_enable_crypto($socket, true, $crypto)) {
                    throw new RuntimeException('TLS handshake failed on ' . $transport);
                }
                $write('EHLO zpgc-services');
                $expect('250', 'EHLO after TLS');
            }
            $write('AUTH LOGIN');
            $expect('334', 'AUTH LOGIN');
            $write(base64_encode($user));
            $expect('334', 'AUTH username');
            $write(base64_encode($pass));
            $expect('235', 'AUTH password');
            $write('MAIL FROM:<' . $from . '>');
            $expect('250', 'MAIL FROM');
            $write('RCPT TO:<' . $to . '>');
            $expect('250', 'RCPT TO');
            $write('DATA');
            $expect('354', 'DATA');

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
            $headers = [
                'Date: ' . date('r'),
                'From: ' . $encodedFromName . ' <' . $from . '>',
                'To: <' . $to . '>',
                'Subject: ' . $encodedSubject,
                'MIME-Version: 1.0',
                'X-Mailer: ZPGC-Services-PHP',
            ];
            $plain = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $plainBody));
            $plain = str_replace("\n", "\r\n", $plain);
            if ($htmlBody !== '') {
                $boundary = 'zpgc_' . bin2hex(random_bytes(8));
                $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
                $html = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $htmlBody));
                $html = str_replace("\n", "\r\n", $html);
                $body = '--' . $boundary . "\r\n"
                    . "Content-Type: text/plain; charset=UTF-8\r\n"
                    . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                    . $plain . "\r\n"
                    . '--' . $boundary . "\r\n"
                    . "Content-Type: text/html; charset=UTF-8\r\n"
                    . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                    . $html . "\r\n"
                    . '--' . $boundary . '--';
            } else {
                $headers[] = 'Content-Type: text/plain; charset=UTF-8';
                $headers[] = 'Content-Transfer-Encoding: 8bit';
                $body = $plain;
            }
            $write(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.");
            $expect('250', 'Message body');
            $write('QUIT');
            fclose($socket);
            return ['ok' => true, 'error' => ''];
        } catch (Throwable $e) {
            fclose($socket);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

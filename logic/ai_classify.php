<?php
/**
 * Stage 7 — call local Flask classifier.
 */

if (!function_exists('ai_classifier_url')) {
    function ai_classifier_url()
    {
        return 'http://127.0.0.1:5000/classify';
    }

    function ai_classify_ticket($subject, $description)
    {
        $payload = json_encode([
            'subject' => (string) $subject,
            'description' => (string) $description,
        ]);

        $url = ai_classifier_url();
        $result = [
            'ok' => false,
            'category' => null,
            'priority' => null,
            'confidence' => null,
            'error' => null,
            'raw' => null,
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 5,
            ]);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno !== 0 || $body === false) {
                $result['error'] = 'Cannot reach AI service. Is Flask running on port 5000?';
                return $result;
            }
            $result['raw'] = $body;
            if ($status < 200 || $status >= 300) {
                $result['error'] = 'AI service returned HTTP ' . $status;
                return $result;
            }
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 5,
                    'ignore_errors' => true,
                ],
            ]);
            $body = @file_get_contents($url, false, $ctx);
            $result['raw'] = $body;
            if ($body === false) {
                $result['error'] = 'Cannot reach AI service. Is Flask running on port 5000?';
                return $result;
            }
        }

        $data = json_decode((string) $body, true);
        if (!is_array($data)) {
            $result['error'] = 'AI service returned invalid JSON.';
            return $result;
        }
        if (isset($data['error'])) {
            $result['error'] = (string) $data['error'];
            return $result;
        }

        $allowedCat = ['hardware', 'software', 'account', 'network', 'other'];
        $allowedPri = ['critical', 'moderate', 'low'];
        $cat = isset($data['category']) ? strtolower((string) $data['category']) : '';
        $pri = isset($data['priority']) ? strtolower((string) $data['priority']) : '';

        $result['ok'] = true;
        $result['category'] = in_array($cat, $allowedCat, true) ? $cat : null;
        $result['priority'] = in_array($pri, $allowedPri, true) ? $pri : null;
        $result['confidence'] = isset($data['confidence']) ? (float) $data['confidence'] : null;
        return $result;
    }
}

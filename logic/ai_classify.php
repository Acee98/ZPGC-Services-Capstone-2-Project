<?php
/**
 * Stage 7+ — call local Flask classifier (OpenAI gpt-5.6-luna or keyword fallback).
 */

if (!function_exists('ai_classifier_base')) {
    function ai_classifier_base()
    {
        return 'http://127.0.0.1:5000';
    }

    function ai_http_post_json($path, array $payload, $timeout = 12)
    {
        $url = ai_classifier_base() . $path;
        $body = json_encode($payload);
        $result = [
            'ok' => false,
            'error' => null,
            'raw' => null,
            'data' => null,
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => $timeout,
            ]);
            $resp = curl_exec($ch);
            $errno = curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno !== 0 || $resp === false) {
                $result['error'] = 'Cannot reach AI service. Is Flask running on port 5000?';
                return $result;
            }
            $result['raw'] = $resp;
            if ($status < 200 || $status >= 300) {
                $result['error'] = 'AI service returned HTTP ' . $status;
                return $result;
            }
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $body,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
            ]);
            $resp = @file_get_contents($url, false, $ctx);
            $result['raw'] = $resp;
            if ($resp === false) {
                $result['error'] = 'Cannot reach AI service. Is Flask running on port 5000?';
                return $result;
            }
        }

        $data = json_decode((string) $resp, true);
        if (!is_array($data)) {
            $result['error'] = 'AI service returned invalid JSON.';
            return $result;
        }
        if (!empty($data['error']) && empty($data['ok'])) {
            $result['error'] = (string) $data['error'];
            return $result;
        }

        $result['ok'] = true;
        $result['data'] = $data;
        return $result;
    }

    function ai_map_priority($pri)
    {
        $pri = strtolower(trim((string) $pri));
        if (in_array($pri, ['high', 'urgent', 'severe'], true)) {
            return 'critical';
        }
        if (in_array($pri, ['medium', 'med', 'normal'], true)) {
            return 'moderate';
        }
        if (in_array($pri, ['critical', 'moderate', 'low'], true)) {
            return $pri;
        }
        return null;
    }

    function ai_classify_ticket($subject, $description)
    {
        $result = [
            'ok' => false,
            'category' => null,
            'priority' => null,
            'confidence' => null,
            'method' => null,
            'model' => null,
            'error' => null,
            'raw' => null,
        ];

        $http = ai_http_post_json('/classify', [
            'subject' => (string) $subject,
            'title' => (string) $subject,
            'description' => (string) $description,
        ], 12);

        $result['raw'] = $http['raw'];
        if (!$http['ok']) {
            $result['error'] = $http['error'];
            return $result;
        }

        $data = $http['data'];
        $allowedCat = ['hardware', 'software', 'account', 'network', 'other'];
        $cat = isset($data['category']) ? strtolower((string) $data['category']) : '';
        $pri = ai_map_priority($data['priority'] ?? '');

        $result['ok'] = true;
        $result['category'] = in_array($cat, $allowedCat, true) ? $cat : null;
        $result['priority'] = $pri;
        $result['confidence'] = isset($data['confidence']) ? (float) $data['confidence'] : null;
        $result['method'] = isset($data['method']) ? (string) $data['method'] : null;
        $result['model'] = isset($data['model']) ? (string) $data['model'] : null;
        return $result;
    }

    function ai_troubleshoot_ticket($subject, $description, $category = 'other', $priority = 'low')
    {
        $result = [
            'ok' => false,
            'summary' => null,
            'steps' => [],
            'ask_technician_if' => null,
            'guidance_text' => null,
            'method' => null,
            'model' => null,
            'error' => null,
        ];

        $http = ai_http_post_json('/suggest', [
            'subject' => (string) $subject,
            'title' => (string) $subject,
            'description' => (string) $description,
            'category' => (string) $category,
            'priority' => (string) $priority,
        ], 20);

        if (!$http['ok']) {
            $result['error'] = $http['error'];
            return $result;
        }

        $data = $http['data'];
        $steps = isset($data['steps']) && is_array($data['steps']) ? $data['steps'] : [];
        $steps = array_values(array_filter(array_map(function ($s) {
            return trim((string) $s);
        }, $steps)));

        $summary = trim((string) ($data['summary'] ?? 'Try these steps first.'));
        $ask = trim((string) ($data['ask_technician_if'] ?? 'If the problem continues, request a technician.'));

        $lines = [$summary, ''];
        $n = 1;
        foreach ($steps as $step) {
            $lines[] = $n . '. ' . $step;
            $n++;
        }
        $lines[] = '';
        $lines[] = 'Ask a technician if: ' . $ask;

        $result['ok'] = true;
        $result['summary'] = $summary;
        $result['steps'] = $steps;
        $result['ask_technician_if'] = $ask;
        $result['guidance_text'] = implode("\n", $lines);
        $result['method'] = isset($data['method']) ? (string) $data['method'] : null;
        $result['model'] = isset($data['model']) ? (string) $data['model'] : null;
        return $result;
    }
}

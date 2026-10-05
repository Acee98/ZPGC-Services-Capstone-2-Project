<?php

if (!function_exists('ai_classifier_base')) {
    /**
     * Optional Flask helper URL.
     * Local XAMPP default: http://127.0.0.1:5000
     * Azure: leave unset and set OPENAI_API_KEY in App Settings (PHP calls OpenAI directly).
     */
    function ai_classifier_base()
    {
        $env = getenv('AI_CLASSIFIER_URL');
        if ($env !== false && trim((string) $env) !== '') {
            return rtrim(trim((string) $env), '/');
        }
        if (ai_is_local_host()) {
            return 'http://127.0.0.1:5000';
        }
        return '';
    }

    function ai_is_local_host()
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return $host === 'localhost'
            || str_starts_with($host, 'localhost:')
            || str_starts_with($host, '127.0.0.1')
            || str_ends_with($host, '.local');
    }

    function ai_openai_env($key, $default = '')
    {
        // Azure App Settings often appear in $_SERVER / $_ENV even when getenv() is empty.
        $candidates = [];
        $g = getenv($key);
        if ($g !== false) {
            $candidates[] = $g;
        }
        if (function_exists('getenv')) {
            $gl = getenv($key, true);
            if ($gl !== false) {
                $candidates[] = $gl;
            }
        }
        if (isset($_SERVER[$key])) {
            $candidates[] = $_SERVER[$key];
        }
        if (isset($_ENV[$key])) {
            $candidates[] = $_ENV[$key];
        }
        foreach ($candidates as $raw) {
            $val = trim((string) $raw);
            if ($val !== '') {
                return $val;
            }
        }

        static $fileEnv = null;
        if ($fileEnv === null) {
            $fileEnv = [];
            $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . '.env';
            if (is_file($path)) {
                $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        $line = trim((string) $line);
                        if ($line === '' || str_starts_with($line, '#')) {
                            continue;
                        }
                        if (!str_contains($line, '=')) {
                            continue;
                        }
                        [$k, $v] = explode('=', $line, 2);
                        $fileEnv[trim($k)] = trim($v, " \t\"'");
                    }
                }
            }
        }

        return isset($fileEnv[$key]) && $fileEnv[$key] !== '' ? (string) $fileEnv[$key] : $default;
    }

    function ai_openai_key()
    {
        $key = ai_openai_env('OPENAI_API_KEY', '');
        if ($key === '' || str_starts_with($key, 'sk-your-key')) {
            return '';
        }
        return $key;
    }

    function ai_openai_model()
    {
        $model = ai_openai_env('OPENAI_MODEL', 'gpt-5.6-luna');
        return $model !== '' ? $model : 'gpt-5.6-luna';
    }

    /**
     * Chat Completions endpoint.
     * Default: https://api.openai.com/v1/chat/completions
     * Optional OPENAI_API_BASE (no trailing slash) becomes {base}/chat/completions.
     */
    function ai_openai_completions_url()
    {
        $base = rtrim(ai_openai_env('OPENAI_API_BASE', 'https://api.openai.com/v1'), '/');
        if ($base === '') {
            $base = 'https://api.openai.com/v1';
        }
        return $base . '/chat/completions';
    }

    function ai_strip_json_fence($text)
    {
        $text = trim((string) $text);
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
            $text = preg_replace('/\s*```$/', '', (string) $text);
        }
        return trim((string) $text);
    }

    function ai_http_post_json($path, array $payload, $timeout = 12)
    {
        $base = ai_classifier_base();
        $result = [
            'ok' => false,
            'error' => null,
            'raw' => null,
            'data' => null,
        ];
        if ($base === '') {
            $result['error'] = 'Flask classifier URL not configured.';
            return $result;
        }

        $url = $base . $path;
        $body = json_encode($payload);

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

    function ai_openai_model_locks_temperature($model)
    {
        $m = strtolower(trim((string) $model));
        if ($m === '') {
            return false;
        }
        // GPT-5 / o-series / Luna reject custom temperature (default 1 only).
        return (bool) preg_match('/^(gpt-5|o[0-9]|.*luna)/', $m);
    }

    function ai_openai_chat(array $messages, $maxTokens = 200, $temperature = 0.2)
    {
        $result = [
            'ok' => false,
            'error' => null,
            'content' => null,
            'fallback_reason' => null,
        ];
        $key = ai_openai_key();
        if ($key === '') {
            $result['error'] = 'OPENAI_API_KEY is not set.';
            $result['fallback_reason'] = 'no_key';
            return $result;
        }
        if (!function_exists('curl_init')) {
            $result['error'] = 'PHP curl extension is required for OpenAI.';
            $result['fallback_reason'] = 'openai_error';
            return $result;
        }

        $model = ai_openai_model();
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => (int) $maxTokens,
        ];
        if (!ai_openai_model_locks_temperature($model)) {
            $payload['temperature'] = $temperature;
        }
        $effort = strtolower(ai_openai_env('OPENAI_REASONING_EFFORT', 'none'));
        if ($effort !== '' && $effort !== 'default' && $effort !== 'none') {
            $payload['reasoning_effort'] = $effort;
        }

        $attempt = ai_openai_http_json($key, $payload);
        // Some accounts/models reject max_completion_tokens; retry with max_tokens.
        if (!$attempt['ok'] && str_contains(strtolower((string) $attempt['error']), 'max_completion_tokens')) {
            unset($payload['max_completion_tokens']);
            $payload['max_tokens'] = (int) $maxTokens;
            $attempt = ai_openai_http_json($key, $payload);
        }
        // Newer models reject custom temperature; retry without it.
        if (
            !$attempt['ok']
            && isset($payload['temperature'])
            && str_contains(strtolower((string) $attempt['error']), 'temperature')
        ) {
            unset($payload['temperature']);
            $attempt = ai_openai_http_json($key, $payload);
        }

        if (!$attempt['ok']) {
            $result['error'] = $attempt['error'];
            $result['fallback_reason'] = $attempt['fallback_reason'];
            return $result;
        }

        $result['ok'] = true;
        $result['content'] = ai_strip_json_fence((string) $attempt['content']);
        return $result;
    }

    function ai_openai_http_json($key, array $payload)
    {
        $out = [
            'ok' => false,
            'error' => null,
            'content' => null,
            'fallback_reason' => 'openai_error',
        ];
        $body = json_encode($payload);
        $resp = false;
        $status = 0;
        $endpoint = ai_openai_completions_url();

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            $curlOpts = [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $key,
                ],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 45,
            ];
            // XAMPP often has empty curl.cainfo; use bundled Mozilla CA file when present.
            $caBundle = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ai' . DIRECTORY_SEPARATOR . 'cacert.pem';
            if (is_file($caBundle)) {
                $curlOpts[CURLOPT_CAINFO] = $caBundle;
                $curlOpts[CURLOPT_SSL_VERIFYPEER] = true;
            }
            curl_setopt_array($ch, $curlOpts);
            $resp = curl_exec($ch);
            $errno = curl_errno($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = curl_error($ch);
            curl_close($ch);
            if ($errno !== 0 || $resp === false) {
                $out['error'] = 'OpenAI request failed (network/curl): ' . ($cerr !== '' ? $cerr : 'errno ' . $errno);
                return $out;
            }
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$key}\r\n",
                    'content' => $body,
                    'timeout' => 45,
                    'ignore_errors' => true,
                ],
            ]);
            $resp = @file_get_contents($endpoint, false, $ctx);
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
                $status = (int) $m[1];
            }
            if ($resp === false) {
                $out['error'] = 'OpenAI request failed (network/stream).';
                return $out;
            }
        }

        $data = json_decode((string) $resp, true);
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            $msg = is_array($data) ? (string) ($data['error']['message'] ?? $resp) : (string) $resp;
            $out['error'] = 'OpenAI HTTP ' . $status . ': ' . substr($msg, 0, 280);
            $low = strtolower($msg);
            if (str_contains($low, 'insufficient_quota') || str_contains($low, 'credit_balance_exhausted')) {
                $out['fallback_reason'] = 'quota';
            }
            return $out;
        }

        $out['ok'] = true;
        $out['content'] = (string) ($data['choices'][0]['message']['content'] ?? '');
        $out['fallback_reason'] = null;
        return $out;
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

    function ai_keyword_classify($subject, $description)
    {
        $blob = strtolower(trim((string) $subject . ' ' . (string) $description));
        $category = 'other';
        $priority = 'moderate';
        $rules = [
            ['/\b(password|login|otp|2fa|account|username|lock(ed)?|reset)\b/i', 'account', 'critical'],
            ['/\b(wifi|wi-?fi|internet|network|lan|vpn|router|dns|offline|disconnect)\b/i', 'network', 'critical'],
            ['/\b(blue\s*screen|bsod|overheat|fan|battery|charger|keyboard|mouse|monitor|printer|hardware|ram|ssd|hdd)\b/i', 'hardware', 'moderate'],
            ['/\b(install|update|crash|freeze|slow|software|app|excel|word|chrome|outlook|license|activation)\b/i', 'software', 'moderate'],
            ['/\b(urgent|critical|down|outage|cannot\s+work|emergency)\b/i', 'other', 'critical'],
        ];
        foreach ($rules as $rule) {
            if (preg_match($rule[0], $blob)) {
                $category = $rule[1];
                $priority = $rule[2];
                break;
            }
        }
        if (preg_match('/\b(urgent|critical|emergency|cannot\s+work|outage)\b/i', $blob)) {
            $priority = 'critical';
        } elseif (preg_match('/\b(asap|important|blocking)\b/i', $blob) && $priority === 'moderate') {
            $priority = 'critical';
        } elseif (preg_match('/\b(minor|small|question|how\s+to|curious)\b/i', $blob)) {
            $priority = 'low';
        }

        return [
            'ok' => true,
            'category' => $category,
            'priority' => $priority,
            'urgency' => $priority === 'low' ? 1 : ($priority === 'critical' ? 3 : 2),
            'impact' => 1,
            'confidence' => 0.55,
            'method' => 'keyword',
            'model' => null,
            'error' => null,
            'raw' => null,
        ];
    }

    function ai_openai_classify($subject, $description)
    {
        $system = 'You classify IT helpdesk tickets for a school campus (ZPGC). '
            . 'Reply with ONLY valid JSON: '
            . '{"category":"hardware|software|network|account|other",'
            . '"priority":"critical|moderate|low","confidence":0.0-1.0,'
            . '"rationale":"short reason"}';
        $user = "Title: {$subject}\nDescription: {$description}";
        $chat = ai_openai_chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], 200, 0.2);

        if (!$chat['ok']) {
            return [
                'ok' => false,
                'error' => $chat['error'],
                'fallback_reason' => $chat['fallback_reason'],
            ];
        }

        $data = json_decode((string) $chat['content'], true);
        if (!is_array($data)) {
            return [
                'ok' => false,
                'error' => 'OpenAI returned invalid JSON.',
                'fallback_reason' => 'openai_error',
            ];
        }

        $allowedCat = ['hardware', 'software', 'account', 'network', 'other'];
        $cat = strtolower((string) ($data['category'] ?? ''));
        $pri = ai_map_priority($data['priority'] ?? '');
        if (!in_array($cat, $allowedCat, true) || $pri === null) {
            return [
                'ok' => false,
                'error' => 'OpenAI JSON missing category/priority.',
                'fallback_reason' => 'openai_error',
            ];
        }

        return [
            'ok' => true,
            'category' => $cat,
            'priority' => $pri,
            'urgency' => $pri === 'low' ? 1 : ($pri === 'critical' ? 3 : 2),
            'impact' => 1,
            'confidence' => isset($data['confidence']) ? (float) $data['confidence'] : 0.8,
            'method' => 'openai',
            'model' => ai_openai_model(),
            'fallback_reason' => null,
            'error' => null,
            'raw' => $chat['content'],
        ];
    }

    function ai_openai_troubleshoot($subject, $description, $category, $priority)
    {
        $system = 'You are a campus IT helpdesk assistant. Give short, safe, step-by-step '
            . 'troubleshooting tips for LOW-priority tickets. No passwords, no '
            . 'destructive commands. Reply with ONLY valid JSON: '
            . '{"summary":"one sentence","steps":["step1","step2","step3"],'
            . '"ask_technician_if":"when to escalate"}';
        $user = "Category: {$category}\nPriority: {$priority}\n"
            . "Title: {$subject}\nDescription: {$description}";
        $chat = ai_openai_chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], 400, 0.4);

        if (!$chat['ok']) {
            return ['ok' => false, 'error' => $chat['error']];
        }

        $data = json_decode((string) $chat['content'], true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'OpenAI returned invalid JSON.'];
        }

        $steps = isset($data['steps']) && is_array($data['steps']) ? $data['steps'] : [];
        $steps = array_values(array_filter(array_map(static function ($s) {
            return trim((string) $s);
        }, $steps)));
        if ($steps === []) {
            $steps = ['Restart the device and try again.'];
        }

        $summary = trim((string) ($data['summary'] ?? 'Try these steps first.'));
        $ask = trim((string) ($data['ask_technician_if'] ?? 'If the problem continues, request a technician.'));
        $lines = [$summary, ''];
        $n = 1;
        foreach (array_slice($steps, 0, 6) as $step) {
            $lines[] = $n . '. ' . $step;
            $n++;
        }
        $lines[] = '';
        $lines[] = 'Ask a technician if: ' . $ask;

        return [
            'ok' => true,
            'summary' => $summary,
            'steps' => array_slice($steps, 0, 6),
            'ask_technician_if' => $ask,
            'guidance_text' => implode("\n", $lines),
            'method' => 'openai',
            'model' => ai_openai_model(),
            'error' => null,
        ];
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

        // Local classifier service, when configured.
        if (ai_classifier_base() !== '') {
            $http = ai_http_post_json('/classify', [
                'subject' => (string) $subject,
                'title' => (string) $subject,
                'description' => (string) $description,
            ], 12);
            $result['raw'] = $http['raw'];
            if ($http['ok']) {
                $data = $http['data'];
                $allowedCat = ['hardware', 'software', 'account', 'network', 'other'];
                $cat = isset($data['category']) ? strtolower((string) $data['category']) : '';
                $pri = ai_map_priority($data['priority'] ?? '');
                $result['ok'] = true;
                $result['category'] = in_array($cat, $allowedCat, true) ? $cat : null;
                $result['priority'] = $pri;
                $result['urgency'] = isset($data['urgency']) ? (int) $data['urgency'] : null;
                $result['impact'] = isset($data['impact']) ? (int) $data['impact'] : null;
                $result['confidence'] = isset($data['confidence']) ? (float) $data['confidence'] : null;
                $result['method'] = isset($data['method']) ? (string) $data['method'] : null;
                $result['model'] = isset($data['model']) ? (string) $data['model'] : null;
                $result['fallback_reason'] = isset($data['fallback_reason']) ? (string) $data['fallback_reason'] : null;
                return $result;
            }
        }

        // OpenAI API from PHP (env / App Settings).
        if (ai_openai_key() !== '') {
            $direct = ai_openai_classify($subject, $description);
            if (!empty($direct['ok'])) {
                return $direct;
            }
            $fallback = ai_keyword_classify($subject, $description);
            if (($direct['fallback_reason'] ?? '') === 'quota') {
                $fallback['method'] = 'kw-quota';
                $fallback['fallback_reason'] = 'quota';
            } else {
                $fallback['fallback_reason'] = (string) ($direct['fallback_reason'] ?? 'openai_error');
            }
            $fallback['error'] = (string) ($direct['error'] ?? '');
            return $fallback;
        }

        // Offline keyword fallback.
        return ai_keyword_classify($subject, $description);
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

        if (ai_classifier_base() !== '') {
            $http = ai_http_post_json('/suggest', [
                'subject' => (string) $subject,
                'title' => (string) $subject,
                'description' => (string) $description,
                'category' => (string) $category,
                'priority' => (string) $priority,
            ], 20);

            if ($http['ok']) {
                $data = $http['data'];
                $steps = isset($data['steps']) && is_array($data['steps']) ? $data['steps'] : [];
                $steps = array_values(array_filter(array_map(static function ($s) {
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

        if (ai_openai_key() !== '') {
            $direct = ai_openai_troubleshoot($subject, $description, $category, $priority);
            if (!empty($direct['ok'])) {
                return $direct;
            }
        }

        $result['ok'] = true;
        $result['summary'] = 'Quick self-help steps before a technician is assigned.';
        $result['steps'] = [
            'Restart the affected device or application.',
            'Check cables, Wi‑Fi, or account login details.',
            'Try again and note any error message.',
        ];
        $result['ask_technician_if'] = 'If these steps do not fix the issue, request a technician.';
        $result['guidance_text'] = $result['summary'] . "\n\n1. " . implode("\n2. ", $result['steps'])
            . "\n\nAsk a technician if: " . $result['ask_technician_if'];
        $result['method'] = 'keyword';
        $result['model'] = null;
        return $result;
    }
}

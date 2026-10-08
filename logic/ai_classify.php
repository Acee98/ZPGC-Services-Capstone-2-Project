<?php

require_once __DIR__ . '/severity_matrix.php';
require_once __DIR__ . '/ai_fewshot.php';

if (!function_exists('ai_classifier_base')) {
    /**
     * Optional Flask helper URL.
     * Only used when AI_CLASSIFIER_URL is set — never auto-probe localhost
     * (that hung ticket submit for ~12s whenever Flask was down).
     * Azure: leave unset and set OPENAI_API_KEY in App Settings.
     */
    function ai_classifier_base()
    {
        $env = getenv('AI_CLASSIFIER_URL');
        if ($env === false || trim((string) $env) === '') {
            if (isset($_SERVER['AI_CLASSIFIER_URL']) && trim((string) $_SERVER['AI_CLASSIFIER_URL']) !== '') {
                $env = $_SERVER['AI_CLASSIFIER_URL'];
            } else {
                return '';
            }
        }
        return rtrim(trim((string) $env), '/');
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
                CURLOPT_TIMEOUT => 5,
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
        $maxTokens = (int) ai_openai_env('OPENAI_MAX_COMPLETION_TOKENS', (string) $maxTokens);
        if ($maxTokens < 50) {
            $maxTokens = 50;
        }
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => $maxTokens,
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
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 5,
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
                    'timeout' => 12,
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
        // Score every category from subject AND description so a vague title
        // ("equipment checked") cannot hide a clear problem ("Windows Update").
        $rules = [
            'account' => [
                '/\b(password|otp|2fa|two[ -]?factor|username|account locked|lock(ed)? account|sign\s*in|log ?in failed|reset (my )?password|profile picture)\b/i',
            ],
            'network' => [
                '/\b(wi-?fi|wireless|vpn|ethernet|dns|router|no internet|cannot connect to (the )?internet)\b/i',
            ],
            'software' => [
                '/\b(windows\s*update|software\s*update|pending\s*updates?|install\s*updates?)\b/i',
                '/\b(windows\s*(10|11)|operating\s*system|microsoft\s*office|office\s*365|excel|microsoft word|\bms word\b|google chrome|outlook|teams|zoom)\b/i',
                '/\b(license key|activation|install (the )?(app|application|program|software)|app(lication)? (crash|frozen|not responding))\b/i',
            ],
            'hardware' => [
                '/\b(blue\s*screen|bsod|overheat|battery|charger|keyboard|mouse|monitor|printer|projector|ram|ssd|hdd|won\'t turn on|will not turn on|computer will not)\b/i',
            ],
        ];
        $scores = [];
        foreach ($rules as $cat => $patterns) {
            foreach ($patterns as $re) {
                if ($blob !== '' && preg_match($re, $blob)) {
                    $scores[$cat] = ($scores[$cat] ?? 0) + 1;
                }
            }
        }
        if ($scores !== []) {
            arsort($scores);
            $top = (int) reset($scores);
            $tied = array_keys(array_filter($scores, static function ($n) use ($top) {
                return (int) $n === $top;
            }));
            $prefer = ['software', 'account', 'network', 'hardware'];
            $category = $tied[0];
            foreach ($prefer as $cat) {
                if (in_array($cat, $tied, true)) {
                    $category = $cat;
                    break;
                }
            }
        }
        $axes = function_exists('severity_estimate_axes')
            ? severity_estimate_axes($subject, $description)
            : ['urgency' => 1, 'impact' => 1];
        $urgency = max(1, min(3, (int) ($axes['urgency'] ?? 1)));
        $impact = max(1, min(3, (int) ($axes['impact'] ?? 1)));
        $priority = function_exists('severity_from_score')
            ? severity_from_score($urgency * $impact)
            : 'low';

        return [
            'ok' => true,
            'category' => $category,
            'priority' => $priority,
            'urgency' => $urgency,
            'impact' => $impact,
            'confidence' => 0.55,
            'method' => 'keyword',
            'model' => null,
            'error' => null,
            'raw' => null,
        ];
    }

    function ai_openai_classify($subject, $description)
    {
        $system = 'You classify campus IT helpdesk tickets for ZPGC. '
            . 'Do not assign priority. The system computes priority as Urgency×Impact '
            . '(1–2 Low, 3–6 Moderate, 9 Critical) and adds +40 if 30+ identical open reports exist. '
            . 'Reply with ONLY valid JSON: '
            . '{"category":"hardware|software|network|account|other",'
            . '"urgency":1,'
            . '"impact":1,'
            . '"confidence":0.0,'
            . '"rationale":"short reason"} '
            . 'Read BOTH subject and description. Classify the actual problem, not vague wording. '
            . 'Windows Update, Office, Chrome, installs, patches, and OS updates are software. '
            . 'A subject like "check equipment" is still software if the body names Windows Update or an application. '
            . 'Use other only for process questions (forms, schedules, how-to with no broken system). '
            . 'urgency 1=can wait, 2=needs attention soon, 3=must be fixed immediately (blocked work, outage). '
            . 'impact 1=one person, 2=group/class/lab, 3=department or whole campus. '
            . 'urgency and impact must be integers 1, 2, or 3.';
        $user = "Subject: {$subject}\nDescription: {$description}";
        $messages = array_merge(
            [['role' => 'system', 'content' => $system]],
            ai_fewshot_classify_messages(),
            [['role' => 'user', 'content' => $user]]
        );
        $chat = ai_openai_chat($messages, 200, 0.2);

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
        $urgency = (int) ($data['urgency'] ?? 0);
        $impact = (int) ($data['impact'] ?? 0);
        if (!in_array($cat, $allowedCat, true) || $urgency < 1 || $urgency > 3 || $impact < 1 || $impact > 3) {
            return [
                'ok' => false,
                'error' => 'OpenAI JSON missing category, urgency, or impact.',
                'fallback_reason' => 'openai_error',
            ];
        }
        $priority = severity_from_score($urgency * $impact);

        return [
            'ok' => true,
            'category' => $cat,
            'priority' => $priority,
            'urgency' => $urgency,
            'impact' => $impact,
            'confidence' => isset($data['confidence']) ? (float) $data['confidence'] : 0.8,
            'method' => 'openai',
            'model' => ai_openai_model(),
            'fallback_reason' => null,
            'error' => null,
            'raw' => $chat['content'],
        ];
    }

    function ai_pack_troubleshoot($summary, array $steps, $ask, $method = 'keyword', $model = null)
    {
        $steps = array_values(array_filter(array_map(static function ($s) {
            return trim((string) $s);
        }, $steps)));
        if ($steps === []) {
            $steps = ['Write down the exact error text and when it started, then retry once.'];
        }
        $steps = array_slice($steps, 0, 6);
        $summary = trim((string) $summary);
        if ($summary === '') {
            $summary = 'Try these steps for this issue before requesting a technician.';
        }
        $ask = trim((string) $ask);
        if ($ask === '') {
            $ask = 'If these steps do not fix the issue, request a technician.';
        }

        $lines = [$summary, ''];
        $n = 1;
        foreach ($steps as $step) {
            $lines[] = $n . '. ' . $step;
            $n++;
        }
        $lines[] = '';
        $lines[] = 'Ask a technician if: ' . $ask;

        return [
            'ok' => true,
            'summary' => $summary,
            'steps' => $steps,
            'ask_technician_if' => $ask,
            'guidance_text' => implode("\n", $lines),
            'method' => (string) $method,
            'model' => $model,
            'error' => null,
        ];
    }

    /**
     * Issue-specific self-help (not generic restart/cables for every ticket).
     */
    function ai_keyword_troubleshoot($subject, $description, $category = 'other')
    {
        $blob = strtolower(trim((string) $subject . ' ' . (string) $description));
        $cat = strtolower(trim((string) $category));
        if (!in_array($cat, ['hardware', 'software', 'account', 'network', 'other'], true)) {
            $cat = 'other';
        }

        $playbooks = [
            [
                '/profile\s*pic|change (my )?(photo|picture|avatar)|display\s*picture|dp\b/i',
                'How to change a campus profile picture.',
                [
                    'If this is the Windows sign-in photo: press Win + I, open Accounts → Your info, then choose Change your photo (JPG or PNG).',
                    'If this is webmail/Outlook: open Outlook on the web, click your initials or photo at the top right, then Change photo / Edit profile.',
                    'Use a clear square photo under 4 MB. After saving, sign out and sign back in so lab PCs pick up the new picture.',
                    'If a school portal photo still will not save, note the exact error on screen and request a technician (do not email the photo as a password reset).',
                ],
                'The photo still does not show after a full sign-out, or the portal returns an error.',
            ],
            [
                '/forgot (my )?password|reset (my )?password|password (expired|not working|incorrect|won\'t work)/i',
                'Reset a campus password safely.',
                [
                    'Check Caps Lock and that you are using your campus username (not a personal Gmail unless that is the official login).',
                    'Use only the Forgot password / official reset link on the school sign-in page. Do not use random websites that ask for your password.',
                    'After a successful reset, wait about two minutes, then sign in again on one device.',
                    'If the same password fails on campus Wi-Fi and on a PC, write down the exact error text and request a technician. Never send your password in the ticket.',
                ],
                'Reset succeeds but you still cannot sign in, or you never received a reset email.',
            ],
            [
                '/account locked|locked out|too many (login|sign-?in) attempts/i',
                'Unlock a locked campus account.',
                [
                    'Stop extra sign-in attempts for 15 minutes — more tries can keep the lock in place.',
                    'Sign in on one device only, with the correct campus username.',
                    'If you recently changed the password, use the new one everywhere (Wi-Fi, email, and lab PCs).',
                    'If it stays locked, request a technician and include the username (not the password) plus the time it locked.',
                ],
                'The account is still locked after waiting, or you need an admin unlock.',
            ],
            [
                '/\b(otp|2fa|two[ -]?factor|authenticator)\b/i',
                'Fix campus two-factor / OTP sign-in.',
                [
                    'Confirm the phone time is set automatically (wrong time makes OTP codes fail).',
                    'Use the latest code from the school authenticator app or SMS; do not reuse an old code.',
                    'If you lost the phone, do not try random codes. Request a technician to reset 2FA with campus ID.',
                    'After a successful sign-in, save a backup method if the portal offers one.',
                ],
                'You lost the device that receives codes, or every new code is rejected.',
            ],
            [
                '/cannot (log|sign)\s*in|can\'t (log|sign)\s*in|(log|sign)[ -]?in failed|invalid (user|credentials)/i',
                'Fix a failed campus sign-in.',
                [
                    'Type the campus username carefully (watch extra spaces and Caps Lock).',
                    'Try the same login in a private/incognito window to skip a stored wrong password.',
                    'If this is email, use the school address and the current campus password — not a personal Microsoft/Google password unless IT told you to.',
                    'Note the exact error (wrong password, account disabled, cannot reach server) before requesting a technician.',
                ],
                'Incognito still fails, or the error says the account is disabled.',
            ],
            [
                '/wi-?fi|wireless|ssid|campus network/i',
                'Reconnect to campus Wi-Fi.',
                [
                    'Turn Wi-Fi off, wait 10 seconds, then turn it back on and pick the official campus network (not a similarly named guest/hotspot).',
                    'Forget that network, join it again, and enter the current Wi-Fi password from IT or your classroom notice.',
                    'Turn off airplane mode and VPN, then retry. If a phone works but the laptop does not, the laptop Wi-Fi adapter or saved password is the issue.',
                    'Write the building, room, and time if only that area fails so IT can check the access point.',
                ],
                'Other devices nearby connect but yours does not, or the official network never appears.',
            ],
            [
                '/\bvpn\b/i',
                'Fix campus VPN.',
                [
                    'Connect to ordinary campus or home internet first, then start the official VPN app (not a free VPN).',
                    'Sign in with the same campus account you use for email.',
                    'If it fails, disconnect, reboot the VPN app, and try again. Pause extra security software briefly if it blocks the VPN.',
                    'Copy the exact VPN error code into your ticket if it still fails.',
                ],
                'The official VPN app will not connect after a retry, or you were never issued VPN access.',
            ],
            [
                '/no internet|cannot connect to (the )?internet|ethernet|lan\b|network cable/i',
                'Restore a wired or general internet connection.',
                [
                    'If you use a cable: unplug both ends, wait 10 seconds, reconnect firmly to the PC and wall/switch port.',
                    'In Windows, open Settings → Network, disconnect and reconnect, then try a known site such as the school homepage.',
                    'Try another wall port or another device on the same cable to see which end is at fault.',
                    'Note whether Wi-Fi still works. If the whole room is down, say so in the ticket.',
                ],
                'The cable and another port still have no link light, or the whole lab is offline.',
            ],
            [
                '/printer|print job|won\'?t print|cannot print|won\'t print/i',
                'Get a campus printer working again.',
                [
                    'Confirm the printer is powered on and shows Ready — clear paper, toner, and jam messages first.',
                    'On the PC, choose that printer as the default and print a one-page test (Notepad or a blank document).',
                    'Cancel stuck jobs in the print queue, then send the test again.',
                    'If it stays Offline, power-cycle the printer (off 15 seconds) and reconnect USB or the printer network, then retry.',
                ],
                'The printer stays Offline/Error after a power cycle, or every PC in the room fails to print.',
            ],
            [
                '/projector|hdmi|no display on (the )?screen/i',
                'Fix a classroom projector or HDMI display.',
                [
                    'Power the projector on and wait until the lamp is ready. Select the matching input (HDMI 1/2).',
                    'Reseat the HDMI (or VGA) cable at both the PC and the projector.',
                    'On Windows press Win + P and choose Duplicate. If the image is on the laptop only, try Extend then Duplicate again.',
                    'Try a second cable or another HDMI port. Note whether the projector shows “No signal”.',
                ],
                'Another laptop also shows No signal, or the lamp/error light stays on.',
            ],
            [
                '/\b(monitor|blank screen|black screen|no display)\b/i',
                'Fix a monitor with no picture.',
                [
                    'Confirm the monitor power light is on and the brightness is not at zero.',
                    'Reseat the video cable (HDMI/VGA/DisplayPort) at the monitor and the PC.',
                    'If you have a laptop, close the lid test is not needed — press Win + P and pick PC screen only, then Duplicate.',
                    'Try the monitor on another PC, or another monitor on this PC, so you know which device failed.',
                ],
                'The PC fans run but there is never an image, or the monitor stays black on every cable.',
            ],
            [
                '/\bkeyboard\b/i',
                'Fix a keyboard that is not typing.',
                [
                    'Unplug the keyboard (or turn wireless off/on), wait 10 seconds, then reconnect to another USB port.',
                    'Check Caps Lock/Num Lock and that Filter Keys is off: Settings → Accessibility → Keyboard.',
                    'Try the keyboard on another PC. If a laptop keyboard fails, connect a USB keyboard so you can keep working.',
                    'Do not pry keys off unless IT asks — report which keys fail.',
                ],
                'No keys work on this PC after a different keyboard also fails, or liquid was spilled.',
            ],
            [
                '/\bmouse\b|touchpad/i',
                'Fix a mouse or touchpad.',
                [
                    'Replace the battery or recharge a wireless mouse; for USB, try another port.',
                    'Toggle the laptop touchpad (often Fn + a key with a pad icon) in case it was turned off.',
                    'Settings → Bluetooth & devices → Mouse: raise pointer speed and check that the left button still clicks.',
                    'Try another mouse. If the pointer moves but clicks do not register, say so in the ticket.',
                ],
                'The pointer never appears, or neither the touchpad nor an external mouse works.',
            ],
            [
                '/charger|won\'?t charge|battery (drains|not charging|dead)/i',
                'Fix charging or battery issues.',
                [
                    'Use the original charger. Confirm the brick LED (if any) is on and the barrel/USB-C plug is fully seated.',
                    'Try another wall outlet. Avoid cheap unpowered hubs for USB-C charging.',
                    'Leave it charging for 20 minutes while powered off, then turn it on.',
                    'If it only runs on AC and dies immediately on battery, request a technician — do not open the case.',
                ],
                'The charging light never comes on, or the laptop shuts down unless it stays plugged in.',
            ],
            [
                '/won\'?t turn on|will not turn on|no power|does not (boot|start)/i',
                'A computer that will not power on.',
                [
                    'Confirm the wall outlet works (plug in a phone charger). Reseat the PC/laptop power cable.',
                    'Hold the power button for 15 seconds, wait, then press it once to start.',
                    'For a desktop, check the switch on the back PSU is set to I (on).',
                    'Listen for fans or beeps. Report power lights, beeps, or a totally dead unit in the ticket.',
                ],
                'There is no light, fan, or beep after a known-good outlet and cable.',
            ],
            [
                '/blue\s*screen|bsod/i',
                'What to do after a blue screen.',
                [
                    'Let the PC restart. If it asks, choose Start Windows normally.',
                    'Write the stop code on the blue screen (for example, CLOCK_WATCHDOG or a hex code).',
                    'Unplug extra USB devices except keyboard/mouse, then reboot once more.',
                    'If it blue-screens again immediately, stop looping it and request a technician with the stop code.',
                ],
                'It blue-screens every boot, or you cannot reach the desktop.',
            ],
            [
                '/overheat|too hot|fan (loud|noise)/i',
                'Cool a PC that overheats or has a loud fan.',
                [
                    'Shut down, wait 5 minutes, and move it off beds/papers onto a hard desk so vents are open.',
                    'Close extra browser tabs and heavy apps, then retry the task.',
                    'Do not block side or bottom vents. For a lab desktop, leave 10 cm of space behind the case.',
                    'If it shuts down from heat after a short time, request a technician — dust or a failed fan needs IT.',
                ],
                'It still powers off from heat after cooling down, or you smell burning.',
            ],
            [
                '/\bexcel\b/i',
                'Fix Microsoft Excel on a lab PC.',
                [
                    'Save a copy of the file to Desktop or Documents, then close Excel fully (check the taskbar) and reopen it.',
                    'Open Excel first, then File → Open the workbook — this skips a broken double-click association.',
                    'If Excel says not licensed, sign in with the school Microsoft account (not a personal one) when prompted.',
                    'If one file crashes Excel but a blank workbook works, the file may be damaged — attach that detail for IT.',
                ],
                'Excel will not start at all, or every workbook shows a license/activation error.',
            ],
            [
                '/microsoft word|\bms word\b|\bwinword\b/i',
                'Fix Microsoft Word on a lab PC.',
                [
                    'Close Word completely and reopen it. If it offers Safe Mode, choose No first, then Yes only if it still fails.',
                    'Open Word, then File → Open your document instead of double-clicking the file.',
                    'Sign in with the school Microsoft account if Word is in unlicensed/readonly mode.',
                    'Copy the text into a new blank document if only one file will not open.',
                ],
                'Word will not start, or it stays unlicensed after school-account sign-in.',
            ],
            [
                '/google chrome|\bchrome\b/i',
                'Fix Google Chrome.',
                [
                    'Close every Chrome window, then open it again. If it is frozen, use Task Manager → End task on Chrome.',
                    'Try an Incognito window. If the site works there, clear cache for that site: ⋮ → Delete browsing data → Cached images.',
                    'Disable extra extensions (⋮ → Extensions) and retry the page.',
                    'If Chrome will not open, use Microsoft Edge temporarily and request a technician to repair Chrome.',
                ],
                'Chrome will not start, or a required school site fails only in Chrome after cache clear.',
            ],
            [
                '/\boutlook\b/i',
                'Fix Outlook mail on campus.',
                [
                    'Confirm you can open Outlook on the web in a browser with your school account first.',
                    'In the Outlook app: File → Office Account, and sign in with the same school account.',
                    'If mail will not send, check the From address is the school mailbox and you are not in Offline mode (uncheck Work Offline).',
                    'If the app is stuck on “Need password”, sign out of Office, sign back in, and retry.',
                ],
                'Webmail works but the desktop app does not, or you cannot send to campus addresses.',
            ],
            [
                '/license key|activation code|not licensed|product activation/i',
                'Fix software license or activation.',
                [
                    'Sign out of the app, then sign in with the school Microsoft or vendor account — personal accounts often have no campus license.',
                    'Use only a key issued by IT. Do not download activators or keys from the internet.',
                    'Restart the app after sign-in and check File → Account for Licensed to.',
                    'If it still says Unlicensed, request a technician and name the exact app and version on the PC.',
                ],
                'School-account sign-in still shows Unlicensed, or you were never given a key.',
            ],
            [
                '/install (the )?(app|application|program|software)|how (do|can) i install/i',
                'Install software the supported way.',
                [
                    'Use only the campus software center, IT installer share, or the vendor site IT named. Avoid random “free download” sites.',
                    'Run the installer as the signed-in user first. If Windows asks for an admin password, stop and request a technician — students usually cannot approve that.',
                    'After install, reboot once if the installer asks, then open the app from the Start menu.',
                    'If install is blocked by policy, include the software name and why you need it in the ticket.',
                ],
                'Setup needs an administrator password, or the install fails with an error code.',
            ],
            [
                '/software update|windows update|pending update/i',
                'Finish a software or Windows update.',
                [
                    'Save your work. Open Settings → Windows Update (or the app’s own Help → Check for updates) and install pending updates.',
                    'Restart when Windows asks. Partial updates often leave apps unable to open.',
                    'After reboot, open the same app again and retry the original task.',
                    'If updates fail with an error code, copy that code into the ticket.',
                ],
                'Windows Update loops or fails with the same error code after a reboot.',
            ],
            [
                '/not responding|frozen|freeze|crashed|crash(es|ed)?\b/i',
                'Recover an app that froze or crashed.',
                [
                    'Wait 30 seconds. If the window stays Not responding, open Task Manager (Ctrl+Shift+Esc) and End task on that app only.',
                    'Reopen the app. Recover files from AutoRecover or the Recycle Bin if Word/Excel offers them.',
                    'Restart the PC once if the same app crashes immediately.',
                    'Note what you clicked right before the crash and whether it happens in every file or one file only.',
                ],
                'The whole PC freezes (mouse included), or the same app crashes after a reboot.',
            ],
            [
                '/how (do|can) i |where (do|can) i |request form|office hours/i',
                'Follow the right campus how-to, not a reboot.',
                [
                    'Say which system you mean (email, enrollment, Wi-Fi, lab booking) and what you already clicked.',
                    'Check the campus homepage or student portal menu for that form or schedule before asking IT to do it for you.',
                    'If a page is missing or forbidden, capture the URL and the message on screen.',
                    'Request a technician only if you cannot find the official form or the page errors.',
                ],
                'The official page is missing, forbidden, or returns an error after you followed the posted steps.',
            ],
        ];

        foreach ($playbooks as $pb) {
            if ($blob !== '' && preg_match($pb[0], $blob)) {
                return ai_pack_troubleshoot($pb[1], $pb[2], $pb[3], 'keyword');
            }
        }

        $byCat = [
            'account' => [
                'Campus account self-help.',
                [
                    'Confirm the campus username (watch extra spaces and Caps Lock).',
                    'Use the official Forgot password / unlock process — never a third-party reset site.',
                    'Try one private/incognito browser window so an old saved password is not reused.',
                    'Write the exact error text if sign-in still fails.',
                ],
                'Sign-in still fails after a reset, or the account is disabled.',
            ],
            'network' => [
                'Campus network self-help.',
                [
                    'Toggle Wi-Fi off and on, or reseat the Ethernet cable until the link light returns.',
                    'Forget the campus Wi-Fi network and join the official SSID again.',
                    'Turn off VPN and airplane mode, then retry a known campus website.',
                    'Note the building and room if only that area is down.',
                ],
                'Other devices in the same room work and yours does not, or the whole room is offline.',
            ],
            'hardware' => [
                'Hardware self-help.',
                [
                    'Fully power off, wait 30 seconds, then power on (do not force a second hard shutdown if it already failed).',
                    'Reseat power, USB, and video cables, and try another known-good cable or port.',
                    'Test the device on another PC when it is a mouse, keyboard, or USB drive.',
                    'Stop and request a technician if there is burning smell, sparks, or liquid spill.',
                ],
                'The device stays dead after a known-good cable/port, or there is a safety issue.',
            ],
            'software' => [
                'Software self-help.',
                [
                    'Save your work, close the app from Task Manager if it is frozen, then open it again.',
                    'Restart the PC once, then retry the same action.',
                    'Install pending app or Windows updates if the PC is allowed to update.',
                    'Note the exact error dialog if it still fails.',
                ],
                'The app will not start after a reboot, or it reports a license error.',
            ],
            'other' => [
                'Quick checks before a technician is assigned.',
                [
                    'Write the exact error message, the time it started, and which PC or room you used.',
                    'Retry the same steps once after a restart of the app (not a random cable check unless cables are involved).',
                    'Try another browser or another campus PC if this is a website problem.',
                    'Attach those details when you request a technician.',
                ],
                'The same error remains after one careful retry, or you cannot complete classwork.',
            ],
        ];
        $row = $byCat[$cat] ?? $byCat['other'];
        return ai_pack_troubleshoot($row[0], $row[1], $row[2], 'keyword');
    }

    function ai_openai_troubleshoot($subject, $description, $category, $priority)
    {
        $system = 'You are a campus IT helpdesk assistant for students and staff. '
            . 'Give short, safe, step-by-step tips that match THIS ticket only. '
            . 'If the user asked how to do something, give those how-to clicks — do not tell them to restart, check cables, or toggle Wi-Fi unless that is actually the problem. '
            . 'No passwords, no cracking, no disk wipes, no registry hacks. Do not invent school portal URLs. '
            . 'Reply with ONLY valid JSON: '
            . '{"summary":"one sentence","steps":["step1","step2","step3","step4"],'
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
        $packed = ai_pack_troubleshoot($summary, array_slice($steps, 0, 6), $ask, 'openai', ai_openai_model());
        return $packed;
    }

    /**
     * If keywords find a real specialty, keep it — assignment routes by category.
     * Models often pick "other" from a vague subject while the body is specific.
     */
    function ai_overlay_keyword_category(array $result, $subject, $description)
    {
        $kw = ai_keyword_classify($subject, $description);
        $kwCat = strtolower((string) ($kw['category'] ?? 'other'));
        $cur = strtolower(trim((string) ($result['category'] ?? '')));
        if ($kwCat !== 'other' && $kwCat !== $cur) {
            $result['category'] = $kwCat;
            $method = (string) ($result['method'] ?? '');
            $result['method'] = $method !== '' ? $method . '+keyword' : 'keyword';
        } elseif ($cur === '' || $cur === 'other') {
            $result['category'] = $kwCat;
        }
        return $result;
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
            ], 5);
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
                return ai_overlay_keyword_category($result, $subject, $description);
            }
        }

        // OpenAI API from PHP (env / App Settings).
        if (ai_openai_key() !== '') {
            $direct = ai_openai_classify($subject, $description);
            if (!empty($direct['ok'])) {
                return ai_overlay_keyword_category($direct, $subject, $description);
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
        if (ai_classifier_base() !== '') {
            $http = ai_http_post_json('/suggest', [
                'subject' => (string) $subject,
                'title' => (string) $subject,
                'description' => (string) $description,
                'category' => (string) $category,
                'priority' => (string) $priority,
            ], 6);

            if ($http['ok']) {
                $data = $http['data'];
                $steps = isset($data['steps']) && is_array($data['steps']) ? $data['steps'] : [];
                $summary = trim((string) ($data['summary'] ?? 'Try these steps first.'));
                $ask = trim((string) ($data['ask_technician_if'] ?? 'If the problem continues, request a technician.'));
                $method = isset($data['method']) ? (string) $data['method'] : 'flask';
                $model = isset($data['model']) ? (string) $data['model'] : null;
                return ai_pack_troubleshoot($summary, $steps, $ask, $method, $model);
            }
        }

        // Direct OpenAI when a key is set (5s HTTP timeout in ai_openai_http_json).
        if (ai_openai_key() !== '') {
            $direct = ai_openai_troubleshoot($subject, $description, $category, $priority);
            if (!empty($direct['ok']) && !empty($direct['steps'])) {
                return $direct;
            }
        }

        return ai_keyword_troubleshoot($subject, $description, $category);
    }
}

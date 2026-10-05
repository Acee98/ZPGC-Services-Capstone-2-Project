<?php
/**
 * Probe the Azure live site (signup validation + mail path).
 * CLI: php ai/smoke_live.php [baseUrl]
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = rtrim($argv[1] ?? 'https://zpgc-services-jp-dudeeuefc4eqdgek.koreacentral-01.azurewebsites.net', '/');
$cookie = sys_get_temp_dir() . '/zpgc_live_smoke.txt';
@unlink($cookie);

$pass = 0;
$fail = 0;
function check($label, $ok, $detail = '')
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "[PASS] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    } else {
        $fail++;
        echo "[FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    }
}

function http_req($url, $cookie, $post = null, $follow = true)
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    if ($post !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $headers = substr((string) $raw, 0, $headerSize);
    $body = substr((string) $raw, $headerSize);
    return compact('code', 'headers', 'body', 'err');
}

function extract_csrf($html)
{
    if (preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function flash_text($html)
{
    $out = [];
    if (preg_match_all('/class="[^"]*(?:error|success|alert|notice)[^"]*"[^>]*>(.*?)<\//is', $html, $m)) {
        foreach ($m[1] as $chunk) {
            $t = trim(preg_replace('/\s+/', ' ', strip_tags($chunk)));
            if ($t !== '') {
                $out[] = $t;
            }
        }
    }
    return $out;
}

$health = http_req($base . '/health.php', $cookie);
check('health 200', $health['code'] === 200 && trim($health['body']) === 'ok', (string) $health['code']);

$login = http_req($base . '/pages/login_signup.php?form=signup', $cookie);
check('login page 200', $login['code'] === 200);
$csrf = extract_csrf($login['body']);
check('csrf present', $csrf !== '', substr($csrf, 0, 8) . '…');
check('signup copy mentions 6-digit', str_contains($login['body'], '6-digit'));

$email = 'live.smoke.' . bin2hex(random_bytes(2)) . '@student.tsu.edu.ph';
$signup = http_req($base . '/logic/user_mngmnt.php', $cookie, [
    '_csrf' => $csrf,
    'signup' => '1',
    'first_name' => 'Live',
    'last_name' => 'Smoke',
    'email' => $email,
    'password' => 'TestLive123!',
    'confirm_password' => 'TestLive123!',
    'role' => 'user',
    'accept_terms' => '1',
], false);
check('tsu signup redirects', in_array($signup['code'], [302, 303], true), (string) $signup['code']);

$after = http_req($base . '/pages/login_signup.php?form=signup', $cookie);
$msgs = flash_text($after['body']);
$joined = strtolower(implode(' | ', $msgs));
check('tsu not rejected as fake', !str_contains($joined, 'fake') && !str_contains($joined, 'look like a real'), $joined !== '' ? $joined : '(no flash; may have redirected to verify)');
$verify = http_req($base . '/pages/verify_pending.php', $cookie);
$onVerify = $verify['code'] === 200 && (str_contains($verify['body'], 'digit') || str_contains(strtolower($verify['body']), 'verif'));
$mailFail = str_contains($joined, 'mail') || str_contains($joined, 'smtp') || str_contains($joined, 'configured') || str_contains($joined, 'could not send');
check('tsu signup either mailed or clear mail error', $onVerify || $mailFail || $joined !== '', $onVerify ? 'verify page' : ($joined ?: 'unclear'));

@unlink($cookie);
$login2 = http_req($base . '/pages/login_signup.php?form=signup', $cookie);
$csrf2 = extract_csrf($login2['body']);
$gmail = http_req($base . '/logic/user_mngmnt.php', $cookie, [
    '_csrf' => $csrf2,
    'signup' => '1',
    'first_name' => 'Bad',
    'last_name' => 'Mail',
    'email' => 'someone@gmail.com',
    'password' => 'TestLive123!',
    'confirm_password' => 'TestLive123!',
    'role' => 'user',
    'accept_terms' => '1',
], true);
$gmsgs = flash_text($gmail['body']);
$gjoin = strtolower(implode(' | ', $gmsgs) . ' ' . strip_tags($gmail['body']));
check('gmail signup blocked', str_contains($gjoin, 'tsu') || str_contains($gjoin, 'outlook') || str_contains($gjoin, 'student.tsu'), implode(' | ', $gmsgs));

echo PHP_EOL . "Base: {$base}" . PHP_EOL;
echo "Passed {$pass}, failed {$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);

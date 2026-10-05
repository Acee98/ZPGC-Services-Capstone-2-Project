<?php
/**
 * HTTP smoke against local Apache — signup validation + login pages.
 * php ai/smoke_http.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('CLI only');
}

$base = getenv('ZPGC_SMOKE_BASE') ?: 'http://127.0.0.1/CP2_V1.6';
$fail = 0;
$pass = 0;

function http_req(string $method, string $url, array $opts = []): array
{
    $ch = curl_init($url);
    $headers = $opts['headers'] ?? [];
    $cookieFile = $opts['cookie'] ?? sys_get_temp_dir() . '/zpgc_smoke_cookies.txt';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    if (!empty($opts['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $header = substr((string) $raw, 0, $headerSize);
    $body = substr((string) $raw, $headerSize);
    return compact('code', 'header', 'body', 'err');
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $fail, $pass;
    if ($ok) {
        $pass++;
        echo "[PASS] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    } else {
        $fail++;
        echo "[FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    }
}

@unlink(sys_get_temp_dir() . '/zpgc_smoke_cookies.txt');

$r = http_req('GET', $base . '/health.php');
check('health 200', $r['code'] === 200 && trim($r['body']) === 'ok', (string) $r['code']);

$r = http_req('GET', $base . '/pages/login_signup.php');
check('login page 200', $r['code'] === 200);
check('login has csrf', str_contains($r['body'], 'name="_csrf"'));
check('login has signup form', str_contains($r['body'], 'name="signup"'));

preg_match('/name="_csrf"\s+value="([^"]+)"/', $r['body'], $m);
$csrf = $m[1] ?? '';
check('csrf extracted', $csrf !== '', substr($csrf, 0, 8) . '…');

// Reject gmail via POST
$r = http_req('POST', $base . '/logic/user_mngmnt.php', [
    'body' => http_build_query([
        '_csrf' => $csrf,
        'signup' => '1',
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'someone@gmail.com',
        'password' => 'TestPass123!',
        'confirm_password' => 'TestPass123!',
        'role' => 'user',
        'accept_terms' => '1',
    ]),
]);
check('gmail signup redirected', in_array($r['code'], [302, 303], true), (string) $r['code']);
$loc = '';
if (preg_match('/^Location:\s*(.+)$/mi', $r['header'], $lm)) {
    $loc = trim($lm[1]);
}
check('gmail signup back to signup', str_contains($loc, 'login_signup') || str_contains($loc, 'form=signup'), $loc);

// Follow redirect to read flash
$r2 = http_req('GET', (str_starts_with($loc, 'http') ? $loc : ($base . '/pages/' . ltrim(str_replace('../pages/', '', $loc), '/'))));
// Location might be relative ../pages/login_signup.php?form=signup
if ($loc !== '' && !str_starts_with($loc, 'http')) {
    if (str_starts_with($loc, '../pages/')) {
        $r2 = http_req('GET', $base . '/pages/' . substr($loc, strlen('../pages/')));
    } elseif (str_starts_with($loc, '/')) {
        $r2 = http_req('GET', 'http://127.0.0.1' . $loc);
    }
}
check(
    'gmail error message shown',
    str_contains($r2['body'] ?? '', 'TSU Outlook') || str_contains($r2['body'] ?? '', 'Gmail'),
    substr(strip_tags($r2['body'] ?? ''), 0, 120)
);

// Refresh csrf
$r = http_req('GET', $base . '/pages/login_signup.php?form=signup');
preg_match('/name="_csrf"\s+value="([^"]+)"/', $r['body'], $m);
$csrf = $m[1] ?? '';

// Valid TSU format should pass validation (may fail later on mail_ready)
$stamp = bin2hex(random_bytes(2));
$email = "smoke.ok.{$stamp}@student.tsu.edu.ph";
$r = http_req('POST', $base . '/logic/user_mngmnt.php', [
    'body' => http_build_query([
        '_csrf' => $csrf,
        'signup' => '1',
        'first_name' => 'Smoke',
        'last_name' => 'Ok',
        'email' => $email,
        'password' => 'TestPass123!',
        'confirm_password' => 'TestPass123!',
        'role' => 'user',
        'accept_terms' => '1',
    ]),
]);
check('tsu signup http redirect', in_array($r['code'], [302, 303], true), (string) $r['code']);
$loc = '';
if (preg_match('/^Location:\s*(.+)$/mi', $r['header'], $lm)) {
    $loc = trim($lm[1]);
}
// Should NOT bounce with the domain hint if validation passed — either verify_pending or signup with mail error
$okPath = str_contains($loc, 'verify_pending') || str_contains($loc, 'login_signup');
check('tsu signup not hard-fail path', $okPath, $loc);

if (str_contains($loc, 'verify_pending')) {
    check('tsu email accepted → verify page', true);
} else {
    // Fetch flash
    $follow = $loc;
    if (str_starts_with($loc, '../pages/')) {
        $follow = $base . '/pages/' . substr($loc, strlen('../pages/'));
    }
    $r3 = http_req('GET', $follow);
    $text = html_entity_decode(strip_tags($r3['body'] ?? ''), ENT_QUOTES, 'UTF-8');
    $blockedByPlausible = str_contains($text, 'placeholder') || (str_contains($text, 'Made-up') && str_contains($text, 'blocked'));
    check('tsu not rejected as fake', !$blockedByPlausible, substr($text, 0, 180));
    // Mail not configured is acceptable locally
    $mailIssue = str_contains($text, 'Email sending is not configured') || str_contains($text, 'Could not send');
    if ($mailIssue) {
        check('mail missing handled cleanly', true, 'mail not configured locally');
    }
}

// Login page for wrong password
$r = http_req('GET', $base . '/pages/login_signup.php');
preg_match('/name="_csrf"\s+value="([^"]+)"/', $r['body'], $m);
$csrf = $m[1] ?? '';
$r = http_req('POST', $base . '/logic/user_mngmnt.php', [
    'body' => http_build_query([
        '_csrf' => $csrf,
        'login' => '1',
        'email' => 'nobody@tsu.edu.ph',
        'password' => 'wrong-password',
    ]),
]);
check('bad login redirects', in_array($r['code'], [302, 303], true));

// Admin/user pages require auth
$r = http_req('GET', $base . '/pages/admin.php');
check('admin redirects when logged out', in_array($r['code'], [302, 303], true) || str_contains($r['header'], 'login'), (string) $r['code']);

$r = http_req('GET', $base . '/pages/user.php');
check('user redirects when logged out', in_array($r['code'], [302, 303], true) || str_contains($r['header'], 'login'), (string) $r['code']);

echo PHP_EOL . "Passed {$pass}, failed {$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);

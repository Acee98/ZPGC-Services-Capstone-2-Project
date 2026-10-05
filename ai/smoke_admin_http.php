<?php
/**
 * Login as admin and hit key pages over HTTP.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('CLI only');
}
$base = 'http://127.0.0.1/CP2_V1.6';
$cookie = sys_get_temp_dir() . '/zpgc_admin_smoke.txt';
@unlink($cookie);
$fail = 0;
$pass = 0;
function check($l, $ok, $d = '')
{
    global $fail, $pass;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $l . ($d !== '' ? " — $d" : '') . PHP_EOL;
    $ok ? $pass++ : $fail++;
}
function req($method, $url, $cookie, $body = null)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HEADER => 1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_FOLLOWLOCATION => 0,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return [
        'code' => $code,
        'header' => substr((string) $raw, 0, $hs),
        'body' => substr((string) $raw, $hs),
    ];
}

$r = req('GET', $base . '/pages/login_signup.php', $cookie);
preg_match('/name="_csrf"\s+value="([^"]+)"/', $r['body'], $m);
$csrf = $m[1] ?? '';
check('csrf', $csrf !== '');

$r = req('POST', $base . '/logic/user_mngmnt.php', $cookie, http_build_query([
    '_csrf' => $csrf,
    'login' => '1',
    'email' => 'user3@gmail.com',
    'password' => 'TestAdmin123!',
]));
$loc = '';
if (preg_match('/^Location:\s*(.+)$/mi', $r['header'], $lm)) {
    $loc = trim($lm[1]);
}
check('admin login redirect', in_array($r['code'], [302, 303], true), (string) $r['code'] . ' ' . $loc);
check('admin lands on admin.php', str_contains($loc, 'admin.php'), $loc);

$r = req('GET', $base . '/pages/admin.php?tab=dashboard', $cookie);
check('admin dashboard 200', $r['code'] === 200);
check('admin dashboard content', str_contains($r['body'], 'data-page="dashboard"') || str_contains($r['body'], 'Dashboard') || str_contains($r['body'], 'page-dashboard'));

$r = req('GET', $base . '/pages/admin.php?tab=utilities', $cookie);
check('utilities 200', $r['code'] === 200);
check('utilities filter tabs', str_contains($r['body'], 'utilities-filter-tabs'));
check('utilities users body', str_contains($r['body'], 'utilities-users-body'));
check('utilities filter script', str_contains($r['body'], 'utilities_filter.js'));
check('no auto-purge surprise text', !str_contains($r['body'], 'Auto-disposed'));

$r = req('GET', $base . '/pages/admin.php?tab=tickets', $cookie);
check('tickets 200', $r['code'] === 200);

$r = req('GET', $base . '/pages/admin.php?tab=messages', $cookie);
check('messages 200', $r['code'] === 200);

echo PHP_EOL . "Passed {$pass}, failed {$fail}" . PHP_EOL;
exit($fail > 0 ? 1 : 0);

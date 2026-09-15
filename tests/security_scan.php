<?php
/**
 * SECURITY SCAN — static + configuration checks.
 *   php tests/security_scan.php
 * Runtime probes (headers, CSRF, traversal, XSS, throttling) live in tests/security_runtime.py
 */
$ROOT = dirname(__DIR__);
$pass = 0; $fail = 0;
function ck($name, $cond, $extra = '') {
    global $pass, $fail;
    if ($cond) { $pass++; } else { $fail++; echo "FAIL: $name  $extra\n"; }
}
$files = array();
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT));
foreach ($rii as $f) {
    $p = $f->getPathname();
    if (substr($p, -4) === '.php' && strpos($p, '/tests/') === false) { $files[] = $p; }
}
sort($files);
$all = ''; foreach ($files as $f) { $all .= file_get_contents($f); }

echo "Scanning " . count($files) . " PHP files\n\n";

/* ---- 1. dangerous functions ---- */
$dangerous = array('eval(', 'assert(', 'system(', 'passthru(', 'popen(', 'proc_open(', 'create_function(',
    'unserialize(', 'extract($_GET', 'extract($_POST', 'include $_GET', 'include $_POST', 'require $_GET', 'require $_POST', 'file_get_contents($_GET',
    'file_get_contents($_POST', 'shell_exec($_', 'exec($_', 'md5($pass', 'sha1($pass', 'mysql_query', 'mysql_connect');
/* A real call needs a non-word character in front of the name, so identifiers such as
   sf_admin_tab_system() or providers_sync() are not mistaken for command execution. */
$callBoundary = '/(^|[^A-Za-z0-9_$>])%s/';
foreach ($dangerous as $d) {
    $found = array();
    $plain = substr($d, -1) !== '(';                     /* entries that are not function calls */
    $re = $plain ? null : sprintf($callBoundary, preg_quote($d, '/'));
    foreach ($files as $f) {
        $src = file_get_contents($f);
        $hit = $plain ? (strpos($src, $d) !== false) : (preg_match($re, $src) === 1);
        if ($hit) { $found[] = str_replace($ROOT, '', $f); }
    }
    ck('no ' . $d, !$found, $found ? ('in ' . implode(', ', array_slice($found, 0, 3))) : '');
}

/* ---- 2. every SQL uses parameter binding ---- */
$sqlRisky = array();
foreach ($files as $f) {
    $src = file_get_contents($f);
    if (preg_match_all('/(?:db_one|db_all|db_val|db_exec|db_insert)\s*\(\s*"([^"]*(?:SELECT|INSERT|UPDATE|DELETE)[^"]*)"/i', $src, $m)) {
        foreach ($m[1] as $sql) { if (strpos($sql, '$') !== false) { $sqlRisky[] = str_replace($ROOT, '', $f) . ' :: ' . substr($sql, 0, 60); } }
    }
    if (preg_match_all("/(?:db_one|db_all|db_val|db_exec|db_insert)\s*\(\s*'([^']*(?:SELECT|INSERT|UPDATE|DELETE)[^']*)'/i", $src, $m)) {
        foreach ($m[1] as $sql) { if (strpos($sql, '$') !== false) { $sqlRisky[] = str_replace($ROOT, '', $f) . ' :: ' . substr($sql, 0, 60); } }
    }
}
ck('no interpolated SQL (all bound)', !$sqlRisky, implode(' | ', array_slice($sqlRisky, 0, 3)));

/* ---- 3. output escaping: no raw superglobal echo ---- */
$rawEcho = array();
foreach ($files as $f) {
    $src = file_get_contents($f);
    if (preg_match_all('/echo\s+\$(?:_GET|_POST|_REQUEST|_SERVER|_COOKIE)/', $src, $m)) { $rawEcho[] = str_replace($ROOT, '', $f); }
    if (preg_match_all('/<\?=\s*\$(?:_GET|_POST|_REQUEST)/', $src, $m)) { $rawEcho[] = str_replace($ROOT, '', $f); }
}
ck('no raw superglobal echo', !$rawEcho, implode(', ', array_slice(array_unique($rawEcho), 0, 3)));

/* ---- 4. password hashing ---- */
ck('uses password_hash (bcrypt)', strpos($all, 'password_hash(') !== false);
ck('uses password_verify', strpos($all, 'password_verify(') !== false);
ck('no md5/sha1 for passwords', !preg_match('/\$pass\s*=\s*(md5|sha1)\(/', $all));

/* ---- 5. CSRF ---- */
ck('CSRF helper exists', strpos($all, 'function csrf_check') !== false);
ck('CSRF enforced on API', strpos(file_get_contents($ROOT . '/core/router.php'), 'csrf_check(false)') !== false);
$postForms = 0; $formsWithToken = 0;
foreach ($files as $f) {
    $src = file_get_contents($f);
    preg_match_all('/ui_form_open\(/', $src, $m); $postForms += count($m[0]);
}
ck('forms use the CSRF helper', $postForms > 0 && strpos($all, 'csrf_field()') !== false, 'forms=' . $postForms);

/* ---- 6. file access containment ---- */
ck('path containment helper exists', strpos($all, 'function sf_safe_path') !== false);
ck('signed links use HMAC+expiry', strpos($all, 'function storage_verify_signed') !== false && strpos($all, 'hash_equals') !== false);
ck('file route uses containment', strpos(file_get_contents($ROOT . '/core/router.php'), 'sf_safe_path') !== false);
ck('uploads validated', strpos($all, 'function sf_upload_validate') !== false);
ck('uploads block executables', strpos($all, 'Executable uploads are blocked') !== false);

/* ---- 7. session + headers ---- */
ck('session hardening', strpos($all, 'function sf_session_harden') !== false);
ck('cookie httponly+strict+samesite', strpos($all, "session.use_only_cookies") !== false && strpos($all, "session.cookie_samesite") !== false);
ck('security response headers', strpos($all, 'function sf_security_headers') !== false);
ck('CSP present', strpos($all, "Content-Security-Policy") !== false);
ck('HSTS when HTTPS', strpos($all, 'Strict-Transport-Security') !== false);
ck('no secrets rendered: config denied', strpos(file_get_contents($ROOT . '/.htaccess'), 'config.php') !== false);

/* ---- 8. brute force + policy ---- */
ck('login throttling', strpos($all, 'function sf_login_allowed') !== false && strpos($all, 'sf_login_fail') !== false);
ck('password policy', strpos($all, 'function sf_password_policy') !== false);
ck('rate limiting', strpos($all, 'function sf_rate_limit_check') !== false);
ck('audit logging', strpos($all, 'function audit(') !== false);

/* ---- 9. secrets handling ---- */
$hardCoded = array();
foreach ($files as $f) {
    $src = file_get_contents($f);
    if (preg_match('/(sk_live_|pk_live_|FLWSECK-|sk_test_[a-z0-9]{10,}|AIza[0-9A-Za-z_\-]{20,})/', $src)) { $hardCoded[] = str_replace($ROOT, '', $f); }
}
ck('no hard-coded live API keys', !$hardCoded, implode(', ', $hardCoded));
ck('no wallet withdrawal function', !preg_match('/function\s+wallet_(withdraw|payout|cashout)/', $all));

/* ---- 10. debug leftovers ---- */
$debug = array();
foreach ($files as $f) {
    $src = file_get_contents($f);
    if (preg_match('/\b(var_dump|print_r|error_log)\s*\(/', $src) && strpos($f, 'core/util.php') === false) { $debug[] = str_replace($ROOT, '', $f); }
}
ck('no debug dumps in shipped code', !$debug, implode(', ', array_slice($debug, 0, 4)));


/* ---- 10b. Apache config must never break the site ---- */
$unguarded_php_flag = function ($txt) {
    $stripped = preg_replace('#<IfModule[^>]*>.*?</IfModule>#is', '', (string) $txt);
    return (bool) preg_match('/php_flag/i', $stripped);
};
$rootHt = (string) @file_get_contents($ROOT . '/.htaccess');
ck('root .htaccess does not disable PHP (php_flag)', strpos($rootHt, 'php_flag') === false, 'php_flag found at site root');
ck('root .htaccess does not serve PHP as text', !preg_match('/AddType\s+text\/plain[^\n]*\.php/', $rootHt), 'AddType text/plain .php at root');
ck('root .htaccess avoids Options -ExecCGI', strpos($rootHt, '-ExecCGI') === false);
ck('root .htaccess is subdirectory-safe (no RewriteBase)', !preg_match('/^\s*RewriteBase/m', $rootHt));
$upHt = (string) @file_get_contents($ROOT . '/uploads/.htaccess');
ck('uploads/.htaccess denies PHP files', strpos($upHt, 'FilesMatch') !== false && strpos($upHt, 'denied') !== false);
ck('uploads/.htaccess php_flag is IfModule-guarded', !$unguarded_php_flag($upHt), 'unguarded php_flag -> HTTP 500 on PHP-FPM hosts');
$stHt = (string) @file_get_contents($ROOT . '/storage/.htaccess');
ck('storage/.htaccess denies all', strpos($stHt, 'denied') !== false || strpos($stHt, 'Deny from all') !== false);
if (function_exists('sf_htaccess_deny_exec')) {
    ck('generated uploads .htaccess is host-safe', !$unguarded_php_flag(sf_htaccess_deny_exec()));
    ck('shipped uploads .htaccess matches the generator', trim($upHt) === trim(sf_htaccess_deny_exec()));
}

/* ---- 11. unit: path containment really contains ---- */
if (!defined('SF_ROOT')) { define('SF_ROOT', $ROOT); }
if (!function_exists('sf_config')) { function sf_config($k, $d = null) { return $d; } }
require_once $ROOT . '/core/util.php';
require_once $ROOT . '/core/security.php';
@mkdir($ROOT . '/uploads/_sectest', 0755, true);
file_put_contents($ROOT . '/uploads/_sectest/ok.txt', 'x');
ck('sf_safe_path allows a file inside uploads/', sf_safe_path('uploads/_sectest/ok.txt') !== null);
ck('sf_safe_path blocks ../../config.php', sf_safe_path('../../config.php') === null);
ck('sf_safe_path blocks uploads/../../config.php', sf_safe_path('uploads/../../config.php') === null);
ck('sf_safe_path blocks null byte', sf_safe_path("uploads/a\0.txt") === null);
ck('sf_safe_path blocks remote schemes', sf_safe_path('http://evil.test/x') === null);
ck('sf_safe_path blocks absolute paths', sf_safe_path('/etc/passwd') === null);
$v = sf_upload_validate('/nonexistent', 'evil.php', array('php'));
ck('upload validation blocks .php', empty($v['ok']));
ck('password policy rejects common', sf_password_policy('password')['ok'] === false);
ck('password policy accepts strong', sf_password_policy('Tr0ubadour!23')['ok'] === true);
@unlink($ROOT . '/uploads/_sectest/ok.txt');
@rmdir($ROOT . '/uploads/_sectest');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

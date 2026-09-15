<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/**
 * SECURITY LAYER
 * ------------------------------------------------------------------
 * Defence in depth for a shared-hosting (cPanel) deployment:
 *   • hardened session cookies + session fixation protection
 *   • security response headers (CSP, nosniff, frame, referrer, HSTS, COOP)
 *   • brute-force login throttling and password policy
 *   • path-traversal-proof file resolution (realpath containment)
 *   • upload hardening (MIME sniffing, size, extension allowlist, random names)
 *   • dedicated HMAC signing key for expiring file links
 *   • suspicious-request auditing
 * Nothing here weakens functionality: every control is bypass-safe and logged.
 */

/* ------------------------------------------------------------ keys */
/** Reads a setting without assuming core/bootstrap.php is loaded yet (the installer
    loads this file before config.php exists), so security helpers never fatal. */
function sf_sec_cfg($key, $default = null) {
    return function_exists('sf_config') ? sf_config($key, $default) : $default;
}

/** Dedicated signing key for expiring links (never reuse the cron token for new installs). */
function sf_security_key() {
    $k = sf_sec_cfg('app.key', '');
    if (is_string($k) && strlen($k) >= 24) return $k;
    $legacy = (string) sf_sec_cfg('app.cron_token', '');
    if (strlen($legacy) >= 16) return 'sf-legacy-' . $legacy;
    return 'sf-insecure-default-change-me';
}

/* -------------------------------------------------------- sessions */
function sf_session_harden() {
    if (php_sapi_name() === 'cli') return;
    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) return;  /* already started */
    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    ini_set('session.use_only_cookies', '1');   // never accept session ids from URLs
    ini_set('session.use_strict_mode', '1');    // reject uninitialised session ids
    ini_set('session.cookie_httponly', '1');    // no JS access
    ini_set('session.cookie_samesite', 'Lax');  // CSRF defence in depth
    ini_set('session.gc_maxlifetime', '86400');
    if ($secure) { ini_set('session.cookie_secure', '1'); }
    session_name('SFSESSION');
    @session_set_cookie_params(array(
        'lifetime' => 0, 'path' => '/', 'httponly' => true,
        'samesite' => 'Lax', 'secure' => $secure,
    ));
}

/* --------------------------------------------------------- headers */
function sf_security_headers() {
    if (php_sapi_name() === 'cli' || headers_sent()) return;
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    /* NOTE: 'unsafe-inline' is required because the UI uses inline style attributes and
       inline event handlers. Everything else is locked down: no plugins, no framing,
       no foreign form posts, no base-tag hijacking. */
    /* Framing is denied by default. Opt in (config `security.allow_framing` or env
       SF_ALLOW_FRAMING=1) only when the app must live inside a partner portal / LMS. */
    $framing = (bool) sf_sec_cfg('security.allow_framing', false) || (bool) getenv('SF_ALLOW_FRAMING');
    $csp = "default-src 'self'; "
         . "script-src 'self' 'unsafe-inline'; "
         . "style-src 'self' 'unsafe-inline'; "
         . "img-src 'self' data: blob:; "
         . "media-src 'self' blob:; "
         . "font-src 'self' data:; "
         . "connect-src 'self'; "
         . "frame-src 'none'; object-src 'none'; base-uri 'self'; form-action 'self'; "
         . ($framing ? '' : "frame-ancestors 'none'; ")
         . "worker-src 'self' blob:";
    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    /* When embedding is opted in, no framing restriction is emitted at all — an iframe
       host is by definition cross-origin, so SAMEORIGIN would simply break the embed. */
    if (!$framing) { header('X-Frame-Options: DENY'); }
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header_remove('X-Powered-By');
    header_remove('Server');
    if ($https) { header('Strict-Transport-Security: max-age=31536000; includeSubDomains'); }
}

/* ------------------------------------------------- login throttling */
function sf_login_key($email) {
    return 'lf_' . md5(sf_ip() . '|' . strtolower(trim((string) $email)));
}
/** Returns array(ok, wait_seconds, attempts). Lock after 8 failures in 15 minutes. */
function sf_login_allowed($email) {
    $max = (int) sf_sec_cfg('security.login_max_attempts', 8);
    $win = (int) sf_sec_cfg('security.login_window', 900);
    $file = SF_ROOT . '/storage/cache/' . sf_login_key($email) . '.txt';
    $data = array('n' => 0, 't' => time());
    if (is_file($file)) {
        $raw = json_decode((string) @file_get_contents($file), true);
        if (is_array($raw)) { $data = $raw; }
    }
    if (time() - (int) $data['t'] > $win) { $data = array('n' => 0, 't' => time()); }
    if ((int) $data['n'] >= $max) {
        return array('ok' => false, 'wait' => max(1, $win - (time() - (int) $data['t'])), 'attempts' => (int) $data['n']);
    }
    return array('ok' => true, 'wait' => 0, 'attempts' => (int) $data['n']);
}
function sf_login_fail($email) {
    $file = SF_ROOT . '/storage/cache/' . sf_login_key($email) . '.txt';
    $data = array('n' => 0, 't' => time());
    if (is_file($file)) {
        $raw = json_decode((string) @file_get_contents($file), true);
        if (is_array($raw) && time() - (int) $raw['t'] <= (int) sf_sec_cfg('security.login_window', 900)) { $data = $raw; }
    }
    $data['n'] = (int) $data['n'] + 1;
    @file_put_contents($file, json_encode($data));
    if ((int) $data['n'] === (int) sf_sec_cfg('security.login_max_attempts', 8)) {
        sf_security_event('auth.bruteforce_lockout', (string) $email);
    }
    return (int) $data['n'];
}
function sf_login_clear($email) {
    $file = SF_ROOT . '/storage/cache/' . sf_login_key($email) . '.txt';
    if (is_file($file)) { @unlink($file); }
}

/* --------------------------------------------------- password policy */
function sf_password_policy($pass) {
    $min = (int) sf_sec_cfg('security.min_pass', 8);
    if (strlen((string) $pass) < $min) return array('ok' => false, 'error' => 'Password must be at least ' . $min . ' characters');
    $common = array('password', '12345678', '123456789', 'qwerty', 'letmein', 'admin', 'welcome',
        'iloveyou', 'abc123', 'password1', '1234567', 'storyfoundry', 'monkey', 'dragon', 'sunshine');
    if (in_array(strtolower((string) $pass), $common, true)) {
        return array('ok' => false, 'error' => 'That password is too common — choose a stronger one');
    }
    return array('ok' => true);
}

/* -------------------------------------------------- path containment */
/**
 * Resolve a relative path inside an allowed root, refusing anything that escapes it.
 * Returns an absolute path or null. Protects signed-link delivery and uploads.
 */
function sf_safe_path($rel, $roots = null) {
    if ($rel === null || $rel === '') return null;
    if (strpos($rel, "\0") !== false) return null;              // null byte
    if (preg_match('/^[a-zA-Z]+:\/\//', $rel)) return null;      // remote scheme
    $rel = str_replace('\\', '/', (string) $rel);
    $roots = $roots === null ? array(SF_ROOT . '/uploads') : (is_array($roots) ? $roots : array($roots));
    $full = realpath(SF_ROOT . '/' . ltrim($rel, '/'));
    if ($full === false) return null;
    foreach ($roots as $r) {
        $root = rtrim((string) realpath($r), '/');
        if ($root === '' || $root === '/') continue;
        if ($full === $root || strpos($full, $root . '/') === 0) {
            return is_file($full) ? $full : null;
        }
    }
    sf_security_event('path.traversal_blocked', substr((string) $rel, 0, 120));
    return null;
}

/* --------------------------------------------------------- uploads */
/** Extra validation applied before an upload is stored. Returns array(ok, error, ext). */
function sf_upload_validate($tmp, $origName, $allowedExt, $maxMb = null) {
    $maxMb = $maxMb === null ? (int) sf_sec_cfg('uploads.max_mb', 64) : (int) $maxMb;
    if (!is_uploaded_file($tmp)) return array('ok' => false, 'error' => 'Not an uploaded file');
    if (@filesize($tmp) > $maxMb * 1048576) return array('ok' => false, 'error' => 'File exceeds ' . $maxMb . 'MB limit');
    $ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, (array) $allowedExt, true)) return array('ok' => false, 'error' => 'File type not allowed: .' . $ext);
    /* never allow anything the web server could execute */
    if (in_array($ext, array('php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'htaccess'), true)) {
        return array('ok' => false, 'error' => 'Executable uploads are blocked');
    }
    if (in_array($ext, array('jpg', 'jpeg', 'png', 'webp', 'gif'), true)) {
        if (@getimagesize($tmp) === false) return array('ok' => false, 'error' => 'Uploaded image is not a valid image file');
    }
    return array('ok' => true, 'ext' => $ext);
}

/* --------------------------------------------------------- auditing */
function sf_security_event($kind, $detail = '') {
    if (function_exists('sf_log')) { sf_log('warn', 'security', $kind . ' ' . $detail); }
    if (function_exists('db')) {
        try { if (db()) { audit($kind, substr((string) $detail, 0, 180), 'warn'); } } catch (Exception $e) {}
    }
}

/* ---------------------------------------------- request sanity guard */
/**
 * Blocks obvious traversal / remote-inclusion attempts in structural parameters
 * (route, path, file ids). Content fields (story text, captions) are deliberately
 * NOT filtered — they are parameterised in SQL and escaped on output instead.
 */
function sf_guard_request() {
    $structural = array('r', 'p', 'a', 'file', 'path');
    foreach ($structural as $k) {
        if (!isset($_GET[$k])) continue;
        $v = (string) $_GET[$k];
        $bad = strpos($v, "\0") !== false          /* null byte truncation */
            || strpos($v, '..') !== false           /* directory traversal */
            || strpos($v, '://') !== false          /* remote inclusion / SSRF */
            || (isset($v[0]) && $v[0] === '/' && $k !== 'r');  /* absolute paths */
        if ($bad) {
            sf_security_event('request.blocked', $k . '=' . substr($v, 0, 80));
            http_response_code(400);
            echo 'Bad request';
            exit;
        }
    }
}

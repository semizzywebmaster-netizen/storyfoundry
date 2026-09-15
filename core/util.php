<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/** Small helpers: escaping, redirects, uploads, CSRF, flash, logging. */
function e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function sf_is_ajax() {
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_GET['format']) && $_GET['format'] === 'json');
}
function sf_json($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function sf_redirect($to) { header('Location: ' . $to); exit; }
function sf_url($path = '') {
    $base = rtrim(sf_config('app.url', '/'), '/');
    return $base . '/' . ltrim($path, '/');
}
function sf_ip() {
    foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR') as $k) {
        if (!empty($_SERVER[$k])) { $v = explode(',', $_SERVER[$k]); return trim($v[0]); }
    }
    return '0.0.0.0';
}
function sf_token($len = 24) {
    $len = max(2, (int) $len);
    $bytes = intdiv($len, 2);
    if (function_exists('random_bytes')) {
        try { return bin2hex(random_bytes($bytes)); } catch (Exception $e) { /* fall through */ }
    }
    /* last-resort fallback: still random, just not CSPRNG — never used for passwords */
    $out = '';
    while (strlen($out) < $len) { $out .= dechex(mt_rand(0, 15)); }
    return substr($out, 0, $len);
}
function sf_slug($s) { return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $s), '-')); }

/* ---- CSRF (feature 78) ---- */
function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = sf_token(32);
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check($throw = true) {
    if (!sf_config('security.csrf', true)) return true;
    $t = isset($_POST['csrf']) ? $_POST['csrf'] : (isset($_SERVER['HTTP_X_CSRF']) ? $_SERVER['HTTP_X_CSRF'] : '');
    $ok = !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $t);
    if (!$ok && $throw) {
        if (sf_is_ajax()) sf_json(array('ok' => false, 'error' => 'CSRF token invalid. Reload and retry.'), 419);
        http_response_code(419); echo 'Security token expired. Go back, reload the page and try again.'; exit;
    }
    return $ok;
}

/* ---- flash messages ---- */
function flash($msg, $kind = 'info') { $_SESSION['flash'][] = array('m' => $msg, 'k' => $kind); }
function flash_take() { $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : array(); unset($_SESSION['flash']); return $f; }

/* ---- money / time ---- */
function sf_money($kobo_or_naira, $currency = 'NGN') {
    $v = is_numeric($kobo_or_naira) ? (float) $kobo_or_naira : 0.0;
    return ($currency === 'NGN' ? '₦' : $currency . ' ') . number_format($v, 2);
}
function time_ago($ts) {
    $t = is_numeric($ts) ? (int) $ts : strtotime((string) $ts);
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    if ($d < 604800) return floor($d / 86400) . 'd ago';
    return date('j M Y', $t);
}

/* ---- logging (feature 77) ---- */
function sf_log($level, $src, $msg) {
    static $busy = false;                 /* guard: a failing log write must never recurse */
    $line = date('c') . "\t" . strtoupper($level) . "\t" . $src . "\t" . str_replace(array("\n", "\r"), ' ', $msg) . "\n";
    @file_put_contents(SF_ROOT . '/storage/logs/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND);
    if ($busy) return;
    if (db() && in_array($level, array('error', 'warn'), true)) {
        $busy = true;
        try { db_exec('INSERT INTO logs (level,src,message,created_at) VALUES (?,?,?,?)', array($level, $src, substr($msg, 0, 900), db_now())); }
        catch (Exception $ex) { /* the file log above already recorded it */ }
        $busy = false;
    }
}

/* ---- uploads (feature 78: validated, non-executable storage) ---- */
function sf_upload($field, $subdir = 'assets', $allowed = null) {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return array('ok' => false, 'error' => 'No file uploaded');
    $allowed = $allowed ? $allowed : explode(',', sf_config('uploads.allowed', 'jpg,jpeg,png,webp,mp3,mp4,pdf,zip'));
    $f = $_FILES[$field];
    $v = sf_upload_validate($f['tmp_name'], $f['name'], $allowed);
    if (empty($v['ok'])) return array('ok' => false, 'error' => $v['error']);
    $ext = $v['ext'];
    $dir = SF_ROOT . '/uploads/' . trim($subdir, '/');
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = date('Ymd') . '-' . sf_token(10) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) return array('ok' => false, 'error' => 'Could not save upload');
    return array('ok' => true, 'path' => 'uploads/' . trim($subdir, '/') . '/' . $name, 'name' => $f['name'], 'size' => (int) $f['size'], 'ext' => $ext);
}
function sf_public_path($rel) { return sf_url(ltrim($rel, '/')); }

/* ---- misc ---- */
function sf_get($k, $default = null) { return isset($_GET[$k]) ? $_GET[$k] : $default; }
function sf_post($k, $default = null) { return isset($_POST[$k]) ? $_POST[$k] : $default; }
function sf_int($v, $default = 0) { return (int) (is_numeric($v) ? $v : $default); }
function sf_json_in() {
    $raw = file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : array();
}
function sf_paging($total, $per = 20, $page = null) {
    $page = $page !== null ? max(1, (int) $page) : max(1, sf_int(sf_get('p'), 1));
    $pages = max(1, (int) ceil($total / $per));
    return array('limit' => $per, 'offset' => ($page - 1) * $per, 'page' => min($page, $pages), 'pages' => $pages, 'total' => $total);
}
function sf_download($path, $name = null) {
    if (!is_file($path)) { http_response_code(404); echo 'File not found'; exit; }
    $name = $name ? $name : basename($path);
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name) . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path); exit;
}

/* ---- project helpers shared by addons ---- */
function project_ctx($project) {
    $c = culture_context($project['culture']);
    return array_merge($c, array(
        'title' => $project['title'], 'genre' => $project['genre'], 'tone' => $project['tone'],
        'audience' => $project['audience'], 'theme' => $project['theme'], 'setting' => $project['setting'],
        'length' => $project['length'], 'culture' => $project['culture'],
    ));
}
function project_asset($project_id, $kind, $name, $path, $meta = array()) {
    $uid = auth_id();
    return db_insert('INSERT INTO assets (user_id,project_id,kind,name,path,mime,size,folder,meta,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)', array(
        $uid, (int) $project_id ?: null, $kind, $name, $path,
        function_exists('mime_content_type') && is_file(SF_ROOT . '/' . ltrim($path, '/')) ? mime_content_type(SF_ROOT . '/' . ltrim($path, '/')) : null,
        is_file(SF_ROOT . '/' . ltrim($path, '/')) ? filesize(SF_ROOT . '/' . ltrim($path, '/')) : 0,
        'Generated', json_encode($meta), db_now()
    ));
}
function project_log_activity($project_id, $what, $icon = '•') {
    db_exec('INSERT INTO project_activity (project_id,user_id,who,what,icon,created_at) VALUES (?,?,?,?,?,?)',
        array((int) $project_id, auth_id(), auth_user() ? auth_user()['name'] : 'System', $what, $icon, db_now()));
}
function project_touch($project_id) { db_exec('UPDATE projects SET updated_at=? WHERE id=?', array(db_now(), (int) $project_id)); }
/** Standard "generate" button markup used across stage addons. */
function ui_generate_btn($plugin, $action, $label, $feature, $payload = array(), $reload = true) {
    return '<button class="btn pri" data-api="' . e($plugin . '.' . $action) . '" data-payload=\'' . e(json_encode($payload)) . '\''
        . ($reload ? ' data-reload="1"' : '') . '>' . e($label) . ' · <span class="dim">' . (int) feature_cost($feature) . ' cr</span></button>';
}
function project_stage_url($project_id, $stage) { return sf_url('index.php?r=project/' . (int) $project_id . '/' . $stage); }

/** Human-readable byte size. */
function sf_bytes($n, $dec = 1) {
    $n = (float) $n;
    if ($n <= 0) return '0 B';
    $u = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = (int) floor(log($n, 1024)); $i = max(0, min($i, count($u) - 1));
    return number_format($n / pow(1024, $i), $i === 0 ? 0 : $dec) . ' ' . $u[$i];
}

/* ---- binary output helper used by provider addons ---- */
function sf_store_bytes($bytes, $ext, $dir = 'uploads/gen') {
    $dir = trim($dir, '/');
    $target = SF_ROOT . '/' . $dir;
    if (!is_dir($target)) { @mkdir($target, 0755, true); }
    $rel = $dir . '/' . date('Ym') . '-' . sf_token(16) . '.' . ltrim($ext, '.');
    if (@file_put_contents(SF_ROOT . '/' . $rel, $bytes) === false) return array('ok' => false, 'error' => 'Could not write file');
    return array('ok' => true, 'path' => $rel, 'bytes' => strlen($bytes));
}
/** Tiny cURL wrapper used by provider addons. Returns array(ok, status, body|error). */
function sf_http($url, $opts = array()) {
    if (!function_exists('curl_init')) return array('ok' => false, 'error' => 'cURL extension is not available on this server');
    $method = isset($opts['method']) ? strtoupper($opts['method']) : 'GET';
    $headers = isset($opts['headers']) ? $opts['headers'] : array();
    $body = isset($opts['body']) ? $opts['body'] : null;
    $timeout = (int) (isset($opts['timeout']) ? $opts['timeout'] : 60);
    $ch = curl_init($url);
    $o = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
    );
    if ($method === 'POST') { $o[CURLOPT_POST] = true; if ($body !== null) { $o[CURLOPT_POSTFIELDS] = $body; } }
    elseif ($method !== 'GET') { $o[CURLOPT_CUSTOMREQUEST] = $method; if ($body !== null) { $o[CURLOPT_POSTFIELDS] = $body; } }
    $hdrs = array();
    foreach ($headers as $k => $v) { $hdrs[] = is_int($k) ? $v : $k . ': ' . $v; }
    if ($hdrs) { $o[CURLOPT_HTTPHEADER] = $hdrs; }
    curl_setopt_array($ch, $o);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false) return array('ok' => false, 'error' => 'Network error: ' . $err);
    return array('ok' => $code >= 200 && $code < 300, 'status' => $code, 'body' => $res, 'error' => ($code >= 400 ? 'HTTP ' . $code : ''));
}

/* ---------------------------------------------------------------------------
 * Truncation helper. mbstring is NOT guaranteed on shared hosting (cPanel),
 * so prefer it when present and fall back to substr() otherwise.
 * ------------------------------------------------------------------------- */
function sf_sub($s, $start, $len = null) {
    $s = (string) $s;
    if (function_exists('mb_substr')) {
        return $len === null ? mb_substr($s, $start) : mb_substr($s, $start, $len);
    }
    return $len === null ? substr($s, $start) : substr($s, $start, $len);
}

/* ---------------------------------------------------------------------------
 * FILE CACHE (feature 76: performance — no Redis required; plain files)
 * Values are stored behind a PHP exit guard and never web-readable
 * (storage/ is denied by .htaccess). Used for expensive, read-mostly work
 * such as addon manifest discovery.
 * ------------------------------------------------------------------------- */
function sf_cache_dir() {
    $dir = SF_ROOT . '/storage/cache';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    return $dir;
}
function sf_cache_path($key) { return sf_cache_dir() . '/c_' . md5((string) $key) . '.php'; }
/** Read a cached value. Expired entries are deleted on read. */
function sf_cache_get($key, $default = null) {
    $f = sf_cache_path($key);
    if (!is_file($f)) return $default;
    $raw = @file_get_contents($f);
    if ($raw === false) return $default;
    $pos = strpos($raw, "\n");
    if ($pos === false) return $default;
    $data = json_decode(substr($raw, $pos + 1), true);   /* JSON only: cached payloads are never deserialized as PHP objects */
    if (!is_array($data)) { return $default; }
    if (!is_array($data) || !array_key_exists('exp', $data)) return $default;
    if ($data['exp'] > 0 && $data['exp'] < time()) { @unlink($f); return $default; }
    return $data['v'];
}
function sf_cache_set($key, $value, $ttl = 300) {
    /* the payload sits after a closing tag, so the file is valid PHP and prints nothing sensitive */
    $guard = "<?php if (!defined('SF_ROOT')) { http_response_code(403); exit; } ?>\n";
    $payload = array('exp' => $ttl > 0 ? (time() + (int) $ttl) : 0, 'v' => $value);
    $json = json_encode($payload);
    if ($json === false) { return false; }   /* non-UTF8 payload: skip caching rather than store junk */
    return @file_put_contents(sf_cache_path($key), $guard . $json, LOCK_EX) !== false;
}
function sf_cache_forget($key) { $f = sf_cache_path($key); if (is_file($f)) { @unlink($f); } return true; }
/** Delete cache files older than $seconds (called from cron). */
function sf_cache_prune($seconds = 86400) {
    $n = 0; $dir = sf_cache_dir();
    foreach (glob($dir . '/c_*.php') as $f) {
        $raw = @file_get_contents($f);
        $pos = $raw === false ? false : strpos($raw, "\n");
        $data = $pos === false ? null : json_decode(substr($raw, $pos + 1), true);
        $exp = is_array($data) && isset($data['exp']) ? (int) $data['exp'] : 0;
        if ($exp > 0 && $exp + $seconds < time()) { @unlink($f); $n++; }
    }
    return $n;
}

/* ---------------------------------------------------------------------------
 * THEME (feature 1: dark/light UI). Preference lives in a cookie for guests
 * and in the settings table for signed-in users. No personal data in the cookie.
 * ------------------------------------------------------------------------- */
function sf_theme() {
    $t = '';
    if (auth_id()) { $t = (string) setting('theme_' . (int) auth_id(), ''); }
    if ($t === '' && isset($_COOKIE['sf_theme'])) { $t = (string) $_COOKIE['sf_theme']; }
    return $t === 'light' ? 'light' : 'dark';
}
function sf_theme_set($theme) {
    $t = $theme === 'light' ? 'light' : 'dark';
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (!headers_sent()) {
        setcookie('sf_theme', $t, time() + 31536000, '/', '', $secure, true);
    }
    $_COOKIE['sf_theme'] = $t;
    if (auth_id()) { setting_set('theme_' . (int) auth_id(), $t); }
    return $t;
}
function sf_current_url() {
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'index.php';
    return sf_url(preg_replace('#^/+#', '', (string) $uri));
}

<?php
/**
 * STORYFOUNDRY core bootstrap — loads config, database, helpers, plugins.
 * PHP 7.4+ (no composer, no namespaces, cPanel friendly).
 */
if (!defined('SF_ROOT')) define('SF_ROOT', dirname(__DIR__));
if (!defined('SF_VERSION')) define('SF_VERSION', '1.0.0');

/* ---------- error handling ---------- */
$__cfg_probe = SF_ROOT . '/config.php';
$__is_prod = true;
if (is_file($__cfg_probe)) {
    $__c = require $__cfg_probe;
    $__is_prod = empty($__c['app']['debug']);
}
if ($__is_prod) { error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED); ini_set('display_errors', '0'); }
else { error_reporting(E_ALL); ini_set('display_errors', '1'); }
unset($__cfg_probe, $__is_prod, $__c);

/* ---------- config ---------- */
function sf_config($key = null, $default = null) {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = array();
        $file = SF_ROOT . '/config.php';
        if (is_file($file)) { $cfg = (array) require $file; }
        $sample = (array) require SF_ROOT . '/config.sample.php';
        $cfg = sf_array_merge_deep($sample, $cfg);
    }
    if ($key === null) return $cfg;
    $parts = explode('.', $key);
    $v = $cfg;
    foreach ($parts as $p) {
        if (!is_array($v) || !array_key_exists($p, $v)) return $default;
        $v = $v[$p];
    }
    return $v;
}
function sf_array_merge_deep($a, $b) {
    foreach ($b as $k => $v) {
        if (is_array($v) && isset($a[$k]) && is_array($a[$k])) $a[$k] = sf_array_merge_deep($a[$k], $v);
        else $a[$k] = $v;
    }
    return $a;
}
function sf_installed() { return is_file(SF_ROOT . '/config.php') && filesize(SF_ROOT . '/config.php') > 200; }

date_default_timezone_set(sf_config('app.timezone', 'Africa/Lagos'));

require_once __DIR__ . '/security.php';
if (php_sapi_name() !== 'cli' && !headers_sent()) {
    sf_session_harden();      /* must run before session_start() */
    sf_security_headers();
    sf_guard_request();
}

/* ---------- core includes ---------- */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/credits.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/media.php';
require_once __DIR__ . '/culture.php';
require_once __DIR__ . '/mockai.php';
require_once __DIR__ . '/providers.php';
require_once __DIR__ . '/orchestrator.php';
require_once __DIR__ . '/jobs.php';
require_once __DIR__ . '/plugins.php';
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/view.php';

/* ---------- boot ---------- */
$GLOBALS['SF_BOOTED'] = false;
function sf_boot() {
    /* config.php present but schema missing (interrupted install): send them back to the installer
       instead of letting every query fail */
    if (php_sapi_name() !== 'cli' && function_exists('db') && db()) {
        try { $hasUsers = db_val("SHOW TABLES LIKE 'users'", array(), null); }
        catch (Exception $e) { $hasUsers = null; }
        if (!$hasUsers) { sf_redirect(sf_url('install.php')); }
    }
    if (!empty($GLOBALS['SF_BOOTED'])) return;
    $GLOBALS['SF_BOOTED'] = true;

    db();                       // connect (fails soft when not installed)
    sf_ensure_dirs();
    auth_resume();              // load current user from session
    providers_bootstrap();      // register core + plugin providers
    plugins_bootstrap();        // discover, load and enable addons
    /* addons register providers during boot, so sync into the DB afterwards */
    if (db()) { try { providers_sync_db(); } catch (Exception $e) { sf_log('warn', 'providers', $e->getMessage()); } }
    /* self-heal: if a host interrupted the installer's seed step, restore the defaults
       so Admin → Feature governance and AI Providers are never empty */
    if (db()) {
        try {
            if ((int) db_val('SELECT COUNT(*) FROM feature_flags', array(), 0) === 0) {
                if (!function_exists('sf_features')) { require_once SF_ROOT . '/core/schema.php'; }
                foreach (sf_features() as $f) {
                    db_exec('INSERT IGNORE INTO feature_flags (fkey,name,enabled,min_plan,credit_cost,daily_limit,maintenance) VALUES (?,?,?,?,?,?,?)',
                        array($f[0], $f[1], 1, $f[3], $f[2], $f[4], 0));
                }
                sf_log('info', 'boot', 'self-healed: feature flags re-seeded');
            }
        } catch (Exception $e) { sf_log('warn', 'boot', 'feature self-heal failed: ' . $e->getMessage()); }
        try {
            if ((int) db_val('SELECT COUNT(*) FROM providers', array(), 0) === 0) { providers_sync_db(); }
        } catch (Exception $e) { sf_log('warn', 'boot', 'provider self-heal failed: ' . $e->getMessage()); }
    }
    sf_rate_limit_check();      // abuse prevention (feature 78)
}

/** Apache directives that stop uploads/ executing PHP.
 *  Every directive is guarded: hosts running PHP-FPM/CGI (most cPanel boxes) do not
 *  have mod_php, and an unguarded `php_flag` there is an unknown directive that
 *  returns HTTP 500 for the whole folder. */
function sf_htaccess_deny_exec() {
    return "# STORYFOUNDRY - files here are data, never code\n"
        . "Options -Indexes\n\n"
        . "<FilesMatch \"\\.(php|php3|php4|php5|php7|php8|phtml|phar|cgi|pl|py|sh|htaccess)$\">\n"
        . "  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n"
        . "  <IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n  </IfModule>\n"
        . "</FilesMatch>\n\n"
        . "<IfModule mod_php.c>\n  php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_php7.c>\n  php_flag engine off\n</IfModule>\n"
        . "<IfModule mod_php8.c>\n  php_flag engine off\n</IfModule>\n";
}
function sf_htaccess_deny_all() {
    return "# STORYFOUNDRY - private runtime storage, never served\n"
        . "Options -Indexes\n\n"
        . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
        . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n";
}
function sf_ensure_dirs() {
    foreach (array('uploads', 'uploads/assets', 'uploads/avatars', 'storage', 'storage/cache', 'storage/logs') as $d) {
        $p = SF_ROOT . '/' . $d;
        if (!is_dir($p)) { @mkdir($p, 0755, true); }
    }
    /* uploads/: deny PHP execution (host-safe) */
    $ht = SF_ROOT . '/uploads/.htaccess';
    $want = sf_htaccess_deny_exec();
    $cur = is_file($ht) ? (string) @file_get_contents($ht) : '';
    /* (re)write when missing, or when an older build left an UNGUARDED php_flag behind */
    $stripped = preg_replace('#<IfModule[^>]*>.*?</IfModule>#is', '', $cur);
    if ($cur === '' || preg_match('/php_flag/i', (string) $stripped)) { @file_put_contents($ht, $want); }
    /* storage/: deny everything */
    $hs = SF_ROOT . '/storage/.htaccess';
    if (!is_file($hs)) { @file_put_contents($hs, sf_htaccess_deny_all()); }
}

/* ---------- rate limiting (no Redis: file/DB backed) ---------- */
function sf_rate_limit_check() {
    if (!sf_config('security.rate_limit', true)) return true;
    $ip = sf_ip();
    $key = 'rl_' . md5($ip . '|' . date('YmdH'));
    $file = SF_ROOT . '/storage/cache/' . $key . '.txt';
    $n = 0;
    if (is_file($file)) { $n = (int) @file_get_contents($file); }
    if ($n > 900) { // ~900 requests/hour per IP
        if (sf_is_ajax()) { sf_json(array('ok' => false, 'error' => 'Rate limit exceeded'), 429); }
        http_response_code(429); echo 'Too many requests. Slow down.'; exit;
    }
    @file_put_contents($file, (string) ($n + 1));
    return true;
}

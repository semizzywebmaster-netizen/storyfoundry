<?php
/**
 * Queue worker entry point for cPanel cron.
 * Cron (every 5 min):  /usr/local/bin/php -q /home/USER/public_html/cron.php token=YOUR_TOKEN
 * It drains jobs, retries reservations, and cleans expired shares/tokens.
 */
define('SF_ROOT', __DIR__);
require_once SF_ROOT . '/core/bootstrap.php';

$token = isset($_GET['token']) ? $_GET['token'] : (isset($argv[1]) ? str_replace('token=', '', $argv[1]) : '');
$expected = sf_config('app.cron_token');
$cli = (php_sapi_name() === 'cli');
if (!$expected || !hash_equals((string) $expected, (string) $token)) {
    if (!$cli) { http_response_code(403); }
    echo "FORBIDDEN - bad or missing token\n";
    exit(1);
}

if (!sf_installed()) { echo "NOT INSTALLED\n"; exit(1); }
sf_boot();

$t0 = microtime(true);
$ran = jobs_run_due(50, 40);              // up to 40 jobs within 50 seconds

/* housekeeping: release orphan reservations older than 2h, drop expired shares */
try {
    db_exec("UPDATE reservations SET status='released', released_at=? WHERE status='held' AND created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)", array(db_now()));
    db_exec("UPDATE users u SET u.reserved = GREATEST(0, u.reserved - IFNULL((SELECT SUM(amount) FROM reservations r WHERE r.user_id=u.id AND r.status='held' AND r.created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)),0))");
    db_exec("DELETE FROM shares WHERE expires_at IS NOT NULL AND expires_at < NOW()");
    db_exec("DELETE FROM password_resets WHERE expires_at < NOW()");
    db_exec("DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $pruned = function_exists('sf_cache_prune') ? sf_cache_prune(86400) : 0;   /* stale file cache */
    if ($pruned) { sf_log('info', 'cron', 'pruned ' . $pruned . ' cache files'); }
} catch (Exception $e) { sf_log('error', 'cron', $e->getMessage()); }

/* hourly-ish hooks: addons do renewals, digests, expiries */
$lastHourly = (int) setting('cron_hourly_at', 0);
if (time() - $lastHourly > 3500) {
    try { do_action('cron.hourly'); setting_set('cron_hourly_at', time()); }
    catch (Exception $e) { sf_log('error', 'cron', 'hourly hook failed: ' . $e->getMessage()); }
}
setting_set('cron_last_run', db_now());

$ms = (int) ((microtime(true) - $t0) * 1000);
sf_log('info', 'cron', 'processed ' . $ran . ' jobs in ' . $ms . 'ms');
echo "OK processed={$ran} ms={$ms}\n";

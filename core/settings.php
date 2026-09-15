<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/** Key/value platform settings (feature 62/63: governance + configuration live in DB). */
function setting($key, $default = null) {
    static $cache = array();
    if (array_key_exists($key, $cache)) return $cache[$key];
    if (!db()) return $default;
    try {
        $row = db_one('SELECT v FROM settings WHERE k=?', array($key));
    } catch (Exception $ex) { return $default; }
    $v = $row ? $row['v'] : null;
    if ($v !== null) { $j = json_decode($v, true); $v = (json_last_error() === JSON_ERROR_NONE && is_array($j)) ? $j : $v; }
    else { $v = $default; }
    $cache[$key] = $v;
    return $v;
}
function setting_set($key, $value) {
    if (!db()) return false;
    $v = is_array($value) || is_bool($value) ? json_encode($value) : (string) $value;
    db_exec('INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)', array($key, $v));
    return true;
}
/** Feature governance: is a feature on, and may this user use it? */
function feature_flag($key) {
    static $c = array();
    if (isset($c[$key])) return $c[$key];
    $row = db() ? db_one('SELECT * FROM feature_flags WHERE fkey=?', array($key)) : null;
    $c[$key] = $row ? $row : array('fkey' => $key, 'name' => $key, 'enabled' => 1, 'min_plan' => 'free', 'credit_cost' => 1, 'daily_limit' => 100, 'maintenance' => 0);
    return $c[$key];
}
function feature_enabled($key) { $f = feature_flag($key); return !empty($f['enabled']) && empty($f['maintenance']); }
function feature_cost($key) { $f = feature_flag($key); return (int) $f['credit_cost']; }
function feature_allows_plan($key, $plan) {
    $f = feature_flag($key);
    $order = array('free' => 0, 'pro' => 1, 'studio' => 2, 'agency' => 3);
    $min = isset($order[strtolower((string) $f['min_plan'])]) ? $order[strtolower((string) $f['min_plan'])] : 0;
    $have = isset($order[strtolower((string) $plan)]) ? $order[strtolower((string) $plan)] : 0;
    return $have >= $min;
}

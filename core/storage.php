<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/** Storage abstraction (feature 38). Local driver is default; addons may override. */
$GLOBALS['SF_STORAGE_DRIVER'] = null;
function storage_register($driver) { $GLOBALS['SF_STORAGE_DRIVER'] = $driver; } // array('put'=>, 'url'=>, 'delete'=>, 'name'=>)
function storage_driver() {
    $d = $GLOBALS['SF_STORAGE_DRIVER'];
    if ($d) return $d;
    $d = apply_filters('storage.driver', null);
    if ($d) { $GLOBALS['SF_STORAGE_DRIVER'] = $d; return $d; }
    return array('name' => 'local', 'put' => 'storage_put_local', 'url' => 'storage_url_local', 'delete' => 'storage_delete_local');
}
function storage_put($rel_path, $binary) { $d = storage_driver(); return call_user_func($d['put'], $rel_path, $binary); }
function storage_url($rel_path) { $d = storage_driver(); return call_user_func($d['url'], $rel_path); }
function storage_delete($rel_path) { $d = storage_driver(); return call_user_func($d['delete'], $rel_path); }

function storage_put_local($rel, $bin) {
    $p = SF_ROOT . '/' . ltrim($rel, '/');
    $dir = dirname($p); if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return file_put_contents($p, $bin) !== false ? $rel : false;
}
function storage_url_local($rel) { return sf_url(ltrim($rel, '/')); }
function storage_delete_local($rel) { $p = SF_ROOT . '/' . ltrim($rel, '/'); return is_file($p) ? @unlink($p) : false; }
function storage_signed_url($rel, $seconds = 900) {
    // Local fallback: signed URL adds an expiry + HMAC so links can be time-boxed.
    $exp = time() + $seconds;
    $sig = substr(hash_hmac('sha256', $rel . '|' . $exp, sf_security_key()), 0, 32);
    return sf_url('index.php?r=file&p=' . urlencode($rel) . '&e=' . $exp . '&s=' . $sig);
}
function storage_verify_signed($rel, $exp, $sig) {
    if (time() > (int) $exp) return false;
    $want = substr(hash_hmac('sha256', $rel . '|' . (int) $exp, sf_security_key()), 0, 32);
    return hash_equals($want, (string) $sig);
}

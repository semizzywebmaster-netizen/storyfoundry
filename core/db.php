<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/** PDO MySQL layer with tiny query helpers. */
function db() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $c = sf_config('db');
    try {
        $pdo = new PDO(
            'mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=' . $c['charset'],
            $c['user'], $c['pass'],
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            )
        );
    } catch (Exception $e) {
        if (!sf_installed()) return null;      // installer will create config
        $pdo = null;
        sf_db_fatal($e);
    }
    return $pdo;
}
function sf_db_fatal($e) {
    $msg = sf_config('app.debug', false) ? $e->getMessage() : 'Database connection failed.';
    sf_log('error', 'db', $e->getMessage());
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><div style="font:15px system-ui;max-width:620px;margin:80px auto;padding:24px;border:1px solid #e2e6ec;border-radius:12px">'
        . '<h2>Database unavailable</h2><p>' . e($msg) . '</p>'
        . '<p>Check <code>config.php</code> (host, database name, user, password). On cPanel, the database and user are usually prefixed with your account name.</p>'
        . '<p><a href="install.php">Run the installer</a></p></div>';
    exit;
}
function q($sql, $params = array()) {
    $pdo = db(); if (!$pdo) return false;
    try { $st = $pdo->prepare($sql); $st->execute($params); return $st; }
    catch (Exception $e) { sf_log('error', 'db', $e->getMessage() . ' :: ' . $sql); throw $e; }
}
function db_all($sql, $params = array()) { $st = q($sql, $params); return $st ? $st->fetchAll() : array(); }
function db_one($sql, $params = array()) { $st = q($sql, $params); return $st ? $st->fetch() : null; }
function db_val($sql, $params = array(), $default = null) {
    $r = db_one($sql, $params);
    if (!$r) return $default;
    $v = array_values($r);
    return isset($v[0]) ? $v[0] : $default;
}
function db_exec($sql, $params = array()) { $st = q($sql, $params); return $st ? $st->rowCount() : 0; }
function db_insert($sql, $params = array()) { q($sql, $params); return (int) db()->lastInsertId(); }
function db_last_id() { return (int) db()->lastInsertId(); }
function db_begin() { $p = db(); if ($p && !$p->inTransaction()) $p->beginTransaction(); }
function db_commit() { $p = db(); if ($p && $p->inTransaction()) $p->commit(); }
function db_rollback() { $p = db(); if ($p && $p->inTransaction()) $p->rollBack(); }
function db_now() { return date('Y-m-d H:i:s'); }

<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/** Authentication, roles and RBAC (features 2, 3, 78). */
$GLOBALS['SF_USER'] = null;

function auth_roles() {
    return array(
        'creator'            => array('label' => 'Creator',            'level' => 10),
        'pro_creator'        => array('label' => 'Pro Creator',        'level' => 20),
        'studio'             => array('label' => 'Studio',             'level' => 30),
        'agency_owner'       => array('label' => 'Agency Owner',       'level' => 40),
        'agency_admin'       => array('label' => 'Agency Admin',       'level' => 41),
        'agency_member'      => array('label' => 'Agency Member',      'level' => 42),
        'client'             => array('label' => 'Client',             'level' => 15),
        'marketplace_seller' => array('label' => 'Marketplace Seller', 'level' => 25),
        'admin'              => array('label' => 'Admin',              'level' => 90),
        'super_admin'        => array('label' => 'Super Admin',        'level' => 100),
    );
}
function auth_role_label($key) { $r = auth_roles(); return isset($r[$key]) ? $r[$key]['label'] : ucfirst(str_replace('_', ' ', $key)); }
function auth_role_level($key) { $r = auth_roles(); return isset($r[$key]) ? $r[$key]['level'] : 0; }

function auth_resume() {
    if (!db()) return;
    if (!empty($_SESSION['uid'])) {
        try {
            $u = db_one('SELECT * FROM users WHERE id=? AND status<>?', array((int) $_SESSION['uid'], 'deleted'));
        } catch (Exception $e) { $u = null; }
        /* server-side session record lets a user revoke other devices */
        if ($u && !empty($_SESSION['sid'])) {
            try {
                $row = db_one('SELECT * FROM sessions WHERE token=? AND user_id=?', array($_SESSION['sid'], (int) $u['id']));
            } catch (Exception $e) { $row = null; }
            if (!$row) { $u = null; }   /* revoked elsewhere */
            else {
                if (empty($GLOBALS['SF_SESSION_TOUCHED'])) {
                    db_exec('UPDATE sessions SET last_at=? WHERE id=?', array(db_now(), (int) $row['id']));
                    $GLOBALS['SF_SESSION_TOUCHED'] = true;
                }
                $GLOBALS['SF_SESSION'] = $row;
            }
        }
        if ($u) { $GLOBALS['SF_USER'] = $u; return; }
        unset($_SESSION['uid'], $_SESSION['sid']);
    }
    $GLOBALS['SF_USER'] = null;
}
function auth_user() { return $GLOBALS['SF_USER']; }
function auth_id() { $u = $GLOBALS['SF_USER']; return $u ? (int) $u['id'] : 0; }
function auth_check() { return $GLOBALS['SF_USER'] !== null; }
function auth_is($role) { $u = auth_user(); return $u && $u['role'] === $role; }
function auth_at_least($role) { $u = auth_user(); if (!$u) return false; return auth_role_level($u['role']) >= auth_role_level($role); }
function auth_is_admin() { return auth_at_least('admin'); }
function auth_can($perm) {
    $u = auth_user(); if (!$u) return false;
    if (auth_at_least('admin')) return true;                 // admins inherit
    $map = array(
        'project.create'   => array('creator', 'pro_creator', 'studio', 'agency_owner', 'agency_admin', 'agency_member'),
        'project.manage'   => array('creator', 'pro_creator', 'studio', 'agency_owner', 'agency_admin', 'agency_member'),
        'credits.spend'    => array('creator', 'pro_creator', 'studio', 'agency_owner', 'agency_admin', 'agency_member'),
        'marketplace.sell' => array('marketplace_seller', 'pro_creator', 'studio', 'agency_owner'),
        'client.review'    => array('client', 'agency_owner', 'agency_admin'),
        'agency.manage'    => array('agency_owner', 'agency_admin'),
    );
    if (!isset($map[$perm])) return true;
    return in_array($u['role'], $map[$perm], true);
}
function require_login() {
    if (!auth_check()) {
        if (sf_is_ajax()) sf_json(array('ok' => false, 'error' => 'Login required', 'login' => true), 401);
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'];
        sf_redirect(sf_url('index.php?r=login'));
    }
}
function require_admin() {
    require_login();
    if (!auth_is_admin()) { http_response_code(403); echo 'Forbidden — admin only.'; exit; }
}

function auth_register($name, $email, $pass, $role = 'creator') {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return array('ok' => false, 'error' => 'Invalid email address');
    $pol = sf_password_policy($pass);
    if (!$pol['ok']) return array('ok' => false, 'error' => $pol['error']);
    if (db_val('SELECT COUNT(*) FROM users WHERE email=?', array($email), 0) > 0) return array('ok' => false, 'error' => 'An account with that email already exists');
    $hash = password_hash($pass, PASSWORD_BCRYPT, array('cost' => 11));
    $free = (int) setting('credits_new_user', 150);
    $ref  = strtoupper('SF-' . substr(md5($email . microtime(true)), 0, 6));
    $uid = db_insert('INSERT INTO users (name,email,username,pass_hash,role,plan,credits,reserved,wallet,referral_code,culture,lang,tz,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', array(
        $name, $email, sf_slug($name) . '-' . substr($ref, 3, 4), $hash, $role, 'free', $free, 0, 0, $ref,
        'Yoruba', 'English (Nigeria)', sf_config('app.timezone', 'Africa/Lagos'), 'active', db_now()
    ));
    // free-credit grant recorded in the ledger (feature 56)
    credits_grant($uid, $free, 'new user bonus', 'signup');
    sf_log('info', 'auth', 'registered ' . $email);
    audit('auth.register', $email, 'info');
    $u = db_one('SELECT * FROM users WHERE id=?', array($uid));
    auth_start_session($u);
    return array('ok' => true, 'user' => $u);
}
function auth_login($email, $pass) {
    $gate = sf_login_allowed($email);
    if (empty($gate['ok'])) {
        sf_security_event('auth.throttled', (string) $email);
        return array('ok' => false, 'error' => 'Too many failed sign-in attempts. Try again in ' . (int) ceil(((int) $gate['wait']) / 60) . ' minute(s).');
    }
    $u = db_one('SELECT * FROM users WHERE email=?', array(strtolower(trim($email))));
    if (!$u || !password_verify($pass, $u['pass_hash'])) {
        audit('auth.login_failed', (string) $email, 'warn');
        sf_login_fail($email);
        return array('ok' => false, 'error' => 'Incorrect email or password');
    }
    sf_login_clear($email);
    if ($u['status'] !== 'active') return array('ok' => false, 'error' => 'This account is ' . $u['status']);
    db_exec('UPDATE users SET last_login=? WHERE id=?', array(db_now(), $u['id']));
    auth_start_session($u);
    audit('auth.login', $u['email'], 'info');
    return array('ok' => true, 'user' => $u);
}
function auth_start_session($u) {
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    $_SESSION['csrf'] = sf_token(32);
    if (db()) {
        $token = sf_token(48);
        $_SESSION['sid'] = $token;
        db_exec('INSERT INTO sessions (user_id,token,ip,agent,created_at,last_at) VALUES (?,?,?,?,?,?)', array(
            (int) $u['id'], $token, sf_ip(),
            isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 250) : null,
            db_now(), db_now()));
    }
    $GLOBALS['SF_USER'] = $u;
}
function auth_logout() {
    audit('auth.logout', auth_user() ? auth_user()['email'] : '', 'info');
    if (db() && !empty($_SESSION['sid'])) { try { db_exec('DELETE FROM sessions WHERE token=?', array($_SESSION['sid'])); } catch (Exception $e) {} }
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
function auth_reset_request($email) {
    $u = db_one('SELECT * FROM users WHERE email=?', array(strtolower(trim($email))));
    if (!$u) return array('ok' => true, 'note' => 'If that account exists, a reset link was sent.');
    $tok = sf_token(32);
    db_exec('INSERT INTO password_resets (user_id,token,expires_at,created_at) VALUES (?,?,?,?)',
        array($u['id'], $tok, date('Y-m-d H:i:s', time() + 3600), db_now()));
    $link = sf_url('index.php?r=reset&token=' . $tok);
    sf_log('info', 'auth', 'password reset link for ' . $u['email'] . ' -> ' . $link);
    audit('auth.reset_request', $u['email'], 'info');
    // cPanel: use PHP mail(). Configure SMTP in cPanel if mail() is restricted.
    @mail($u['email'], 'STORYFOUNDRY password reset', "Reset your password:\n\n" . $link . "\n\nLink expires in 1 hour.");
    return array('ok' => true, 'note' => 'If that account exists, a reset link was sent.');
}
function auth_reset_do($token, $pass) {
    $r = db_one('SELECT * FROM password_resets WHERE token=? AND used=0 AND expires_at>NOW()', array($token));
    if (!$r) return array('ok' => false, 'error' => 'Invalid or expired reset token');
    if (strlen($pass) < (int) sf_config('security.min_pass', 8)) return array('ok' => false, 'error' => 'Password too short');
    db_exec('UPDATE users SET pass_hash=? WHERE id=?', array(password_hash($pass, PASSWORD_BCRYPT, array('cost' => 11)), $r['user_id']));
    db_exec('UPDATE password_resets SET used=1 WHERE id=?', array($r['id']));
    audit('auth.reset_complete', 'user#' . $r['user_id'], 'info');
    return array('ok' => true);
}

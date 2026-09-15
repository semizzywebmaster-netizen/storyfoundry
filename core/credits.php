<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/**
 * CREDIT LEDGER — contracts: check → reserve → execute → consume | release
 * Also: wallet (no withdrawal), transactions, usage limits (features 48, 49, 50).
 */
function credits_available($user_id) {
    $u = db_one('SELECT credits,reserved FROM users WHERE id=?', array((int) $user_id));
    if (!$u) return 0;
    return max(0, (int) $u['credits'] - (int) $u['reserved']);
}
function credits_check($user_id, $amount) { return credits_available($user_id) >= (int) $amount; }

function credits_reserve($user_id, $amount, $ref = 'job', $job_id = null) {
    $amount = max(0, (int) $amount);
    if ($amount === 0) return array('ok' => true, 'id' => 0, 'amount' => 0);
    if (!credits_check($user_id, $amount)) return array('ok' => false, 'error' => 'Insufficient credits');
    db_begin();
    try {
        db_exec('UPDATE users SET reserved = reserved + ? WHERE id=?', array($amount, (int) $user_id));
        $id = db_insert('INSERT INTO reservations (user_id,job_id,amount,ref,status,created_at) VALUES (?,?,?,?,?,?)',
            array((int) $user_id, $job_id, $amount, (string) $ref, 'held', db_now()));
        db_commit();
        return array('ok' => true, 'id' => (int) $id, 'amount' => $amount);
    } catch (Exception $ex) { db_rollback(); return array('ok' => false, 'error' => $ex->getMessage()); }
}
function credits_consume($reservation) {
    if (!$reservation || empty($reservation['id'])) return true;
    db_begin();
    try {
        $r = db_one('SELECT * FROM reservations WHERE id=? AND status=?', array($reservation['id'], 'held'));
        if (!$r) { db_commit(); return true; }
        db_exec('UPDATE users SET credits = credits - ?, reserved = reserved - ? WHERE id=?', array((int) $r['amount'], (int) $r['amount'], (int) $r['user_id']));
        db_exec('UPDATE reservations SET status=?, released_at=? WHERE id=?', array('consumed', db_now(), $r['id']));
        db_exec('INSERT INTO credit_tx (user_id,type,amount,ref,note,created_at) VALUES (?,?,?,?,?,?)',
            array((int) $r['user_id'], 'spend', -1 * (int) $r['amount'], 'SF-CR-' . strtoupper(sf_token(5)), $r['ref'], db_now()));
        db_commit();
        return true;
    } catch (Exception $ex) { db_rollback(); sf_log('error', 'credits', $ex->getMessage()); return false; }
}
function credits_release($reservation) {
    if (!$reservation || empty($reservation['id'])) return true;
    db_begin();
    try {
        $r = db_one('SELECT * FROM reservations WHERE id=? AND status=?', array($reservation['id'], 'held'));
        if (!$r) { db_commit(); return true; }
        db_exec('UPDATE users SET reserved = reserved - ? WHERE id=?', array((int) $r['amount'], (int) $r['user_id']));
        db_exec('UPDATE reservations SET status=?, released_at=? WHERE id=?', array('released', db_now(), $r['id']));
        db_exec('INSERT INTO credit_tx (user_id,type,amount,ref,note,created_at) VALUES (?,?,?,?,?,?)',
            array((int) $r['user_id'], 'refund', (int) $r['amount'], 'SF-RF-' . strtoupper(sf_token(5)), 'released: ' . $r['ref'], db_now()));
        db_commit();
        return true;
    } catch (Exception $ex) { db_rollback(); sf_log('error', 'credits', $ex->getMessage()); return false; }
}
function credits_grant($user_id, $amount, $note = 'grant', $type = 'grant') {
    $amount = (int) $amount;
    db_exec('UPDATE users SET credits = credits + ? WHERE id=?', array($amount, (int) $user_id));
    db_exec('INSERT INTO credit_tx (user_id,type,amount,ref,note,created_at) VALUES (?,?,?,?,?,?)',
        array((int) $user_id, $type, $amount, 'SF-GR-' . strtoupper(sf_token(5)), $note, db_now()));
    return true;
}

/* ---- wallet: deposit + spend only. NO WITHDRAWAL (feature 50). ---- */
function wallet_balance($user_id) { return (float) db_val('SELECT wallet FROM users WHERE id=?', array((int) $user_id), 0); }
function wallet_deposit($user_id, $amount, $method, $ref) {
    $amount = (float) $amount;
    db_exec('UPDATE users SET wallet = wallet + ? WHERE id=?', array($amount, (int) $user_id));
    db_exec('INSERT INTO transactions (user_id,type,ref,amount,currency,method,status,note,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
        array((int) $user_id, 'wallet_deposit', $ref, $amount, 'NGN', $method, 'verified', 'Wallet top-up', db_now()));
    audit('wallet.deposit', $ref . ' ' . $amount, 'info');
    return true;
}
function wallet_spend($user_id, $amount, $note, $ref = '') {
    $amount = (float) $amount;
    if (wallet_balance($user_id) < $amount) return array('ok' => false, 'error' => 'Insufficient wallet balance');
    db_exec('UPDATE users SET wallet = wallet - ? WHERE id=?', array($amount, (int) $user_id));
    db_exec('INSERT INTO transactions (user_id,type,ref,amount,currency,method,status,note,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
        array((int) $user_id, 'wallet_spend', $ref ? $ref : ('SF-W-' . strtoupper(sf_token(5))), $amount, 'NGN', 'wallet', 'verified', $note, db_now()));
    return array('ok' => true);
}

/* ---- usage limits (feature 49) ---- */
function usage_count_today($user_id, $feature) {
    return (int) db_val('SELECT COUNT(*) FROM usage_log WHERE user_id=? AND feature=? AND created_at>=?',
        array((int) $user_id, $feature, date('Y-m-d 00:00:00')), 0);
}
function usage_within_limit($user_id, $feature) {
    $f = feature_flag($feature);
    $limit = (int) $f['daily_limit'];
    if ($limit <= 0) return true;
    return usage_count_today($user_id, $feature) < $limit;
}
function usage_log($user_id, $provider, $model, $feature, $credits, $ms, $status, $tokens = 0, $cost = 0) {
    db_exec('INSERT INTO usage_log (user_id,provider,model,feature,tokens,credits,cost,ms,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
        array((int) $user_id, (string) $provider, (string) $model, (string) $feature, (int) $tokens, (int) $credits, (float) $cost, (int) $ms, $status, db_now()));
    if ($provider) {
        db_exec('UPDATE providers SET calls=calls+1' . ($status === 'error' ? ', errors=errors+1' : '') . ' WHERE pkey=?', array($provider));
    }
}

<?php
class SFPlugin_paystack extends PluginBase {
    private function cfg() {
        return array(
            'public' => setting('paystack_public', ''),
            'secret' => setting('paystack_secret', ''),
            'enabled' => (int) setting('paystack_enabled', 0),
        );
    }
    public function status() {
        $c = $this->cfg();
        if (!$c['enabled']) return 'DISABLED';
        return ($c['public'] && $c['secret']) ? 'REAL' : 'NOT CONFIGURED';
    }
    public function api($action, $in) {
        $c = $this->cfg();
        if ($action === 'init') {
            $amount = (float) (isset($in['amount']) ? $in['amount'] : 0);
            if ($amount <= 0) return array('ok' => false, 'error' => 'Invalid amount');
            if ($this->status() !== 'REAL') {
                return array('ok' => false, 'error' => 'Paystack is ' . $this->status() . ' — add keys in Admin → Settings', 'status' => $this->status());
            }
            $ref = 'SF-PS-' . strtoupper(sf_token(8));
            $r = sf_http('https://api.paystack.co/transaction/initialize', array(
                'method' => 'POST',
                'headers' => array('Authorization' => 'Bearer ' . $c['secret'], 'Content-Type' => 'application/json'),
                'body' => json_encode(array(
                    'email' => auth_user()['email'], 'amount' => (int) round($amount * 100),
                    'reference' => $ref, 'currency' => 'NGN',
                    'callback_url' => sf_url('index.php?r=paystack/callback'),
                    'metadata' => array('user_id' => auth_id(), 'credits' => (int) (isset($in['credits']) ? $in['credits'] : 0), 'type' => isset($in['type']) ? $in['type'] : 'credit_purchase'),
                )), 'timeout' => 45));
            if (empty($r['ok'])) return array('ok' => false, 'error' => 'Paystack init failed: ' . (isset($r['error']) ? $r['error'] : 'request failed'));
            $j = json_decode($r['body'], true);
            if (empty($j['status']) || empty($j['data']['authorization_url'])) return array('ok' => false, 'error' => 'Paystack did not return an authorization URL');
            db_exec('INSERT INTO transactions (user_id,type,ref,amount,currency,method,status,note,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
                array(auth_id(), isset($in['type']) ? $in['type'] : 'credit_purchase', $ref, $amount, 'NGN', 'paystack', 'pending',
                    (int) (isset($in['credits']) ? $in['credits'] : 0) . ' credits', db_now()));
            audit('payment.init', $ref . ' ' . $amount, 'info');
            return array('ok' => true, 'redirect' => $j['data']['authorization_url'], 'ref' => $ref, 'status' => 'REAL');
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
    /** Server-side verification: value is granted ONLY after a successful verify call. */
    public function route($route, $get, $post) {
        $c = $this->cfg();
        $ref = isset($get['reference']) ? preg_replace('/[^A-Za-z0-9\-_]/', '', $get['reference']) : (isset($get['trxref']) ? $get['trxref'] : '');
        if (!$ref) { flash('Missing payment reference', 'err'); return sf_response_redirect(sf_url('index.php?r=billing/credits')); }
        $tx = db_one("SELECT * FROM transactions WHERE ref=? AND method='paystack'", array($ref));
        if (!$tx) { flash('Unknown transaction', 'err'); return sf_response_redirect(sf_url('index.php?r=billing/credits')); }
        if ($tx['status'] === 'verified') { flash('Payment already verified', 'ok'); return sf_response_redirect(sf_url('index.php?r=billing/credits')); }
        if ($this->status() !== 'REAL') { flash('Paystack is NOT CONFIGURED — cannot verify', 'err'); return sf_response_redirect(sf_url('index.php?r=billing/credits')); }
        $r = sf_http('https://api.paystack.co/transaction/verify/' . rawurlencode($ref), array(
            'headers' => array('Authorization' => 'Bearer ' . $c['secret']), 'timeout' => 45));
        if (empty($r['ok'])) { flash('Could not verify payment — try again', 'err'); return sf_response_redirect(sf_url('index.php?r=billing/transactions')); }
        $j = json_decode($r['body'], true);
        $ok = !empty($j['status']) && !empty($j['data']['status']) && $j['data']['status'] === 'success';
        $paid = isset($j['data']['amount']) ? ((int) $j['data']['amount']) / 100 : 0;
        $expected = (float) $tx['amount'];
        if ($ok && $paid + 0.01 < $expected) { $ok = false; }
        if (!$ok) {
            db_exec('UPDATE transactions SET status=? WHERE id=?', array('failed', (int) $tx['id']));
            audit('payment.failed', $ref, 'warn');
            flash('Payment not confirmed by Paystack', 'err');
            return sf_response_redirect(sf_url('index.php?r=billing/transactions'));
        }
        db_exec('UPDATE transactions SET status=? WHERE id=?', array('verified', (int) $tx['id']));
        $credits = (int) preg_replace('/[^0-9]/', '', (string) $tx['note']);
        if ($credits > 0) { credits_grant((int) $tx['user_id'], $credits, 'paystack ' . $ref, 'purchase'); }
        notify((int) $tx['user_id'], 'ok', 'Payment confirmed', e(sf_money($paid)) . ' verified — ' . $credits . ' credits added.');
        audit('payment.verified', $ref . ' ' . $paid, 'info');
        flash('Payment verified — ' . $credits . ' credits added', 'ok');
        return sf_response_redirect(sf_url('index.php?r=billing/credits'));
    }
    public function admin($input) {
        $c = $this->cfg();
        $st = $this->status();
        return '<div class="card"><h3>Paystack</h3>'
            . '<div class="kv"><span>Status</span>' . ui_status($st) . '</div>'
            . '<div class="kv"><span>Public key</span><span class="mono dim" style="font-size:11.5px">' . e($c['public'] ? substr($c['public'], 0, 10) . '…' : 'not set') . '</span></div>'
            . '<div class="kv"><span>Secret key</span><span class="mono dim" style="font-size:11.5px">' . e($c['secret'] ? '•••••••• (hidden)' : 'not set') . '</span></div>'
            . '<div class="kv"><span>Callback URL</span><span class="mono dim" style="font-size:11.5px">' . e(sf_url('index.php?r=paystack/callback')) . '</span></div>'
            . '<div class="banner info" style="margin-top:12px"><span>ℹ</span><div style="font-size:12px">Keys are set in Admin → Settings. '
            . 'Credits and wallet value are granted <b>only</b> after a server-side verify call confirms the amount.</div></div></div>';
    }
    public function menu() { return array(array('route' => 'paystack', 'label' => 'Paystack', 'icon' => '🏦', 'group' => 'admin', 'perm' => 'admin')); }
}

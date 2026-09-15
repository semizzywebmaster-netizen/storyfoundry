<?php
class SFPlugin_flutterwave extends PluginBase {
    private function cfg() {
        return array(
            'public' => setting('flutterwave_public', ''),
            'secret' => setting('flutterwave_secret', ''),
            'enabled' => (int) setting('flutterwave_enabled', 0),
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
            if ($this->status() !== 'REAL') return array('ok' => false, 'error' => 'Flutterwave is ' . $this->status() . ' — add keys in Admin → Settings', 'status' => $this->status());
            $u = auth_user();
            $ref = 'SF-FW-' . strtoupper(sf_token(8));
            $r = sf_http('https://api.flutterwave.com/v3/payments', array(
                'method' => 'POST',
                'headers' => array('Authorization' => 'Bearer ' . $c['secret'], 'Content-Type' => 'application/json'),
                'body' => json_encode(array(
                    'tx_ref' => $ref, 'amount' => $amount, 'currency' => 'NGN',
                    'redirect_url' => sf_url('index.php?r=flutterwave/callback'),
                    'customer' => array('email' => $u['email'], 'name' => $u['name']),
                    'customizations' => array('title' => 'STORYFOUNDRY', 'logo' => sf_url('assets/img/logo.png')),
                    'meta' => array('user_id' => auth_id(), 'credits' => (int) (isset($in['credits']) ? $in['credits'] : 0)),
                )), 'timeout' => 45));
            if (empty($r['ok'])) return array('ok' => false, 'error' => 'Flutterwave init failed: ' . (isset($r['error']) ? $r['error'] : 'request failed'));
            $j = json_decode($r['body'], true);
            if (empty($j['data']['link'])) return array('ok' => false, 'error' => 'Flutterwave did not return a payment link');
            db_exec('INSERT INTO transactions (user_id,type,ref,amount,currency,method,status,note,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
                array(auth_id(), isset($in['type']) ? $in['type'] : 'credit_purchase', $ref, $amount, 'NGN', 'flutterwave', 'pending',
                    (int) (isset($in['credits']) ? $in['credits'] : 0) . ' credits', db_now()));
            audit('payment.init', $ref . ' ' . $amount, 'info');
            return array('ok' => true, 'redirect' => $j['data']['link'], 'ref' => $ref, 'status' => 'REAL');
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
    public function route($route, $get, $post) {
        $c = $this->cfg();
        $status = isset($get['status']) ? $get['status'] : '';
        $txid = isset($get['transaction_id']) ? preg_replace('/[^0-9]/', '', $get['transaction_id']) : '';
        $ref = isset($get['tx_ref']) ? preg_replace('/[^A-Za-z0-9\-_]/', '', $get['tx_ref']) : '';
        if ($status !== 'successful' || !$txid) { flash('Payment not completed', 'err'); return sf_response_redirect(sf_url('index.php?r=billing/credits')); }
        $tx = db_one("SELECT * FROM transactions WHERE ref=? AND method='flutterwave'", array($ref));
        if (!$tx) { flash('Unknown transaction', 'err'); return sf_response_redirect(sf_url('index.php?r=billing/credits')); }
        if ($tx['status'] === 'verified') { flash('Payment already verified', 'ok'); return sf_response_redirect(sf_url('index.php?r=billing/credits')); }
        if ($this->status() !== 'REAL') { flash('Flutterwave is NOT CONFIGURED — cannot verify', 'err'); return sf_response_redirect(sf_url('index.php?r=billing/credits')); }
        $r = sf_http('https://api.flutterwave.com/v3/transactions/' . $txid . '/verify', array(
            'headers' => array('Authorization' => 'Bearer ' . $c['secret']), 'timeout' => 45));
        if (empty($r['ok'])) { flash('Could not verify payment', 'err'); return sf_response_redirect(sf_url('index.php?r=billing/transactions')); }
        $j = json_decode($r['body'], true);
        $d = isset($j['data']) ? $j['data'] : array();
        $ok = !empty($d['status']) && $d['status'] === 'successful' && isset($d['amount']) && (float) $d['amount'] + 0.01 >= (float) $tx['amount'];
        if (!$ok) {
            db_exec('UPDATE transactions SET status=? WHERE id=?', array('failed', (int) $tx['id']));
            audit('payment.failed', (string) $ref, 'warn');
            flash('Payment not confirmed by Flutterwave', 'err');
            return sf_response_redirect(sf_url('index.php?r=billing/transactions'));
        }
        db_exec('UPDATE transactions SET status=? WHERE id=?', array('verified', (int) $tx['id']));
        $credits = (int) preg_replace('/[^0-9]/', '', (string) $tx['note']);
        if ($credits > 0) { credits_grant((int) $tx['user_id'], $credits, 'flutterwave ' . $ref, 'purchase'); }
        notify((int) $tx['user_id'], 'ok', 'Payment confirmed', e(sf_money((float) $d['amount'])) . ' verified — ' . $credits . ' credits added.');
        audit('payment.verified', (string) $ref, 'info');
        flash('Payment verified — ' . $credits . ' credits added', 'ok');
        return sf_response_redirect(sf_url('index.php?r=billing/credits'));
    }
    public function admin($input) {
        $c = $this->cfg();
        return '<div class="card"><h3>Flutterwave</h3>'
            . '<div class="kv"><span>Status</span>' . ui_status($this->status()) . '</div>'
            . '<div class="kv"><span>Public key</span><span class="mono dim" style="font-size:11.5px">' . e($c['public'] ? substr($c['public'], 0, 10) . '…' : 'not set') . '</span></div>'
            . '<div class="kv"><span>Secret key</span><span class="mono dim" style="font-size:11.5px">' . e($c['secret'] ? '•••••••• (hidden)' : 'not set') . '</span></div>'
            . '<div class="kv"><span>Redirect URL</span><span class="mono dim" style="font-size:11.5px">' . e(sf_url('index.php?r=flutterwave/callback')) . '</span></div>'
            . '<div class="banner info" style="margin-top:12px"><span>ℹ</span><div style="font-size:12px">Amount and currency are re-checked server-side before any credit is granted.</div></div></div>';
    }
    public function menu() { return array(array('route' => 'flutterwave', 'label' => 'Flutterwave', 'icon' => '🏦', 'group' => 'admin', 'perm' => 'admin')); }
}

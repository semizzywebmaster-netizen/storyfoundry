<?php
class SFPlugin_billing_suite extends PluginBase {
    public function dashboard() {
        $uid = auth_id();
        $spent = (int) db_val("SELECT IFNULL(SUM(amount),0) FROM credit_tx WHERE user_id=? AND amount<0", array($uid), 0);
        $h = '<div class="card"><h3>Credits</h3>'
            . '<div class="grid g2" style="gap:10px">'
            . '<div class="kpi"><b>' . (int) credits_available($uid) . '</b><span>Available</span></div>'
            . '<div class="kpi"><b>' . abs($spent) . '</b><span>Lifetime spent</span></div></div>'
            . '<button class="btn pri blk" style="margin-top:11px" data-api="billing-suite.daily">🎁 Claim daily bonus (25)</button>'
            . '<div class="row" style="gap:6px;margin-top:8px">'
            . '<a class="btn sm gho" href="' . e(sf_url('index.php?r=billing/credits')) . '">Buy credits</a>'
            . '<a class="btn sm gho" href="' . e(sf_url('index.php?r=billing/wallet')) . '">Wallet</a></div>'
            . '<div class="dim" style="font-size:11.5px;margin-top:9px">Wallet funds can be spent inside STORYFOUNDRY only — there is no withdrawal path.</div></div>';
        return array($h);
    }
    public function admin($input) {
        if ($_POST && sf_post('act') === 'coupon_new') {
            csrf_check();
            db_exec('INSERT INTO coupons (code,type,value,uses,max_uses,status,created_at) VALUES (?,?,?,0,?,?,?)',
                array(strtoupper(trim((string) sf_post('code'))), sf_post('type'), (int) sf_post('value'), (int) sf_post('max_uses'), 'active', db_now()));
            flash('Coupon created', 'ok'); sf_redirect(sf_url('index.php?r=admin&p=billing-suite'));
        }
        $rev = (float) db_val("SELECT IFNULL(SUM(amount),0) FROM transactions WHERE status IN ('verified','paid')", array(), 0);
        $wallet = (float) db_val('SELECT IFNULL(SUM(wallet),0) FROM users', array(), 0);
        $held = (int) db_val("SELECT IFNULL(SUM(amount),0) FROM reservations WHERE status='held'", array(), 0);
        $h = '<div class="grid g4" style="margin-bottom:14px">' . ui_kpi(e(sf_money($rev)), 'Verified revenue')
            . ui_kpi(e(sf_money($wallet)), 'Wallet balances') . ui_kpi($held, 'Credits held') . ui_kpi((int) db_val('SELECT COUNT(*) FROM transactions', array(), 0), 'Transactions') . '</div>';
        $h .= '<div class="card"><h3>Coupons</h3>' . ui_form_open(sf_url('index.php?r=admin&p=billing-suite')) . '<input type="hidden" name="act" value="coupon_new">'
            . '<div class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-end">'
            . '<label class="fl" style="margin:0">Code<input name="code" placeholder="LAUNCH50"></label>'
            . '<label class="fl" style="margin:0">Type' . ui_select('type', array('credit' => 'Credits', 'percent' => 'Percent off')) . '</label>'
            . '<label class="fl" style="margin:0">Value<input name="value" type="number" value="100"></label>'
            . '<label class="fl" style="margin:0">Max uses<input name="max_uses" type="number" value="100"></label>'
            . '<button class="btn pri">Create</button></form></div>';
        $h .= '<div class="tbl" style="border:0;margin-top:12px"><table><thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Uses</th><th>Status</th></tr></thead><tbody>';
        foreach (db_all('SELECT * FROM coupons ORDER BY id DESC LIMIT 30') as $c) {
            $h .= '<tr><td class="mono"><b>' . e($c['code']) . '</b></td><td>' . e($c['type']) . '</td><td class="mono">' . (int) $c['value'] . '</td>'
                . '<td class="mono dim">' . (int) $c['uses'] . '/' . (int) $c['max_uses'] . '</td><td><span class="chip">' . e($c['status']) . '</span></td></tr>';
        }
        $h .= '</tbody></table></div></div>';
        $h .= '<div class="card"><h3>Recent transactions</h3><div class="tbl" style="border:0"><table><thead><tr><th>Ref</th><th>User</th><th>Type</th><th>Amount</th><th>Method</th><th>Status</th><th>When</th></tr></thead><tbody>';
        foreach (db_all('SELECT * FROM transactions ORDER BY id DESC LIMIT 40') as $t) {
            $h .= '<tr><td class="mono dim" style="font-size:11px">' . e($t['ref']) . '</td><td class="mono dim">' . (int) $t['user_id'] . '</td>'
                . '<td>' . e($t['type']) . '</td><td class="mono">' . e(sf_money($t['amount'])) . '</td><td>' . e($t['method']) . '</td>'
                . '<td><span class="chip ' . (in_array($t['status'], array('verified', 'paid'), true) ? 'chip-ok' : ($t['status'] === 'simulated' ? 'chip-warn' : '')) . '">' . e($t['status']) . '</span></td>'
                . '<td class="dim" style="font-size:11.5px">' . e(ui_time_ago($t['created_at'])) . '</td></tr>';
        }
        $h .= '</tbody></table></div><div class="dim" style="font-size:11.5px;margin-top:9px">Status <b>simulated</b> means no payment gateway was configured — that money never moved.</div></div>';
        return $h;
    }
    public function api($action, $in) {
        if ($action === 'daily') {
            $uid = auth_id();
            $last = (int) setting('daily_bonus_' . $uid, 0);
            if ($last && $last > time() - 86400) return array('ok' => false, 'error' => 'Daily bonus already claimed');
            setting_set('daily_bonus_' . $uid, time());
            credits_grant($uid, 25, 'daily bonus', 'bonus');
            return array('ok' => true, 'message' => '+25 credits', 'reload' => true);
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
    public function menu() { return array(array('route' => 'billing-suite', 'label' => 'Billing', 'icon' => '💳', 'group' => 'admin', 'perm' => 'admin')); }
}

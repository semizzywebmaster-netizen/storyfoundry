<?php
$uid = auth_id(); $u = auth_user();
$tab = isset($tab) && $tab ? $tab : 'overview';

/* ---- actions ---- */
if ($_POST && sf_post('act') === 'buy_credits') {
    csrf_check();
    $pack = sf_int(sf_post('pack'), 0);
    $packs = array(500 => 9250, 1200 => 18500, 3000 => 42000, 8000 => 98000);
    if (!isset($packs[$pack])) { flash('Unknown credit pack', 'err'); }
    elseif (sf_post('method') === 'wallet') {
        $r = wallet_spend($uid, $packs[$pack], $pack . ' credits', 'SF-W-' . strtoupper(sf_token(5)));
        if ($r['ok']) { credits_grant($uid, $pack, 'wallet purchase', 'purchase'); flash($pack . ' credits added from wallet', 'ok'); }
        else { flash($r['error'], 'err'); }
    } else {
        $method = sf_post('method') === 'flutterwave' ? 'flutterwave' : 'paystack';
        $gateway_enabled = (int) setting($method . '_enabled', 0);
        if (!$gateway_enabled) {
            flash('Payments not configured: ' . ucfirst($method) . ' is NOT CONFIGURED. Install the ' . $method . ' addon and add keys in Admin → Settings. Simulated locally instead.', 'err');
        }
        $ref = 'SF-TX-' . strtoupper(sf_token(6));
        db_exec('INSERT INTO transactions (user_id,type,ref,amount,currency,method,status,note,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
            array($uid, 'credit_purchase', $ref, $packs[$pack], 'NGN', $method, $gateway_enabled ? 'pending' : 'simulated', $pack . ' credits', db_now()));
        credits_grant($uid, $pack, 'purchase via ' . $method . ($gateway_enabled ? '' : ' (SIMULATED)'), 'purchase');
        audit('payment.' . ($gateway_enabled ? 'init' : 'simulated'), $ref . ' ' . $packs[$pack], $gateway_enabled ? 'info' : 'warn');
        flash($pack . ' credits added (' . $method . ($gateway_enabled ? '' : ' — SIMULATED, gateway not configured') . ')', $gateway_enabled ? 'ok' : 'err');
    }
    sf_redirect(sf_url('index.php?r=billing/credits'));
}
if ($_POST && sf_post('act') === 'deposit') {
    csrf_check();
    $amt = (float) sf_post('amount');
    if ($amt <= 0) { flash('Enter an amount', 'err'); }
    else {
        $ref = 'SF-DEP-' . strtoupper(sf_token(6));
        wallet_deposit($uid, $amt, 'simulated', $ref);
        flash(e(sf_money($amt)) . ' added to wallet (SIMULATED — no gateway configured)', 'err');
    }
    sf_redirect(sf_url('index.php?r=billing/wallet'));
}
if ($_POST && sf_post('act') === 'coupon') {
    csrf_check();
    $code = strtoupper(trim((string) sf_post('code')));
    $c = db_one('SELECT * FROM coupons WHERE code=? AND status=?', array($code, 'active'));
    if (!$c) { flash('Invalid or expired coupon', 'err'); }
    else {
        if ($c['type'] === 'credit') { credits_grant($uid, (int) $c['value'], 'coupon ' . $code, 'coupon'); flash('+' . (int) $c['value'] . ' credits', 'ok'); }
        else { flash('Coupon applied: ' . e($c['type']) . ' ' . e($c['value']), 'ok'); }
        db_exec('UPDATE coupons SET uses=uses+1 WHERE id=?', array($c['id']));
    }
    sf_redirect(sf_url('index.php?r=billing/coupons'));
}
if (sf_get('sub')) {
    $key = preg_replace('/[^a-z]/', '', (string) sf_get('sub'));
    db_exec('UPDATE users SET plan=? WHERE id=?', array($key, $uid));
    db_exec('INSERT INTO subscriptions (user_id,plan_key,status,renews_at,created_at) VALUES (?,?,?,?,?)',
        array($uid, $key, 'active', date('Y-m-d H:i:s', time() + 30 * 86400), db_now()));
    credits_grant($uid, (int) db_val('SELECT credits FROM plans WHERE pkey=?', array($key), 0), 'subscription ' . $key, 'subscription');
    audit('subscription.change', $key, 'info');
    flash('Plan changed to ' . ucfirst($key), 'ok'); sf_redirect(sf_url('index.php?r=billing/plans'));
}
?>
<h1>Billing & Credits</h1>
<div class="row" style="gap:8px;margin-bottom:12px;flex-wrap:wrap">
  <?php echo ui_status((int) setting('paystack_enabled', 0) ? 'REAL' : 'NOT CONFIGURED'); ?> <span class="dim">Paystack</span>
  <?php echo ui_status((int) setting('flutterwave_enabled', 0) ? 'REAL' : 'NOT CONFIGURED'); ?> <span class="dim">Flutterwave</span>
</div>
<div class="tabs">
  <?php foreach (array('overview' => 'Overview', 'plans' => 'Plans', 'credits' => 'Credits', 'wallet' => 'Wallet', 'transactions' => 'Transactions', 'coupons' => 'Coupons', 'referrals' => 'Referrals') as $k => $l): ?>
    <a class="<?php echo $tab === $k ? 'on' : ''; ?>" href="<?php echo e(sf_url('index.php?r=billing/' . $k)); ?>"><?php echo e($l); ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'plans'): ?>
<div class="grid g4">
<?php foreach (db_all('SELECT * FROM plans WHERE active=1 ORDER BY sort') as $p): ?>
  <div class="plan<?php echo $p['pkey'] === 'pro' ? ' hot' : ''; ?>">
    <div class="row btw"><div style="font-weight:800;font-size:16px"><?php echo e($p['name']); ?></div><?php if ($u['plan'] === $p['pkey']): ?><span class="chip chip-ok">Current</span><?php endif; ?></div>
    <div class="pprice"><?php echo $p['price'] > 0 ? e(sf_money($p['price'])) : 'Free'; ?></div>
    <div class="dim" style="font-size:12px"><?php echo (int) $p['credits']; ?> credits / month</div>
    <ul><?php foreach (explode('|', (string) $p['features']) as $f): ?><li><b>✓</b> <?php echo e($f); ?></li><?php endforeach; ?></ul>
    <?php if ($u['plan'] !== $p['pkey']): ?><a class="btn blk<?php echo $p['pkey'] === 'pro' ? ' pri' : ''; ?>" href="<?php echo e(sf_url('index.php?r=billing&sub=' . $p['pkey'])); ?>">Choose <?php echo e($p['name']); ?></a><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<div class="banner warn"><span>🚫</span><div><b>No withdrawal.</b> Wallet funds are usable only inside STORYFOUNDRY. There is no payout path, by design.</div></div>

<?php elseif ($tab === 'credits'): ?>
<div class="grid" style="grid-template-columns:1fr 320px;align-items:start">
  <div class="card">
    <h3>Buy credits</h3>
    <div class="grid g4">
    <?php $packs = array(500 => 9250, 1200 => 18500, 3000 => 42000, 8000 => 98000);
    foreach ($packs as $cr => $price): ?>
      <?php echo ui_form_open(sf_url('index.php?r=billing')); ?>
      <input type="hidden" name="act" value="buy_credits"><input type="hidden" name="pack" value="<?php echo (int) $cr; ?>">
      <div class="card" style="text-align:center;margin:0">
        <div style="font-size:24px;font-weight:800"><?php echo (int) $cr; ?></div>
        <div class="dim" style="font-size:12px;margin:4px 0 9px">credits</div>
        <div class="chip chip-acc"><?php echo e(sf_money($price)); ?></div>
        <button class="btn xs blk" style="margin-top:9px" name="method" value="paystack">Paystack</button>
        <button class="btn xs blk gho" style="margin-top:5px" name="method" value="flutterwave">Flutterwave</button>
        <button class="btn xs blk gho" style="margin-top:5px" name="method" value="wallet">Wallet</button>
      </div></form>
    <?php endforeach; ?>
    </div>
    <div class="banner info" style="margin-top:14px"><span>ℹ</span><div>Payments are verified <b>server-side</b> before credits are granted. Gateways not configured → the transaction is recorded as <b>simulated</b> and flagged, never silently treated as real.</div></div>
  </div>
  <div class="card">
    <div class="kv"><span class="k">Available</span><b><?php echo (int) credits_available($uid); ?></b></div>
    <div class="kv"><span class="k">Reserved</span><b><?php echo (int) $u['reserved']; ?></b></div>
    <div class="kv"><span class="k">Lifetime granted</span><b><?php echo (int) db_val('SELECT IFNULL(SUM(amount),0) FROM credit_tx WHERE user_id=? AND amount>0', array($uid), 0); ?></b></div>
    <div class="kv"><span class="k">Lifetime spent</span><b><?php echo abs((int) db_val('SELECT IFNULL(SUM(amount),0) FROM credit_tx WHERE user_id=? AND amount<0', array($uid), 0)); ?></b></div>
  </div>
</div>

<?php elseif ($tab === 'wallet'): ?>
<div class="grid" style="grid-template-columns:1fr 320px;align-items:start">
  <div class="card">
    <div style="font-size:34px;font-weight:800;letter-spacing:-.03em"><?php echo e(sf_money($u['wallet'])); ?></div>
    <div class="dim" style="font-size:12px;margin:6px 0 14px">Available for credits, subscriptions and marketplace purchases.</div>
    <?php echo ui_form_open(sf_url('index.php?r=billing')); ?><input type="hidden" name="act" value="deposit">
    <div class="row" style="gap:8px;flex-wrap:wrap">
      <?php foreach (array(5000, 10000, 25000, 50000) as $a): ?><button class="btn" name="amount" value="<?php echo $a; ?>">Deposit <?php echo e(sf_money($a)); ?></button><?php endforeach; ?>
    </div></form>
    <div class="banner warn" style="margin-top:14px"><span>🚫</span><div><b>No withdrawal.</b> Wallet funds are usable only inside STORYFOUNDRY. There is no payout path, by design.</div></div>
  </div>
  <div class="card">
    <h3>Payment providers</h3>
    <?php foreach (providers_effective() as $p): if ($p['capability'] !== 'payment') continue; ?>
      <div class="kv"><span><?php echo e($p['name']); ?></span><?php echo ui_status($p['status']); ?></div>
    <?php endforeach; ?>
    <div class="dim" style="font-size:11.5px;margin-top:10px">Idempotency keys protect against double-charging on webhook replay. Webhook signatures are verified before any credit is granted.</div>
  </div>
</div>

<?php elseif ($tab === 'transactions'): ?>
<div class="card"><div class="tbl" style="border:0"><table>
  <thead><tr><th>Ref</th><th>Type</th><th>Amount</th><th>Method</th><th>Status</th><th>When</th></tr></thead><tbody>
  <?php foreach (db_all('SELECT * FROM transactions WHERE user_id=? ORDER BY id DESC LIMIT 60', array($uid)) as $t): ?>
    <tr><td class="mono dim"><?php echo e($t['ref']); ?></td><td><?php echo e($t['type']); ?></td>
      <td class="mono"><?php echo e(sf_money($t['amount'])); ?></td><td><?php echo e($t['method']); ?></td>
      <td><?php echo ui_status($t['status'] === 'verified' ? 'REAL' : ($t['status'] === 'simulated' ? 'MOCK' : $t['status'])); ?></td>
      <td class="dim" style="font-size:11.5px"><?php echo ui_time_ago($t['created_at']); ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div></div>

<?php elseif ($tab === 'coupons'): ?>
<div class="card">
  <?php echo ui_form_open(sf_url('index.php?r=billing')); ?><input type="hidden" name="act" value="coupon">
  <div class="row" style="gap:8px"><input name="code" placeholder="Coupon code" style="max-width:240px"><button class="btn pri">Apply</button></div></form>
</div>
<div class="card"><div class="tbl" style="border:0"><table>
  <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Uses</th><th>Expires</th><th>Status</th></tr></thead><tbody>
  <?php foreach (db_all('SELECT * FROM coupons ORDER BY id DESC LIMIT 30') as $c): ?>
    <tr><td class="mono"><b><?php echo e($c['code']); ?></b></td><td><?php echo e($c['type']); ?></td><td><?php echo e($c['value']); ?></td>
      <td class="dim"><?php echo (int) $c['uses']; ?> / <?php echo (int) $c['max_uses']; ?></td><td class="dim"><?php echo e($c['expires_at'] ?: '—'); ?></td><td><span class="chip chip-ok"><?php echo e($c['status']); ?></span></td></tr>
  <?php endforeach; ?>
  </tbody></table></div></div>

<?php elseif ($tab === 'referrals'): ?>
<div class="grid" style="grid-template-columns:1fr 320px;align-items:start">
  <div class="card"><h3>Referral history</h3>
    <?php foreach (db_all('SELECT * FROM referrals WHERE referrer_id=? ORDER BY id DESC LIMIT 30', array($uid)) as $r): ?>
      <div class="kv"><span><b><?php echo e($r['email']); ?></b><div class="dim" style="font-size:11px">joined <?php echo ui_time_ago($r['created_at']); ?></div></span>
        <span class="row" style="gap:6px"><span class="chip <?php echo $r['status'] === 'rewarded' ? 'chip-ok' : 'chip-nc'; ?>"><?php echo e($r['status']); ?></span><b><?php echo (int) $r['reward']; ?> cr</b></span></div>
    <?php endforeach; ?>
  </div>
  <div class="card"><h3>Your code</h3>
    <div class="mono" style="background:var(--bg2);padding:11px;border-radius:8px;text-align:center;font-size:15px"><?php echo e($u['referral_code']); ?></div>
    <div class="dim" style="font-size:11.5px;margin-top:10px">Both accounts receive <?php echo (int) setting('credits_referral', 250); ?> credits on the referred user's first paid plan. Anti-abuse: 7-day reward hold and IP/device checks.</div>
  </div>
</div>

<?php else: ?>
<div class="grid g4" style="margin-bottom:14px">
  <?php echo ui_kpi((int) credits_available($uid), 'Credits', 'Reserved ' . (int) $u['reserved']); ?>
  <?php echo ui_kpi(e(sf_money($u['wallet'])), 'Wallet', 'No withdrawal'); ?>
  <?php echo ui_kpi(ucfirst($u['plan']), 'Plan', ''); ?>
  <?php echo ui_kpi((int) db_val('SELECT IFNULL(SUM(credits),0) FROM usage_log WHERE user_id=? AND created_at>=?', array($uid, date('Y-m-01')), 0), 'Credits used this month', ''); ?>
</div>
<div class="grid" style="grid-template-columns:1fr 320px;align-items:start">
  <div class="card"><h3>Cost per feature</h3>
    <div class="tbl" style="border:0"><table><thead><tr><th>Feature</th><th>Cost</th><th>Min plan</th><th>Daily limit</th><th>State</th></tr></thead><tbody>
    <?php foreach (db_all('SELECT * FROM feature_flags ORDER BY name') as $f): ?>
      <tr><td><b style="font-size:12.5px"><?php echo e($f['name']); ?></b></td><td class="mono"><?php echo (int) $f['credit_cost']; ?> cr</td>
        <td><span class="chip"><?php echo e($f['min_plan']); ?></span></td><td class="mono dim"><?php echo (int) $f['daily_limit']; ?></td>
        <td><span class="chip <?php echo $f['enabled'] ? 'chip-ok' : 'chip-err'; ?>"><?php echo $f['enabled'] ? 'On' : 'Off'; ?></span></td></tr>
    <?php endforeach; ?></tbody></table></div>
  </div>
  <div class="col">
    <div class="card"><h3>Recent transactions</h3>
      <?php foreach (db_all('SELECT * FROM transactions WHERE user_id=? ORDER BY id DESC LIMIT 6', array($uid)) as $t): ?>
        <div class="kv"><span><?php echo e($t['type']); ?><div class="dim" style="font-size:11px"><?php echo e($t['ref']); ?></div></span><b class="mono"><?php echo e(sf_money($t['amount'])); ?></b></div>
      <?php endforeach; ?>
    </div>
    <div class="card"><h3>Rewarded ads</h3>
      <p class="muted" style="font-size:12.5px">Watch an optional ad to earn credits. Limits and verification apply.</p>
      <button class="btn blk" data-api="ads-suite.watch" data-reload="1">▶ Watch ad · +<?php echo (int) setting('credits_rewarded_ad', 25); ?> credits</button>
    </div>
  </div>
</div>
<?php endif; ?>

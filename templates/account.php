<?php
$uid = auth_id(); $u = auth_user();
$tab = isset($tab) && $tab ? $tab : 'profile';
if ($_POST && sf_post('act') === 'profile') {
    csrf_check();
    db_exec('UPDATE users SET name=?, username=?, bio=?, culture=?, lang=?, tz=?, region=? WHERE id=?', array(
        sf_post('name'), sf_post('username'), sf_post('bio'), sf_post('culture'), sf_post('lang'), sf_post('tz'), sf_post('region'), $uid));
    audit('account.update', 'profile', 'info');
    flash('Profile saved', 'ok'); sf_redirect(sf_url('index.php?r=account'));
}
if ($_POST && sf_post('act') === 'password') {
    csrf_check();
    $cur = (string) sf_post('current'); $new = (string) sf_post('new');
    if (!password_verify($cur, $u['pass_hash'])) { flash('Current password is incorrect', 'err'); }
    elseif (strlen($new) < 8) { flash('New password must be at least 8 characters', 'err'); }
    else { db_exec('UPDATE users SET pass_hash=? WHERE id=?', array(password_hash($new, PASSWORD_BCRYPT, array('cost' => 11)), $uid)); audit('auth.password_change', '', 'info'); flash('Password updated', 'ok'); }
    sf_redirect(sf_url('index.php?r=account/security'));
}
/* avatar upload handled by pwa-pack? keep simple here */
if ($_POST && sf_post('act') === 'export_data') {
    $data = array('user' => $u, 'projects' => db_all('SELECT * FROM projects WHERE user_id=?', array($uid)),
        'assets' => db_all('SELECT * FROM assets WHERE user_id=?', array($uid)),
        'credit_tx' => db_all('SELECT * FROM credit_tx WHERE user_id=? ORDER BY id DESC LIMIT 500', array($uid)));
    sf_response_json($data);
}
?>
<h1>Settings</h1>
<div class="tabs">
  <?php foreach (array('profile' => 'Profile', 'security' => 'Security', 'culture' => 'Culture & language', 'notifications' => 'Notifications', 'data' => 'Data') as $k => $l): ?>
    <a class="<?php echo $tab === $k ? 'on' : ''; ?>" href="<?php echo e(sf_url('index.php?r=account/' . $k)); ?>"><?php echo e($l); ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'profile'): ?>
<div class="grid" style="grid-template-columns:1fr 300px;align-items:start">
  <div class="card"><?php echo ui_form_open(sf_url('index.php?r=account')); ?><input type="hidden" name="act" value="profile">
    <div class="grid g2">
      <label class="fl">Display name<input name="name" value="<?php echo e($u['name']); ?>"></label>
      <label class="fl">Username<input name="username" value="<?php echo e($u['username']); ?>"></label>
    </div>
    <label class="fl" style="margin-top:10px">Bio<textarea name="bio"><?php echo e($u['bio']); ?></textarea></label>
    <div class="grid g2" style="margin-top:10px">
      <label class="fl">Timezone<input name="tz" value="<?php echo e($u['tz']); ?>"></label>
      <label class="fl">Region<input name="region" value="<?php echo e($u['region']); ?>"></label>
    </div>
    <button class="btn pri" style="margin-top:14px">Save profile</button></form></div>
  <div class="card">
    <div class="kv"><span class="k">Email</span><b style="font-size:12px"><?php echo e($u['email']); ?></b></div>
    <div class="kv"><span class="k">Role</span><span class="chip"><?php echo e(auth_role_label($u['role'])); ?></span></div>
    <div class="kv"><span class="k">Plan</span><b><?php echo e(ucfirst($u['plan'])); ?></b></div>
    <div class="kv"><span class="k">Member since</span><b style="font-size:12px"><?php echo e(date('j M Y', strtotime($u['created_at']))); ?></b></div>
    <div class="kv"><span class="k">Credits</span><b><?php echo (int) credits_available($uid); ?></b></div>
  </div>
</div>

<?php elseif ($tab === 'security'): ?>
<div class="grid" style="grid-template-columns:1fr 320px;align-items:start">
  <div class="card"><h3>Change password</h3>
    <?php echo ui_form_open(sf_url('index.php?r=account')); ?><input type="hidden" name="act" value="password">
    <label class="fl">Current password<input type="password" name="current" required></label>
    <label class="fl" style="margin-top:10px">New password<input type="password" name="new" required minlength="8"></label>
    <button class="btn pri" style="margin-top:14px">Update password</button></form></div>
  <div class="card"><h3>Platform security</h3>
    <?php foreach (array('Password hashing (bcrypt)', 'RBAC on every route', 'Rate limiting per IP', 'Prepared statements (SQLi)', 'Escaped output (XSS)', 'CSRF tokens on state changes', 'Validated uploads, non-exec folders', 'Signed storage URLs', 'Webhook signature verification', 'Audit logging') as $s): ?>
      <div class="kv"><span style="font-size:12px"><?php echo e($s); ?></span><span class="chip chip-ok">✓</span></div>
    <?php endforeach; ?>
  </div>
</div>

<?php elseif ($tab === 'culture'): ?>
<div class="grid" style="grid-template-columns:1fr 320px;align-items:start">
  <div class="card"><?php echo ui_form_open(sf_url('index.php?r=account')); ?><input type="hidden" name="act" value="profile">
    <?php $cs = array(); foreach (culture_keys() as $k) { $cs[$k] = $k; } ?>
    <label class="fl">Default cultural context<?php echo ui_select('culture', $cs, $u['culture']); ?></label>
    <label class="fl" style="margin-top:10px">Interface language<input name="lang" value="<?php echo e($u['lang']); ?>"></label>
    <button class="btn pri" style="margin-top:14px">Save</button></form>
    <hr class="sep">
    <h3>Supported contexts</h3>
    <div class="row wrap" style="gap:6px"><?php foreach (culture_keys() as $k): ?><span class="chip <?php echo $k === $u['culture'] ? 'chip-acc' : ''; ?>"><?php echo e($k); ?></span><?php endforeach; ?></div>
  </div>
  <div class="card"><h3>How culture is used</h3>
    <p class="muted" style="font-size:12.5px">Cultural context influences names, places, flora, food, textures, clothing, traditions, register and tone. It never assigns personality traits, morality, ability or behaviour to a group.</p>
    <?php $ctx = culture_context($u['culture']); ?>
    <div class="kv"><span class="k">Sample place</span><b style="font-size:12px"><?php echo e($ctx['place']); ?></b></div>
    <div class="kv"><span class="k">Texture</span><b style="font-size:12px"><?php echo e($ctx['texture']); ?></b></div>
    <div class="kv"><span class="k">Greeting</span><b style="font-size:12px"><?php echo e($ctx['greet']); ?></b></div>
  </div>
</div>

<?php elseif ($tab === 'notifications'): ?>
<div class="card"><h3>Notification channels</h3>
  <?php foreach (array('Generation completed', 'Generation failed', 'Payment confirmation', 'Credit updates', 'Subscription alerts', 'Project updates', 'Collaboration', 'Announcements') as $n): ?>
    <div class="kv"><span><?php echo e($n); ?></span><span class="row" style="gap:6px"><span class="chip">in-app</span><span class="chip">email</span></span></div>
  <?php endforeach; ?>
  <div class="dim" style="font-size:11.5px;margin-top:10px">WhatsApp and SMS channels are architected but not enabled — no provider configured.</div>
</div>

<?php else: ?>
<div class="grid" style="grid-template-columns:1fr 320px;align-items:start">
  <div class="card"><h3>Your data</h3>
    <div class="kv"><span class="k">Projects</span><b><?php echo (int) db_val('SELECT COUNT(*) FROM projects WHERE user_id=? AND deleted_at IS NULL', array($uid), 0); ?></b></div>
    <div class="kv"><span class="k">Assets</span><b><?php echo (int) db_val('SELECT COUNT(*) FROM assets WHERE user_id=?', array($uid), 0); ?></b></div>
    <div class="kv"><span class="k">Generations</span><b><?php echo (int) db_val('SELECT COUNT(*) FROM usage_log WHERE user_id=?', array($uid), 0); ?></b></div>
    <hr class="sep">
    <?php echo ui_form_open(sf_url('index.php?r=account')); ?><input type="hidden" name="act" value="export_data">
    <button class="btn">Export all data (JSON)</button></form>
  </div>
  <div class="card"><h3>Session</h3>
    <div class="kv"><span class="k">Signed in as</span><b style="font-size:12px"><?php echo e($u['email']); ?></b></div>
    <div class="kv"><span class="k">IP</span><b class="mono" style="font-size:12px"><?php echo e(sf_ip()); ?></b></div>
    <a class="btn blk gho" style="margin-top:10px" href="<?php echo e(sf_url('index.php?r=logout')); ?>">Sign out</a>
  </div>
</div>
<?php endif; ?>

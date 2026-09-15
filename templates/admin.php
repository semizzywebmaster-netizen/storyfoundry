<?php
$p = isset($p) && $p ? $p : 'overview';

require_once SF_ROOT . '/core/admin_tabs.php';

/* ---------- actions ---------- */
if (sf_get('toggle_feature')) {
    $k = preg_replace('/[^a-z_]/', '', (string) sf_get('toggle_feature'));
    $f = db_one('SELECT * FROM feature_flags WHERE fkey=?', array($k));
    db_exec('UPDATE feature_flags SET enabled=? WHERE fkey=?', array($f && $f['enabled'] ? 0 : 1, $k));
    audit('feature.toggle', $k, 'info'); flash('Feature updated', 'ok'); sf_redirect(sf_url('index.php?r=admin&p=features'));
}
if (sf_get('maint_feature')) {
    $k = preg_replace('/[^a-z_]/', '', (string) sf_get('maint_feature'));
    $f = db_one('SELECT * FROM feature_flags WHERE fkey=?', array($k));
    db_exec('UPDATE feature_flags SET maintenance=? WHERE fkey=?', array($f && $f['maintenance'] ? 0 : 1, $k));
    audit('feature.maintenance', $k, 'warn'); sf_redirect(sf_url('index.php?r=admin&p=features'));
}
if ($_POST && sf_post('act') === 'feature_save') {
    csrf_check();
    $costs = isset($_POST['cost']) && is_array($_POST['cost']) ? $_POST['cost'] : array();
    $plans = isset($_POST['plan']) && is_array($_POST['plan']) ? $_POST['plan'] : array();
    $lims  = isset($_POST['limit']) && is_array($_POST['limit']) ? $_POST['limit'] : array();
    foreach ($costs as $k => $v) {
        db_exec('UPDATE feature_flags SET credit_cost=?, min_plan=?, daily_limit=? WHERE fkey=?',
            array((int) $v, preg_replace('/[^a-z0-9_]/i', '', (string) (isset($plans[$k]) ? $plans[$k] : 'free')), (int) (isset($lims[$k]) ? $lims[$k] : 100), preg_replace('/[^a-z_]/', '', (string) $k)));
    }
    audit('feature.config', 'bulk', 'info'); flash('Feature configuration saved', 'ok'); sf_redirect(sf_url('index.php?r=admin&p=features'));
}
if ($_POST && sf_post('act') === 'provider_save') {
    csrf_check();
    $key = (string) sf_post('pkey');
    $cfg = array();
    foreach ($_POST as $k => $v) { if (strpos($k, 'cfg_') === 0) { $cfg[substr($k, 4)] = trim((string) $v); } }
    db_exec('UPDATE providers SET config=?, priority=?, enabled=? WHERE pkey=?',
        array(json_encode($cfg), (int) sf_post('priority'), sf_post('enabled') ? 1 : 0, $key));
    audit('provider.update', $key, 'info'); flash('Provider saved', 'ok'); sf_redirect(sf_url('index.php?r=admin&p=providers'));
}
if ($_POST && sf_post('act') === 'settings_save') {
    csrf_check();
    $sets = isset($_POST['set']) && is_array($_POST['set']) ? $_POST['set'] : array();
    foreach ($sets as $k => $v) {
        $k = preg_replace('/[^a-z0-9_.]/i', '', (string) $k);
        if ($k === '') continue;
        setting_set($k, is_numeric($v) ? (strpos((string) $v, '.') !== false ? (float) $v : (int) $v) : $v);
    }
    audit('settings.update', 'bulk', 'info'); flash('Settings saved', 'ok'); sf_redirect(sf_url('index.php?r=admin&p=settings'));
}
if (sf_get('plugin_install')) { $r = plugin_install(sf_get('plugin_install')); flash($r['ok'] ? 'Addon installed' : $r['error'], $r['ok'] ? 'ok' : 'err'); sf_redirect(sf_url('index.php?r=admin&p=addons')); }
if (sf_get('plugin_uninstall')) { plugin_uninstall(sf_get('plugin_uninstall')); flash('Addon uninstalled', 'ok'); sf_redirect(sf_url('index.php?r=admin&p=addons')); }
if (sf_get('plugin_toggle')) {
    $k = sf_get('plugin_toggle');
    $row = db_one('SELECT * FROM plugins WHERE pkey=?', array($k));
    plugin_set_enabled($k, $row && $row['enabled'] ? 0 : 1);
    sf_redirect(sf_url('index.php?r=admin&p=addons'));
}
if ($_POST && sf_post('act') === 'grant_credits') {
    csrf_check();
    $uid = sf_int(sf_post('user_id')); $amt = sf_int(sf_post('amount'));
    credits_grant($uid, $amt, 'admin grant', 'admin');
    notify($uid, 'info', 'Credits added', $amt . ' credits added by admin.');
    audit('credits.grant', 'user#' . $uid . ' +' . $amt, 'info');
    flash('Credits granted', 'ok'); sf_redirect(sf_url('index.php?r=admin&p=users'));
}
if ($_POST && sf_post('act') === 'user_role') {
    csrf_check();
    db_exec('UPDATE users SET role=?, plan=? WHERE id=?', array(sf_post('role'), sf_post('plan'), sf_int(sf_post('user_id'))));
    audit('user.update', 'user#' . sf_int(sf_post('user_id')), 'info');
    flash('User updated', 'ok'); sf_redirect(sf_url('index.php?r=admin&p=users'));
}
if ($_POST && sf_post('act') === 'announce') {
    csrf_check();
    db_exec('INSERT INTO announcements (type,title,body,target,created_at) VALUES (?,?,?,?,?)',
        array(sf_post('type'), sf_post('title'), sf_post('body'), sf_post('target') ?: 'all', db_now()));
    flash('Announcement published', 'ok'); sf_redirect(sf_url('index.php?r=admin&p=overview'));
}

/* ---------- shell ---------- */
$counts = array(
    'users' => (int) db_val('SELECT COUNT(*) FROM users', array(), 0),
    'projects' => (int) db_val('SELECT COUNT(*) FROM projects WHERE deleted_at IS NULL', array(), 0),
    'gens' => (int) db_val('SELECT COUNT(*) FROM usage_log', array(), 0),
    'rev' => (float) db_val("SELECT IFNULL(SUM(amount),0) FROM transactions WHERE status IN ('verified','paid')", array(), 0),
);
?>
<h1>Admin</h1>
<div class="tabs">
  <?php foreach (array(
    'overview' => 'Overview', 'users' => 'Users', 'addons' => 'Addons', 'providers' => 'AI Providers',
    'features' => 'Feature governance', 'wallet' => 'Wallet &amp; Payments', 'coupons' => 'Coupons', 'referrals' => 'Referrals',
    'announcements' => 'Announcements', 'marketplace' => 'Marketplace', 'agency' => 'Agencies', 'social' => 'Social',
    'storage' => 'Storage', 'pwa' => 'PWA', 'analytics' => 'Analytics', 'audit' => 'Audit log', 'logs' => 'Logs',
    'system' => 'System check', 'settings' => 'Settings') as $k => $l): ?>
    <a class="<?php echo $p === $k ? 'on' : ''; ?>" href="<?php echo e(sf_url('index.php?r=admin&p=' . $k)); ?>"><?php echo e($l); ?></a>
  <?php endforeach; ?>
  <?php foreach (plugins_menu('admin') as $it): ?>
    <a class="<?php echo $p === $it['route'] ? 'on' : ''; ?>" href="<?php echo e(sf_url('index.php?r=admin&p=' . $it['route'])); ?>"><?php echo e($it['label']); ?></a>
  <?php endforeach; ?>
</div>

<?php
/* Addon-owned admin pages */
$pluginPage = null;
foreach (plugins_menu('admin') as $it) { if ($it['route'] === $p) { $pluginPage = $it['plugin']; } }
if (!$pluginPage) { foreach (plugins_db_rows() as $k => $row) { if (!empty($row['enabled']) && $k === $p) { $pluginPage = $k; } } }
if ($pluginPage && in_array($p, array('overview', 'users', 'addons', 'providers', 'features', 'analytics', 'audit', 'logs', 'settings', 'wallet', 'marketplace', 'storage', 'pwa', 'social', 'agency', 'announcements', 'referrals', 'system'), true) === false) {
    $pl = plugin_instance($pluginPage);
    echo $pl ? $pl->admin($_POST) : ui_empty('⚠️', 'Addon admin page failed to load.');
    return;
}
?>

<?php if ($p === 'overview'): ?>
<div class="grid g4" style="margin-bottom:14px">
  <?php echo ui_kpi($counts['users'], 'Users'); ?><?php echo ui_kpi($counts['projects'], 'Projects'); ?>
  <?php echo ui_kpi($counts['gens'], 'Generations'); ?><?php echo ui_kpi(e(sf_money($counts['rev'])), 'Revenue (verified)'); ?>
</div>
<div class="grid" style="grid-template-columns:1fr 1fr;align-items:start">
  <div class="card"><h3>System health</h3>
    <?php $ms = media_status(); ?>
    <div class="kv"><span>Database</span><?php echo ui_status('REAL'); ?></div>
    <div class="kv"><span>FFmpeg (rendering)</span><?php echo ui_status($ms['ffmpeg']); ?></div>
    <div class="kv"><span>GD (thumbnails)</span><?php echo ui_status($ms['gd']); ?></div>
    <div class="kv"><span>cURL (AI APIs)</span><?php echo ui_status(function_exists('curl_init') ? 'REAL' : 'NOT CONFIGURED'); ?></div>
    <div class="kv"><span>Queue</span><?php echo ui_status('MOCK'); ?> <span class="dim" style="font-size:11.5px">MySQL + cron</span></div>
    <?php if (!$ms['ffmpeg_path']): ?><div class="banner warn" style="margin-top:10px"><span>⚠</span><div style="font-size:12px"><?php echo e($ms['note']); ?></div></div><?php endif; ?>
  </div>
  <div class="card"><h3>Publish an announcement</h3>
    <?php echo ui_form_open(sf_url('index.php?r=admin&p=overview')); ?><input type="hidden" name="act" value="announce">
    <div class="grid g2">
      <label class="fl">Type<?php echo ui_select('type', array('Feature' => 'Feature', 'Maintenance' => 'Maintenance', 'Promotion' => 'Promotion', 'Alert' => 'Alert')); ?></label>
      <label class="fl">Target<input name="target" placeholder="all"></label>
    </div>
    <label class="fl" style="margin-top:10px">Title<input name="title" required></label>
    <label class="fl" style="margin-top:10px">Body<textarea name="body"></textarea></label>
    <button class="btn pri" style="margin-top:12px">Publish</button></form>
  </div>
</div>

<?php elseif ($p === 'users'): ?>
<div class="card"><div class="tbl" style="border:0"><table>
  <thead><tr><th>User</th><th>Role</th><th>Plan</th><th>Credits</th><th>Wallet</th><th>Joined</th><th>Actions</th></tr></thead><tbody>
  <?php foreach (db_all('SELECT * FROM users ORDER BY id DESC LIMIT 200') as $usr): ?>
    <tr>
      <td><b style="font-size:12.5px"><?php echo e($usr['name']); ?></b><div class="dim" style="font-size:11px"><?php echo e($usr['email']); ?></div></td>
      <td><span class="chip"><?php echo e(auth_role_label($usr['role'])); ?></span></td>
      <td><?php echo e(ucfirst($usr['plan'])); ?></td>
      <td class="mono"><?php echo (int) $usr['credits']; ?> <span class="dim">(<?php echo (int) $usr['reserved']; ?> held)</span></td>
      <td class="mono"><?php echo e(sf_money($usr['wallet'])); ?></td>
      <td class="dim" style="font-size:11.5px"><?php echo ui_time_ago($usr['created_at']); ?></td>
      <td>
        <?php echo ui_form_open(sf_url('index.php?r=admin&p=users'), 'post', 'style="display:flex;gap:4px;align-items:center"'); ?>
        <input type="hidden" name="act" value="user_role"><input type="hidden" name="user_id" value="<?php echo (int) $usr['id']; ?>">
        <?php $roles = array(); foreach (auth_roles() as $rk => $rv) { $roles[$rk] = $rv['label']; } ?>
        <?php echo ui_select('role', $roles, $usr['role'], 'style="width:auto;padding:4px 6px;font-size:11px"'); ?>
        <?php echo ui_select('plan', array('free' => 'Free', 'pro' => 'Pro', 'studio' => 'Studio', 'agency' => 'Agency'), $usr['plan'], 'style="width:auto;padding:4px 6px;font-size:11px"'); ?>
        <button class="btn xs">Save</button></form>
        <?php echo ui_form_open(sf_url('index.php?r=admin&p=users'), 'post', 'style="display:flex;gap:4px;align-items:center;margin-top:4px"'); ?>
        <input type="hidden" name="act" value="grant_credits"><input type="hidden" name="user_id" value="<?php echo (int) $usr['id']; ?>">
        <input name="amount" type="number" value="100" style="width:70px;padding:4px 6px;font-size:11px"><button class="btn xs gho">+ credits</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>

<?php elseif ($p === 'addons'): ?>
<div class="banner info"><span>🧩</span><div>Every capability is an addon. Drop a folder into <code>plugins/&lt;key&gt;/</code> with <code>plugin.json</code> + <code>plugin.php</code>, then Install and Enable here. No core files are ever edited.</div></div>
<div class="grid g3">
<?php $rows = plugins_db_rows(); foreach (plugins_discover() as $key => $meta):
  $installed = isset($rows[$key]); $enabled = $installed && $rows[$key]['enabled']; ?>
  <div class="card" style="margin:0">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
      <div><b><?php echo e($meta['name']); ?></b>
        <div class="dim" style="font-size:11px">v<?php echo e($meta['version']); ?> · <?php echo e($key); ?></div></div>
      <?php echo $enabled ? '<span class="chip chip-ok">Enabled</span>' : ($installed ? '<span class="chip chip-nc">Disabled</span>' : '<span class="chip">Available</span>'); ?>
    </div>
    <p class="muted" style="font-size:12.5px;margin:9px 0"><?php echo e(isset($meta['description']) ? $meta['description'] : ''); ?></p>
    <?php if (!empty($meta['features'])): ?><div class="dim" style="font-size:11px;margin-bottom:9px">Spec features: #<?php echo e(implode(', #', $meta['features'])); ?></div><?php endif; ?>
    <?php if (!empty($meta['stage'])): ?><div class="dim" style="font-size:11px">Pipeline stage: <b><?php echo e($meta['stage']['label']); ?></b></div><?php endif; ?>
    <div class="row" style="gap:5px;margin-top:11px">
      <?php if (!$installed): ?><a class="btn xs pri" href="<?php echo e(sf_url('index.php?r=admin&p=addons&plugin_install=' . urlencode($key))); ?>">Install &amp; enable</a>
      <?php else: ?>
        <a class="btn xs" href="<?php echo e(sf_url('index.php?r=admin&p=addons&plugin_toggle=' . urlencode($key))); ?>"><?php echo $enabled ? 'Disable' : 'Enable'; ?></a>
        <a class="btn xs gho dgr" href="<?php echo e(sf_url('index.php?r=admin&p=addons&plugin_uninstall=' . urlencode($key))); ?>" onclick="return confirm('Uninstall this addon?')">Uninstall</a>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php elseif ($p === 'providers'): ?>
<div class="banner info"><span>ℹ</span><div>Status is authoritative: <b>REAL</b> = key present · <b>MOCK</b> = local substitute, badged everywhere · <b>NOT CONFIGURED</b> = adapter present, keys missing. Production never silently falls back to mocks.</div></div>
<?php $by = array(); foreach (providers_effective() as $pv) { $by[$pv['capability']][] = $pv; }
foreach ($by as $cap => $list): ?>
  <div class="card">
    <h3><?php echo e(ucfirst($cap)); ?></h3>
    <div class="tbl" style="border:0"><table>
      <thead><tr><th>Provider</th><th>Status</th><th>Models</th><th>Priority</th><th>Enabled</th><th>Calls</th><th>Errors</th><th>Configuration</th></tr></thead><tbody>
      <?php foreach ($list as $pv): ?>
        <tr>
          <td><b style="font-size:12.5px"><?php echo e($pv['name']); ?></b><?php if ($pv['docs']): ?><div class="dim" style="font-size:11px"><?php echo e($pv['docs']); ?></div><?php endif; ?></td>
          <td><?php echo ui_status($pv['status']); ?></td>
          <td class="dim mono" style="font-size:11px"><?php echo e(implode(', ', (array) $pv['models'])); ?></td>
          <td><?php echo ui_form_open(sf_url('index.php?r=admin&p=providers')); ?><input type="hidden" name="act" value="provider_save"><input type="hidden" name="pkey" value="<?php echo e($pv['pkey']); ?>">
            <input type="number" name="priority" value="<?php echo (int) $pv['priority']; ?>" style="width:62px;padding:4px 6px">
            </td>
          <td><input type="checkbox" name="enabled" value="1" <?php echo $pv['enabled'] ? 'checked' : ''; ?>></td>
          <td class="mono dim"><?php echo (int) $pv['calls']; ?></td><td class="mono" style="color:<?php echo $pv['errors'] ? 'var(--warn)' : 'var(--tx3)'; ?>"><?php echo (int) $pv['errors']; ?></td>
          <td>
            <?php if ($pv['config_fields']): foreach ($pv['config_fields'] as $f => $label): ?>
              <input name="cfg_<?php echo e($f); ?>" placeholder="<?php echo e($label); ?>" value="<?php echo e(isset($pv['config'][$f]) ? $pv['config'][$f] : ''); ?>" style="width:auto;min-width:180px;display:inline-block;padding:4px 6px;margin-right:4px" type="<?php echo stripos($f, 'key') !== false || stripos($f, 'secret') !== false ? 'password' : 'text'; ?>">
            <?php endforeach; endif; ?>
            <button class="btn xs">Save</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
  </div>
<?php endforeach; ?>

<?php elseif ($p === 'features'): ?>
<div class="card">
  <?php echo ui_form_open(sf_url('index.php?r=admin&p=features')); ?><input type="hidden" name="act" value="feature_save">
  <div class="tbl" style="border:0"><table>
    <thead><tr><th>Feature</th><th>State</th><th>Min plan</th><th>Credit cost</th><th>Daily limit</th><th>Maintenance</th></tr></thead><tbody>
    <?php foreach (db_all('SELECT * FROM feature_flags ORDER BY name') as $f): ?>
      <tr>
        <td><b style="font-size:12.5px"><?php echo e($f['name']); ?></b><div class="dim mono" style="font-size:10.5px"><?php echo e($f['fkey']); ?></div></td>
        <td><a class="switch <?php echo $f['enabled'] ? 'on' : ''; ?>" href="<?php echo e(sf_url('index.php?r=admin&p=features&toggle_feature=' . $f['fkey'])); ?>"></a></td>
        <td><?php echo ui_select('plan[' . $f['fkey'] . ']', array('free' => 'Free', 'pro' => 'Pro', 'studio' => 'Studio', 'agency' => 'Agency'), $f['min_plan'], 'style="width:auto;padding:4px 6px"'); ?></td>
        <td><input type="number" name="cost[<?php echo e($f['fkey']); ?>]" value="<?php echo (int) $f['credit_cost']; ?>" style="width:70px;padding:4px 6px"></td>
        <td><input type="number" name="limit[<?php echo e($f['fkey']); ?>]" value="<?php echo (int) $f['daily_limit']; ?>" style="width:80px;padding:4px 6px"></td>
        <td><a class="switch <?php echo $f['maintenance'] ? 'on' : ''; ?>" href="<?php echo e(sf_url('index.php?r=admin&p=features&maint_feature=' . $f['fkey'])); ?>"></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <button class="btn pri" style="margin-top:12px">Save configuration</button></form>
</div>

<?php elseif ($p === 'analytics'): ?>
<div class="grid g4" style="margin-bottom:14px">
  <?php echo ui_kpi($counts['users'], 'Users'); ?>
  <?php echo ui_kpi((int) db_val('SELECT COUNT(DISTINCT user_id) FROM usage_log WHERE created_at>=?', array(date('Y-m-d H:i:s', time() - 30 * 86400)), 0), 'Active (30d)'); ?>
  <?php echo ui_kpi($counts['gens'], 'Generations'); ?>
  <?php echo ui_kpi(e(sf_money($counts['rev'])), 'Verified revenue'); ?>
</div>
<div class="grid g2">
  <div class="card"><h3>Top features (30 days)</h3>
    <?php foreach (db_all('SELECT feature, COUNT(*) c FROM usage_log WHERE created_at>=? GROUP BY feature ORDER BY c DESC LIMIT 8', array(date('Y-m-d H:i:s', time() - 30 * 86400))) as $r): ?>
      <div style="margin-bottom:9px"><div class="row" style="justify-content:space-between"><b style="font-size:12px"><?php echo e($r['feature']); ?></b><span class="dim mono"><?php echo (int) $r['c']; ?></span></div>
        <div class="bar" style="margin-top:4px"><i style="width:<?php echo min(100, (int) $r['c'] * 4); ?>%"></i></div></div>
    <?php endforeach; ?>
  </div>
  <div class="card"><h3>Provider usage</h3>
    <?php foreach (db_all('SELECT provider, COUNT(*) c, AVG(ms) avgms FROM usage_log GROUP BY provider ORDER BY c DESC LIMIT 8') as $r): ?>
      <div class="kv"><span class="mono"><?php echo e($r['provider']); ?></span><span class="row" style="gap:9px"><b><?php echo (int) $r['c']; ?></b><span class="dim mono"><?php echo (int) $r['avgms']; ?>ms</span></span></div>
    <?php endforeach; ?>
    <div class="dim" style="font-size:11.5px;margin-top:10px">Figures are live counts from this installation — nothing is estimated.</div>
  </div>
</div>

<?php elseif ($p === 'audit'): ?>
<div class="card"><div class="tbl" style="border:0"><table>
  <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>IP</th><th>Severity</th></tr></thead><tbody>
  <?php foreach (db_all('SELECT * FROM audit ORDER BY id DESC LIMIT 200') as $a): ?>
    <tr><td class="dim" style="font-size:11.5px"><?php echo ui_time_ago($a['created_at']); ?></td>
      <td class="mono" style="font-size:11.5px"><?php echo e($a['actor']); ?></td>
      <td><b style="font-size:12px"><?php echo e($a['action']); ?></b></td>
      <td class="dim" style="font-size:11.5px"><?php echo e($a['target']); ?></td>
      <td class="mono dim" style="font-size:11px"><?php echo e($a['ip']); ?></td>
      <td><span class="chip <?php echo $a['sev'] === 'warn' ? 'chip-warn' : 'chip-info'; ?>"><?php echo e($a['sev']); ?></span></td></tr>
  <?php endforeach; ?>
  </tbody></table></div></div>

<?php elseif ($p === 'logs'): ?>
<div class="card"><div class="tbl" style="border:0"><table>
  <thead><tr><th>Time</th><th>Level</th><th>Source</th><th>Message</th></tr></thead><tbody>
  <?php foreach (db_all('SELECT * FROM logs ORDER BY id DESC LIMIT 200') as $l): ?>
    <tr><td class="mono dim" style="font-size:11px"><?php echo e($l['created_at']); ?></td>
      <td><span class="chip <?php echo $l['level'] === 'error' ? 'chip-err' : ($l['level'] === 'warn' ? 'chip-warn' : 'chip-info'); ?>"><?php echo e($l['level']); ?></span></td>
      <td class="mono dim" style="font-size:11px"><?php echo e($l['src']); ?></td>
      <td class="mono" style="font-size:11.5px"><?php echo e($l['message']); ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <div class="dim" style="font-size:11.5px;margin-top:10px">Also streaming to <code>storage/logs/app-YYYY-MM-DD.log</code>. Cron trims entries older than 30 days.</div>
</div>

<?php elseif ($p === 'wallet'): echo sf_admin_tab_wallet();
elseif ($p === 'marketplace'): echo sf_admin_tab_marketplace();
elseif ($p === 'storage'): echo sf_admin_tab_storage();
elseif ($p === 'pwa'): echo sf_admin_tab_pwa();
elseif ($p === 'social'): echo sf_admin_tab_social();
elseif ($p === 'agency'): echo sf_admin_tab_agency();
elseif ($p === 'announcements'): echo sf_admin_tab_announcements();
elseif ($p === 'referrals'): echo sf_admin_tab_referrals();
elseif ($p === 'system'): echo sf_admin_tab_system();
elseif ($p === 'coupons'):
    $pl = plugin_instance('billing-suite');
    echo $pl ? $pl->admin($_POST) : ui_empty('\xE2\x9A\xA0\xEF\xB8\x8F', 'Billing addon is disabled.'); ?>

<?php else: ?>
<div class="card">
  <?php echo ui_form_open(sf_url('index.php?r=admin&p=settings')); ?><input type="hidden" name="act" value="settings_save">
  <div class="grid g2">
    <?php $sets = array(
      'credits_new_user' => 'Free credits on signup', 'credits_referral' => 'Referral reward (credits)',
      'credits_rewarded_ad' => 'Rewarded ad credits', 'marketplace_commission' => 'Marketplace commission %',
      'signup_open' => 'Open registration (1/0)', 'maintenance_mode' => 'Maintenance mode (1/0)',
      'referral_enabled' => 'Referrals enabled (1/0)', 'coupons_enabled' => 'Coupons enabled (1/0)',
      'ads_enabled' => 'Advertising enabled (1/0)', 'rewarded_ads_enabled' => 'Rewarded ads enabled (1/0)',
      'default_culture' => 'Default culture', 'default_style' => 'Default visual style',
      'paystack_enabled' => 'Paystack enabled (1/0)', 'paystack_public' => 'Paystack public key', 'paystack_secret' => 'Paystack secret key',
      'flutterwave_enabled' => 'Flutterwave enabled (1/0)', 'flutterwave_public' => 'Flutterwave public key', 'flutterwave_secret' => 'Flutterwave secret key',
    );
    foreach ($sets as $k => $label): $v = setting($k, ''); ?>
      <label class="fl"><?php echo e($label); ?><input name="set[<?php echo e($k); ?>]" value="<?php echo e(is_bool($v) ? ($v ? 1 : 0) : $v); ?>"></label>
    <?php endforeach; ?>
  </div>
  <button class="btn pri" style="margin-top:14px">Save settings</button></form>
  <div class="banner warn" style="margin-top:14px"><span>🔐</span><div>Keys are stored in the database, never echoed to the client. In production, restrict DB access and enable HTTPS.</div></div>
</div>
<?php endif; ?>

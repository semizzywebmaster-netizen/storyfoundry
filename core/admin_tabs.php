<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
if (!function_exists('sf_features')) { require_once SF_ROOT . '/core/schema.php'; }
/**
 * ADMIN TABS — management surfaces for every part of the platform.
 *
 * Each function handles its own POST (CSRF-checked, audited, flashed) and then
 * renders its tab. They are reached from Admin → <tab> via templates/admin.php.
 *
 * Features covered here:
 *   38 cloud storage · 50 wallet · 51/52 payments · 54 coupons · 55 referrals
 *   57/58 advertising · 60 announcements · 66/67 agency + clients · 71/72 marketplace
 *   73/74 social connections + publishing · 75 PWA · plus a system self-check tab.
 */

function sf_admin_redirect($tab) { sf_redirect(sf_url('index.php?r=admin&p=' . $tab)); }
function sf_admin_act($name) { return $_POST && sf_post('act') === $name; }
function sf_admin_text($key, $max = 255) { return sf_sub(trim((string) sf_post($key)), 0, $max); }

/* ============================================================ 50/51/52 wallet */
function sf_admin_tab_wallet() {
    if (sf_admin_act('wallet_grant')) {
        csrf_check();
        $email = strtolower(sf_admin_text('email', 190));
        $amt = (int) sf_post('amount');
        $u = db_one('SELECT id FROM users WHERE email=?', array($email));
        if (!$u) { flash('No user with that email.', 'err'); }
        elseif ($amt === 0) { flash('Amount must not be zero.', 'err'); }
        else {
            credits_grant((int) $u['id'], $amt, 'admin grant: ' . sf_admin_text('note', 120), 'admin');
            db_exec('INSERT INTO transactions (user_id,type,ref,amount,currency,method,status,note,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
                array((int) $u['id'], 'admin_adjustment', 'ADM-' . strtoupper(sf_token(8)), abs($amt), setting('currency', 'NGN'),
                    'admin', 'verified', sf_admin_text('note', 200), db_now()));
            audit('wallet.grant', 'user#' . (int) $u['id'] . ' ' . $amt . ' credits', 'warn');
            flash(($amt > 0 ? 'Granted ' : 'Removed ') . abs($amt) . ' credits.', 'ok');
        }
        sf_admin_redirect('wallet');
    }

    $h = '<div class="banner info"><span>🔒</span><div>Wallet funds can only be spent inside STORYFOUNDRY.
        <b>There is no withdrawal path</b> — no payout function exists in this codebase.</div></div>';
    $h .= '<div class="grid g4">' .
        ui_kpi(number_format((float) db_val('SELECT IFNULL(SUM(wallet),0) FROM users', array(), 0)), 'Wallet balance (all users)') .
        ui_kpi(number_format((float) db_val('SELECT IFNULL(SUM(credits),0) FROM users', array(), 0)), 'Credits in circulation') .
        ui_kpi(number_format((float) db_val("SELECT IFNULL(SUM(amount),0) FROM transactions WHERE status IN ('verified','paid')", array(), 0)), 'Verified payments') .
        ui_kpi((string) (int) db_val("SELECT COUNT(*) FROM transactions WHERE status='pending'", array(), 0), 'Pending payments') .
        '</div>';

    $h .= '<div class="card"><h3>Adjust a user\'s credits</h3>' .
        ui_form_open(sf_url('index.php?r=admin&p=wallet')) .
        '<input type="hidden" name="act" value="wallet_grant">' .
        '<div class="row" style="gap:8px;flex-wrap:wrap;align-items:flex-end">' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">User email</label>' .
        '<input class="inp" name="email" placeholder="name@domain.com" required></div>' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Credits (negative to remove)</label>' .
        '<input class="inp" type="number" name="amount" value="100" required></div>' .
        '<div style="flex:1;min-width:180px"><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Note</label>' .
        '<input class="inp" name="note" style="width:100%" placeholder="Reason (audited)"></div>' .
        '<button class="btn pri">Apply</button></div></form></div>';

    $rows = db_all('SELECT t.*, u.email FROM transactions t LEFT JOIN users u ON u.id=t.user_id ORDER BY t.id DESC LIMIT 40');
    $h .= '<div class="card"><h3>Payment &amp; wallet ledger</h3><div class="tbl" style="border:0"><table>' .
        '<thead><tr><th>User</th><th>Type</th><th>Amount</th><th>Method</th><th>Status</th><th>Reference</th><th>When</th></tr></thead><tbody>';
    if (!$rows) { $h .= '<tr><td colspan="7" class="dim">No transactions yet.</td></tr>'; }
    foreach ($rows as $t) {
        $h .= '<tr><td class="dim" style="font-size:12px">' . e($t['email']) . '</td><td>' . e($t['type']) . '</td>' .
            '<td class="mono">' . e(number_format((float) $t['amount'], 2)) . ' ' . e($t['currency']) . '</td>' .
            '<td class="dim">' . e($t['method']) . '</td><td>' . ui_status(strtoupper((string) $t['status'])) . '</td>' .
            '<td class="mono dim" style="font-size:11px">' . e($t['ref']) . '</td>' .
            '<td class="dim" style="font-size:11.5px">' . e(time_ago($t['created_at'])) . '</td></tr>';
    }
    return $h . '</tbody></table></div></div>';
}

/* ============================================================ 71/72 marketplace */
function sf_admin_tab_marketplace() {
    if (sf_admin_act('mkt_commission')) {
        csrf_check();
        setting_set('marketplace_commission', max(0, min(60, (int) sf_post('commission'))));
        setting_set('marketplace_enabled', sf_post('enabled') ? 1 : 0);
        audit('marketplace.settings', 'commission + enabled updated', 'info');
        flash('Marketplace settings saved.', 'ok');
        sf_admin_redirect('marketplace');
    }
    if (sf_get('mkt_item') && sf_get('mkt_to')) {
        $to = in_array(sf_get('mkt_to'), array('active', 'hidden', 'removed'), true) ? sf_get('mkt_to') : 'active';
        db_exec('UPDATE marketplace_items SET status=? WHERE id=?', array($to, sf_int(sf_get('mkt_item'))));
        audit('marketplace.moderate', 'item#' . sf_int(sf_get('mkt_item')) . ' -> ' . $to, 'warn');
        flash('Listing set to ' . $to . '.', 'ok');
        sf_admin_redirect('marketplace');
    }

    $h = '<div class="grid g4">' .
        ui_kpi((string) (int) db_val('SELECT COUNT(*) FROM marketplace_items', array(), 0), 'Listings') .
        ui_kpi((string) (int) db_val('SELECT COUNT(*) FROM marketplace_orders', array(), 0), 'Orders') .
        ui_kpi(number_format((float) db_val('SELECT IFNULL(SUM(commission),0) FROM marketplace_orders', array(), 0)), 'Commission earned') .
        ui_kpi(number_format((float) db_val('SELECT IFNULL(SUM(amount),0) FROM marketplace_orders', array(), 0)), 'Gross sales') .
        '</div>';

    $h .= '<div class="card"><h3>Marketplace configuration</h3>' . ui_form_open(sf_url('index.php?r=admin&p=marketplace')) .
        '<input type="hidden" name="act" value="mkt_commission">' .
        '<div class="row" style="gap:10px;flex-wrap:wrap;align-items:flex-end">' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Commission %</label>' .
        '<input class="inp" type="number" name="commission" min="0" max="60" value="' . (int) setting('marketplace_commission', 15) . '"></div>' .
        '<label class="row" style="gap:6px;font-size:12.5px;align-items:center"><input type="checkbox" name="enabled" value="1"' .
        (setting('marketplace_enabled', 1) ? ' checked' : '') . '> Marketplace open</label>' .
        '<button class="btn pri">Save</button></div></form></div>';

    $items = db_all('SELECT i.*, u.email AS seller FROM marketplace_items i LEFT JOIN users u ON u.id=i.seller_id ORDER BY i.id DESC LIMIT 60');
    $h .= '<div class="card"><h3>Listings (moderation)</h3><div class="tbl" style="border:0"><table>' .
        '<thead><tr><th>Title</th><th>Seller</th><th>Category</th><th>Price</th><th>Sales</th><th>Status</th><th>Action</th></tr></thead><tbody>';
    if (!$items) { $h .= '<tr><td colspan="7" class="dim">No listings yet.</td></tr>'; }
    foreach ($items as $it) {
        $h .= '<tr><td><b style="font-size:12.5px">' . e($it['title']) . '</b></td>' .
            '<td class="dim" style="font-size:11.5px">' . e($it['seller']) . '</td><td>' . e($it['cat']) . '</td>' .
            '<td class="mono">' . e(number_format((float) $it['price'], 2)) . '</td><td class="mono">' . (int) $it['sales'] . '</td>' .
            '<td>' . ui_status(strtoupper((string) $it['status'])) . '</td>' .
            '<td class="row" style="gap:4px"><a class="btn xs" href="' . e(sf_url('index.php?r=admin&p=marketplace&mkt_item=' . (int) $it['id'] . '&mkt_to=active')) . '">Approve</a>' .
            '<a class="btn xs" href="' . e(sf_url('index.php?r=admin&p=marketplace&mkt_item=' . (int) $it['id'] . '&mkt_to=hidden')) . '">Hide</a>' .
            '<a class="btn xs gho" href="' . e(sf_url('index.php?r=admin&p=marketplace&mkt_item=' . (int) $it['id'] . '&mkt_to=removed')) . '">Remove</a></td></tr>';
    }
    $h .= '</tbody></table></div></div>';

    $orders = db_all('SELECT o.*, i.title, u.email AS buyer FROM marketplace_orders o LEFT JOIN marketplace_items i ON i.id=o.item_id LEFT JOIN users u ON u.id=o.buyer_id ORDER BY o.id DESC LIMIT 30');
    $h .= '<div class="card"><h3>Recent orders</h3><div class="tbl" style="border:0"><table>' .
        '<thead><tr><th>Item</th><th>Buyer</th><th>Amount</th><th>Commission</th><th>Status</th><th>When</th></tr></thead><tbody>';
    if (!$orders) { $h .= '<tr><td colspan="6" class="dim">No orders yet.</td></tr>'; }
    foreach ($orders as $o) {
        $h .= '<tr><td>' . e($o['title']) . '</td><td class="dim" style="font-size:11.5px">' . e($o['buyer']) . '</td>' .
            '<td class="mono">' . e(number_format((float) $o['amount'], 2)) . '</td>' .
            '<td class="mono">' . e(number_format((float) $o['commission'], 2)) . '</td>' .
            '<td>' . ui_status(strtoupper((string) $o['status'])) . '</td>' .
            '<td class="dim" style="font-size:11.5px">' . e(time_ago($o['created_at'])) . '</td></tr>';
    }
    return $h . '</tbody></table></div></div>';
}

/* ============================================================ 38 cloud storage */
function sf_admin_tab_storage() {
    if (sf_admin_act('storage_save')) {
        csrf_check();
        setting_set('upload_max_mb', max(1, min(512, (int) sf_post('upload_max_mb'))));
        setting_set('storage_quota_mb', max(50, (int) sf_post('storage_quota_mb')));
        setting_set('signed_url_ttl', max(60, (int) sf_post('signed_url_ttl')));
        $r2 = array(
            'endpoint' => sf_admin_text('r2_endpoint', 190),
            'bucket' => sf_admin_text('r2_bucket', 120),
            'region' => sf_admin_text('r2_region', 60),
            'access_key' => sf_admin_text('r2_access_key', 190),
            'secret' => sf_admin_text('r2_secret', 190),
        );
        setting_set('storage_r2', $r2);
        audit('storage.settings', 'storage configuration updated', 'info');
        flash('Storage settings saved.', 'ok');
        sf_admin_redirect('storage');
    }
    if (sf_get('storage_prune')) {
        $n = 0;
        foreach (glob(SF_ROOT . '/storage/cache/c_*.php') as $f) { @unlink($f); $n++; }
        flash('Removed ' . $n . ' cached files.', 'ok');
        sf_admin_redirect('storage');
    }

    $assets = (int) db_val('SELECT COUNT(*) FROM assets', array(), 0);
    $bytes = 0;
    $dir = SF_ROOT . '/uploads';
    if (is_dir($dir)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile()) { $bytes += $f->getSize(); }
        }
    }
    $r2 = setting('storage_r2', array());
    $r2 = is_array($r2) ? $r2 : array();

    $h = '<div class="grid g4">' .
        ui_kpi((string) $assets, 'Assets stored') .
        ui_kpi(number_format($bytes / 1048576, 1) . ' MB', 'Uploads directory') .
        ui_kpi((string) (int) setting('upload_max_mb', 25), 'Max upload (MB)') .
        ui_kpi((function_exists('disk_free_space') ? number_format(@disk_free_space(SF_ROOT) / 1073741824, 1) . ' GB' : 'n/a'), 'Free space') .
        '</div>';

    $h .= '<div class="card"><h3>Storage configuration</h3>' . ui_form_open(sf_url('index.php?r=admin&p=storage')) .
        '<input type="hidden" name="act" value="storage_save">' .
        '<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Max upload size (MB)</label>' .
        '<input class="inp" type="number" name="upload_max_mb" value="' . (int) setting('upload_max_mb', 25) . '"></div>' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Per-user quota (MB)</label>' .
        '<input class="inp" type="number" name="storage_quota_mb" value="' . (int) setting('storage_quota_mb', 500) . '"></div>' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Signed URL lifetime (seconds)</label>' .
        '<input class="inp" type="number" name="signed_url_ttl" value="' . (int) setting('signed_url_ttl', 3600) . '"></div>' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">S3 / R2 endpoint</label>' .
        '<input class="inp" name="r2_endpoint" value="' . e(isset($r2['endpoint']) ? $r2['endpoint'] : '') . '" placeholder="https://<account>.r2.cloudflarestorage.com"></div>' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Bucket</label>' .
        '<input class="inp" name="r2_bucket" value="' . e(isset($r2['bucket']) ? $r2['bucket'] : '') . '"></div>' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Region</label>' .
        '<input class="inp" name="r2_region" value="' . e(isset($r2['region']) ? $r2['region'] : 'auto') . '"></div>' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Access key ID</label>' .
        '<input class="inp" name="r2_access_key" value="' . e(isset($r2['access_key']) ? $r2['access_key'] : '') . '" autocomplete="off"></div>' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Secret access key</label>' .
        '<input class="inp" type="password" name="r2_secret" value="" placeholder="' . (empty($r2['secret']) ? 'not set' : 'saved — leave blank to keep') . '" autocomplete="new-password"></div>' .
        '</div><button class="btn pri" style="margin-top:12px">Save storage settings</button></form>' .
        '<div class="dim" style="font-size:11.5px;margin-top:8px">Secrets are stored in the database and never rendered back into the page. ' .
        'Until an S3/R2 endpoint is configured, files are stored locally under <code>uploads/</code> and served through signed links (<code>?r=file</code>).</div></div>';

    $h .= '<div class="card"><h3>Maintenance</h3><a class="btn sm" href="' . e(sf_url('index.php?r=admin&p=storage&storage_prune=1')) . '">🧹 Clear file cache</a></div>';
    return $h;
}

/* ============================================================ 75 PWA */
function sf_admin_tab_pwa() {
    if (sf_admin_act('pwa_save')) {
        csrf_check();
        setting_set('pwa_name', sf_admin_text('pwa_name', 60) ?: 'STORYFOUNDRY');
        setting_set('pwa_short', sf_admin_text('pwa_short', 20) ?: 'SF');
        setting_set('pwa_theme', preg_replace('/[^#0-9a-fA-F]/', '', (string) sf_post('pwa_theme')) ?: '#0b0c0e');
        setting_set('pwa_background', preg_replace('/[^#0-9a-fA-F]/', '', (string) sf_post('pwa_background')) ?: '#0b0c0e');
        setting_set('pwa_offline', sf_post('pwa_offline') ? 1 : 0);
        setting_set('pwa_install_prompt', sf_post('pwa_install_prompt') ? 1 : 0);
        audit('pwa.settings', 'PWA configuration updated', 'info');
        flash('PWA settings saved.', 'ok');
        sf_admin_redirect('pwa');
    }
    $h = '<div class="card"><h3>Installable app (PWA)</h3>' . ui_form_open(sf_url('index.php?r=admin&p=pwa')) .
        '<input type="hidden" name="act" value="pwa_save">' .
        '<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">App name</label>' .
        '<input class="inp" name="pwa_name" value="' . e(setting('pwa_name', 'STORYFOUNDRY')) . '"></div>' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Short name</label>' .
        '<input class="inp" name="pwa_short" value="' . e(setting('pwa_short', 'SF')) . '"></div>' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Theme colour</label>' .
        '<input class="inp" type="color" name="pwa_theme" value="' . e(setting('pwa_theme', '#0b0c0e')) . '"></div>' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Background colour</label>' .
        '<input class="inp" type="color" name="pwa_background" value="' . e(setting('pwa_background', '#0b0c0e')) . '"></div>' .
        '</div>' .
        '<div class="row" style="gap:14px;margin-top:10px;flex-wrap:wrap">' .
        '<label class="row" style="gap:6px;font-size:12.5px;align-items:center"><input type="checkbox" name="pwa_offline" value="1"' .
        (setting('pwa_offline', 1) ? ' checked' : '') . '> Offline app shell</label>' .
        '<label class="row" style="gap:6px;font-size:12.5px;align-items:center"><input type="checkbox" name="pwa_install_prompt" value="1"' .
        (setting('pwa_install_prompt', 1) ? ' checked' : '') . '> Show install prompt</label></div>' .
        '<button class="btn pri" style="margin-top:12px">Save PWA settings</button></form></div>';
    $h .= '<div class="card"><h3>Endpoints</h3><div class="kv"><span>Web app manifest</span>' .
        '<a class="btn xs" href="' . e(sf_url('index.php?r=manifest.json')) . '">manifest.json</a></div>' .
        '<div class="kv"><span>Service worker</span><a class="btn xs" href="' . e(sf_url('index.php?r=sw.js')) . '">sw.js</a></div>' .
        '<div class="dim" style="font-size:11.5px;margin-top:8px">The manifest is generated by the pwa-pack addon from these settings. ' .
        'HTTPS is required for installation.</div></div>';
    return $h;
}

/* ============================================================ 73/74 social */
function sf_admin_tab_social() {
    if (sf_admin_act('social_save')) {
        csrf_check();
        $plats = array('youtube', 'facebook', 'instagram', 'tiktok', 'x', 'linkedin');
        $on = array();
        foreach ($plats as $pl) { $on[$pl] = isset($_POST['plat'][$pl]) ? 1 : 0; }
        setting_set('social_platforms', $on);
        setting_set('social_publish_enabled', isset($_POST['publish_enabled']) ? 1 : 0);
        audit('social.settings', 'platform availability updated', 'info');
        flash('Social platform settings saved.', 'ok');
        sf_admin_redirect('social');
    }
    $on = setting('social_platforms', array('youtube' => 1, 'facebook' => 1, 'instagram' => 1, 'tiktok' => 1, 'x' => 1, 'linkedin' => 1));
    $on = is_array($on) ? $on : array();
    $labels = array('youtube' => 'YouTube', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'x' => 'X', 'linkedin' => 'LinkedIn');

    $h = '<div class="card"><h3>Platforms</h3>' . ui_form_open(sf_url('index.php?r=admin&p=social')) .
        '<input type="hidden" name="act" value="social_save"><div class="row" style="gap:12px;flex-wrap:wrap">';
    foreach ($labels as $k => $l) {
        $h .= '<label class="row" style="gap:6px;font-size:12.5px;align-items:center"><input type="checkbox" name="plat[' . e($k) . ']" value="1"' .
            (!empty($on[$k]) ? ' checked' : '') . '> ' . e($l) . '</label>';
    }
    $h .= '</div><div class="row" style="gap:12px;margin-top:10px">' .
        '<label class="row" style="gap:6px;font-size:12.5px;align-items:center"><input type="checkbox" name="publish_enabled" value="1"' .
        (setting('social_publish_enabled', 1) ? ' checked' : '') . '> Publishing enabled</label></div>' .
        '<button class="btn pri" style="margin-top:12px">Save</button></form>' .
        '<div class="dim" style="font-size:11.5px;margin-top:8px">Direct publishing needs an OAuth app per platform and the platform\'s API credentials. ' .
        'Until those are configured the connection shows <b>NOT CONFIGURED</b> and creators export ready-to-post packages instead.</div></div>';

    $acc = db_all('SELECT a.*, u.email FROM social_accounts a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 50');
    $h .= '<div class="card"><h3>Connected accounts</h3><div class="tbl" style="border:0"><table>' .
        '<thead><tr><th>User</th><th>Platform</th><th>Handle</th><th>Status</th><th>Expires</th></tr></thead><tbody>';
    if (!$acc) { $h .= '<tr><td colspan="5" class="dim">No accounts connected yet.</td></tr>'; }
    foreach ($acc as $a) {
        $h .= '<tr><td class="dim" style="font-size:11.5px">' . e($a['email']) . '</td><td>' . e($a['platform']) . '</td>' .
            '<td>' . e($a['handle']) . '</td><td>' . ui_status($a['status']) . '</td>' .
            '<td class="dim" style="font-size:11.5px">' . e($a['expires_at'] ? $a['expires_at'] : '—') . '</td></tr>';
    }
    $h .= '</tbody></table></div></div>';

    $pub = db_all('SELECT p.*, u.email FROM publishing p LEFT JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 30');
    $h .= '<div class="card"><h3>Publishing queue</h3><div class="tbl" style="border:0"><table>' .
        '<thead><tr><th>User</th><th>Platform</th><th>Scheduled</th><th>Status</th><th>Caption</th></tr></thead><tbody>';
    if (!$pub) { $h .= '<tr><td colspan="5" class="dim">Nothing scheduled yet.</td></tr>'; }
    foreach ($pub as $p) {
        $h .= '<tr><td class="dim" style="font-size:11.5px">' . e($p['email']) . '</td><td>' . e($p['platform']) . '</td>' .
            '<td class="dim" style="font-size:11.5px">' . e($p['scheduled_at'] ? $p['scheduled_at'] : 'immediate') . '</td>' .
            '<td>' . ui_status(strtoupper((string) $p['status'])) . '</td>' .
            '<td class="dim" style="font-size:11.5px">' . e(sf_sub((string) $p['caption'], 0, 70)) . '</td></tr>';
    }
    return $h . '</tbody></table></div></div>';
}

/* ============================================================ 66/67 agency */
function sf_admin_tab_agency() {
    $h = '<div class="grid g4">' .
        ui_kpi((string) (int) db_val('SELECT COUNT(*) FROM agencies', array(), 0), 'Agencies') .
        ui_kpi((string) (int) db_val('SELECT COUNT(*) FROM agency_members', array(), 0), 'Seats used') .
        ui_kpi((string) (int) db_val('SELECT COUNT(*) FROM agency_clients', array(), 0), 'Clients') .
        ui_kpi((string) (int) db_val("SELECT COUNT(*) FROM projects WHERE agency_id IS NOT NULL", array(), 0), 'Agency projects') .
        '</div>';

    $ags = db_all('SELECT a.*, u.email AS owner FROM agencies a LEFT JOIN users u ON u.id=a.owner_id ORDER BY a.id DESC LIMIT 40');
    $h .= '<div class="card"><h3>Agencies</h3><div class="tbl" style="border:0"><table>' .
        '<thead><tr><th>Agency</th><th>Owner</th><th>Seats</th><th>Members</th><th>Clients</th><th>Created</th></tr></thead><tbody>';
    if (!$ags) { $h .= '<tr><td colspan="6" class="dim">No agencies yet.</td></tr>'; }
    foreach ($ags as $a) {
        $h .= '<tr><td><b style="font-size:12.5px">' . e($a['name']) . '</b></td>' .
            '<td class="dim" style="font-size:11.5px">' . e($a['owner']) . '</td><td class="mono">' . (int) $a['seats'] . '</td>' .
            '<td class="mono">' . (int) db_val('SELECT COUNT(*) FROM agency_members WHERE agency_id=?', array($a['id']), 0) . '</td>' .
            '<td class="mono">' . (int) db_val('SELECT COUNT(*) FROM agency_clients WHERE agency_id=?', array($a['id']), 0) . '</td>' .
            '<td class="dim" style="font-size:11.5px">' . e(time_ago($a['created_at'])) . '</td></tr>';
    }
    $h .= '</tbody></table></div></div>';

    $cl = db_all('SELECT c.*, a.name AS agency FROM agency_clients c LEFT JOIN agencies a ON a.id=c.agency_id ORDER BY c.id DESC LIMIT 40');
    $h .= '<div class="card"><h3>Clients</h3><div class="tbl" style="border:0"><table>' .
        '<thead><tr><th>Client</th><th>Agency</th><th>Contact</th><th>Status</th></tr></thead><tbody>';
    if (!$cl) { $h .= '<tr><td colspan="4" class="dim">No clients yet.</td></tr>'; }
    foreach ($cl as $c) {
        $h .= '<tr><td>' . e($c['name']) . '</td><td class="dim">' . e($c['agency']) . '</td>' .
            '<td class="dim" style="font-size:11.5px">' . e($c['contact']) . '</td>' .
            '<td>' . ui_status(strtoupper((string) $c['status'])) . '</td></tr>';
    }
    return $h . '</tbody></table></div></div>';
}

/* ============================================================ 60 announcements */
function sf_admin_tab_announcements() {
    if (sf_admin_act('announce_new')) {
        csrf_check();
        db_exec('INSERT INTO announcements (type,title,body,target,created_at) VALUES (?,?,?,?,?)',
            array(sf_admin_text('type', 30) ?: 'Feature', sf_admin_text('title', 190), sf_admin_text('body', 4000),
                sf_admin_text('target', 80) ?: 'all', db_now()));
        audit('announcement.create', sf_admin_text('title', 80), 'info');
        flash('Announcement published.', 'ok');
        sf_admin_redirect('announcements');
    }
    if (sf_get('del_ann')) {
        db_exec('DELETE FROM announcements WHERE id=?', array(sf_int(sf_get('del_ann'))));
        audit('announcement.delete', '#' . sf_int(sf_get('del_ann')), 'warn');
        flash('Announcement removed.', 'ok');
        sf_admin_redirect('announcements');
    }
    $h = '<div class="card"><h3>Publish an announcement</h3>' . ui_form_open(sf_url('index.php?r=admin&p=announcements')) .
        '<input type="hidden" name="act" value="announce_new">' .
        '<div class="grid" style="grid-template-columns:150px 150px 1fr;gap:10px">' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Type</label>' .
        ui_select('type', array('Feature' => 'Feature', 'Maintenance' => 'Maintenance', 'Promo' => 'Promotional'), 'Feature') . '</div>' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Audience</label>' .
        ui_select('target', array('all' => 'Everyone', 'free' => 'Free plan', 'pro' => 'Pro and above', 'agency' => 'Agency plan'), 'all') . '</div>' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Title</label>' .
        '<input class="inp" name="title" style="width:100%" required maxlength="190"></div></div>' .
        '<div style="margin-top:10px"><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Message</label>' .
        '<textarea class="inp" name="body" rows="3" style="width:100%" maxlength="4000"></textarea></div>' .
        '<button class="btn pri" style="margin-top:12px">Publish</button></form></div>';

    $rows = db_all('SELECT * FROM announcements ORDER BY id DESC LIMIT 40');
    $h .= '<div class="card"><h3>Announcements</h3><div class="tbl" style="border:0"><table>' .
        '<thead><tr><th>Type</th><th>Title</th><th>Audience</th><th>When</th><th></th></tr></thead><tbody>';
    if (!$rows) { $h .= '<tr><td colspan="5" class="dim">None yet.</td></tr>'; }
    foreach ($rows as $a) {
        $h .= '<tr><td>' . e($a['type']) . '</td><td><b style="font-size:12.5px">' . e($a['title']) . '</b>' .
            '<div class="dim" style="font-size:11.5px">' . e(sf_sub((string) $a['body'], 0, 90)) . '</div></td>' .
            '<td class="dim">' . e($a['target']) . '</td><td class="dim" style="font-size:11.5px">' . e(time_ago($a['created_at'])) . '</td>' .
            '<td><a class="btn xs gho" href="' . e(sf_url('index.php?r=admin&p=announcements&del_ann=' . (int) $a['id'])) . '">Delete</a></td></tr>';
    }
    return $h . '</tbody></table></div></div>';
}

/* ============================================================ 55 referrals */
function sf_admin_tab_referrals() {
    if (sf_admin_act('referral_save')) {
        csrf_check();
        setting_set('referral_enabled', sf_post('enabled') ? 1 : 0);
        setting_set('credits_referral', max(0, (int) sf_post('reward')));
        setting_set('referral_min_signup_days', max(0, (int) sf_post('min_days')));
        audit('referral.settings', 'referral configuration updated', 'info');
        flash('Referral settings saved.', 'ok');
        sf_admin_redirect('referrals');
    }
    $h = '<div class="grid g4">' .
        ui_kpi((string) (int) db_val('SELECT COUNT(*) FROM referrals', array(), 0), 'Referrals') .
        ui_kpi((string) (int) db_val("SELECT COUNT(*) FROM referrals WHERE status='rewarded'", array(), 0), 'Rewarded') .
        ui_kpi((string) (int) db_val("SELECT IFNULL(SUM(reward),0) FROM referrals", array(), 0), 'Credits awarded') .
        ui_kpi((string) (int) setting('credits_referral', 250), 'Reward per signup') .
        '</div>';
    $h .= '<div class="card"><h3>Referral configuration</h3>' . ui_form_open(sf_url('index.php?r=admin&p=referrals')) .
        '<input type="hidden" name="act" value="referral_save">' .
        '<div class="row" style="gap:12px;flex-wrap:wrap;align-items:flex-end">' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Credits per signup</label>' .
        '<input class="inp" type="number" name="reward" value="' . (int) setting('credits_referral', 250) . '"></div>' .
        '<div><label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Anti-abuse: min days before reward</label>' .
        '<input class="inp" type="number" name="min_days" value="' . (int) setting('referral_min_signup_days', 1) . '"></div>' .
        '<label class="row" style="gap:6px;font-size:12.5px;align-items:center"><input type="checkbox" name="enabled" value="1"' .
        (setting('referral_enabled', 1) ? ' checked' : '') . '> Referrals enabled</label>' .
        '<button class="btn pri">Save</button></div></form></div>';

    $rows = db_all('SELECT r.*, u.email AS referrer FROM referrals r LEFT JOIN users u ON u.id=r.referrer_id ORDER BY r.id DESC LIMIT 40');
    $h .= '<div class="card"><h3>Referral log</h3><div class="tbl" style="border:0"><table>' .
        '<thead><tr><th>Referrer</th><th>Referred</th><th>Status</th><th>Reward</th><th>When</th></tr></thead><tbody>';
    if (!$rows) { $h .= '<tr><td colspan="5" class="dim">No referrals yet.</td></tr>'; }
    foreach ($rows as $r) {
        $h .= '<tr><td class="dim" style="font-size:11.5px">' . e($r['referrer']) . '</td>' .
            '<td>' . e($r['email']) . '</td><td>' . ui_status(strtoupper((string) $r['status'])) . '</td>' .
            '<td class="mono">' . (int) $r['reward'] . '</td><td class="dim" style="font-size:11.5px">' . e(time_ago($r['created_at'])) . '</td></tr>';
    }
    return $h . '</tbody></table></div></div>';
}

/* ============================================================ system self-check */
function sf_admin_tab_system() {
    if (sf_get('repair') === 'features') {
        $n = 0;
        foreach (sf_features() as $f) {
            db_exec('INSERT IGNORE INTO feature_flags (fkey,name,enabled,min_plan,credit_cost,daily_limit,maintenance) VALUES (?,?,?,?,?,?,?)',
                array($f[0], $f[1], 1, $f[3], $f[2], $f[4], 0));
            $n++;
        }
        audit('system.repair', 're-seeded ' . $n . ' feature flags', 'warn');
        flash('Feature flags re-seeded (' . $n . ').', 'ok');
        sf_admin_redirect('system');
    }
    if (sf_get('repair') === 'providers') {
        providers_sync_db();
        audit('system.repair', 'providers re-synced', 'info');
        flash('Providers re-synced.', 'ok');
        sf_admin_redirect('system');
    }
    if (sf_get('repair') === 'cache') {
        $n = 0;
        foreach (glob(SF_ROOT . '/storage/cache/c_*.php') as $f) { @unlink($f); $n++; }
        flash('Cache cleared (' . $n . ' files).', 'ok');
        sf_admin_redirect('system');
    }
    if (sf_get('repair') === 'drain') {
        $ran = jobs_run_due(20, 10);
        flash('Queue drained: ' . (int) $ran . ' job(s).', 'ok');
        sf_admin_redirect('system');
    }

    $flags = (int) db_val('SELECT COUNT(*) FROM feature_flags', array(), 0);
    $provs = db_all('SELECT pkey,status,enabled FROM providers ORDER BY pkey');
    $addonsOn = (int) db_val('SELECT COUNT(*) FROM plugins WHERE enabled=1', array(), 0);
    $addonsAll = (int) db_val('SELECT COUNT(*) FROM plugins', array(), 0);
    $tables = (int) db_val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()", array(), 0);
    $ff = media_ffmpeg();

    $h = '<div class="grid g4">' .
        ui_kpi((string) $flags, 'Feature flags') .
        ui_kpi((string) count($provs), 'Providers registered') .
        ui_kpi($addonsOn . '/' . $addonsAll, 'Addons enabled') .
        ui_kpi((string) $tables, 'Database tables') .
        '</div>';

    $write = array();
    foreach (array('uploads', 'storage', 'storage/cache', 'storage/logs') as $d) {
        $ok = is_dir(SF_ROOT . '/' . $d) && is_writable(SF_ROOT . '/' . $d);
        $write[] = '<span class="chip ' . ($ok ? 'chip-ok' : 'chip-err') . '">' . e($d) . '</span>';
    }

    $h .= '<div class="card"><h3>Environment</h3><div class="kv"><span>PHP</span><b>' . e(PHP_VERSION) . '</b></div>' .
        '<div class="kv"><span>Database</span><b>' . e((string) db_val('SELECT VERSION()', array(), 'unknown')) . '</b></div>' .
        '<div class="kv"><span>FFmpeg (real rendering)</span>' . ($ff ? ui_status('REAL') . ' <span class="dim mono" style="font-size:11px">' . e($ff) . '</span>' : ui_status('NOT CONFIGURED')) . '</div>' .
        '<div class="kv"><span>Upload limit (PHP)</span><b>' . e((string) ini_get('upload_max_filesize')) . ' / post ' . e((string) ini_get('post_max_size')) . '</b></div>' .
        '<div class="kv"><span>Queue worker (cron)</span><b>' . e((string) (setting('cron_last_run', 'never run'))) . '</b></div>' .
        '<div class="kv"><span>Writable directories</span><div class="row wrap" style="gap:4px">' . implode('', $write) . '</div></div></div>';

    $h .= '<div class="card"><h3>Registered providers</h3><div class="tbl" style="border:0"><table>' .
        '<thead><tr><th>Provider</th><th>Status</th><th>Enabled</th><th>Source</th></tr></thead><tbody>';
    if (!$provs) { $h .= '<tr><td colspan="4"><div class="banner warn"><span>⚠</span><div>No providers are registered. Enable the provider addons in <a href="' . e(sf_url('index.php?r=admin&p=addons')) . '">Admin → Addons</a>, then press <i>Re-sync providers</i> below.</div></div></td></tr>'; }
    foreach ($provs as $p) {
        $addonKey = strpos($p['pkey'], 'mock.') === 0 ? 'core (local)' : $p['pkey'];
        $h .= '<tr><td class="mono" style="font-size:12px">' . e($p['pkey']) . '</td><td>' . ui_status($p['status']) . '</td>' .
            '<td>' . ($p['enabled'] ? 'yes' : 'no') . '</td><td class="dim" style="font-size:11.5px">' . e($addonKey) . '</td></tr>';
    }
    $h .= '</tbody></table></div></div>';

    $h .= '<div class="card"><h3>Repair &amp; maintenance</h3><div class="row" style="gap:8px;flex-wrap:wrap">' .
        '<a class="btn sm" href="' . e(sf_url('index.php?r=admin&p=system&repair=features')) . '">Re-seed feature flags</a>' .
        '<a class="btn sm" href="' . e(sf_url('index.php?r=admin&p=system&repair=providers')) . '">Re-sync providers</a>' .
        '<a class="btn sm" href="' . e(sf_url('index.php?r=admin&p=system&repair=cache')) . '">Clear cache</a>' .
        '<a class="btn sm" href="' . e(sf_url('index.php?r=admin&p=system&repair=drain')) . '">Run queue now</a>' .
        '</div><div class="dim" style="font-size:11.5px;margin-top:9px">The install wizard seeds ' . count(sf_features()) .
        ' feature flags and syncs every provider adapter. If a host interrupts that step, these buttons restore it without touching your data.</div></div>';
    return $h;
}

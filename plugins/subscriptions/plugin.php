<?php
class SFPlugin_subscriptions extends PluginBase {
    public function boot() { add_action('cron.hourly', array($this, 'renewals')); }
    /** Called by cron.php: expire lapsed subscriptions and grant renewal credits. */
    public function renewals() {
        $rows = db_all("SELECT s.*, u.plan FROM subscriptions s JOIN users u ON u.id=s.user_id WHERE s.status='active' AND s.renews_at IS NOT NULL AND s.renews_at < ?", array(db_now()));
        foreach ($rows as $s) {
            $p = db_one('SELECT * FROM plans WHERE pkey=?', array($s['plan_key']));
            if (!$p) continue;
            db_exec('UPDATE subscriptions SET renews_at=? WHERE id=?', array(date('Y-m-d H:i:s', time() + 30 * 86400), (int) $s['id']));
            credits_grant((int) $s['user_id'], (int) $p['credits'], 'subscription renewal ' . $s['plan_key'], 'subscription');
            notify((int) $s['user_id'], 'ok', 'Subscription renewed', ucfirst($s['plan_key']) . ' plan renewed — ' . (int) $p['credits'] . ' credits added.');
            audit('subscription.renew', 'user#' . $s['user_id'] . ' ' . $s['plan_key'], 'info');
        }
    }
    public function admin($input) {
        if ($_POST && sf_post('act') === 'plan_save') {
            csrf_check();
            db_exec('UPDATE plans SET name=?, price=?, credits=?, features=? WHERE pkey=?',
                array(sf_post('name'), (float) sf_post('price'), (int) sf_post('credits'), sf_post('features'), sf_post('pkey')));
            flash('Plan saved', 'ok'); sf_redirect(sf_url('index.php?r=admin&p=subscriptions'));
        }
        $h = '<div class="card"><h3>Plans</h3><div class="grid g4">';
        foreach (db_all('SELECT * FROM plans ORDER BY price ASC') as $p) {
            $h .= '<div class="card" style="margin:0">'
                . ui_form_open(sf_url('index.php?r=admin&p=subscriptions')) . '<input type="hidden" name="act" value="plan_save">'
                . '<input type="hidden" name="pkey" value="' . e($p['pkey']) . '">'
                . '<label class="fl">Name<input name="name" value="' . e($p['name']) . '"></label>'
                . '<label class="fl" style="margin-top:8px">Price<input name="price" value="' . e($p['price']) . '"></label>'
                . '<label class="fl" style="margin-top:8px">Credits / month<input name="credits" value="' . e($p['credits']) . '"></label>'
                . '<label class="fl" style="margin-top:8px">Feature summary<textarea name="features">' . e($p['features']) . '</textarea></label>'
                . '<button class="btn xs" style="margin-top:9px">Save</button></form></div>';
        }
        $h .= '</div></div>';
        $subs = db_all("SELECT s.*, u.name, u.email FROM subscriptions s JOIN users u ON u.id=s.user_id ORDER BY s.id DESC LIMIT 100");
        $h .= '<div class="card"><h3>Active subscriptions</h3><div class="tbl" style="border:0"><table><thead><tr><th>User</th><th>Plan</th><th>Status</th><th>Renews</th></tr></thead><tbody>';
        foreach ($subs as $s) {
            $h .= '<tr><td><b style="font-size:12.5px">' . e($s['name']) . '</b><div class="dim" style="font-size:11px">' . e($s['email']) . '</div></td>'
                . '<td>' . e($s['plan_key']) . '</td><td><span class="chip ' . ($s['status'] === 'active' ? 'chip-ok' : '') . '">' . e($s['status']) . '</span></td>'
                . '<td class="dim" style="font-size:11.5px">' . e($s['renews_at'] ?: '—') . '</td></tr>';
        }
        $h .= '</tbody></table></div></div>';
        return $h;
    }
    public function menu() { return array(array('route' => 'subscriptions', 'label' => 'Plans', 'icon' => '💎', 'group' => 'admin', 'perm' => 'admin')); }
}

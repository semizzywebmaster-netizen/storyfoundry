<?php
class SFPlugin_ads_suite extends PluginBase {
    private function enabled() { return (int) setting('ads_enabled', 0) === 1; }
    public function dashboard() {
        if (!$this->enabled()) return array();
        $h = '<div class="card"><h3>Earn credits</h3>'
            . '<div class="muted" style="font-size:13px">Watch a short ad to top up your credits.</div>'
            . '<button class="btn pri" style="margin-top:10px" data-api="ads-suite.reward" data-reload="1">▶ Watch &amp; earn ' . (int) setting('credits_rewarded_ad', 25) . ' credits</button>'
            . '<div class="dim" style="font-size:11.5px;margin-top:8px">Ad delivery: ' . ui_status($this->enabled() ? 'REAL' : 'NOT CONFIGURED') . ' — no ad network is configured in this install, so nothing is served.</div></div>';
        return array($h);
    }
    public function admin($input) {
        $impr = (int) db_val('SELECT COUNT(*) FROM ad_events', array(), 0);
        $rewards = (int) db_val("SELECT IFNULL(SUM(reward),0) FROM ad_events WHERE event='reward'", array(), 0);
        $h = '<div class="grid g3" style="margin-bottom:14px">' . ui_kpi($impr, 'Ad events')
            . ui_kpi($rewards, 'Credits granted') . ui_kpi($this->enabled() ? 'On' : 'Off', 'Advertising') . '</div>';
        $h .= '<div class="card"><h3>Ad events</h3><div class="tbl" style="border:0"><table><thead><tr><th>User</th><th>Kind</th><th>Placement</th><th>Credits</th><th>When</th></tr></thead><tbody>';
        foreach (db_all('SELECT * FROM ad_events ORDER BY id DESC LIMIT 60') as $e) {
            $h .= '<tr><td class="mono dim">' . (int) $e['user_id'] . '</td><td><span class="chip">' . e($e['event']) . '</span></td>'
                . '<td class="dim">' . e($e['placement']) . '</td><td class="mono">' . (int) $e['reward'] . '</td>'
                . '<td class="dim" style="font-size:11.5px">' . e(ui_time_ago($e['created_at'])) . '</td></tr>';
        }
        $h .= '</tbody></table></div>'
            . '<div class="banner warn" style="margin-top:12px"><span>⚠</span><div style="font-size:12px">'
            . 'No ad SDK is bundled. Enable <code>ads_enabled</code> in Settings only after you add your network’s tag — '
            . 'STORYFOUNDRY will not pretend to serve impressions it did not load.</div></div></div>';
        return $h;
    }
    public function api($action, $in) {
        if ($action === 'reward') {
            if (!$this->enabled()) return array('ok' => false, 'error' => 'Advertising is NOT CONFIGURED on this installation');
            $amt = (int) setting('credits_rewarded_ad', 25);
            db_exec('INSERT INTO ad_events (user_id,event,placement,reward,created_at) VALUES (?,?,?,?,?)', array(auth_id(), 'reward', 'dashboard', $amt, db_now()));
            credits_grant(auth_id(), $amt, 'rewarded ad', 'ad');
            return array('ok' => true, 'message' => '+' . $amt . ' credits', 'reload' => true);
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
    public function menu() { return array(array('route' => 'ads-suite', 'label' => 'Advertising', 'icon' => '📢', 'group' => 'admin', 'perm' => 'admin')); }
}

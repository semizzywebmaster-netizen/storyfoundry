<?php
class SFPlugin_analytics_pro extends PluginBase {
    public function route($route, $get, $post) {
        require_login();
        $uid = auth_id();
        $since = date('Y-m-d H:i:s', time() - 30 * 86400);
        $stats = array(
            'projects' => (int) db_val('SELECT COUNT(*) FROM projects WHERE user_id=? AND deleted_at IS NULL', array($uid), 0),
            'runs' => (int) db_val('SELECT COUNT(*) FROM usage_log WHERE user_id=?', array($uid), 0),
            'credits' => (int) db_val('SELECT IFNULL(SUM(credits),0) FROM usage_log WHERE user_id=?', array($uid), 0),
            'failed' => (int) db_val("SELECT COUNT(*) FROM usage_log WHERE user_id=? AND status='error'", array($uid), 0),
        );
        $h = '<h1>Analytics</h1><div class="grid g4" style="margin-bottom:14px">'
            . ui_kpi($stats['projects'], 'Projects') . ui_kpi($stats['runs'], 'Generations')
            . ui_kpi($stats['credits'], 'Credits spent') . ui_kpi($stats['failed'], 'Failed runs') . '</div>';
        $h .= '<div class="grid g2">';
        $h .= '<div class="card"><h3>Usage by feature (30 days)</h3>';
        foreach (db_all('SELECT feature, COUNT(*) c, IFNULL(SUM(credits),0) cr FROM usage_log WHERE user_id=? AND created_at>=? GROUP BY feature ORDER BY c DESC LIMIT 10', array($uid, $since)) as $r) {
            $h .= '<div style="margin-bottom:9px"><div class="row" style="justify-content:space-between"><b style="font-size:12.5px">' . e($r['feature']) . '</b><span class="mono dim">' . (int) $r['c'] . ' · ' . (int) $r['cr'] . ' cr</span></div>'
                . '<div class="bar" style="margin-top:4px"><i style="width:' . min(100, (int) $r['c'] * 6) . '%"></i></div></div>';
        }
        $h .= '</div><div class="card"><h3>Provider mix</h3>';
        foreach (db_all('SELECT provider, COUNT(*) c, IFNULL(AVG(ms),0) ms FROM usage_log WHERE user_id=? GROUP BY provider ORDER BY c DESC LIMIT 10', array($uid)) as $r) {
            $h .= '<div class="kv"><span class="mono">' . e($r['provider']) . '</span><span class="row" style="gap:9px"><b>' . (int) $r['c'] . '</b><span class="dim mono">' . (int) $r['ms'] . 'ms</span></span></div>';
        }
        $h .= '<div class="dim" style="font-size:11.5px;margin-top:10px">All numbers are real counts from this installation.</div></div></div>';
        return sf_view('plugin', array('html' => $h), 'Analytics');
    }
    public function menu() { return array(array('route' => 'analytics', 'label' => 'Analytics', 'icon' => '📊', 'group' => 'main', 'perm' => 'user')); }
    public function dashboard() {
        $rows = db_all('SELECT feature, COUNT(*) c FROM usage_log WHERE user_id=? GROUP BY feature ORDER BY c DESC LIMIT 4', array(auth_id()));
        if (!$rows) return array();
        $h = '<div class="card"><h3>Your activity</h3>';
        foreach ($rows as $r) { $h .= '<div class="kv"><span>' . e($r['feature']) . '</span><b>' . (int) $r['c'] . '</b></div>'; }
        $h .= '</div>';
        return array($h);
    }
}

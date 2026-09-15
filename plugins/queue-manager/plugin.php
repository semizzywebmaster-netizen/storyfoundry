<?php
class SFPlugin_queue_manager extends PluginBase {
    public function admin($input) {
        $s = jobs_stats();
        $h = '<div class="grid g4" style="margin-bottom:14px">'
            . ui_kpi($s['queued'], 'Queued') . ui_kpi($s['processing'], 'Processing')
            . ui_kpi($s['completed'], 'Completed') . ui_kpi($s['failed'], 'Failed') . '</div>';
        $h .= '<div class="card"><h3>Worker</h3>'
            . '<div class="kv"><span>Driver</span>' . ui_status('REAL') . ' <span class="dim" style="font-size:11.5px">MySQL table <code>jobs</code></span></div>'
            . '<div class="kv"><span>cPanel cron</span><span class="mono dim" style="font-size:11.5px">*/5 * * * * /usr/local/bin/php -q ' . e(SF_ROOT) . '/cron.php token=' . e(sf_config('app.cron_token', 'SET-ME')) . '</span></div>'
            . '<div class="kv"><span>Opportunistic drain</span><span class="dim" style="font-size:11.5px">' . (int) sf_config('app.queue_inline_seconds', 20) . 's per web request</span></div>'
            . '<div class="kv"><span>Last heartbeat</span><span class="mono dim" style="font-size:11.5px">' . e(setting('cron_last_run', 'never')) . '</span></div>'
            . '<div class="row" style="gap:6px;margin-top:11px">'
            . '<button class="btn sm pri" data-api="queue-manager.drain" data-reload="1">Run worker now (10s)</button>'
            . '<button class="btn sm gho" data-api="queue-manager.retry_failed" data-reload="1">Retry failed</button>'
            . '<button class="btn sm gho dgr" data-api="queue-manager.prune" data-reload="1">Clear finished</button></div></div>';
        $h .= '<div class="card"><h3>Live queue</h3><div class="tbl" style="border:0"><table>'
            . '<thead><tr><th>ID</th><th>Job</th><th>Feature</th><th>Status</th><th>Progress</th><th>Cost</th><th>Attempts</th><th>Queued</th><th></th></tr></thead><tbody>';
        foreach (db_all('SELECT * FROM jobs ORDER BY id DESC LIMIT 60') as $j) {
            $st = $j['status'];
            $h .= '<tr><td class="mono dim">#' . (int) $j['id'] . '</td><td><b style="font-size:12.5px">' . e($j['label']) . '</b>'
                . ($j['error'] ? '<div class="dim" style="font-size:11px;color:var(--err)">' . e(substr($j['error'], 0, 90)) . '</div>' : '') . '</td>'
                . '<td class="dim">' . e($j['feature']) . '</td>'
                . '<td><span class="chip ' . ($st === 'completed' ? 'chip-ok' : ($st === 'failed' ? 'chip-err' : ($st === 'processing' ? 'chip-acc' : 'chip-warn'))) . '">' . e($st) . '</span></td>'
                . '<td><div class="bar" style="width:70px"><i style="width:' . (int) $j['progress'] . '%"></i></div></td>'
                . '<td class="mono">' . (int) $j['cost'] . '</td><td class="mono dim">' . (int) $j['attempts'] . '</td>'
                . '<td class="dim" style="font-size:11.5px">' . e(ui_time_ago($j['created_at'])) . '</td>'
                . '<td class="row" style="gap:4px">' . (in_array($st, array('failed', 'cancelled'), true) ? '<button class="btn xs" data-api="queue-manager.retry" data-payload=\'{"id":' . (int) $j['id'] . '}\' data-reload="1">Retry</button>' : '')
                . (in_array($st, array('queued', 'processing'), true) ? '<button class="btn xs gho" data-api="queue-manager.cancel" data-payload=\'{"id":' . (int) $j['id'] . '}\' data-reload="1">Cancel</button>' : '') . '</td></tr>';
        }
        $h .= '</tbody></table></div></div>';
        return $h;
    }
    public function api($action, $in) {
        $u = auth_user();
        if ($action === 'drain') {
            if (!auth_is_admin()) return array('ok' => false, 'error' => 'Admin only');
            $n = jobs_run_due(10, 20);
            return array('ok' => true, 'message' => $n . ' job(s) processed', 'reload' => true);
        }
        if ($action === 'retry_failed') {
            if (!auth_is_admin()) return array('ok' => false, 'error' => 'Admin only');
            db_exec("UPDATE jobs SET status='queued', progress=0, error=NULL, attempts=attempts+1 WHERE status='failed'", array());
            return array('ok' => true, 'message' => 'Failed jobs re-queued', 'reload' => true);
        }
        if ($action === 'prune') {
            if (!auth_is_admin()) return array('ok' => false, 'error' => 'Admin only');
            db_exec("DELETE FROM jobs WHERE status IN ('completed','cancelled') AND finished_at < ?", array(date('Y-m-d H:i:s', time() - 7 * 86400)));
            return array('ok' => true, 'message' => 'Finished jobs older than 7 days cleared', 'reload' => true);
        }
        if ($action === 'retry' || $action === 'cancel') {
            $j = db_one('SELECT * FROM jobs WHERE id=?', array((int) $in['id']));
            if (!$j) return array('ok' => false, 'error' => 'Job not found');
            if ($j['user_id'] != $u['id'] && !auth_is_admin()) return array('ok' => false, 'error' => 'Not your job');
            if ($action === 'retry') { jobs_retry((int) $j['id']); return array('ok' => true, 'message' => 'Job re-queued', 'reload' => true); }
            jobs_cancel((int) $j['id']);
            $res = db_one('SELECT * FROM reservations WHERE job_id=? AND status=?', array((int) $j['id'], 'held'));
            if ($res) { credits_release($res); }
            return array('ok' => true, 'message' => 'Job cancelled and credits released', 'reload' => true);
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
    public function menu() { return array(array('route' => 'queue-manager', 'label' => 'Queue', 'icon' => '⚡', 'group' => 'admin', 'perm' => 'admin')); }
}

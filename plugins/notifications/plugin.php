<?php
class SFPlugin_notifications extends PluginBase {
    public function boot() { add_action('job.completed', array($this, 'on_job')); }
    public function on_job($job, $res) { /* notify() already fires in the orchestrator; hook kept for addons to extend */ }
    public function dashboard() {
        $rows = db_all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 4', array(auth_id()));
        if (!$rows) return array();
        $h = '<div class="card"><h3>Notifications</h3>';
        foreach ($rows as $n) {
            $h .= '<div class="banner ' . ($n['type'] === 'err' ? 'err' : ($n['type'] === 'warn' ? 'warn' : 'info')) . '">'
                . '<span>' . ($n['type'] === 'err' ? '🔴' : ($n['type'] === 'warn' ? '⚠' : 'ℹ')) . '</span>'
                . '<div><b style="font-size:12.5px">' . e($n['title']) . '</b><div class="muted" style="font-size:12px">' . e($n['body']) . '</div></div></div>';
        }
        $h .= '<a class="btn blk gho" style="margin-top:9px" href="' . e(sf_url('index.php?r=notifications')) . '">All notifications</a></div>';
        return array($h);
    }
    public function admin($input) {
        if ($_POST && sf_post('act') === 'notify_user') {
            csrf_check();
            notify((int) sf_post('user_id'), sf_post('type'), sf_post('title'), sf_post('body'));
            flash('Notification sent', 'ok'); sf_redirect(sf_url('index.php?r=admin&p=notifications'));
        }
        $h = '<div class="card"><h3>Send a notification</h3>'
            . ui_form_open(sf_url('index.php?r=admin&p=notifications')) . '<input type="hidden" name="act" value="notify_user">'
            . '<div class="grid g2"><label class="fl">User id<input type="number" name="user_id" value="1"></label>'
            . '<label class="fl">Type' . ui_select('type', array('info' => 'Info', 'ok' => 'Success', 'warn' => 'Warning', 'err' => 'Error')) . '</label></div>'
            . '<label class="fl" style="margin-top:10px">Title<input name="title" required></label>'
            . '<label class="fl" style="margin-top:10px">Body<textarea name="body"></textarea></label>'
            . '<button class="btn pri" style="margin-top:12px">Send</button></form></div>';
        $h .= '<div class="card"><h3>Recent notifications</h3><div class="tbl" style="border:0"><table><thead><tr><th>User</th><th>Type</th><th>Title</th><th>Read</th><th>When</th></tr></thead><tbody>';
        foreach (db_all('SELECT * FROM notifications ORDER BY id DESC LIMIT 50') as $n) {
            $h .= '<tr><td class="mono dim">' . (int) $n['user_id'] . '</td><td><span class="chip">' . e($n['type']) . '</span></td>'
                . '<td><b style="font-size:12.5px">' . e($n['title']) . '</b><div class="dim" style="font-size:11.5px">' . e($n['body']) . '</div></td>'
                . '<td>' . (!empty($n['read_at']) ? '✓' : '—') . '</td><td class="dim" style="font-size:11.5px">' . e(ui_time_ago($n['created_at'])) . '</td></tr>';
        }
        $h .= '</tbody></table></div></div>';
        return $h;
    }
    public function menu() { return array(array('route' => 'notifications', 'label' => 'Notifications', 'icon' => '🔔', 'group' => 'admin', 'perm' => 'admin')); }
}

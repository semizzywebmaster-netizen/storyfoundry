<?php
class SFPlugin_social_publishing extends PluginBase {
    private function nets() { return array('YouTube', 'TikTok', 'Instagram', 'Facebook', 'X', 'LinkedIn'); }
    public function route($route, $get, $post) {
        require_login();
        $nets = $this->nets();
        if ($_POST && sf_post('act') === 'connect') {
            csrf_check();
            db_exec('INSERT INTO social_accounts (user_id,platform,handle,status,created_at) VALUES (?,?,?,?,?)',
                array(auth_id(), sf_post('platform'), sf_post('handle'), 'pending_oauth', db_now()));
            flash('Account added (awaiting OAuth authorisation)', 'ok');
            sf_redirect(sf_url('index.php?r=publishing'));
        }
        if ($_POST && sf_post('act') === 'schedule') {
            csrf_check();
            db_exec('INSERT INTO publishing (user_id,project_id,platform,caption,scheduled_at,status,created_at) VALUES (?,?,?,?,?,?,?)',
                array(auth_id(), (int) sf_post('project_id'), sf_post('platform'), sf_post('caption'), sf_post('scheduled_at'), 'scheduled', db_now()));
            flash('Post scheduled', 'ok'); sf_redirect(sf_url('index.php?r=publishing'));
        }
        $accts = db_all('SELECT * FROM social_accounts WHERE user_id=? ORDER BY id DESC', array(auth_id()));
        $queue = db_all('SELECT * FROM publishing WHERE user_id=? ORDER BY id DESC LIMIT 40', array(auth_id()));
        $projects = db_all('SELECT id,title FROM projects WHERE user_id=? AND deleted_at IS NULL ORDER BY id DESC LIMIT 50', array(auth_id()));
        $h = '<h1>Publishing</h1>';
        $h .= '<div class="banner info"><span>ℹ</span><div>STORYFOUNDRY prepares and schedules your content. Direct posting requires each network’s official API plus your OAuth consent — '
            . 'status: ' . ui_status('NOT CONFIGURED') . ' (no network OAuth app is bundled in this install). Scheduled items are exported as ready-to-upload files until one is connected.</div></div>';
        $h .= '<div class="grid" style="grid-template-columns:1fr 320px;align-items:start">';
        $h .= '<div class="card"><h3>Scheduled posts</h3>';
        if (!$queue) { $h .= ui_empty('📅', 'Nothing scheduled.'); }
        else {
            $h .= '<div class="tbl" style="border:0"><table><thead><tr><th>Platform</th><th>Caption</th><th>When</th><th>Status</th></tr></thead><tbody>';
            foreach ($queue as $q) {
                $h .= '<tr><td><span class="chip">' . e($q['platform']) . '</span></td>'
                    . '<td style="max-width:380px"><div style="font-size:12.5px">' . e(substr((string) $q['caption'], 0, 110)) . '</div></td>'
                    . '<td class="dim" style="font-size:11.5px">' . e($q['scheduled_at']) . '</td>'
                    . '<td><span class="chip ' . ($q['status'] === 'published' ? 'chip-ok' : 'chip-warn') . '">' . e($q['status']) . '</span></td></tr>';
            }
            $h .= '</tbody></table></div>';
        }
        $h .= '</div><div class="col">';
        $h .= '<div class="card"><h3>Schedule a post</h3>' . ui_form_open(sf_url('index.php?r=publishing')) . '<input type="hidden" name="act" value="schedule">'
            . '<label class="fl">Project<select name="project_id">';
        foreach ($projects as $p) { $h .= '<option value="' . (int) $p['id'] . '">' . e($p['title']) . '</option>'; }
        $h .= '</select></label>'
            . '<label class="fl" style="margin-top:9px">Platform' . ui_select('platform', array_combine($nets, $nets)) . '</label>'
            . '<label class="fl" style="margin-top:9px">When<input type="datetime-local" name="scheduled_at"></label>'
            . '<label class="fl" style="margin-top:9px">Caption<textarea name="caption"></textarea></label>'
            . '<button class="btn pri blk" style="margin-top:11px">Schedule</button></form></div>';
        $h .= '<div class="card"><h3>Connected accounts</h3>';
        foreach ($accts as $a) { $h .= '<div class="kv"><span>' . e($a['platform']) . ' · ' . e($a['handle']) . '</span>' . ui_status('NOT CONFIGURED') . '</div>'; }
        $h .= ui_form_open(sf_url('index.php?r=publishing')) . '<input type="hidden" name="act" value="connect">'
            . '<div class="row" style="gap:6px;margin-top:9px">' . ui_select('platform', array_combine($nets, $nets), null, 'style="flex:1"')
            . '<input name="handle" placeholder="@handle" style="flex:1"><button class="btn xs">Add</button></div></form></div>';
        $h .= '</div></div>';
        return sf_view('plugin', array('html' => $h), 'Publishing');
    }
    public function menu() { return array(array('route' => 'publishing', 'label' => 'Publishing', 'icon' => '📤', 'group' => 'main', 'perm' => 'user')); }
}

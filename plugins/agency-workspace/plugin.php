<?php
class SFPlugin_agency_workspace extends PluginBase {
    public function route($route, $get, $post) {
        require_login();
        $uid = auth_id();
        if ($_POST && sf_post('act') === 'new_client') {
            csrf_check();
            db_exec('INSERT INTO agency_clients (agency_id,name,contact,status,created_at) VALUES (?,?,?,?,?)',
                array($uid, sf_post('name'), sf_post('contact'), 'active', db_now()));
            flash('Client added', 'ok'); sf_redirect(sf_url('index.php?r=clients'));
        }
        if ($route === 'clients') {
            $rows = db_all('SELECT * FROM agency_clients WHERE agency_id=? ORDER BY id DESC LIMIT 100', array($uid));
            $h = '<h1>Clients</h1><div class="grid" style="grid-template-columns:1fr 300px;align-items:start">';
            $h .= '<div>' . ($rows ? '<div class="tbl"><table><thead><tr><th>Client</th><th>Contact</th><th>Status</th><th>Projects</th><th>Added</th></tr></thead><tbody>' : ui_empty('🏢', 'No clients yet.'));
            foreach ($rows as $c) {
                $n = (int) db_val('SELECT COUNT(*) FROM projects WHERE user_id=? AND client_id=?', array($uid, (int) $c['id']), 0);
                $h .= '<tr><td><b style="font-size:13px">' . e($c['name']) . '</b></td><td class="dim">' . e($c['contact']) . '</td>'
                    . '<td><span class="chip chip-ok">' . e($c['status']) . '</span></td><td class="mono">' . $n . '</td>'
                    . '<td class="dim" style="font-size:11.5px">' . e(ui_time_ago($c['created_at'])) . '</td></tr>';
            }
            $h .= ($rows ? '</tbody></table></div>' : '') . '</div>';
            $h .= '<div class="card"><h3>Add a client</h3>' . ui_form_open(sf_url('index.php?r=clients')) . '<input type="hidden" name="act" value="new_client">'
                . '<label class="fl">Client name<input name="name" required></label>'
                . '<label class="fl" style="margin-top:9px">Contact email<input name="contact"></label>'
                . '<button class="btn pri blk" style="margin-top:11px">Add client</button></form>'
                . '<div class="dim" style="font-size:11.5px;margin-top:10px">Share a project read-only link from the project page to collect approvals.</div></div></div>';
            return sf_view('plugin', array('html' => $h), 'Clients');
        }
        $members = db_all('SELECT * FROM agency_members WHERE agency_id=? ORDER BY id DESC LIMIT 50', array($uid));
        $clients = (int) db_val('SELECT COUNT(*) FROM agency_clients WHERE agency_id=?', array($uid), 0);
        $shared = (int) db_val('SELECT COUNT(*) FROM project_collaborators WHERE user_id=?', array($uid), 0);
        $h = '<h1>Agency workspace</h1><div class="grid g4" style="margin-bottom:14px">'
            . ui_kpi($clients, 'Clients') . ui_kpi(count($members), 'Team members')
            . ui_kpi($shared, 'Shared with me') . ui_kpi('Agency', 'Workspace type') . '</div>';
        $h .= '<div class="card"><h3>Team &amp; roles</h3><div class="tbl" style="border:0"><table><thead><tr><th>Member</th><th>Role</th><th>Status</th></tr></thead><tbody>';
        foreach ($members as $m) {
            $u = db_one('SELECT name,email FROM users WHERE id=?', array((int) $m['user_id']));
            $h .= '<tr><td><b style="font-size:12.5px">' . e($u ? $u['name'] : 'user#' . $m['user_id']) . '</b>'
                . '<div class="dim" style="font-size:11px">' . e($u ? $u['email'] : '') . '</div></td>'
                . '<td><span class="chip">' . e($m['role']) . '</span></td><td>' . e($m['status']) . '</td></tr>';
        }
        $h .= '</tbody></table></div><div class="dim" style="font-size:11.5px;margin-top:9px">Roles: owner · admin · member · client (read-only approval).</div></div>';
        return sf_view('plugin', array('html' => $h), 'Agency');
    }
    public function menu() {
        return array(
            array('route' => 'agency', 'label' => 'Agency', 'icon' => '🏢', 'group' => 'main', 'perm' => 'user'),
            array('route' => 'clients', 'label' => 'Clients', 'icon' => '🤝', 'group' => 'main', 'perm' => 'user'),
        );
    }
}

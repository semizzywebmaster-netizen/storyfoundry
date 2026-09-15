<?php
class SFPlugin_audit_security extends PluginBase {
    public function boot() {
        add_action('auth.login', array($this, 'on_login'));
    }
    public function on_login($user) {
        audit('auth.login', 'user#' . (int) $user['id'], 'info');
    }
    public function admin($input) {
        $sev = isset($_GET['sev']) ? $_GET['sev'] : '';
        $where = $sev ? 'WHERE sev=?' : '';
        $params = $sev ? array($sev) : array();
        $h = '<div class="tabs">';
        foreach (array('' => 'All', 'info' => 'Info', 'warn' => 'Warnings', 'err' => 'Errors') as $k => $l) {
            $h .= '<a class="' . ($sev === $k ? 'on' : '') . '" href="' . e(sf_url('index.php?r=admin&p=audit-security' . ($k ? '&sev=' . $k : ''))) . '">' . $l . '</a>';
        }
        $h .= '</div>';
        $h .= '<div class="card"><h3>Security & audit trail</h3><div class="tbl" style="border:0"><table>'
            . '<thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>IP</th><th>Severity</th></tr></thead><tbody>';
        foreach (db_all('SELECT * FROM audit ' . $where . ' ORDER BY id DESC LIMIT 200', $params) as $a) {
            $h .= '<tr><td class="dim" style="font-size:11.5px">' . e(ui_time_ago($a['created_at'])) . '</td>'
                . '<td class="mono" style="font-size:11.5px">' . e($a['actor']) . '</td>'
                . '<td><b style="font-size:12px">' . e($a['action']) . '</b></td>'
                . '<td class="dim" style="font-size:11.5px">' . e($a['target']) . '</td>'
                . '<td class="mono dim" style="font-size:11px">' . e($a['ip']) . '</td>'
                . '<td><span class="chip ' . ($a['sev'] === 'err' ? 'chip-err' : ($a['sev'] === 'warn' ? 'chip-warn' : 'chip-info')) . '">' . e($a['sev']) . '</span></td></tr>';
        }
        $h .= '</tbody></table></div>';
        $h .= '<div class="grid g3" style="margin-top:14px">'
            . '<div class="card" style="margin:0"><h3>Sessions</h3><div class="dim" style="font-size:12px">Sessions are stored server-side in <code>sessions</code>; revoking one ends it immediately.</div>'
            . '<button class="btn sm gho" style="margin-top:9px" data-api="audit-security.sessions">Revoke other sessions</button></div>'
            . '<div class="card" style="margin:0"><h3>Two-factor auth</h3>' . ui_status(setting('twofa_enabled', 0) ? 'REAL' : 'NOT CONFIGURED')
            . '<div class="dim" style="font-size:12px;margin-top:7px">Enable TOTP in <code>config.php</code> → <code>app.twofa_enabled</code>, then enforce per role.</div></div>'
            . '<div class="card" style="margin:0"><h3>Secrets</h3><div class="dim" style="font-size:12px">Provider keys live in the database, are never rendered in the client, and are masked as password fields in Admin → AI Providers.</div></div>'
            . '</div></div>';
        return $h;
    }
    public function api($action, $in) {
        if ($action === 'sessions') {
            /* revoke every session EXCEPT the one making this request */
            $keep = isset($_SESSION['sid']) ? (string) $_SESSION['sid'] : '';
            if ($keep !== '') {
                db_exec('DELETE FROM sessions WHERE user_id=? AND token<>?', array(auth_id(), $keep));
            } else {
                db_exec('DELETE FROM sessions WHERE user_id=?', array(auth_id()));
            }
            audit('security.sessions_revoked', 'user#' . auth_id() . ' (kept current)', 'warn');
            return array('ok' => true, 'message' => 'Other sessions revoked — this device stays signed in');
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
    public function menu() { return array(array('route' => 'audit-security', 'label' => 'Audit & security', 'icon' => '🛡️', 'group' => 'admin', 'perm' => 'admin')); }
}

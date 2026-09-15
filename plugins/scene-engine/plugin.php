<?php
class SFPlugin_scene_engine extends PluginBase {
    public function stage($project, $data) {
        $scenes = is_array($data) ? $data : array();
        if (!$scenes) {
            return ui_empty('🎬', 'No scenes yet.', ui_generate_btn('scene-engine', 'generate', 'Generate scenes', 'scenes', array('project_id' => (int) $project['id'])));
        }
        $h = '<div class="row" style="gap:8px;margin-bottom:12px">'
           . ui_generate_btn('scene-engine', 'generate', 'Regenerate scenes', 'scenes', array('project_id' => (int) $project['id']))
           . '<a class="btn" href="' . e(project_stage_url($project['id'], 'shots')) . '">Run AI Director →</a></div>';
        foreach ($scenes as $s) {
            $h .= '<div class="card"><div class="row" style="justify-content:space-between;flex-wrap:wrap">'
                . '<div class="row" style="gap:9px"><span class="chip chip-acc">Sc. ' . (int) $s['n'] . '</span><b style="font-size:15px">' . e($s['title']) . '</b></div>'
                . '<div class="row" style="gap:6px;flex-wrap:wrap"><span class="chip">' . e($s['time']) . '</span><span class="chip">' . e($s['weather']) . '</span>'
                . '<span class="chip chip-info">' . e($s['mood']) . '</span></div></div>'
                . '<div class="grid" style="grid-template-columns:220px 1fr;gap:14px;align-items:start;margin-top:11px">'
                . '<div class="frame"><img src="' . e(sf_url('index.php?r=frame&seed=' . urlencode('sc|' . $project['id'] . '|' . $s['n']) . '&style=' . urlencode($project['style']))) . '" alt="">'
                . '<div class="btm">' . e($s['location']) . '</div></div>'
                . '<div><div class="muted" style="font-size:13px;margin-bottom:9px">' . e($s['action']) . '</div>'
                . '<div class="grid g2" style="gap:8px;font-size:12.5px">'
                . '<div><span class="dim">Cast:</span> <b>' . e(implode(', ', (array) $s['characters'])) . '</b></div>'
                . '<div><span class="dim">Props:</span> <b>' . e($s['props']) . '</b></div>'
                . '<div><span class="dim">Direction:</span> <b>' . e($s['direction']) . '</b></div>'
                . '<div><span class="dim">Continuity:</span> <b>' . e($s['continuity']) . '</b></div></div>'
                . '<div class="row" style="gap:6px;margin-top:10px">'
                . '<button class="btn xs" data-api="scene-engine.shots" data-payload=\'{"project_id":' . (int) $project['id'] . ',"n":' . (int) $s['n'] . '}\'>Direct this scene →</button>'
                . '</div></div></div></div>';
        }
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        if ($action === 'generate') {
            $ctx = project_ctx($p);
            $chars = stage_data($pid, 'characters', array());
            $ctx['names'] = array(); foreach ($chars as $c) { $ctx['names'][] = $c['name']; }
            if (!$ctx['names']) { $ctx['names'] = array('Ada', 'Tunde'); }
            $r = orchestrator_run('scenes', array('kind' => 'scenes', 'context' => $ctx, 'n' => 6), array(
                'project_id' => $pid, 'label' => 'Scene breakdown', 'inline' => true));
            if (empty($r['ok'])) return array('ok' => false, 'error' => isset($r['error']) ? $r['error'] : 'Failed');
            stage_save($pid, 'scenes', $r['data']);
            project_log_activity($pid, 'generated scenes', '🎬');
            return array('ok' => true, 'message' => count($r['data']) . ' scenes created', 'reload' => true);
        }
        if ($action === 'shots') {
            return array('ok' => true, 'goto' => project_stage_url($pid, 'shots'));
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
}

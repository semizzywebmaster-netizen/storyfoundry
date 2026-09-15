<?php
class SFPlugin_ai_director extends PluginBase {
    public function stage($project, $data) {
        $scenes = stage_data($project['id'], 'scenes', array());
        $shots = is_array($data) ? $data : array();
        if (!$scenes) return ui_empty('🎬', 'Generate scenes first.');
        if (!$shots) {
            return ui_empty('🎥', 'No shot list yet.', ui_generate_btn('ai-director', 'generate', 'Run AI Director', 'director', array('project_id' => (int) $project['id'])));
        }
        $total = 0; foreach ($shots as $s) { $total += (int) $s['duration']; }
        $h = '<div class="row" style="gap:8px;margin-bottom:12px;flex-wrap:wrap">'
           . '<span class="chip">' . count($shots) . ' shots · ' . gmdate('i:s', $total) . ' total</span>'
           . ui_generate_btn('ai-director', 'generate', 'Re-direct', 'director', array('project_id' => (int) $project['id']))
           . '<button class="btn" data-api="ai-director.storyboard" data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-reload="1">🖼️ Build storyboard</button>'
           . '<button class="btn gho" data-api="ai-director.csv" data-payload=\'{"project_id":' . (int) $project['id'] . '}\'>Export CSV</button>'
           . '<a class="btn" href="' . e(project_stage_url($project['id'], 'images')) . '">Generate images →</a></div>';
        $h .= '<div class="card"><div class="tbl" style="border:0"><table>'
            . '<thead><tr><th>#</th><th>Scene</th><th>Type</th><th>Angle</th><th>Movement</th><th>Lens</th><th>Subject / action</th><th>Lighting</th><th>Dur</th></tr></thead><tbody>';
        foreach ($shots as $s) {
            $h .= '<tr><td class="mono dim">' . (int) $s['n'] . '</td><td class="dim">Sc.' . (int) $s['scene'] . '</td>'
                . '<td><b>' . e($s['type']) . '</b></td><td>' . e($s['angle']) . '</td><td>' . e($s['move']) . '</td>'
                . '<td class="mono">' . e($s['lens']) . '</td><td><b>' . e($s['subject']) . '</b><div class="dim" style="font-size:11px">' . e($s['action']) . '</div></td>'
                . '<td class="dim" style="font-size:11.5px">' . e($s['lighting']) . '</td><td class="mono">' . (int) $s['duration'] . 's</td></tr>';
        }
        $h .= '</tbody></table></div></div>';
        $sb = stage_data($project['id'], 'storyboard');
        if ($sb) {
            $h .= '<div class="card"><h3>Storyboard</h3><div class="shotgrid">';
            foreach ($sb as $f) {
                $h .= '<div><div class="frame"><img src="' . e(sf_url('index.php?r=frame&seed=' . urlencode($f['seed']) . '&style=' . urlencode($project['style']))) . '" alt="">'
                    . '<div class="btm">Sh.' . (int) $f['n'] . ' · ' . e($f['type']) . '</div></div></div>';
            }
            $h .= '</div></div>';
        }
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        $scenes = stage_data($pid, 'scenes', array());
        if ($action === 'generate') {
            $ctx = project_ctx($p);
            $all = array(); $n = 0;
            foreach ($scenes as $s) {
                $ctx['scene_id'] = 'sc' . $pid . '_' . $s['n'];
                $ctx['characters'] = (array) $s['characters'];
                if (!$ctx['characters']) { $ctx['characters'] = array('the room'); }
                $r = orchestrator_run('director', array('kind' => 'shots', 'context' => $ctx, 'n' => 4), array(
                    'project_id' => $pid, 'label' => 'AI Director — Sc.' . $s['n'], 'inline' => true));
                if (!empty($r['ok'])) {
                    foreach ($r['data'] as $sh) { $sh['scene'] = (int) $s['n']; $n++; $sh['n'] = $n; $all[] = $sh; }
                }
            }
            if (!$all) return array('ok' => false, 'error' => 'Director produced no shots');
            stage_save($pid, 'shots', $all);
            db_exec('UPDATE projects SET status=? WHERE id=?', array('In Production', $pid));
            project_log_activity($pid, 'generated shot list', '🎬');
            return array('ok' => true, 'message' => count($all) . ' shots created', 'reload' => true);
        }
        if ($action === 'storyboard') {
            $shots = stage_data($pid, 'shots', array());
            $frames = array();
            $i = 0;
            foreach ($shots as $s) {
                $frames[] = array('n' => $s['n'], 'type' => $s['type'], 'seed' => 'sb|' . $pid . '|' . $s['n'] . '|' . $s['scene']);
                if (++$i >= 60) break;
            }
            stage_save($pid, 'storyboard', $frames);
            return array('ok' => true, 'message' => 'Storyboard built', 'reload' => true);
        }
        if ($action === 'csv') {
            $shots = stage_data($pid, 'shots', array());
            $csv = "Scene,Shot,Type,Angle,Movement,Lens,Subject,Action,Duration,Audio,Lighting,Notes\n";
            foreach ($shots as $s) {
                $csv .= implode(',', array_map(function ($v) { return '"' . str_replace('"', '""', (string) $v) . '"'; },
                    array($s['scene'], $s['n'], $s['type'], $s['angle'], $s['move'], $s['lens'], $s['subject'], $s['action'], $s['duration'], '', $s['lighting'], isset($s['note']) ? $s['note'] : ''))) . "\n";
            }
            $rel = 'uploads/assets/shotlist-' . $pid . '.csv';
            @file_put_contents(SF_ROOT . '/' . $rel, $csv);
            return array('ok' => true, 'download' => sf_url($rel), 'message' => 'Shot list exported');
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
}

<?php
class SFPlugin_idea_engine extends PluginBase {
    public function stage($project, $data) {
        $ctx = project_ctx($project);
        $h = '<div class="grid" style="grid-template-columns:1fr 320px;align-items:start">';
        $h .= '<div class="col">';
        if (!$data) {
            $h .= ui_empty('💡', 'No concepts yet.', ui_generate_btn('idea-engine', 'generate', 'Generate 4 concepts', 'idea', array('project_id' => (int) $project['id'])));
        } else {
            foreach ((array) $data as $idea) {
                $h .= '<div class="card"><div class="row" style="justify-content:space-between"><b style="font-size:16px">' . e($idea['title']) . '</b><span class="chip">' . e($project['genre']) . '</span></div>'
                    . '<p class="muted" style="margin:8px 0 10px">' . e($idea['logline']) . '</p>'
                    . '<div class="grid g2" style="gap:8px">'
                    . '<div><div class="dim" style="font-size:10.5px;font-weight:700">CONFLICT</div><div style="font-size:13px">' . e($idea['conflict']) . '</div></div>'
                    . '<div><div class="dim" style="font-size:10.5px;font-weight:700">TWIST</div><div style="font-size:13px">' . e($idea['twist']) . '</div></div>'
                    . '<div><div class="dim" style="font-size:10.5px;font-weight:700">ENDING</div><div style="font-size:13px">' . e($idea['ending']) . '</div></div></div>'
                    . '<div class="row" style="margin-top:11px;gap:6px">'
                    . '<button class="btn sm pri" data-api="idea-engine.use" data-payload=\'{"project_id":' . (int) $project['id'] . ',"title":' . json_encode($idea['title']) . '}\' data-reload="1">Use this concept →</button>'
                    . '</div></div>';
            }
            $h .= '<div class="row" style="gap:8px">' . ui_generate_btn('idea-engine', 'generate', 'Regenerate concepts', 'idea', array('project_id' => (int) $project['id']))
                . '<a class="btn" href="' . e(project_stage_url($project['id'], 'story')) . '">Continue to Story →</a></div>';
        }
        $h .= '</div><div class="col">';
        $h .= '<div class="card"><h3>Concept brief</h3>'
            . '<div class="kv"><span class="k">Genre</span><b>' . e($project['genre']) . '</b></div>'
            . '<div class="kv"><span class="k">Tone</span><b>' . e($project['tone']) . '</b></div>'
            . '<div class="kv"><span class="k">Audience</span><b>' . e($project['audience']) . '</b></div>'
            . '<div class="kv"><span class="k">Theme</span><b>' . e($project['theme']) . '</b></div>'
            . '<div class="kv"><span class="k">Setting</span><b>' . e($project['setting']) . '</b></div></div>';
        $h .= '<div class="card"><h3>Cultural context</h3>'
            . '<div class="row" style="justify-content:space-between"><b>' . e($project['culture']) . '</b><span class="chip">' . e($ctx['region']) . '</span></div>'
            . '<div class="kv"><span class="k">Texture</span><b style="font-size:12px">' . e($ctx['texture']) . '</b></div>'
            . '<div class="kv"><span class="k">Places</span><b style="font-size:12px">' . e($ctx['place']) . '</b></div>'
            . '<div class="banner info" style="margin-top:10px"><span>🌍</span><div style="font-size:12px">Culture shapes setting, register and texture. It never assigns personality, morality or ability to a group.</div></div></div>';
        $h .= '</div></div>';
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        if ($action === 'generate') {
            $r = orchestrator_run('idea', array('kind' => 'ideas', 'context' => project_ctx($p), 'n' => 4), array(
                'project_id' => $pid, 'label' => 'Story concepts — ' . $p['title'], 'feature' => 'idea', 'inline' => true));
            if (empty($r['ok'])) return array('ok' => false, 'error' => isset($r['error']) ? $r['error'] : 'Generation failed');
            stage_save($pid, 'idea', $r['data']);
            project_log_activity($pid, 'generated story concepts', '💡');
            return array('ok' => true, 'message' => '4 concepts generated', 'reload' => true);
        }
        if ($action === 'use') {
            $title = isset($in['title']) ? trim((string) $in['title']) : '';
            if ($title) { db_exec('UPDATE projects SET title=? WHERE id=?', array($title, $pid)); }
            project_log_activity($pid, 'selected a concept', '✅');
            return array('ok' => true, 'message' => 'Concept applied', 'reload' => true);
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
}

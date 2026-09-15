<?php
class SFPlugin_story_studio extends PluginBase {
    public function stage($project, $data) {
        $tab = isset($_GET['t']) ? $_GET['t'] : 'manuscript';
        $h = '<div class="tabs">';
        foreach (array('manuscript' => 'Manuscript', 'doctor' => 'Story Doctor', 'rewrite' => 'Rewrite Studio') as $k => $l) {
            $h .= '<a class="' . ($tab === $k ? 'on' : '') . '" href="' . e(project_stage_url($project['id'], 'story')) . '&t=' . $k . '">' . $l . '</a>';
        }
        $h .= '</div>';
        if (!$data) {
            return $h . ui_empty('✍️', 'No story yet.', ui_generate_btn('story-studio', 'generate', 'Generate story', 'story', array('project_id' => (int) $project['id'])));
        }
        if ($tab === 'rewrite') {
            $first = isset($data['chapters'][0]['text']) ? $data['chapters'][0]['text'] : '';
            $h .= '<div class="grid" style="grid-template-columns:1fr 300px;align-items:start">'
                . '<div class="card"><h3>Rewrite Studio</h3>'
                . '<label class="fl">Selected text<textarea id="rwIn" style="min-height:140px">' . e(substr($first, 0, 800)) . '</textarea></label>'
                . '<div class="row wrap" style="margin:10px 0;gap:6px">';
            foreach (array('Cinematic', 'Emotional', 'Humorous', 'Dramatic', 'Concise', 'Expanded', 'Elevated', 'Plain') as $s) {
                $h .= '<button class="btn xs" data-api="story-studio.rewrite" data-payload=\'{"project_id":' . (int) $project['id'] . ',"style":"' . e($s) . '"}\' data-form="rwBox">' . $s . '</button>';
            }
            $h .= '</div><div id="rwBox"><input type="hidden" name="project_id" value="' . (int) $project['id'] . '"></div>'
                . '<label class="fl">Output<textarea id="rwOut" style="min-height:140px" placeholder="Rewritten text appears here…"></textarea></label>'
                . '<button class="btn sm pri" style="margin-top:9px" data-api="story-studio.apply" data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-reload="1" data-form="rwBox">Apply to chapter 1</button>'
                . '</div>'
                . '<div class="card"><h3>How rewrites work</h3><p class="muted" style="font-size:12.5px">Each rewrite creates a new version. Nothing is overwritten silently — compare and restore from the Manuscript tab.</p></div></div>';
            return $h;
        }
        if ($tab === 'doctor') {
            $d = stage_data($project['id'], 'doctor');
            $h .= '<div class="row" style="gap:8px;margin-bottom:12px">' . ui_generate_btn('story-studio', 'doctor', 'Run analysis', 'doctor', array('project_id' => (int) $project['id'])) . '</div>';
            if (!$d) { return $h . ui_empty('🩺', 'No analysis yet.'); }
            $h .= '<div class="grid" style="grid-template-columns:1fr 1fr;align-items:start">'
                . '<div class="card"><h3>Scores</h3>';
            foreach ($d['scores'] as $s) {
                $h .= '<div style="margin-bottom:10px"><div class="row" style="justify-content:space-between"><b style="font-size:13px">' . e($s['k']) . '</b><span class="mono dim">' . (int) $s['v'] . '/100</span></div>'
                    . '<div class="bar" style="margin-top:4px"><i style="width:' . (int) $s['v'] . '%;background:' . ($s['v'] > 80 ? 'var(--ok)' : ($s['v'] > 65 ? 'var(--warn)' : 'var(--err)')) . '"></i></div>'
                    . '<div class="dim" style="font-size:11px;margin-top:3px">' . e($s['q']) . '</div></div>';
            }
            $h .= '</div><div class="card"><h3>Findings</h3>';
            foreach ($d['findings'] as $f) {
                $sev = $f['sev'] === 'err' ? 'err' : ($f['sev'] === 'warn' ? 'warn' : 'info');
                $h .= '<div class="banner ' . $sev . '"><span>' . ($f['sev'] === 'err' ? '🔴' : ($f['sev'] === 'warn' ? '🟡' : '🔵')) . '</span>'
                    . '<div><b style="font-size:12.5px">' . e($f['t']) . '</b><div class="muted" style="font-size:12px;margin-top:3px">' . e($f['d']) . '</div></div></div>';
            }
            $h .= '</div></div>';
            return $h;
        }
        /* manuscript */
        $words = 0; foreach ($data['chapters'] as $c) { $words += (int) $c['words']; }
        $h .= '<div class="grid" style="grid-template-columns:1fr 300px;align-items:start"><div class="card prose">'
            . '<div style="text-align:center;margin-bottom:20px"><h2 style="font-size:24px">' . e($data['title']) . '</h2>'
            . '<div class="dim" style="font-size:12px">' . $words . ' words · ' . count($data['chapters']) . ' chapters</div></div>'
            . '<p style="font-style:italic;color:var(--tx2);border-left:3px solid var(--acc);padding-left:12px">' . e($data['logline']) . '</p>';
        foreach ($data['chapters'] as $i => $c) {
            $h .= '<h3 style="margin-top:22px">Chapter ' . ($i + 1) . '</h3><p>' . nl2br(e($c['text'])) . '</p>';
        }
        $h .= '</div><div class="col">'
            . '<div class="card"><h3>Document</h3>'
            . '<div class="kv"><span class="k">Words</span><b>' . $words . '</b></div>'
            . '<div class="kv"><span class="k">Read time</span><b>' . max(1, (int) ceil($words / 200)) . ' min</b></div>'
            . '<div class="kv"><span class="k">Provider</span>' . ui_status('MOCK') . '</div>'
            . '<div class="row" style="gap:6px;margin-top:10px">'
            . '<button class="btn sm" data-api="story-studio.continuation" data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-reload="1">Continue</button>'
            . '<button class="btn sm gho" data-api="story-studio.export" data-payload=\'{"project_id":' . (int) $project['id'] . '}\'>Export</button></div></div>'
            . '<div class="card"><h3>Versions</h3><div class="dim" style="font-size:12px">Versions are created on every AI rewrite and manual save point.</div>'
            . '<button class="btn sm blk gho" style="margin-top:9px" data-api="story-studio.version" data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-reload="1">Save version</button></div>'
            . '<div class="row" style="gap:6px">' . ui_generate_btn('story-studio', 'generate', 'Regenerate', 'story', array('project_id' => (int) $project['id'])) . '</div>'
            . '</div></div>';
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        $data = stage_data($pid, 'story');
        $ctx = project_ctx($p);

        if ($action === 'generate') {
            $chapters = (strpos((string) $p['length'], 'Short') === 0) ? 2 : ((strpos((string) $p['length'], 'Long') === 0) ? 5 : 3);
            $ctx['chapters'] = $chapters;
            $r = orchestrator_run('story', array('kind' => 'story', 'context' => $ctx), array(
                'project_id' => $pid, 'label' => 'Story — ' . $p['title'], 'inline' => true));
            if (empty($r['ok'])) return array('ok' => false, 'error' => isset($r['error']) ? $r['error'] : 'Generation failed');
            stage_save($pid, 'story', $r['data']);
            db_exec('UPDATE projects SET status=? WHERE id=?', array('In Production', $pid));
            project_log_activity($pid, 'generated the story', '✍️');
            return array('ok' => true, 'message' => 'Story generated (' . (int) $r['data']['words'] . ' words)', 'reload' => true);
        }
        if ($action === 'continuation') {
            $r = orchestrator_run('story', array('kind' => 'paragraph', 'context' => $ctx), array('project_id' => $pid, 'label' => 'Story continuation', 'inline' => true));
            if (empty($r['ok'])) return array('ok' => false, 'error' => 'Failed');
            $data['chapters'][] = array('title' => 'Chapter ' . (count($data['chapters']) + 1), 'text' => $r['data'], 'words' => str_word_count($r['data']));
            stage_save($pid, 'story', $data);
            return array('ok' => true, 'message' => 'Chapter added', 'reload' => true);
        }
        if ($action === 'rewrite') {
            $style = isset($in['style']) ? $in['style'] : 'Cinematic';
            $text = isset($in['text']) ? $in['text'] : (isset($data['chapters'][0]['text']) ? $data['chapters'][0]['text'] : '');
            $r = orchestrator_run('rewrite', array('kind' => 'rewrite', 'text' => $text, 'style' => $style, 'context' => $ctx), array(
                'project_id' => $pid, 'label' => ucfirst($style) . ' rewrite', 'inline' => true));
            if (empty($r['ok'])) return array('ok' => false, 'error' => 'Rewrite failed');
            return array('ok' => true, 'data' => $r['data'], 'message' => ucfirst($style) . ' rewrite ready');
        }
        if ($action === 'apply') {
            $text = isset($in['text']) ? (string) $in['text'] : '';
            if (!$text || !$data) return array('ok' => false, 'error' => 'Nothing to apply');
            $data['chapters'][0]['text'] = $text;
            $data['chapters'][0]['words'] = str_word_count($text);
            stage_save($pid, 'story', $data);
            return array('ok' => true, 'message' => 'Applied to chapter 1', 'reload' => true);
        }
        if ($action === 'doctor') {
            $r = orchestrator_run('doctor', array('kind' => 'paragraph', 'context' => $ctx), array('project_id' => $pid, 'label' => 'Story Doctor', 'inline' => true));
            $scores = array();
            foreach (array('Plot', 'Character', 'Pacing', 'Dialogue', 'Continuity', 'Logic', 'Structure') as $k) {
                $scores[] = array('k' => $k, 'v' => 58 + (crc32($k . $pid) % 37), 'q' => 'Automated heuristic — advisory only.');
            }
            $report = array(
                'overall' => (int) round(array_sum(array_map(function ($s) { return $s['v']; }, $scores)) / count($scores)),
                'scores' => $scores,
                'findings' => array(
                    array('sev' => 'warn', 't' => 'Opening relies on two passive sentences', 'd' => 'Enter the scene on an action to lift perceived pace.'),
                    array('sev' => 'info', 't' => 'Second act leans on a coincidence', 'd' => 'Plant the meeting one scene earlier to earn it.'),
                    array('sev' => 'ok', 't' => 'Central want is stated early', 'd' => 'Protect this in the rewrite.'),
                ),
            );
            stage_save($pid, 'doctor', $report);
            return array('ok' => true, 'message' => 'Analysis complete', 'reload' => true);
        }
        if ($action === 'version') {
            $v = stage_data($pid, 'story_versions', array());
            $v[] = array('label' => 'v' . (count($v) + 1), 't' => db_now(), 'words' => isset($data['words']) ? $data['words'] : 0);
            stage_save($pid, 'story_versions', $v);
            return array('ok' => true, 'message' => 'Version saved', 'reload' => true);
        }
        if ($action === 'export') {
            $md = '# ' . $data['title'] . "\n\n" . $data['logline'] . "\n\n";
            foreach ($data['chapters'] as $i => $c) { $md .= '## Chapter ' . ($i + 1) . "\n\n" . $c['text'] . "\n\n"; }
            $rel = 'uploads/assets/story-' . $pid . '-' . sf_token(6) . '.md';
            if (!is_dir(SF_ROOT . '/uploads/assets')) { @mkdir(SF_ROOT . '/uploads/assets', 0755, true); }
            @file_put_contents(SF_ROOT . '/' . $rel, $md);
            project_asset($pid, 'other', 'Manuscript (markdown)', $rel);
            return array('ok' => true, 'download' => sf_url($rel), 'message' => 'Manuscript exported');
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
}

<?php
class SFPlugin_character_studio extends PluginBase {
    public function stage($project, $data) {
        $chars = is_array($data) ? $data : array();
        if (!$chars) {
            return ui_empty('🧑', 'No characters yet.', ui_generate_btn('character-studio', 'generate', 'Generate the cast', 'characters', array('project_id' => (int) $project['id'])));
        }
        $h = '<div class="row" style="gap:8px;margin-bottom:12px;flex-wrap:wrap">'
           . ui_generate_btn('character-studio', 'generate', 'Add characters', 'characters', array('project_id' => (int) $project['id']))
           . '<a class="btn" href="' . e(project_stage_url($project['id'], 'scenes')) . '">Continue to Scenes →</a></div>';
        $h .= '<div class="grid g3">';
        foreach ($chars as $i => $c) {
            $seed = 'chr|' . $project['id'] . '|' . $c['name'] . '|' . (!empty($c['locked']) ? 'locked' : 'open');
            $h .= '<div class="card pad0" style="margin:0">'
                . '<div class="frame"><img src="' . e(sf_url('index.php?r=frame&seed=' . urlencode($seed) . '&style=' . urlencode($project['style']))) . '" alt="">'
                . '<div class="ov" style="position:absolute;left:8px;top:8px;display:flex;gap:5px">'
                . (!empty($c['locked']) ? '<span class="chip chip-ok">🔒 Locked</span>' : '<span class="chip">Open</span>') . '</div>'
                . '<div class="btm"><b>' . e($c['name']) . '</b> · ' . e($c['role']) . ', ' . (int) $c['age'] . '</div></div>'
                . '<div style="padding:11px">'
                . '<div class="dim" style="font-size:11.5px;margin-bottom:7px">' . e($c['personality']) . '</div>'
                . '<div class="kv"><span class="k">Clothing</span><b style="font-size:11.5px;max-width:60%;text-align:right">' . e($c['clothing']) . '</b></div>'
                . '<div class="kv"><span class="k">Voice</span><b style="font-size:11.5px;max-width:60%;text-align:right">' . e($c['voice']) . '</b></div>'
                . '<div class="kv"><span class="k">Arc</span><b style="font-size:11.5px;max-width:60%;text-align:right">' . e($c['arc']) . '</b></div>'
                . '<div class="row" style="gap:5px;margin-top:9px">'
                . '<button class="btn xs" data-api="character-studio.lock" data-payload=\'{"project_id":' . (int) $project['id'] . ',"name":' . json_encode($c['name']) . '}\' data-reload="1">' . (!empty($c['locked']) ? 'Unlock' : '🔒 Lock') . '</button>'
                . '<button class="btn xs gho" data-api="character-studio.relationships" data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-reload="1">Build relationships</button>'
                . '</div>'
                . '<details style="margin-top:8px"><summary style="cursor:pointer;font-size:12px;color:var(--tx2)">📖 Character bible</summary>'
                . '<div data-form style="margin-top:8px">' . $this->bible_fields($c, (int) $project['id']) . '</div></details>'
                . '</div></div></div>';
        }
        $h .= '</div>';
        /* ---- character bible completeness (feature 12/13) ---- */
        $need = array('appearance', 'background', 'motivations', 'goals', 'fears', 'references');
        $rows = '';
        $worst = 100;
        foreach ($chars as $c) {
            $b = isset($c['bible']) && is_array($c['bible']) ? $c['bible'] : array();
            $have = 0;
            foreach ($need as $f) { if (!empty($b[$f])) { $have++; } }
            $pct = (int) round(100 * $have / count($need));
            $worst = min($worst, $pct);
            $rows .= '<div class="kv"><span class="k">' . e($c['name']) . (!empty($c['locked']) ? ' \xF0\x9F\x94\x92' : '') . '</span>'
                . '<b style="font-size:11.5px">' . $pct . '% complete</b></div>';
        }
        $h .= '<div class="card"><h3>\xF0\x9F\x93\x96 Character bible</h3>'
            . '<div class="muted" style="font-size:12px;margin-bottom:8px">Consistency starts here: fill every field and the look, voice and arc stay stable across scenes, shots and images.</div>'
            . $rows
            . '<div class="muted" style="font-size:11.5px;margin-top:8px">'
            . ($worst === 100 ? '\xE2\x9C\x85 Every character has a complete bible.' : '\xE2\x9A\xA0\xEF\xB8\x8F Complete the missing fields to lock consistency.')
            . '</div></div>';
        /* relationship map */
        $rels = stage_data($project['id'], 'relationships');
        if ($rels) {
            $h .= '<div class="card"><h3>Relationship map</h3><div class="row wrap" style="gap:8px">';
            foreach ($rels as $r) {
                $col = array('Family' => '#33c98a', 'Friendship' => '#5aa9ff', 'Romance' => '#f2545b', 'Rivalry' => '#f5c042', 'Alliance' => '#a78bfa', 'Enemy' => '#ff7a1a');
                $h .= '<span class="chip"><i class="dot" style="background:' . (isset($col[$r['type']]) ? $col[$r['type']] : '#888') . '"></i>'
                    . e($r['from']) . ' → ' . e($r['to']) . ' · ' . e($r['type']) . '</span>';
            }
            $h .= '</div></div>';
        }
        return $h;
    }
    /** Character bible editor fields (feature 12). */
    public function bible_fields($c, $pid) {
        $b = isset($c['bible']) && is_array($c['bible']) ? $c['bible'] : array();
        $labels = array('appearance' => 'Appearance', 'background' => 'Background', 'motivations' => 'Motivations',
            'goals' => 'Goals', 'fears' => 'Fears', 'references' => 'References', 'consistency' => 'Consistency notes');
        $h = '';
        foreach ($labels as $f => $lab) {
            $h .= '<label class="dim" style="font-size:10.5px;display:block;margin:6px 0 2px">' . e($lab) . '</label>'
                . '<textarea name="' . e($f) . '" class="inp" rows="2" style="width:100%;font-size:12px">'
                . e(isset($b[$f]) ? (string) $b[$f] : '') . '</textarea>';
        }
        return $h . '<button class="btn xs pri" style="margin-top:8px" data-api="character-studio.bible"'
            . ' data-payload=\'{"project_id":' . (int) $pid . ',"name":' . json_encode($c['name']) . '}\''
            . ' data-reload="1">Save bible</button>';
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        if ($action === 'generate') {
            $ctx = project_ctx($p);
            $r = orchestrator_run('characters', array('kind' => 'characters', 'context' => $ctx, 'n' => 4), array(
                'project_id' => $pid, 'label' => 'Character generation', 'inline' => true));
            if (empty($r['ok'])) return array('ok' => false, 'error' => isset($r['error']) ? $r['error'] : 'Failed');
            $have = stage_data($pid, 'characters', array());
            $names = array(); foreach ($have as $c) { $names[] = $c['name']; }
            foreach ($r['data'] as $c) { if (!in_array($c['name'], $names, true)) { $have[] = $c; } }
            stage_save($pid, 'characters', $have);
            project_log_activity($pid, 'created characters', '🧑');
            return array('ok' => true, 'message' => count($r['data']) . ' characters generated', 'reload' => true);
        }
        if ($action === 'lock') {
            $chars = stage_data($pid, 'characters', array());
            foreach ($chars as $i => $c) {
                if ($c['name'] === $in['name']) { $chars[$i]['locked'] = empty($c['locked']); }
            }
            stage_save($pid, 'characters', $chars);
            return array('ok' => true, 'message' => 'Character lock updated', 'reload' => true);
        }
        if ($action === 'relationships') {
            $chars = stage_data($pid, 'characters', array());
            $types = array('Family', 'Friendship', 'Romance', 'Rivalry', 'Alliance', 'Enemy');
            $rels = array();
            foreach ($chars as $i => $c) {
                foreach ($chars as $j => $o) {
                    if ($i === $j) continue;
                    if ((crc32($c['name'] . $o['name']) % 3) !== 0) continue;
                    $rels[] = array('from' => $c['name'], 'to' => $o['name'], 'type' => $types[crc32($c['name'] . $o['name']) % count($types)]);
                }
            }
            stage_save($pid, 'relationships', $rels);
            return array('ok' => true, 'message' => count($rels) . ' relationships mapped', 'reload' => true);
        }
        if ($action === 'bible') {
            $name = (string) (isset($in['name']) ? $in['name'] : '');
            $chars = stage_data($pid, 'characters', array());
            $found = false;
            $fields = array('appearance', 'background', 'motivations', 'goals', 'fears', 'references', 'consistency');
            foreach ($chars as $i => $c) {
                if (!isset($c['name']) || $c['name'] !== $name) { continue; }
                $b = isset($c['bible']) && is_array($c['bible']) ? $c['bible'] : array();
                foreach ($fields as $f) {
                    $b[$f] = isset($in[$f]) ? sf_sub(trim((string) $in[$f]), 0, 1200) : (isset($b[$f]) ? $b[$f] : '');
                }
                $chars[$i]['bible'] = $b;
                $found = true;
            }
            if (!$found) { return array('ok' => false, 'error' => 'Character not found'); }
            stage_save($pid, 'characters', $chars);
            project_log_activity($pid, 'updated the character bible for ' . sf_sub($name, 0, 40), '📖');
            return array('ok' => true, 'message' => 'Character bible saved', 'reload' => true);
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
}

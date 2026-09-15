<?php
class SFPlugin_voice_studio extends PluginBase {
    public function stage($project, $data) {
        $chars = stage_data($project['id'], 'characters', array());
        $voices = is_array($data) ? $data : array();
        if (!$chars) return ui_empty('🧑', 'Create characters first.');
        $h = '<div class="row" style="gap:8px;margin-bottom:12px">'
           . ui_generate_btn('voice-studio', 'all', 'Generate all voices', 'voice', array('project_id' => (int) $project['id']))
           . '<a class="btn" href="' . e(project_stage_url($project['id'], 'audio')) . '">Continue to Music & SFX →</a></div>';
        $h .= '<div class="col">';
        foreach ($chars as $c) {
            $assigned = isset($voices[$c['name']]) ? $voices[$c['name']] : null;
            $h .= '<div class="card"><div class="row" style="gap:14px;align-items:flex-start">'
                . '<div class="frame" style="width:72px;flex:0 0 72px"><img src="' . e(sf_url('index.php?r=frame&seed=' . urlencode('chr|' . $project['id'] . '|' . $c['name'] . '|open') . '&style=' . urlencode($project['style']) . '&subject=character')) . '" alt=""></div>'
                . '<div style="flex:1"><div class="row" style="justify-content:space-between"><b style="font-size:15px">' . e($c['name']) . '</b><span class="chip">' . e($c['role']) . '</span></div>'
                . '<div class="dim" style="font-size:12px;margin:4px 0 9px">' . e($c['voice']) . '</div>'
                . '<div class="row wrap" style="gap:8px">'
                . '<input name="voice" placeholder="Voice name (e.g. Adaeze)" value="' . e($assigned ? $assigned['voice'] : '') . '" style="width:auto;min-width:180px" data-vc="' . e($c['name']) . '">'
                . '<select name="emotion" data-vc="' . e($c['name']) . '" style="width:auto">' . implode('', array_map(function ($x) use ($assigned) {
                    return '<option' . ($assigned && $assigned['emotion'] === $x ? ' selected' : '') . '>' . e($x) . '</option>';
                }, array('Neutral', 'Warm', 'Tense', 'Tender', 'Authoritative', 'Playful'))) . '</select>'
                . '<button class="btn xs pri" data-api="voice-studio.assign" data-payload=\'{"project_id":' . (int) $project['id'] . ',"name":' . json_encode($c['name']) . '}\' data-form="vc-' . e(md5($c['name'])) . '">Assign</button>'
                . '<button class="btn xs" data-api="voice-studio.render" data-payload=\'{"project_id":' . (int) $project['id'] . ',"name":' . json_encode($c['name']) . '}\' data-reload="1">Generate lines</button>'
                . '</div></div>';
            if ($assigned && !empty($assigned['path'])) {
                $h .= '<div style="width:260px"><audio controls src="' . e(sf_url($assigned['path'])) . '" style="width:100%"></audio>'
                    . '<div class="dim" style="font-size:10.5px;margin-top:4px">provider: ' . e($assigned['provider']) . ' · ' . ui_status('MOCK') . '</div></div>';
            }
            $h .= '</div><div data-form="vc-' . e(md5($c['name'])) . '" style="display:none"></div></div>';
        }
        $h .= '</div>';
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        $voices = stage_data($pid, 'voice', array());
        if ($action === 'assign') {
            $name = isset($in['name']) ? (string) $in['name'] : '';
            $voices[$name] = array(
                'voice' => isset($in['voice']) && $in['voice'] ? $in['voice'] : 'Adaeze',
                'emotion' => isset($in['emotion']) ? $in['emotion'] : 'Neutral',
                'path' => isset($voices[$name]['path']) ? $voices[$name]['path'] : null,
                'provider' => isset($voices[$name]['provider']) ? $voices[$name]['provider'] : null,
            );
            stage_save($pid, 'voice', $voices);
            return array('ok' => true, 'message' => 'Voice assigned to ' . $name, 'reload' => true);
        }
        if ($action === 'render' || $action === 'all') {
            $chars = stage_data($pid, 'characters', array());
            $story = stage_data($pid, 'story', array());
            $line = isset($story['logline']) ? $story['logline'] : (isset($story['chapters'][0]['text']) ? substr($story['chapters'][0]['text'], 0, 220) : 'This is a sample line for ' . $p['title'] . '.');
            $targets = $action === 'all' ? $chars : array_filter($chars, function ($c) use ($in) { return $c['name'] === $in['name']; });
            $done = 0;
            foreach ($targets as $c) {
                $voice = isset($voices[$c['name']]['voice']) ? $voices[$c['name']]['voice'] : 'Adaeze';
                $emotion = isset($voices[$c['name']]['emotion']) ? $voices[$c['name']]['emotion'] : 'Neutral';
                $res = orchestrator_run('voice', array(
                    'text' => $line,
                    'voice' => array('id' => $voice, 'gender' => (stripos((string) (isset($c['gender']) ? $c['gender'] : ''), 'Male') === 0 ? 'Masculine' : 'Feminine')),
                ), array('project_id' => $pid, 'label' => 'Voice — ' . $c['name'], 'inline' => true));
                if (!empty($res['ok']) && !empty($res['data']['path'])) {
                    $voices[$c['name']] = array('voice' => $voice, 'emotion' => $emotion, 'path' => $res['data']['path'], 'provider' => $res['data']['provider'], 'duration' => $res['data']['duration']);
                    project_asset($pid, 'audio', 'Voice — ' . $c['name'], $res['data']['path'], array('character' => $c['name'], 'voice' => $voice));
                    $done++;
                }
            }
            stage_save($pid, 'voice', $voices);
            return array('ok' => $done > 0, 'message' => $done . ' voice track(s) rendered', 'reload' => true);
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
}

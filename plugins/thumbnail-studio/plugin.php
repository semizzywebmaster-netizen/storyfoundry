<?php
class SFPlugin_thumbnail_studio extends PluginBase {
    public function stage($project, $data) {
        $data = is_array($data) ? $data : array();
        $h = '<div class="row" style="gap:8px;margin-bottom:12px">'
           . ui_generate_btn('thumbnail-studio', 'generate', 'Generate 6 thumbnails', 'thumb', array('project_id' => (int) $project['id']))
           . '<a class="btn" href="' . e(project_stage_url($project['id'], 'social')) . '">Social media pack →</a></div>';
        if (!$data) return $h . ui_empty('🖼️', 'No thumbnails yet.');
        $h .= '<div class="grid g3">';
        foreach ($data as $i => $t) {
            $meta = isset($t['meta']) ? $t['meta'] : array('element' => 'Face', 'text' => $project['title'], 'arrow' => false, 'emoji' => true, 'ctr' => 50);
            $h .= '<div class="card pad0" style="margin:0">'
                . '<div class="frame"><img src="' . e(sf_url('index.php?r=frame&seed=' . urlencode($t['seed']) . '&style=' . urlencode($project['style']) . '&subject=thumbnail&text=' . urlencode($meta['text']))) . '" alt="">'
                . '<div class="btm">' . e($meta['element']) . ($meta['arrow'] ? ' · arrow' : '') . ($meta['emoji'] ? ' · emoji' : '') . '</div></div>'
                . '<div style="padding:10px"><div class="row" style="justify-content:space-between;margin-bottom:6px"><b style="font-size:12px">Option ' . ($i + 1) . '</b>'
                . '<span class="chip ' . ($meta['ctr'] > 60 ? 'chip-ok' : '') . '">predicted CTR ' . (int) $meta['ctr'] . '%</span></div>'
                . '<div class="bar"><i style="width:' . (int) $meta['ctr'] . '%"></i></div>'
                . '<div class="row" style="gap:5px;margin-top:8px">'
                . '<button class="btn xs pri" data-api="thumbnail-studio.pick" data-payload=\'{"project_id":' . (int) $project['id'] . ',"i":' . $i . '}\' data-reload="1">' . (!empty($t['picked']) ? '★ Selected' : 'Select') . '</button>'
                . '<button class="btn xs gho" data-api="thumbnail-studio.ab" data-payload=\'{"project_id":' . (int) $project['id'] . ',"i":' . $i . '}\' data-reload="1">A/B test</button>'
                . '</div></div></div>';
        }
        $h .= '</div>';
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        $data = stage_data($pid, 'thumbnail', array());
        if ($action === 'generate') {
            $elements = array('Face', 'Text', 'Arrow', 'Emoji', 'Circle', 'Split', 'Before/After');
            $out = array();
            for ($i = 0; $i < 6; $i++) {
                $seed = 'thumb|' . $pid . '|' . $i . '|' . sf_token(4);
                $res = orchestrator_run('thumb', array('seed' => $seed, 'style' => $p['style'], 'w' => 1280, 'h' => 720, 'subject' => 'thumbnail', 'text' => $p['title']), array(
                    'project_id' => $pid, 'label' => 'Thumbnail ' . ($i + 1), 'capability' => 'image', 'inline' => true));
                $meta = array(
                    'element' => $elements[$i % count($elements)], 'text' => strtoupper(substr($p['title'], 0, 22)),
                    'arrow' => ($i % 2 === 0), 'emoji' => ($i % 3 === 0),
                    'ctr' => 45 + (crc32($seed) % 45),
                );
                $out[] = array('seed' => $seed, 'path' => !empty($res['ok']) && !empty($res['data']['path']) ? $res['data']['path'] : null, 'meta' => $meta);
            }
            stage_save($pid, 'thumbnail', $out);
            return array('ok' => true, 'message' => '6 thumbnails generated', 'reload' => true);
        }
        if ($action === 'pick') {
            foreach ($data as $i => $t) { $data[$i]['picked'] = ($i === (int) $in['i']); }
            stage_save($pid, 'thumbnail', $data);
            project_log_activity($pid, 'picked a thumbnail', '🖼️');
            return array('ok' => true, 'message' => 'Thumbnail selected', 'reload' => true);
        }
        if ($action === 'ab') {
            $d = stage_data($pid, 'thumbnail_ab', array('a' => 0, 'b' => 0));
            $d['b'] = (int) $in['i'];
            $d['impressions'] = 0; $d['clicks_a'] = 0; $d['clicks_b'] = 0;
            stage_save($pid, 'thumbnail_ab', $d);
            return array('ok' => true, 'message' => 'A/B test armed — option 1 vs option ' . ((int) $in['i'] + 1), 'reload' => true);
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
}

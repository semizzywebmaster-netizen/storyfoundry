<?php
class SFPlugin_image_studio extends PluginBase {
    public function stage($project, $data) {
        $shots = stage_data($project['id'], 'shots', array());
        $frames = is_array($data) ? $data : array();
        $styles = array('cinematic', 'realistic', 'anime', 'cartoon', 'threed', 'documentary', 'film', 'comic', 'illustration', 'darkcinematic', 'fantasy', 'historical', 'noir');
        $ratios = array('16:9', '9:16', '1:1', '4:3', '2.39:1');
        $style = isset($_GET['style']) ? $_GET['style'] : $project['style'];
        $ratio = isset($_GET['ratio']) ? $_GET['ratio'] : '16:9';
        if (!$shots) return ui_empty('🎞️', 'Generate a shot list first.');

        $h = '<div class="grid" style="grid-template-columns:280px 1fr;align-items:start">';
        $h .= '<div class="col">';
        $h .= '<div class="card"><h3>Generation settings</h3>'
            . '<label class="fl">Visual style<select name="style" onchange="location.href=this.form.action+this.value">';
        foreach ($styles as $s) { $h .= '<option value="' . e($s) . '"' . ($s === $style ? ' selected' : '') . '>' . e($s) . '</option>'; }
        $h .= '</select></label>';
        $h .= '<label class="fl" style="margin-top:10px">Aspect ratio<select name="ratio">';
        foreach ($ratios as $r) { $h .= '<option value="' . e($r) . '"' . ($r === $ratio ? ' selected' : '') . '>' . e($r) . '</option>'; }
        $h .= '</select></label>';
        $h .= '<button class="btn pri blk" style="margin-top:12px" data-api="image-studio.generate" data-payload=\'{"project_id":' . (int) $project['id'] . ',"style":' . json_encode($style) . ',"ratio":' . json_encode($ratio) . '}\' data-reload="1">Generate ' . count($shots) . ' frames</button>';
        $h .= '<hr class="sep"><div class="dim" style="font-size:11.5px"><b>Provider routing</b><br>';
        foreach (providers_for('image') as $pv) { $h .= '· ' . e($pv['name']) . ' — ' . ui_status($pv['status']) . '<br>'; }
        $h .= '</div>';
        $h .= '<div class="banner warn" style="margin-top:10px"><span>⚠</span><div style="font-size:11.5px">With no image provider configured, frames come from the local procedural synthesiser and are badged MOCK.</div></div>';
        $h .= '<hr class="sep"><div class="dim" style="font-size:11.5px"><b>Prompt enhancement</b></div>'
            . '<div class="mono" style="font-size:11px;background:var(--bg2);padding:9px;border-radius:8px;margin-top:6px">'
            . e('cinematic ' . $style . ' still from ' . $project['title'] . ', ' . strtolower((string) $project['genre']) . ', set in ' . strtolower((string) $project['setting']) . ', ' . $project['culture'] . ' texture, warm practical light, shallow depth, film grain --ar ' . $ratio)
            . '</div></div>';
        $h .= '<div class="card"><h3>Style library</h3><div class="grid g2" style="gap:8px">';
        foreach ($styles as $s) {
            $h .= '<a class="pick' . ($s === $style ? ' sel' : '') . '" href="' . e(project_stage_url($project['id'], 'images') . '&style=' . urlencode($s)) . '">'
                . '<div class="frame"><img src="' . e(sf_url('index.php?r=frame&seed=' . urlencode('style|' . $s) . '&style=' . urlencode($s))) . '" alt=""></div>'
                . '<div style="padding:5px;font-size:11px;text-align:center;font-weight:700">' . e($s) . '</div></a>';
        }
        $h .= '</div></div></div>';

        $h .= '<div class="col"><div class="card"><h3>Scene frames</h3><div class="shotgrid">';
        foreach ($shots as $s) {
            $key = 'img|' . $project['id'] . '|' . $s['n'];
            $has = isset($frames[$key]);
            $h .= '<div><div class="frame">'
                . '<img src="' . e(sf_url('index.php?r=frame&seed=' . urlencode($key) . '&style=' . urlencode($style))) . '" alt="">'
                . '<div class="ov" style="position:absolute;left:8px;top:8px">' . ($has ? '<span class="chip chip-ok">✓ saved</span>' : '<span class="chip">preview</span>') . '</div>'
                . '<div class="btm">Sh.' . (int) $s['n'] . ' · ' . e($s['type']) . '</div></div>'
                . '<div class="row" style="gap:5px;margin-top:6px">'
                . '<button class="btn xs" data-api="image-studio.variations" data-payload=\'{"project_id":' . (int) $project['id'] . ',"n":' . (int) $s['n'] . '}\'>Variations</button>'
                . '<button class="btn xs gho" data-api="image-studio.save" data-payload=\'{"project_id":' . (int) $project['id'] . ',"n":' . (int) $s['n'] . ',"style":' . json_encode($style) . '}\'>Save to library</button>'
                . '</div></div>';
        }
        $h .= '</div></div></div></div>';
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        $shots = stage_data($pid, 'shots', array());
        $frames = stage_data($pid, 'images', array());
        if ($action === 'generate') {
            $style = isset($in['style']) ? $in['style'] : $p['style'];
            $ratio = isset($in['ratio']) ? $in['ratio'] : '16:9';
            $wh = array('16:9' => array(1280, 720), '9:16' => array(720, 1280), '1:1' => array(1024, 1024), '4:3' => array(1152, 864), '2.39:1' => array(1280, 536));
            $dims = isset($wh[$ratio]) ? $wh[$ratio] : array(1280, 720);
            $saved = 0;
            foreach ($shots as $s) {
                $res = orchestrator_run('image', array(
                    'prompt' => $p['title'] . ' shot ' . $s['n'] . ': ' . $s['type'] . ', ' . $s['action'] . ', ' . $s['lighting'],
                    'seed' => 'img|' . $pid . '|' . $s['n'], 'style' => $style, 'w' => $dims[0], 'h' => $dims[1],
                    'subject' => 'scene', 'figures' => 1, 'time' => 'day',
                ), array('project_id' => $pid, 'label' => 'Frame — shot ' . $s['n'], 'inline' => true));
                if (!empty($res['ok']) && !empty($res['data']['path'])) {
                    $frames['img|' . $pid . '|' . $s['n']] = array('path' => $res['data']['path'], 'style' => $style, 'ratio' => $ratio, 'provider' => $res['data']['provider']);
                    project_asset($pid, 'image', 'Shot ' . $s['n'] . ' frame', $res['data']['path'], array('style' => $style, 'shot' => $s['n']));
                    $saved++;
                }
            }
            stage_save($pid, 'images', $frames);
            db_exec('UPDATE projects SET style=? WHERE id=?', array($style, $pid));
            return array('ok' => true, 'message' => $saved . ' frames generated and saved', 'reload' => true);
        }
        if ($action === 'save') {
            $n = (int) $in['n'];
            $key = 'img|' . $pid . '|' . $n;
            $res = providers_call('image', array('seed' => $key, 'style' => $p['style'], 'w' => 1280, 'h' => 720, 'subject' => 'scene'), array());
            if (empty($res['ok'])) return array('ok' => false, 'error' => 'Frame generation failed');
            $frames[$key] = array('path' => $res['data']['path'], 'style' => $p['style'], 'provider' => $res['data']['provider']);
            stage_save($pid, 'images', $frames);
            project_asset($pid, 'image', 'Shot ' . $n . ' frame', $res['data']['path'], array('shot' => $n));
            return array('ok' => true, 'message' => 'Frame saved', 'reload' => true);
        }
        if ($action === 'variations') {
            $n = (int) $in['n'];
            $out = array();
            for ($i = 0; $i < 4; $i++) {
                $res = providers_call('image', array('seed' => 'img|' . $pid . '|' . $n . '|v' . $i, 'style' => $p['style'], 'w' => 640, 'h' => 360), array());
                if (!empty($res['ok'])) { $out[] = sf_url($res['data']['path']); }
            }
            return array('ok' => true, 'variations' => $out, 'message' => count($out) . ' variations rendered');
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
}

<?php
class SFPlugin_video_engine extends PluginBase {
    public function stage($project, $data) {
        $frames = stage_data($project['id'], 'images', array());
        $clips = is_array($data) ? $data : array();
        if (!$frames) return ui_empty('🖼️', 'Generate images first.');
        $motion = isset($_GET['motion']) ? (int) $_GET['motion'] : 40;
        $h = '<div class="row" style="gap:8px;margin-bottom:12px;flex-wrap:wrap;align-items:center">'
           . '<span class="dim" style="font-size:12px">Motion</span>'
           . '<input type="range" min="0" max="100" value="' . $motion . '" style="width:150px" onchange="location.href=\'' . e(project_stage_url($project['id'], 'video')) . '&motion=\'+this.value">'
           . '<span class="mono dim">' . $motion . '%</span>'
           . '<button class="btn pri" data-api="video-engine.all" data-payload=\'{"project_id":' . (int) $project['id'] . ',"motion":' . $motion . '}\'>🎬 Generate ' . count($frames) . ' clips (queued)</button>'
           . '<a class="btn" href="' . e(project_stage_url($project['id'], 'timeline')) . '">Open timeline →</a></div>';
        $h .= '<div class="banner info"><span>ℹ</span><div>Clip generation is <b>queued</b> — long renders run in the background worker (<code>cron.php</code>), not inside the web request. With no video provider configured you get a MOCK clip (procedural frames + Ken Burns), clearly badged.</div></div>';
        $h .= '<div class="grid g3">';
        foreach ($frames as $key => $f) {
            $n = (int) (is_array($f) && isset($f['shot']) ? $f['shot'] : 0);
            $has = isset($clips[$key]);
            $h .= '<div class="card pad0" style="margin:0"><div class="frame">'
                . '<img src="' . e(sf_url('index.php?r=frame&seed=' . urlencode($key) . '&style=' . urlencode($f['style']))) . '" alt="">'
                . ($has ? '<div class="ov" style="position:absolute;left:8px;top:8px"><span class="chip chip-ok">✓ clip</span></div><div class="btm">' . e(basename($clips[$key]['path'])) . '</div>'
                        : '<div class="ov" style="position:absolute;left:8px;top:8px"><span class="chip">still</span></div>')
                . '</div><div style="padding:10px" class="row" style="gap:6px">'
                . '<button class="btn xs" data-api="video-engine.one" data-payload=\'{"project_id":' . (int) $project['id'] . ',"key":' . json_encode($key) . ',"motion":' . $motion . '}\'>' . ($has ? 'Re-generate' : 'Animate') . '</button>'
                . ($has ? '<a class="btn xs gho" href="' . e(sf_url($clips[$key]['path'])) . '">Preview</a>' : '') . '</div></div>';
        }
        $h .= '</div>';
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        $frames = stage_data($pid, 'images', array());
        $clips = stage_data($pid, 'video', array());
        if ($action === 'all') {
            $motion = (int) (isset($in['motion']) ? $in['motion'] : 40);
            $jobs = array();
            foreach (array_keys($frames) as $key) {
                $j = sf_queue('video', 'video-engine.render_clip', array(
                    'project_id' => $pid, 'key' => $key, 'motion' => $motion,
                ), array('project_id' => $pid, 'label' => 'Clip — shot ' . (int) (isset($frames[$key]['shot']) ? $frames[$key]['shot'] : 0)));
                if (!empty($j['ok'])) { $jobs[] = $j; }
            }
            if (!$jobs) return array('ok' => false, 'error' => 'Could not queue clips');
            project_log_activity($pid, count($jobs) . ' clips queued', '🎬');
            return array('ok' => true, 'queued' => count($jobs), 'message' => count($jobs) . ' clips queued — track them in Jobs');
        }
        if ($action === 'one') {
            $j = sf_queue('video', 'video-engine.render_clip', array(
                'project_id' => $pid, 'key' => $in['key'], 'motion' => (int) (isset($in['motion']) ? $in['motion'] : 40),
            ), array('project_id' => $pid, 'label' => 'Clip render'));
            return !empty($j['ok']) ? array('ok' => true, 'queued' => 1, 'message' => 'Clip queued') : array('ok' => false, 'error' => isset($j['error']) ? $j['error'] : 'Queue failed');
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
    /** Job payload handler executed by cron.php / orchestrator. */
    public function job_render_clip($payload, $job) {
        $pid = (int) $payload['project_id'];
        $key = $payload['key'];
        $frames = stage_data($pid, 'images', array());
        if (!isset($frames[$key])) throw new RuntimeException('Source frame missing');
        $motion = max(0, min(100, (int) $payload['motion'])) / 100;
        $out = 'uploads/assets/clip-' . $pid . '-' . md5($key) . '.mp4';
        if (media_ffmpeg()) {
            /* real Ken Burns render from the generated still */
            $full = SF_ROOT . '/' . ltrim($frames[$key]['path'], '/');
            if (is_file($full)) {
                $r = media_render_video(array(array('path' => $frames[$key]['path'], 'duration' => 4, 'motion' => $motion)), $out, array());
                if (!empty($r['ok'])) { return array('path' => $out, 'provider' => 'ffmpeg', 'status' => 'REAL'); }
            }
        }
        /* MOCK fallback: a frame sequence the player can step through, always badged */
        $svg = mockai_frame_svg($key, array('style' => $frames[$key]['style'], 'w' => 1280, 'h' => 720, 'subject' => 'scene'));
        $mockRel = 'uploads/assets/clip-' . $pid . '-' . md5($key) . '.svg';
        if (!is_dir(SF_ROOT . '/uploads/assets')) { @mkdir(SF_ROOT . '/uploads/assets', 0755, true); }
        @file_put_contents(SF_ROOT . '/' . $mockRel, $svg);
        return array('ok' => true, 'data' => array('path' => $mockRel), 'provider' => 'mock.video', 'status' => 'MOCK',
            'note' => 'Placeholder clip — configure a video provider or enable FFmpeg for real motion.');
    }
    public function job_done($method, $job, $res) {
        $result = isset($res['data']) ? $res['data'] : $res;
        $pid = (int) $job['project_id'];
        $clips = stage_data($pid, 'video', array());
        $payload = json_decode($job['payload'], true);
        if (!is_array($payload)) { $payload = array(); }
        $key     = isset($payload['key']) ? (string) $payload['key'] : ('clip-' . (int) $job['id']);
        $path    = isset($result['path']) ? (string) $result['path'] : '';
        $status  = isset($result['status']) ? (string) $result['status'] : 'DONE';
        $prov    = isset($result['provider']) ? (string) $result['provider'] : 'unknown';
        $clips[$key] = array('path' => $path, 'status' => $status, 'provider' => $prov, 't' => db_now());
        stage_save($pid, 'video', $clips);
        $title = isset($job['title']) ? (string) $job['title'] : 'Clip';
        if ($path !== '') {
            project_asset($pid, 'video', 'Clip — ' . $title, $path, array('status' => $status, 'provider' => $prov));
        }
        project_log_activity($pid, 'clip rendered (' . $status . ')', '🎬');
    }
}

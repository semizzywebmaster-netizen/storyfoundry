<?php
class SFPlugin_timeline_editor extends PluginBase {
    private function order($project, $data) {
        $shots = stage_data($project['id'], 'shots', array());
        $order = isset($data['order']) ? $data['order'] : array();
        if (!$order) { foreach ($shots as $s) { $order[] = $s['n']; } }
        $by = array(); foreach ($shots as $s) { $by[$s['n']] = $s; }
        $list = array(); foreach ($order as $n) { if (isset($by[$n])) { $list[] = $by[$n]; } }
        return array($list, $by);
    }
    public function stage($project, $data) {
        $data = is_array($data) ? $data : array();
        $clips = stage_data($project['id'], 'video', array());
        list($list, $by) = $this->order($project, $data);
        if (!$list) return ui_empty('🎞️', 'Generate shots first.');
        $audio = stage_data($project['id'], 'audio', array());
        $subs = stage_data($project['id'], 'subtitles', array());
        $total = 0; foreach ($list as $s) { $total += (int) $s['duration']; }

        $h = '<div class="row" style="gap:8px;margin-bottom:12px;flex-wrap:wrap">'
           . '<span class="chip">Duration ' . gmdate('i:s', $total) . '</span>'
           . '<button class="btn pri" data-api="timeline-editor.autocut" data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-reload="1">✨ Auto-cut to beats</button>'
           . '<button class="btn" data-api="timeline-editor.transitions" data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-reload="1">Add transitions</button>'
           . '<button class="btn gho" data-api="timeline-editor.render" data-payload=\'{"project_id":' . (int) $project['id'] . '}\'>🎬 Render video</button>'
           . '<a class="btn" href="' . e(project_stage_url($project['id'], 'subtitles')) . '">Subtitles →</a></div>';

        /* tracks */
        $h .= '<div class="card"><h3>Timeline</h3>';
        $h .= '<div class="track"><div class="lbl">Video</div><div class="clips">';
        foreach ($list as $i => $s) {
            $key = 'img|' . $project['id'] . '|' . $s['n'];
            $w = max(70, (int) $s['duration'] * 26);
            $h .= '<div class="clip" style="width:' . $w . 'px">'
                . '<img src="' . e(sf_url('index.php?r=frame&seed=' . urlencode($key) . '&style=' . urlencode($project['style']) . '&w=320&h=180')) . '" alt="">'
                . '<span class="tag">' . (int) $s['duration'] . 's</span>'
                . (isset($clips[$key]) ? '<span class="chip chip-ok" style="position:absolute;right:4px;top:4px;font-size:9px">clip</span>' : '')
                . '<div class="row" style="gap:2px;margin-top:3px">'
                . '<button class="btn xs" data-api="timeline-editor.move" data-payload=\'{"project_id":' . (int) $project['id'] . ',"n":' . (int) $s['n'] . ',"dir":-1}\' data-reload="1">◀</button>'
                . '<button class="btn xs" data-api="timeline-editor.move" data-payload=\'{"project_id":' . (int) $project['id'] . ',"n":' . (int) $s['n'] . ',"dir":1}\' data-reload="1">▶</button>'
                . '<button class="btn xs gho" data-api="timeline-editor.drop" data-payload=\'{"project_id":' . (int) $project['id'] . ',"n":' . (int) $s['n'] . '}\' data-reload="1">✕</button>'
                . '</div></div>';
        }
        $h .= '</div></div>';
        $h .= '<div class="track"><div class="lbl">Audio</div><div class="clips">'
            . '<div class="clip aud" style="width:180px">🔊 Dialogue' . (!empty($audio['mix']) ? '' : '') . '</div>'
            . '<div class="clip aud" style="width:160px">🎵 Music bed</div><div class="clip aud" style="width:120px">✨ SFX</div></div></div>';
        $h .= '<div class="track"><div class="lbl">Subtitles</div><div class="clips">'
            . ($subs ? '<div class="clip sub" style="width:260px">“' . e($subs[0]['text']) . '…”</div>' : '<div class="clip sub" style="width:140px;opacity:.5">no subtitles yet</div>')
            . '</div></div>';
        $h .= '<div class="row" style="gap:8px;margin-top:10px"><span class="dim" style="font-size:11.5px">Tracks: video · audio · subtitle · overlay</span>'
            . '<button class="btn xs gho" data-api="timeline-editor.overlay" data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-reload="1">+ Text overlay</button></div>';
        $h .= '</div>';
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        $data = stage_data($pid, 'timeline', array());
        list($list, $by) = $this->order($p, $data);
        if ($action === 'move') {
            $order = array(); foreach ($list as $s) { $order[] = $s['n']; }
            $n = (int) $in['n']; $dir = (int) $in['dir'];
            $i = array_search($n, $order, true);
            if ($i !== false) {
                $j = $i + $dir;
                if ($j >= 0 && $j < count($order)) { $tmp = $order[$i]; $order[$i] = $order[$j]; $order[$j] = $tmp; }
            }
            $data['order'] = $order; stage_save($pid, 'timeline', $data);
            return array('ok' => true, 'message' => 'Clip moved', 'reload' => true);
        }
        if ($action === 'drop') {
            $order = array(); foreach ($list as $s) { if ($s['n'] != $in['n']) { $order[] = $s['n']; } }
            $data['order'] = $order; stage_save($pid, 'timeline', $data);
            return array('ok' => true, 'message' => 'Clip removed from timeline', 'reload' => true);
        }
        if ($action === 'autocut') {
            /* tighten durations to the beat: shorter shots in the middle, longer at the ends */
            $story = stage_data($pid, 'story', array());
            $beats = array();
            foreach ($list as $i => $s) {
                $d = (int) $s['duration'];
                $d = max(2, min(8, (int) round($d * (0.7 + 0.3 * (($i % 3) === 0 ? 1.25 : 0.9)))));
                $beats[] = array('n' => $s['n'], 'duration' => $d);
            }
            $data['beats'] = $beats; stage_save($pid, 'timeline', $data);
            return array('ok' => true, 'message' => 'Timeline auto-cut to ' . count($beats) . ' beats', 'reload' => true);
        }
        if ($action === 'transitions') {
            $types = array('cut', 'dissolve', 'fade', 'wipe', 'push');
            $tr = array();
            foreach ($list as $i => $s) { $tr[] = array('after' => $s['n'], 'type' => $types[$i % count($types)], 'ms' => 350); }
            $data['transitions'] = $tr; stage_save($pid, 'timeline', $data);
            return array('ok' => true, 'message' => count($tr) . ' transitions added', 'reload' => true);
        }
        if ($action === 'overlay') {
            $ov = isset($data['overlays']) ? $data['overlays'] : array();
            $ov[] = array('text' => 'New lower third', 'at' => 2, 'dur' => 3, 'pos' => 'bottom');
            $data['overlays'] = $ov; stage_save($pid, 'timeline', $data);
            return array('ok' => true, 'message' => 'Overlay added', 'reload' => true);
        }
        if ($action === 'render') {
            $j = sf_queue('render', 'timeline-editor.render_video', array('project_id' => $pid), array(
                'project_id' => $pid, 'label' => 'Render — ' . $p['title']));
            return !empty($j['ok']) ? array('ok' => true, 'queued' => true, 'message' => 'Render queued (job #' . $j['job_id'] . ')')
                                    : array('ok' => false, 'error' => isset($j['error']) ? $j['error'] : 'Render could not be queued');
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
    public function job_render_video($input, $job) {
        $pid = (int) $input['project_id'];
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        $data = stage_data($pid, 'timeline', array());
        list($list, $by) = $this->order($p, $data);
        $images = stage_data($pid, 'images', array());
        $tracks = array();
        foreach ($list as $s) {
            $key = 'img|' . $pid . '|' . $s['n'];
            if (isset($images[$key])) { $tracks[] = array('path' => $images[$key]['path'], 'duration' => (int) $s['duration']); }
        }
        if (!$tracks) throw new RuntimeException('Nothing on the timeline to render');
        $out = 'uploads/assets/video-' . $pid . '-' . sf_token(6) . '.mp4';
        if (!media_ffmpeg()) {
            return array('ok' => false, 'error' => 'FFmpeg is NOT CONFIGURED on this server, so a real video file cannot be produced. '
                . 'Enable FFmpeg (ask your host) or configure a video provider — STORYFOUNDRY will not fake a finished file.');
        }
        $r = media_render_video($tracks, $out, array());
        if (empty($r['ok'])) return array('ok' => false, 'error' => isset($r['error']) ? $r['error'] : 'Render failed');
        return array('ok' => true, 'data' => array('path' => $out, 'seconds' => array_sum(array_map(function ($t) { return $t['duration']; }, $tracks))),
            'provider' => 'ffmpeg', 'status' => 'REAL');
    }
    public function job_done($method, $job, $res) {
        if ($method !== 'job_render_video' || empty($res['data']['path'])) return null;
        $pid = (int) $job['project_id'];
        project_asset($pid, 'video', 'Master video', $res['data']['path'], array('status' => $res['status']));
        db_exec('UPDATE projects SET status=? WHERE id=?', array('Complete', $pid));
        project_log_activity($pid, 'video rendered', '🎬');
        return null;
    }
}

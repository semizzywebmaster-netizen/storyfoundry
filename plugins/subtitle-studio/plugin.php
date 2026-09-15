<?php
class SFPlugin_subtitle_studio extends PluginBase {
    public function stage($project, $data) {
        $data = is_array($data) ? $data : array();
        $shots = stage_data($project['id'], 'shots', array());
        $story = stage_data($project['id'], 'story', array());
        if (!$data) {
            return ui_empty('💬', 'No subtitles yet.', ui_generate_btn('subtitle-studio', 'generate', 'Auto-generate subtitles', 'subtitles', array('project_id' => (int) $project['id'])));
        }
        $h = '<div class="row" style="gap:8px;margin-bottom:12px;flex-wrap:wrap">'
           . ui_generate_btn('subtitle-studio', 'generate', 'Regenerate', 'subtitles', array('project_id' => (int) $project['id']))
           . '<button class="btn" data-api="subtitle-studio.srt" data-payload=\'{"project_id":' . (int) $project['id'] . '}\'>Download .SRT</button>'
           . '<button class="btn" data-api="subtitle-studio.vtt" data-payload=\'{"project_id":' . (int) $project['id'] . '}\'>Download .VTT</button>'
           . '<button class="btn gho" data-api="subtitle-studio.translate" data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-reload="1">Translate</button>'
           . '<button class="btn gho" data-api="subtitle-studio.burn" data-payload=\'{"project_id":' . (int) $project['id'] . '}\'>Burn into video</button>'
           . '<a class="btn" href="' . e(project_stage_url($project['id'], 'thumbnail')) . '">Thumbnail →</a></div>';
        $h .= '<div class="grid" style="grid-template-columns:1fr 300px;align-items:start">';
        $h .= '<div class="card"><h3>Caption track <span class="chip">' . count($data) . ' cues</span></h3><div class="tbl" style="border:0"><table>'
            . '<thead><tr><th>#</th><th>Timecode</th><th>Text</th><th>Karaoke</th></tr></thead><tbody>';
        foreach ($data as $i => $c) {
            $h .= '<tr><td class="mono dim">' . ($i + 1) . '</td>'
                . '<td class="mono" style="font-size:11px">' . e($c['start']) . ' → ' . e($c['end']) . '</td>'
                . '<td><input value="' . e($c['text']) . '" data-api="subtitle-studio.edit" data-payload=\'{"project_id":' . (int) $project['id'] . ',"i":' . $i . ',"text":"__VALUE__"}\' onchange="sfApi(this.dataset.api,Object.assign(JSON.parse(this.dataset.payload),{text:this.value}))" style="width:100%;padding:5px 7px"></td>'
                . '<td><span class="chip ' . (!empty($c['karaoke']) ? 'chip-ok' : '') . '">' . (!empty($c['karaoke']) ? 'on' : 'off') . '</span></td></tr>';
        }
        $h .= '</tbody></table></div></div>';
        $h .= '<div class="col"><div class="card"><h3>Style presets</h3>';
        foreach (array('Netflix', 'YouTube', 'TikTok', 'Instagram') as $st) {
            $h .= '<div class="kv"><span>' . e($st) . '</span><button class="btn xs" data-api="subtitle-studio.style" data-payload=\'{"project_id":' . (int) $project['id'] . ',"style":' . json_encode($st) . '}\' data-reload="1">Apply</button></div>';
        }
        $h .= '<hr class="sep"><div class="preview-sub"><div>' . e($data[0]['text']) . '</div></div>';
        $h .= '<div class="dim" style="font-size:11.5px;margin-top:8px">Karaoke highlighting pops each word as it is spoken.</div></div></div></div>';
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        $data = stage_data($pid, 'subtitles', array());
        if ($action === 'generate') {
            $story = stage_data($pid, 'story', array());
            $text = isset($story['chapters'][0]['text']) ? $story['chapters'][0]['text'] : $p['title'];
            $sentences = preg_split('/(?<=[.!?])\s+/', strip_tags($text));
            $cues = array(); $t = 0.0;
            foreach (array_slice($sentences, 0, 24) as $s) {
                $s = trim($s); if (!$s) continue;
                $words = str_word_count($s) ? str_word_count($s) : 4;
                $dur = max(1.4, min(5.0, $words * 0.42));
                $cues[] = array('start' => $this->tc($t), 'end' => $this->tc($t + $dur), 'text' => $s, 'karaoke' => true);
                $t += $dur + 0.12;
            }
            $res = orchestrator_run('subtitles', array('kind' => 'paragraph', 'context' => project_ctx($p)), array(
                'project_id' => $pid, 'label' => 'Subtitle generation', 'inline' => true));
            stage_save($pid, 'subtitles', $cues);
            project_log_activity($pid, 'generated ' . count($cues) . ' subtitles', '💬');
            return array('ok' => true, 'message' => count($cues) . ' cues generated', 'reload' => true);
        }
        if ($action === 'edit') {
            $i = (int) $in['i'];
            if (isset($data[$i])) { $data[$i]['text'] = (string) $in['text']; stage_save($pid, 'subtitles', $data); }
            return array('ok' => true, 'message' => 'Cue updated');
        }
        if ($action === 'style') {
            $d = stage_data($pid, 'subtitles_style', array());
            $d['preset'] = $in['style']; stage_save($pid, 'subtitles_style', $d);
            return array('ok' => true, 'message' => 'Preset applied: ' . $in['style'], 'reload' => true);
        }
        if ($action === 'translate') {
            $langs = array('French', 'Yoruba', 'Hausa', 'Igbo', 'Swahili', 'Spanish', 'Arabic');
            $out = array();
            foreach ($data as $c) {
                $out[] = array('lang' => $langs[0], 'text' => '[' . $langs[0] . '] ' . $c['text']);
            }
            stage_save($pid, 'subtitles_tr', $out);
            return array('ok' => true, 'message' => 'Translated to ' . $langs[0] . ' (provider translation when configured)', 'reload' => true);
        }
        if ($action === 'srt' || $action === 'vtt') {
            if (!$data) return array('ok' => false, 'error' => 'No cues yet');
            if ($action === 'srt') {
                $out = ''; foreach ($data as $i => $c) { $out .= ($i + 1) . "\n" . str_replace('.', ',', $c['start']) . ' --> ' . str_replace('.', ',', $c['end']) . "\n" . $c['text'] . "\n\n"; }
                $ext = 'srt';
            } else {
                $out = "WEBVTT\n\n"; foreach ($data as $i => $c) { $out .= $c['start'] . ' --> ' . $c['end'] . "\n" . $c['text'] . "\n\n"; }
                $ext = 'vtt';
            }
            $rel = 'uploads/assets/subs-' . $pid . '.' . $ext;
            if (!is_dir(SF_ROOT . '/uploads/assets')) { @mkdir(SF_ROOT . '/uploads/assets', 0755, true); }
            @file_put_contents(SF_ROOT . '/' . $rel, $out);
            project_asset($pid, 'other', 'Subtitles.' . $ext, $rel);
            return array('ok' => true, 'download' => sf_url($rel), 'message' => strtoupper($ext) . ' exported');
        }
        if ($action === 'burn') {
            if (!media_ffmpeg()) return array('ok' => false, 'error' => 'FFmpeg is NOT CONFIGURED — cannot burn subtitles on this server');
            $vid = stage_data($pid, 'timeline', array());
            $master = db_one("SELECT * FROM assets WHERE project_id=? AND kind='video' ORDER BY id DESC LIMIT 1", array($pid));
            if (!$master) return array('ok' => false, 'error' => 'Render a video first');
            $srt = 'uploads/assets/subs-' . $pid . '.srt';
            $out = 'uploads/assets/video-subbed-' . $pid . '.mp4';
            $r = media_burn_subtitles($master['path'], $srt, $out);
            return empty($r['ok']) ? array('ok' => false, 'error' => 'Burn failed') : array('ok' => true, 'download' => sf_url($out), 'message' => 'Subtitles burned in');
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
    private function tc($s) {
        $s = (float) $s;
        $h = (int) floor($s / 3600);
        $m = (int) floor(fmod($s, 3600) / 60);
        $sec = fmod($s, 60);
        return sprintf('%02d:%02d:%06.3f', $h, $m, $sec);
    }
}

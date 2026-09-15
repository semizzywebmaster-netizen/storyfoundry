<?php
class SFPlugin_audio_studio extends PluginBase {
    public function stage($project, $data) {
        $data = is_array($data) ? $data : array();
        $mix = isset($data['mix']) ? $data['mix'] : array('voice' => 80, 'music' => 34, 'sfx' => 26, 'master' => 0);
        $moods = array('Cinematic', 'Emotional', 'Suspense', 'Action', 'Romance', 'Comedy', 'Drama', 'Background', 'Intro', 'Outro');
        $sfx = array('Rain on zinc roof', 'Market crowd', 'Generator hum', 'Footsteps — concrete', 'Door — wooden creak', 'Danfo bus pass-by', 'Night insects', 'Cinematic riser');
        $h = '<div class="grid" style="grid-template-columns:1fr 320px;align-items:start">';
        $h .= '<div class="col">';
        $h .= '<div class="card"><h3>Music Studio</h3><div class="row wrap" style="gap:6px;margin-bottom:10px">';
        foreach ($moods as $m) { $h .= '<button class="btn xs" data-api="audio-studio.music" data-payload=\'{"project_id":' . (int) $project['id'] . ',"mood":' . json_encode($m) . '}\' data-reload="1">' . e($m) . '</button>'; }
        $h .= '</div>';
        if (!empty($data['music'])) {
            $h .= '<div class="card" style="margin:0"><b>' . e($data['music']['mood']) . ' score</b>'
                . '<audio controls src="' . e(sf_url($data['music']['path'])) . '" style="width:100%;margin-top:8px"></audio>'
                . '<div class="dim" style="font-size:11px;margin-top:6px">provider: ' . e($data['music']['provider']) . ' ' . ui_status('MOCK') . '</div></div>';
        }
        $h .= '</div>';
        $h .= '<div class="card"><h3>Sound effects</h3><div class="grid g2" style="gap:8px">';
        foreach ($sfx as $s) { $h .= '<button class="btn sm" data-api="audio-studio.sfx" data-payload=\'{"project_id":' . (int) $project['id'] . ',"kind":' . json_encode($s) . '}\' data-reload="1">🔊 ' . e($s) . '</button>'; }
        $h .= '</div>';
        if (!empty($data['sfx'])) {
            $h .= '<hr class="sep"><div class="grid g2" style="gap:8px">';
            foreach (array_slice($data['sfx'], -4) as $s) {
                $h .= '<div><div class="dim" style="font-size:11.5px;margin-bottom:4px">' . e($s['kind']) . '</div><audio controls src="' . e(sf_url($s['path'])) . '" style="width:100%"></audio></div>';
            }
            $h .= '</div>';
        }
        $h .= '</div></div>';
        $h .= '<div class="col"><div class="card"><h3>Audio mixing</h3>';
        foreach (array('voice' => 'Voice / dialogue', 'music' => 'Music bed', 'sfx' => 'Sound effects', 'master' => 'Master') as $k => $l) {
            $h .= '<div style="margin-bottom:11px"><div class="row" style="justify-content:space-between"><b style="font-size:12.5px">' . e($l) . '</b><span class="mono dim">' . (int) $mix[$k] . ' dB</span></div>'
                . '<input type="range" min="-60" max="12" value="' . (int) $mix[$k] . '" data-api="audio-studio.mix" data-payload=\'{"project_id":' . (int) $project['id'] . ',"key":' . json_encode($k) . ',"value":"__VALUE__"}\' onchange="sfApi(this.dataset.api,Object.assign(JSON.parse(this.dataset.payload),{value:this.value}))"></div>';
        }
        $h .= '<hr class="sep"><button class="btn blk gho" data-api="audio-studio.master" data-payload=\'{"project_id":' . (int) $project['id'] . '}\'>Render master mix</button>';
        $ff = media_ffmpeg();
        $h .= '<div class="dim" style="font-size:11.5px;margin-top:9px">Master mix requires FFmpeg: ' . ui_status($ff ? 'REAL' : 'NOT CONFIGURED') . '</div>';
        $h .= '</div></div></div>';
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        $data = stage_data($pid, 'audio', array());
        if ($action === 'music') {
            $mood = isset($in['mood']) ? $in['mood'] : 'Cinematic';
            $res = orchestrator_run('music', array('kind' => 'music', 'mood' => $mood, 'seconds' => 14), array(
                'project_id' => $pid, 'label' => 'Score — ' . $mood, 'inline' => true));
            if (empty($res['ok'])) return array('ok' => false, 'error' => isset($res['error']) ? $res['error'] : 'Music generation failed');
            $data['music'] = array('mood' => $mood, 'path' => $res['data']['path'], 'provider' => $res['data']['provider']);
            project_asset($pid, 'audio', 'Score — ' . $mood, $res['data']['path'], array('mood' => $mood));
            stage_save($pid, 'audio', $data);
            return array('ok' => true, 'message' => 'Score generated', 'reload' => true);
        }
        if ($action === 'sfx') {
            $kind = isset($in['kind']) ? $in['kind'] : 'Cinematic';
            $res = orchestrator_run('sfx', array('kind' => 'sfx', 'mood' => $kind, 'seconds' => 2), array(
                'project_id' => $pid, 'label' => 'SFX — ' . $kind, 'inline' => true));
            if (empty($res['ok'])) return array('ok' => false, 'error' => 'SFX generation failed');
            if (!isset($data['sfx'])) { $data['sfx'] = array(); }
            $data['sfx'][] = array('kind' => $kind, 'path' => $res['data']['path'], 'provider' => $res['data']['provider']);
            project_asset($pid, 'audio', 'SFX — ' . $kind, $res['data']['path'], array('kind' => $kind));
            stage_save($pid, 'audio', $data);
            return array('ok' => true, 'message' => 'SFX generated', 'reload' => true);
        }
        if ($action === 'mix') {
            if (!isset($data['mix'])) { $data['mix'] = array('voice' => 80, 'music' => 34, 'sfx' => 26, 'master' => 0); }
            $data['mix'][$in['key']] = (int) $in['value'];
            stage_save($pid, 'audio', $data);
            return array('ok' => true, 'message' => 'Mix updated');
        }
        if ($action === 'master') {
            if (!media_ffmpeg()) return array('ok' => false, 'error' => 'FFmpeg is NOT CONFIGURED on this server — install the media-pipeline addon or ask your host to enable FFmpeg');
            $tracks = array();
            if (!empty($data['music']['path'])) { $tracks[] = array('path' => $data['music']['path'], 'gain' => pow(10, ((int) $data['mix']['music'] - 60) / 40)); }
            $out = 'uploads/assets/master-' . $pid . '-' . sf_token(6) . '.mp3';
            $r = media_mix_audio($tracks, $out);
            if (empty($r['ok'])) return array('ok' => false, 'error' => 'Mix failed');
            return array('ok' => true, 'download' => sf_url($out), 'message' => 'Master mix rendered');
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
}

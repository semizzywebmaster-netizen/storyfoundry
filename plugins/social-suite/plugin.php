<?php
class SFPlugin_social_suite extends PluginBase {
    private $platforms = array(
        'TikTok' => array('ratio' => '9:16', 'max' => 180, 'best' => 'Tue–Thu, 18:00–22:00', 'hashtags' => 5),
        'YouTube' => array('ratio' => '16:9', 'max' => 600, 'best' => 'Fri–Sun, 17:00–21:00', 'hashtags' => 8),
        'Instagram Reels' => array('ratio' => '9:16', 'max' => 90, 'best' => 'Mon–Wed, 11:00–14:00', 'hashtags' => 10),
        'Facebook' => array('ratio' => '1:1', 'max' => 240, 'best' => 'Wed–Fri, 09:00–13:00', 'hashtags' => 3),
        'X' => array('ratio' => '16:9', 'max' => 140, 'best' => 'Mon–Fri, 08:00–10:00', 'hashtags' => 2),
        'LinkedIn' => array('ratio' => '4:3', 'max' => 180, 'best' => 'Tue–Thu, 07:30–09:30', 'hashtags' => 3),
    );
    public function stage($project, $data) {
        $data = is_array($data) ? $data : array();
        $h = '<div class="row" style="gap:8px;margin-bottom:12px">'
           . ui_generate_btn('social-suite', 'generate', 'Generate pack', 'social', array('project_id' => (int) $project['id']))
           . '<button class="btn" data-api="social-suite.optimize" data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-reload="1">🚀 Viral optimizer</button>'
           . '<button class="btn" data-api="social-suite.clips" data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-reload="1">✂️ Auto clips</button>'
           . '<a class="btn" href="' . e(project_stage_url($project['id'], 'export')) . '">Export →</a></div>';
        /* ---- viral optimizer panel (feature 34) ---- */
        $viral = stage_data($project['id'], 'viral', null);
        $h .= '<div class="card"><div class="row" style="justify-content:space-between;flex-wrap:wrap">'
            . '<div><h3 style="margin:0">🚀 Viral optimizer</h3>'
            . '<div class="muted" style="font-size:12px">Hooks, titles, hashtags and clip selection. Suggestions only — no guaranteed virality.</div></div>'
            . '<button class="btn sm" data-api="social-suite.optimize" data-payload=\'{"project_id":' . (int) $project['id'] . '}\' data-reload="1">'
            . ($viral ? 'Re-run' : 'Run optimizer') . '</button></div>';
        if ($viral) {
            $h .= '<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:12px">';
            $h .= '<div><div class="dim" style="font-size:11px;margin-bottom:5px">HOOKS</div>';
            foreach ((array) $viral['hooks'] as $hk) { $h .= '<div class="chip" style="display:block;margin-bottom:5px;text-align:left">' . e($hk) . '</div>'; }
            $h .= '</div><div><div class="dim" style="font-size:11px;margin-bottom:5px">TITLES</div>';
            foreach ((array) $viral['titles'] as $ti) { $h .= '<div class="chip" style="display:block;margin-bottom:5px;text-align:left">' . e($ti) . '</div>'; }
            $h .= '</div><div><div class="dim" style="font-size:11px;margin-bottom:5px">AUDIENCE &amp; CLIP</div>'
                . '<div class="muted" style="font-size:12px">' . e($viral['audience']) . '</div>'
                . '<div class="muted" style="font-size:12px;margin-top:4px">Best clip: <b>' . e($viral['best_clip']) . '</b></div>';
            $h .= '</div></div><div class="row wrap" style="gap:5px;margin-top:10px">';
            foreach ((array) $viral['hashtags'] as $tg) { $h .= '<span class="chip">#' . e(ltrim($tg, '#')) . '</span>'; }
            $h .= '</div><div class="row" style="gap:6px;margin-top:9px">'
                . ui_status(isset($viral['status']) ? $viral['status'] : 'MOCK')
                . '<span class="muted" style="font-size:11.5px">provider: ' . e(isset($viral['provider']) ? $viral['provider'] : 'mock') . '</span></div>';
        } else {
            $h .= '<div class="muted" style="font-size:12.5px;margin-top:8px">Not run yet.</div>';
        }
        $h .= '</div>';
        if (!$data) return $h . ui_empty('📱', 'No social pack yet.');
        foreach ($data as $plat => $pack) {
            $cfg = $this->platforms[$plat];
            $h .= '<div class="card"><div class="row" style="justify-content:space-between;flex-wrap:wrap">'
                . '<div class="row" style="gap:9px"><b style="font-size:15px">' . e($plat) . '</b><span class="chip">' . e($cfg['ratio']) . '</span><span class="chip chip-info">' . e($cfg['best']) . '</span></div>'
                . '<div class="row" style="gap:6px"><button class="btn xs" data-api="social-suite.copy" data-payload=\'{"project_id":' . (int) $project['id'] . ',"plat":' . json_encode($plat) . '}\'>Copy caption</button>'
                . '<button class="btn xs gho" data-api="social-suite.format" data-payload=\'{"project_id":' . (int) $project['id'] . ',"plat":' . json_encode($plat) . '}\' data-reload="1">Format clip</button></div></div>'
                . '<div class="grid" style="grid-template-columns:200px 1fr;gap:14px;margin-top:11px">'
                . '<div class="frame"><img src="' . e(sf_url('index.php?r=frame&seed=' . urlencode('social|' . $project['id'] . '|' . $plat) . '&style=' . urlencode($project['style']) . '&w=720&h=1280')) . '" alt="">'
                . '<div class="btm">' . e($cfg['ratio']) . '</div></div>'
                . '<div><div class="muted" style="font-size:13px">' . e($pack['caption']) . '</div>'
                . '<div class="row wrap" style="gap:5px;margin-top:9px">';
            foreach ((array) $pack['hashtags'] as $tag) { $h .= '<span class="chip">#' . e(ltrim($tag, '#')) . '</span>'; }
            $h .= '</div></div></div></div>';
        }
        $clips = stage_data($project['id'], 'clips');
        if ($clips) {
            $h .= '<div class="card"><h3>Auto clips</h3><div class="grid g3">';
            foreach ($clips as $c) {
                $h .= '<div class="card pad0" style="margin:0"><div class="frame">'
                    . '<img src="' . e(sf_url('index.php?r=frame&seed=' . urlencode($c['seed']) . '&style=' . urlencode($project['style']) . '&w=720&h=1280')) . '" alt="">'
                    . '<div class="btm">' . e($c['start']) . ' – ' . e($c['end']) . ' · ' . e($c['platform']) . '</div></div></div>';
            }
            $h .= '</div></div>';
        }
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        $ctx = project_ctx($p);
        if ($action === 'generate') {
            $out = array();
            foreach ($this->platforms as $plat => $cfg) {
                $r = orchestrator_run('social', array('kind' => 'paragraph', 'context' => $ctx), array(
                    'project_id' => $pid, 'label' => 'Social pack — ' . $plat, 'inline' => true));
                $body = !empty($r['ok']) ? (string) $r['data'] : $p['title'] . ' — a story about ' . strtolower((string) $p['theme']) . '.';
                $words = preg_split('/\s+/', strip_tags($body));
                $caption = implode(' ', array_slice($words, 0, 42));
                $tags = array();
                $pool = array('storytime', 'africanstories', 'aivideo', 'filmmaking', 'shortfilm', 'storytelling', 'naijacreatives', 'cinematic');
                for ($i = 0; $i < $cfg['hashtags']; $i++) { $tags[] = $pool[($i + crc32($plat)) % count($pool)]; }
                $out[$plat] = array('caption' => $caption, 'hashtags' => $tags, 'ratio' => $cfg['ratio'], 'best_time' => $cfg['best']);
            }
            stage_save($pid, 'social', $out);
            return array('ok' => true, 'message' => count($out) . ' platform packs generated', 'reload' => true);
        }
        if ($action === 'clips') {
            $shots = stage_data($pid, 'shots', array());
            $plats = array('TikTok', 'Instagram Reels', 'YouTube Shorts');
            $out = array(); $i = 0;
            foreach ($shots as $s) {
                if ((int) $s['duration'] > 12) continue;
                $out[] = array('seed' => 'clip|' . $pid . '|' . $s['n'], 'start' => '00:0' . ($i % 9) . ':00', 'end' => '00:0' . ($i % 9) . ':' . str_pad((string) ((int) $s['duration']), 2, '0', STR_PAD_LEFT), 'platform' => $plats[$i % 3], 'shot' => $s['n']);
                if (++$i >= 9) break;
            }
            if (!$out) { foreach (array_slice($shots, 0, 6) as $s) { $out[] = array('seed' => 'clip|' . $pid . '|' . $s['n'], 'start' => '00:00:00', 'end' => '00:00:0' . max(3, (int) $s['duration']), 'platform' => 'TikTok', 'shot' => $s['n']); } }
            stage_save($pid, 'clips', $out);
            return array('ok' => true, 'message' => count($out) . ' clips cut', 'reload' => true);
        }
        if ($action === 'copy') {
            $pack = stage_data($pid, 'social', array());
            $plat = isset($in['plat']) ? $in['plat'] : '';
            if (!isset($pack[$plat])) return array('ok' => false, 'error' => 'Pack missing');
            $text = $pack[$plat]['caption'] . "\n\n" . implode(' ', array_map(function ($t) { return '#' . $t; }, $pack[$plat]['hashtags']));
            return array('ok' => true, 'copy' => $text, 'message' => 'Caption copied');
        }
        if ($action === 'format') {
            $plat = isset($in['plat']) ? $in['plat'] : '';
            $cfg = isset($this->platforms[$plat]) ? $this->platforms[$plat] : array('ratio' => '16:9');
            $res = providers_call('image', array('seed' => 'social|' . $pid . '|' . $plat, 'style' => $p['style'],
                'w' => (strpos($cfg['ratio'], '9:16') === 0 ? 720 : 1280), 'h' => (strpos($cfg['ratio'], '9:16') === 0 ? 1280 : 720)), array());
            if (empty($res['ok'])) return array('ok' => false, 'error' => 'Formatting failed');
            project_asset($pid, 'image', $plat . ' frame (' . $cfg['ratio'] . ')', $res['data']['path'], array('platform' => $plat));
            return array('ok' => true, 'message' => $plat . ' version formatted to ' . $cfg['ratio'], 'reload' => true);
        }
        if ($action === 'optimize') {
            $r = orchestrator_run('social', array('kind' => 'paragraph', 'context' => $ctx), array(
                'project_id' => $pid, 'feature' => 'viral', 'label' => 'Viral optimizer', 'inline' => true));
            if (empty($r['ok'])) return array('ok' => false, 'error' => isset($r['error']) ? $r['error'] : 'Optimizer failed');
            $text = trim((string) $r['data']);
            /* pull hooks, titles and hashtags out of the model text; top up deterministically */
            $hooks = array(); $titles = array(); $tags = array();
            foreach (preg_split('/[\r\n]+/', $text) as $line) {
                $line = trim(preg_replace('/^[-*\d\.\)\s]+/', '', strip_tags($line)));
                if (strlen($line) < 8) continue;
                if (preg_match_all('/#([A-Za-z0-9_]{3,30})/', $line, $m)) { foreach ($m[1] as $t) { $tags[] = strtolower($t); } }
                $clean = trim(preg_replace('/#[A-Za-z0-9_]{3,30}/', '', $line));
                if (strlen($clean) < 8) continue;
                if (count($hooks) < 3 && (preg_match('/hook|open|first 3|attention/i', $clean) || count($hooks) < 1)) { $hooks[] = $clean; }
                elseif (count($titles) < 3) { $titles[] = $clean; }
            }
            $pool = array('storytime', 'africanstories', 'aivideo', 'filmmaking', 'shortfilm', 'storytelling',
                'naijacreatives', 'cinema', 'creator', 'trending');
            $i = 0;
            while (count($tags) < 8 && $i < 40) { $t = $pool[($i + crc32($p['title'])) % count($pool)]; if (!in_array($t, $tags, true)) { $tags[] = $t; } $i++; }
            while (count($hooks) < 3) { $hooks[] = 'Watch what happens when ' . strtolower((string) $p['theme']) . ' turns everything upside down.'; }
            while (count($titles) < 3) { $titles[] = $p['title'] . ' — ' . ucfirst(strtolower((string) $p['genre'])) . ' short'; }
            $clips = stage_data($pid, 'clips', array());
            $pick = null;
            if ($clips) { $pick = is_array($clips) ? $clips[0] : null; }
            $out = array(
                'hooks' => array_slice($hooks, 0, 3),
                'titles' => array_slice($titles, 0, 3),
                'hashtags' => array_slice(array_values(array_unique($tags)), 0, 10),
                'audience' => trim((string) $p['audience']) . ' · ' . trim((string) $p['culture']) . ' audiences',
                'best_clip' => $pick ? (isset($pick['label']) ? $pick['label'] : (isset($pick['title']) ? $pick['title'] : 'Clip 1')) : 'Generate clips first',
                'notes' => $text,
                'provider' => isset($r['provider']) ? $r['provider'] : '',
                'status' => isset($r['status']) ? $r['status'] : 'MOCK',
                'generated_at' => db_now(),
            );
            stage_save($pid, 'viral', $out);
            project_log_activity($pid, 'ran the viral optimizer', '🚀');
            return array('ok' => true, 'message' => 'Optimizer suggestions ready', 'reload' => true);
        }
        return array('ok' => false, 'error' => 'Unknown action');
    }
}

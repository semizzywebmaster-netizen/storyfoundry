<?php
class SFPlugin_export_suite extends PluginBase {
    private function items($pid) {
        return array(
            'video' => array('label' => 'Master video (MP4)', 'icon' => '🎬', 'ext' => 'mp4', 'note' => 'Requires FFmpeg or a video provider'),
            'script' => array('label' => 'Script / manuscript', 'icon' => '📄', 'ext' => 'md'),
            'shots' => array('label' => 'Shot list', 'icon' => '🎞️', 'ext' => 'csv'),
            'subs' => array('label' => 'Subtitles', 'icon' => '💬', 'ext' => 'srt'),
            'frames' => array('label' => 'Scene frames', 'icon' => '🖼️', 'ext' => 'zip'),
            'audio' => array('label' => 'Audio stems', 'icon' => '🎵', 'ext' => 'zip'),
            'bundle' => array('label' => 'Full project bundle', 'icon' => '📦', 'ext' => 'json', 'note' => 'Everything, for backup or hand-off'),
        );
    }
    public function stage($project, $data) {
        $pid = (int) $project['id'];
        $master = db_one("SELECT * FROM assets WHERE project_id=? AND kind='video' ORDER BY id DESC LIMIT 1", array($pid));
        $h = '<div class="banner info"><span>ℹ</span><div>Exports are generated on demand and served through <b>signed, expiring links</b>. '
            . 'Video and ZIP exports need FFmpeg — status: ' . ui_status(media_ffmpeg() ? 'REAL' : 'NOT CONFIGURED') . '</div></div>';
        $h .= '<div class="grid g3">';
        foreach ($this->items($pid) as $k => $it) {
            $ready = $k !== 'video' || $master;
            $h .= '<div class="card" style="margin:0"><div class="row" style="justify-content:space-between">'
                . '<div class="row" style="gap:8px"><span style="font-size:20px">' . $it['icon'] . '</span><b style="font-size:13.5px">' . e($it['label']) . '</b></div>'
                . '<span class="chip">' . e($it['ext']) . '</span></div>'
                . (!empty($it['note']) ? '<div class="dim" style="font-size:11.5px;margin:7px 0">' . e($it['note']) . '</div>' : '<div style="height:7px"></div>')
                . '<button class="btn sm blk ' . ($ready ? 'pri' : 'gho') . '" data-api="export-suite.make" data-payload=\'{"project_id":' . $pid . ',"kind":' . json_encode($k) . '}\'>' . ($ready ? 'Prepare export' : 'Render first') . '</button>'
                . '</div>';
        }
        $h .= '</div>';
        $ex = db_all('SELECT * FROM exports WHERE project_id=? ORDER BY id DESC LIMIT 10', array($pid));
        if ($ex) {
            $h .= '<div class="card"><h3>Recent exports</h3><div class="tbl" style="border:0"><table><thead><tr><th>File</th><th>Kind</th><th>Size</th><th>When</th><th>Link</th></tr></thead><tbody>';
            foreach ($ex as $e) {
                $link = storage_signed_url($e['path'], 3600);
                $h .= '<tr><td class="mono" style="font-size:11.5px">' . e(basename($e['path'])) . '</td><td>' . e($e['kind']) . '</td>'
                    . '<td class="mono dim">' . e(sf_bytes($e['size'])) . '</td><td class="dim" style="font-size:11.5px">' . e(ui_time_ago($e['created_at'])) . '</td>'
                    . '<td><a class="btn xs" href="' . e($link) . '">Download (1h)</a></td></tr>';
            }
            $h .= '</tbody></table></div></div>';
        }
        return $h;
    }
    public function api($action, $in) {
        $pid = (int) (isset($in['project_id']) ? $in['project_id'] : 0);
        $p = db_one('SELECT * FROM projects WHERE id=?', array($pid));
        if (!$p) return array('ok' => false, 'error' => 'Project not found');
        if ($action !== 'make') return array('ok' => false, 'error' => 'Unknown action');
        $kind = isset($in['kind']) ? $in['kind'] : 'script';
        if (!is_dir(SF_ROOT . '/uploads/exports')) { @mkdir(SF_ROOT . '/uploads/exports', 0755, true); }
        $stub = 'uploads/exports/' . $p['id'] . '-' . $kind;
        $path = null;
        switch ($kind) {
            case 'script':
                $story = stage_data($pid, 'story', array());
                $md = '# ' . $p['title'] . "\n\n";
                if (isset($story['chapters'])) { foreach ($story['chapters'] as $i => $c) { $md .= '## ' . (isset($c['title']) ? $c['title'] : 'Chapter ' . ($i + 1)) . "\n\n" . $c['text'] . "\n\n"; } }
                $path = $stub . '.md'; @file_put_contents(SF_ROOT . '/' . $path, $md); break;
            case 'shots':
                $shots = stage_data($pid, 'shots', array());
                $csv = "Scene,Shot,Type,Angle,Movement,Lens,Subject,Action,Duration,Lighting\n";
                foreach ($shots as $s) { $csv .= implode(',', array_map(function ($v) { return '"' . str_replace('"', '""', (string) $v) . '"'; },
                    array($s['scene'], $s['n'], $s['type'], $s['angle'], $s['move'], $s['lens'], $s['subject'], $s['action'], $s['duration'], $s['lighting']))) . "\n"; }
                $path = $stub . '.csv'; @file_put_contents(SF_ROOT . '/' . $path, $csv); break;
            case 'subs':
                $subs = stage_data($pid, 'subtitles', array());
                $out = ''; foreach ($subs as $i => $c) { $out .= ($i + 1) . "\n" . str_replace('.', ',', $c['start']) . ' --> ' . str_replace('.', ',', $c['end']) . "\n" . $c['text'] . "\n\n"; }
                $path = $stub . '.srt'; @file_put_contents(SF_ROOT . '/' . $path, $out); break;
            case 'frames':
            case 'audio':
            case 'bundle':
                if (!media_ffmpeg() && $kind !== 'bundle') return array('ok' => false, 'error' => 'FFmpeg is NOT CONFIGURED — ZIP packaging is unavailable on this server');
                $bundle = array('project' => $p, 'stages' => array());
                foreach (array('idea', 'story', 'characters', 'scenes', 'shots', 'images', 'voice', 'audio', 'video', 'subtitles', 'thumbnail', 'social') as $st) {
                    $bundle['stages'][$st] = stage_data($pid, $st);
                }
                $path = $stub . '.json'; @file_put_contents(SF_ROOT . '/' . $path, json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); break;
            case 'video':
                $master = db_one("SELECT * FROM assets WHERE project_id=? AND kind='video' ORDER BY id DESC LIMIT 1", array($pid));
                if (!$master) return array('ok' => false, 'error' => 'No rendered video yet — render from the Timeline first');
                $path = $master['path']; break;
            default: return array('ok' => false, 'error' => 'Unknown export kind');
        }
        $size = is_file(SF_ROOT . '/' . ltrim($path, '/')) ? filesize(SF_ROOT . '/' . ltrim($path, '/')) : 0;
        db_exec('INSERT INTO exports (project_id,user_id,kind,path,size,created_at) VALUES (?,?,?,?,?,?)', array($pid, auth_id(), $kind, $path, $size, db_now()));
        audit('export', $kind . ' for project #' . $pid, 'info');
        return array('ok' => true, 'download' => storage_signed_url($path, 3600), 'message' => 'Export ready (link valid 1 hour)');
    }
}

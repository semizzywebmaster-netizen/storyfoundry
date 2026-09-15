<?php
class SFPlugin_media_pipeline extends PluginBase {
    public function stage($project, $data) {
        $ms = media_status();
        $h = '<div class="grid g2">';
        $h .= '<div class="card"><h3>Rendering engine</h3>'
            . '<div class="kv"><span>FFmpeg</span>' . ui_status($ms['ffmpeg']) . '</div>'
            . '<div class="kv"><span>Binary</span><span class="mono dim" style="font-size:11.5px">' . e($ms['ffmpeg_path'] ?: 'not found') . '</span></div>'
            . '<div class="kv"><span>GD</span>' . ui_status($ms['gd']) . '</div>'
            . '<div class="kv"><span>ZIP</span>' . ui_status(class_exists('ZipArchive') ? 'REAL' : 'NOT CONFIGURED') . '</div>'
            . '<div class="kv"><span>Version</span><span class="mono dim" style="font-size:11.5px">' . e($ms['version'] ?: '—') . '</span></div>'
            . '<div class="banner ' . ($ms['ffmpeg'] ? 'ok' : 'warn') . '" style="margin-top:10px"><span>' . ($ms['ffmpeg'] ? '✓' : '⚠') . '</span>'
            . '<div style="font-size:12px">' . e($ms['note']) . '</div></div></div>';
        $h .= '<div class="card"><h3>What this addon unlocks</h3>'
            . '<div class="kv"><span>Render timeline to MP4</span>' . ui_status($ms['ffmpeg']) . '</div>'
            . '<div class="kv"><span>Mix audio stems</span>' . ui_status($ms['ffmpeg']) . '</div>'
            . '<div class="kv"><span>Burn subtitles</span>' . ui_status($ms['ffmpeg']) . '</div>'
            . '<div class="kv"><span>Concatenate clips</span>' . ui_status($ms['ffmpeg']) . '</div>'
            . '<div class="dim" style="font-size:11.5px;margin-top:10px">Without FFmpeg, STORYFOUNDRY still plans, generates and stores every asset — '
            . 'but it will <b>never</b> claim a video file was produced when it was not.</div></div>';
        $h .= '</div>';
        return $h;
    }
    public function admin($input) {
        if ($_POST && sf_post('act') === 'mp_probe') {
            csrf_check();
            setting_set('ffmpeg_path', (string) sf_post('ffmpeg_path'));
            flash('FFmpeg path saved: ' . (media_ffmpeg() ? 'detected' : 'still not found'), media_ffmpeg() ? 'ok' : 'warn');
            sf_redirect(sf_url('index.php?r=admin&p=media-pipeline'));
        }
        $ms = media_status();
        $h = '<div class="card"><h3>FFmpeg configuration</h3>'
            . '<div class="kv"><span>Detected</span>' . ui_status($ms['ffmpeg']) . '</div>'
            . '<div class="kv"><span>Path</span><span class="mono dim" style="font-size:11.5px">' . e($ms['ffmpeg_path'] ?: 'not found') . '</span></div>'
            . '<div class="kv"><span>Version</span><span class="mono dim" style="font-size:11.5px">' . e($ms['version'] ?: '—') . '</span></div>'
            . '<div class="kv"><span>GD</span>' . ui_status($ms['gd']) . '</div>'
            . ui_form_open(sf_url('index.php?r=admin&p=media-pipeline')) . '<input type="hidden" name="act" value="mp_probe">'
            . '<label class="fl" style="margin-top:10px">Override FFmpeg path (optional)<input name="ffmpeg_path" value="' . e(setting('ffmpeg_path', '')) . '" placeholder="/usr/local/bin/ffmpeg"></label>'
            . '<button class="btn pri" style="margin-top:10px">Save &amp; re-probe</button></form>'
            . '<div class="banner ' . ($ms['ffmpeg'] ? 'ok' : 'warn') . '" style="margin-top:12px"><span>' . ($ms['ffmpeg'] ? '✓' : '⚠') . '</span>'
            . '<div style="font-size:12px">' . e($ms['note']) . '</div></div>'
            . '<div class="dim" style="font-size:11.5px;margin-top:10px">On shared cPanel hosting FFmpeg is often unavailable. '
            . 'Options: ask the host to enable it, upload a static ffmpeg binary and set the path above, or run rendering off-server and upload the result.</div></div>';
        return $h;
    }
    public function menu() { return array(array('route' => 'media-pipeline', 'label' => 'Media pipeline', 'icon' => '⚙️', 'group' => 'admin', 'perm' => 'admin')); }
}

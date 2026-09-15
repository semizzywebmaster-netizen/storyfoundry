<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/** FFmpeg media pipeline (feature 29). Detects availability; never fakes success. */
function media_ffmpeg() {
    static $bin = null;
    if ($bin !== null) return $bin;
    $bin = '';
    if (function_exists('exec')) {
        $try = array('ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg');
        foreach ($try as $b) {
            $o = array(); $rc = 1;
            @exec(escapeshellcmd($b) . ' -version 2>&1', $o, $rc);
            if ($rc === 0) { $bin = $b; break; }
        }
    }
    return $bin;
}
function media_status() {
    $ff = media_ffmpeg();
    $version = '';
    if ($ff && function_exists('exec')) {
        $out = array(); $code = 0;
        @exec(escapeshellcmd($ff) . ' -version 2>&1', $out, $code);
        if (!$code && !empty($out[0]) && preg_match('/version\s+([\w\.\-]+)/i', $out[0], $m)) { $version = $m[1]; }
    }
    return array(
        'ffmpeg' => $ff ? 'REAL' : 'NOT CONFIGURED',
        'ffmpeg_path' => $ff,
        'version' => $version,
        'gd' => function_exists('imagecreatetruecolor') ? 'REAL' : 'NOT CONFIGURED',
        'exec' => function_exists('exec') ? 'REAL' : 'NOT CONFIGURED',
        'note' => $ff ? '' : 'Many cPanel plans disable FFmpeg. Install the media-pipeline addon and/or ask your host to enable it; renders stay queued until then.'
    );
}
function media_run($args) {
    $ff = media_ffmpeg();
    if (!$ff) return array('ok' => false, 'error' => 'FFmpeg is not available on this server', 'configured' => false);
    if (!function_exists('exec')) return array('ok' => false, 'error' => 'exec() disabled by host', 'configured' => false);
    $cmd = escapeshellcmd($ff) . ' ' . $args . ' 2>&1';
    $out = array(); $rc = 1;
    @exec($cmd, $out, $rc);
    return array('ok' => $rc === 0, 'rc' => $rc, 'output' => implode("\n", $out));
}
/** Image sequence + audio -> mp4 (requires ffmpeg). */
function media_render_video($frames, $audio, $outfile, $fps = 24) {
    if (!media_ffmpeg()) return array('ok' => false, 'error' => 'FFmpeg not available', 'configured' => false);
    $list = SF_ROOT . '/storage/cache/seq-' . sf_token(8) . '.txt';
    $lines = array();
    foreach ($frames as $f) { $lines[] = "file '" . SF_ROOT . '/' . ltrim($f, '/') . "'"; $lines[] = 'duration ' . number_format(1 / $fps, 4); }
    if ($frames) { $lines[] = "file '" . SF_ROOT . '/' . ltrim(end($frames), '/') . "'"; }
    file_put_contents($list, implode("\n", $lines));
    $args = '-y -f concat -safe 0 -i ' . escapeshellarg($list);
    if ($audio && is_file(SF_ROOT . '/' . ltrim($audio, '/'))) { $args .= ' -i ' . escapeshellarg(SF_ROOT . '/' . ltrim($audio, '/')); }
    $args .= ' -vf "scale=trunc(iw/2)*2:trunc(ih/2)*2" -c:v libx264 -pix_fmt yuv420p -shortest ' . escapeshellarg(SF_ROOT . '/' . ltrim($outfile, '/'));
    $r = media_run($args);
    @unlink($list);
    return $r;
}
/** Burn subtitles into a video (requires ffmpeg). */
function media_burn_subtitles($video, $srt, $outfile) {
    if (!media_ffmpeg()) return array('ok' => false, 'error' => 'FFmpeg not available', 'configured' => false);
    $args = '-y -i ' . escapeshellarg(SF_ROOT . '/' . ltrim($video, '/'))
          . ' -vf subtitles=' . escapeshellarg(SF_ROOT . '/' . ltrim($srt, '/'))
          . ' -c:a copy ' . escapeshellarg(SF_ROOT . '/' . ltrim($outfile, '/'));
    return media_run($args);
}
/** Mix voice + music + sfx into one track (requires ffmpeg). */
function media_mix_audio($tracks, $outfile) {
    if (!media_ffmpeg()) return array('ok' => false, 'error' => 'FFmpeg not available', 'configured' => false);
    $inputs = ''; $filters = array();
    foreach ($tracks as $i => $t) { $inputs .= ' -i ' . escapeshellarg(SF_ROOT . '/' . ltrim($t['path'], '/')); $filters[] = '[' . $i . ':a]volume=' . (float) $t['gain']; }
    $args = '-y ' . $inputs . ' -filter_complex "' . implode(',', $filters) . ' amix=inputs=' . count($tracks) . ':normalize=0" '
          . escapeshellarg(SF_ROOT . '/' . ltrim($outfile, '/'));
    return media_run($args);
}

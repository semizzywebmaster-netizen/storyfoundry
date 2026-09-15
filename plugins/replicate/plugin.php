<?php
class SFPlugin_replicate extends PluginBase {
    public function boot() {
        providers_register(array(
            'pkey' => 'replicate', 'name' => 'Replicate (images / video)', 'capability' => 'video',
            'status' => 'NOT CONFIGURED', 'priority' => 20,
            'models' => ["minimax/video-01", "stability-ai/stable-video-diffusion"],
            'docs' => 'https://replicate.com/docs',
            'config_fields' => array('api_key' => 'Replicate API token', 'model' => 'Video model (owner/name)'),
            'call' => function ($input, $cfg) {
                return $this->call_provider($input, $cfg);
            },
        ));
    }
    public function call_provider($input, $cfg) {
        $key = isset($cfg['api_key']) ? trim((string) $cfg['api_key']) : '';
        if (!$key) return array('ok' => false, 'error' => 'Replicate (images / video) is NOT CONFIGURED (missing API key)');
        $model = isset($cfg['model']) && $cfg['model'] ? $cfg['model'] : 'minimax/video-01';
        $isUrl = strpos($model, '/') !== false;
        $image = isset($input['image_path']) ? $input['image_path'] : '';
        $payload = array('input' => array('prompt' => isset($input['prompt']) ? $input['prompt'] : ''));
        if ($image) { $payload['input']['image'] = sf_url($image); }
        $endpoint = $isUrl ? 'https://api.replicate.com/v1/models/' . $model . '/predictions' : 'https://api.replicate.com/v1/predictions';
        if (!$isUrl) { $payload['version'] = $model; }
        $r = sf_http($endpoint, array(
            'method' => 'POST', 'headers' => array('Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json', 'Prefer' => 'wait'),
            'body' => json_encode($payload), 'timeout' => 90));
        if (empty($r['ok'])) return array('ok' => false, 'error' => 'Replicate error: ' . (isset($r['error']) ? $r['error'] : 'request failed'));
        $j = json_decode($r['body'], true);
        $url = null;
        if (isset($j['output'])) { $url = is_array($j['output']) ? end($j['output']) : $j['output']; }
        if (!$url && !empty($j['urls']['get'])) {
            for ($i = 0; $i < 10; $i++) {
                sleep(3);
                $p = sf_http($j['urls']['get'], array('headers' => array('Authorization' => 'Bearer ' . $key)));
                if (!empty($p['ok'])) {
                    $pj = json_decode($p['body'], true);
                    if (!empty($pj['status']) && $pj['status'] === 'succeeded') { $url = is_array($pj['output']) ? end($pj['output']) : $pj['output']; break; }
                    if (!empty($pj['status']) && in_array($pj['status'], array('failed', 'canceled'), true)) { return array('ok' => false, 'error' => 'Replicate prediction ' . $pj['status']); }
                }
            }
        }
        if (!$url) return array('ok' => false, 'error' => 'Replicate did not finish in time - retry the job');
        $vid = sf_http($url, array('timeout' => 180));
        if (empty($vid['ok'])) return array('ok' => false, 'error' => 'Could not download the rendered video');
        $st = sf_store_bytes($vid['body'], 'mp4');
        if (empty($st['ok'])) return array('ok' => false, 'error' => $st['error']);
        return array('ok' => true, 'data' => array('path' => $st['path'], 'provider' => 'replicate', 'status' => 'REAL'), 'usage' => array('videos' => 1));
    }
}

<?php
class SFPlugin_runway extends PluginBase {
    public function boot() {
        providers_register(array(
            'pkey' => 'runway', 'name' => 'Runway (image to video)', 'capability' => 'video',
            'status' => 'NOT CONFIGURED', 'priority' => 15,
            'models' => ["gen3a_turbo", "gen4_turbo"],
            'docs' => 'https://docs.dev.runwayml.com',
            'config_fields' => array('api_key' => 'Runway API key', 'model' => 'Model (gen3a_turbo)'),
            'call' => function ($input, $cfg) {
                return $this->call_provider($input, $cfg);
            },
        ));
    }
    public function call_provider($input, $cfg) {
        $key = isset($cfg['api_key']) ? trim((string) $cfg['api_key']) : '';
        if (!$key) return array('ok' => false, 'error' => 'Runway (image to video) is NOT CONFIGURED (missing API key)');
        $image = isset($input['image_path']) ? $input['image_path'] : '';
        $model = isset($cfg['model']) && $cfg['model'] ? $cfg['model'] : 'gen3a_turbo';
        $payload = array('model' => $model,
            'promptText' => isset($input['prompt']) ? $input['prompt'] : '',
            'ratio' => '1280:720', 'duration' => 5);
        if ($image) { $payload['promptImage'] = sf_url($image); }
        $r = sf_http('https://api.dev.runwayml.com/v1/image_to_video', array(
            'method' => 'POST',
            'headers' => array('Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json', 'X-Runway-Version' => '2024-11-06'),
            'body' => json_encode($payload), 'timeout' => 90));
        if (empty($r['ok'])) return array('ok' => false, 'error' => 'Runway error: ' . (isset($r['error']) ? $r['error'] : 'request failed'));
        $j = json_decode($r['body'], true);
        if (empty($j['id'])) return array('ok' => false, 'error' => 'Runway returned no task id');
        for ($i = 0; $i < 15; $i++) {
            sleep(4);
            $p = sf_http('https://api.dev.runwayml.com/v1/tasks/' . rawurlencode($j['id']), array(
                'headers' => array('Authorization' => 'Bearer ' . $key, 'X-Runway-Version' => '2024-11-06')));
            if (!empty($p['ok'])) {
                $pj = json_decode($p['body'], true);
                if (!empty($pj['status']) && $pj['status'] === 'SUCCEEDED' && !empty($pj['output'])) {
                    $vid = sf_http(is_array($pj['output']) ? reset($pj['output']) : $pj['output'], array('timeout' => 180));
                    if (empty($vid['ok'])) return array('ok' => false, 'error' => 'Could not download the rendered video');
                    $st = sf_store_bytes($vid['body'], 'mp4');
                    if (empty($st['ok'])) return array('ok' => false, 'error' => $st['error']);
                    return array('ok' => true, 'data' => array('path' => $st['path'], 'provider' => 'runway', 'status' => 'REAL'), 'usage' => array('videos' => 1));
                }
                if (!empty($pj['status']) && in_array($pj['status'], array('FAILED', 'CANCELLED'), true)) {
                    return array('ok' => false, 'error' => 'Runway task ' . $pj['status']);
                }
            }
        }
        return array('ok' => false, 'error' => 'Runway did not finish in time - retry the job');
    }
}

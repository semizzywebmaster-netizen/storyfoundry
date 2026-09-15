<?php
class SFPlugin_stability extends PluginBase {
    public function boot() {
        providers_register(array(
            'pkey' => 'stability', 'name' => 'Stability AI (images)', 'capability' => 'image',
            'status' => 'NOT CONFIGURED', 'priority' => 20,
            'models' => ["sd3.5-large", "sd3.5-medium"],
            'docs' => 'https://platform.stability.ai/docs/api-reference',
            'config_fields' => array('api_key' => 'Stability API key', 'model' => 'Model (sd3.5-large)'),
            'call' => function ($input, $cfg) {
                return $this->call_provider($input, $cfg);
            },
        ));
    }
    public function call_provider($input, $cfg) {
        $key = isset($cfg['api_key']) ? trim((string) $cfg['api_key']) : '';
        if (!$key) return array('ok' => false, 'error' => 'Stability AI (images) is NOT CONFIGURED (missing API key)');
        $prompt = isset($input['prompt']) ? (string) $input['prompt'] : '';
        $model = isset($cfg['model']) && $cfg['model'] ? $cfg['model'] : 'sd3.5-large';
        $w = isset($input['w']) ? (int) $input['w'] : 1280; $h = isset($input['h']) ? (int) $input['h'] : 720;
        $ratio = ($w >= $h) ? '16:9' : '9:16';
        $r = sf_http('https://api.stability.ai/v2beta/stable-image/generate/sd3', array(
            'method' => 'POST',
            'headers' => array('Authorization' => 'Bearer ' . $key, 'Accept' => 'image/*'),
            'body' => array('prompt' => $prompt, 'output_format' => 'png', 'aspect_ratio' => $ratio, 'model' => $model),
            'timeout' => 180));
        if (empty($r['ok'])) return array('ok' => false, 'error' => 'Stability error: ' . (isset($r['error']) ? $r['error'] : 'request failed'));
        $st = sf_store_bytes($r['body'], 'png');
        if (empty($st['ok'])) return array('ok' => false, 'error' => $st['error']);
        return array('ok' => true, 'data' => array('path' => $st['path'], 'provider' => 'stability', 'status' => 'REAL'), 'usage' => array('images' => 1));
    }
}

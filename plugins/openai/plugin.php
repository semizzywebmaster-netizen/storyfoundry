<?php
class SFPlugin_openai extends PluginBase {
    public function boot() {
        providers_register(array(
            'pkey' => 'openai', 'name' => 'OpenAI (text + images)', 'capability' => 'text',
            'status' => 'NOT CONFIGURED', 'priority' => 10,
            'models' => ["gpt-4o-mini", "gpt-4o", "dall-e-3"],
            'docs' => 'https://platform.openai.com/docs/api-reference',
            'config_fields' => array('api_key' => 'OpenAI API key', 'model' => 'Model (gpt-4o-mini)', 'image_model' => 'Image model (dall-e-3)'),
            'call' => function ($input, $cfg) {
                return $this->call_provider($input, $cfg);
            },
        ));
    }
    public function call_provider($input, $cfg) {
        $key = isset($cfg['api_key']) ? trim((string) $cfg['api_key']) : '';
        if (!$key) return array('ok' => false, 'error' => 'OpenAI (text + images) is NOT CONFIGURED (missing API key)');
        $prompt = isset($input['prompt']) ? (string) $input['prompt'] : '';
        $kind = isset($input['kind']) ? $input['kind'] : 'text';
        $model = isset($cfg['model']) && $cfg['model'] ? $cfg['model'] : 'gpt-4o-mini';
        $sys = 'You are a screenwriting and production assistant for STORYFOUNDRY. Return exactly what is asked, no commentary.';
        if ($kind === 'ideas' || $kind === 'story' || $kind === 'characters' || $kind === 'scenes' || $kind === 'shots' || $kind === 'rewrite' || $kind === 'social') {
            $payload = array('model' => $model, 'messages' => array(
                array('role' => 'system', 'content' => $sys),
                array('role' => 'user', 'content' => $prompt),
            ), 'temperature' => 0.8);
            if (in_array($kind, array('ideas', 'characters', 'scenes', 'shots'), true)) {
                $payload['response_format'] = array('type' => 'json_object');
            }
            $r = sf_http('https://api.openai.com/v1/chat/completions', array(
                'method' => 'POST', 'headers' => array('Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'),
                'body' => json_encode($payload), 'timeout' => 120));
            if (empty($r['ok'])) return array('ok' => false, 'error' => 'OpenAI error: ' . (isset($r['error']) ? $r['error'] : 'request failed'));
            $j = json_decode($r['body'], true);
            $txt = isset($j['choices'][0]['message']['content']) ? $j['choices'][0]['message']['content'] : '';
            $data = json_decode($txt, true);
            if (json_last_error() !== JSON_ERROR_NONE) { $data = $txt; }
            return array('ok' => true, 'data' => $data, 'usage' => array('tokens' => isset($j['usage']['total_tokens']) ? (int) $j['usage']['total_tokens'] : 0));
        }
        $im = isset($cfg['image_model']) && $cfg['image_model'] ? $cfg['image_model'] : 'dall-e-3';
        $payload = array('model' => $im, 'prompt' => $prompt, 'n' => 1, 'size' => '1792x1024');
        $r = sf_http('https://api.openai.com/v1/images/generations', array(
            'method' => 'POST', 'headers' => array('Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'),
            'body' => json_encode($payload), 'timeout' => 180));
        if (empty($r['ok'])) return array('ok' => false, 'error' => 'OpenAI image error: ' . (isset($r['error']) ? $r['error'] : 'request failed'));
        $j = json_decode($r['body'], true);
        $url = isset($j['data'][0]['url']) ? $j['data'][0]['url'] : '';
        if (!$url) return array('ok' => false, 'error' => 'OpenAI returned no image URL');
        $img = sf_http($url, array('timeout' => 120));
        if (empty($img['ok'])) return array('ok' => false, 'error' => 'Could not download generated image');
        $st = sf_store_bytes($img['body'], 'png');
        if (empty($st['ok'])) return array('ok' => false, 'error' => $st['error']);
        return array('ok' => true, 'data' => array('path' => $st['path'], 'provider' => 'openai', 'status' => 'REAL'), 'usage' => array('images' => 1));
    }
}

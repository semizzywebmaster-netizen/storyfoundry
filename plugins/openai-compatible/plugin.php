<?php
/**
 * OPENAI-COMPATIBLE PROVIDER ADAPTER
 * -----------------------------------------------------------------------------
 * Lets an administrator connect any service that speaks the OpenAI REST schema:
 * OpenRouter, Groq, Together, Fireworks, DeepSeek, Mistral, Perplexity, Ollama,
 * LM Studio, vLLM, or an internal proxy — by entering a base URL, key and model.
 *
 * Registers three providers (text / image / voice). Each stays NOT CONFIGURED
 * until its key is filled in at Admin → AI Providers, and production never
 * silently falls back to a mock.
 */
class SFPlugin_openai_compatible extends PluginBase {

    public function boot() {
        $providers = array(
            'oai.text'  => array('cap' => 'text',  'name' => 'OpenAI-compatible — text',  'model' => 'gpt-4o-mini',      'prio' => 20),
            'oai.image' => array('cap' => 'image', 'name' => 'OpenAI-compatible — images', 'model' => 'dall-e-3',        'prio' => 21),
            'oai.voice' => array('cap' => 'voice', 'name' => 'OpenAI-compatible — voice',  'model' => 'tts-1',           'prio' => 22),
        );
        foreach ($providers as $pkey => $p) {
            providers_register(array(
                'pkey' => $pkey,
                'name' => $p['name'],
                'capability' => $p['cap'],
                'status' => 'NOT CONFIGURED',
                'priority' => $p['prio'],
                'models' => array(),
                'docs' => 'Any OpenAI-compatible endpoint. Set base URL, key and model in Admin → AI Providers.',
                'config_fields' => array(
                    'base_url' => 'Base URL (e.g. https://openrouter.ai/api/v1)',
                    'api_key'  => 'API key',
                    'model'    => 'Model id (e.g. ' . $p['model'] . ')',
                ),
                'call' => function ($input, $cfg) use ($p) {
                    return $this->call_provider($p['cap'], $input, $cfg, $p['model']);
                },
            ));
        }
    }

    /** Normalise the base URL the admin typed (accept host or full /v1 path). */
    private function base($cfg) {
        $b = isset($cfg['base_url']) ? trim((string) $cfg['base_url']) : '';
        if ($b === '') { $b = 'https://openrouter.ai/api/v1'; }
        $b = rtrim($b, '/');
        if (!preg_match('#/v\d+$#', $b) && substr($b, -3) !== '/v1') { $b .= '/v1'; }
        return $b;
    }

    private function post($url, $payload, $key, $timeout = 120) {
        $headers = array('Content-Type: application/json');
        if ($key !== '') { $headers[] = 'Authorization: Bearer ' . $key; }
        $r = sf_http($url, array(
            'method' => 'POST', 'headers' => $headers,
            'body' => json_encode($payload), 'timeout' => $timeout,
        ));
        if (empty($r['ok'])) {
            return array('ok' => false, 'error' => 'Provider request failed: ' . (isset($r['error']) ? $r['error'] : 'HTTP error'));
        }
        $j = json_decode(isset($r['body']) ? $r['body'] : '', true);
        if (!is_array($j)) { return array('ok' => false, 'error' => 'Provider returned non-JSON response'); }
        if (isset($j['error'])) {
            $msg = is_array($j['error']) && isset($j['error']['message']) ? $j['error']['message'] : (string) $j['error'];
            return array('ok' => false, 'error' => 'Provider error: ' . sf_sub($msg, 0, 200));
        }
        return array('ok' => true, 'json' => $j);
    }

    public function call_provider($cap, $input, $cfg, $defaultModel) {
        $key   = isset($cfg['api_key']) ? trim((string) $cfg['api_key']) : '';
        $model = isset($cfg['model']) && $cfg['model'] ? trim((string) $cfg['model']) : $defaultModel;
        $base  = $this->base($cfg);
        $label = 'OpenAI-compatible (' . $cap . ')';

        if ($key === '' && $cap !== 'voice') {
            /* Ollama and LM Studio run without a key, so allow an empty key for local endpoints */
            $local = preg_match('#(localhost|127\.0\.0\.1|\[::1\]|192\.168\.|10\.|172\.(1[6-9]|2\d|3[01])\.)#i', $base);
            if (!$local) {
                return array('ok' => false, 'error' => $label . ' is NOT CONFIGURED (missing API key)');
            }
        }

        /* ------------------------------------------------------------- text */
        if ($cap === 'text') {
            $prompt = isset($input['prompt']) ? (string) $input['prompt'] : '';
            if ($prompt === '') { return array('ok' => false, 'error' => 'Empty prompt'); }
            $kind = isset($input['kind']) ? $input['kind'] : 'text';
            $wantsJson = in_array($kind, array('ideas', 'characters', 'scenes', 'shots', 'story', 'social', 'music', 'sfx'), true);
            $payload = array(
                'model' => $model,
                'messages' => array(
                    array('role' => 'system', 'content' => 'You are a screenwriting and production assistant for STORYFOUNDRY. Return exactly what is asked and nothing else.'),
                    array('role' => 'user', 'content' => $prompt),
                ),
                'temperature' => 0.8,
            );
            if ($wantsJson) { $payload['response_format'] = array('type' => 'json_object'); }
            $r = $this->post($base . '/chat/completions', $payload, $key);
            if (empty($r['ok'])) { return $r; }
            $j = $r['json'];
            $txt = isset($j['choices'][0]['message']['content']) ? $j['choices'][0]['message']['content'] : '';
            if ($txt === '' && isset($j['choices'][0]['text'])) { $txt = $j['choices'][0]['text']; }
            if ($txt === '') { return array('ok' => false, 'error' => $label . ' returned an empty completion'); }
            $data = json_decode($txt, true);
            if (json_last_error() !== JSON_ERROR_NONE) { $data = $txt; }
            return array('ok' => true, 'data' => $data,
                'usage' => array('tokens' => isset($j['usage']['total_tokens']) ? (int) $j['usage']['total_tokens'] : 0));
        }

        /* ------------------------------------------------------------ image */
        if ($cap === 'image') {
            $prompt = isset($input['prompt']) ? (string) $input['prompt'] : '';
            if ($prompt === '') { return array('ok' => false, 'error' => 'Empty image prompt'); }
            $size = isset($input['size']) ? (string) $input['size'] : '1024x1024';
            if (!preg_match('/^\d+x\d+$/', $size)) { $size = '1024x1024'; }
            $payload = array('model' => $model, 'prompt' => $prompt, 'n' => 1, 'size' => $size);
            $r = $this->post($base . '/images/generations', $payload, $key, 180);
            if (empty($r['ok'])) { return $r; }
            $j = $r['json'];
            $bytes = null;
            if (!empty($j['data'][0]['b64_json'])) {
                $bytes = base64_decode($j['data'][0]['b64_json'], true);
            } elseif (!empty($j['data'][0]['url'])) {
                $img = sf_http($j['data'][0]['url'], array('timeout' => 120));
                if (empty($img['ok'])) { return array('ok' => false, 'error' => 'Could not download the generated image'); }
                $bytes = isset($img['body']) ? $img['body'] : null;
            } elseif (!empty($j[0]['url'])) {
                $img = sf_http($j[0]['url'], array('timeout' => 120));
                if (empty($img['ok'])) { return array('ok' => false, 'error' => 'Could not download the generated image'); }
                $bytes = isset($img['body']) ? $img['body'] : null;
            }
            if (!$bytes) { return array('ok' => false, 'error' => $label . ' returned no image data'); }
            $st = sf_store_bytes($bytes, 'png');
            if (empty($st['ok'])) { return array('ok' => false, 'error' => isset($st['error']) ? $st['error'] : 'Could not store image'); }
            return array('ok' => true,
                'data' => array('path' => $st['path'], 'provider' => 'oai.image', 'status' => 'REAL'),
                'usage' => array('images' => 1));
        }

        /* ------------------------------------------------------------ voice */
        $text = isset($input['text']) ? (string) $input['text'] : '';
        if ($text === '') { return array('ok' => false, 'error' => 'Empty narration text'); }
        $voice = 'alloy';
        if (isset($input['voice']) && is_array($input['voice']) && !empty($input['voice']['id'])) { $voice = (string) $input['voice']['id']; }
        $payload = array('model' => $model, 'input' => sf_sub($text, 0, 4000), 'voice' => $voice);
        $headers = array('Content-Type: application/json');
        if ($key !== '') { $headers[] = 'Authorization: Bearer ' . $key; }
        $r = sf_http($base . '/audio/speech', array(
            'method' => 'POST', 'headers' => $headers, 'body' => json_encode($payload), 'timeout' => 180));
        if (empty($r['ok'])) { return array('ok' => false, 'error' => $label . ' TTS failed: ' . (isset($r['error']) ? $r['error'] : 'request failed')); }
        $body = isset($r['body']) ? $r['body'] : '';
        if (strlen($body) < 512) { return array('ok' => false, 'error' => $label . ' returned no audio (this endpoint may not support /audio/speech)'); }
        $st = sf_store_bytes($body, 'mp3');
        if (empty($st['ok'])) { return array('ok' => false, 'error' => isset($st['error']) ? $st['error'] : 'Could not store audio'); }
        return array('ok' => true,
            'data' => array('path' => $st['path'], 'provider' => 'oai.voice', 'status' => 'REAL', 'duration' => 0),
            'usage' => array('chars' => strlen($text)));
    }

    /** Admin → Addons → openai-compatible: quick setup hints. */
    public function admin($input) {
        $rows = '';
        foreach (providers_effective() as $p) {
            if (strpos($p['pkey'], 'oai.') !== 0) { continue; }
            $rows .= '<div class="kv"><span>' . e($p['name']) . '</span>' . ui_status($p['status']) . '</div>';
        }
        return '<div class="card"><h3>OpenAI-compatible provider</h3>' . $rows .
            '<div class="dim" style="font-size:12px;margin-top:10px">Enter a base URL, key and model in ' .
            '<a href="' . e(sf_url('index.php?r=admin&p=providers')) . '">Admin → AI Providers</a>. ' .
            'Works with OpenRouter, Groq, Together, Fireworks, DeepSeek, Mistral, Ollama and LM Studio.</div></div>';
    }
}

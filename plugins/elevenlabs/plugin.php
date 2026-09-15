<?php
class SFPlugin_elevenlabs extends PluginBase {
    public function boot() {
        providers_register(array(
            'pkey' => 'elevenlabs', 'name' => 'ElevenLabs (voices)', 'capability' => 'voice',
            'status' => 'NOT CONFIGURED', 'priority' => 20,
            'models' => ["eleven_multilingual_v2"],
            'docs' => 'https://elevenlabs.io/docs/api-reference',
            'config_fields' => array('api_key' => 'ElevenLabs API key', 'voice_id' => 'Default voice id'),
            'call' => function ($input, $cfg) {
                return $this->call_provider($input, $cfg);
            },
        ));
    }
    public function call_provider($input, $cfg) {
        $key = isset($cfg['api_key']) ? trim((string) $cfg['api_key']) : '';
        if (!$key) return array('ok' => false, 'error' => 'ElevenLabs (voices) is NOT CONFIGURED (missing API key)');
        $text = isset($input['text']) ? (string) $input['text'] : '';
        if (!$text) return array('ok' => false, 'error' => 'No text to speak');
        $vid = isset($input['voice']['id']) && $input['voice']['id'] ? $input['voice']['id'] : (isset($cfg['voice_id']) && $cfg['voice_id'] ? $cfg['voice_id'] : '21m00Tcm4TlvDq8ikWAM');
        $r = sf_http('https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode($vid), array(
            'method' => 'POST',
            'headers' => array('xi-api-key' => $key, 'Content-Type' => 'application/json', 'Accept' => 'audio/mpeg'),
            'body' => json_encode(array('text' => $text, 'model_id' => 'eleven_multilingual_v2',
                'voice_settings' => array('stability' => 0.45, 'similarity_boost' => 0.75))),
            'timeout' => 180));
        if (empty($r['ok'])) return array('ok' => false, 'error' => 'ElevenLabs error: ' . (isset($r['error']) ? $r['error'] : 'request failed'));
        $st = sf_store_bytes($r['body'], 'mp3');
        if (empty($st['ok'])) return array('ok' => false, 'error' => $st['error']);
        return array('ok' => true, 'data' => array('path' => $st['path'], 'provider' => 'elevenlabs', 'status' => 'REAL', 'duration' => 0), 'usage' => array('chars' => strlen($text)));
    }
}

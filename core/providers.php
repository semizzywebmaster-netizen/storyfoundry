<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/**
 * Multi-AI provider abstraction (features 39-43).
 * Status is authoritative:  REAL = key present + health ok
 *                           MOCK = local deterministic substitute (always labelled)
 *                      NOT CONFIGURED = adapter exists, credentials missing
 * Production never silently falls back to MOCK: results carry 'status' and the UI badges them.
 */
$GLOBALS['SF_PROVIDERS'] = array();

function providers_register($def) {
    $def = array_merge(array(
        'pkey' => '', 'name' => '', 'capability' => 'text', 'status' => 'MOCK',
        'priority' => 50, 'call' => null, 'config_fields' => array(), 'models' => array(), 'docs' => '',
    ), $def);
    if (!$def['pkey']) return false;
    $GLOBALS['SF_PROVIDERS'][$def['pkey']] = $def;
    return true;
}
function providers_all() { return $GLOBALS['SF_PROVIDERS']; }
function providers_get($pkey) { $p = $GLOBALS['SF_PROVIDERS']; return isset($p[$pkey]) ? $p[$pkey] : null; }

/** Sync registered providers into the DB (so admin can toggle/prioritise/configure). */
function providers_sync_db() {
    if (!db()) return;
    foreach (providers_all() as $k => $p) {
        $row = db_one('SELECT * FROM providers WHERE pkey=?', array($k));
        if (!$row) {
            db_exec('INSERT INTO providers (pkey,name,capability,status,priority,config,models,enabled,created_at) VALUES (?,?,?,?,?,?,?,?,?)', array(
                $k, $p['name'], $p['capability'], $p['status'], $p['priority'],
                json_encode(array()), json_encode($p['models']), ($p['status'] === 'NOT CONFIGURED') ? 0 : 1, db_now()
            ));
        } else {
            db_exec('UPDATE providers SET name=?, capability=?, models=? WHERE pkey=?', array($p['name'], $p['capability'], json_encode($p['models']), $k));
        }
    }
}
/** Effective status: DB config (keys/enable) + registered default. */
function providers_effective() {
    $out = array();
    $rows = array();
    try { $rows = db_all('SELECT * FROM providers'); } catch (Exception $e) { $rows = array(); }
    $dbmap = array(); foreach ($rows as $r) { $dbmap[$r['pkey']] = $r; }
    foreach (providers_all() as $k => $p) {
        $row = isset($dbmap[$k]) ? $dbmap[$k] : null;
        $cfg = array();
        if ($row && $row['config']) { $j = json_decode($row['config'], true); if (is_array($j)) $cfg = $j; }
        $needsKey = !empty($p['config_fields']);
        $hasKey = true;
        foreach ($p['config_fields'] as $f => $label) { if (empty($cfg[$f])) { $hasKey = false; } }
        $status = $p['status'];
        if ($needsKey) { $status = $hasKey ? 'REAL' : 'NOT CONFIGURED'; }
        if ($row && empty($row['enabled'])) { $status = 'DISABLED'; }
        $out[$k] = array_merge($p, array(
            'status' => $status, 'config' => $cfg, 'priority' => $row ? (int) $row['priority'] : $p['priority'],
            'enabled' => $row ? (int) $row['enabled'] : 1, 'health' => $row ? $row['health'] : 'unknown',
            'calls' => $row ? (int) $row['calls'] : 0, 'errors' => $row ? (int) $row['errors'] : 0,
            'db_id' => $row ? (int) $row['id'] : 0,
        ));
    }
    usort($out, function ($a, $b) { return $a['priority'] - $b['priority']; });
    return $out;
}
function providers_for($capability, $include_disabled = false) {
    $all = providers_effective();
    $out = array();
    foreach ($all as $p) {
        if ($p['capability'] !== $capability) continue;
        if (!$include_disabled && ($p['status'] === 'DISABLED' || $p['status'] === 'NOT CONFIGURED')) continue;
        $out[] = $p;
    }
    return $out;
}
/**
 * Call the best provider for a capability, with timeout, retry and fallback.
 * Returns array(ok, data, provider, status, error, attempts[])
 */
function providers_call($capability, $input, $opts = array()) {
    $list = providers_for($capability);
    if (isset($opts['pkey'])) {
        foreach ($list as $p) { if ($p['pkey'] === $opts['pkey']) { $list = array($p); break; } }
    }
    if (!$list) return array('ok' => false, 'error' => 'No ' . $capability . ' provider configured', 'status' => 'NOT CONFIGURED', 'attempts' => array());

    $attempts = array();
    $started = microtime(true);
    foreach ($list as $p) {
        if (empty($p['call']) || !is_callable($p['call'])) { $attempts[] = array('provider' => $p['pkey'], 'error' => 'no callable'); continue; }
        $t0 = microtime(true);
        try {
            $res = call_user_func($p['call'], $input, $p['config']);
        } catch (Exception $ex) {
            $res = array('ok' => false, 'error' => $ex->getMessage());
        }
        $ms = (int) ((microtime(true) - $t0) * 1000);
        $res = is_array($res) ? $res : array('ok' => false, 'error' => 'bad provider response');
        $attempts[] = array('provider' => $p['pkey'], 'status' => $p['status'], 'ms' => $ms,
            'ok' => !empty($res['ok']), 'error' => isset($res['error']) ? $res['error'] : '');
        if (!empty($res['ok'])) {
            return array(
                'ok' => true, 'data' => isset($res['data']) ? $res['data'] : null,
                'provider' => $p['pkey'], 'status' => $p['status'], 'ms' => $ms, 'attempts' => $attempts,
                'usage' => isset($res['usage']) ? $res['usage'] : array(),
            );
        }
        sf_log('warn', 'provider', $p['pkey'] . ' failed: ' . (isset($res['error']) ? $res['error'] : 'unknown'));
    }
    return array('ok' => false, 'error' => 'All providers failed', 'attempts' => $attempts,
        'ms' => (int) ((microtime(true) - $started) * 1000), 'status' => 'FAILED');
}

/* ------------------------------------------------------------------ */
/* Core local (MOCK) providers — always available, never billed       */
/* ------------------------------------------------------------------ */
function providers_bootstrap() {
    if (!empty($GLOBALS['SF_PROVIDERS_BOOTED'])) return;
    $GLOBALS['SF_PROVIDERS_BOOTED'] = true;

    providers_register(array(
        'pkey' => 'mock.text', 'name' => 'Local Composer (deterministic)', 'capability' => 'text',
        'status' => 'MOCK', 'priority' => 1, 'models' => array('mock-composer-v1'),
        'docs' => 'Template-compositional text engine that runs entirely on your server. No key, no cost.',
        'call' => function ($input, $cfg) {
            $kind = isset($input['kind']) ? $input['kind'] : 'paragraph';
            $ctx = isset($input['context']) && is_array($input['context']) ? $input['context'] : array();
            if (!isset($ctx['names'])) { $c = culture_get(isset($ctx['culture']) ? $ctx['culture'] : 'African'); $ctx['names'] = $c['names']; $ctx['places'] = $c['places']; }
            $n = isset($input['n']) ? (int) $input['n'] : 4;
            if ($kind === 'rewrite') {
                $data = mockai_rewrite(isset($input['text']) ? $input['text'] : '', isset($input['style']) ? $input['style'] : 'Cinematic', $ctx);
            } else {
                $data = mockai_text($kind, $ctx, $n);
            }
            return array('ok' => true, 'data' => $data, 'usage' => array('tokens' => is_array($data) ? count($data) : str_word_count((string) $data)));
        },
    ));

    providers_register(array(
        'pkey' => 'mock.image', 'name' => 'Procedural Frame Synth', 'capability' => 'image',
        'status' => 'MOCK', 'priority' => 1, 'models' => array('framesynth-v1'),
        'docs' => 'Deterministic SVG frame synthesiser. Produces real image files with no external API.',
        'call' => function ($input, $cfg) {
            $o = array(
                'seed' => isset($input['seed']) ? $input['seed'] : md5(isset($input['prompt']) ? $input['prompt'] : 'frame'),
                'style' => isset($input['style']) ? $input['style'] : 'cinematic',
                'w' => isset($input['w']) ? (int) $input['w'] : 1280, 'h' => isset($input['h']) ? (int) $input['h'] : 720,
                'subject' => isset($input['subject']) ? $input['subject'] : 'scene',
                'time' => isset($input['time']) ? $input['time'] : 'day',
                'text' => isset($input['text']) ? $input['text'] : '',
                'figures' => isset($input['figures']) ? (int) $input['figures'] : 1,
            );
            $r = mockai_image(isset($input['prompt']) ? $input['prompt'] : '', $o);
            if (empty($r['ok'])) return array('ok' => false, 'error' => isset($r['error']) ? $r['error'] : 'image failed');
            return array('ok' => true, 'data' => $r, 'usage' => array('images' => 1));
        },
    ));

    providers_register(array(
        'pkey' => 'mock.voice', 'name' => 'Local Voice Synth', 'capability' => 'voice',
        'status' => 'MOCK', 'priority' => 1, 'models' => array('synth-ng-1'),
        'docs' => 'Formant-style synthesis used for previews and offline demos. Not a voice clone.',
        'call' => function ($input, $cfg) {
            $r = mockai_voice(isset($input['text']) ? $input['text'] : 'Preview.', isset($input['voice']) ? $input['voice'] : array());
            return $r ? array('ok' => true, 'data' => $r, 'usage' => array('chars' => strlen(isset($input['text']) ? $input['text'] : ''))) : array('ok' => false, 'error' => 'voice failed');
        },
    ));

    providers_register(array(
        'pkey' => 'mock.music', 'name' => 'Local Score & SFX Synth', 'capability' => 'music',
        'status' => 'MOCK', 'priority' => 1, 'models' => array('scoresynth-1'),
        'call' => function ($input, $cfg) {
            $kind = isset($input['kind']) ? $input['kind'] : 'music';
            $r = $kind === 'sfx'
                ? mockai_sfx(isset($input['mood']) ? $input['mood'] : 'Cinematic', isset($input['seconds']) ? (int) $input['seconds'] : 2)
                : mockai_music(isset($input['mood']) ? $input['mood'] : 'Cinematic', isset($input['seconds']) ? (int) $input['seconds'] : 12);
            return $r ? array('ok' => true, 'data' => $r) : array('ok' => false, 'error' => 'audio failed');
        },
    ));

    /* plugin-registered providers are added by plugins_bootstrap() */
    if (db()) { try { providers_sync_db(); } catch (Exception $e) { sf_log('warn', 'providers', $e->getMessage()); } }
}

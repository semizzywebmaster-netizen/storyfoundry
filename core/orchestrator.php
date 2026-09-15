<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/**
 * AI ORCHESTRATOR (features 39, 40, 41, 43)
 * Contract: check feature → check plan → check limits → reserve credits
 *           → route to provider (with fallback) → consume on success | release on failure
 */
function orchestrator_capability($feature) {
    $map = array(
        'idea' => 'text', 'story' => 'text', 'rewrite' => 'text', 'doctor' => 'text', 'characters' => 'text',
        'scenes' => 'text', 'director' => 'text', 'storyboard' => 'image', 'social' => 'text', 'viral' => 'text',
        'clips' => 'text', 'image' => 'image', 'thumb' => 'image', 'voice' => 'voice', 'music' => 'music',
        'sfx' => 'music', 'video' => 'video', 'render' => 'video', 'subtitles' => 'text', 'export' => 'video',
        'translate' => 'text', 'batch' => 'text',
    );
    $m = apply_filters('orchestrator.capabilities', $map);
    return isset($m[$feature]) ? $m[$feature] : 'text';
}
function orchestrator_preflight($user, $feature, $cost) {
    if (!feature_enabled($feature)) return array('ok' => false, 'error' => 'This feature is disabled or in maintenance');
    if (!feature_allows_plan($feature, $user['plan'])) return array('ok' => false, 'error' => 'This feature needs a higher plan');
    if (!usage_within_limit($user['id'], $feature)) return array('ok' => false, 'error' => 'Daily limit reached for this feature');
    if ($cost > 0 && !credits_check($user['id'], $cost)) return array('ok' => false, 'error' => 'Not enough credits (need ' . $cost . ')');
    return array('ok' => true);
}
/**
 * Run a generation.
 * $opts: inline (bool), project_id, label, cost, capability, payload, callback(job, result)
 */
function orchestrator_run($feature, $input, $opts = array()) {
    $user = auth_user();
    if (!$user) return array('ok' => false, 'error' => 'Login required');
    $cost = isset($opts['cost']) ? (int) $opts['cost'] : feature_cost($feature);
    $label = isset($opts['label']) ? $opts['label'] : ucfirst(str_replace('_', ' ', $feature));
    $cap = isset($opts['capability']) ? $opts['capability'] : orchestrator_capability($feature);

    $pre = orchestrator_preflight($user, $feature, $cost);
    if (!$pre['ok']) return array('ok' => false, 'error' => $pre['error']);

    $res = credits_reserve($user['id'], $cost, $label);
    if (!$res['ok']) return array('ok' => false, 'error' => $res['error']);

    $job_id = jobs_create($user['id'], isset($opts['project_id']) ? (int) $opts['project_id'] : 0,
        $feature, $cap, $label, $input, $cost, isset($opts['priority']) ? (int) $opts['priority'] : 5);
    db_exec('UPDATE reservations SET job_id=? WHERE id=?', array($job_id, $res['id']));
    sf_log('info', 'orchestrator', 'route ' . $feature . ' -> ' . $cap . ' (job #' . $job_id . ', ' . $cost . 'cr reserved)');

    $inline = isset($opts['inline']) ? (bool) $opts['inline'] : ($cap === 'text');
    if ($inline) {
        $job = jobs_get($job_id);
        $out = orchestrator_execute($job);
        return array('ok' => !empty($out['ok']), 'job_id' => $job_id, 'status' => $job ? $job['feature'] : '',
            'data' => isset($out['data']) ? $out['data'] : null, 'error' => isset($out['error']) ? $out['error'] : null,
            'provider' => isset($out['provider']) ? $out['provider'] : null, 'inline' => true);
    }
    /* heavy work: queue for cron, but opportunistically burn a few seconds now */
    $budget = (int) sf_config('app.queue_inline_seconds', 20);
    if ($budget > 0) { jobs_run_due(min(12, $budget), 1); }
    return array('ok' => true, 'job_id' => $job_id, 'queued' => true, 'label' => $label,
        'note' => 'Job queued. cPanel cron (cron.php) processes the queue; the web request also drains a little on each hit.');
}

/**
 * Enqueue work owned by an addon: sf_queue($feature, 'plugin.method', $input, $opts)
 * Honours the same contract as orchestrator_run: preflight -> reserve -> execute -> consume|release.
 */
function sf_queue($feature, $handler, $input, $opts = array()) {
    $user = auth_user();
    if (!$user) return array('ok' => false, 'error' => 'Login required');
    $cost = isset($opts['cost']) ? (int) $opts['cost'] : feature_cost($feature);
    $label = isset($opts['label']) ? $opts['label'] : ucfirst(str_replace('_', ' ', $feature));
    $cap = isset($opts['capability']) ? $opts['capability'] : orchestrator_capability($feature);

    $pre = orchestrator_preflight($user, $feature, $cost);
    if (!$pre['ok']) return array('ok' => false, 'error' => $pre['error']);

    $res = credits_reserve($user['id'], $cost, $label);
    if (!$res['ok']) return array('ok' => false, 'error' => $res['error']);

    $input['handler'] = $handler;
    $job_id = jobs_create($user['id'], isset($opts['project_id']) ? (int) $opts['project_id'] : 0,
        $feature, $cap, $label, $input, $cost, isset($opts['priority']) ? (int) $opts['priority'] : 5);
    db_exec('UPDATE reservations SET job_id=? WHERE id=?', array($job_id, $res['id']));
    sf_log('info', 'orchestrator', 'queue ' . $feature . ' -> ' . $handler . ' (job #' . $job_id . ', ' . $cost . 'cr reserved)');

    if (!empty($opts['inline'])) {
        $job = jobs_get($job_id);
        $out = orchestrator_execute($job);
        return array('ok' => !empty($out['ok']), 'job_id' => $job_id, 'data' => isset($out['data']) ? $out['data'] : null,
            'error' => isset($out['error']) ? $out['error'] : null, 'provider' => isset($out['provider']) ? $out['provider'] : null, 'inline' => true);
    }
    $budget = (int) sf_config('app.queue_inline_seconds', 20);
    if ($budget > 0) { jobs_run_due(min(10, $budget), 1); }
    return array('ok' => true, 'job_id' => $job_id, 'queued' => true, 'label' => $label,
        'note' => 'Queued. cron.php drains the queue; web requests drain a little too.');
}

/** Execute a job row (shared by inline + cron). */
function orchestrator_execute($job) {
    $job_id = (int) $job['id'];
    $input = array();
    if (!empty($job['payload'])) { $j = json_decode($job['payload'], true); if (is_array($j)) $input = $j; }
    jobs_start($job_id);
    jobs_progress($job_id, 10);

    $cap = $job['capability'] ? $job['capability'] : orchestrator_capability($job['feature']);
    $handler = isset($input['handler']) ? (string) $input['handler'] : '';
    $hplugin = null; $hmethod = '';
    if ($handler && strpos($handler, '.') !== false) {
        $bits = explode('.', $handler, 2);
        $hplugin = plugin_instance($bits[0]);
        $hmethod = 'job_' . $bits[1];
        if (!$hplugin || !method_exists($hplugin, $hmethod)) {
            $err = 'Addon handler unavailable: ' . $handler;
            jobs_fail($job_id, $err);
            $reservation = db_one('SELECT * FROM reservations WHERE job_id=? AND status=?', array($job_id, 'held'));
            credits_release($reservation);
            notify($job['user_id'], 'err', 'Job failed', $job['label'] . ' failed — credits were released');
            return array('ok' => false, 'error' => $err);
        }
        $res = $hplugin->$hmethod($input, $job);
        if (empty($res['provider'])) { $res['provider'] = $handler; }
        if (empty($res['status'])) { $res['status'] = 'REAL'; }
    } else {
        $res = providers_call($cap, $input, array());
    }
    jobs_progress($job_id, 80);

    $reservation = db_one('SELECT * FROM reservations WHERE job_id=? AND status=?', array($job_id, 'held'));
    if (!empty($res['ok'])) {
        jobs_complete($job_id, $res['data']);
        credits_consume($reservation);
        usage_log($job['user_id'], $res['provider'], isset($res['data']['model']) ? $res['data']['model'] : 'default',
            $job['feature'], (int) $job['cost'], isset($res['ms']) ? (int) $res['ms'] : 0, 'ok',
            isset($res['usage']['tokens']) ? (int) $res['usage']['tokens'] : 0);
        notify($job['user_id'], 'ok', 'Generation complete', $job['label'] . ' finished · ' . $job['cost'] . ' credits used');
        sf_log('info', 'worker', 'job #' . $job_id . ' completed via ' . $res['provider'] . ' (' . $res['status'] . ')');
        if ($hplugin && method_exists($hplugin, 'job_done')) {
            try { $hplugin->job_done($hmethod, $job, $res); } catch (Exception $e) { sf_log('error', 'worker', 'addon job_done failed: ' . $e->getMessage()); }
        }
        do_action('job.completed', $job, $res);
        return array('ok' => true, 'data' => $res['data'], 'provider' => $res['provider'], 'status' => $res['status']);
    }
    $err = isset($res['error']) ? $res['error'] : 'Unknown provider error';
    /* fallback exhausted → release the reservation and fail clean */
    jobs_fail($job_id, $err);
    credits_release($reservation);
    usage_log($job['user_id'], isset($res['attempts'][0]['provider']) ? $res['attempts'][0]['provider'] : '', 'default',
        $job['feature'], 0, isset($res['ms']) ? (int) $res['ms'] : 0, 'error');
    notify($job['user_id'], 'err', 'Job failed', $job['label'] . ' failed — ' . $job['cost'] . ' credits were released');
    sf_log('error', 'worker', 'job #' . $job_id . ' failed: ' . $err);
    do_action('job.failed', $job, $res);
    return array('ok' => false, 'error' => $err, 'attempts' => isset($res['attempts']) ? $res['attempts'] : array());
}

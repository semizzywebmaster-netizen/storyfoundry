<?php
if (!defined('SF_ROOT')) { http_response_code(403); exit('Direct access denied'); }
/** Job queue (features 45, 46, 47). MySQL-backed; processed inline and/or by cron.php. */
function jobs_create($user_id, $project_id, $feature, $capability, $label, $payload, $cost = 0, $priority = 5) {
    return db_insert('INSERT INTO jobs (user_id,project_id,feature,capability,label,payload,cost,status,progress,priority,attempts,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', array(
        (int) $user_id, (int) $project_id, $feature, $capability, $label,
        json_encode($payload), (int) $cost, 'queued', 0, (int) $priority, 0, db_now()
    ));
}
function jobs_get($id) { return db_one('SELECT * FROM jobs WHERE id=?', array((int) $id)); }
function jobs_start($id, $provider = '') {
    db_exec('UPDATE jobs SET status=?, provider=?, started_at=? WHERE id=?', array('processing', $provider, db_now(), (int) $id));
}
function jobs_progress($id, $pct) {
    db_exec('UPDATE jobs SET progress=? WHERE id=?', array(max(0, min(100, (int) $pct)), (int) $id));
}
function jobs_complete($id, $result = null) {
    db_exec('UPDATE jobs SET status=?, progress=100, result=?, finished_at=? WHERE id=?',
        array('completed', json_encode($result), db_now(), (int) $id));
}
function jobs_fail($id, $error) {
    db_exec('UPDATE jobs SET status=?, error=?, finished_at=? WHERE id=?', array('failed', (string) $error, db_now(), (int) $id));
}
function jobs_cancel($id) { db_exec('UPDATE jobs SET status=?, finished_at=? WHERE id=?', array('cancelled', db_now(), (int) $id)); }
function jobs_retry($id) {
    $j = jobs_get($id); if (!$j) return false;
    db_exec('UPDATE jobs SET status=?, progress=0, error=NULL, attempts=attempts+1, finished_at=NULL WHERE id=?', array('queued', (int) $id));
    return true;
}
function jobs_claim_next($capability = null) {
    $sql = 'SELECT * FROM jobs WHERE status=? ' . ($capability ? 'AND capability=? ' : '') . 'ORDER BY priority ASC, id ASC LIMIT 1';
    $p = $capability ? array('queued', $capability) : array('queued');
    return db_one($sql, $p);
}
function jobs_for_user($user_id, $limit = 20) {
    return db_all('SELECT * FROM jobs WHERE user_id=? ORDER BY id DESC LIMIT ' . (int) $limit, array((int) $user_id));
}
function jobs_active($user_id) {
    return db_all("SELECT * FROM jobs WHERE user_id=? AND status IN ('queued','processing') ORDER BY id DESC LIMIT 6", array((int) $user_id));
}
function jobs_stats() {
    $rows = db_all("SELECT status, COUNT(*) c FROM jobs GROUP BY status");
    $s = array('queued' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0, 'cancelled' => 0);
    foreach ($rows as $r) { $s[$r['status']] = (int) $r['c']; }
    return $s;
}
/** Process queued jobs for up to $seconds (used by cron.php and opportunistically on web requests). */
function jobs_run_due($seconds = 15, $limit = 5) {
    $t0 = microtime(true);
    $done = 0;
    while ($done < $limit && (microtime(true) - $t0) < $seconds) {
        $job = jobs_claim_next();
        if (!$job) break;
        orchestrator_execute($job);
        $done++;
    }
    return $done;
}

<?php
if (sf_is_ajax()) {
    $active = jobs_active(auth_id());
    ob_start();
    foreach ($active as $j) {
        echo '<div style="margin-bottom:11px"><div class="row" style="justify-content:space-between"><div style="font-size:13px;font-weight:600">' . e($j['label']) . '</div>'
            . '<div class="row" style="gap:8px">' . ui_status($j['status']) . '<span class="dim mono">' . (int) $j['progress'] . '%</span></div></div>'
            . '<div class="bar" style="margin-top:5px"><i style="width:' . (int) $j['progress'] . '%"></i></div></div>';
    }
    if (!$active) { echo '<div class="empty"><div class="ico">✅</div>No jobs running.</div>'; }
    sf_json(array('ok' => true, 'html' => ob_get_clean(), 'active' => count($active)));
}
$uid = auth_id();
if (sf_get('retry')) { jobs_retry(sf_int(sf_get('retry'))); flash('Job requeued', 'ok'); sf_redirect(sf_url('index.php?r=jobs')); }
if (sf_get('cancel')) {
    $j = jobs_get(sf_int(sf_get('cancel')));
    if ($j && $j['user_id'] == $uid) {
        $res = db_one('SELECT * FROM reservations WHERE job_id=? AND status=?', array($j['id'], 'held'));
        jobs_cancel($j['id']); credits_release($res);
        flash('Job cancelled — reservation released', 'ok');
    }
    sf_redirect(sf_url('index.php?r=jobs'));
}
$jobs = jobs_for_user($uid, 40); $stats = jobs_stats();
?>
<h1>Jobs & Queue</h1>
<p class="muted" style="margin-top:-4px">BullMQ-style job model on MySQL: progress, retries, cancellation and automatic reservation release.</p>
<div class="grid g4" style="margin-bottom:14px">
  <?php echo ui_kpi($stats['queued'], 'Queued'); ?><?php echo ui_kpi($stats['processing'], 'Processing'); ?>
  <?php echo ui_kpi($stats['completed'], 'Completed'); ?><?php echo ui_kpi($stats['failed'], 'Failed'); ?>
</div>
<div class="banner info"><span>ℹ</span><div>Queue driver: <b>MySQL + cron</b> (cPanel has no Redis/BullMQ). Add <code>*/5 * * * * php -q <?php echo e(SF_ROOT); ?>/cron.php token=<?php echo e(sf_config('app.cron_token')); ?></code> in cPanel → Cron Jobs. Web requests also drain a little on each hit.</div></div>
<div class="card">
  <div class="tbl"><table>
    <thead><tr><th>Job</th><th>Capability</th><th>Provider</th><th>Progress</th><th>Status</th><th>Cost</th><th>Started</th><th></th></tr></thead>
    <tbody>
    <?php if (!$jobs): ?><tr><td colspan="8"><div class="empty">No jobs yet.</div></td></tr><?php endif; ?>
    <?php foreach ($jobs as $j): ?>
      <tr>
        <td><b style="font-size:12.5px"><?php echo e($j['label']); ?></b><?php if ($j['error']): ?><div class="dim" style="font-size:11px;color:var(--err)"><?php echo e(substr($j['error'], 0, 120)); ?></div><?php endif; ?></td>
        <td><span class="chip"><?php echo e($j['capability']); ?></span></td>
        <td class="mono dim" style="font-size:11.5px"><?php echo e($j['provider'] ?: '—'); ?></td>
        <td style="min-width:110px"><div class="bar"><i style="width:<?php echo (int) $j['progress']; ?>%"></i></div><div class="dim mono" style="font-size:10px"><?php echo (int) $j['progress']; ?>%</div></td>
        <td><?php echo ui_status($j['status']); ?></td>
        <td class="mono"><?php echo (int) $j['cost']; ?> cr</td>
        <td class="dim" style="font-size:11.5px"><?php echo ui_time_ago($j['created_at']); ?></td>
        <td class="row" style="gap:4px">
          <?php if ($j['status'] === 'failed'): ?><a class="btn xs pri" href="<?php echo e(sf_url('index.php?r=jobs&retry=' . $j['id'])); ?>">Retry</a><?php endif; ?>
          <?php if (in_array($j['status'], array('queued', 'processing'), true)): ?><a class="btn xs gho" href="<?php echo e(sf_url('index.php?r=jobs&cancel=' . $j['id'])); ?>">Cancel</a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<div class="card">
  <h3>Credit contract</h3>
  <p class="muted" style="font-size:13px">Every job runs <b>check → reserve → execute → consume</b>. On failure the reservation is released and credits return to your balance. Nothing is charged for a failed generation.</p>
  <?php
  $me = auth_user();
  $held = db_all('SELECT * FROM reservations WHERE user_id=? AND status=?', array($uid, 'held'));
  $heldCr = (int) db_val('SELECT IFNULL(SUM(amount),0) FROM reservations WHERE user_id=? AND status=?', array($uid, 'held'), 0);
  ?>
  <div class="kv"><span class="k">Active reservations</span><b><?php echo count($held); ?> (<?php echo $heldCr; ?> cr held)</b></div>
  <div class="kv"><span class="k">Reserved on your account</span><b><?php echo (int) ($me ? $me['reserved'] : 0); ?> cr</b></div>
</div>

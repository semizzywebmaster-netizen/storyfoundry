<?php
if (sf_get('read')) { db_exec('UPDATE notifications SET `read`=1 WHERE user_id=?', array(auth_id())); sf_redirect(sf_url('index.php?r=notifications')); }
$rows = db_all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 60', array(auth_id()));
?>
<div class="row" style="align-items:flex-end;margin-bottom:14px"><div><h1>Notifications</h1><p class="muted" style="margin:0">Generation, payment, credit, subscription and collaboration alerts.</p></div>
<span class="spacer"></span><a class="btn sm" href="<?php echo e(sf_url('index.php?r=notifications&read=1')); ?>">Mark all read</a></div>
<?php if (!$rows): echo ui_empty('🔔', 'No notifications.'); else: foreach ($rows as $n): ?>
  <div class="banner <?php echo $n['type'] === 'err' ? 'err' : ($n['type'] === 'ok' ? '' : 'info'); ?>">
    <span><?php echo $n['type'] === 'err' ? '🔴' : ($n['type'] === 'ok' ? '🟢' : '🔵'); ?></span>
    <div style="flex:1"><b><?php echo e($n['title']); ?></b><div class="muted" style="font-size:12.5px"><?php echo e($n['body']); ?></div>
    <div class="dim" style="font-size:10.5px;margin-top:4px"><?php echo ui_time_ago($n['created_at']); ?></div></div>
  </div>
<?php endforeach; endif; ?>

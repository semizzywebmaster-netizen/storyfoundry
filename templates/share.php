<?php
$tok = isset($token) ? $token : '';
$sh = db_one('SELECT * FROM shares WHERE token=?', array($tok));
if (!$sh) { echo ui_empty('🔗', 'Link invalid or expired.'); return; }
$project = db_one('SELECT * FROM projects WHERE id=?', array($sh['project_id']));
if (!$project) { echo ui_empty('🔗', 'Project unavailable.'); return; }
$expired = $sh['expires_at'] && strtotime($sh['expires_at']) < time();
if ($expired) { echo ui_empty('⌛', 'This link has expired.'); return; }
if ($_POST && sf_post('comment')) {
    csrf_check(false);
    db_exec('INSERT INTO comments (project_id,who,body,kind,created_at) VALUES (?,?,?,?,?)',
        array($project['id'], 'Guest reviewer', substr((string) sf_post('comment'), 0, 2000), 'comment', db_now()));
    db_exec('UPDATE shares SET uses=uses+1 WHERE id=?', array($sh['id']));
    flash('Comment sent', 'ok'); sf_redirect(sf_url('index.php?r=share/' . $tok));
}
?>
<div style="max-width:820px;margin:0 auto">
  <div class="row" style="gap:10px;margin-bottom:16px"><div class="logo">SF</div><b>Shared review link</b><span class="spacer"></span><span class="chip"><?php echo e($sh['permission']); ?></span></div>
  <h1><?php echo e($project['title']); ?></h1>
  <p class="muted"><?php echo e($project['genre']); ?> · <?php echo e($project['culture']); ?> · status <?php echo e($project['status']); ?></p>
  <?php if ($project['cover']): ?><div class="frame" style="margin-bottom:14px"><img src="<?php echo e(sf_public_path($project['cover'])); ?>" alt=""></div><?php endif; ?>
  <?php $story = stage_data($project['id'], 'story'); if ($story && !empty($story['logline'])): ?>
    <div class="card"><h3>Logline</h3><p style="margin:0"><?php echo e($story['logline']); ?></p></div>
  <?php endif; ?>
  <div class="card">
    <h3>Comments</h3>
    <?php foreach (db_all('SELECT * FROM comments WHERE project_id=? ORDER BY id DESC LIMIT 20', array($project['id'])) as $c): ?>
      <div class="banner"><span>💬</span><div><b style="font-size:12.5px"><?php echo e($c['who']); ?></b> <span class="dim" style="font-size:10.5px"><?php echo ui_time_ago($c['created_at']); ?></span>
      <div class="muted" style="font-size:13px"><?php echo e($c['body']); ?></div></div></div>
    <?php endforeach; ?>
    <?php echo ui_form_open(sf_url('index.php?r=share/' . $tok)); ?>
    <label class="fl">Add a comment<textarea name="comment" required></textarea></label>
    <button class="btn pri" style="margin-top:10px">Send comment</button></form>
  </div>
</div>

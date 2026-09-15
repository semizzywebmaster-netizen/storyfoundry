<?php
$uid = auth_id();
if ($_POST && sf_post('upload')) {
    csrf_check();
    $up = sf_upload('file', 'assets');
    if (!empty($up['ok'])) {
        db_exec('INSERT INTO assets (user_id,project_id,kind,name,path,mime,size,folder,created_at) VALUES (?,?,?,?,?,?,?,?,?)', array(
            $uid, sf_int(sf_post('project_id')) ?: null, in_array($up['ext'], array('mp3', 'wav', 'm4a')) ? 'audio' : (in_array($up['ext'], array('mp4', 'mov', 'webm')) ? 'video' : 'image'),
            $up['name'], $up['path'], $up['ext'], $up['size'], 'Uploads', db_now()
        ));
        flash('Uploaded', 'ok');
    } else { flash($up['error'], 'err'); }
    sf_redirect(sf_url('index.php?r=assets'));
}
if (sf_get('del')) { db_exec('DELETE FROM assets WHERE id=? AND user_id=?', array(sf_int(sf_get('del')), $uid)); flash('Asset deleted', 'ok'); sf_redirect(sf_url('index.php?r=assets')); }
$kind = sf_get('kind', '');
$rows = db_all('SELECT * FROM assets WHERE user_id=?' . ($kind ? ' AND kind=?' : '') . ' ORDER BY id DESC LIMIT 120', $kind ? array($uid, $kind) : array($uid));
?>
<div class="row" style="align-items:flex-end;margin-bottom:14px;flex-wrap:wrap">
  <div><h1>Asset Library</h1><p class="muted" style="margin:0"><?php echo count($rows); ?> assets · images, video, audio, voices, music, SFX, documents.</p></div>
  <span class="spacer"></span>
  <?php echo ui_form_open(sf_url('index.php?r=assets'), 'post', 'enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center"'); ?>
  <input type="file" name="file" required style="width:auto"><button class="btn">⬆ Upload</button></form>
</div>
<div class="tabs">
  <?php foreach (array('' => 'All', 'image' => 'Images', 'audio' => 'Audio', 'video' => 'Video', 'document' => 'Documents') as $k => $l): ?>
    <a class="<?php echo (string) $kind === (string) $k ? 'on' : ''; ?>" href="<?php echo e(sf_url('index.php?r=assets' . ($k ? '&kind=' . $k : ''))); ?>"><?php echo e($l); ?></a>
  <?php endforeach; ?>
</div>
<?php if (!$rows): echo ui_empty('🗂️', 'No assets yet. Generate or upload something.'); else: ?>
<div class="assetgrid">
<?php foreach ($rows as $a): ?>
  <div class="card pad0" style="margin:0">
    <div class="frame">
      <?php if (in_array($a['kind'], array('image'), true)): ?><img src="<?php echo e(sf_public_path($a['path'])); ?>" alt="">
      <?php elseif ($a['kind'] === 'audio'): ?><div style="padding:26px 12px;text-align:center"><div style="font-size:26px">🎧</div><audio controls src="<?php echo e(sf_public_path($a['path'])); ?>" style="width:100%;margin-top:8px"></audio></div>
      <?php elseif ($a['kind'] === 'video'): ?><video controls src="<?php echo e(sf_public_path($a['path'])); ?>" style="width:100%;display:block"></video>
      <?php else: ?><div style="padding:30px 12px;text-align:center;font-size:26px">📄</div><?php endif; ?>
      <div class="btm"><?php echo e($a['name']); ?></div>
    </div>
    <div style="padding:9px">
      <div class="row" style="justify-content:space-between"><span class="dim" style="font-size:10.5px"><?php echo e($a['kind']); ?> · <?php echo e(round($a['size'] / 1024)); ?> KB</span>
        <span class="row" style="gap:4px"><a class="btn xs gho" href="<?php echo e(sf_url('index.php?r=assets&dl=' . $a['id'])); ?>">⬇</a>
        <a class="btn xs gho dgr" href="<?php echo e(sf_url('index.php?r=assets&del=' . $a['id'])); ?>" onclick="return confirm('Delete asset?')">✕</a></span></div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<div class="card" style="margin-top:14px">
  <div class="kv"><span class="k">Storage driver</span><?php echo ui_status(storage_driver()['name'] === 'local' ? 'MOCK' : 'REAL'); ?> <span class="mono dim"><?php echo e(storage_driver()['name']); ?></span></div>
  <div class="kv"><span class="k">Cloudflare R2</span><?php echo ui_status('NOT CONFIGURED'); ?> <span class="dim" style="font-size:12px">install the storage-r2 addon</span></div>
</div>

<?php
$uid = auth_id();
$action = isset($action) ? $action : null;
if ($_POST && $action === 'new') {
    csrf_check();
    $title = trim((string) sf_post('title')) ?: 'Untitled project';
    $pid = db_insert('INSERT INTO projects (user_id,title,genre,tone,audience,culture,theme,setting,length,style,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', array(
        $uid, $title, sf_post('genre'), sf_post('tone'), sf_post('audience'), sf_post('culture'), sf_post('theme'),
        sf_post('setting'), sf_post('length'), sf_post('style'), 'Draft', db_now(), db_now()
    ));
    db_exec('INSERT INTO project_activity (project_id,user_id,who,what,icon,created_at) VALUES (?,?,?,?,?,?)', array($pid, $uid, 'You', 'created the project', '📁', db_now()));
    audit('project.create', $title, 'info');
    sf_redirect(sf_url('index.php?r=project/' . $pid . '/idea'));
}
if ($action === 'delete' && sf_get('id')) {
    db_exec('UPDATE projects SET deleted_at=? WHERE id=? AND user_id=?', array(db_now(), sf_int(sf_get('id')), $uid));
    flash('Project archived', 'ok'); sf_redirect(sf_url('index.php?r=projects'));
}
if ($action === 'duplicate' && sf_get('id')) {
    $p = db_one('SELECT * FROM projects WHERE id=? AND user_id=?', array(sf_int(sf_get('id')), $uid));
    if ($p) {
        $new = db_insert('INSERT INTO projects (user_id,title,genre,tone,audience,culture,theme,setting,length,style,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            array($uid, $p['title'] . ' (copy)', $p['genre'], $p['tone'], $p['audience'], $p['culture'], $p['theme'], $p['setting'], $p['length'], $p['style'], 'Draft', db_now(), db_now()));
        foreach (db_all('SELECT * FROM project_data WHERE project_id=?', array($p['id'])) as $d) {
            db_exec('INSERT INTO project_data (project_id,stage_key,data,updated_at) VALUES (?,?,?,?)', array($new, $d['stage_key'], $d['data'], db_now()));
        }
        flash('Project duplicated', 'ok');
    }
    sf_redirect(sf_url('index.php?r=projects'));
}

if ($action === 'new'): ?>
  <h1>New project</h1>
  <div class="card" style="max-width:720px">
    <?php echo ui_form_open(sf_url('index.php?r=projects&a=new'));
    $cultures = array(); foreach (culture_keys() as $k) { $cultures[$k] = $k; }
    $genres = array(); foreach (array('Drama','Thriller','Comedy','Romance','Sci-Fi','Fantasy','Horror','Documentary','Crime','Adventure','Historical Epic','Animation','Family','Mystery') as $g) { $genres[$g] = $g; }
    $styles = array(); foreach (array('cinematic','realistic','anime','cartoon','threed','documentary','film','comic','illustration','darkcinematic','fantasy','historical','noir') as $s) { $styles[$s] = $s; }
    ?>
    <label class="fl">Title<input name="title" placeholder="The Last Harvest" required></label>
    <div class="grid g2" style="margin-top:10px">
      <label class="fl">Genre<?php echo ui_select('genre', $genres, 'Drama'); ?></label>
      <label class="fl">Cultural context<?php echo ui_select('culture', $cultures, $u['culture']); ?></label>
      <label class="fl">Tone<?php echo ui_select('tone', array('Gritty realism'=>'Gritty realism','Warm and hopeful'=>'Warm and hopeful','Dark and tense'=>'Dark and tense','Playful'=>'Playful','Melancholic'=>'Melancholic','Epic'=>'Epic','Intimate'=>'Intimate','Satirical'=>'Satirical','Inspirational'=>'Inspirational','Suspenseful'=>'Suspenseful'), 'Warm and hopeful'); ?></label>
      <label class="fl">Audience<?php echo ui_select('audience', array('General (PG)'=>'General (PG)','Teens (13+)'=>'Teens (13+)','Mature (18+)'=>'Mature (18+)','Kids / Family'=>'Kids / Family','Corporate / Brand'=>'Corporate / Brand'), 'General (PG)'); ?></label>
      <label class="fl">Length<?php echo ui_select('length', array('Short (2-5 min)'=>'Short (2–5 min)','Medium (8-15 min)'=>'Medium (8–15 min)','Long (25-45 min)'=>'Long (25–45 min)','Feature (60-90 min)'=>'Feature (60–90 min)'), 'Medium (8-15 min)'); ?></label>
      <label class="fl">Visual style<?php echo ui_select('style', $styles, 'cinematic'); ?></label>
    </div>
    <div class="row" style="margin-top:16px;gap:8px"><button class="btn pri">Create project</button><a class="btn gho" href="<?php echo e(sf_url('index.php?r=projects')); ?>">Cancel</a></div>
    </form>
  </div>
<?php else:
  $rows = db_all('SELECT * FROM projects WHERE user_id=? AND deleted_at IS NULL ORDER BY updated_at DESC', array(auth_id())); ?>
  <div class="row" style="align-items:flex-end;margin-bottom:14px;flex-wrap:wrap">
    <div><h1>Projects</h1><p class="muted" style="margin:0">Create, duplicate, archive and share.</p></div>
    <span class="spacer"></span><a class="btn pri" href="<?php echo e(sf_url('index.php?r=projects&a=new')); ?>">＋ New project</a>
  </div>
  <?php if (!$rows): echo ui_empty('📁', 'No projects yet.', '<a class="btn pri" href="' . e(sf_url('index.php?r=projects&a=new')) . '">Create your first project</a>');
  else: ?>
  <div class="grid g3">
  <?php foreach ($rows as $p): ?>
    <div class="card pad0" style="margin:0">
      <a href="<?php echo e(sf_url('index.php?r=project/' . $p['id'])); ?>">
        <div class="frame"><img src="<?php echo e($p['cover'] ? sf_public_path($p['cover']) : sf_url('uploads/placeholder.svg')); ?>" alt="">
          <div class="btm"><b><?php echo e($p['title']); ?></b></div></div>
      </a>
      <div style="padding:11px">
        <div class="row" style="justify-content:space-between"><span class="chip"><?php echo e($p['genre']); ?></span><span class="dim" style="font-size:10.5px"><?php echo ui_time_ago($p['updated_at']); ?></span></div>
        <div class="dim" style="font-size:11.5px;margin-top:7px"><?php echo e($p['culture']); ?> · <?php echo e($p['status']); ?></div>
        <div class="row" style="gap:5px;margin-top:9px">
          <a class="btn xs" href="<?php echo e(sf_url('index.php?r=project/' . $p['id'])); ?>">Open</a>
          <a class="btn xs gho" href="<?php echo e(sf_url('index.php?r=projects&a=duplicate&id=' . $p['id'])); ?>">⧉</a>
          <a class="btn xs gho dgr" href="<?php echo e(sf_url('index.php?r=projects&a=delete&id=' . $p['id'])); ?>" onclick="return confirm('Archive this project?')">✕</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
  <?php endif; endif; ?>

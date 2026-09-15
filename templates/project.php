<?php
$pid = isset($id) ? (int) $id : 0;
$project = db_one('SELECT * FROM projects WHERE id=? AND deleted_at IS NULL', array($pid));
if (!$project) { echo ui_empty('🔍', 'Project not found.'); return; }
if ($project['user_id'] != auth_id() && !auth_is_admin()) { http_response_code(403); echo 'Forbidden'; return; }

$stages = plugins_stages();
$keys = array(); foreach ($stages as $s) { $keys[] = $s['key']; }
$active = isset($stage) && $stage ? $stage : ($keys ? $keys[0] : '');
$stageMeta = null; foreach ($stages as $s) { if ($s['key'] === $active) { $stageMeta = $s; } }
if (!$stageMeta && $stages) { $stageMeta = $stages[0]; $active = $stageMeta['key']; }

/* which stages already have data (for the rail ticks) */
$filled = array();
foreach (db_all('SELECT stage_key FROM project_data WHERE project_id=?', array($pid)) as $r) { $filled[$r['stage_key']] = true; }
?>
<div class="row" style="align-items:flex-end;gap:12px;margin-bottom:6px;flex-wrap:wrap">
  <div>
    <div class="dim" style="font-size:12px"><a href="<?php echo e(sf_url('index.php?r=projects')); ?>">Projects</a> / <?php echo e($project['title']); ?></div>
    <h1><?php echo e($project['title']); ?></h1>
    <p class="muted" style="margin:0"><?php echo e($project['genre']); ?> · <?php echo e($project['culture']); ?> · style <span class="chip"><?php echo e($project['style']); ?></span> · status <span class="chip"><?php echo e($project['status']); ?></span></p>
  </div>
  <span class="spacer"></span>
  <a class="btn sm" href="<?php echo e(sf_url('index.php?r=projects&a=duplicate&id=' . $pid)); ?>">⧉ Duplicate</a>
  <a class="btn sm pri" href="<?php echo e(sf_url('index.php?r=project/' . $pid . '/export')); ?>">📦 Export</a>
</div>

<div class="rail">
<?php $i = 0; foreach ($stages as $s):
  $isActive = $s['key'] === $active;
  $isDone = !empty($filled[$s['key']]) && !$isActive; ?>
  <a class="rstep <?php echo $isActive ? 'on' : ($isDone ? 'done' : ''); ?>" href="<?php echo e(sf_url('index.php?r=project/' . $pid . '/' . $s['key'])); ?>">
    <span class="n"><?php echo $isDone ? '✓' : ($i + 1); ?></span><?php echo e($s['icon']); ?> <?php echo e($s['label']); ?></a>
<?php $i++; endforeach; ?>
</div>

<?php
/* ---- approval workflow (feature 69): Draft → Review → Changes → Approval → Final ---- */
$states = array('Draft', 'Review', 'Changes', 'Approval', 'Final');
$cur = isset($project['approval']) && $project['approval'] ? $project['approval'] : 'Draft';
$open = db_all('SELECT * FROM comments WHERE project_id=? AND kind=? AND resolved=0 ORDER BY id DESC LIMIT 8', array($pid, 'change_request'));
$hist = db_all('SELECT * FROM project_activity WHERE project_id=? ORDER BY id DESC LIMIT 6', array($pid));
?>
<div class="card">
  <div class="row" style="justify-content:space-between;flex-wrap:wrap;gap:10px">
    <div>
      <h3 style="margin:0">✅ Approval workflow</h3>
      <div class="muted" style="font-size:12px">Draft → Review → Changes → Approval → Final. Add a change request and it is tracked here.</div>
    </div>
    <div class="row wrap" style="gap:5px">
      <?php foreach ($states as $st): ?>
        <span class="chip <?php echo $st === $cur ? 'chip-ok' : ''; ?>"><?php echo e($st); ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <form method="post" action="<?php echo e(sf_url('index.php?r=project/' . $pid . '/review')); ?>" class="row" style="gap:8px;align-items:flex-end;margin-top:12px;flex-wrap:wrap">
    <?php echo csrf_field(); ?>
    <div>
      <label class="dim" style="font-size:11px;display:block;margin-bottom:3px">State</label>
      <select name="approval_state" class="inp">
        <?php foreach ($states as $st): ?>
          <option value="<?php echo e($st); ?>" <?php echo $st === $cur ? 'selected' : ''; ?>><?php echo e($st); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="flex:1;min-width:220px">
      <label class="dim" style="font-size:11px;display:block;margin-bottom:3px">Comment / change request</label>
      <input name="note" class="inp" style="width:100%" placeholder="What needs to change?" maxlength="2000">
    </div>
    <label class="row" style="gap:5px;font-size:12px;align-items:center">
      <input type="checkbox" name="note_kind" value="1"> change request
    </label>
    <button class="btn pri sm" type="submit">Save</button>
  </form>

  <?php if ($open): ?>
    <div style="margin-top:12px">
      <div class="dim" style="font-size:11px;margin-bottom:5px">OPEN CHANGE REQUESTS</div>
      <?php foreach ($open as $c): ?>
        <div class="row" style="gap:8px;align-items:center;padding:6px 0;border-top:1px solid var(--line)">
          <span class="chip chip-warn">✏️</span>
          <div style="flex:1"><b style="font-size:12.5px"><?php echo e($c['who']); ?></b>
          <span class="muted" style="font-size:12.5px"> · <?php echo e($c['body']); ?></span></div>
          <form method="post" action="<?php echo e(sf_url('index.php?r=project/' . $pid . '/review')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="resolve_id" value="<?php echo (int) $c['id']; ?>">
            <button class="btn xs" type="submit">Resolve</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($hist): ?>
    <div style="margin-top:12px">
      <div class="dim" style="font-size:11px;margin-bottom:5px">RECENT ACTIVITY</div>
      <?php foreach ($hist as $a): ?>
        <div class="muted" style="font-size:12px"><?php echo e($a['icon']); ?> <?php echo e($a['who']); ?> <?php echo e($a['what']); ?> · <span class="dim"><?php echo e(time_ago($a['created_at'])); ?></span></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if (!$stageMeta): ?>
  <?php echo ui_empty('🧩', 'No pipeline addons enabled.', '<a class="btn pri" href="' . e(sf_url('index.php?r=admin&p=addons')) . '">Open Addons</a>'); ?>
<?php else:
  $plugin = plugin_instance($stageMeta['plugin']);
  $data = stage_data($pid, $active, null);
  echo $plugin ? $plugin->stage($project, $data) : ui_empty('⚠️', 'Addon failed to load: ' . e($stageMeta['plugin']));
endif; ?>

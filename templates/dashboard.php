<?php $u = auth_user(); $active = jobs_active(auth_id()); ?>
<div class="ph" style="display:flex;align-items:flex-end;gap:12px;margin-bottom:14px;flex-wrap:wrap">
  <div><h1>Welcome back, <?php echo e(explode(' ', $u['name'])[0]); ?></h1>
  <p class="muted" style="margin:0">Everything in flight, in one place.</p></div>
  <span class="spacer"></span>
  <a class="btn pri" href="<?php echo e(sf_url('index.php?r=projects&a=new')); ?>">＋ New project</a>
</div>

<div class="grid g4" style="margin-bottom:14px">
  <?php echo ui_kpi((int) credits_available(auth_id()), 'Credits available', 'Reserved: ' . (int) $u['reserved']); ?>
  <?php echo ui_kpi(e(sf_money($u['wallet'])), 'Wallet', 'Spend inside STORYFOUNDRY only'); ?>
  <?php echo ui_kpi((int) db_val('SELECT COUNT(*) FROM projects WHERE user_id=? AND deleted_at IS NULL', array(auth_id()), 0), 'Projects', ''); ?>
  <?php echo ui_kpi(count($active), 'Jobs active', ''); ?>
</div>

<div class="grid" style="grid-template-columns:1.55fr 1fr;align-items:start">
<div class="col">
  <div class="card">
    <div class="row" style="justify-content:space-between"><h3>Production status</h3><a class="btn xs gho" href="<?php echo e(sf_url('index.php?r=jobs')); ?>">All jobs →</a></div>
    <div data-jobpoll data-autoreload="1">
      <?php if (!$active): ?><div class="empty"><div class="ico">✅</div>No jobs running.</div>
      <?php else: foreach ($active as $j): ?>
        <div style="margin-bottom:11px">
          <div class="row" style="justify-content:space-between"><div style="font-size:13px;font-weight:600"><?php echo e($j['label']); ?></div>
            <div class="row" style="gap:8px"><?php echo ui_status($j['status']); ?><span class="dim mono"><?php echo (int) $j['progress']; ?>%</span></div></div>
          <div class="bar" style="margin-top:5px"><i style="width:<?php echo (int) $j['progress']; ?>%"></i></div>
          <div class="dim" style="font-size:11px;margin-top:4px"><?php echo e($j['provider'] ?: 'routing…'); ?> · <?php echo (int) $j['cost']; ?> cr reserved</div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="row" style="justify-content:space-between"><h3>Recent projects</h3><a class="btn xs gho" href="<?php echo e(sf_url('index.php?r=projects')); ?>">All →</a></div>
    <div class="grid g2">
    <?php $rows = db_all('SELECT * FROM projects WHERE user_id=? AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 4', array(auth_id()));
    if (!$rows): echo ui_empty('📁', 'No projects yet. Create your first one.'); else: foreach ($rows as $p): ?>
      <a class="card pad0" style="margin:0" href="<?php echo e(sf_url('index.php?r=project/' . $p['id'])); ?>">
        <div class="frame"><img src="<?php echo e($p['cover'] ? sf_public_path($p['cover']) : sf_url('uploads/placeholder.svg')); ?>" alt="">
          <div class="btm"><b><?php echo e($p['title']); ?></b></div></div>
        <div style="padding:10px"><div class="row" style="justify-content:space-between"><span class="chip"><?php echo e($p['genre']); ?></span><span class="dim" style="font-size:10.5px"><?php echo ui_time_ago($p['updated_at']); ?></span></div></div>
      </a>
    <?php endforeach; endif; ?>
    </div>
  </div>

  <?php foreach (apply_filters('dashboard.cards', array()) as $card): ?><?php echo $card; ?><?php endforeach; ?>
</div>

<div class="col">
  <div class="card">
    <div class="row" style="justify-content:space-between"><h3>Announcements</h3></div>
    <?php foreach (announcements_active(3) as $a): ?>
      <div style="padding:9px 0;border-bottom:1px dashed var(--line)">
        <div class="row" style="gap:7px"><span class="chip <?php echo $a['type'] === 'Maintenance' ? 'chip-warn' : 'chip-info'; ?>"><?php echo e($a['type']); ?></span><span class="dim" style="font-size:10.5px"><?php echo ui_time_ago($a['created_at']); ?></span></div>
        <b style="display:block;margin:5px 0 2px;font-size:13px"><?php echo e($a['title']); ?></b>
        <div class="muted" style="font-size:12px"><?php echo e($a['body']); ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h3>Provider status</h3>
    <div class="dim" style="font-size:11.5px;margin-bottom:8px">Live capability matrix. MOCK providers run locally; add keys in Admin to go REAL.</div>
    <?php foreach (array('text', 'image', 'voice', 'music', 'video') as $cap):
      $ps = providers_for($cap); $real = 0; $mock = 0; foreach ($ps as $p) { if ($p['status'] === 'REAL') $real++; else $mock++; } ?>
      <div class="kv"><span class="k"><?php echo e(ucfirst($cap)); ?></span>
        <span class="row" style="gap:5px"><?php echo $real ? '<span class="chip chip-ok">' . $real . ' real</span>' : ''; ?><span class="chip chip-mock"><?php echo $mock; ?> mock</span></span></div>
    <?php endforeach; ?>
    <?php if (auth_is_admin()): ?><a class="btn sm blk gho" style="margin-top:10px" href="<?php echo e(sf_url('index.php?r=admin&p=providers')); ?>">Manage providers →</a><?php endif; ?>
  </div>
</div>
</div>

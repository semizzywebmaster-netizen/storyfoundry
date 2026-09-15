<!doctype html>
<html lang="en" data-theme="<?php echo e(sf_theme()); ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo e(isset($title) ? $title : 'STORYFOUNDRY'); ?> · STORYFOUNDRY</title>
<meta name="csrf" content="<?php echo e(csrf_token()); ?>">
<meta name="base" content="<?php echo e(sf_url('')); ?>">
<link rel="stylesheet" href="<?php echo e(sf_asset('css/app.css')); ?>">
<link rel="manifest" href="<?php echo e(sf_url('index.php?r=manifest.json')); ?>">
<meta name="theme-color" content="<?php echo sf_theme() === 'light' ? '#ffffff' : '#0b0c0e'; ?>">
<?php do_action('layout.head'); ?>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%23ffb020'/%3E%3Ctext x='16' y='23' font-family='Arial' font-size='17' font-weight='900' text-anchor='middle' fill='%231a1206'%3ES%3C/text%3E%3C/svg%3E">
</head>
<body>
<?php $u = auth_user(); if ($u): ?>
<div id="app">
<aside id="sb">
  <div class="brand"><div class="logo">SF</div><div><b>STORYFOUNDRY</b><small>AI PRODUCTION STUDIO</small></div></div>
  <div style="padding:0 10px 8px"><a class="btn pri blk" href="<?php echo e(sf_url('index.php?r=projects&a=new')); ?>">＋ New project</a></div>
  <nav class="nav">
    <div class="navgrp">Studio</div>
    <a href="<?php echo e(sf_url('index.php?r=dashboard')); ?>" class="<?php echo sf_nav_active('dashboard'); ?>"><span class="ico">📊</span>Dashboard</a>
    <a href="<?php echo e(sf_url('index.php?r=projects')); ?>" class="<?php echo sf_nav_active('projects'); ?>"><span class="ico">📁</span>Projects</a>
    <a href="<?php echo e(sf_url('index.php?r=studio')); ?>" class="<?php echo sf_nav_active('studio'); ?>"><span class="ico">🧰</span>Studios</a>
    <a href="<?php echo e(sf_url('index.php?r=assets')); ?>" class="<?php echo sf_nav_active('assets'); ?>"><span class="ico">🗂️</span>Asset Library</a>
    <a href="<?php echo e(sf_url('index.php?r=jobs')); ?>" class="<?php echo sf_nav_active('jobs'); ?>"><span class="ico">⚙️</span>Jobs & Queue<?php $jq = db_val("SELECT COUNT(*) FROM jobs WHERE user_id=? AND status IN ('queued','processing')", array(auth_id()), 0); if ($jq): ?><span class="cnt"><?php echo (int) $jq; ?></span><?php endif; ?></a>
    <?php if (sf_nav_group('Business') || sf_nav_group('Create')): ?>
    <div class="navgrp">Addons</div>
    <?php foreach (array_merge(sf_nav_group('Create'), sf_nav_group('Business')) as $it): ?>
      <a href="<?php echo e(sf_url('index.php?r=' . $it['route'])); ?>" class="<?php echo sf_nav_active($it['route']); ?>"><span class="ico"><?php echo e(isset($it['icon']) ? $it['icon'] : '•'); ?></span><?php echo e($it['label']); ?></a>
    <?php endforeach; endif; ?>
    <div class="navgrp">Account</div>
    <a href="<?php echo e(sf_url('index.php?r=billing')); ?>" class="<?php echo sf_nav_active('billing'); ?>"><span class="ico">💳</span>Billing & Credits</a>
    <a href="<?php echo e(sf_url('index.php?r=account')); ?>" class="<?php echo sf_nav_active('account'); ?>"><span class="ico">⚙️</span>Settings</a>
    <?php if (auth_is_admin()): ?>
    <div class="navgrp">Platform</div>
    <a href="<?php echo e(sf_url('index.php?r=admin')); ?>" class="<?php echo sf_nav_active('admin'); ?>"><span class="ico">🛡️</span>Admin</a>
    <?php endif; ?>
  </nav>
  <div style="padding:10px;border-top:1px solid var(--line)">
    <div class="card" style="padding:11px;margin:0">
      <div class="row" style="justify-content:space-between"><b style="font-size:12px">Credits</b><span class="chip chip-acc"><?php echo (int) credits_available(auth_id()); ?></span></div>
      <div class="bar" style="margin-top:7px"><i style="width:<?php echo min(100, max(2, credits_available(auth_id()) / 20)); ?>%"></i></div>
      <a class="btn xs blk gho" style="margin-top:9px" href="<?php echo e(sf_url('index.php?r=billing')); ?>">Top up</a>
    </div>
  </div>
</aside>
<main>
  <div id="top">
    <button class="btn sm gho" id="burger">☰</button>
    <b>STORYFOUNDRY</b>
    <span class="spacer"></span>
    <form method="post" action="<?php echo e(sf_url('index.php?r=theme')); ?>" style="display:inline;margin:0">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="v" value="<?php echo sf_theme() === 'light' ? 'dark' : 'light'; ?>">
      <button class="btn sm gho" type="submit" title="Switch to <?php echo sf_theme() === 'light' ? 'dark' : 'light'; ?> mode"><?php echo sf_theme() === 'light' ? '🌙' : '☀️'; ?></button>
    </form>
    <a class="btn sm gho" href="<?php echo e(sf_url('index.php?r=notifications')); ?>">🔔<?php $nn = notifications_unread(auth_id()); if ($nn): ?> <span class="chip chip-err"><?php echo (int) $nn; ?></span><?php endif; ?></a>
    <a class="btn sm gho" href="<?php echo e(sf_url('index.php?r=account')); ?>"><span class="avatar" style="width:24px;height:24px;font-size:10px"><?php echo e(strtoupper(substr($u['name'], 0, 2))); ?></span></a>
  </div>
  <div id="view"><div class="page"><?php echo $body; ?></div></div>
</main>
</div>
<?php else: ?>
<div id="view"><div class="page"><?php echo $body; ?></div></div>
<?php endif; ?>
<div class="flashes" id="flashes">
  <?php foreach ($flash as $f): ?><div class="flash <?php echo e($f['k']); ?>"><?php echo e($f['m']); ?></div><?php endforeach; ?>
</div>
<script src="<?php echo e(sf_asset('js/app.js')); ?>"></script>
<?php do_action('footer'); ?>
</body></html>

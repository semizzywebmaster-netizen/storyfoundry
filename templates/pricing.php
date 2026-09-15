<div style="max-width:1140px;margin:0 auto;padding:20px">
  <div class="row" style="gap:10px;margin-bottom:22px"><div class="logo">SF</div><b>STORYFOUNDRY</b><span class="spacer"></span><a class="btn sm" href="<?php echo e(sf_url('index.php?r=home')); ?>">Home</a></div>
  <h1 style="font-size:30px;text-align:center">Pricing</h1>
  <p class="muted" style="text-align:center;margin-bottom:28px">Credits are consumed per generation. Subscriptions include a monthly allocation. Paystack or Flutterwave.</p>
  <div class="grid g4">
    <?php foreach (db_all('SELECT * FROM plans WHERE active=1 ORDER BY sort') as $p):
      $feats = explode('|', (string) $p['features']); ?>
      <div class="plan<?php echo $p['pkey'] === 'pro' ? ' hot' : ''; ?>">
        <div class="row btw"><div style="font-weight:800;font-size:16px"><?php echo e($p['name']); ?></div><?php if ($p['pkey'] === 'pro'): ?><span class="chip chip-acc">Popular</span><?php endif; ?></div>
        <div class="pprice"><?php echo $p['price'] > 0 ? e(sf_money($p['price'])) : 'Free'; ?></div>
        <div class="dim" style="font-size:12px"><?php echo (int) $p['credits']; ?> credits / month</div>
        <ul><?php foreach ($feats as $f): ?><li><b>✓</b> <?php echo e($f); ?></li><?php endforeach; ?></ul>
        <a class="btn blk<?php echo $p['pkey'] === 'pro' ? ' pri' : ''; ?>" href="<?php echo e(sf_url('index.php?r=' . (auth_check() ? 'billing' : 'register'))); ?>"><?php echo $p['price'] > 0 ? 'Choose ' . e($p['name']) : 'Start free'; ?></a>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="banner warn" style="margin-top:20px"><span>🚫</span><div><b>No withdrawal.</b> Wallet funds are usable only inside STORYFOUNDRY — for credits, subscriptions and marketplace purchases.</div></div>
</div>

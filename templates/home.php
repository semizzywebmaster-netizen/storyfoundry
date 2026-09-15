<div class="lnav">
  <div class="row"><div class="logo">SF</div><b>STORYFOUNDRY</b></div>
  <span class="spacer"></span>
  <a class="btn sm gho hide-sm" href="#pipeline">Pipeline</a>
  <a class="btn sm gho hide-sm" href="#features">Features</a>
  <a class="btn sm gho hide-sm" href="<?php echo e(sf_url('index.php?r=pricing')); ?>">Pricing</a>
  <a class="btn sm" href="<?php echo e(sf_url('index.php?r=login')); ?>">Sign in</a>
  <a class="btn sm pri" href="<?php echo e(sf_url('index.php?r=register')); ?>">Start free →</a>
</div>
<section class="hero">
  <div style="display:inline-flex;gap:8px;align-items:center;padding:6px 13px;border-radius:99px;border:1px solid var(--line);background:var(--panel);font-size:12px;color:var(--tx2);margin-bottom:20px">
    🟡 PHP + MySQL · runs on cPanel · addon architecture
  </div>
  <h1>From Idea to <em>Finished Story</em>.</h1>
  <p class="lead">A complete AI content production studio — story, characters, scenes, shots, images, voice, music, SFX, video, subtitles, thumbnail, social pack, export and publish. One pipeline. Nineteen stages. No hand-offs.</p>
  <div class="row" style="justify-content:center;gap:10px;flex-wrap:wrap">
    <a class="btn pri" style="padding:12px 20px;font-size:15px" href="<?php echo e(sf_url('index.php?r=register')); ?>">Start free — 150 credits →</a>
    <a class="btn" style="padding:12px 20px;font-size:15px" href="<?php echo e(sf_url('index.php?r=pricing')); ?>">See pricing</a>
  </div>
  <div class="flow" id="pipeline">
    <?php $flow = array('IDEA','STORY','CHARACTERS','SCENES','SHOTS','IMAGES','VOICE','MUSIC','SFX','VIDEO','SUBTITLES','THUMBNAIL','SOCIAL','EXPORT','PUBLISH');
    foreach ($flow as $i => $f) { echo '<span>' . e($f) . '</span>' . ($i < count($flow) - 1 ? '<i>→</i>' : ''); } ?>
  </div>
</section>
<section class="sec" id="features">
  <h2>Everything the studio needs. One roof.</h2>
  <p class="muted" style="max-width:640px;margin:0 0 26px">Eighty-three capability areas wired into a single pipeline. Every stage writes artefacts the next stage reads — the character bible constrains the image prompts, the shot list constrains the edit, the edit constrains the subtitles.</p>
  <div class="grid g3">
    <?php $cards = array(
      array('💡','Idea Engine','Genre, tone, audience and culture-aware concepts with conflict, twist and ending options.'),
      array('✍️','Story Studio','Outlines, chapters, scenes, dialogue, versions, Story Doctor and Rewrite Studio.'),
      array('🧑','Character Bible','Identity, appearance, clothing, voice, relationships and locked visual consistency.'),
      array('🎬','Scene & Shot Engine','AI Director converts prose into camera, lens, movement, blocking and continuity.'),
      array('🎨','Image Generation','Multi-provider scene frames, variations, aspect ratios and style locking.'),
      array('🗣️','Voice Studio','Narration and per-character voices with emotion, style and language control.'),
      array('🎵','Music & SFX','Score, ambience and effects with per-track mixing, fades and master bus.'),
      array('🎥','Video & Timeline','Image-to-video, multi-track timeline, trim, transitions and FFmpeg rendering.'),
      array('📝','Subtitles','Auto cues, timing, styling, multi-language and burned-in export.'),
      array('📱','Social Pack','Titles, descriptions, tags, captions, hashtags and hooks per platform.'),
      array('📦','Export','Story docs, frames, audio, video, subtitles, storyboard and social pack.'),
      array('🧩','Addon architecture','Every capability is a plugin. Install, disable or add new ones from Admin → Addons.'),
    ); foreach ($cards as $c): ?>
    <div class="fcard"><div style="font-size:22px;margin-bottom:9px"><?php echo $c[0]; ?></div><b><?php echo e($c[1]); ?></b><p><?php echo e($c[2]); ?></p></div>
    <?php endforeach; ?>
  </div>
</section>
<section class="sec" style="padding-top:0">
  <h2>Honest about what is real</h2>
  <p class="muted" style="max-width:640px;margin:0 0 20px">This platform ships with working local providers so you can use it before you add API keys. Every provider carries one of three statuses, visible in Admin → AI Providers.</p>
  <div class="grid g3">
    <div class="card"><span class="chip chip-ok"><i class="dot"></i>REAL</span><p style="margin:10px 0 0" class="muted">Credentials present and health-checked. Live API calls.</p></div>
    <div class="card"><span class="chip chip-mock"><i class="dot"></i>MOCK</span><p style="margin:10px 0 0" class="muted">Local deterministic substitute (composer, frame synthesiser, voice/score synth). Always labelled. Production never silently falls back to these.</p></div>
    <div class="card"><span class="chip chip-nc"><i class="dot"></i>NOT CONFIGURED</span><p style="margin:10px 0 0" class="muted">Adapter exists, keys missing. Shown in the UI and blocked in the orchestrator.</p></div>
  </div>
</section>
<footer class="lf">
  <b>STORYFOUNDRY</b> · www.storyfoundry.online ·
  <a href="<?php echo e(sf_url('index.php?r=page&p=terms')); ?>">Terms</a> ·
  <a href="<?php echo e(sf_url('index.php?r=page&p=privacy')); ?>">Privacy</a> ·
  <a href="<?php echo e(sf_url('index.php?r=page&p=contact')); ?>">Contact</a>
  <div style="margin-top:8px">Tagline: From Idea to Finished Story.</div>
</footer>

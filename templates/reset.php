<div style="max-width:400px;margin:60px auto">
  <b style="font-size:18px;display:block;margin-bottom:16px">Reset password</b>
  <div class="card">
    <?php if (!empty($token)): ?>
      <?php echo ui_form_open(sf_url('index.php?r=reset')); ?>
      <input type="hidden" name="token" value="<?php echo e($token); ?>">
      <label class="fl">New password<input type="password" name="password" required minlength="8"></label>
      <button class="btn pri blk" style="margin-top:14px">Set new password</button></form>
    <?php else: ?>
      <?php echo ui_form_open(sf_url('index.php?r=reset')); ?>
      <label class="fl">Account email<input type="email" name="email" required></label>
      <button class="btn pri blk" style="margin-top:14px">Send reset link</button></form>
      <div class="dim" style="font-size:12px;margin-top:10px">On cPanel, PHP <code>mail()</code> is used. Configure SMTP in cPanel → Email if delivery fails; the link is also written to <code>storage/logs/</code>.</div>
    <?php endif; ?>
  </div>
</div>

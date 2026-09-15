<div style="max-width:400px;margin:60px auto">
  <div class="row" style="gap:10px;margin-bottom:20px"><div class="logo">SF</div><b style="font-size:18px">Create your account</b></div>
  <div class="card">
    <div class="banner info"><span>🎁</span><div><?php echo (int) setting('credits_new_user', 150); ?> free credits on signup — enough to generate a full short film pipeline once.</div></div>
    <?php echo ui_form_open(sf_url('index.php?r=register')); ?>
    <label class="fl">Full name<input name="name" required autofocus></label>
    <label class="fl" style="margin-top:10px">Email<input type="email" name="email" required></label>
    <label class="fl" style="margin-top:10px">Password<input type="password" name="password" required minlength="8"><small class="dim">Minimum 8 characters.</small></label>
    <button class="btn pri blk" style="margin-top:16px">Create account</button>
    </form>
    <hr class="sep"><div class="muted" style="font-size:12.5px">Already have an account? <a class="acc" href="<?php echo e(sf_url('index.php?r=login')); ?>">Sign in</a>.</div>
  </div>
</div>

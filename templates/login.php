<div style="max-width:400px;margin:60px auto">
  <div class="row" style="gap:10px;margin-bottom:20px"><div class="logo">SF</div><b style="font-size:18px">Sign in to STORYFOUNDRY</b></div>
  <div class="card">
    <?php echo ui_form_open(sf_url('index.php?r=login')); ?>
    <label class="fl">Email<input type="email" name="email" required autofocus></label>
    <label class="fl" style="margin-top:10px">Password<input type="password" name="password" required></label>
    <button class="btn pri blk" style="margin-top:16px">Sign in</button>
    </form>
    <hr class="sep">
    <div class="row btw"><a class="muted" href="<?php echo e(sf_url('index.php?r=register')); ?>">Create account</a><a class="muted" href="<?php echo e(sf_url('index.php?r=reset')); ?>">Forgot password?</a></div>
  </div>
</div>

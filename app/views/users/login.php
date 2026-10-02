<h1>Log in</h1>
<?php if ($error): ?><div class="flash flash-warn"><?= e($error) ?></div><?php endif ?>
<form method="post" action="<?= url('/login') ?>" class="auth-form">
  <?= csrf_field() ?>
  <table class="form" cellspacing="0">
    <tr><th><label for="username">Username</label></th><td><input type="text" id="username" name="username" value="<?= e($username) ?>" required autocomplete="username"></td></tr>
    <tr><th><label for="password">Password</label></th><td><input type="password" id="password" name="password" required autocomplete="current-password"></td></tr>
    <tr><th></th><td><button type="submit" class="primary">Log in</button></td></tr>
  </table>
</form>
<p class="small">New here? <a href="<?= url('/signup') ?>">Sign up</a>.</p>

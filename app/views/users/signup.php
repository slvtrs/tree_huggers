<h1>Sign up</h1>
<p class="small">Just a username and a password. No email, no newsletter, no tracking. Your password is stored hashed.</p>
<form method="post" action="<?= url('/signup') ?>" class="auth-form">
  <?= csrf_field() ?>
  <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
  <table class="form" cellspacing="0">
    <tr><th><label for="username">Username</label></th>
      <td><input type="text" id="username" name="username" value="<?= e($username) ?>" maxlength="20" pattern="[A-Za-z0-9_]{3,20}" title="3-20 letters, numbers or underscores" required autocomplete="username">
      <div class="hint">3-20 letters, numbers or underscores. Saved in lowercase, so RatinLoot becomes ratinloot.</div>
      <?php if (isset($errors['username'])): ?><div class="err"><?= e($errors['username']) ?></div><?php endif ?></td></tr>
    <tr><th><label for="password">Password</label></th>
      <td><input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
      <?php if (isset($errors['password'])): ?><div class="err"><?= e($errors['password']) ?></div><?php endif ?></td></tr>
    <tr><th><label for="password_confirm">Again</label></th>
      <td><input type="password" id="password_confirm" name="password_confirm" minlength="8" required autocomplete="new-password">
      <?php if (isset($errors['password_confirm'])): ?><div class="err"><?= e($errors['password_confirm']) ?></div><?php endif ?></td></tr>
    <tr><th></th><td><button type="submit" class="primary">Create account</button></td></tr>
  </table>
</form>
<p class="small">Already have one? <a href="<?= url('/login') ?>">Log in</a>.</p>

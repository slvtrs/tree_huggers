<?php
$friendly = [
    400 => ['Hmm, that request looked odd.', 'Something in what was sent did not make sense to us.'],
    403 => ['That is not your tree to prune.', 'Only the person who hugged a tree can change it. If it is yours, log in from the browser you hugged it with.'],
    404 => ['This tree is not on our map.', 'The page you were looking for does not exist, or it has been removed.'],
    429 => ['Whoa, slow down there.', 'You have been doing that a lot in a short time. Take a breath and try again in a little while.'],
    500 => ['Something fell out of the tree.', 'An error happened on our side. It has been logged and we will have a look. Please try again in a moment.'],
];
[$heading, $explain] = $friendly[$code] ?? ['Something went wrong.', 'Please try again.'];
$showDetail = $code !== 500 || !empty($GLOBALS['config']['debug']);
$generic = ['Bad request', 'Forbidden', 'Not found', 'Slow down', 'Error'];
?>
<div class="error-page">
  <div class="error-art"><?= tree_sprite(6, 'class="px"') ?></div>
  <h1><?= e($heading) ?></h1>
  <p><?= e($explain) ?></p>
  <?php if ($showDetail && $message !== '' && !in_array($message, $generic, true)): ?>
    <p class="small muted error-detail"><?= nl2br(e($message)) ?></p>
  <?php endif ?>
  <p class="error-links">
    <a class="btn" href="<?= url('/') ?>">&larr; Back to the map</a>
    <a class="btn" href="<?= url('/trees/new') ?>">&#127795; Hug a tree</a>
  </p>
  <p class="small muted">Error <?= (int) $code ?></p>
</div>

<h1><?= tree_sprite(2, 'class="px"') ?> <?= e($user['username']) ?></h1>
<p class="small muted">Tree hugger since <?= e(gmdate('F Y', strtotime($user['created_at']))) ?> &middot; <?= count($trees) ?> tree<?= count($trees) === 1 ? '' : 's' ?> hugged
<?php if ($is_me): ?> &middot; this is you! Share this page: <code><?= e((($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . url('/u/' . $user['username'])) ?></code><?php endif ?></p>

<?php if ($trees): ?>
<div id="map" class="map map-medium" data-trees='<?= e(json_encode(array_map('tree_summary', $trees), JSON_UNESCAPED_SLASHES)) ?>'></div>
<table class="data" cellspacing="0" width="100%">
  <?php foreach ($trees as $t): ?>
  <tr>
    <td class="thumb-cell"><a href="<?= url('/trees/' . $t['id']) ?>">
      <?php if ($thumb = tree_photo_url($t, true)): ?><img src="<?= e($thumb) ?>" alt="" width="60" height="60" class="thumb">
      <?php else: ?><?= tree_sprite(3, 'class="px thumb-placeholder"') ?><?php endif ?></a></td>
    <td><a href="<?= url('/trees/' . $t['id']) ?>"><b><?= e($t['title']) ?></b></a>
      <?php if ($t['species']): ?><br><i><?= e(implode(', ', array_map('species_label', $t['species']))) ?></i><?php endif ?>
      <br><span class="small muted"><?= e(time_ago($t['created_at'])) ?></span></td>
  </tr>
  <?php endforeach ?>
</table>
<?php else: ?>
  <p><?= $is_me ? 'You have not hugged any trees yet. <a href="' . url('/trees/new') . '">Hug one now!</a>' : 'No trees hugged yet.' ?></p>
<?php endif ?>

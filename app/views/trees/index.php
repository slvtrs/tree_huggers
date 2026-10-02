<h1>All trees (<?= count($trees) ?>)</h1>
<?php if (!$trees): ?>
  <p>Nothing here yet. <a href="<?= url('/trees/new') ?>">Hug the first tree.</a></p>
<?php else: ?>
<table class="data" cellspacing="0" width="100%">
  <tr><th></th><th>Tree</th><th>Species</th><th>Hugged by</th><th class="hide-mobile">When</th></tr>
  <?php foreach ($trees as $t): ?>
  <tr>
    <td class="thumb-cell"><a href="<?= url('/trees/' . $t['id']) ?>">
      <?php if ($thumb = tree_photo_url($t, true)): ?><img src="<?= e($thumb) ?>" alt="" width="48" height="48" class="thumb">
      <?php else: ?><?= tree_sprite(3, 'class="px thumb-placeholder"') ?><?php endif ?></a></td>
    <td><a href="<?= url('/trees/' . $t['id']) ?>"><?= e($t['title']) ?></a></td>
    <td><i><?= e(implode(', ', array_map('species_label', $t['species']))) ?></i></td>
    <td><?= $t['user'] ? '<a href="' . url('/u/' . $t['user']) . '">' . e($t['user']) . '</a>' : '<span class="muted">anonymous</span>' ?></td>
    <td class="small hide-mobile"><?= e(time_ago($t['created_at'])) ?></td>
  </tr>
  <?php endforeach ?>
</table>
<?php endif ?>

<div class="map-wrap">
  <div id="map" class="map map-big" data-trees-url="<?= url('/api/trees.json') ?>"></div>
  <div class="map-legend small">
    <?= tree_sprite(1, 'class="px"') ?> = a hugged tree. Click one to meet it.
    &nbsp;&middot;&nbsp; <a href="<?= url('/trees/new') ?>">Know a tree? Put it on the map.</a>
  </div>
</div>

<h2>&#9660; Recently hugged</h2>
<?php if (!$trees): ?>
  <p>No trees yet. <a href="<?= url('/trees/new') ?>">Be the first to hug one!</a></p>
<?php else: ?>
<table class="data recent" cellspacing="0">
  <?php foreach (array_slice($trees, 0, 8) as $t): ?>
  <tr>
    <td class="thumb-cell">
      <a href="<?= url('/trees/' . $t['id']) ?>">
      <?php if ($thumb = tree_photo_url($t, true)): ?><img src="<?= e($thumb) ?>" alt="" width="60" height="60" class="thumb">
      <?php else: ?><?= tree_sprite(3, 'class="px thumb-placeholder"') ?><?php endif ?>
      </a>
    </td>
    <td>
      <a href="<?= url('/trees/' . $t['id']) ?>"><b><?= e($t['title']) ?></b></a>
      <?php if ($t['species']): ?><br><i><?= e(implode(', ', array_map('species_label', $t['species']))) ?></i><?php endif ?>
      <br><span class="small muted">by <?= $t['user'] ? '<a href="' . url('/u/' . $t['user']) . '">' . e($t['user']) . '</a>' : 'anonymous' ?>, <?= e(time_ago($t['created_at'])) ?></span>
    </td>
  </tr>
  <?php endforeach ?>
</table>
<p class="small"><a href="<?= url('/trees') ?>">See all <?= count($trees) ?> trees &raquo;</a></p>
<?php endif ?>

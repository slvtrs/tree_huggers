<h1><?= e($tree['title']) ?></h1>
<?php if (!empty($mine_anon)): ?>
  <div class="notice small" <?= $claim_token ? 'data-claim-token="' . e($claim_token) . '" data-claim-id="' . e($tree['id']) . '"' : '' ?>>
    &#9829; You hugged this tree anonymously from this browser, so you can still edit it here.
    <a href="<?= url('/signup') ?>">Sign up</a> or <a href="<?= url('/login') ?>">log in</a> and we will add it to your profile.
  </div>
<?php endif ?>
<table class="show" width="100%" cellspacing="0"><tr>
  <td class="show-photo" valign="top">
    <?php if ($photo = tree_photo_url($tree)): ?>
      <a href="<?= e($photo) ?>"><img src="<?= e($photo) ?>" alt="Photo of <?= e($tree['title']) ?>" class="photo"></a>
    <?php else: ?>
      <div class="no-photo"><?= tree_sprite(8, 'class="px"') ?><br><span class="small muted">no photo yet</span></div>
    <?php endif ?>
  </td>
  <td class="show-meta" valign="top">
    <table class="data" cellspacing="0" width="100%">
      <tr><th>Species</th><td>
        <?php if ($tree['species']): ?>
          <?php foreach ($tree['species'] as $s): ?>
            <a class="chip" href="<?= e(species_inat_url($s)) ?>" target="_blank" rel="noopener"><?= e(species_label($s)) ?></a>
          <?php endforeach ?>
        <?php else: ?><span class="muted">unknown</span><?php endif ?>
      </td></tr>
      <tr><th>Location</th><td>
        <?= e(number_format((float) $tree['lat'], 5)) ?>, <?= e(number_format((float) $tree['lng'], 5)) ?><br>
        <a class="small" href="https://www.openstreetmap.org/?mlat=<?= e($tree['lat']) ?>&amp;mlon=<?= e($tree['lng']) ?>#map=18/<?= e($tree['lat']) ?>/<?= e($tree['lng']) ?>" target="_blank" rel="noopener">open in OpenStreetMap</a>
      </td></tr>
      <tr><th>Hugged by</th><td>
        <?= $owner ? '<a href="' . url('/u/' . $owner['username']) . '">' . e($owner['username']) . '</a>' : '<span class="muted">anonymous</span>' ?>
        <br><span class="small muted"><?= e(gmdate('F j, Y', strtotime($tree['created_at']))) ?></span>
      </td></tr>
    </table>
    <div id="map" class="map map-small" data-tree='<?= e(json_encode(tree_summary($tree), JSON_UNESCAPED_SLASHES)) ?>'></div>
    <?php if ($can_edit): ?>
      <p class="actions">
        <a class="btn" href="<?= url('/trees/' . $tree['id'] . '/edit') ?>">Edit</a>
        <form method="post" action="<?= url('/trees/' . $tree['id'] . '/delete') ?>" class="inline" onsubmit="return confirm('Remove this tree from the map?')"><?= csrf_field() ?><button type="submit" class="danger">Delete</button></form>
      </p>
    <?php endif ?>
  </td>
</tr></table>

<?php if ($tree['description'] !== ''): ?>
<h2>About this tree</h2>
<div class="prose"><?= nl2br(e($tree['description'])) ?></div>
<?php endif ?>

<p class="small"><a href="<?= url('/') ?>">&laquo; back to the map</a></p>

<?php $isNew = $tree['created_at'] === null; $me = current_user(); ?>
<h1><?= $isNew ? 'Hug a tree' : 'Edit tree' ?></h1>
<?php if ($isNew && !$me): ?>
  <p class="notice small">You are hugging anonymously. That is fine! You can still edit this tree from this browser, and if you
  <a href="<?= url('/signup') ?>">sign up</a> or <a href="<?= url('/login') ?>">log in</a> later we will add it to your profile.</p>
<?php endif ?>
<?php if ($errors): ?>
  <div class="flash flash-warn">Please fix the following:<ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach ?></ul></div>
<?php endif ?>

<form method="post" action="<?= $isNew ? url('/trees') : url('/trees/' . $tree['id']) ?>" enctype="multipart/form-data" class="tree-form" id="tree-form">
  <?= csrf_field() ?>
  <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

  <table class="form" cellspacing="0" width="100%">
    <tr>
      <th><label for="title">Name *</label></th>
      <td><input type="text" id="title" name="title" maxlength="80" required value="<?= e($tree['title']) ?>" placeholder="The big oak by the library" size="40">
      <?php if (isset($errors['title'])): ?><div class="err"><?= e($errors['title']) ?></div><?php endif ?></td>
    </tr>
    <tr>
      <th><label for="photo">Photo</label></th>
      <td>
        <?php if ($thumb = tree_photo_url($tree, true)): ?>
          <img src="<?= e($thumb) ?>" alt="" class="thumb" width="80"> current photo
          <label class="small"><input type="checkbox" name="remove_photo" value="1"> remove</label><br>
        <?php endif ?>
        <input type="file" id="photo" name="photo" accept="image/*">
        <div class="hint">JPEG, PNG, GIF or WebP. If the photo has GPS info we will use it for the location.</div>
        <?php if (isset($errors['photo'])): ?><div class="err"><?= e($errors['photo']) ?></div><?php endif ?>
      </td>
    </tr>
    <tr>
      <th>Location *</th>
      <td>
        <div id="locpicker" class="map map-picker" data-lat="<?= e($tree['lat']) ?>" data-lng="<?= e($tree['lng']) ?>"></div>
        <div class="loc-row">
          <button type="button" id="use-location">&#9673; Use my location</button>
          <label>lat <input type="text" name="lat" id="lat" size="11" inputmode="decimal" value="<?= e($tree['lat']) ?>"></label>
          <label>lng <input type="text" name="lng" id="lng" size="11" inputmode="decimal" value="<?= e($tree['lng']) ?>"></label>
        </div>
        <div class="hint">Click the map to drop the tree, drag it to adjust, or type coordinates.</div>
        <?php if (isset($errors['location'])): ?><div class="err"><?= e($errors['location']) ?></div><?php endif ?>
      </td>
    </tr>
    <tr>
      <th>Species</th>
      <td>
        <div id="species-widget" data-search-url="<?= url('/api/species') ?>" data-nearby-url="<?= url('/api/species/nearby') ?>" data-max="<?= TREE_MAX_SPECIES ?>">
          <div id="species-chips"></div>
          <input type="hidden" name="species_json" id="species-json" value="<?= e(json_encode($tree['species'])) ?>">
          <input type="text" id="species-q" placeholder="search, e.g. red maple" size="30" autocomplete="off">
          <div id="species-results" class="results"></div>
          <div id="species-nearby" class="nearby small"><span class="muted">Set a location to see trees commonly found nearby.</span></div>
        </div>
        <div class="hint">Up to <?= TREE_MAX_SPECIES ?> tags. Names come from <a href="https://www.inaturalist.org" target="_blank" rel="noopener">iNaturalist</a>.</div>
      </td>
    </tr>
    <tr>
      <th><label for="description">About</label></th>
      <td><textarea id="description" name="description" rows="6" cols="60" maxlength="3000" placeholder="Why do you love this tree? How is it doing? Does it need anything?"><?= e($tree['description']) ?></textarea>
      <?php if (isset($errors['description'])): ?><div class="err"><?= e($errors['description']) ?></div><?php endif ?></td>
    </tr>
    <tr>
      <th></th>
      <td><button type="submit" class="primary"><?= $isNew ? '&#127795; Put it on the map' : 'Save changes' ?></button>
      &nbsp; <a class="small" href="<?= $isNew ? url('/') : url('/trees/' . $tree['id']) ?>">cancel</a></td>
    </tr>
  </table>
</form>

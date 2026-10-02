<?php $me = current_user(); $flash = flash_take(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($title ? $title . ' - ' . $config['site_name'] : $config['site_name'] . ' - ' . $config['tagline']) ?></title>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode(tree_sprite(1)) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('/assets/vendor/leaflet/leaflet.css') ?>">
<link rel="stylesheet" href="<?= asset('/assets/style.css') ?>">
</head>
<body data-claim-ids='<?= e(json_encode(array_keys(claims_all()))) ?>' data-sync-url="<?= url('/api/claims/sync') ?>">
<div class="page">
  <table class="header" width="100%" cellspacing="0" cellpadding="0"><tr>
    <td class="logo-cell">
      <a class="logo" href="<?= url('/') ?>"><?= tree_sprite(3, 'class="px"') ?> <span>TREE<br>HUGGERS</span></a>
      <div class="tagline"><?= e($config['tagline']) ?></div>
    </td>
    <td class="nav-cell" align="right">
      <span class="music-controls">
        <span id="music-name" class="music-name" aria-live="polite"></span>
      <button type="button" id="music-toggle" class="music" title="Music" aria-label="Toggle music" aria-pressed="false">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 10" width="30" height="25" shape-rendering="crispEdges" class="px">
          <path fill="#1a1a1a" d="M7 0h1v1H7zM6 1h2v1H6zM5 2h3v1H5zM1 3h7v1H1zM1 4h7v1H1zM1 5h7v1H1zM1 6h7v1H1zM5 7h3v1H5zM6 8h2v1H6zM7 9h1v1H7z"/>
          <path class="cone" fill="#3fa34d" d="M6 2h1v1H6zM2 4h5v1H2zM2 5h5v1H2zM6 7h1v1H6z"/>
          <path class="waves" fill="#1a1a1a" d="M9 2h1v1H9zM10 3h1v1H10zM10 4h1v1H10zM10 5h1v1H10zM10 6h1v1H10zM9 7h1v1H9z"/>
          <path class="slash" fill="#c00000" d="M1 9h2v1H1zM2 8h2v1H2zM3 7h2v1H3zM4 6h2v1H4zM5 5h2v1H5zM6 4h2v1H6zM7 3h2v1H7zM8 2h2v1H8zM9 1h2v1H9zM10 0h2v1H10z"/>
        </svg>
      </button>
      <button type="button" id="music-next" class="music" title="Next song" aria-label="Next song">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 10" width="30" height="25" shape-rendering="crispEdges" class="px">
          <path fill="#1a1a1a" d="M1 1h1v8H1zM2 2h1v6H2zM3 3h1v4H3zM4 4h1v2H4zM6 1h1v8H6zM7 2h1v6H7zM8 3h1v4H8zM9 4h1v2H9zM10 1h1v8H10z"/>
          <path fill="#3fa34d" d="M2 3h1v4H2zM3 4h1v2H3zM7 3h1v4H7zM8 4h1v2H8z"/>
        </svg>
      </button>
      </span>
      <div class="nav">
        [ <a href="<?= url('/') ?>">Map</a> |
        <a href="<?= url('/trees') ?>">All Trees</a> |
        <a href="<?= url('/trees/new') ?>">+ Hug a Tree</a> ]
      </div>
      <div class="nav small">
        <?php if ($me): ?>
          logged in as <a href="<?= url('/u/' . $me['username']) ?>"><?= e($me['username']) ?></a>
          <form method="post" action="<?= url('/logout') ?>" class="inline"><?= csrf_field() ?><button class="link" type="submit">log out</button></form>
        <?php else: ?>
          <a href="<?= url('/login') ?>">Log in</a> / <a href="<?= url('/signup') ?>">Sign up</a>
        <?php endif ?>
      </div>
    </td>
  </tr></table>
  <hr class="thick">
  <?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['kind']) ?>">&#9654; <?= e($flash['message']) ?></div>
  <?php endif ?>

  <?= $content ?>

  <hr class="thick">
  <table class="footer" width="100%"><tr>
    <td>
      <?php if (isset($hits)): ?>
        You are visitor <span class="counter"><?= str_pad((string) $hits, 6, '0', STR_PAD_LEFT) ?></span>
      <?php endif ?>
    </td>
    <td align="right" class="small">
      Species data from <a href="https://www.inaturalist.org">iNaturalist</a>. Map data <?= $config['tile_attribution'] ?>.<br>
      Best viewed at 800x600.
    </td>
  </tr></table>
  <marquee scrollamount="3">&#127795; welcome to tree huggers &#127795; every tree deserves a friend &#127795; hug a tree today &#127795;</marquee>
</div>
<script id="th-config" type="application/json"><?= json_encode([
    'base'        => BASE_PATH,
    'tileUrl'     => $config['tile_url'],
    'attribution' => $config['tile_attribution'],
    'center'      => $config['default_center'],
    'zoom'        => $config['default_zoom'],
    'pixelSize'   => $config['pixel_size'],
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<script src="<?= url('/assets/vendor/leaflet/leaflet.js') ?>"></script>
<script src="<?= asset('/assets/pixelmap.js') ?>"></script>
<script src="<?= asset('/assets/music.js') ?>"></script>
<script src="<?= asset('/assets/app.js') ?>"></script>
</body>
</html>

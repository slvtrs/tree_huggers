<?php
declare(strict_types=1);

// ---------- Pages ----------

route('GET', '/', function () {
    return render('home', [
        'trees'   => tree_all(),
        'hits'    => counter_hit('home'),
        'use_map' => true,
    ]);
});

route('GET', '/trees', fn() => render('trees/index', ['trees' => tree_all(), 'title' => 'All trees']));

route('GET', '/trees/new', function () {
    $tree = tree_blank();
    return render('trees/form', ['tree' => $tree, 'errors' => [], 'title' => 'Hug a tree', 'use_map' => true]);
});

route('POST', '/trees', function () {
    csrf_check();
    throttle('tree_create', ...$GLOBALS['config']['limits']['tree_create']);
    if (!empty($_POST['website'])) { // honeypot field, hidden from humans
        redirect('/');
    }
    $tree = tree_blank();
    $tree['id'] = tree_new_id();
    $tree['user'] = current_user()['username'] ?? null;
    [$tree, $errors] = tree_apply_input($tree, $_POST, $_FILES['photo'] ?? null);
    if ($errors) {
        photo_delete($tree['id']);
        $tree['photo'] = $tree['thumb'] = null;
        return render('trees/form', ['tree' => $tree, 'errors' => $errors, 'title' => 'Hug a tree', 'use_map' => true]);
    }
    $token = null;
    if ($tree['user'] === null) {
        // Anonymous: remember this browser as the hugger so it can edit and later claim the tree.
        $token = claim_new_token();
        $tree['claim_hash'] = claim_hash($token);
    }
    tree_save($tree);
    if ($token !== null) {
        claims_add($tree['id'], $token);
    }
    flash('Tree hugged. Thank you for looking after it!');
    redirect('/trees/' . $tree['id']);
});

route('GET', '/trees/{id}', function ($p) {
    $tree = tree_find($p['id']) ?? abort(404, 'That tree is not on our map.');
    $owner = $tree['user'] ? user_find($tree['user']) : null;
    $mineAnon = empty($tree['user']) && claims_verify($tree);
    return render('trees/show', [
        'tree'        => $tree,
        'owner'       => $owner,
        'can_edit'    => tree_can_edit(current_user(), $tree),
        'mine_anon'   => $mineAnon,
        'claim_token' => $mineAnon ? (claims_all()[$tree['id']] ?? null) : null,
        'title'       => $tree['title'],
        'use_map'     => true,
    ]);
});

route('GET', '/trees/{id}/edit', function ($p) {
    $tree = tree_find($p['id']) ?? abort(404);
    tree_can_edit(current_user(), $tree) || abort(403, 'Only the person who hugged this tree can edit it.');
    return render('trees/form', ['tree' => $tree, 'errors' => [], 'title' => 'Edit ' . $tree['title'], 'use_map' => true]);
});

route('POST', '/trees/{id}', function ($p) {
    csrf_check();
    throttle('tree_edit', ...$GLOBALS['config']['limits']['tree_edit']);
    $tree = tree_find($p['id']) ?? abort(404);
    tree_can_edit(current_user(), $tree) || abort(403, 'Only the person who hugged this tree can edit it.');
    [$updated, $errors] = tree_apply_input($tree, $_POST, $_FILES['photo'] ?? null);
    if ($errors) {
        return render('trees/form', ['tree' => $updated, 'errors' => $errors, 'title' => 'Edit ' . $tree['title'], 'use_map' => true]);
    }
    tree_save($updated);
    flash('Tree updated.');
    redirect('/trees/' . $tree['id']);
});

route('POST', '/trees/{id}/delete', function ($p) {
    csrf_check();
    $tree = tree_find($p['id']) ?? abort(404);
    tree_can_edit(current_user(), $tree) || abort(403);
    tree_delete($tree);
    claims_remove([$tree['id']]);
    flash('Tree removed from the map.');
    redirect($tree['user'] ? '/u/' . $tree['user'] : '/');
});

// ---------- Users ----------

route('GET', '/signup', function () {
    if (current_user()) redirect('/');
    return render('users/signup', ['errors' => [], 'username' => '', 'title' => 'Sign up']);
});

route('POST', '/signup', function () {
    csrf_check();
    throttle('signup', ...$GLOBALS['config']['limits']['signup']);
    if (!empty($_POST['website'])) redirect('/');
    [$user, $errors] = user_create((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));
    if (!$user) {
        return render('users/signup', ['errors' => $errors, 'username' => $_POST['username'] ?? '', 'title' => 'Sign up']);
    }
    login_user($user);
    $claimed = claims_apply($user);
    flash('Welcome, ' . $user['username'] . '!' . ($claimed ? claims_flash_text($claimed) : ' Go hug your first tree.'));
    redirect($claimed ? '/u/' . $user['username'] : '/trees/new');
});

route('GET', '/login', function () {
    if (current_user()) redirect('/');
    return render('users/login', ['error' => null, 'username' => '', 'title' => 'Log in']);
});

route('POST', '/login', function () {
    csrf_check();
    throttle('login', ...$GLOBALS['config']['limits']['login']);
    [$user, $error] = user_authenticate((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
    if (!$user) {
        return render('users/login', ['error' => $error, 'username' => $_POST['username'] ?? '', 'title' => 'Log in']);
    }
    login_user($user);
    $claimed = claims_apply($user);
    if ($claimed) {
        flash('Welcome back!' . claims_flash_text($claimed));
    }
    $next = $_SESSION['after_login'] ?? null;
    unset($_SESSION['after_login']);
    $nextPath = $next ? (parse_url($next, PHP_URL_PATH) ?: '') : '';
    redirect($nextPath && str_starts_with($nextPath, BASE_PATH . '/') ? $next : '/u/' . $user['username']);
});

route('POST', '/logout', function () {
    csrf_check();
    logout_user();
    redirect('/');
});

route('GET', '/u/{username}', function ($p) {
    $user = user_find($p['username']) ?? abort(404, 'No such tree hugger.');
    $trees = trees_by_user($user['username']);
    return render('users/profile', [
        'user'    => user_public($user),
        'trees'   => $trees,
        'is_me'   => (current_user()['username'] ?? null) === $user['username'],
        'title'   => $user['username'],
        'use_map' => true,
    ]);
});

// ---------- JSON API (used by the map, the species picker, and claim sync) ----------

// The browser mirrors its claim tokens into localStorage; if the cookie was
// lost, app.js posts them back here so the server can re-issue the cookie.
route('POST', '/api/claims/sync', function () {
    throttle('claims_sync', $GLOBALS['config']['limits']['claims_sync'][0], $GLOBALS['config']['limits']['claims_sync'][1], true);
    $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        json_response(['error' => 'bad token'], 403);
    }
    $body = json_decode((string) file_get_contents('php://input', false, null, 0, 64 * 1024), true);
    $incoming = is_array($body['claims'] ?? null) ? $body['claims'] : [];
    $claims = claims_all();
    $drop = [];
    $added = 0;
    foreach (array_slice($incoming, 0, CLAIMS_MAX, true) as $id => $token) {
        if (!is_string($id) || !db_valid_id($id) || !claim_token_valid($token)) {
            continue;
        }
        $tree = tree_find($id);
        if (!$tree || !empty($tree['user']) || !claims_verify($tree, $token)) {
            $drop[] = $id; // gone, claimed elsewhere, or forged: browser should forget it
            continue;
        }
        if (!isset($claims[$id])) {
            $claims[$id] = $token;
            $added++;
        }
    }
    if ($added) {
        claims_store($claims);
    }
    $claimed = 0;
    if ($user = current_user()) {
        $claimed = claims_apply($user);
    }
    json_response(['added' => $added, 'claimed' => $claimed, 'drop' => $drop, 'held' => array_keys(claims_all())]);
});

route('GET', '/api/trees.json', function () {
    $out = [];
    foreach (tree_all() as $t) {
        if ($t['lat'] !== null && $t['lng'] !== null) {
            $out[] = tree_summary($t);
        }
    }
    json_response($out);
});

route('GET', '/api/species', function () {
    throttle('api', $GLOBALS['config']['limits']['api'][0], $GLOBALS['config']['limits']['api'][1], true);
    json_response(species_search((string) ($_GET['q'] ?? '')));
});

route('GET', '/api/species/nearby', function () {
    throttle('api', $GLOBALS['config']['limits']['api'][0], $GLOBALS['config']['limits']['api'][1], true);
    $lat = $_GET['lat'] ?? null;
    $lng = $_GET['lng'] ?? null;
    if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
        json_response(['error' => 'lat and lng required'], 400);
    }
    json_response(species_nearby((float) $lat, (float) $lng));
});

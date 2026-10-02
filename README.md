# Tree Huggers

A small web app for people who protect and care for trees. Hug a tree with a
photo, a location, species tags and a story; browse everyone's trees on an
8-bit map; keep your trees on a shareable profile.

Plain PHP (8.0+), no framework, no database server: every tree and user is a
JSON file. Built to run on ordinary cPanel shared hosting.

## Layout

```
app/        PHP code (lib/, views/, routes.php, config.php)   -> keep OUTSIDE the web root
data/       the "database": trees/, users/, cache/, locks/   -> keep OUTSIDE the web root, writable
public/     the web root: index.php, .htaccess, assets/, uploads/ (writable)
```

`public/index.php` looks for `../app/bootstrap.php` first, then `./app/bootstrap.php`.

## Local development

```
php -S localhost:8000 -t public public/router.php
```

Then open http://localhost:8000. Create `app/config.local.php` to override
anything in `app/config.php`, e.g. `<?php return ['debug' => true];`.

## Deploying to cPanel

1. In File Manager (or over SFTP), upload `app/` and `data/` into your home
   directory, **beside** `public_html` (so you get `/home/USER/app` and
   `/home/USER/data`).
2. Upload the *contents* of `public/` into `public_html/` (so `index.php`,
   `.htaccess`, `assets/` and `uploads/` sit directly inside `public_html`).
3. Make sure `data/` (and everything under it) and `public_html/uploads/` are
   writable by PHP. On cPanel PHP usually runs as your user, so the default
   permissions (755) are fine. If uploads fail, try 775 on those two folders.
4. In **MultiPHP Manager** pick PHP 8.1 or newer. In **MultiPHP INI Editor**
   raise `upload_max_filesize` and `post_max_size` to at least `12M` if you
   want big phone photos to work.
5. Visit the site. Species search needs outbound HTTPS from PHP (curl or
   `allow_url_fopen`), which is on by default on cPanel.

If your host does not let you put files beside `public_html`, upload
`app/` and `data/` *inside* `public_html` instead. The `.htaccess` files deny
all web access to both folders.

To run in a subdirectory (e.g. `example.com/trees/`), put the `public/`
contents in `public_html/trees/` and `app/` + `data/` wherever you like relative
to it; paths are detected automatically.

## Data model

```
data/trees/<id>.json   { id, title, description, lat, lng,
                         species: [{ id, name, common_name }],   # iNaturalist taxon ids
                         photo, thumb, user, created_at, updated_at }
data/users/<name>.json { username, password_hash, bio, created_at, ... }
public/uploads/<id>.jpg, <id>_t.jpg
```

Passwords are hashed with `password_hash()` (argon2id or bcrypt). Writes are
atomic (temp file + rename) and guarded with `flock`. Backing up the site is
copying `data/` and `public/uploads/`.

## Credits

Map tiles from [OpenStreetMap](https://www.openstreetmap.org/copyright),
pixelated client-side. Species names and nearby-tree suggestions from the
[iNaturalist](https://www.inaturalist.org) public API. Map rendering by
[Leaflet](https://leafletjs.com).

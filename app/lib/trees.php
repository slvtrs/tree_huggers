<?php
declare(strict_types=1);

const TREE_MAX_SPECIES = 5;

function tree_blank(): array
{
    return [
        'id'          => '',
        'title'       => '',
        'description' => '',
        'lat'         => null,
        'lng'         => null,
        'species'     => [],
        'photo'       => null,
        'thumb'       => null,
        'user'        => null,
        'created_at'  => null,
        'updated_at'  => null,
    ];
}

function tree_new_id(): string
{
    do {
        $id = str_pad(substr(base_convert(bin2hex(random_bytes(6)), 16, 36), 0, 8), 8, '0');
    } while (db_exists('trees', $id));
    return $id;
}

function tree_find(string $id): ?array
{
    $tree = db_read('trees', $id);
    return $tree ? $tree + tree_blank() : null;
}

/** @return array<int, array> newest first */
function tree_all(): array
{
    $trees = db_all('trees');
    usort($trees, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return $trees;
}

function trees_by_user(string $username): array
{
    return array_values(array_filter(tree_all(), fn($t) => ($t['user'] ?? null) === $username));
}

function tree_save(array $tree): void
{
    $tree['updated_at'] = db_now();
    $tree['created_at'] ??= $tree['updated_at'];
    db_write('trees', $tree['id'], $tree);
}

function tree_delete(array $tree): void
{
    photo_delete($tree['id']);
    db_delete('trees', $tree['id']);
}

function tree_can_edit(?array $user, array $tree): bool
{
    if ($user !== null && !empty($tree['user']) && $tree['user'] === $user['username']) {
        return true;
    }
    return claims_verify($tree); // anonymous tree hugged from this browser
}

function tree_photo_url(array $tree, bool $thumb = false): ?string
{
    $name = $thumb ? ($tree['thumb'] ?? null) : ($tree['photo'] ?? null);
    return $name ? upload_url($name, $tree['updated_at'] ?? null) : null;
}

/** Compact representation for the map. */
function tree_summary(array $tree): array
{
    return [
        'id'      => $tree['id'],
        'title'   => $tree['title'],
        'lat'     => $tree['lat'],
        'lng'     => $tree['lng'],
        'species' => array_map(fn($s) => species_label($s), $tree['species'] ?? []),
        'thumb'   => tree_photo_url($tree, true),
        'url'     => url('/trees/' . $tree['id']),
        'user'    => $tree['user'],
        'when'    => time_ago($tree['created_at'] ?? ''),
    ];
}

/**
 * Apply submitted form data (and an optional photo) to a tree.
 * @return array{0: array, 1: array<string,string>} [tree, errors]
 */
function tree_apply_input(array $tree, array $input, ?array $photoFile): array
{
    $errors = [];

    $title = trim((string) ($input['title'] ?? ''));
    $title = preg_replace('/\s+/', ' ', $title);
    if ($title === '' || mb_strlen($title) > 80) {
        $errors['title'] = 'Give your tree a name (up to 80 characters).';
    }
    $tree['title'] = mb_substr($title, 0, 80);

    $desc = trim(str_replace("\r\n", "\n", (string) ($input['description'] ?? '')));
    if (mb_strlen($desc) > 3000) {
        $errors['description'] = 'Description is too long (3000 characters max).';
    }
    $tree['description'] = mb_substr($desc, 0, 3000);

    // Species: JSON from the picker widget, validated here.
    $species = [];
    $raw = json_decode((string) ($input['species_json'] ?? '[]'), true);
    if (is_array($raw)) {
        foreach (array_slice($raw, 0, TREE_MAX_SPECIES) as $s) {
            if (!is_array($s) || empty($s['id']) || empty($s['name'])) {
                continue;
            }
            $species[] = [
                'id'          => (int) $s['id'],
                'name'        => mb_substr(trim((string) $s['name']), 0, 100),
                'common_name' => mb_substr(trim((string) ($s['common_name'] ?? '')), 0, 100),
            ];
        }
    }
    $tree['species'] = $species;

    // Photo (optional). May supply coordinates via EXIF GPS.
    $gps = null;
    $photo = photo_process($photoFile, $tree['id']);
    if (!empty($photo['error'])) {
        $errors['photo'] = $photo['error'];
    } elseif ($photo['ok']) {
        $names = photo_filenames($tree['id']);
        $tree['photo'] = $names['full'];
        $tree['thumb'] = $names['thumb'];
        $gps = $photo['gps'] ?? null;
    }
    if (!empty($input['remove_photo']) && empty($photo['ok'])) {
        photo_delete($tree['id']);
        $tree['photo'] = $tree['thumb'] = null;
    }

    // Coordinates.
    $latIn = trim((string) ($input['lat'] ?? ''));
    $lngIn = trim((string) ($input['lng'] ?? ''));
    if ($latIn === '' && $lngIn === '' && $gps) {
        $tree['lat'] = $gps['lat'];
        $tree['lng'] = $gps['lng'];
    } elseif (is_numeric($latIn) && is_numeric($lngIn) && abs((float) $latIn) <= 90 && abs((float) $lngIn) <= 180) {
        $tree['lat'] = round((float) $latIn, 6);
        $tree['lng'] = round((float) $lngIn, 6);
    } else {
        $errors['location'] = $latIn === '' && $lngIn === ''
            ? 'Where is this tree? Click the map, use your location, or upload a photo with GPS info.'
            : 'Those coordinates do not look right.';
    }

    return [$tree, $errors];
}

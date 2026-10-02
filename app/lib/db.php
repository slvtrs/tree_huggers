<?php
declare(strict_types=1);

// A tiny document store: one pretty-printed JSON file per record,
// written atomically (temp file + rename). Collections are directories.

function db_dir(string $collection): string
{
    global $config;
    $dir = $config['data_dir'] . '/' . $collection;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function db_valid_id(string $id): bool
{
    return (bool) preg_match('/^[a-z0-9_-]{1,40}$/', $id);
}

function db_path(string $collection, string $id): string
{
    if (!db_valid_id($id)) {
        throw new InvalidArgumentException('Invalid record id');
    }
    return db_dir($collection) . '/' . $id . '.json';
}

function db_read(string $collection, string $id): ?array
{
    if (!db_valid_id($id)) {
        return null;
    }
    $path = db_path($collection, $id);
    if (!is_file($path)) {
        return null;
    }
    $doc = json_decode((string) file_get_contents($path), true);
    return is_array($doc) ? $doc : null;
}

function db_write(string $collection, string $id, array $doc): void
{
    $path = db_path($collection, $id);
    $tmp  = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($tmp, $json) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException("Could not write $collection/$id");
    }
}

function db_delete(string $collection, string $id): void
{
    $path = db_path($collection, $id);
    if (is_file($path)) {
        unlink($path);
    }
}

function db_exists(string $collection, string $id): bool
{
    return db_valid_id($id) && is_file(db_path($collection, $id));
}

/** @return array<int, array> */
function db_all(string $collection): array
{
    $docs = [];
    foreach (glob(db_dir($collection) . '/*.json') ?: [] as $file) {
        $doc = json_decode((string) file_get_contents($file), true);
        if (is_array($doc)) {
            $docs[] = $doc;
        }
    }
    return $docs;
}

/** Acquire an exclusive advisory lock; pass the handle to db_unlock(). */
function db_lock(string $name)
{
    $handle = fopen(db_dir('locks') . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $name) . '.lock', 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        throw new RuntimeException('Could not acquire lock');
    }
    return $handle;
}

function db_unlock($handle): void
{
    flock($handle, LOCK_UN);
    fclose($handle);
}

function db_now(): string
{
    return gmdate('c');
}

/** Old-school hit counter. Returns the new count. */
function counter_hit(string $name = 'home'): int
{
    global $config;
    $lock = db_lock('counter');
    $path = $config['data_dir'] . '/counter.json';
    $counts = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    $counts[$name] = ($counts[$name] ?? 0) + 1;
    file_put_contents($path, json_encode($counts));
    db_unlock($lock);
    return (int) $counts[$name];
}

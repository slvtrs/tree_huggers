<?php
declare(strict_types=1);

// Anonymous hugs get a secret claim token. The browser keeps it in a
// long-lived cookie (mirrored into localStorage by app.js so it survives
// cookie loss); the tree stores only a hash. Whoever presents the token can
// edit that tree and, on signup or login, has it attributed to their profile.

const CLAIMS_COOKIE = 'th_claims';
const CLAIMS_MAX = 60;
const CLAIMS_TTL = 86400 * 365;

function claim_hash(string $token): string
{
    return hash('sha256', $token);
}

function claim_new_token(): string
{
    return bin2hex(random_bytes(16));
}

function claim_token_valid($token): bool
{
    return is_string($token) && (bool) preg_match('/^[a-f0-9]{32}$/', $token);
}

/** @return array<string,string> tree id => token currently held by this browser */
function claims_all(): array
{
    $raw = $_COOKIE[CLAIMS_COOKIE] ?? '';
    $data = $raw !== '' ? json_decode($raw, true) : null;
    $out = [];
    if (is_array($data)) {
        foreach ($data as $id => $token) {
            if (is_string($id) && db_valid_id($id) && claim_token_valid($token)) {
                $out[$id] = $token;
            }
        }
    }
    return $out;
}

function claims_store(array $claims): void
{
    $claims = array_slice($claims, -CLAIMS_MAX, null, true);
    $value = $claims ? json_encode($claims) : '';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if (!headers_sent()) {
        setcookie(CLAIMS_COOKIE, $value, [
            'expires'  => $claims ? time() + CLAIMS_TTL : time() - 3600,
            'path'     => BASE_PATH ?: '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    $_COOKIE[CLAIMS_COOKIE] = $value; // so later reads in this request see it
}

function claims_add(string $id, string $token): void
{
    $claims = claims_all();
    $claims[$id] = $token;
    claims_store($claims);
}

function claims_remove(array $ids): void
{
    $claims = claims_all();
    foreach ($ids as $id) {
        unset($claims[$id]);
    }
    claims_store($claims);
}

/** Does this browser hold the valid token for this (anonymous) tree? */
function claims_verify(array $tree, ?string $token = null): bool
{
    if (!empty($tree['user']) || empty($tree['claim_hash'])) {
        return false;
    }
    $token = $token ?? (claims_all()[$tree['id']] ?? null);
    return claim_token_valid($token) && hash_equals($tree['claim_hash'], claim_hash($token));
}

/** Attribute every claimable anonymous tree this browser holds to $user. Returns how many. */
function claims_apply(array $user): int
{
    $count = 0;
    $drop = [];
    foreach (claims_all() as $id => $token) {
        $tree = tree_find($id);
        if (!$tree || !empty($tree['user'])) {
            $drop[] = $id; // deleted, or already attributed
            continue;
        }
        if (claims_verify($tree, $token)) {
            unset($tree['claim_hash']);
            $tree['user'] = $user['username'];
            tree_save($tree);
            $count++;
            $drop[] = $id;
        }
    }
    if ($drop) {
        claims_remove($drop);
    }
    return $count;
}

function claims_flash_text(int $n): string
{
    return $n === 1 ? ' We added the tree you hugged anonymously to your profile.'
                    : " We added the $n trees you hugged anonymously to your profile.";
}

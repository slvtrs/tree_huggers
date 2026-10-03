<?php
declare(strict_types=1);

// Abuse controls: per-IP rate limits (file-based, no database needed), text
// sanitizing that keeps JSON and HTML safe, and a Content-Security-Policy.

/** Best-effort client IP. Cloudflare sits in front of the site, so prefer its header. */
function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', (string) $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Sliding-window rate limit. Returns true if the action is allowed.
 * $bucket names the action (e.g. "tree_create"); counts are kept per bucket+IP.
 */
function rate_limit(string $bucket, int $max, int $windowSeconds, ?string $who = null): bool
{
    $who = $who ?? client_ip();
    $dir = db_dir('ratelimit');
    $file = $dir . '/' . sha1($bucket . '|' . $who) . '.json';
    $now = time();
    $lock = db_lock('rl_' . substr(sha1($file), 0, 12));
    try {
        $hits = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        $hits = array_values(array_filter($hits, fn($t) => is_int($t) && $t > $now - $windowSeconds));
        if (count($hits) >= $max) {
            return false;
        }
        $hits[] = $now;
        file_put_contents($file, json_encode($hits));
    } finally {
        db_unlock($lock);
    }
    if (random_int(1, 200) === 1) {
        rate_limit_prune($dir);
    }
    return true;
}

function rate_limit_prune(string $dir): void
{
    $cutoff = time() - 86400;
    foreach (glob($dir . '/*.json') ?: [] as $f) {
        if (@filemtime($f) < $cutoff) {
            @unlink($f);
        }
    }
}

/** Enforce a limit or stop with 429. */
function throttle(string $bucket, int $max, int $windowSeconds, bool $json = false): void
{
    if (rate_limit($bucket, $max, $windowSeconds)) {
        return;
    }
    header('Retry-After: ' . $windowSeconds);
    if ($json) {
        json_response(['error' => 'Too many requests. Please slow down.'], 429);
    }
    abort(429, 'Whoa there, that is a lot of requests. Take a breath and try again in a little while.');
}

/**
 * Make user text safe to store and show: valid UTF-8 only, control characters
 * removed (newlines/tabs kept when $multiline), whitespace tidied, length capped.
 */
function clean_text($value, int $max, bool $multiline = false): string
{
    $s = (string) $value;
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = (string) mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
    $s = str_replace(["\r\n", "\r"], "\n", $s);
    $s = preg_replace($multiline ? '/[^\P{C}\n\t]+/u' : '/\p{C}+/u', '', $s) ?? '';
    $s = $multiline ? preg_replace('/\n{3,}/', "\n\n", $s) : preg_replace('/\s+/u', ' ', $s);
    return mb_substr(trim((string) $s), 0, $max);
}

function count_urls(string $s): int
{
    return preg_match_all('~(?:https?://|www\.)\S+~i', $s);
}

/** Security headers, including a CSP that allows exactly what the site uses. */
function send_security_headers(array $config): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), payment=()');
    if (empty($config['csp'])) {
        return;
    }
    $tileHost = parse_url($config['tile_url'], PHP_URL_HOST) ?: 'tile.openstreetmap.org';
    $gaScript = !empty($config['ga_script']) ? ' ' . (parse_url($config['ga_script'], PHP_URL_SCHEME) . '://' . parse_url($config['ga_script'], PHP_URL_HOST)) : '';
    $csp = [
        "default-src 'self'",
        "script-src 'self' https://www.googletagmanager.com https://static.cloudflareinsights.com" . $gaScript,
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com",
        "img-src 'self' data: https://$tileHost https://*.tile.openstreetmap.org https://inaturalist-open-data.s3.amazonaws.com https://static.inaturalist.org https://*.google-analytics.com https://*.googletagmanager.com",
        "connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com https://cloudflareinsights.com",
        "media-src 'self' data:", // the silent clip that unlocks audio on iOS is a data: URI
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));
}

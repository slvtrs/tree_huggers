<?php
declare(strict_types=1);

// Species lookups proxy the public iNaturalist API and cache results on disk,
// so the browser never talks to iNaturalist directly and we stay well within
// their rate limits.

const SPECIES_PLANTAE_ID = 47126;

function http_get_json(string $url): ?array
{
    $ua = 'TreeHuggers/1.0 (+' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ')';
    $body = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($status >= 400) {
            $body = false;
        }
    } elseif (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => ['timeout' => 10, 'header' => "User-Agent: $ua\r\nAccept: application/json\r\n"]]);
        $body = @file_get_contents($url, false, $ctx);
    }
    if ($body === false) {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

function cache_get(string $key)
{
    global $config;
    $path = db_dir('cache') . '/' . sha1($key) . '.json';
    if (!is_file($path) || filemtime($path) < time() - $config['cache_ttl']) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);
    return $data['v'] ?? null;
}

function cache_set(string $key, $value): void
{
    $path = db_dir('cache') . '/' . sha1($key) . '.json';
    file_put_contents($path, json_encode(['k' => $key, 'v' => $value]), LOCK_EX);
}

function cached_json(string $url): ?array
{
    $hit = cache_get($url);
    if ($hit !== null) {
        return $hit;
    }
    $data = http_get_json($url);
    if ($data !== null) {
        cache_set($url, $data);
    }
    return $data;
}

function species_normalize(array $taxon, ?int $count = null): array
{
    $out = [
        'id'          => (int) ($taxon['id'] ?? 0),
        'name'        => (string) ($taxon['name'] ?? ''),
        'common_name' => (string) ($taxon['preferred_common_name'] ?? ''),
        'rank'        => (string) ($taxon['rank'] ?? ''),
        'photo'       => $taxon['default_photo']['square_url'] ?? null,
    ];
    if ($count !== null) {
        $out['count'] = $count;
    }
    return $out;
}

/** Autocomplete plant taxa by name. */
function species_search(string $q): array
{
    global $config;
    $q = trim(mb_substr($q, 0, 60));
    if (mb_strlen($q) < 2) {
        return [];
    }
    $url = $config['inat_api'] . '/taxa/autocomplete?' . http_build_query([
        'q'         => $q,
        'taxon_id'  => SPECIES_PLANTAE_ID,
        'is_active' => 'true',
        'per_page'  => 15,
    ]);
    $data = cached_json($url);
    $out = [];
    foreach ($data['results'] ?? [] as $t) {
        // Belt and braces: only plants, only ranks that make sense as a tag.
        if (!in_array(SPECIES_PLANTAE_ID, $t['ancestor_ids'] ?? [], true)) continue;
        if (!in_array($t['rank'] ?? '', ['species', 'genus', 'subspecies', 'variety', 'hybrid', 'form'], true)) continue;
        $out[] = species_normalize($t);
        if (count($out) >= 10) break;
    }
    return $out;
}

/** Tree species most often observed near a point, from research-grade iNaturalist observations. */
function species_nearby(float $lat, float $lng): array
{
    global $config;
    $url = $config['inat_api'] . '/observations/species_counts?' . http_build_query([
        'lat'           => round($lat, 1),
        'lng'           => round($lng, 1),
        'radius'        => $config['nearby_radius_km'],
        'taxon_id'      => implode(',', $config['tree_taxa']),
        'quality_grade' => 'research',
        'per_page'      => 15,
    ]);
    $data = cached_json($url);
    $out = [];
    foreach ($data['results'] ?? [] as $r) {
        if (!empty($r['taxon'])) {
            $out[] = species_normalize($r['taxon'], (int) ($r['count'] ?? 0));
        }
    }
    return $out;
}

function species_label(array $s): string
{
    if ($s['common_name'] !== '' && strcasecmp($s['common_name'], $s['name']) !== 0) {
        return $s['common_name'] . ' (' . $s['name'] . ')';
    }
    return $s['name'];
}

function species_inat_url(array $s): string
{
    return 'https://www.inaturalist.org/taxa/' . (int) $s['id'];
}

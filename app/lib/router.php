<?php
declare(strict_types=1);

$GLOBALS['routes'] = [];

function route(string $method, string $pattern, callable $handler): void
{
    $GLOBALS['routes'][] = [$method, $pattern, $handler];
}

function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (BASE_PATH !== '' && str_starts_with($uri, BASE_PATH)) {
        $uri = substr($uri, strlen(BASE_PATH));
    }
    $uri = '/' . trim($uri, '/');
    return $uri === '/index.php' ? '/' : $uri;
}

function dispatch(string $method, string $path): void
{
    foreach ($GLOBALS['routes'] as [$m, $pattern, $handler]) {
        if ($m !== $method) {
            continue;
        }
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        if (preg_match($regex, $path, $match)) {
            $params = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
            $params = array_map('rawurldecode', $params);
            $out = $handler($params);
            if (is_string($out)) {
                echo $out;
            }
            return;
        }
    }
    abort(404);
}

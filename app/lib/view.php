<?php
declare(strict_types=1);

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

/** URL for a file under public/, with its modification time appended so browsers never serve a stale copy. */
function asset(string $path): string
{
    $file = PUBLIC_DIR . '/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '0';
    return url($path) . '?v=' . $v;
}

function upload_url(string $filename, ?string $version = null): string
{
    global $config;
    $u = url($config['uploads_url'] . '/' . rawurlencode($filename));
    return $version ? $u . '?v=' . rawurlencode($version) : $u;
}

function render(string $view, array $vars = [], ?string $layout = 'layout'): string
{
    global $config;
    $vars += ['title' => null, 'use_map' => false];
    $content = render_partial($view, $vars);
    if ($layout === null) {
        return $content;
    }
    return render_partial($layout, $vars + ['content' => $content, 'config' => $config]);
}

function render_partial(string $view, array $vars = []): string
{
    $file = APP_DIR . '/views/' . $view . '.php';
    if (!is_file($file)) {
        throw new RuntimeException("Missing view: $view");
    }
    extract($vars, EXTR_SKIP);
    ob_start();
    include $file;
    return (string) ob_get_clean();
}

function redirect(string $path, int $code = 303): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)), true, $code);
    exit;
}

function abort(int $code, string $message = ''): void
{
    http_response_code($code);
    $defaults = [400 => 'Bad request', 403 => 'Forbidden', 404 => 'Not found', 429 => 'Slow down'];
    echo render('error', ['code' => $code, 'message' => $message ?: ($defaults[$code] ?? 'Error'), 'title' => $defaults[$code] ?? 'Error']);
    exit;
}

function json_response($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function flash(string $message, string $kind = 'ok'): void
{
    $_SESSION['flash'] = ['message' => $message, 'kind' => $kind];
}

function flash_take(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function time_ago(string $iso): string
{
    $t = strtotime($iso);
    if ($t === false) {
        return $iso;
    }
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . ' hr ago';
    if ($d < 86400 * 30) return floor($d / 86400) . ' days ago';
    return gmdate('M j, Y', $t);
}

/** Render a tiny pixel-art SVG from a text pattern. Keys map chars to colors. */
function pixel_svg(array $rows, array $colors, int $scale = 3, string $attrs = ''): string
{
    $h = count($rows);
    $w = max(array_map('strlen', $rows));
    $out = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . ($w * $scale) . '" height="' . ($h * $scale) . '" shape-rendering="crispEdges" ' . $attrs . '>';
    foreach ($rows as $y => $row) {
        for ($x = 0; $x < strlen($row); $x++) {
            $c = $colors[$row[$x]] ?? null;
            if ($c) {
                $out .= '<rect x="' . $x . '" y="' . $y . '" width="1" height="1" fill="' . $c . '"/>';
            }
        }
    }
    return $out . '</svg>';
}

function tree_sprite(int $scale = 3, string $attrs = ''): string
{
    $rows = [
        '......OOOO......',
        '....OOGGGGOO....',
        '...OGGLLGGGGO...',
        '..OGGLLLGGGGGO..',
        '..OGGGLGGGGGGO..',
        '.OGGGGGGGGGGGGO.',
        '.OGGGGGGGDDGGGO.',
        '.OGGGGGGGGDGGGO.',
        '..OGGGGGGGGGGO..',
        '...OGGGGDDGGO...',
        '....OOGGGGOO....',
        '......OTTO......',
        '......OTTO......',
        '......OTTO......',
        '.....OTTTTO.....',
        '.....OOOOOO.....',
    ];
    $colors = ['O' => '#1b2a1c', 'G' => '#3fa34d', 'L' => '#8fe388', 'D' => '#2a7a37', 'T' => '#7a4a24'];
    return pixel_svg($rows, $colors, $scale, $attrs);
}

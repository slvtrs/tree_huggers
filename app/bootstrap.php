<?php
declare(strict_types=1);

define('APP_DIR', __DIR__);
if (!defined('PUBLIC_DIR')) {
    define('PUBLIC_DIR', dirname(__DIR__) . '/public');
}

$config = require APP_DIR . '/config.php';
if (is_file(APP_DIR . '/config.local.php')) {
    $config = array_replace($config, require APP_DIR . '/config.local.php');
}

require APP_DIR . '/lib/polyfill.php';

date_default_timezone_set('UTC');
ini_set('display_errors', $config['debug'] ? '1' : '0');
error_reporting(E_ALL);

// Base path lets the app live in a subdirectory (e.g. /trees/) without changes.
// Set 'base_path' in config to force it; otherwise it is derived from the script
// location. If the repo root forwards requests into public/ (see the root
// .htaccess), the public/ segment is dropped so links stay clean.
if (!empty($config['base_path'])) {
    define('BASE_PATH', rtrim($config['base_path'], '/'));
} else {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($scriptDir !== '' && !str_starts_with($reqPath . '/', $scriptDir . '/') && str_ends_with($scriptDir, '/public')) {
        $scriptDir = substr($scriptDir, 0, -strlen('/public'));
    }
    define('BASE_PATH', $scriptDir);
}

require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/view.php';
require APP_DIR . '/lib/router.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/photos.php';
require APP_DIR . '/lib/species.php';
require APP_DIR . '/lib/trees.php';
require APP_DIR . '/lib/claims.php';

foreach (['trees', 'users', 'cache', 'locks'] as $dir) {
    db_dir($dir);
}
if (!is_dir($config['uploads_dir'])) {
    @mkdir($config['uploads_dir'], 0755, true);
}

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('treehuggers');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => BASE_PATH ?: '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Error handling: exceptions render the error page; if even that fails, or a
// fatal error slips past, emit a plain-text message instead of a blank 500.
// Details are shown only when 'debug' is on, and always written to data/error.log.
function th_log_error(string $text): void
{
    global $config;
    @file_put_contents($config['data_dir'] . '/error.log', '[' . gmdate('c') . '] ' . $text . "\n", FILE_APPEND | LOCK_EX);
    error_log($text);
}

set_exception_handler(function (Throwable $e) use ($config) {
    th_log_error((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    $msg = $config['debug'] ? (string) $e : 'Something fell out of the tree. Try again.';
    try {
        echo render('error', ['code' => 500, 'message' => $msg, 'title' => 'Error']);
    } catch (Throwable $inner) {
        th_log_error('Error page failed too: ' . $inner);
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "Tree Huggers hit an error.\n\n", $msg, "\n\n", $config['debug'] ? "While rendering the error page:\n" . $inner : '';
    }
    exit;
});

register_shutdown_function(function () use ($config) {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $text = sprintf('%s in %s:%d', $err['message'], $err['file'], $err['line']);
        th_log_error('Fatal: ' . $text);
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "Tree Huggers hit a fatal error.\n", $config['debug'] ? $text : 'Check data/error.log for details.', "\n";
    }
});

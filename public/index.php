<?php
declare(strict_types=1);

// Friendly message instead of a parse error on very old PHP. Change the version
// in cPanel's MultiPHP Manager; 8.1 or newer is recommended.
if (PHP_VERSION_ID < 70400) {
    http_response_code(500);
    header('Content-Type: text/plain');
    exit("Tree Huggers needs PHP 7.4 or newer (8.1+ recommended). This server is running PHP " . PHP_VERSION . ".\nIn cPanel, open MultiPHP Manager, select this domain, and choose a newer PHP version.\n");
}

define('PUBLIC_DIR', __DIR__);

// app/ normally sits beside the web root (../app). If everything was uploaded
// into public_html instead, it will be found at ./app (protected by .htaccess).
foreach ([dirname(__DIR__) . '/app', __DIR__ . '/app'] as $dir) {
    if (is_file($dir . '/bootstrap.php')) {
        require $dir . '/bootstrap.php';
        break;
    }
}
if (!defined('APP_DIR')) {
    http_response_code(500);
    exit('Tree Huggers: could not find the app/ directory.');
}

require APP_DIR . '/routes.php';
dispatch($_SERVER['REQUEST_METHOD'], request_path());

<?php

declare(strict_types=1);

// Serve static files when using PHP's built-in dev server
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($path !== '/' && file_exists(__DIR__ . $path)) {
        return false;
    }
}

define('ROOT_DIR', dirname(__DIR__));

require ROOT_DIR . '/src/bootstrap.php';

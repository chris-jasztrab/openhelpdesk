<?php

declare(strict_types=1);

require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/Auth.php';
require_once ROOT_DIR . '/src/Router.php';

// Load environment configuration
loadEnv(ROOT_DIR . '/.env');

// Error reporting
if (env('APP_DEBUG', 'false') === 'true') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Start session
session_start();

// Create router, load routes, dispatch
$router = new Router();
require ROOT_DIR . '/src/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

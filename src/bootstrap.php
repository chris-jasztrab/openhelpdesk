<?php

declare(strict_types=1);

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/config/version.php';
require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/Auth.php';
require_once ROOT_DIR . '/src/Router.php';
require_once ROOT_DIR . '/src/Sla.php';

// Load environment configuration
loadEnv(ROOT_DIR . '/.env');

// Upload configuration
define('UPLOAD_MAX_SIZE', (int) env('UPLOAD_MAX_SIZE', '20971520'));
define('UPLOAD_ALLOWED_TYPES', array_map('trim', explode(',', env('UPLOAD_ALLOWED_TYPES', 'application/pdf,image/jpeg,image/png'))));
define('ATTACHMENT_STORAGE_PATH', ROOT_DIR . '/storage/attachments/');

// Error reporting
if (env('APP_DEBUG', 'false') === 'true') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Run any pending database migrations automatically
require ROOT_DIR . '/database/migrate.php';

// Start session
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Create router, load routes, dispatch
$router = new Router();
require ROOT_DIR . '/src/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

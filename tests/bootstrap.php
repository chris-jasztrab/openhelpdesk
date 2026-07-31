<?php

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require ROOT_DIR . '/vendor/autoload.php';
require ROOT_DIR . '/src/helpers.php';
require ROOT_DIR . '/src/Database.php';
require ROOT_DIR . '/src/Auth.php';

// Load the application .env so DB credentials are available
loadEnv(ROOT_DIR . '/.env');

// Upload configuration, mirroring src/bootstrap.php. Most tests drive the app
// over HTTP and get these from the running process, but helper-level tests call
// into src/helpers.php directly in THIS process, where nothing else defines them.
if (!defined('UPLOAD_MAX_SIZE')) {
    define('UPLOAD_MAX_SIZE', (int) env('UPLOAD_MAX_SIZE', '20971520'));
}
if (!defined('UPLOAD_ALLOWED_TYPES')) {
    define('UPLOAD_ALLOWED_TYPES', array_map('trim', explode(',', env('UPLOAD_ALLOWED_TYPES', 'application/pdf,image/jpeg,image/png'))));
}
if (!defined('ATTACHMENT_STORAGE_PATH')) {
    define('ATTACHMENT_STORAGE_PATH', ROOT_DIR . '/storage/attachments/');
}

// Seed test users and fixtures into the live DB
Tests\Support\DatabaseSeeder::seed();

// Clean up test records when the process exits
register_shutdown_function(static function (): void {
    Tests\Support\DatabaseSeeder::cleanup();
});

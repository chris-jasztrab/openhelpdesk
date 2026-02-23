<?php

declare(strict_types=1);

define('ROOT_DIR', dirname(__DIR__));

require ROOT_DIR . '/vendor/autoload.php';
require ROOT_DIR . '/src/helpers.php';
require ROOT_DIR . '/src/Database.php';
require ROOT_DIR . '/src/Auth.php';

// Load the application .env so DB credentials are available
loadEnv(ROOT_DIR . '/.env');

// Seed test users and fixtures into the live DB
Tests\Support\DatabaseSeeder::seed();

// Clean up test records when the process exits
register_shutdown_function(static function (): void {
    Tests\Support\DatabaseSeeder::cleanup();
});

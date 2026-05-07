<?php

declare(strict_types=1);

require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/config/version.php';
require_once ROOT_DIR . '/src/helpers.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/Auth.php';
require_once ROOT_DIR . '/src/Router.php';
require_once ROOT_DIR . '/src/Sla.php';
require_once ROOT_DIR . '/src/Holidays.php';
require_once ROOT_DIR . '/src/AI.php';
require_once ROOT_DIR . '/src/RecurringTickets.php';
require_once ROOT_DIR . '/src/PWA.php';

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
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $isHttps,
]);
session_start();

// Baseline security response headers. Set before any output so they apply to
// every response (HTML, JSON, redirects). CSP is intentionally permissive
// enough for the current Bootstrap-from-CDN + inline-handler templates; tighten
// once those are migrated to nonces or local assets.
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header(
        "Content-Security-Policy: default-src 'self'; "
      . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.ckeditor.com; "
      . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.ckeditor.com; "
      . "font-src 'self' data: https://cdn.jsdelivr.net https://cdn.ckeditor.com; "
      . "img-src 'self' data: blob:; "
      . "connect-src 'self' https://cdn.ckeditor.com; "
      . "frame-ancestors 'none'; "
      . "base-uri 'self'; "
      . "form-action 'self'"
    );
}

// Create router, load routes, dispatch
$router = new Router();
require ROOT_DIR . '/src/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

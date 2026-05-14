<?php

declare(strict_types=1);

/* ==================================================================
 * PWA — Progressive Web App routes.
 *
 *   GET /manifest.webmanifest      — dynamic Web App Manifest
 *   GET /sw.js                     — service worker (version-baked)
 *   GET /pwa/icon-{size}.png       — generated app icon
 *   GET /pwa/icon-{size}-maskable.png — Android adaptive icon
 *   GET /pwa/apple-touch-icon.png  — iOS home-screen icon
 *   GET /offline                   — fallback page rendered by SW
 *
 * The manifest, icons, and offline page are reachable without auth so
 * that the install prompt on the public help-center works.
 * ================================================================== */

$router->get('/manifest.webmanifest', function () {
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    echo json_encode(PWA::manifestData(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
});

$router->get('/sw.js', function () {
    // The service worker MUST be served from the root scope, with a
    // Service-Worker-Allowed header if you ever move it under a path.
    // Keeping it at /sw.js means scope is /.
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Service-Worker-Allowed: /');

    $version = PWA::swCacheVersion();
    $appName = json_encode(getSetting('branding_app_name', 'OpenHelpDesk'), JSON_UNESCAPED_SLASHES);

    // The service worker is plain JS — no PHP except for the version
    // and app-name strings injected via heredoc.
    echo <<<JS
// {$appName} service worker — cache version {$version}
const VERSION = '{$version}';
const STATIC_CACHE  = 'ld-static-' + VERSION;
const RUNTIME_CACHE = 'ld-runtime-' + VERSION;
const OFFLINE_URL   = '/offline';

// Pre-cache the bare minimum (same-origin only) so the offline shell
// loads even on a brand-new install where nothing has been navigated
// to yet. Cross-origin URLs (CDN bootstrap etc.) are NOT precached:
// SW-initiated cross-origin fetches go through the page CSP's
// connect-src directive, which on this app is 'self' + ckeditor only —
// jsdelivr would be blocked, the precache fetch would fail, and the
// browser would receive a stylesheet response with no body and render
// every page unstyled. Letting the browser load CDN assets natively
// (via <link rel="stylesheet"> which uses style-src, not connect-src)
// is the only path that actually works under our CSP.
const PRECACHE_URLS = [
    '/offline',
    '/manifest.webmanifest',
    '/pwa/icon-192.png',
    '/pwa/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS).catch(() => null))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((k) => k !== STATIC_CACHE && k !== RUNTIME_CACHE)
                .map((k) => caches.delete(k))
        )).then(() => self.clients.claim())
    );
});

// Allow the page to send a SKIP_WAITING message after a new SW lands,
// so users can tap "Update" instead of waiting for a full app close.
self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING' || (event.data && event.data.type === 'SKIP_WAITING')) {
        self.skipWaiting();
    }
});

// Routes/paths that must NEVER be served from cache — they touch
// per-user state, are CSRF-bearing, or carry one-time payloads.
function isUncacheable(url) {
    const p = url.pathname;
    return (
        p.startsWith('/api/')             ||
        p.startsWith('/admin/')           ||
        p.startsWith('/sso/')             ||
        p.startsWith('/login')            ||
        p.startsWith('/logout')           ||
        p.startsWith('/2fa')              ||
        p.startsWith('/install/')         ||
        p.startsWith('/setup/')           ||
        p.includes('/download')           ||
        p.includes('/attachments/')       ||
        p === '/sw.js'
    );
}

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Only handle GET — POSTs (form submits, comments, ticket creates)
    // pass straight through so CSRF + flash flow keeps working.
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // CRITICAL: do not intercept cross-origin requests. SW fetch() to
    // cross-origin hosts is governed by connect-src on this app's CSP
    // (which is 'self' + ckeditor only); CDN stylesheets, scripts, and
    // fonts must reach the browser through their native loaders (where
    // style-src / script-src / font-src apply). Intercepting them was
    // the cause of the unstyled-pages outage in 2.34.0–2.35.0.
    if (url.origin !== self.location.origin) return;

    if (isUncacheable(url)) return;

    // Navigations: network-first, fall back to last cached HTML, then
    // to the offline shell. Lets staff keep reading the last screen
    // they had open even when wifi blips.
    if (req.mode === 'navigate') {
        event.respondWith((async () => {
            try {
                const fresh = await fetch(req);
                const copy = fresh.clone();
                caches.open(RUNTIME_CACHE).then((c) => c.put(req, copy)).catch(() => null);
                return fresh;
            } catch (e) {
                const cached = await caches.match(req);
                if (cached) return cached;
                const offline = await caches.match(OFFLINE_URL);
                if (offline) return offline;
                return new Response('Offline', { status: 503, statusText: 'Offline' });
            }
        })());
        return;
    }

    // Same-origin static assets: stale-while-revalidate. Covers
    // /pwa/icons and any future own-origin CSS/JS/fonts. Cross-origin
    // CDN assets bypass the SW entirely (see early-return above).
    const dest = req.destination;
    if (
        dest === 'style' || dest === 'script' || dest === 'font' || dest === 'image' ||
        url.pathname.startsWith('/pwa/')
    ) {
        event.respondWith((async () => {
            const cached = await caches.match(req);
            const networkPromise = fetch(req).then((res) => {
                if (res && res.status === 200) {
                    const copy = res.clone();
                    caches.open(STATIC_CACHE).then((c) => c.put(req, copy)).catch(() => null);
                }
                return res;
            }).catch(() => null);
            return cached || networkPromise || new Response('', { status: 504 });
        })());
    }
});
JS;
    exit;
});

// Maskable variant is registered FIRST because the Router iterates
// patterns in insertion order — a /pwa/icon-192-maskable.png URL could
// otherwise satisfy the simpler /pwa/icon-{size}.png pattern via
// regex backtracking and serve the wrong file.
$router->get('/pwa/icon-{size}-maskable.png', function (array $p) {
    $size = (int) $p['size'];
    if ((string) $size !== $p['size']) { http_response_code(404); echo 'Bad size'; exit; }
    pwaServeIcon($size, true);
});

$router->get('/pwa/icon-{size}.png', function (array $p) {
    $size = (int) $p['size'];
    if ((string) $size !== $p['size']) { http_response_code(404); echo 'Bad size'; exit; }
    pwaServeIcon($size, false);
});

$router->get('/pwa/apple-touch-icon.png', function () {
    pwaServeIcon(PWA::APPLE_ICON_SIZE, false);
});

$router->get('/offline', function () {
    // Light-weight offline shell — no DB calls, no auth, no flash.
    header('Cache-Control: public, max-age=86400');
    $appName    = getSetting('branding_app_name', 'OpenHelpDesk');
    $primary    = getSetting('branding_primary_color', '#4f46e5');
    $startGrad  = getSetting('branding_navbar_start', '#1e1b4b');
    $endGrad    = getSetting('branding_navbar_end', '#312e81');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline &ndash; <?= e($appName) ?></title>
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="<?= e($startGrad) ?>">
    <link rel="apple-touch-icon" href="/pwa/apple-touch-icon.png">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, <?= e($startGrad) ?> 0%, <?= e($endGrad) ?> 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            max-width: 420px;
            width: 100%;
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 16px;
            padding: 2rem 1.75rem;
            text-align: center;
            backdrop-filter: blur(8px);
        }
        .card h1 { margin: .5rem 0 .25rem; font-size: 1.5rem; }
        .card p  { margin: .5rem 0; opacity: .85; line-height: 1.5; }
        .icon {
            width: 64px; height: 64px; border-radius: 16px;
            background: <?= e($primary) ?>;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 2rem; font-weight: 700; color: #fff;
            box-shadow: 0 6px 18px rgba(0,0,0,.3);
        }
        button {
            margin-top: 1rem;
            padding: .65rem 1.25rem;
            background: #fff; color: <?= e($primary) ?>;
            border: none; border-radius: 8px;
            font-weight: 600; font-size: 1rem;
            cursor: pointer;
        }
        button:hover { background: #f1f5f9; }
        small { display: block; margin-top: 1rem; opacity: .6; font-size: .8rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon"><?= e(PWA::appInitial()) ?></div>
        <h1>You're offline</h1>
        <p>This device can't reach the helpdesk right now. New tickets and replies will need to wait until you're back online.</p>
        <p>Pages you've already visited may still load from the cache.</p>
        <button type="button" onclick="location.reload()">Try again</button>
        <small><?= e($appName) ?></small>
    </div>
</body>
</html>
    <?php
    exit;
});

/**
 * Serve a generated PWA icon. Sets Content-Type, a long-lived cache
 * header (the cache key in the filename means the bytes are immutable
 * for a given key), and reads the cached PNG straight off disk.
 */
function pwaServeIcon(int $size, bool $maskable): void
{
    if (!in_array($size, [...PWA::ICON_SIZES, PWA::APPLE_ICON_SIZE], true)) {
        http_response_code(404);
        echo 'Unsupported icon size';
        exit;
    }
    $path = PWA::iconPath($size, $maskable);
    if (!is_file($path)) {
        http_response_code(500);
        echo 'Icon not generated';
        exit;
    }
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

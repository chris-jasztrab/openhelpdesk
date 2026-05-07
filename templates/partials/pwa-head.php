<?php
/**
 * PWA <head> tags — manifest, theme color, apple-touch-icon. Included
 * by every layout (app, base, auth, public) so install + theming work
 * regardless of which surface the user lands on.
 */
$_pwaThemeColor = getSetting('branding_navbar_start', '#1e1b4b');
?>
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="<?= e($_pwaThemeColor) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(getSetting('branding_app_name', 'LocalDesk')) ?>">
<meta name="mobile-web-app-capable" content="yes">
<link rel="apple-touch-icon" href="/pwa/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="192x192" href="/pwa/icon-192.png">
<link rel="icon" type="image/png" sizes="512x512" href="/pwa/icon-512.png">

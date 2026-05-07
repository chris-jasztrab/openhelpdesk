<?php

declare(strict_types=1);

/**
 * Progressive Web App helpers.
 *
 * - Builds the dynamic Web App Manifest from branding settings.
 * - Generates icon variants on demand (using GD) from the configured
 *   branding logo, falling back to a brand-coloured letter glyph when no
 *   raster logo is available.
 * - Owns the service-worker cache version stamp so deploys auto-bust.
 *
 * Generated icons are cached on disk at storage/pwa/ and only regenerated
 * when the underlying logo file mtime, the brand colour, or the app name
 * change.
 */

final class PWA
{
    /** Sizes (px) we publish in the manifest. */
    public const ICON_SIZES = [192, 256, 384, 512];

    /** Apple touch icon size (single best-of). */
    public const APPLE_ICON_SIZE = 180;

    /**
     * Returns the absolute path to the configured raster branding logo,
     * or null when none is set or the file is missing or is an SVG (GD
     * can't ingest SVG so we fall back to the letter glyph).
     *
     * @return array{path:string, mtime:int}|null
     */
    public static function resolveLogo(): ?array
    {
        $logo = trim(getSetting('branding_logo', ''));
        if ($logo === '') {
            return null;
        }
        $path = ROOT_DIR . '/public/uploads/branding/' . basename($logo);
        if (!is_file($path)) {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            return null;
        }
        return ['path' => $path, 'mtime' => (int) filemtime($path)];
    }

    /**
     * Cache key incorporates everything that affects icon output: logo
     * mtime, brand colour, app-name initial, app version. When any of
     * those change, the new key produces a new on-disk filename and the
     * old cached PNGs are simply ignored.
     */
    public static function cacheKey(): string
    {
        $logo  = self::resolveLogo();
        $stamp = $logo ? ('l' . $logo['mtime']) : 'n';
        $hash  = substr(sha1(implode('|', [
            $stamp,
            getSetting('branding_primary_color', '#4f46e5'),
            self::appInitial(),
            APP_VERSION,
        ])), 0, 10);
        return $hash;
    }

    /** First grapheme of the app name, uppercased. */
    public static function appInitial(): string
    {
        $name = trim(getSetting('branding_app_name', 'LocalDesk'));
        if ($name === '') {
            return 'L';
        }
        $first = mb_substr($name, 0, 1, 'UTF-8');
        return mb_strtoupper($first, 'UTF-8');
    }

    /** Storage root for generated icons. Created on first write. */
    public static function cacheDir(): string
    {
        return ROOT_DIR . '/storage/pwa';
    }

    /**
     * Returns the absolute filesystem path to the cached icon PNG for
     * the given size, generating it if missing. $maskable=true draws
     * the foreground inside the safe-zone (~80%) for Android adaptive
     * icons; standard icons fill ~92%.
     */
    public static function iconPath(int $size, bool $maskable = false): string
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $variant = $maskable ? 'm' : 's';
        $file = $dir . '/icon-' . self::cacheKey() . '-' . $size . $variant . '.png';
        if (!is_file($file)) {
            self::renderIcon($file, $size, $maskable);
        }
        return $file;
    }

    /**
     * Render an icon PNG to disk. Background is a rounded-square (or
     * full square when maskable) in the brand colour. Foreground is the
     * raster logo composited centrally, or the app-name initial in white
     * when no raster logo is available.
     */
    private static function renderIcon(string $outPath, int $size, bool $maskable): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            // GD missing — write a 1×1 transparent PNG so the response
            // doesn't 500. The PWA install will still show but with a
            // blank icon.
            file_put_contents($outPath, base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
            ));
            return;
        }

        $brand = self::hexToRgb(getSetting('branding_primary_color', '#4f46e5'));

        $img = imagecreatetruecolor($size, $size);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefilledrectangle($img, 0, 0, $size, $size, $transparent);

        $bgColor = imagecolorallocate($img, $brand[0], $brand[1], $brand[2]);

        if ($maskable) {
            // Full-bleed square — Android masks it to the system shape.
            imagefilledrectangle($img, 0, 0, $size, $size, $bgColor);
        } else {
            // Rounded square (radius ~22% of size, matches iOS aesthetic).
            $r = (int) round($size * 0.22);
            self::filledRoundedRect($img, 0, 0, $size, $size, $r, $bgColor);
        }

        // Foreground area: shrink for maskable to keep the logo inside
        // Android's 80%-diameter safe zone.
        $pad = (int) round($size * ($maskable ? 0.18 : 0.10));
        $fgX = $pad;
        $fgY = $pad;
        $fgW = $size - 2 * $pad;
        $fgH = $size - 2 * $pad;

        $logo = self::resolveLogo();
        if ($logo) {
            $src = self::loadImage($logo['path']);
            if ($src) {
                $sw = imagesx($src);
                $sh = imagesy($src);
                $scale = min($fgW / $sw, $fgH / $sh);
                $dw = (int) round($sw * $scale);
                $dh = (int) round($sh * $scale);
                $dx = $fgX + (int) round(($fgW - $dw) / 2);
                $dy = $fgY + (int) round(($fgH - $dh) / 2);
                imagecopyresampled($img, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
                imagedestroy($src);
            }
        } else {
            // Letter glyph fallback. Use GD's built-in font 5 scaled via
            // imagettftext if a font is bundled; otherwise fall back to
            // the largest built-in font centred in the foreground area.
            $white = imagecolorallocate($img, 255, 255, 255);
            $glyph = self::appInitial();

            $font = self::findFont();
            if ($font !== null) {
                $fontSize = (int) round($size * 0.55);
                $bbox = imagettfbbox($fontSize, 0, $font, $glyph);
                $textW = $bbox[2] - $bbox[0];
                $textH = $bbox[1] - $bbox[7];
                $tx = $fgX + (int) round(($fgW - $textW) / 2) - $bbox[0];
                $ty = $fgY + (int) round(($fgH + $textH) / 2);
                imagettftext($img, $fontSize, 0, $tx, $ty, $white, $font, $glyph);
            } else {
                $fontId = 5;
                $cw = imagefontwidth($fontId);
                $ch = imagefontheight($fontId);
                $tx = $fgX + (int) round(($fgW - $cw) / 2);
                $ty = $fgY + (int) round(($fgH - $ch) / 2);
                imagestring($img, $fontId, $tx, $ty, $glyph, $white);
            }
        }

        imagepng($img, $outPath, 6);
        imagedestroy($img);
    }

    /**
     * Try to find a bundled TTF for the letter glyph fallback. We check
     * a few common Linux paths and the Windows Arial path, returning the
     * first hit or null.
     */
    private static function findFont(): ?string
    {
        foreach ([
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/Library/Fonts/Arial Bold.ttf',
            'C:\\Windows\\Fonts\\arialbd.ttf',
        ] as $f) {
            if (is_file($f) && function_exists('imagettftext')) {
                return $f;
            }
        }
        return null;
    }

    /** Load a PNG/JPEG/GIF/WEBP into a GD image resource. */
    private static function loadImage(string $path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'png'           => @imagecreatefrompng($path),
            'jpg', 'jpeg'   => @imagecreatefromjpeg($path),
            'gif'           => @imagecreatefromgif($path),
            'webp'          => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default         => false,
        } ?: null;
    }

    /** Draw a filled rounded rectangle. */
    private static function filledRoundedRect($img, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        $r = max(0, min($radius, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2)));
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        imagefilledellipse($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
    }

    /**
     * @return array{0:int,1:int,2:int} RGB triple (0-255) parsed from a
     * `#rrggbb` hex string. Falls back to the brand default on any
     * unparseable input.
     */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '4f46e5';
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Build the manifest payload (PHP assoc array — caller json_encode()s).
     */
    public static function manifestData(): array
    {
        $appName     = getSetting('branding_app_name', 'LocalDesk');
        $shortName   = mb_substr($appName, 0, 12, 'UTF-8') ?: 'LocalDesk';
        $themeColor  = getSetting('branding_navbar_start', '#1e1b4b');
        $brandColor  = getSetting('branding_primary_color', '#4f46e5');
        $bgColor     = '#f1f5f9';

        $icons = [];
        foreach (self::ICON_SIZES as $size) {
            $icons[] = [
                'src'     => '/pwa/icon-' . $size . '.png',
                'sizes'   => $size . 'x' . $size,
                'type'    => 'image/png',
                'purpose' => 'any',
            ];
            $icons[] = [
                'src'     => '/pwa/icon-' . $size . '-maskable.png',
                'sizes'   => $size . 'x' . $size,
                'type'    => 'image/png',
                'purpose' => 'maskable',
            ];
        }

        // Pick the start_url for the most likely role: agents go to
        // floor mode, patrons land on the portal. Since the manifest is
        // global we use a neutral landing page that redirects based on
        // role; /post-login already does that for sign-in.
        $startUrl = '/?utm_source=pwa';

        $shortcuts = [
            [
                'name'        => 'Floor mode',
                'short_name'  => 'Floor',
                'description' => 'Tablet-friendly ticket queue',
                'url'         => '/agent/floor?utm_source=pwa-shortcut',
                'icons'       => [['src' => '/pwa/icon-192.png', 'sizes' => '192x192']],
            ],
            [
                'name'        => 'New ticket',
                'short_name'  => 'New',
                'description' => 'Create a help request',
                'url'         => '/portal/tickets/create?utm_source=pwa-shortcut',
                'icons'       => [['src' => '/pwa/icon-192.png', 'sizes' => '192x192']],
            ],
            [
                'name'        => 'My tickets',
                'short_name'  => 'Tickets',
                'description' => 'Your open requests',
                'url'         => '/portal/tickets?utm_source=pwa-shortcut',
                'icons'       => [['src' => '/pwa/icon-192.png', 'sizes' => '192x192']],
            ],
        ];

        return [
            'name'             => $appName,
            'short_name'       => $shortName,
            'description'      => 'Help desk for branch staff and patrons',
            'id'               => '/?pwa=1',
            'start_url'        => $startUrl,
            'scope'            => '/',
            'display'          => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation'      => 'any',
            'theme_color'      => $themeColor,
            'background_color' => $bgColor,
            'lang'             => 'en',
            'dir'              => 'ltr',
            'categories'       => ['productivity', 'business'],
            'icons'            => $icons,
            'shortcuts'        => $shortcuts,
        ];
    }

    /**
     * Cache version for the service worker. Tied to APP_VERSION plus a
     * hash of the manifest contents — so a branding change (which
     * doesn't bump the app version) still busts the cache.
     */
    public static function swCacheVersion(): string
    {
        return APP_VERSION . '-' . substr(sha1(json_encode(self::manifestData())), 0, 8);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\TestCase;

/**
 * Pasted-image extraction (inlineImagesToAttachments() + textColumnOverflows()).
 *
 * The bug these lock down: CKEditor's Base64UploadAdapter inlines a pasted
 * screenshot into the body as `data:image/png;base64,...`. `description` and
 * `details` are TEXT (65,535 bytes) and production runs STRICT_TRANS_TABLES, so
 * anything past ~48 KB of image was rejected outright with a 1406 — an uncaught
 * PDOException and a bare HTTP 500, with no log entry because the app had
 * error_reporting(0). Every attempt to paste a screenshot hit it.
 *
 * These are deliberately unit-level: they call the helper directly rather than
 * driving the six routes, because the helper is where the decode/validate/store
 * decisions live and it is shared by all of them.
 *
 * Files really are written to attachment storage, so every test tracks what it
 * created and tearDownAfterClass() removes it.
 */
class InlineImageExtractionTest extends TestCase
{
    /** @var string[] absolute paths written during the run */
    private static array $written = [];

    /** Remember every file the helper stored so we can delete it afterwards. */
    private static function track(array $saved): void
    {
        foreach ($saved as $row) {
            self::$written[] = ATTACHMENT_STORAGE_PATH . $row['stored_name'];
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$written as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        self::$written = [];
    }

    /**
     * An incompressible PNG, so the base64 really does exceed the TEXT limit
     * rather than gzip-ing down under it (a smooth gradient compresses to ~4 KB).
     */
    private static function noisyPng(int $side = 300): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('ext-gd not available');
        }
        mt_srand(1234); // deterministic
        $im = imagecreatetruecolor($side, $side);
        for ($x = 0; $x < $side; $x++) {
            for ($y = 0; $y < $side; $y++) {
                imagesetpixel($im, $x, $y, imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }
        ob_start();
        imagepng($im, null, 0);
        return (string) ob_get_clean();
    }

    private static function body(string $mime, string $bytes): string
    {
        return '<p>Before</p><figure class="image"><img src="data:' . $mime . ';base64,'
             . base64_encode($bytes) . '"></figure><p>After</p>';
    }

    // ── the actual regression ────────────────────────────────────────────────

    public function test_oversized_pasted_image_is_extracted_and_body_fits_the_column(): void
    {
        $png  = self::noisyPng();
        $html = self::body('image/png', $png);

        $this->assertTrue(
            textColumnOverflows($html),
            'fixture is too small to reproduce the bug — base64 must exceed 65,535 bytes'
        );

        $out = inlineImagesToAttachments($html, $saved, $rejected);
        self::track($saved);

        $this->assertSame([], $rejected, 'a valid PNG should not be rejected');
        $this->assertCount(1, $saved);
        $this->assertStringNotContainsString('data:image', $out, 'no data URI may survive');
        $this->assertFalse(textColumnOverflows($out), 'rewritten body must fit TEXT');
        $this->assertStringContainsString('Before', $out);
        $this->assertStringContainsString('After', $out);
    }

    public function test_extracted_row_carries_the_sniffed_mime_and_real_size(): void
    {
        $png = self::noisyPng(120);
        inlineImagesToAttachments(self::body('image/png', $png), $saved);
        self::track($saved);

        $this->assertCount(1, $saved);
        $this->assertSame('image/png', $saved[0]['mime_type']);
        $this->assertSame(strlen($png), $saved[0]['file_size']);
        $this->assertSame('pasted-image-1.png', $saved[0]['original_name']);
        $this->assertFileExists(ATTACHMENT_STORAGE_PATH . $saved[0]['stored_name']);
        $this->assertSame(
            $png,
            file_get_contents(ATTACHMENT_STORAGE_PATH . $saved[0]['stored_name']),
            'stored bytes must round-trip exactly'
        );
    }

    /**
     * The URL must not end in .png/.jpg: PHP's built-in server (local dev and
     * this very test harness) intercepts known static extensions and 404s them
     * before the router runs.
     */
    public function test_rewritten_url_is_extension_free_but_file_on_disk_keeps_one(): void
    {
        inlineImagesToAttachments(self::body('image/png', self::noisyPng(80)), $saved);
        self::track($saved);

        $this->assertMatchesRegularExpression('#^att_[0-9a-f]{32}\.png$#', $saved[0]['stored_name']);
        $token = substr($saved[0]['stored_name'], 0, -4);
        $this->assertSame('/attachments/img/' . $token, inlineImageUrl($token));
        $this->assertStringNotContainsString('.', $token, 'URL token must be dot-free');
    }

    public function test_multiple_pasted_images_each_get_their_own_attachment(): void
    {
        $html = self::body('image/png', self::noisyPng(60))
              . self::body('image/png', self::noisyPng(70));

        $out = inlineImagesToAttachments($html, $saved);
        self::track($saved);

        $this->assertCount(2, $saved);
        $this->assertNotSame($saved[0]['stored_name'], $saved[1]['stored_name']);
        $this->assertSame('pasted-image-2.png', $saved[1]['original_name']);
        $this->assertSame(2, substr_count($out, '/attachments/img/'));
    }

    // ── validation ───────────────────────────────────────────────────────────

    /**
     * SVG can carry inline <script> and the CSP allows script-src 'unsafe-inline',
     * so it must never be stored and served back from our own origin.
     */
    public function test_svg_is_rejected_and_stripped(): void
    {
        $svg  = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $out  = inlineImagesToAttachments(self::body('image/svg+xml', $svg), $saved, $rejected);
        self::track($saved);

        $this->assertSame([], $saved, 'SVG must not be stored');
        $this->assertNotSame([], $rejected, 'caller must be told it was dropped');
        $this->assertStringNotContainsString('data:', $out);
        $this->assertStringNotContainsString('script', $out);
    }

    /** The claimed MIME is attacker-controlled text; only the sniffed bytes count. */
    public function test_claimed_image_mime_cannot_smuggle_non_image_bytes(): void
    {
        $out = inlineImagesToAttachments(
            self::body('image/png', '<html><body>not a png at all</body></html>'),
            $saved,
            $rejected
        );
        self::track($saved);

        $this->assertSame([], $saved, 'bytes that are not an image must not be stored');
        $this->assertNotSame([], $rejected);
        $this->assertStringNotContainsString('data:', $out);
    }

    public function test_image_over_the_upload_limit_is_rejected(): void
    {
        // Sniffs as PNG (magic bytes) but is far larger than UPLOAD_MAX_SIZE.
        $fake = "\x89PNG\r\n\x1a\n" . str_repeat('A', UPLOAD_MAX_SIZE + 1024);
        inlineImagesToAttachments(self::body('image/png', $fake), $saved, $rejected);
        self::track($saved);

        $this->assertSame([], $saved);
        $this->assertNotSame([], $rejected);
        $this->assertStringContainsString('larger than', $rejected[0]);
    }

    public function test_body_without_any_data_uri_is_returned_untouched(): void
    {
        $html = '<p>Just text and a <a href="/x">link</a>, plus <img src="/attachments/img/att_'
              . str_repeat('a', 32) . '"></p>';

        $this->assertSame($html, inlineImagesToAttachments($html, $saved, $rejected));
        $this->assertSame([], $saved);
        $this->assertSame([], $rejected);
    }

    /** Re-saving an already-rewritten body must not duplicate the attachment. */
    public function test_rewritten_body_is_idempotent_on_a_second_pass(): void
    {
        $once = inlineImagesToAttachments(self::body('image/png', self::noisyPng(60)), $saved);
        self::track($saved);
        $this->assertCount(1, $saved);

        $twice = inlineImagesToAttachments($once, $saved2);
        $this->assertSame($once, $twice);
        $this->assertSame([], $saved2, 'a second pass must not re-extract anything');
    }

    // ── the overflow guard ───────────────────────────────────────────────────

    public function test_text_column_overflow_is_measured_in_bytes_not_characters(): void
    {
        $this->assertFalse(textColumnOverflows(str_repeat('a', 65535)));
        $this->assertTrue(textColumnOverflows(str_repeat('a', 65536)));
        $this->assertFalse(textColumnOverflows(null));
        $this->assertFalse(textColumnOverflows(''));

        // 3-byte characters: 21,845 of them fit, 21,846 do not.
        $this->assertFalse(textColumnOverflows(str_repeat('€', 21845)));
        $this->assertTrue(textColumnOverflows(str_repeat('€', 21846)));
    }
}

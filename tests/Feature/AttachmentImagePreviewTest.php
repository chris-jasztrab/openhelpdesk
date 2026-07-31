<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * Inline image previews for attachments (attachmentIsImage() +
 * templates/partials/attachment-thumb.php).
 *
 * Rows are inserted straight into ticket_attachments on the seeded fixture
 * ticket — no file ever lands on disk, which is fine because these tests only
 * care about the markup the templates emit, not about the download route.
 *
 * SVG is expected NOT to preview: it is a document format that can carry inline
 * <script>, and the app's CSP allows script-src 'self' 'unsafe-inline'.
 */
class AttachmentImagePreviewTest extends TestCase
{
    /** original_name => mime_type ; names carry the [TEST] prefix for cleanup */
    private const ROWS = [
        '[TEST] preview-screenshot.png' => 'image/png',
        '[TEST] preview-photo.jpeg'     => 'image/jpeg',
        '[TEST] preview-upper.PNG'      => 'IMAGE/PNG',
        '[TEST] preview-manual.pdf'     => 'application/pdf',
        '[TEST] preview-logo.svg'       => 'image/svg+xml',
        '[TEST] preview-notes.txt'      => 'text/plain',
    ];

    /** @var array<string, int> original_name => attachment id */
    private static array $ids = [];

    /** attachment attached to a timeline entry (the "inline in the thread" path) */
    private static int $timelineId       = 0;
    private static int $timelineAttachId = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::removeFixtures();
        $db = \Database::connect();

        $ins = $db->prepare(
            'INSERT INTO ticket_attachments
                (ticket_id, timeline_id, uploaded_by, original_name, stored_name, mime_type, file_size)
             VALUES (?, NULL, ?, ?, ?, ?, ?)'
        );
        foreach (self::ROWS as $name => $mime) {
            $ins->execute([
                DatabaseSeeder::$ticketId,
                DatabaseSeeder::$adminId,
                $name,
                'test-fixture-' . bin2hex(random_bytes(6)),
                $mime,
                4096,
            ]);
            self::$ids[$name] = (int) $db->lastInsertId();
        }

        // One more image, this time hung off a timeline entry, so the "inline in
        // the conversation" branch of the templates is exercised too.
        $db->prepare(
            "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
             VALUES (?, ?, 'comment', '[TEST] attachment preview fixture comment', 0)"
        )->execute([DatabaseSeeder::$ticketId, DatabaseSeeder::$adminId]);
        self::$timelineId = (int) $db->lastInsertId();

        $db->prepare(
            'INSERT INTO ticket_attachments
                (ticket_id, timeline_id, uploaded_by, original_name, stored_name, mime_type, file_size)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            DatabaseSeeder::$ticketId,
            self::$timelineId,
            DatabaseSeeder::$adminId,
            '[TEST] preview-inline.png',
            'test-fixture-' . bin2hex(random_bytes(6)),
            'image/png',
            2048,
        ]);
        self::$timelineAttachId = (int) $db->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        self::removeFixtures();
        parent::tearDownAfterClass();
    }

    private static function removeFixtures(): void
    {
        try {
            $db    = \Database::connect();
            $names = array_merge(array_keys(self::ROWS), ['[TEST] preview-inline.png']);
            $ph    = implode(',', array_fill(0, count($names), '?'));

            $db->prepare("DELETE FROM ticket_attachments WHERE original_name IN ($ph)")->execute($names);
            $db->prepare(
                "DELETE FROM ticket_timeline WHERE details = '[TEST] attachment preview fixture comment'"
            )->execute();
        } catch (\Throwable) {
            // never mask a real failure
        }
    }

    // ── attachmentIsImage() itself ─────────────────────────────────────────────

    /** @dataProvider mimeCases */
    public function test_attachment_is_image_classifies_mime_types(?string $mime, bool $expected): void
    {
        $this->assertSame($expected, \attachmentIsImage($mime), 'mime: ' . var_export($mime, true));
    }

    /** @return array<string, array{0:?string, 1:bool}> */
    public static function mimeCases(): array
    {
        return [
            'png'                => ['image/png', true],
            'jpeg'               => ['image/jpeg', true],
            'gif'                => ['image/gif', true],
            'webp'               => ['image/webp', true],
            'uppercase png'      => ['IMAGE/PNG', true],
            'padded png'         => ["  image/png\t", true],
            'svg+xml'            => ['image/svg+xml', false],
            'svg (bare)'         => ['image/svg', false],
            'svg uppercase'      => ['IMAGE/SVG+XML', false],
            'pdf'                => ['application/pdf', false],
            'plain text'         => ['text/plain', false],
            'html'               => ['text/html', false],
            'empty string'       => ['', false],
            'null'               => [null, false],
            'prefix lookalike'   => ['imagex/png', false],
            'image word only'    => ['image', false],
        ];
    }

    // ── The rendered ticket pages ─────────────────────────────────────────────

    /** @dataProvider panels */
    public function test_image_attachment_renders_an_img_with_a_non_empty_alt(
        string $role,
        string $ticketPath,
        string $downloadBase
    ): void {
        $html = (string) $this->get($this->clientFor($role), $ticketPath)->getBody();

        foreach (['[TEST] preview-screenshot.png', '[TEST] preview-photo.jpeg', '[TEST] preview-upper.PNG'] as $name) {
            $alt = $this->imgAltFor($html, $downloadBase, self::$ids[$name]);
            $this->assertNotNull($alt, "{$role}: no <img> rendered for image attachment «{$name}».");
            $this->assertNotSame('', $alt, "{$role}: <img> for «{$name}» has an empty alt.");
            $this->assertSame($name, $alt, "$role: alt should carry the original filename.");
        }
    }

    /** @dataProvider panels */
    public function test_pdf_attachment_does_not_render_an_img(
        string $role,
        string $ticketPath,
        string $downloadBase
    ): void {
        $html = (string) $this->get($this->clientFor($role), $ticketPath)->getBody();

        $this->assertNull(
            $this->imgAltFor($html, $downloadBase, self::$ids['[TEST] preview-manual.pdf']),
            "$role: a PDF attachment was rendered as an <img>."
        );
        // …and the file is still listed, so this is not passing because the whole
        // attachment panel vanished.
        $this->assertStringContainsString('[TEST] preview-manual.pdf', $html, "$role: PDF row missing entirely.");
    }

    /** @dataProvider panels */
    public function test_svg_attachment_does_not_render_an_img(
        string $role,
        string $ticketPath,
        string $downloadBase
    ): void {
        $html = (string) $this->get($this->clientFor($role), $ticketPath)->getBody();

        $this->assertNull(
            $this->imgAltFor($html, $downloadBase, self::$ids['[TEST] preview-logo.svg']),
            "$role: an SVG attachment was rendered as an <img> — SVG must never be previewed inline."
        );
        $this->assertStringContainsString('[TEST] preview-logo.svg', $html, "$role: SVG row missing entirely.");
    }

    /** @dataProvider panels */
    public function test_text_attachment_does_not_render_an_img(
        string $role,
        string $ticketPath,
        string $downloadBase
    ): void {
        $html = (string) $this->get($this->clientFor($role), $ticketPath)->getBody();

        $this->assertNull(
            $this->imgAltFor($html, $downloadBase, self::$ids['[TEST] preview-notes.txt']),
            "$role: a text/plain attachment was rendered as an <img>."
        );
    }

    /** @dataProvider panels */
    public function test_thumbnails_are_lazy_loaded_and_height_capped(
        string $role,
        string $ticketPath,
        string $downloadBase
    ): void {
        $html = (string) $this->get($this->clientFor($role), $ticketPath)->getBody();
        $tag  = $this->imgTagFor($html, $downloadBase, self::$ids['[TEST] preview-screenshot.png']);

        $this->assertNotNull($tag, "$role: no thumbnail tag found.");
        $this->assertStringContainsString('loading="lazy"', $tag, "$role: thumbnail is not lazy-loaded.");
        $this->assertMatchesRegularExpression('/max-height:\d+px/', $tag, "$role: thumbnail height is not capped.");
    }

    /** @return array<string, array{0:string,1:string,2:string}> */
    public static function panels(): array
    {
        $id = DatabaseSeeder::$ticketId;
        return [
            'agent'  => ['agent',  "/agent/tickets/{$id}",  '/agent/attachments'],
            'admin'  => ['admin',  "/admin/tickets/{$id}",  '/admin/attachments'],
            'portal' => ['portal', "/portal/tickets/{$id}", '/portal/attachments'],
        ];
    }

    // ── The timeline (in-conversation) branch ─────────────────────────────────

    /** @dataProvider staffPanels */
    public function test_timeline_attached_image_also_previews(
        string $role,
        string $ticketPath,
        string $downloadBase
    ): void {
        $html = (string) $this->get($this->clientFor($role), $ticketPath)->getBody();

        $alt = $this->imgAltFor($html, $downloadBase, self::$timelineAttachId);
        $this->assertNotNull($alt, "$role: timeline image attachment did not render an <img>.");
        $this->assertSame('[TEST] preview-inline.png', $alt);
    }

    /** @return array<string, array{0:string,1:string,2:string}> */
    public static function staffPanels(): array
    {
        $id = DatabaseSeeder::$ticketId;
        return [
            'agent' => ['agent', "/agent/tickets/{$id}", '/agent/attachments'],
            'admin' => ['admin', "/admin/tickets/{$id}", '/admin/attachments'],
        ];
    }

    // ── Demo mode is excluded ─────────────────────────────────────────────────

    /**
     * The portal tour's demo ticket fabricates attachment rows with id 0 and no
     * file on disk, so it must not emit thumbnails (they'd be broken images).
     */
    public function test_portal_demo_ticket_renders_no_attachment_thumbnails(): void
    {
        $r = $this->get($this->portalClient(), '/portal/tour/demo-ticket');
        if ($r->getStatusCode() !== 200) {
            $this->markTestSkipped('Demo ticket route is not reachable for this fixture user.');
        }

        $this->assertDoesNotMatchRegularExpression(
            '/<img[^>]*src="\/portal\/attachments\/\d+\/download"/',
            (string) $r->getBody(),
            'Demo mode rendered an attachment thumbnail.'
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function clientFor(string $role): \GuzzleHttp\Client
    {
        return match ($role) {
            'admin'  => $this->adminClient(),
            'agent'  => $this->agentClient(),
            default  => $this->portalClient(),
        };
    }

    /** The full <img …> tag whose src is this attachment's download URL, or null. */
    private function imgTagFor(string $html, string $downloadBase, int $attachmentId): ?string
    {
        $pattern = '/<img[^>]*src="' . preg_quote($downloadBase . '/' . $attachmentId . '/download', '/') . '"[^>]*>/';
        return preg_match($pattern, $html, $m) ? $m[0] : null;
    }

    private function imgAltFor(string $html, string $downloadBase, int $attachmentId): ?string
    {
        $tag = $this->imgTagFor($html, $downloadBase, $attachmentId);
        if ($tag === null) {
            return null;
        }
        return preg_match('/alt="([^"]*)"/', $tag, $m) ? $m[1] : '';
    }
}

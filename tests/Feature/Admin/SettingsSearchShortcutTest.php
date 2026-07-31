<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\Support\TestCase;

/**
 * The "/" shortcut fix on settings pages (templates/partials/settings-nav-end.php).
 *
 * IMPORTANT SCOPE NOTE: this behaviour is entirely client-side — the fix is that
 * the settings handler runs in the capture phase and calls
 * stopImmediatePropagation() so navbar.php's bubble-phase document handler never
 * fires.  HTTP tests cannot execute JS, so everything below asserts only that the
 * shipped markup has the shape the fix requires and that the old buggy shape is
 * gone.  It does NOT prove focus actually lands in the settings filter or that
 * the navbar dropdown stays closed — that needs a real browser.
 */
class SettingsSearchShortcutTest extends TestCase
{
    /** Pages that include the settings nav partial. */
    public static function settingsPages(): array
    {
        return [
            '/admin/settings'         => ['/admin/settings'],
            '/admin/settings/branding'=> ['/admin/settings/branding'],
            '/admin/groups'           => ['/admin/groups'],
            '/admin/locations'        => ['/admin/locations'],
        ];
    }

    /** @dataProvider settingsPages */
    public function test_settings_partial_is_included_exactly_once(string $path): void
    {
        $body = (string) $this->get($this->adminClient(), $path)->getBody();

        $this->assertSame(
            1,
            substr_count($body, 'window.__settingsSearchIndex = '),
            "settings-nav-end.php should be included exactly once on $path"
        );
        // Two calls, both inside the single settings keydown handler. Any other
        // count means the partial was included twice (two competing capture-phase
        // handlers) or a branch lost its call.
        $this->assertSame(
            2,
            substr_count($body, 'e.stopImmediatePropagation();'),
            "Unexpected number of stopImmediatePropagation() calls on $path"
        );
    }

    /** @dataProvider settingsPages */
    public function test_shortcut_handler_is_registered_in_the_capture_phase(string $path): void
    {
        $handler = $this->shortcutHandler($path);

        $this->assertNotNull($handler, "No settings keydown handler found on $path");
        $this->assertStringEndsWith(
            '}, true);',
            trim($handler),
            "The settings keydown listener on $path is not registered with capture=true"
        );
    }

    /** @dataProvider settingsPages */
    public function test_both_shortcut_branches_stop_immediate_propagation(string $path): void
    {
        $handler = $this->shortcutHandler($path);
        $this->assertNotNull($handler, "No settings keydown handler found on $path");

        // One for the "/" branch, one for the Ctrl/Cmd+K branch — without both,
        // navbar.php's handler still runs for the branch that is missing it.
        $this->assertSame(
            2,
            substr_count($handler, 'e.stopImmediatePropagation();'),
            "Expected stopImmediatePropagation() in both shortcut branches on $path"
        );
        $this->assertSame(2, substr_count($handler, 'e.preventDefault();'), " — $path");
    }

    /** @dataProvider settingsPages */
    public function test_shortcut_has_the_wcag_modifier_guard(string $path): void
    {
        $handler = $this->shortcutHandler($path);
        $this->assertNotNull($handler);

        $this->assertStringContainsString(
            "e.key === '/' && !e.altKey && !e.ctrlKey && !e.metaKey",
            $handler,
            "The \"/\" branch on $path is missing the Alt/Ctrl/Meta guard (WCAG 2.1.4)"
        );
        $this->assertStringContainsString(
            '!e.altKey && e.key.toLowerCase()',
            $handler,
            "The Ctrl/Cmd+K branch on $path is missing the Alt guard"
        );
    }

    /** @dataProvider settingsPages */
    public function test_typing_check_uses_active_element_not_event_target(string $path): void
    {
        $handler = $this->shortcutHandler($path);
        $this->assertNotNull($handler);

        $this->assertStringContainsString('document.activeElement', $handler, " — $path");
        $this->assertStringNotContainsString(
            'e.target.tagName',
            $handler,
            "The old e.target-based typing check is still present on $path"
        );
        // Old code lowercased tag names; the fixed version compares uppercase.
        $this->assertStringContainsString("tag === 'INPUT'", $handler, " — $path");
    }

    /**
     * navbar.php also claims "/" and is still live on settings pages — that is
     * exactly why capture + stopImmediatePropagation is needed.  If the navbar
     * handler ever disappears, the capture trick becomes dead weight and someone
     * should know.
     */
    public function test_navbar_shortcut_is_still_present_on_a_settings_page(): void
    {
        $body = (string) $this->get($this->adminClient(), '/admin/settings')->getBody();

        $this->assertStringContainsString(
            "if (e.key === '/' && !e.altKey && !e.ctrlKey && !e.metaKey) {",
            $body,
            'navbar.php keydown handler is missing from the settings page.'
        );
    }

    /** The partial must not leak onto non-settings pages. */
    public function test_non_settings_pages_do_not_include_the_settings_handler(): void
    {
        foreach (['/admin/tickets', '/agent/tickets'] as $path) {
            $client = str_starts_with($path, '/admin') ? $this->adminClient() : $this->agentClient();
            $body   = (string) $this->get($client, $path)->getBody();

            $this->assertStringNotContainsString(
                'window.__settingsSearchIndex',
                $body,
                "The settings shortcut partial leaked onto $path"
            );
        }
    }

    /**
     * The navbar shortcut on a non-settings page must stay bubble-phase and must
     * NOT have grown a stopImmediatePropagation of its own.
     */
    public function test_navbar_handler_on_a_plain_page_is_unchanged(): void
    {
        $body    = (string) $this->get($this->adminClient(), '/admin/tickets')->getBody();
        $handler = $this->extractHandler($body, "document.addEventListener('keydown'");

        $this->assertNotNull($handler, 'No navbar keydown handler on /admin/tickets.');
        $this->assertStringNotContainsString('stopImmediatePropagation', $handler);
        $this->assertStringNotContainsString('}, true);', $handler, 'The navbar handler became capture-phase.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** The settings partial's keydown handler source, sliced out of the page. */
    private function shortcutHandler(string $path): ?string
    {
        $body = (string) $this->get($this->adminClient(), $path)->getBody();

        // Everything after the settings-search bootstrap belongs to the partial,
        // so slicing there keeps navbar.php's identical-looking handler out.
        $start = strpos($body, 'window.__settingsSearchIndex');
        if ($start === false) {
            return null;
        }

        return $this->extractHandler(substr($body, $start), "document.addEventListener('keydown'");
    }

    /**
     * Return the source of the first listener registration starting at $needle,
     * up to and including its closing `});` / `}, true);`.
     */
    private function extractHandler(string $haystack, string $needle): ?string
    {
        $start = strpos($haystack, $needle);
        if ($start === false) {
            return null;
        }

        $rest = substr($haystack, $start);
        if (preg_match('/^.*?\n\s*\}(?:, true)?\);/s', $rest, $m)) {
            return $m[0];
        }
        return null;
    }
}

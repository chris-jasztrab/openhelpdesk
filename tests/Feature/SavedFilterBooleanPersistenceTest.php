<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\TestCase;

/**
 * "Save Current Filter" must round-trip the on/off filter toggles.
 *
 * The save routes used to store only status/priority/type/location/agent/group
 * and the search string, so every boolean toggle was silently dropped: you could
 * tick "My Watched Tickets", save the filter, apply it later, and get an
 * unwatched list back with no indication anything had been lost.
 *
 * Two failure modes are covered.
 *
 * 1. **The value must survive into the saved filter's URL**, since that URL is how
 *    the filter is re-applied (and how the default-filter redirect works).
 *
 * 2. **It must be stored as the string `'1'`, not int `1`.** The templates pick
 *    which saved filter to highlight as active by strictly comparing the stored
 *    value against the request's own filter value, which is always a string —
 *    so an int would parse, apply, and filter correctly while never lighting up
 *    as the active filter. That is the kind of bug that survives a smoke test.
 *
 * `sla` and `created_within` are intentionally out of scope here: they are an enum
 * and an integer, not toggles. See ticketBoolFilterKeys().
 */
class SavedFilterBooleanPersistenceTest extends TestCase
{
    private const ADMIN_NAME = '[TEST] Bool Filter Admin';
    private const AGENT_NAME = '[TEST] Bool Filter Agent';

    public static function tearDownAfterClass(): void
    {
        try {
            $db = \Database::connect();
            $db->prepare('DELETE FROM saved_filters WHERE name IN (?, ?)')
               ->execute([self::ADMIN_NAME, self::AGENT_NAME]);
        } catch (\Throwable) {
            // Never let cleanup crash the runner.
        }
    }

    /** The decoded `filters` JSON of a saved filter, by name. */
    private function storedFilters(string $name): array
    {
        $stmt = \Database::connect()->prepare('SELECT filters FROM saved_filters WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $json = $stmt->fetchColumn();
        $this->assertNotFalse($json, "Saved filter «{$name}» was not created");
        return json_decode((string) $json, true) ?: [];
    }

    /**
     * The `class` attribute of the saved-filter button whose label contains $name,
     * which is how the template signals active (`text-white`) vs inactive
     * (`btn-outline-secondary`).
     */
    private function savedFilterButtonClass(string $html, string $name): string
    {
        preg_match_all('~<a\s[^>]*class="(btn text-start[^"]*)"[^>]*>(.*?)</a>~s', $html, $m, PREG_SET_ORDER);
        foreach ($m as $set) {
            if (str_contains($set[2], $name)) {
                return $set[1];
            }
        }
        $this->fail("No saved-filter button found for «{$name}»");
    }

    public function test_admin_saved_filter_keeps_its_boolean_toggles(): void
    {
        $this->post($this->adminClient(), '/admin/tickets/filters/save', [
            'name'           => self::ADMIN_NAME,
            'status'         => ['open'],
            'watched'        => '1',
            'has_attachment' => '1',
        ]);

        $stored = $this->storedFilters(self::ADMIN_NAME);

        $this->assertSame('1', $stored['watched'] ?? null, 'watched must be stored as the string "1"');
        $this->assertSame('1', $stored['has_attachment'] ?? null, 'has_attachment must be stored as the string "1"');
        $this->assertSame(['open'], $stored['status'] ?? null, 'existing array filters must keep working');

        // The saved filter is applied by following its link, so the toggles have
        // to be in that URL.
        $body = (string) $this->get($this->adminClient(), '/admin/tickets')->getBody();
        $this->assertMatchesRegularExpression(
            '~href="/admin/tickets\?[^"]*watched=1[^"]*"~',
            $body,
            'The saved-filter link must carry watched=1'
        );
        $this->assertMatchesRegularExpression(
            '~href="/admin/tickets\?[^"]*has_attachment=1[^"]*"~',
            $body,
            'The saved-filter link must carry has_attachment=1'
        );
    }

    public function test_agent_saved_filter_keeps_every_boolean_toggle(): void
    {
        $toggles = ticketBoolFilterKeys('agent');
        $this->assertContains('resolved_today', $toggles, 'Precondition: agent panel has more than the shared toggles');

        $this->post($this->agentClient(), '/agent/tickets/filters/save', array_merge(
            ['name' => self::AGENT_NAME, 'status' => ['open']],
            array_fill_keys($toggles, '1')
        ));

        $stored = $this->storedFilters(self::AGENT_NAME);
        foreach ($toggles as $key) {
            $this->assertSame('1', $stored[$key] ?? null, "«{$key}» must be stored as the string \"1\"");
        }
    }

    /**
     * The strict-comparison trap: a saved filter is highlighted only when the
     * current request matches it, and a differing boolean must break the match.
     */
    public function test_saved_filter_highlights_only_when_its_toggles_match(): void
    {
        $this->post($this->adminClient(), '/admin/tickets/filters/save', [
            'name'           => self::ADMIN_NAME,
            'status'         => ['open'],
            'watched'        => '1',
            'has_attachment' => '1',
        ]);

        $matching = (string) $this->get(
            $this->adminClient(),
            '/admin/tickets?status%5B%5D=open&watched=1&has_attachment=1'
        )->getBody();
        $this->assertStringNotContainsString(
            'btn-outline-secondary',
            $this->savedFilterButtonClass($matching, self::ADMIN_NAME),
            'A saved filter should be highlighted when the request matches it exactly '
            . '(a stored int instead of a string "1" breaks this)'
        );

        // Same filter set, one toggle off — must no longer read as active.
        $differing = (string) $this->get(
            $this->adminClient(),
            '/admin/tickets?status%5B%5D=open&watched=1'
        )->getBody();
        $this->assertStringContainsString(
            'btn-outline-secondary',
            $this->savedFilterButtonClass($differing, self::ADMIN_NAME),
            'Dropping has_attachment should stop the saved filter reading as active'
        );
    }

    /**
     * Removing one applied-filter pill must not take the other toggles with it.
     * The pill URLs and the save route read the same key list for this reason.
     */
    public function test_removing_one_pill_keeps_the_other_toggles(): void
    {
        $body = (string) $this->get(
            $this->agentClient(),
            '/agent/tickets?watched=1&has_attachment=1&resolved_today=1'
        )->getBody();

        // The "remove watched" pill link must still carry the other two.
        preg_match_all('~href="(/agent/tickets\?[^"]*)"[^>]*class="pill-remove~s', $body, $m);
        $urls = $m[1] ?? [];
        $this->assertNotEmpty($urls, 'Expected applied-filter pill remove links');

        $withoutWatched = array_values(array_filter(
            $urls,
            static fn(string $u): bool => !str_contains($u, 'watched=1')
        ));
        $this->assertNotEmpty($withoutWatched, 'Expected a pill that removes watched');

        foreach ($withoutWatched as $u) {
            $this->assertStringContainsString('has_attachment=1', $u, "«{$u}» dropped has_attachment");
            $this->assertStringContainsString('resolved_today=1', $u, "«{$u}» dropped resolved_today");
        }
    }
}

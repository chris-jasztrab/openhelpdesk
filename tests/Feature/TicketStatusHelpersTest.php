<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Exercises the ticket status helper functions added in Phase 2.
 *
 * These functions are pure reads from the seeded ticket_statuses table.
 * No data mutation, no cleanup.
 */
class TicketStatusHelpersTest extends TestCase
{
    public function test_ticketStatuses_returns_all_seven_seeded_rows(): void
    {
        $statuses = ticketStatuses();
        // Admins may have added custom statuses in this DB — assert about the
        // seeded ones only by filtering on is_system, not by total count.
        $systemSlugs = array_column(
            array_filter($statuses, static fn(array $r) => $r['is_system']),
            'slug'
        );
        $this->assertSame(
            ['open', 'in_progress', 'pending', 'waiting_on_customer', 'waiting_on_third_party', 'resolved', 'closed'],
            $systemSlugs,
            'system rows must come back in sort_order'
        );
    }

    public function test_ticketStatuses_rows_have_expected_keys(): void
    {
        $row = ticketStatuses()[0];
        // `id` is load-bearing: the admin settings page renders it into
        // `data-id`/form action URLs, so dropping it silently breaks every
        // edit/delete/toggle/reorder POST (they target /0/...).
        $expected = [
            'id', 'slug', 'label', 'bucket', 'pauses_sla', 'sort_order', 'color',
            'is_default_new', 'is_default_resolved', 'is_default_closed',
            'is_system', 'is_active',
        ];
        foreach ($expected as $k) {
            $this->assertArrayHasKey($k, $row, "row should have key «{$k}»");
        }
        $this->assertIsInt($row['id']);
        $this->assertGreaterThan(0, $row['id']);
    }

    public function test_ticketActiveStatuses_returns_only_active(): void
    {
        $active = ticketActiveStatuses();
        foreach ($active as $row) {
            $this->assertTrue($row['is_active'], "active set should not contain {$row['slug']}");
        }
        // At minimum, the 7 seeded slugs are active by default; admins may have
        // added more on top.
        $this->assertGreaterThanOrEqual(7, count($active));
    }

    public function test_ticketStatusSlugs_includes_all_seven_seeded(): void
    {
        $slugs = ticketStatusSlugs();
        foreach (['open', 'in_progress', 'pending', 'waiting_on_customer', 'waiting_on_third_party', 'resolved', 'closed'] as $expected) {
            $this->assertContains($expected, $slugs, "ticketStatusSlugs must include «{$expected}»");
        }
    }

    public function test_ticketActiveStatusSlugs_matches_active_set(): void
    {
        $this->assertSame(ticketStatusSlugs(), ticketActiveStatusSlugs());
    }

    public function test_ticketOpenBucketSlugs_contains_open_through_waiting(): void
    {
        $slugs = ticketOpenBucketSlugs();
        foreach (['open', 'in_progress', 'pending', 'waiting_on_customer', 'waiting_on_third_party'] as $expected) {
            $this->assertContains($expected, $slugs, "open-bucket must include «{$expected}»");
        }
    }

    public function test_ticketClosedBucketSlugs_contains_resolved_and_closed(): void
    {
        $slugs = ticketClosedBucketSlugs();
        $this->assertContains('resolved', $slugs);
        $this->assertContains('closed',   $slugs);
    }

    public function test_ticketSlaPausingSlugs_returns_the_three_waiting_states(): void
    {
        $slugs = ticketSlaPausingSlugs();
        foreach (['pending', 'waiting_on_customer', 'waiting_on_third_party'] as $expected) {
            $this->assertContains($expected, $slugs, "SLA-pausing set must include «{$expected}»");
        }
    }

    public function test_default_slug_accessors_return_expected_values(): void
    {
        $this->assertSame('open',     ticketDefaultNewStatusSlug());
        $this->assertSame('resolved', ticketDefaultResolvedStatusSlug());
        $this->assertSame('closed',   ticketDefaultClosedStatusSlug());
    }

    public function test_ticketStatusLabel_returns_friendly_labels(): void
    {
        $this->assertSame('Open',                   ticketStatusLabel('open'));
        $this->assertSame('In Progress',            ticketStatusLabel('in_progress'));
        $this->assertSame('Waiting on Third Party', ticketStatusLabel('waiting_on_third_party'));
        $this->assertSame('Resolved',               ticketStatusLabel('resolved'));
    }

    public function test_ticketStatusLabel_falls_back_for_unknown_slug(): void
    {
        // Orphan slug → humanized fallback, never empty / never 500.
        $this->assertSame('Some Orphan Status', ticketStatusLabel('some_orphan_status'));
    }

    public function test_ticketStatusColor_returns_hex_for_known(): void
    {
        $this->assertSame('#0d6efd', ticketStatusColor('open'));
        $this->assertSame('#198754', ticketStatusColor('resolved'));
    }

    public function test_ticketStatusColor_falls_back_to_neutral_for_unknown(): void
    {
        $this->assertSame('#6c757d', ticketStatusColor('zzz_unknown'));
    }

    public function test_ticketStatusMeta_returns_row_or_null(): void
    {
        $meta = ticketStatusMeta('pending');
        $this->assertIsArray($meta);
        $this->assertSame('pending', $meta['slug']);
        $this->assertTrue($meta['pauses_sla']);
        $this->assertSame('open', $meta['bucket']);

        $this->assertNull(ticketStatusMeta('does_not_exist'));
    }

    public function test_cache_does_not_re_query_db(): void
    {
        // First call primes the cache; subsequent calls should not increment
        // the connection-level query count. We sample SHOW STATUS LIKE 'Questions'
        // before and after; the diff should be exactly 1 (the second SHOW STATUS
        // itself) if the 5 helper calls hit the cache.
        $pdo = \Database::connect();

        ticketStatuses(); // prime

        $before = (int) $pdo->query("SHOW STATUS LIKE 'Questions'")->fetch(\PDO::FETCH_NUM)[1];
        ticketStatuses();
        ticketOpenBucketSlugs();
        ticketClosedBucketSlugs();
        ticketStatusLabel('open');
        ticketStatusColor('resolved');
        $after = (int) $pdo->query("SHOW STATUS LIKE 'Questions'")->fetch(\PDO::FETCH_NUM)[1];

        // Diff of 1 = only the trailing SHOW STATUS ran. Anything > 1 means a
        // helper re-queried ticket_statuses.
        $this->assertSame(1, $after - $before, 'helpers should not re-query within a request');
    }

    public function test_cache_refresh_does_re_query(): void
    {
        $pdo = \Database::connect();

        ticketStatuses(); // prime

        $before = (int) $pdo->query("SHOW STATUS LIKE 'Questions'")->fetch(\PDO::FETCH_NUM)[1];
        ticketStatusCacheRefresh();
        $after = (int) $pdo->query("SHOW STATUS LIKE 'Questions'")->fetch(\PDO::FETCH_NUM)[1];

        // Refresh + trailing SHOW STATUS = 2.
        $this->assertSame(2, $after - $before, 'cache refresh should re-query');
    }
}

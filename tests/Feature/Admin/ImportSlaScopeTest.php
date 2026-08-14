<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\Support\TestCase;

/**
 * Which imported rows the chosen SLA handling actually applies to.
 *
 * "Start SLA clocks now" and "Apply from the original open date" used to apply
 * to every row in the file. That is the wrong granularity for a real backlog:
 * one legacy ticket type is typically years old and pointless to time against,
 * while the rest of the same export is recent and worth tracking. Cherry-picking
 * those rows out of the CSV by hand is not a workable answer at 9,762 rows.
 *
 * importSlaScopeAllows() is the decision, isolated from the import route so it
 * can be pinned here. Two independent filters, ANDed — an opted-in set of ticket
 * types and an "opened on or after" cutoff.
 *
 * The direction of every failure matters more than the filters themselves: a row
 * this returns false for is imported permanently SLA-exempt, so a bug that
 * wrongly returns *true* arms a clock on an ancient ticket, which is the outcome
 * the whole feature exists to prevent.
 */
class ImportSlaScopeTest extends TestCase
{
    /** Type keys arrive lowercased and array-keyed, as the confirm route builds them. */
    private function types(string ...$labels): array
    {
        return array_fill_keys(array_map('strtolower', $labels), true);
    }

    // ── No filters: the pre-2.170 behaviour ───────────────────────────────────

    /**
     * A post that never rendered the picker — a script, or a future API caller —
     * sends neither filter. That must keep applying the handling file-wide rather
     * than reading the absent fields as "exclude everything", which would
     * silently discard the choice the caller did make.
     */
    public function test_null_filters_allow_every_row(): void
    {
        $this->assertTrue(\importSlaScopeAllows(null, null, 'Anything', '2019-01-01 00:00:00'));
        $this->assertTrue(\importSlaScopeAllows(null, null, '', null));
    }

    // ── Type filter ───────────────────────────────────────────────────────────

    public function test_a_ticked_type_is_in_scope(): void
    {
        $allowed = $this->types('Hardware', 'Software');

        $this->assertTrue(\importSlaScopeAllows($allowed, null, 'Hardware', '2026-01-01 00:00:00'));
    }

    public function test_an_unticked_type_is_out_of_scope(): void
    {
        $allowed = $this->types('Hardware', 'Software');

        $this->assertFalse(
            \importSlaScopeAllows($allowed, null, 'Legacy Archive', '2026-01-01 00:00:00'),
            ' — the noisy legacy type must stay exempt even though the row is recent'
        );
    }

    /**
     * The admin ticks a label rendered from the CSV; the CSV's own casing and
     * padding vary row to row within the same export. Matching has to survive
     * that or the picker silently exempts rows the admin ticked.
     */
    public function test_type_matching_ignores_case_and_surrounding_space(): void
    {
        $allowed = $this->types('Hardware');

        $this->assertTrue(\importSlaScopeAllows($allowed, null, 'HARDWARE', '2026-01-01 00:00:00'));
        $this->assertTrue(\importSlaScopeAllows($allowed, null, '  hardware  ', '2026-01-01 00:00:00'));
    }

    /**
     * Rows with a blank Type cell are their own bucket, keyed '', and are
     * pickable like any other. Legacy exports are full of them, and they must not
     * fall through to "allowed" by accident.
     */
    public function test_blank_type_is_its_own_bucket(): void
    {
        $this->assertTrue(\importSlaScopeAllows($this->types(''), null, '', '2026-01-01 00:00:00'));
        $this->assertFalse(
            \importSlaScopeAllows($this->types('Hardware'), null, '', '2026-01-01 00:00:00'),
            ' — an untyped row is out of scope when its bucket was not ticked'
        );
    }

    public function test_ticking_nothing_excludes_everything(): void
    {
        $this->assertFalse(\importSlaScopeAllows([], null, 'Hardware', '2026-01-01 00:00:00'));
        $this->assertFalse(\importSlaScopeAllows([], null, '', '2026-01-01 00:00:00'));
    }

    // ── Date cutoff ───────────────────────────────────────────────────────────

    public function test_a_row_opened_after_the_cutoff_is_in_scope(): void
    {
        $this->assertTrue(\importSlaScopeAllows(null, '2026-01-01 00:00:00', 'Hardware', '2026-06-01 09:00:00'));
    }

    public function test_a_row_opened_before_the_cutoff_is_out_of_scope(): void
    {
        $this->assertFalse(
            \importSlaScopeAllows(null, '2026-01-01 00:00:00', 'Hardware', '2019-04-02 11:15:00'),
            ' — a 2019 ticket must not get a clock under a 2026 cutoff'
        );
    }

    /** "On or after" is inclusive: midnight on the cutoff date itself counts. */
    public function test_the_cutoff_is_inclusive(): void
    {
        $this->assertTrue(\importSlaScopeAllows(null, '2026-01-01 00:00:00', 'Hardware', '2026-01-01 00:00:00'));
        $this->assertFalse(\importSlaScopeAllows(null, '2026-01-01 00:00:00', 'Hardware', '2025-12-31 23:59:59'));
    }

    /**
     * An unparseable or missing open date under a cutoff has to fail closed. The
     * import falls back to `date('Y-m-d H:i:s')` for the stored created_at, so
     * treating a null as "passes" would hand a clock to precisely the rows whose
     * real age is unknown.
     */
    public function test_a_missing_created_at_fails_closed_under_a_cutoff(): void
    {
        $this->assertFalse(\importSlaScopeAllows(null, '2026-01-01 00:00:00', 'Hardware', null));
    }

    public function test_a_missing_created_at_is_allowed_with_no_cutoff(): void
    {
        $this->assertTrue(
            \importSlaScopeAllows($this->types('Hardware'), null, 'Hardware', null),
            ' — with no cutoff there is nothing to compare, so the type decides alone'
        );
    }

    // ── Both filters together ─────────────────────────────────────────────────

    /**
     * The two filters are ANDed, not ORed. The scenario this feature was built
     * for: a recent ticket of a ticked type gets a clock, and everything else in
     * the same file — old rows of ticked types, recent rows of unticked ones —
     * does not.
     */
    public function test_both_filters_must_pass(): void
    {
        $allowed = $this->types('Hardware');
        $cutoff  = '2026-01-01 00:00:00';

        $this->assertTrue(
            \importSlaScopeAllows($allowed, $cutoff, 'Hardware', '2026-06-01 09:00:00'),
            ' — ticked type, after cutoff'
        );
        $this->assertFalse(
            \importSlaScopeAllows($allowed, $cutoff, 'Hardware', '2019-06-01 09:00:00'),
            ' — ticked type but before the cutoff'
        );
        $this->assertFalse(
            \importSlaScopeAllows($allowed, $cutoff, 'Legacy Archive', '2026-06-01 09:00:00'),
            ' — after the cutoff but the type was not ticked'
        );
        $this->assertFalse(
            \importSlaScopeAllows($allowed, $cutoff, 'Legacy Archive', '2019-06-01 09:00:00'),
            ' — neither filter passes'
        );
    }

    /**
     * Comparison is on 'Y-m-d H:i:s' strings in server local time, which sort
     * chronologically without parsing. A year boundary is where a naive
     * comparison would give up its ordering, so it is worth pinning.
     */
    public function test_comparison_holds_across_a_year_boundary(): void
    {
        $this->assertTrue(\importSlaScopeAllows(null, '2019-12-31 23:00:00', 'Hardware', '2020-01-01 01:00:00'));
        $this->assertFalse(\importSlaScopeAllows(null, '2020-01-01 01:00:00', 'Hardware', '2019-12-31 23:00:00'));
    }
}

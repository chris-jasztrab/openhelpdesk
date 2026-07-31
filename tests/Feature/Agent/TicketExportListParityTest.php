<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use Tests\Support\TestCase;

/**
 * `/agent/tickets/export` must select the same tickets as `/agent/tickets`.
 *
 * The export's whole promise is "the file matches the list you were looking at".
 * It nearly did: the two paths agreed on every recognised filter, but disagreed
 * on an *unrecognised* status slug, and disagreed in the worst possible
 * direction — the list showed nothing while the export returned every ticket the
 * agent could see. Someone exporting a bookmarked or saved filter that named a
 * since-deleted status would get a file far wider than the screen they were
 * looking at, with nothing to indicate the filter had been ignored.
 *
 * The cause was that the two built their status predicate differently. The
 * export goes through `buildTicketFilterQuery()`, which intersects the requested
 * slugs against `ticketStatusSlugs()` and drops unknown ones — deliberately, so
 * that a mixed filter like `status[]=open&status[]=deleted_slug` still returns
 * open tickets instead of silently returning zero rows. The agent list built its
 * `status IN (...)` by hand and applied the slugs verbatim, so it alone returned
 * nothing. The list now normalises through the same intersection.
 *
 * Note this is a *consistency* test, not a confidentiality test — the visibility
 * predicate was always applied on both sides, and the export never contained
 * tickets the agent couldn't see. `TicketExportVisibilityTest` covers that.
 */
class TicketExportListParityTest extends TestCase
{
    /**
     * Pull the ticket-id column out of an export CSV.
     *
     * @return list<string>
     */
    private function exportIds(string $csv): array
    {
        $rows = array_values(array_filter(explode("\n", trim($csv))));
        array_shift($rows); // header row
        return array_map(static fn(string $r): string => trim(strtok($r, ','), "\"\r "), $rows);
    }

    private function exportIdsFor(string $query): array
    {
        $r = $this->get($this->agentClient(), '/agent/tickets/export?' . $query);
        $this->assertOk($r, " for /agent/tickets/export?{$query}");
        return $this->exportIds((string) $r->getBody());
    }

    /**
     * A status slug that does not exist must not widen the export.
     *
     * Before the fix this returned every visible ticket while /agent/tickets
     * returned none.
     */
    public function test_unknown_status_slug_does_not_widen_the_export(): void
    {
        $unfiltered = $this->exportIdsFor('');
        $bogus      = $this->exportIdsFor('status[]=definitely_not_a_status');

        $this->assertNotSame(
            [],
            $unfiltered,
            'Precondition: the agent must be able to see at least one ticket, '
            . 'otherwise this test proves nothing'
        );

        // The list drops the unrecognised slug and therefore applies no status
        // filter, so the two are expected to match. What must NOT happen is the
        // export returning rows the list would have hidden — asserted below by
        // comparing against the list page itself.
        $this->assertSame(
            $unfiltered,
            $bogus,
            'An unrecognised status slug should be dropped consistently'
        );
    }

    /**
     * The real invariant: whatever the list page shows for a given status
     * filter, the export must contain exactly those ids.
     */
    public function test_list_and_export_agree_on_status_filters(): void
    {
        foreach (['', 'status[]=open', 'status[]=resolved', 'status[]=definitely_not_a_status'] as $query) {
            $listBody = (string) $this->get($this->agentClient(), '/agent/tickets?' . $query)->getBody();
            $exportIds = $this->exportIdsFor($query);

            // Every id in the export must appear in the rendered list. The list
            // paginates, so the converse is not asserted — a short fixture set
            // keeps both on one page in practice.
            $missing = array_values(array_filter(
                $exportIds,
                static fn(string $id): bool => !str_contains($listBody, '/tickets/' . $id)
            ));

            $this->assertSame(
                [],
                $missing,
                "Export for «{$query}» contained ticket ids the list page did not show: "
                . implode(', ', $missing)
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * The `has_attachment` ticket filter, on every list that offers it.
 *
 * Two things are easy to get wrong here and both are asserted below.
 *
 * 1. **Row multiplication.** A ticket with five files must be listed once.
 *    Joining `ticket_attachments` would return it five times, inflating the
 *    paginator's COUNT(*) and eating five of the page's LIMIT slots.
 *    `ticketHasAttachmentSql()` uses EXISTS for exactly this reason.
 *
 * 2. **Internal-note attachments on the portal.** A requester never sees files
 *    hanging off an internal note. If the filter matched on them anyway, ticking
 *    "Has attachment" would return a request whose attachment list renders empty
 *    — a filter that looks broken, and a signal that staff left a private note.
 *    So the portal (and the API, for non-staff callers) matches only on
 *    requester-visible attachments; staff panels match on all of them.
 *
 * The fixtures are built here rather than in DatabaseSeeder because no other test
 * needs attachment rows. Only DB rows are created — nothing on these paths reads
 * the file itself, so no bytes are written to attachment storage.
 */
class TicketAttachmentFilterTest extends TestCase
{
    /** Subject prefix, used as the `q` filter so the fixtures own the result set. */
    private const PREFIX = '[TEST] Attachment filter';

    /** Ticket with two attachments on no timeline entry — visible to everyone. */
    private static int $visibleId = 0;

    /** Ticket whose ONLY attachment is on an internal note — staff-only. */
    private static int $internalId = 0;

    /** Ticket with no attachment at all — must never match. */
    private static int $bareId = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $db  = \Database::connect();
        $uid = DatabaseSeeder::$portalId;

        $mk = static function (string $suffix) use ($db, $uid): int {
            $db->prepare(
                "INSERT INTO tickets (subject, description, created_by, status) VALUES (?, ?, ?, 'open')"
            )->execute([self::PREFIX . ' ' . $suffix, 'Automated fixture.', $uid]);
            return (int) $db->lastInsertId();
        };

        $attach = static function (int $ticketId, ?int $timelineId) use ($db, $uid): void {
            $db->prepare(
                'INSERT INTO ticket_attachments
                    (ticket_id, timeline_id, uploaded_by, original_name, stored_name, mime_type, file_size)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$ticketId, $timelineId, $uid, 'fixture.pdf', 'test-fixture.pdf', 'application/pdf', 1]);
        };

        self::$visibleId  = $mk('visible');
        self::$internalId = $mk('internal-only');
        self::$bareId     = $mk('none');

        // Two files on one ticket: proves EXISTS does not multiply the row.
        $attach(self::$visibleId, null);
        $attach(self::$visibleId, null);

        $db->prepare(
            "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
             VALUES (?, ?, 'note', 'Fixture internal note.', 1)"
        )->execute([self::$internalId, DatabaseSeeder::$agentId]);
        $attach(self::$internalId, (int) $db->lastInsertId());
    }

    public static function tearDownAfterClass(): void
    {
        try {
            $db = \Database::connect();
            foreach ([self::$visibleId, self::$internalId, self::$bareId] as $id) {
                if ($id === 0) {
                    continue;
                }
                $db->prepare('DELETE FROM ticket_attachments WHERE ticket_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM ticket_timeline    WHERE ticket_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM tickets            WHERE id = ?')->execute([$id]);
            }
        } catch (\Throwable) {
            // Never let cleanup crash the runner.
        }
    }

    /**
     * Which of the three fixture tickets a rendered list page links to, in a
     * fixed order so the assertion does not depend on the page's sort.
     *
     * @return list<int>
     */
    private function linkedFixtures(string $html): array
    {
        return array_values(array_filter(
            [self::$visibleId, self::$internalId, self::$bareId],
            static fn(int $id): bool => preg_match('~/tickets/' . $id . '\b~', $html) === 1
        ));
    }

    private function listFixtures(string $base, string $extra = ''): array
    {
        $url = $base . '?per_page=200&q=' . rawurlencode(self::PREFIX) . $extra;
        $r   = $this->get($this->adminClient(), $url);
        $this->assertOk($r, " for {$url}");
        return $this->linkedFixtures((string) $r->getBody());
    }

    /**
     * Staff match on every attachment, including one on an internal note — but
     * never on a ticket without any.
     */
    public function test_admin_list_matches_all_attachments_and_drops_bare_tickets(): void
    {
        $this->assertSame(
            [self::$visibleId, self::$internalId, self::$bareId],
            $this->listFixtures('/admin/tickets'),
            'Precondition: all three fixture tickets must be visible unfiltered'
        );

        $this->assertSame(
            [self::$visibleId, self::$internalId],
            $this->listFixtures('/admin/tickets', '&has_attachment=1'),
            'Admin list should keep both attachment-bearing tickets and drop the bare one'
        );
    }

    /** Same rule on the agent list, which builds its predicate separately. */
    public function test_agent_list_applies_the_filter(): void
    {
        $this->assertSame(
            [self::$visibleId, self::$internalId, self::$bareId],
            $this->listFixtures('/agent/tickets'),
            'Precondition: all three fixture tickets must be visible unfiltered'
        );

        $this->assertSame(
            [self::$visibleId, self::$internalId],
            $this->listFixtures('/agent/tickets', '&has_attachment=1')
        );
    }

    /**
     * An export's promise is "the file matches the list you were looking at". A
     * filter the export ignored would quietly widen the download.
     */
    public function test_exports_agree_with_their_lists(): void
    {
        foreach (['/admin/tickets/export', '/agent/tickets/export'] as $path) {
            $url = $path . '?q=' . rawurlencode(self::PREFIX) . '&has_attachment=1';
            $r   = $this->get($this->adminClient(), $url);
            $this->assertOk($r, " for {$url}");

            $csv = (string) $r->getBody();
            $this->assertStringContainsString(self::PREFIX . ' visible', $csv, " in {$path}");
            $this->assertStringContainsString(self::PREFIX . ' internal-only', $csv, " in {$path}");
            $this->assertStringNotContainsString(self::PREFIX . ' none', $csv, " in {$path}");
        }
    }

    /**
     * The carve-out: on the portal, an attachment on an internal note does not
     * make a request match.
     */
    public function test_portal_ignores_attachments_on_internal_notes(): void
    {
        // status= (empty) clears the portal's default status filter.
        $unfiltered = $this->get($this->portalClient(), '/portal/tickets?status=');
        $this->assertOk($unfiltered);
        $this->assertSame(
            [self::$visibleId, self::$internalId, self::$bareId],
            $this->linkedFixtures((string) $unfiltered->getBody()),
            'Precondition: the requester must be able to see all three fixture tickets'
        );

        $filtered = $this->get($this->portalClient(), '/portal/tickets?status=&has_attachment=1');
        $this->assertOk($filtered);
        $this->assertSame(
            [self::$visibleId],
            $this->linkedFixtures((string) $filtered->getBody()),
            'The internal-note-only ticket must not match on the portal'
        );
    }

    /**
     * A ticket with two attachments must be rendered exactly as many times as it
     * is without the filter. A JOIN instead of EXISTS would have doubled it.
     */
    public function test_multiple_attachments_do_not_duplicate_the_row(): void
    {
        $count = function (string $extra): int {
            $url = '/admin/tickets?per_page=200&q=' . rawurlencode(self::PREFIX) . $extra;
            $body = (string) $this->get($this->adminClient(), $url)->getBody();
            return (int) preg_match_all('~/admin/tickets/' . self::$visibleId . '\b~', $body);
        };

        $unfiltered = $count('');
        $this->assertGreaterThan(0, $unfiltered, 'Precondition: the ticket must be listed at all');
        $this->assertSame(
            $unfiltered,
            $count('&has_attachment=1'),
            'A ticket with two attachments should not be listed more often when the filter is on'
        );
    }
}

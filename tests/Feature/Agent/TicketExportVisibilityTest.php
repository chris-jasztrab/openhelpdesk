<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * /agent/tickets/export — the CSV must never contain a ticket the agent is not
 * allowed to see.  This is the security-critical half of the sprint: the route
 * is the only place ticketStaffVisibilitySql() stands between an agent and
 * every confidential / out-of-group ticket in the database.
 *
 * Fixtures built here (all removed in tearDownAfterClass):
 *   [TEST] Sealed Group        — a group the test agent is NOT a member of
 *   [TEST] Sealed Type         — is_confidential = 1, owned by that group
 *   [TEST] EXPORT-LEAK-CANARY  — a ticket of that type, created by the admin
 *   [TEST] EXPORT-OUTGROUP     — non-confidential, but in the sealed group and
 *                                neither created by, assigned to nor watched by
 *                                the agent (this agent has no view_all)
 *
 * The seeded ticket ([TEST] Test Ticket, which the agent watches) is asserted
 * to be PRESENT so an empty/blank file cannot make these tests pass.
 */
class TicketExportVisibilityTest extends TestCase
{
    private const SEALED_GROUP  = '[TEST] Sealed Group';
    private const SEALED_TYPE   = '[TEST] Sealed Type';
    private const CANARY        = '[TEST] EXPORT-LEAK-CANARY confidential payroll';
    private const OUTGROUP      = '[TEST] EXPORT-OUTGROUP other team ticket';

    private static int $groupId       = 0;
    private static int $typeId        = 0;
    private static int $canaryId      = 0;
    private static int $outGroupId    = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::removeFixtures();          // drain anything a crashed earlier run left
        $db = \Database::connect();

        $db->prepare('INSERT INTO `groups` (name, description) VALUES (?, ?)')
           ->execute([self::SEALED_GROUP, 'Fixture for agent-export visibility test.']);
        self::$groupId = (int) $db->lastInsertId();

        // Belt and braces: the agent must not be a member of this group.
        $db->prepare('DELETE FROM group_user_map WHERE group_id = ?')->execute([self::$groupId]);

        $db->prepare('INSERT INTO ticket_types (name, group_id, is_confidential) VALUES (?, ?, 1)')
           ->execute([self::SEALED_TYPE, self::$groupId]);
        self::$typeId = (int) $db->lastInsertId();

        $ins = $db->prepare(
            "INSERT INTO tickets (subject, description, created_by, status, type_id, group_id)
             VALUES (?, ?, ?, 'open', ?, ?)"
        );
        $ins->execute([self::CANARY, 'Fixture.', DatabaseSeeder::$adminId, self::$typeId, self::$groupId]);
        self::$canaryId = (int) $db->lastInsertId();

        $ins->execute([self::OUTGROUP, 'Fixture.', DatabaseSeeder::$adminId, null, self::$groupId]);
        self::$outGroupId = (int) $db->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        self::removeFixtures();
        parent::tearDownAfterClass();
    }

    /** Delete by literal name, not by captured id, so leftovers self-heal. */
    private static function removeFixtures(): void
    {
        try {
            $db = \Database::connect();

            $ids = $db->prepare('SELECT id FROM tickets WHERE subject IN (?, ?)');
            $ids->execute([self::CANARY, self::OUTGROUP]);
            foreach ($ids->fetchAll(\PDO::FETCH_COLUMN) as $id) {
                $db->prepare('DELETE FROM ticket_attachments WHERE ticket_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM ticket_watchers    WHERE ticket_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM ticket_timeline     WHERE ticket_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM tickets             WHERE id = ?')->execute([$id]);
            }

            $tids = $db->prepare('SELECT id FROM ticket_types WHERE name = ?');
            $tids->execute([self::SEALED_TYPE]);
            foreach ($tids->fetchAll(\PDO::FETCH_COLUMN) as $tid) {
                $db->prepare('DELETE FROM ticket_type_priorities WHERE type_id = ?')->execute([$tid]);
                $db->prepare('DELETE FROM ticket_types WHERE id = ?')->execute([$tid]);
            }

            $gids = $db->prepare('SELECT id FROM `groups` WHERE name = ?');
            $gids->execute([self::SEALED_GROUP]);
            foreach ($gids->fetchAll(\PDO::FETCH_COLUMN) as $gid) {
                $db->prepare('DELETE FROM group_user_map WHERE group_id = ?')->execute([$gid]);
                $db->prepare('DELETE FROM `groups` WHERE id = ?')->execute([$gid]);
            }
        } catch (\Throwable) {
            // never let cleanup mask a real failure
        }
    }

    // ── Preconditions ─────────────────────────────────────────────────────────

    /**
     * Everything below reasons about "the agent has no groups and no view_all".
     * Assert that rather than assume it — if a future fixture change puts the
     * test agent in a group, these tests would silently stop testing anything.
     */
    public function test_precondition_agent_has_no_groups(): void
    {
        $db = \Database::connect();
        $q  = $db->prepare('SELECT COUNT(*) FROM group_user_map WHERE user_id = ?');
        $q->execute([DatabaseSeeder::$agentId]);
        $this->assertSame(0, (int) $q->fetchColumn(), 'Fixture agent must belong to no group.');

        $p = $db->prepare(
            "SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id = rp.role_id
             WHERE r.slug = 'agent' AND rp.perm_key = 'tickets.view_all'"
        );
        $p->execute();
        $this->assertSame(0, (int) $p->fetchColumn(), "The 'agent' role must not have tickets.view_all.");
    }

    public function test_precondition_fixtures_exist(): void
    {
        $this->assertGreaterThan(0, self::$canaryId, 'Confidential canary ticket was not created.');
        $this->assertGreaterThan(0, self::$outGroupId, 'Out-of-group ticket was not created.');

        $db = \Database::connect();
        $q  = $db->prepare('SELECT is_confidential, group_id FROM ticket_types WHERE id = ?');
        $q->execute([self::$typeId]);
        $row = $q->fetch();
        $this->assertSame(1, (int) $row['is_confidential']);
        $this->assertSame(self::$groupId, (int) $row['group_id']);
    }

    // ── The leak tests ────────────────────────────────────────────────────────

    public function test_export_is_a_csv_download_for_an_agent(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets/export');
        $this->assertOk($r);
        $this->assertStringContainsString('text/csv', $r->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment', $r->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString('.csv', $r->getHeaderLine('Content-Disposition'));
    }

    public function test_export_has_the_expected_header_row(): void
    {
        $rows = $this->csvRows($this->get($this->agentClient(), '/agent/tickets/export'));
        $this->assertNotEmpty($rows, 'CSV had no rows at all.');
        $this->assertSame(
            ['ID', 'Subject', 'Status', 'Priority', 'Type', 'Location',
             'Group', 'Assigned To', 'Created By', 'Tags', 'Created', 'Due Date', 'SLA State'],
            $rows[0]
        );
    }

    public function test_export_does_not_leak_a_confidential_out_of_group_ticket(): void
    {
        $r    = $this->get($this->agentClient(), '/agent/tickets/export');
        $body = (string) $r->getBody();

        // Not by subject …
        $this->assertStringNotContainsString(
            self::CANARY,
            $body,
            'LEAK: confidential ticket subject appeared in the agent CSV export.'
        );
        // … and not by id either (a redaction that kept the row would still leak
        // the ticket id, group and timestamps).
        $this->assertNotContains(
            (string) self::$canaryId,
            $this->csvIds($r),
            'LEAK: confidential ticket id appeared as a row in the agent CSV export.'
        );
        // The sealed group / type names must not appear anywhere either.
        $this->assertStringNotContainsString(self::SEALED_TYPE, $body, 'LEAK: confidential type name in export.');
    }

    public function test_export_does_not_leak_a_non_confidential_out_of_group_ticket(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets/export');

        $this->assertStringNotContainsString(
            self::OUTGROUP,
            (string) $r->getBody(),
            'LEAK: out-of-group ticket appeared in the agent CSV export.'
        );
        $this->assertNotContains((string) self::$outGroupId, $this->csvIds($r));
    }

    public function test_export_does_contain_a_ticket_the_agent_may_see(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets/export');

        $this->assertStringContainsString(
            '[TEST] Test Ticket',
            (string) $r->getBody(),
            'The export is missing a ticket the agent watches — the absence assertions above would be vacuous.'
        );
        $this->assertContains((string) DatabaseSeeder::$ticketId, $this->csvIds($r));
    }

    /**
     * The strongest form: the exact id set in the file must equal the set an
     * independently-written visibility query produces.  Deliberately does NOT
     * reuse ticketStaffVisibilitySql() — that is the code under test.
     */
    public function test_export_id_set_equals_independently_computed_visible_set(): void
    {
        $me = DatabaseSeeder::$agentId;
        $db = \Database::connect();

        // Agent role: no groups, no tickets.view_all (asserted above), so:
        //   confidential  -> only tickets they created
        //   normal        -> assigned to / created by / watched
        $q = $db->prepare(
            'SELECT t.id
               FROM tickets t
               LEFT JOIN ticket_types tt ON tt.id = t.type_id
              WHERE (COALESCE(tt.is_confidential, 0) = 1 AND t.created_by = ?)
                 OR (COALESCE(tt.is_confidential, 0) = 0
                     AND (t.assigned_to = ? OR t.created_by = ?
                          OR t.id IN (SELECT ticket_id FROM ticket_watchers WHERE user_id = ?)))'
        );
        $q->execute([$me, $me, $me, $me]);
        $expected = array_map('strval', $q->fetchAll(\PDO::FETCH_COLUMN));

        $actual = $this->csvIds($this->get($this->agentClient(), '/agent/tickets/export'));

        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual, 'Export id set diverges from the visible set.');
    }

    // ── Admin behaviour on the same route ─────────────────────────────────────

    /**
     * Admins reach this route too (requireStaff), where the visibility predicate
     * is 1=1 — so the confidential row IS in their file, but redacted, because
     * the test admin is not in the owning group.
     */
    public function test_admin_export_redacts_a_confidential_ticket_outside_their_groups(): void
    {
        $db = \Database::connect();
        $g  = $db->prepare('SELECT COUNT(*) FROM group_user_map WHERE user_id = ? AND group_id = ?');
        $g->execute([DatabaseSeeder::$adminId, self::$groupId]);
        $this->assertSame(0, (int) $g->fetchColumn(), 'Test admin must not be in the sealed group.');

        $r = $this->get($this->adminClient(), '/agent/tickets/export');
        $this->assertOk($r);

        $ids = $this->csvIds($r);
        $this->assertContains((string) self::$canaryId, $ids, 'Admin export should still list the row.');
        $this->assertStringNotContainsString(
            self::CANARY,
            (string) $r->getBody(),
            'Admin export leaked the confidential subject instead of redacting it.'
        );
        $this->assertStringContainsString('[Confidential]', (string) $r->getBody());
    }

    // ── Role enforcement ──────────────────────────────────────────────────────

    public function test_portal_user_cannot_reach_the_agent_export(): void
    {
        $r = $this->get($this->portalClient(), '/agent/tickets/export', follow: false);
        $this->assertForbidden($r, ' — portal user on /agent/tickets/export');
        $this->assertStringNotContainsString('text/csv', $r->getHeaderLine('Content-Type'));
    }

    public function test_guest_cannot_reach_the_agent_export(): void
    {
        $r = $this->get($this->guestClient(), '/agent/tickets/export', follow: false);
        $this->assertForbidden($r, ' — guest on /agent/tickets/export');
        $this->assertStringNotContainsString('text/csv', $r->getHeaderLine('Content-Type'));
    }

    /**
     * "export" must not be swallowed by /agent/tickets/{id} — if route order
     * regressed, this would 404 or render an HTML ticket page.
     */
    public function test_export_route_is_not_shadowed_by_the_ticket_id_route(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets/export');
        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringNotContainsString('text/html', $r->getHeaderLine('Content-Type'));
    }

    // ── Filters still cannot widen visibility ─────────────────────────────────

    /**
     * The obvious attack: ask for the sealed group / confidential type by id.
     * A filter must never be able to add rows the visibility predicate excluded.
     */
    public function test_filtering_for_the_sealed_group_returns_nothing(): void
    {
        $r = $this->get(
            $this->agentClient(),
            '/agent/tickets/export?group%5B%5D=' . self::$groupId
        );
        $this->assertOk($r);
        $this->assertStringNotContainsString(self::CANARY, (string) $r->getBody());
        $this->assertStringNotContainsString(self::OUTGROUP, (string) $r->getBody());
        $this->assertSame([], $this->csvIds($r), 'Filtering by an out-of-group group returned rows.');
    }

    public function test_filtering_for_the_confidential_type_returns_nothing(): void
    {
        $r = $this->get(
            $this->agentClient(),
            '/agent/tickets/export?type%5B%5D=' . self::$typeId
        );
        $this->assertOk($r);
        $this->assertStringNotContainsString(self::CANARY, (string) $r->getBody());
        $this->assertSame([], $this->csvIds($r), 'Filtering by a confidential type returned rows.');
    }

    public function test_searching_for_the_canary_subject_returns_nothing(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets/export?q=EXPORT-LEAK-CANARY');
        $this->assertOk($r);
        $this->assertStringNotContainsString(self::CANARY, (string) $r->getBody());
        $this->assertSame([], $this->csvIds($r));
    }

    /**
     * Non-vacuity guard.  Grant the agent membership of the sealed group and the
     * very same request must now return both fixtures — proving the exclusions
     * above come from a live, discriminating predicate rather than from the
     * fixtures being unreachable, the route erroring, or the file being empty.
     */
    public function test_group_membership_flips_both_fixtures_into_the_export(): void
    {
        $db = \Database::connect();
        $db->prepare('INSERT INTO group_user_map (group_id, user_id, is_manager) VALUES (?, ?, 0)')
           ->execute([self::$groupId, DatabaseSeeder::$agentId]);

        try {
            $r    = $this->get($this->agentClient(), '/agent/tickets/export');
            $body = (string) $r->getBody();
            $ids  = $this->csvIds($r);

            $this->assertStringContainsString(self::CANARY, $body, 'Confidential-group member should see the row.');
            $this->assertContains((string) self::$canaryId, $ids);
            $this->assertStringContainsString(self::OUTGROUP, $body, 'Group member should see the group ticket.');
            $this->assertContains((string) self::$outGroupId, $ids);
        } finally {
            $db->prepare('DELETE FROM group_user_map WHERE group_id = ? AND user_id = ?')
               ->execute([self::$groupId, DatabaseSeeder::$agentId]);
        }

        // And it must go away again once membership is revoked.
        $after = $this->csvIds($this->get($this->agentClient(), '/agent/tickets/export'));
        $this->assertNotContains((string) self::$canaryId, $after);
        $this->assertNotContains((string) self::$outGroupId, $after);
    }

    // ── CSV helpers ───────────────────────────────────────────────────────────

    /** @return array<int, array<int, string>> parsed rows, header first */
    private function csvRows(\Psr\Http\Message\ResponseInterface $r): array
    {
        $body = (string) $r->getBody();
        // Strip the Excel BOM the route writes.
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            $body = substr($body, 3);
        }

        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $body);
        rewind($fh);

        $rows = [];
        while (($row = fgetcsv($fh)) !== false) {
            if ($row === [null] || $row === ['']) {
                continue; // trailing newline
            }
            $rows[] = array_map(static fn($v) => (string) $v, $row);
        }
        fclose($fh);

        return $rows;
    }

    /** @return array<int, string> the ID column of every data row */
    private function csvIds(\Psr\Http\Message\ResponseInterface $r): array
    {
        $rows = $this->csvRows($r);
        array_shift($rows); // header
        return array_values(array_filter(array_map(static fn($row) => $row[0] ?? '', $rows), 'strlen'));
    }
}

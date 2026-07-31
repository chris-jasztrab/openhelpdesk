<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * The created_today / resolved_today drill-downs swapped
 *
 *     DATE(col) = CURDATE()
 * for
 *     col >= CURDATE() AND col < CURDATE() + INTERVAL 1 DAY
 *
 * Semantics must be identical.  This asks the database both questions directly
 * and compares the id sets, with boundary rows planted at the exact instants
 * where a half-open range can go wrong.
 *
 * Boundary fixtures (all created_by the test agent so the HTTP checks can see
 * them; all removed in tearDownAfterClass):
 *
 *   BOUNDARY today 00:00:00       — in
 *   BOUNDARY today 23:59:59       — in
 *   BOUNDARY yesterday 23:59:59   — out (one second before the window)
 *   BOUNDARY tomorrow 00:00:00    — out (the exclusive upper bound itself)
 */
class SargableDatePredicateTest extends TestCase
{
    private const OLD_CREATED = 'DATE(t.created_at) = CURDATE()';
    private const NEW_CREATED = 't.created_at >= CURDATE() AND t.created_at < CURDATE() + INTERVAL 1 DAY';

    private const OLD_UPDATED = "t.status = 'resolved' AND DATE(t.updated_at) = CURDATE()";
    private const NEW_UPDATED = "t.status = 'resolved'
                                 AND t.updated_at >= CURDATE() AND t.updated_at < CURDATE() + INTERVAL 1 DAY";

    /** subject => [created_at expr, updated_at expr, status] — MySQL expressions so CURDATE() is the DB's idea of today */
    private const FIXTURES = [
        '[TEST] BOUNDARY created today 00:00:00' => ['CURDATE()',                                     'CURDATE()',                                     'open'],
        '[TEST] BOUNDARY created today 23:59:59' => ['CURDATE() + INTERVAL 1 DAY - INTERVAL 1 SECOND', 'CURDATE() + INTERVAL 1 DAY - INTERVAL 1 SECOND', 'open'],
        '[TEST] BOUNDARY created yesterday end'  => ['CURDATE() - INTERVAL 1 SECOND',                  'CURDATE() - INTERVAL 1 SECOND',                  'open'],
        '[TEST] BOUNDARY created tomorrow start' => ['CURDATE() + INTERVAL 1 DAY',                     'CURDATE() + INTERVAL 1 DAY',                     'open'],
        '[TEST] BOUNDARY resolved today 00:00:00'=> ['CURDATE()',                                      'CURDATE()',                                      'resolved'],
        '[TEST] BOUNDARY resolved yesterday end' => ['CURDATE() - INTERVAL 1 SECOND',                  'CURDATE() - INTERVAL 1 SECOND',                  'resolved'],
    ];

    /** @var array<string, int> subject => ticket id */
    private static array $ids = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::removeFixtures();
        $db = \Database::connect();

        foreach (self::FIXTURES as $subject => [$createdExpr, $updatedExpr, $status]) {
            $db->prepare(
                "INSERT INTO tickets (subject, description, created_by, status, created_at, updated_at)
                 VALUES (?, 'Sargable boundary fixture.', ?, ?, {$createdExpr}, {$updatedExpr})"
            )->execute([$subject, DatabaseSeeder::$agentId, $status]);
            self::$ids[$subject] = (int) $db->lastInsertId();
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::removeFixtures();
        parent::tearDownAfterClass();
    }

    private static function removeFixtures(): void
    {
        try {
            $db  = \Database::connect();
            $ph  = implode(',', array_fill(0, count(self::FIXTURES), '?'));
            $ids = $db->prepare("SELECT id FROM tickets WHERE subject IN ($ph)");
            $ids->execute(array_keys(self::FIXTURES));
            foreach ($ids->fetchAll(\PDO::FETCH_COLUMN) as $id) {
                $db->prepare('DELETE FROM ticket_watchers WHERE ticket_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM ticket_timeline  WHERE ticket_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM tickets          WHERE id = ?')->execute([$id]);
            }
        } catch (\Throwable) {
            // never mask a real failure
        }
    }

    // ── Fixtures really landed on the boundaries ───────────────────────────────

    public function test_boundary_fixtures_have_the_intended_timestamps(): void
    {
        $db = \Database::connect();
        $q  = $db->prepare('SELECT created_at, updated_at FROM tickets WHERE id = ?');

        $expect = [
            '[TEST] BOUNDARY created today 00:00:00'  => 'today 00:00:00',
            '[TEST] BOUNDARY created today 23:59:59'  => 'today 23:59:59',
            '[TEST] BOUNDARY created yesterday end'   => 'yesterday 23:59:59',
            '[TEST] BOUNDARY created tomorrow start'  => 'tomorrow 00:00:00',
        ];

        $today = (string) $db->query('SELECT CURDATE()')->fetchColumn();
        $tomorrow = date('Y-m-d', strtotime($today . ' +1 day'));
        $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));

        $wanted = [
            'today 00:00:00'     => "$today 00:00:00",
            'today 23:59:59'     => "$today 23:59:59",
            'yesterday 23:59:59' => "$yesterday 23:59:59",
            'tomorrow 00:00:00'  => "$tomorrow 00:00:00",
        ];

        foreach ($expect as $subject => $label) {
            $q->execute([self::$ids[$subject]]);
            $row = $q->fetch();
            $this->assertSame($wanted[$label], $row['created_at'], " — $subject created_at");
            $this->assertSame($wanted[$label], $row['updated_at'], " — $subject updated_at");
        }
    }

    // ── Old vs new predicate, whole table ─────────────────────────────────────

    public function test_created_today_predicates_select_the_same_ids(): void
    {
        $old = $this->idsMatching(self::OLD_CREATED);
        $new = $this->idsMatching(self::NEW_CREATED);

        $this->assertNotEmpty($old, 'No ticket matches DATE(created_at)=CURDATE() — comparison would be vacuous.');
        $this->assertSame($old, $new, 'The created_today rewrite changed the result set.');
    }

    public function test_resolved_today_predicates_select_the_same_ids(): void
    {
        $old = $this->idsMatching(self::OLD_UPDATED);
        $new = $this->idsMatching(self::NEW_UPDATED);

        $this->assertNotEmpty($old, 'No resolved ticket updated today — comparison would be vacuous.');
        $this->assertSame($old, $new, 'The resolved_today rewrite changed the result set.');
    }

    /**
     * A row-by-row XOR: any single ticket where the two forms disagree shows up
     * here even if the two id lists happened to be the same length.
     */
    public function test_no_single_row_disagrees_between_the_two_forms(): void
    {
        $db = \Database::connect();

        $createdDiff = $db->query(
            'SELECT COUNT(*) FROM tickets t WHERE (' . self::OLD_CREATED . ') <> (' . self::NEW_CREATED . ')'
        )->fetchColumn();
        $this->assertSame(0, (int) $createdDiff, 'created_at: old and new predicates disagree on some row.');

        $updatedDiff = $db->query(
            'SELECT COUNT(*) FROM tickets t WHERE (' . self::OLD_UPDATED . ') <> (' . self::NEW_UPDATED . ')'
        )->fetchColumn();
        $this->assertSame(0, (int) $updatedDiff, 'updated_at: old and new predicates disagree on some row.');
    }

    // ── Boundary membership ───────────────────────────────────────────────────

    public function test_created_boundaries_land_on_the_expected_side(): void
    {
        $new = $this->idsMatching(self::NEW_CREATED);
        $old = $this->idsMatching(self::OLD_CREATED);

        foreach ([
            ['[TEST] BOUNDARY created today 00:00:00', true],
            ['[TEST] BOUNDARY created today 23:59:59', true],
            ['[TEST] BOUNDARY created yesterday end',  false],
            ['[TEST] BOUNDARY created tomorrow start', false],
        ] as [$subject, $shouldMatch]) {
            $id = self::$ids[$subject];
            if ($shouldMatch) {
                $this->assertContains($id, $new, " — $subject should be inside the new range");
                $this->assertContains($id, $old, " — $subject should be inside the old range");
            } else {
                $this->assertNotContains($id, $new, " — $subject should be outside the new range");
                $this->assertNotContains($id, $old, " — $subject should be outside the old range");
            }
        }
    }

    public function test_resolved_boundaries_land_on_the_expected_side(): void
    {
        $new = $this->idsMatching(self::NEW_UPDATED);
        $old = $this->idsMatching(self::OLD_UPDATED);

        $in  = self::$ids['[TEST] BOUNDARY resolved today 00:00:00'];
        $out = self::$ids['[TEST] BOUNDARY resolved yesterday end'];

        $this->assertContains($in, $new);
        $this->assertContains($in, $old);
        $this->assertNotContains($out, $new);
        $this->assertNotContains($out, $old);
    }

    /**
     * The rewrite's stated justification is that both columns are NOT NULL, so
     * neither form has a NULL edge case.  Verify that, rather than trust it.
     */
    public function test_both_columns_are_not_nullable(): void
    {
        $db = \Database::connect();
        foreach (['created_at', 'updated_at'] as $col) {
            $q = $db->prepare(
                'SELECT IS_NULLABLE FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $q->execute(['tickets', $col]);
            $this->assertSame('NO', (string) $q->fetchColumn(), "tickets.$col is nullable — the rewrite's premise fails.");
        }
    }

    // ── Through the app ───────────────────────────────────────────────────────

    public function test_created_today_filter_shows_the_midnight_ticket_and_not_yesterdays(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets?created_today=1');
        $this->assertOk($r);

        $this->assertSee('[TEST] BOUNDARY created today 00:00:00', $r, ' — created_today=1');
        $this->assertSee('[TEST] BOUNDARY created today 23:59:59', $r, ' — created_today=1');
        $this->assertNotSee('[TEST] BOUNDARY created yesterday end', $r, ' — created_today=1');
        $this->assertNotSee('[TEST] BOUNDARY created tomorrow start', $r, ' — created_today=1');
    }

    public function test_resolved_today_filter_shows_the_midnight_ticket_and_not_yesterdays(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets?resolved_today=1');
        $this->assertOk($r);

        $this->assertSee('[TEST] BOUNDARY resolved today 00:00:00', $r, ' — resolved_today=1');
        $this->assertNotSee('[TEST] BOUNDARY resolved yesterday end', $r, ' — resolved_today=1');
    }

    /**
     * The export route re-implements the same drill-downs, so it must agree with
     * the list page rather than drift.
     */
    public function test_export_created_today_matches_the_same_boundaries(): void
    {
        $body = (string) $this->get($this->agentClient(), '/agent/tickets/export?created_today=1')->getBody();

        $this->assertStringContainsString('[TEST] BOUNDARY created today 00:00:00', $body);
        $this->assertStringContainsString('[TEST] BOUNDARY created today 23:59:59', $body);
        $this->assertStringNotContainsString('[TEST] BOUNDARY created yesterday end', $body);
        $this->assertStringNotContainsString('[TEST] BOUNDARY created tomorrow start', $body);
    }

    public function test_export_resolved_today_matches_the_same_boundaries(): void
    {
        $body = (string) $this->get($this->agentClient(), '/agent/tickets/export?resolved_today=1')->getBody();

        $this->assertStringContainsString('[TEST] BOUNDARY resolved today 00:00:00', $body);
        $this->assertStringNotContainsString('[TEST] BOUNDARY resolved yesterday end', $body);
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /** @return array<int, int> sorted ticket ids matching a raw predicate */
    private function idsMatching(string $predicate): array
    {
        $ids = \Database::connect()
            ->query("SELECT t.id FROM tickets t WHERE $predicate ORDER BY t.id")
            ->fetchAll(\PDO::FETCH_COLUMN);

        return array_map('intval', $ids);
    }
}

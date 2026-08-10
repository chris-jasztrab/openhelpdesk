<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Sla;
use Tests\Support\TestCase;

/**
 * SLA behaviour for imported tickets.
 *
 * A CSV import brings in tickets whose `created_at` is the legacy open date,
 * often years old. Two guarantees are pinned here:
 *
 *  - An SLA-exempt ticket never acquires a clock, even when someone changes its
 *    priority or type. Before the `sla_exempt` column existed, that recompute
 *    measured from `created_at` and silently breached the whole backlog — a bulk
 *    priority change could do it to hundreds of tickets at once.
 *
 *  - When an import *does* apply SLA, the clock counts from `sla_started_at`,
 *    not `created_at`, so "start the clocks now" genuinely gives a fresh window.
 *
 * The suite rewrites SLA settings and policies, so both are snapshotted and
 * restored around every test, along with the tickets it creates.
 *
 * setUp() asserts SLA is actually enabled before each test: every Sla method
 * returns early when it isn't, so without that guard the whole class would pass
 * while asserting nothing.
 */
class SlaImportBaselineTest extends TestCase
{
    private PDO $db;
    private array $policySnapshot = [];
    private array $settingSnapshot = [];
    private array $ticketIds = [];
    private int $priorityId;
    private int $userId;

    private const SETTING_KEYS = ['sla_enabled', 'business_hours_timezone', 'business_hours_schedule'];

    protected function setUp(): void
    {
        require_once ROOT_DIR . '/src/Sla.php';
        $this->db = Database::connect();

        $priorityId = $this->db->query('SELECT id FROM ticket_priorities ORDER BY sort_order, id LIMIT 1')->fetchColumn();
        $userId     = $this->db->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
        if ($priorityId === false || $userId === false) {
            $this->markTestSkipped('Needs at least one priority and one user.');
        }
        $this->priorityId = (int) $priorityId;
        $this->userId     = (int) $userId;

        // Read through raw SQL, not getSetting() — a getSetting() call here would
        // populate its static cache with the pre-test value and the writes below
        // would then be invisible for the rest of the process.
        $read = $this->db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        foreach (self::SETTING_KEYS as $key) {
            $read->execute([$key]);
            $value = $read->fetchColumn();
            $this->settingSnapshot[$key] = $value === false ? null : (string) $value;
        }
        $this->policySnapshot = $this->db->query(
            'SELECT type_id, priority_id, first_response_minutes, resolution_minutes, counted_days FROM sla_policies'
        )->fetchAll(PDO::FETCH_ASSOC);

        // A predictable always-open schedule keeps the arithmetic obvious: with
        // 24/7 hours, a 60-minute target is due exactly 60 minutes after the
        // baseline, whatever day the suite runs on.
        //
        // The business timezone is pinned to PHP's own default deliberately.
        // Sla stores due dates formatted in the business timezone but
        // computeSlaState() reparses them in the PHP default timezone, so any
        // difference between the two skews every SLA by the offset. That is a
        // separate pre-existing bug; matching them here keeps these tests
        // measuring the import baseline rather than that skew.
        \setSetting('sla_enabled', '1');
        \setSetting('business_hours_timezone', date_default_timezone_get());
        \setSetting('business_hours_schedule', json_encode([
            'mon' => ['00:00', '23:59'], 'tue' => ['00:00', '23:59'], 'wed' => ['00:00', '23:59'],
            'thu' => ['00:00', '23:59'], 'fri' => ['00:00', '23:59'], 'sat' => ['00:00', '23:59'],
            'sun' => ['00:00', '23:59'],
        ]));

        $this->db->exec('DELETE FROM sla_policies');
        $this->db->prepare(
            'INSERT INTO sla_policies (type_id, priority_id, first_response_minutes, resolution_minutes, counted_days) VALUES (NULL, ?, 60, 120, NULL)'
        )->execute([$this->priorityId]);

        // Every Sla method returns early when SLA is off, so without this the
        // whole class would pass while asserting nothing.
        $this->assertTrue(\slaEnabled(), ' — SLA must be on for these assertions to mean anything');
    }

    protected function tearDown(): void
    {
        if ($this->ticketIds !== []) {
            $in = implode(',', array_map('intval', $this->ticketIds));
            $this->db->exec("DELETE FROM ticket_timeline WHERE ticket_id IN ($in)");
            $this->db->exec("DELETE FROM tickets WHERE id IN ($in)");
        }

        $this->db->exec('DELETE FROM sla_policies');
        $insert = $this->db->prepare(
            'INSERT INTO sla_policies (type_id, priority_id, first_response_minutes, resolution_minutes, counted_days) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($this->policySnapshot as $row) {
            $insert->execute([
                $row['type_id'], $row['priority_id'],
                $row['first_response_minutes'], $row['resolution_minutes'], $row['counted_days'],
            ]);
        }
        // Restore through setSetting(), not raw SQL — it also rewinds the shared
        // settings memo, so later tests in this process don't keep reading the
        // values this class installed.
        $delete = $this->db->prepare('DELETE FROM settings WHERE setting_key = ?');
        foreach ($this->settingSnapshot as $key => $value) {
            $value === null ? $delete->execute([$key]) : \setSetting($key, $value);
        }
    }

    /** Create an imported-looking ticket opened $daysAgo days ago. */
    private function makeImportedTicket(int $daysAgo, int $exempt): int
    {
        $createdAt = (new DateTimeImmutable("-{$daysAgo} days", new DateTimeZone(date_default_timezone_get())))->format('Y-m-d H:i:s');
        $this->db->prepare(
            'INSERT INTO tickets (subject, description, legacy_id, created_by, created_at, status, priority_id, sla_exempt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute(['Imported backlog ticket', 'body', 'LEG-1', $this->userId, $createdAt, 'open', $this->priorityId, $exempt]);

        $id = (int) $this->db->lastInsertId();
        $this->ticketIds[] = $id;
        return $id;
    }

    private function ticket(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT created_at, sla_started_at, first_response_due_at, resolution_due_at, sla_state, sla_exempt FROM tickets WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ── The landmine ──────────────────────────────────────────────────────────

    /**
     * The reported risk: changing the priority on a years-old imported ticket
     * used to recompute its due dates from created_at, landing them in the past
     * and flipping the ticket to breached on the spot.
     */
    public function test_priority_change_never_breaches_an_exempt_imported_ticket(): void
    {
        $id = $this->makeImportedTicket(900, 1);

        Sla::onPriorityChanged($this->db, $id, $this->priorityId, null);

        $row = $this->ticket($id);
        $this->assertNull($row['first_response_due_at'], ' — an exempt ticket must not acquire a response clock');
        $this->assertNull($row['resolution_due_at'], ' — an exempt ticket must not acquire a resolution clock');
        $this->assertNull($row['sla_state'], ' — and must not be marked breached');
    }

    public function test_type_change_never_breaches_an_exempt_imported_ticket(): void
    {
        $id = $this->makeImportedTicket(900, 1);

        Sla::onTypeChanged($this->db, $id, null);

        $row = $this->ticket($id);
        $this->assertNull($row['first_response_due_at']);
        $this->assertNull($row['sla_state'], ' — a type change must not breach an exempt ticket either');
    }

    /**
     * The exemption must not leak into normal operation: a non-exempt ticket
     * still gets its clock recomputed as before.
     */
    public function test_priority_change_still_applies_sla_to_a_non_exempt_ticket(): void
    {
        $id = $this->makeImportedTicket(900, 0);

        Sla::onPriorityChanged($this->db, $id, $this->priorityId, null);

        $row = $this->ticket($id);
        $this->assertNotNull($row['first_response_due_at'], ' — a non-exempt ticket keeps the old behaviour');
        $this->assertSame('breached', $row['sla_state'], ' — measured from a 900-day-old created_at, it is genuinely breached');
    }

    // ── Import baselines ──────────────────────────────────────────────────────

    /**
     * "Start SLA clocks now" must measure from the import moment. Counting from
     * the legacy created_at instead would put both due dates ~900 days in the
     * past and breach the ticket immediately.
     */
    public function test_starting_clocks_now_gives_an_old_ticket_a_fresh_window(): void
    {
        $id  = $this->makeImportedTicket(900, 0);
        $ctx = Sla::makeImportContext($this->db);
        $now = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));

        $this->assertTrue(Sla::initializeForImportedTicket($this->db, $ctx, $id, $this->priorityId, null, $now));

        $row = $this->ticket($id);
        $this->assertSame('on_track', $row['sla_state'], ' — a fresh window must not start out breached');
        $this->assertNotNull($row['sla_started_at'], ' — the baseline must be recorded');
        $this->assertGreaterThan(
            $row['created_at'],
            $row['sla_started_at'],
            ' — the clock starts at import time, not at the legacy open date'
        );
        $this->assertGreaterThan(
            $now->format('Y-m-d H:i:s'),
            $row['first_response_due_at'],
            ' — the 60-minute response target must fall in the future'
        );
    }

    /**
     * "Apply from original open date" is the honest-reporting option and is
     * expected to breach an old backlog — the state must be written as breached
     * immediately rather than as 'on_track', so the next recalculateAll() sees
     * no transition and fires no notification or Teams post.
     */
    public function test_historical_baseline_lands_breached_without_a_pending_transition(): void
    {
        $id   = $this->makeImportedTicket(900, 0);
        $ctx  = Sla::makeImportContext($this->db);
        $open = new DateTimeImmutable($this->ticket($id)['created_at'], new DateTimeZone(date_default_timezone_get()));

        $this->assertTrue(Sla::initializeForImportedTicket($this->db, $ctx, $id, $this->priorityId, null, $open));

        $row = $this->ticket($id);
        $this->assertSame('breached', $row['sla_state'], ' — must be stored as breached at import, not left to a later flip');
        $this->assertSame(
            $row['created_at'],
            $row['sla_started_at'],
            ' — the historical option anchors the clock to the original open date'
        );

        // Nothing left to transition: a recalculation agrees with what was stored,
        // which is what keeps the notification/Teams storm from firing.
        $this->assertSame('breached', Sla::computeSlaState($row));
    }

    // ── Exemption semantics ───────────────────────────────────────────────────

    public function test_compute_sla_state_returns_null_for_an_exempt_ticket(): void
    {
        $this->assertNull(Sla::computeSlaState([
            'sla_exempt'            => 1,
            'created_at'            => '2022-01-01 00:00:00',
            'sla_started_at'        => '2022-01-01 00:00:00',
            'first_response_due_at' => '2022-01-01 01:00:00',
            'resolution_due_at'     => '2022-01-01 02:00:00',
        ]), ' — an exempt ticket has no SLA state at all');
    }

    public function test_baseline_prefers_sla_started_at_and_falls_back_to_created_at(): void
    {
        $this->assertSame('2026-05-01 09:00:00', Sla::baselineFor([
            'created_at'     => '2022-01-01 00:00:00',
            'sla_started_at' => '2026-05-01 09:00:00',
        ]));

        $this->assertSame('2022-01-01 00:00:00', Sla::baselineFor([
            'created_at'     => '2022-01-01 00:00:00',
            'sla_started_at' => null,
        ]), ' — tickets predating the column keep counting from created_at');
    }

    public function test_recalculate_all_skips_exempt_tickets(): void
    {
        // An exempt ticket carrying stale due dates must not be re-stated.
        $id = $this->makeImportedTicket(900, 1);
        $this->db->prepare(
            "UPDATE tickets SET first_response_due_at = '2022-01-01 01:00:00', resolution_due_at = '2022-01-01 02:00:00' WHERE id = ?"
        )->execute([$id]);

        Sla::recalculateAll($this->db);

        $this->assertNull($this->ticket($id)['sla_state'], ' — recalculateAll must filter exempt rows out');
    }
}

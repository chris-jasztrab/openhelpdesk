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
 * What happens to a ticket's SLA clock when a closed ticket is reopened.
 *
 * Before this was configurable, a reopen did nothing at all: the ticket went
 * straight back under the due dates it already had, so one closed six months ago
 * breached on the next cron run five minutes later. Closed tickets leave SLA
 * scope because recalculateAll() only scans the open bucket — not because
 * anything was paused — so no credit was ever applied on the way back in.
 *
 * That behaviour survives as 'keep'. The other three are new, and the direction
 * of each failure is what matters:
 *
 *  - 'keep' must NOT quietly start extending due dates, or every existing
 *    install's SLA history shifts on upgrade.
 *  - 'resume' must credit only *business* minutes, so a ticket closed over a
 *    weekend on a Mon–Fri schedule gets nothing back.
 *  - 'restart' must clear first_responded_at and 'restart_resolution' must not —
 *    that single column is the whole difference between the two.
 *
 * The suite rewrites SLA settings, policies and one ticket type, so all three are
 * snapshotted and restored around every test.
 */
class SlaReopenBehaviorTest extends TestCase
{
    private PDO $db;
    private array $policySnapshot = [];
    private array $settingSnapshot = [];
    private array $ticketIds = [];
    private int $priorityId;
    private int $userId;
    private ?int $typeId = null;
    private $typeReopenSnapshot = false;

    private const SETTING_KEYS = [
        'sla_enabled',
        'business_hours_timezone',
        'business_hours_schedule',
        'sla_reopen_behavior',
    ];

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

        $typeId = $this->db->query('SELECT id FROM ticket_types ORDER BY sort_order, id LIMIT 1')->fetchColumn();
        if ($typeId !== false) {
            $this->typeId = (int) $typeId;
            $stmt = $this->db->prepare('SELECT sla_reopen_behavior FROM ticket_types WHERE id = ?');
            $stmt->execute([$this->typeId]);
            $this->typeReopenSnapshot = $stmt->fetchColumn();
        }

        // Raw SQL, not getSetting() — a read here would populate the static memo
        // with the pre-test value and the writes below would be invisible.
        $read = $this->db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        foreach (self::SETTING_KEYS as $key) {
            $read->execute([$key]);
            $value = $read->fetchColumn();
            $this->settingSnapshot[$key] = $value === false ? null : (string) $value;
        }
        $this->policySnapshot = $this->db->query(
            'SELECT type_id, priority_id, first_response_minutes, resolution_minutes, counted_days FROM sla_policies'
        )->fetchAll(PDO::FETCH_ASSOC);

        // 24/7 hours so the arithmetic is obvious: a 60-minute target is due
        // exactly 60 minutes after the baseline whatever day the suite runs.
        // Business timezone pinned to PHP's own so expected dates are readable.
        \setSetting('sla_enabled', '1');
        \setSetting('business_hours_timezone', date_default_timezone_get());
        \setSetting('business_hours_schedule', json_encode([
            'mon' => ['00:00', '23:59'], 'tue' => ['00:00', '23:59'], 'wed' => ['00:00', '23:59'],
            'thu' => ['00:00', '23:59'], 'fri' => ['00:00', '23:59'], 'sat' => ['00:00', '23:59'],
            'sun' => ['00:00', '23:59'],
        ]));
        \setSetting('sla_reopen_behavior', 'keep');

        $this->db->exec('DELETE FROM sla_policies');
        $this->db->prepare(
            'INSERT INTO sla_policies (type_id, priority_id, first_response_minutes, resolution_minutes, counted_days) VALUES (NULL, ?, 60, 120, NULL)'
        )->execute([$this->priorityId]);

        $this->assertTrue(\slaEnabled(), ' — SLA must be on for these assertions to mean anything');
    }

    protected function tearDown(): void
    {
        if ($this->ticketIds !== []) {
            $in = implode(',', array_map('intval', $this->ticketIds));
            $this->db->exec("DELETE FROM ticket_timeline WHERE ticket_id IN ($in)");
            $this->db->exec("DELETE FROM tickets WHERE id IN ($in)");
        }

        if ($this->typeId !== null) {
            $this->db->prepare('UPDATE ticket_types SET sla_reopen_behavior = ? WHERE id = ?')
                ->execute([$this->typeReopenSnapshot === false ? null : $this->typeReopenSnapshot, $this->typeId]);
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
        // Restore via setSetting() so the shared settings memo rewinds too.
        $delete = $this->db->prepare('DELETE FROM settings WHERE setting_key = ?');
        foreach ($this->settingSnapshot as $key => $value) {
            $value === null ? $delete->execute([$key]) : \setSetting($key, $value);
        }
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
    }

    /**
     * A closed ticket that had a live clock, closed $closedHoursAgo hours ago,
     * with due dates already in the past — the shape that breaches instantly
     * under 'keep'.
     *
     * created_at is written from PHP's clock rather than SQL NOW() on purpose: the
     * local dev MySQL runs hours behind PHP, and these assertions compare against
     * PHP-computed instants. See SlaTimezoneSkewTest.
     */
    private function makeClosedTicket(
        int $openedDaysAgo,
        ?int $closedHoursAgo,
        ?string $firstRespondedAt = null,
        ?int $typeId = null
    ): int {
        $created  = $this->now()->modify("-{$openedDaysAgo} days");
        $closedAt = $closedHoursAgo === null ? null : $this->now()->modify("-{$closedHoursAgo} hours")->format('Y-m-d H:i:s');

        $this->db->prepare(
            'INSERT INTO tickets
                (subject, description, created_by, created_at, sla_started_at, status, priority_id, type_id,
                 first_response_due_at, resolution_due_at, first_responded_at, sla_state, sla_closed_at, sla_exempt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
        )->execute([
            'Reopen behaviour fixture', 'body', $this->userId,
            $created->format('Y-m-d H:i:s'),
            $created->format('Y-m-d H:i:s'),
            'closed', $this->priorityId, $typeId,
            $created->modify('+60 minutes')->format('Y-m-d H:i:s'),
            $created->modify('+120 minutes')->format('Y-m-d H:i:s'),
            $firstRespondedAt,
            'breached',
            $closedAt,
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->ticketIds[] = $id;
        return $id;
    }

    private function ticket(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT created_at, sla_started_at, first_response_due_at, resolution_due_at,
                    first_responded_at, sla_state, sla_paused_at, sla_closed_at, sla_exempt
             FROM tickets WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function minutesFromNow(?string $datetime): float
    {
        return (strtotime((string) $datetime) - $this->now()->getTimestamp()) / 60;
    }

    // ── keep: the pre-2.171 behaviour, which must not drift ───────────────────

    public function test_keep_leaves_the_due_dates_exactly_where_they_were(): void
    {
        \setSetting('sla_reopen_behavior', 'keep');
        $id     = $this->makeClosedTicket(180, 24);
        $before = $this->ticket($id);

        Sla::onReopened($this->db, $id);

        $after = $this->ticket($id);
        $this->assertSame(
            $before['first_response_due_at'],
            $after['first_response_due_at'],
            ' — keep must not move the response due date'
        );
        $this->assertSame(
            $before['resolution_due_at'],
            $after['resolution_due_at'],
            ' — keep must not move the resolution due date'
        );
        $this->assertSame($before['sla_started_at'], $after['sla_started_at']);
    }

    /**
     * Whatever the behaviour, sla_closed_at has to be cleared. Leaving it set
     * makes the NEXT close a no-op (markClosed only writes when it is NULL), which
     * would silently break the credit on the reopen after that.
     */
    public function test_every_behaviour_clears_the_closed_at_stamp(): void
    {
        foreach (Sla::REOPEN_BEHAVIORS as $behavior) {
            \setSetting('sla_reopen_behavior', $behavior);
            $id = $this->makeClosedTicket(180, 24);

            Sla::onReopened($this->db, $id);

            $this->assertNull(
                $this->ticket($id)['sla_closed_at'],
                " — {$behavior} must clear sla_closed_at"
            );
        }
    }

    // ── resume: credit the closed time back ───────────────────────────────────

    public function test_resume_pushes_both_due_dates_out_by_the_closed_duration(): void
    {
        \setSetting('sla_reopen_behavior', 'resume');
        $id     = $this->makeClosedTicket(180, 24);
        $before = $this->ticket($id);

        Sla::onReopened($this->db, $id);

        $after = $this->ticket($id);
        $creditedResponse = (strtotime($after['first_response_due_at']) - strtotime($before['first_response_due_at'])) / 60;
        $creditedResolve  = (strtotime($after['resolution_due_at']) - strtotime($before['resolution_due_at'])) / 60;

        // 24 hours closed on a 24/7 schedule = 1440 business minutes. Allow a
        // minute of slack for the clock advancing mid-test.
        $this->assertEqualsWithDelta(1440, $creditedResponse, 2, ' — response due date should gain the closed duration');
        $this->assertEqualsWithDelta(1440, $creditedResolve, 2, ' — resolution due date should gain the closed duration');
    }

    /**
     * The credit is in business minutes, not wall-clock — a ticket closed over
     * hours the desk isn't open gets none of that time back, or every weekend
     * silently hands out two free days.
     *
     * Pinned with a one-minute-per-day schedule on all seven days rather than a
     * Mon–Fri schedule and a weekend anchor: the latter's result depends on which
     * weekday the suite happens to run, and degenerates to "0 vs 0" on a Sunday.
     * Here 24 hours closed can credit at most a couple of minutes no matter when
     * it runs, against 1440 minutes of wall clock.
     */
    public function test_resume_credits_business_minutes_not_wall_clock(): void
    {
        $oneMinuteADay = ['09:00', '09:01'];
        \setSetting('business_hours_schedule', json_encode([
            'mon' => $oneMinuteADay, 'tue' => $oneMinuteADay, 'wed' => $oneMinuteADay,
            'thu' => $oneMinuteADay, 'fri' => $oneMinuteADay, 'sat' => $oneMinuteADay,
            'sun' => $oneMinuteADay,
        ]));
        \setSetting('sla_reopen_behavior', 'resume');

        $id     = $this->makeClosedTicket(180, 24);
        $before = $this->ticket($id);

        Sla::onReopened($this->db, $id);

        $after    = $this->ticket($id);
        $credited = (strtotime($after['resolution_due_at']) - strtotime($before['resolution_due_at'])) / 60;

        $this->assertLessThanOrEqual(
            2,
            $credited,
            ' — 24 hours closed on a one-minute-a-day schedule is at most ~1 business minute of credit'
        );
        $this->assertGreaterThanOrEqual(
            0,
            $credited,
            ' — and never negative'
        );
    }

    /**
     * A ticket closed before the sla_closed_at column existed has no recorded
     * closed-at. Guessing one (from updated_at, say) would push due dates out by
     * an arbitrary amount, so resume degrades to keep.
     */
    public function test_resume_without_a_closed_at_falls_back_to_keep(): void
    {
        \setSetting('sla_reopen_behavior', 'resume');
        $id     = $this->makeClosedTicket(180, null);
        $before = $this->ticket($id);
        $this->assertNull($before['sla_closed_at'], ' — fixture precondition');

        Sla::onReopened($this->db, $id);

        $after = $this->ticket($id);
        $this->assertSame(
            $before['resolution_due_at'],
            $after['resolution_due_at'],
            ' — with no closed-at recorded there is no credit to apply'
        );
    }

    /** A response already given needs no extension — same guard resume() uses. */
    public function test_resume_does_not_extend_a_response_leg_already_met(): void
    {
        \setSetting('sla_reopen_behavior', 'resume');
        $responded = $this->now()->modify('-179 days')->format('Y-m-d H:i:s');
        $id        = $this->makeClosedTicket(180, 24, $responded);
        $before    = $this->ticket($id);

        Sla::onReopened($this->db, $id);

        $after = $this->ticket($id);
        $this->assertSame(
            $before['first_response_due_at'],
            $after['first_response_due_at'],
            ' — the response target was already met, so there is nothing to extend'
        );
        $this->assertNotSame(
            $before['resolution_due_at'],
            $after['resolution_due_at'],
            ' — but the resolution leg still gets its credit'
        );
    }

    // ── restart: a fresh window from the reopen ───────────────────────────────

    public function test_restart_recomputes_both_due_dates_from_now(): void
    {
        \setSetting('sla_reopen_behavior', 'restart');
        $id = $this->makeClosedTicket(180, 24);

        Sla::onReopened($this->db, $id);

        $after = $this->ticket($id);
        $this->assertEqualsWithDelta(60, $this->minutesFromNow($after['first_response_due_at']), 2, ' — 60-minute response target measured from the reopen');
        $this->assertEqualsWithDelta(120, $this->minutesFromNow($after['resolution_due_at']), 2, ' — 120-minute resolution target measured from the reopen');
        $this->assertEqualsWithDelta(0, $this->minutesFromNow($after['sla_started_at']), 2, ' — the baseline moves to the reopen moment');
        $this->assertSame('on_track', $after['sla_state'], ' — a fresh window is on track, not still breached');
    }

    public function test_restart_clears_first_responded_at_so_a_new_response_is_required(): void
    {
        \setSetting('sla_reopen_behavior', 'restart');
        $responded = $this->now()->modify('-179 days')->format('Y-m-d H:i:s');
        $id        = $this->makeClosedTicket(180, 24, $responded);

        Sla::onReopened($this->db, $id);

        $this->assertNull(
            $this->ticket($id)['first_responded_at'],
            ' — restart means the reopened ticket needs a fresh first response'
        );
    }

    /** The cleared timestamp must survive somewhere, or it is data lost silently. */
    public function test_restart_records_the_previous_response_time_in_the_timeline(): void
    {
        \setSetting('sla_reopen_behavior', 'restart');
        $responded = $this->now()->modify('-179 days')->format('Y-m-d H:i:s');
        $id        = $this->makeClosedTicket(180, 24, $responded);

        Sla::onReopened($this->db, $id);

        $stmt = $this->db->prepare(
            "SELECT details FROM ticket_timeline WHERE ticket_id = ? AND action = 'sla_reopened' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$id]);
        $details = (string) $stmt->fetchColumn();

        $this->assertStringContainsString(
            $responded,
            $details,
            ' — the previous first-response time must be recoverable from the history'
        );
    }

    // ── restart_resolution: the response leg stays met ────────────────────────

    public function test_restart_resolution_keeps_first_responded_at(): void
    {
        \setSetting('sla_reopen_behavior', 'restart_resolution');
        $responded = $this->now()->modify('-179 days')->format('Y-m-d H:i:s');
        $id        = $this->makeClosedTicket(180, 24, $responded);

        Sla::onReopened($this->db, $id);

        $after = $this->ticket($id);
        $this->assertSame(
            $responded,
            $after['first_responded_at'],
            ' — the response already given still counts under restart_resolution'
        );
        $this->assertEqualsWithDelta(120, $this->minutesFromNow($after['resolution_due_at']), 2, ' — but the resolution window is fresh');
        $this->assertSame('on_track', $after['sla_state']);
    }

    /**
     * The single column that separates the two restart modes. Asserted head-to-head
     * so a future refactor can't collapse them into one behaviour unnoticed.
     */
    public function test_the_two_restart_modes_differ_only_on_the_response_leg(): void
    {
        $responded = $this->now()->modify('-179 days')->format('Y-m-d H:i:s');

        \setSetting('sla_reopen_behavior', 'restart');
        $full = $this->makeClosedTicket(180, 24, $responded);
        Sla::onReopened($this->db, $full);

        \setSetting('sla_reopen_behavior', 'restart_resolution');
        $partial = $this->makeClosedTicket(180, 24, $responded);
        Sla::onReopened($this->db, $partial);

        $a = $this->ticket($full);
        $b = $this->ticket($partial);

        $this->assertNull($a['first_responded_at']);
        $this->assertSame($responded, $b['first_responded_at']);
        $this->assertEqualsWithDelta(
            $this->minutesFromNow($a['resolution_due_at']),
            $this->minutesFromNow($b['resolution_due_at']),
            2,
            ' — both restart the resolution clock identically'
        );
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    /**
     * An exempt ticket has no clock to restart, and inventing one on reopen would
     * reintroduce exactly the retroactive breach sla_exempt exists to prevent —
     * the imported-backlog hazard from 2.168.0.
     */
    public function test_an_exempt_ticket_never_gains_a_clock_on_reopen(): void
    {
        \setSetting('sla_reopen_behavior', 'restart');
        $id = $this->makeClosedTicket(180, 24);
        $this->db->prepare('UPDATE tickets SET sla_exempt = 1, first_response_due_at = NULL, resolution_due_at = NULL, sla_state = NULL WHERE id = ?')
            ->execute([$id]);

        Sla::onReopened($this->db, $id);

        $after = $this->ticket($id);
        $this->assertNull($after['first_response_due_at'], ' — an exempt ticket must not acquire a response clock');
        $this->assertNull($after['resolution_due_at'], ' — nor a resolution clock');
        $this->assertNull($after['sla_state']);
        $this->assertNull($after['sla_closed_at'], ' — but the stamp is still cleared');
    }

    /**
     * A ticket that was never under SLA at all must not start being timed just
     * because someone reopened it, or every closed no-priority ticket acquires a
     * clock on its first reopen.
     */
    public function test_a_ticket_with_no_clock_does_not_start_one_on_reopen(): void
    {
        \setSetting('sla_reopen_behavior', 'restart');
        $id = $this->makeClosedTicket(180, 24);
        $this->db->prepare('UPDATE tickets SET first_response_due_at = NULL, resolution_due_at = NULL, sla_state = NULL WHERE id = ?')
            ->execute([$id]);

        Sla::onReopened($this->db, $id);

        $after = $this->ticket($id);
        $this->assertNull($after['first_response_due_at'], ' — no clock existed, so a reopen must not create one');
        $this->assertNull($after['resolution_due_at']);
    }

    // ── Resolution order: per-type override beats the global default ──────────

    public function test_a_type_override_beats_the_global_default(): void
    {
        if ($this->typeId === null) {
            $this->markTestSkipped('Needs at least one ticket type.');
        }

        \setSetting('sla_reopen_behavior', 'keep');
        $this->db->prepare('UPDATE ticket_types SET sla_reopen_behavior = ? WHERE id = ?')
            ->execute(['restart', $this->typeId]);

        $this->assertSame('restart', Sla::reopenBehaviorFor($this->db, $this->typeId));
        $this->assertSame('keep', Sla::reopenBehaviorFor($this->db, null), ' — the global default is untouched');
    }

    public function test_a_null_type_override_inherits_the_global_default(): void
    {
        if ($this->typeId === null) {
            $this->markTestSkipped('Needs at least one ticket type.');
        }

        \setSetting('sla_reopen_behavior', 'resume');
        $this->db->prepare('UPDATE ticket_types SET sla_reopen_behavior = NULL WHERE id = ?')
            ->execute([$this->typeId]);

        $this->assertSame('resume', Sla::reopenBehaviorFor($this->db, $this->typeId));
    }

    /**
     * A value the code doesn't recognise degrades to 'keep' rather than throwing:
     * 'keep' is the pre-2.171 behaviour, so a bad row leaves tickets exactly as
     * the previous version would have.
     */
    public function test_an_unrecognised_stored_value_degrades_to_keep(): void
    {
        if ($this->typeId === null) {
            $this->markTestSkipped('Needs at least one ticket type.');
        }

        $this->db->prepare('UPDATE ticket_types SET sla_reopen_behavior = ? WHERE id = ?')
            ->execute(['nonsense', $this->typeId]);
        \setSetting('sla_reopen_behavior', 'also_nonsense');

        $this->assertSame('keep', Sla::reopenBehaviorFor($this->db, $this->typeId));
        $this->assertSame('keep', Sla::reopenBehaviorFor($this->db, null));
    }

    /** The per-type behaviour must actually reach onReopened(), not just the resolver. */
    public function test_the_type_override_drives_the_actual_reopen(): void
    {
        if ($this->typeId === null) {
            $this->markTestSkipped('Needs at least one ticket type.');
        }

        \setSetting('sla_reopen_behavior', 'keep');
        $this->db->prepare('UPDATE ticket_types SET sla_reopen_behavior = ? WHERE id = ?')
            ->execute(['restart', $this->typeId]);

        $id = $this->makeClosedTicket(180, 24, null, $this->typeId);
        Sla::onReopened($this->db, $id);

        $after = $this->ticket($id);
        $this->assertEqualsWithDelta(
            120,
            $this->minutesFromNow($after['resolution_due_at']),
            2,
            ' — the type said restart even though the global default is keep'
        );
    }

    // ── onStatusChanged: the transition matrix ────────────────────────────────

    public function test_closing_stamps_the_closed_at_and_reopening_applies_the_behaviour(): void
    {
        \setSetting('sla_reopen_behavior', 'restart');
        $id = $this->makeClosedTicket(180, null);
        $this->db->prepare("UPDATE tickets SET status = 'open', sla_closed_at = NULL WHERE id = ?")->execute([$id]);

        Sla::onStatusChanged($this->db, $id, 'open', 'closed');
        $this->assertNotNull($this->ticket($id)['sla_closed_at'], ' — closing must record when it happened');

        Sla::onStatusChanged($this->db, $id, 'closed', 'open');
        $after = $this->ticket($id);
        $this->assertNull($after['sla_closed_at'], ' — and reopening must clear it');
        $this->assertEqualsWithDelta(120, $this->minutesFromNow($after['resolution_due_at']), 2, ' — restart applied');
    }

    /**
     * A closed → pending transition is both a reopen and a pause. The reopen has
     * to resolve the new due dates first, then the pause freezes them — the other
     * order would pause, then overwrite, losing the pause.
     */
    public function test_reopening_straight_into_a_pausing_status_reopens_then_pauses(): void
    {
        $pausing = \ticketSlaPausingSlugs();
        if ($pausing === []) {
            $this->markTestSkipped('Needs at least one SLA-pausing status.');
        }

        \setSetting('sla_reopen_behavior', 'restart');
        $id = $this->makeClosedTicket(180, 24);

        Sla::onStatusChanged($this->db, $id, 'closed', $pausing[0]);

        $after = $this->ticket($id);
        $this->assertEqualsWithDelta(120, $this->minutesFromNow($after['resolution_due_at']), 2, ' — the reopen recomputed the window');
        $this->assertNotNull($after['sla_paused_at'], ' — and the pausing status then paused it');
        $this->assertNull($after['sla_closed_at']);
    }

    /**
     * Leaving a pausing status must resume regardless of destination, including
     * straight to closed. Two of the API call sites previously resumed only when
     * the NEW status was an open non-pausing one, so pending → closed left
     * sla_paused_at set forever — and computeSlaState() then returns the frozen
     * state for good.
     */
    public function test_closing_from_a_paused_status_still_clears_the_pause(): void
    {
        $pausing = \ticketSlaPausingSlugs();
        if ($pausing === []) {
            $this->markTestSkipped('Needs at least one SLA-pausing status.');
        }

        $id = $this->makeClosedTicket(180, null);
        $this->db->prepare('UPDATE tickets SET status = ?, sla_paused_at = ?, sla_closed_at = NULL WHERE id = ?')
            ->execute([$pausing[0], $this->now()->modify('-2 hours')->format('Y-m-d H:i:s'), $id]);

        Sla::onStatusChanged($this->db, $id, $pausing[0], 'closed');

        $this->assertNull(
            $this->ticket($id)['sla_paused_at'],
            ' — a pause must never survive into the closed state'
        );
    }

    /** A no-op status change must not fire any of it. */
    public function test_an_unchanged_status_does_nothing(): void
    {
        \setSetting('sla_reopen_behavior', 'restart');
        $id     = $this->makeClosedTicket(180, 24);
        $before = $this->ticket($id);

        Sla::onStatusChanged($this->db, $id, 'closed', 'closed');

        $this->assertSame($before, $this->ticket($id), ' — nothing changed, so nothing should have been written');
    }

    /** Resolved and closed are both closed-bucket: moving between them is not a reopen. */
    public function test_moving_between_two_closed_statuses_is_not_a_reopen(): void
    {
        $closed = \ticketClosedBucketSlugs();
        if (count($closed) < 2) {
            $this->markTestSkipped('Needs at least two closed-bucket statuses.');
        }

        \setSetting('sla_reopen_behavior', 'restart');
        $id     = $this->makeClosedTicket(180, 24);
        $before = $this->ticket($id);

        Sla::onStatusChanged($this->db, $id, $closed[0], $closed[1]);

        $after = $this->ticket($id);
        $this->assertSame(
            $before['resolution_due_at'],
            $after['resolution_due_at'],
            ' — resolved → closed is not a reopen and must not restart the clock'
        );
        $this->assertSame($before['sla_closed_at'], $after['sla_closed_at'], ' — nor re-stamp the closed-at');
    }
}

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
 * SLA arithmetic when the business timezone differs from the server's.
 *
 * Sla used to store due dates formatted in the *business* timezone while every
 * reader — the ticket views, the ticket cards, the SLA reports' SQL `NOW()`
 * comparisons — treats them as server local time. Wherever the two differed,
 * every due date was off by the offset, which was enough to mark a brand-new
 * ticket breached the moment it was created.
 *
 * These tests deliberately pick a business timezone whose current UTC offset is
 * different from PHP's, so they fail against the old behaviour in either
 * direction. Assertions are on the stored string, not just the resulting state:
 * a positive skew hid itself by pushing due dates further into the future.
 */
class SlaTimezoneSkewTest extends TestCase
{
    private const SETTING_KEYS = ['sla_enabled', 'business_hours_timezone', 'business_hours_schedule'];

    private PDO $db;
    private array $policySnapshot = [];
    private array $settingSnapshot = [];
    private array $ticketIds = [];
    private int $priorityId;
    private int $userId;
    private string $offsetTz;

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
        $this->offsetTz   = $this->pickTimezoneOffsetFromServer();

        $read = $this->db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        foreach (self::SETTING_KEYS as $key) {
            $read->execute([$key]);
            $value = $read->fetchColumn();
            $this->settingSnapshot[$key] = $value === false ? null : (string) $value;
        }
        $this->policySnapshot = $this->db->query(
            'SELECT type_id, priority_id, first_response_minutes, resolution_minutes, counted_days FROM sla_policies'
        )->fetchAll(PDO::FETCH_ASSOC);

        // Always-open hours: a 60-minute target is due exactly 60 minutes out,
        // so any discrepancy in the stored value is timezone skew and nothing else.
        \setSetting('sla_enabled', '1');
        \setSetting('business_hours_timezone', $this->offsetTz);
        \setSetting('business_hours_schedule', json_encode([
            'mon' => ['00:00', '23:59'], 'tue' => ['00:00', '23:59'], 'wed' => ['00:00', '23:59'],
            'thu' => ['00:00', '23:59'], 'fri' => ['00:00', '23:59'], 'sat' => ['00:00', '23:59'],
            'sun' => ['00:00', '23:59'],
        ]));

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
        $delete = $this->db->prepare('DELETE FROM settings WHERE setting_key = ?');
        foreach ($this->settingSnapshot as $key => $value) {
            $value === null ? $delete->execute([$key]) : \setSetting($key, $value);
        }
    }

    /** A timezone whose current offset genuinely differs from the server's. */
    private function pickTimezoneOffsetFromServer(): string
    {
        $now       = new DateTimeImmutable('now');
        $serverOff = $now->getOffset();
        foreach (['UTC', 'Asia/Tokyo', 'Pacific/Niue', 'America/Toronto', 'Australia/Eucla'] as $candidate) {
            if ($now->setTimezone(new DateTimeZone($candidate))->getOffset() !== $serverOff) {
                return $candidate;
            }
        }
        $this->markTestSkipped('No candidate timezone differs from the server offset.');
    }

    /**
     * created_at is written from PHP's clock rather than SQL NOW() on purpose.
     * The app assumes MySQL's session timezone matches PHP's — prod pins both to
     * America/Toronto — but a dev box where they drift would otherwise fail these
     * tests for a reason that has nothing to do with the business timezone.
     */
    private function makeTicket(): int
    {
        $this->db->prepare(
            'INSERT INTO tickets (subject, description, created_by, created_at, status, priority_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            'Timezone skew probe', 'body', $this->userId,
            (new DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            'open', $this->priorityId,
        ]);

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

    /**
     * The headline bug: a ticket created right now, under a 60-minute policy,
     * must not be born breached just because Business Hours names a different
     * timezone than the server runs in.
     */
    public function test_a_new_ticket_is_not_born_breached_under_a_foreign_business_timezone(): void
    {
        $id = $this->makeTicket();

        Sla::initializeForTicket($this->db, $id, $this->priorityId, null);

        $row = $this->ticket($id);
        $this->assertNotNull($row['first_response_due_at'], ' — the policy should have produced a clock');
        $this->assertSame('on_track', $row['sla_state'], ' — a brand-new ticket must never start out breached');
    }

    /**
     * Assert on the stored value itself. A business timezone *ahead* of the
     * server pushed due dates further out, which looks healthy but silently
     * granted extra hours — so state alone is not enough to pin this.
     */
    public function test_stored_due_dates_are_in_server_time_not_business_time(): void
    {
        $id  = $this->makeTicket();
        $now = new DateTimeImmutable('now');

        Sla::initializeForTicket($this->db, $id, $this->priorityId, null);

        $row = $this->ticket($id);
        $expectedResponse = $now->getTimestamp() + 3600;   // 60-minute target
        $expectedResolve  = $now->getTimestamp() + 7200;   // 120-minute target

        $this->assertEqualsWithDelta(
            $expectedResponse,
            strtotime($row['first_response_due_at']),
            120,
            ' — first_response_due_at must read as 60 minutes out in server time'
        );
        $this->assertEqualsWithDelta(
            $expectedResolve,
            strtotime($row['resolution_due_at']),
            120,
            ' — resolution_due_at must read as 120 minutes out in server time'
        );
    }

    /** The SLA baseline is stored in the same frame as everything else. */
    public function test_sla_started_at_is_stored_in_server_time(): void
    {
        $id  = $this->makeTicket();
        $now = new DateTimeImmutable('now');

        Sla::initializeForTicket($this->db, $id, $this->priorityId, null);

        $this->assertEqualsWithDelta(
            $now->getTimestamp(),
            strtotime($this->ticket($id)['sla_started_at']),
            120,
            ' — sla_started_at must be "now" in server time, not shifted by the business offset'
        );
    }

    /**
     * The recompute path reads created_at (a MySQL timestamp, i.e. server time)
     * as its baseline. Parsing that in the business timezone shifted the whole
     * window; a ticket created moments ago must still be on track afterwards.
     */
    public function test_priority_change_recomputes_without_skewing_the_baseline(): void
    {
        $id  = $this->makeTicket();
        $now = new DateTimeImmutable('now');

        Sla::onPriorityChanged($this->db, $id, $this->priorityId, null);

        $row = $this->ticket($id);
        $this->assertSame('on_track', $row['sla_state'], ' — a fresh ticket must stay on track after a priority change');
        $this->assertEqualsWithDelta(
            $now->getTimestamp() + 3600,
            strtotime($row['first_response_due_at']),
            120,
            ' — the recomputed due date must also land in server time'
        );
    }

    /** An import anchored to "now" must land in the same frame too. */
    public function test_imported_ticket_clock_is_stored_in_server_time(): void
    {
        $id  = $this->makeTicket();
        $ctx = Sla::makeImportContext($this->db);
        $now = new DateTimeImmutable('now');

        $this->assertTrue(Sla::initializeForImportedTicket($this->db, $ctx, $id, $this->priorityId, null, $now));

        $row = $this->ticket($id);
        $this->assertSame('on_track', $row['sla_state']);
        $this->assertEqualsWithDelta(
            $now->getTimestamp() + 3600,
            strtotime($row['first_response_due_at']),
            120,
            ' — an import-set clock must be stored in server time as well'
        );
    }
}

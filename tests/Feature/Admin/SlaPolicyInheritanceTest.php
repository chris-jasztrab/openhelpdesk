<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Database;
use PDO;
use Sla;
use Tests\Support\TestCase;

/**
 * SLA policy saving and per-field inheritance.
 *
 * A type-specific policy overrides the default policy field by field: a blank
 * (0) duration on a type row means "follow the default", and a type may restrict
 * the days its SLA timer counts without setting any durations at all. These
 * tests pin both the save handler (which used to discard a days-only change) and
 * Sla::findPolicy()'s resolution of the two rows.
 *
 * Every test rewrites sla_policies, so the table is snapshotted and restored
 * around each one.
 */
class SlaPolicyInheritanceTest extends TestCase
{
    private const PATH = '/admin/settings/sla-policies';

    private PDO $db;
    private array $snapshot = [];
    private int $typeId;
    private int $priorityId;

    protected function setUp(): void
    {
        require_once ROOT_DIR . '/src/Sla.php';

        $this->db = Database::connect();
        $this->snapshot = $this->db->query(
            'SELECT type_id, priority_id, first_response_minutes, resolution_minutes, counted_days FROM sla_policies'
        )->fetchAll(PDO::FETCH_ASSOC);

        $typeId = $this->db->query('SELECT id FROM ticket_types ORDER BY id LIMIT 1')->fetchColumn();
        $priorityId = $this->db->query('SELECT id FROM ticket_priorities ORDER BY sort_order LIMIT 1')->fetchColumn();
        if ($typeId === false || $priorityId === false) {
            $this->markTestSkipped('Needs at least one ticket type and one priority.');
        }
        $this->typeId = (int) $typeId;
        $this->priorityId = (int) $priorityId;
    }

    protected function tearDown(): void
    {
        $this->db->exec('DELETE FROM sla_policies');
        $insert = $this->db->prepare(
            'INSERT INTO sla_policies (type_id, priority_id, first_response_minutes, resolution_minutes, counted_days) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($this->snapshot as $row) {
            $insert->execute([
                $row['type_id'],
                $row['priority_id'],
                $row['first_response_minutes'],
                $row['resolution_minutes'],
                $row['counted_days'],
            ]);
        }
    }

    // ── Save handler ──────────────────────────────────────────────────────────

    /**
     * The reported bug: on a type tab, changing only "SLA counts on" (leaving
     * both duration boxes blank, so the type inherits the default) was dropped
     * by the save handler even though the page reported success.
     */
    public function test_changing_only_the_counted_days_on_a_type_saves(): void
    {
        $weekdays = ['mon' => '1', 'tue' => '1', 'wed' => '1', 'thu' => '1', 'fri' => '1'];

        $r = $this->post($this->adminClient(), self::PATH, [
            'policies' => [
                // Default policy: real durations, all seven days.
                '0' => [
                    (string) $this->priorityId => [
                        'first_response_minutes' => '1h',
                        'resolution_minutes'     => '8h',
                        'days' => $weekdays + ['sat' => '1', 'sun' => '1'],
                    ],
                ],
                // Type policy: no durations at all, weekends deselected.
                (string) $this->typeId => [
                    (string) $this->priorityId => [
                        'first_response_minutes' => '',
                        'resolution_minutes'     => '',
                        'days' => $weekdays,
                    ],
                ],
            ],
        ]);
        $this->assertOk($r);

        $row = $this->typeRow();
        $this->assertNotNull($row, ' — the days-only override must be stored, not skipped');
        $this->assertSame('mon,tue,wed,thu,fri', $row['counted_days']);
        $this->assertSame(0, (int) $row['first_response_minutes'], ' — a blank box stays 0 (inherit), not a copy of the default');
        $this->assertSame(0, (int) $row['resolution_minutes'], ' — a blank box stays 0 (inherit), not a copy of the default');
    }

    /**
     * A type row that says nothing the default doesn't already say is not
     * stored, so the type keeps following the default as the default changes.
     */
    public function test_a_type_row_matching_the_default_is_not_stored(): void
    {
        $allDays = ['mon' => '1', 'tue' => '1', 'wed' => '1', 'thu' => '1', 'fri' => '1', 'sat' => '1', 'sun' => '1'];

        $r = $this->post($this->adminClient(), self::PATH, [
            'policies' => [
                '0' => [
                    (string) $this->priorityId => [
                        'first_response_minutes' => '1h',
                        'resolution_minutes'     => '8h',
                        'days' => $allDays,
                    ],
                ],
                (string) $this->typeId => [
                    (string) $this->priorityId => [
                        'first_response_minutes' => '',
                        'resolution_minutes'     => '',
                        'days' => $allDays,
                    ],
                ],
            ],
        ]);
        $this->assertOk($r);

        $this->assertNull($this->typeRow(), ' — an all-blank type row is "no override" and must not be stored');
    }

    /** Setting only one of the two durations still saves that one. */
    public function test_a_single_duration_saves_without_the_other(): void
    {
        $r = $this->post($this->adminClient(), self::PATH, [
            'policies' => [
                (string) $this->typeId => [
                    (string) $this->priorityId => [
                        'first_response_minutes' => '',
                        'resolution_minutes'     => '6h',
                    ],
                ],
            ],
        ]);
        $this->assertOk($r);

        $row = $this->typeRow();
        $this->assertNotNull($row);
        $this->assertSame(360, (int) $row['resolution_minutes']);
        $this->assertSame(0, (int) $row['first_response_minutes'], ' — the blank leg stays 0 so it can inherit');
    }

    // ── findPolicy() resolution ───────────────────────────────────────────────

    public function test_a_days_only_type_policy_inherits_both_default_durations(): void
    {
        $this->seed(null, 60, 480, null);
        $this->seed($this->typeId, 0, 0, 'mon,tue,wed,thu,fri');

        $policy = Sla::findPolicy($this->db, $this->typeId, $this->priorityId);

        $this->assertNotNull($policy);
        $this->assertSame(60, (int) $policy['first_response_minutes']);
        $this->assertSame(480, (int) $policy['resolution_minutes']);
        $this->assertSame(['mon', 'tue', 'wed', 'thu', 'fri'], Sla::parseCountedDays($policy['counted_days']));
    }

    public function test_a_partial_type_policy_inherits_only_the_blank_leg(): void
    {
        $this->seed(null, 60, 480, null);
        $this->seed($this->typeId, 0, 360, null);

        $policy = Sla::findPolicy($this->db, $this->typeId, $this->priorityId);

        $this->assertNotNull($policy);
        $this->assertSame(60, (int) $policy['first_response_minutes'], ' — blank leg follows the default');
        $this->assertSame(360, (int) $policy['resolution_minutes'], ' — set leg wins over the default');
    }

    /** Editing the default must move a type that inherits from it. */
    public function test_the_default_durations_stay_live_for_an_inheriting_type(): void
    {
        $this->seed(null, 60, 480, null);
        $this->seed($this->typeId, 0, 0, 'mon,tue,wed,thu,fri');

        $this->db->prepare('UPDATE sla_policies SET resolution_minutes = 180 WHERE type_id IS NULL AND priority_id = ?')
            ->execute([$this->priorityId]);

        $policy = Sla::findPolicy($this->db, $this->typeId, $this->priorityId);
        $this->assertSame(180, (int) $policy['resolution_minutes'], ' — the type must not hold a frozen copy');
    }

    /** No durations anywhere means no SLA, not a deadline of "right now". */
    public function test_a_policy_with_no_durations_has_no_sla(): void
    {
        $this->seed(null, 0, 0, 'mon,tue,wed,thu,fri');

        $this->assertNull(Sla::findPolicy($this->db, null, $this->priorityId));
        $this->assertNull(Sla::findPolicy($this->db, $this->typeId, $this->priorityId));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function seed(?int $typeId, int $firstResponse, int $resolution, ?string $countedDays): void
    {
        $this->db->prepare(
            'DELETE FROM sla_policies WHERE priority_id = ? AND ' . ($typeId === null ? 'type_id IS NULL' : 'type_id = ' . $typeId)
        )->execute([$this->priorityId]);
        $this->db->prepare(
            'INSERT INTO sla_policies (type_id, priority_id, first_response_minutes, resolution_minutes, counted_days) VALUES (?, ?, ?, ?, ?)'
        )->execute([$typeId, $this->priorityId, $firstResponse, $resolution, $countedDays]);
    }

    private function typeRow(): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT first_response_minutes, resolution_minutes, counted_days FROM sla_policies WHERE type_id = ? AND priority_id = ?'
        );
        $stmt->execute([$this->typeId, $this->priorityId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Task tickets (v2.202.0): a New Employee ticket spawns one task ticket per
 * configured type, each in its own group and carrying only its own fields; the
 * parent resolves once every task is closed; only the owning group can create
 * a restricted type.
 *
 * Every test runs inside a transaction that is rolled back. The task types are
 * confidential so AI classification never runs, and mail is suppressed by the
 * test instance's MAIL_ENABLED=false.
 */
class TicketTypeTasksTest extends TestCase
{
    private PDO $db;
    private int $requester;

    protected function setUp(): void
    {
        require_once ROOT_DIR . '/src/Sla.php';
        $this->db  = Database::connect();
        $requester = $this->db->query("SELECT id FROM users WHERE role = 'agent' LIMIT 1")->fetchColumn();
        if ($requester === false) {
            $this->markTestSkipped('Needs an agent.');
        }
        $this->requester = (int) $requester;
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function group(string $name): int
    {
        $this->db->prepare("INSERT INTO `groups` (name, assign_strategy) VALUES (?, 'manual')")->execute([$name]);
        return (int) $this->db->lastInsertId();
    }

    private function type(string $name, ?int $groupId, int $confidential = 1, int $restrict = 0): int
    {
        $this->db->prepare(
            'INSERT INTO ticket_types (name, group_id, is_confidential, restrict_create_to_group) VALUES (?, ?, ?, ?)'
        )->execute([$name, $groupId, $confidential, $restrict]);
        return (int) $this->db->lastInsertId();
    }

    private function field(string $label): int
    {
        $this->db->prepare("INSERT INTO ticket_form_fields (field_type, label) VALUES ('text', ?)")->execute([$label]);
        return (int) $this->db->lastInsertId();
    }

    private function fieldValues(int $ticketId): array
    {
        $s = $this->db->prepare('SELECT field_id, value FROM ticket_field_values WHERE ticket_id = ? ORDER BY field_id');
        $s->execute([$ticketId]);
        return array_map('strval', $s->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /** A New Employee parent with IT and Payroll tasks; returns [parentId, [itGroup, payGroup], [nameField, wageField]]. */
    private function newHire(): array
    {
        $hr  = $this->group('TTT HR');
        $it  = $this->group('TTT IT');
        $pay = $this->group('TTT Payroll');
        $parentType = $this->type('TTT New Employee', $hr, 1, 1);
        $itType     = $this->type('TTT Onboarding - IT', $it);
        $payType    = $this->type('TTT Onboarding - Payroll', $pay);
        $name = $this->field('TTT Name');
        $wage = $this->field('TTT Starting Wage');
        $this->db->prepare("INSERT INTO ticket_form_fields (field_type, label) VALUES ('date', 'TTT Start Date')")->execute();
        $start = (int) $this->db->lastInsertId();
        $this->db->prepare('INSERT INTO ticket_type_form_layout (type_id, field_kind, field_key, sort_order, visibility) VALUES (?, ?, ?, 10, ?)')
            ->execute([$parentType, 'custom', (string) $start, 'required']);
        $this->db->prepare('UPDATE ticket_types SET due_date_field_id = ? WHERE id = ?')->execute([$start, $parentType]);
        $this->db->prepare('INSERT INTO ticket_type_tasks (type_id, child_type_id, field_ids) VALUES (?, ?, ?), (?, ?, ?)')
            ->execute([$parentType, $itType, json_encode([$name]), $parentType, $payType, json_encode([$name, $wage])]);

        $this->db->exec("INSERT INTO locations (name) VALUES ('TTT Branch')");
        $this->db->prepare(
            "INSERT INTO tickets (subject, description, created_by, type_id, status, group_id, location_id)
             VALUES ('New hire: Test Person', 'x', ?, ?, 'open', ?, ?)"
        )->execute([$this->requester, $parentType, $hr, (int) $this->db->lastInsertId()]);
        $parentId = (int) $this->db->lastInsertId();
        $this->db->prepare('INSERT INTO ticket_field_values (ticket_id, field_id, value) VALUES (?, ?, ?), (?, ?, ?), (?, ?, ?)')
            ->execute([$parentId, $name, 'Test Person', $parentId, $wage, '29.36', $parentId, $start, '2026-11-02']);
        syncDueDateFromTypeField($this->db, $parentId);

        return [$parentId, [$it, $pay], [$name, $wage]];
    }

    private function setStatus(int $ticketId, string $status): void
    {
        $this->db->prepare('UPDATE tickets SET status = ? WHERE id = ?')->execute([$status, $ticketId]);
    }

    private function statusOf(int $ticketId): string
    {
        $s = $this->db->prepare('SELECT status FROM tickets WHERE id = ?');
        $s->execute([$ticketId]);
        return (string) $s->fetchColumn();
    }

    public function test_each_task_lands_in_its_own_group_with_only_its_own_fields(): void
    {
        [$parentId, [$itGroup, $payGroup], [$name, $wage]] = $this->newHire();

        $taskIds = spawnTicketTypeTasks($this->db, $parentId);

        $this->assertCount(2, $taskIds);
        $s = $this->db->prepare('SELECT group_id, created_by, parent_ticket_id, subject, description, location_id, due_date FROM tickets WHERE id = ?');
        $s->execute([$taskIds[0]]);
        $it = $s->fetch();
        $s->execute([$taskIds[1]]);
        $pay = $s->fetch();

        $this->assertSame([$itGroup, $payGroup], [(int) $it['group_id'], (int) $pay['group_id']]);
        $this->assertSame($this->requester, (int) $it['created_by'], 'replies on a task reach whoever filed the parent');
        $this->assertSame($parentId, (int) $pay['parent_ticket_id']);
        $this->assertSame('TTT Onboarding - IT: New hire: Test Person', $it['subject']);
        $this->assertNotNull($pay['location_id'], 'every task keeps the location');
        $this->assertSame('2026-11-02', $this->db->query("SELECT due_date FROM tickets WHERE id = $parentId")->fetchColumn(), 'due date comes from the Start Date field');
        $this->assertSame(['2026-11-02', '2026-11-02'], [$it['due_date'], $pay['due_date']], 'tasks inherit the due date');
        $startField = (int) $this->db->query("SELECT due_date_field_id FROM ticket_types t JOIN tickets k ON k.type_id = t.id WHERE k.id = $parentId")->fetchColumn();
        $this->db->prepare('UPDATE ticket_field_values SET value = ? WHERE ticket_id = ? AND field_id = ?')->execute(['2026-11-09', $parentId, $startField]);
        syncDueDateFromTypeField($this->db, $parentId);
        $this->assertSame(['2026-11-09', '2026-11-09'], $this->db->query("SELECT due_date FROM tickets WHERE id IN ($taskIds[0], $taskIds[1])")->fetchAll(PDO::FETCH_COLUMN), 'a changed Start Date moves every task');
        $this->assertStringContainsString('<td style="padding:1px 12px 1px 0;color:#64748b;white-space:nowrap;vertical-align:top;">Location</td><td style="padding:1px 0;">TTT Branch</td>', $pay['description']);
        $this->assertStringContainsString('>TTT Starting Wage</td><td style="padding:1px 0;">29.36</td>', $pay['description'], 'the handed-over details are in the description so they reach the emails');
        $this->assertStringNotContainsString('Starting Wage', $it['description'], 'IT never gets the wage');
        $this->assertSame([
            'Location' => 'TTT Branch', 'TTT Name' => 'Test Person', 'TTT Starting Wage' => '29.36',
        ], ['Location' => 'TTT Branch'] + ticketFieldValuesForDisplay($this->db, $parentId, (int) $this->db->query("SELECT type_id FROM tickets WHERE id = $parentId")->fetchColumn(), [$name, $wage]));
        $this->assertSame([$name => 'Test Person'], $this->fieldValues($taskIds[0]), 'IT never gets the wage');
        $this->assertSame([$name => 'Test Person', $wage => '29.36'], $this->fieldValues($taskIds[1]));

        $links = ticketTaskLinks($this->db, ['id' => $parentId, 'parent_ticket_id' => null]);
        $this->assertSame($taskIds, array_map('intval', array_column($links['tasks'], 'id')));
    }

    public function test_parent_resolves_once_every_task_is_closed_and_only_once(): void
    {
        [$parentId] = $this->newHire();
        [$itTask, $payTask] = spawnTicketTypeTasks($this->db, $parentId);
        $closed = ticketClosedBucketSlugs()[0];

        resolveParentsWithClosedTasks($this->db);
        $this->assertSame('open', $this->statusOf($parentId), 'no task closed yet');

        $this->setStatus($itTask, $closed);
        resolveParentsWithClosedTasks($this->db);
        $this->assertSame('open', $this->statusOf($parentId), 'payroll still open');

        $this->setStatus($payTask, $closed);
        resolveParentsWithClosedTasks($this->db);
        $this->assertSame(ticketDefaultResolvedStatusSlug(), $this->statusOf($parentId));

        // HR reopens it on purpose — the sweep must not resolve it again.
        $this->setStatus($parentId, 'open');
        resolveParentsWithClosedTasks($this->db);
        $this->assertSame('open', $this->statusOf($parentId));
    }

    public function test_link_person_assets_links_the_picked_persons_devices_to_that_task_only(): void
    {
        require_once ROOT_DIR . '/src/Assets.php';
        [$parentId] = $this->newHire();
        $leaver = (int) $this->db->query("SELECT id FROM users WHERE is_external = 0 AND is_shared_account = 0 AND id <> {$this->requester} LIMIT 1")->fetchColumn();
        $this->db->exec("INSERT INTO ticket_form_fields (field_type, label) VALUES ('user_picker', 'TTT Leaver')");
        $person = (int) $this->db->lastInsertId();
        $this->db->prepare('INSERT INTO ticket_field_values (ticket_id, field_id, value) VALUES (?, ?, ?)')->execute([$parentId, $person, (string) $leaver]);
        $typeId = (int) $this->db->query("SELECT type_id FROM tickets WHERE id = $parentId")->fetchColumn();
        [$itRow, $payRow] = $this->db->query("SELECT id FROM ticket_type_tasks WHERE type_id = $typeId ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $this->db->prepare('UPDATE ticket_type_tasks SET field_ids = ?, link_person_assets = 1 WHERE id = ?')->execute([json_encode([$person]), $itRow]);
        $this->db->prepare('UPDATE ticket_type_tasks SET field_ids = ? WHERE id = ?')->execute([json_encode([$person]), $payRow]);

        $this->db->exec("INSERT INTO asset_categories (name) VALUES ('TTT Laptops')");
        $this->db->prepare("INSERT INTO asset_models (name, category_id) VALUES ('TTT ThinkPad', ?)")->execute([(int) $this->db->lastInsertId()]);
        $model  = (int) $this->db->lastInsertId();
        $status = (int) $this->db->query('SELECT id FROM asset_statuses ORDER BY id LIMIT 1')->fetchColumn();
        $this->db->prepare("INSERT INTO assets (asset_tag, model_id, status_id, assigned_type, assigned_id) VALUES ('TTT-LAP1', ?, ?, 'user', ?)")
            ->execute([$model, $status, $leaver]);
        $asset = (int) $this->db->lastInsertId();

        [$itTask, $payTask] = spawnTicketTypeTasks($this->db, $parentId);

        $n = fn(string $sql) => (int) $this->db->query($sql)->fetchColumn();
        $this->assertSame(1, $n("SELECT COUNT(*) FROM ticket_asset_map WHERE ticket_id = $itTask AND asset_id = $asset"), 'IT task gets the leaver\'s device');
        $this->assertSame(0, $n("SELECT COUNT(*) FROM ticket_asset_map WHERE ticket_id = $payTask"), 'a task without the switch links nothing');

        // Nobody's devices: an internal note says there is nothing to collect.
        $this->db->prepare("UPDATE assets SET assigned_type = NULL, assigned_id = NULL WHERE id = ?")->execute([$asset]);
        $this->db->prepare("DELETE FROM ticket_asset_map WHERE ticket_id = ?")->execute([$itTask]);
        $none = $n("SELECT u.id FROM users u WHERE NOT EXISTS (SELECT 1 FROM assets a WHERE a.assigned_type = 'user' AND a.assigned_id = u.id AND a.deleted_at IS NULL) LIMIT 1");
        $this->assertSame(0, \Assets::linkUserDevices($this->db, $itTask, $none));
        $this->assertSame(1, $n("SELECT COUNT(*) FROM ticket_timeline WHERE ticket_id = $itTask AND is_internal = 1 AND details LIKE 'No devices are checked out to %'"));
    }

    public function test_a_restricted_type_is_creatable_only_by_its_groups_members(): void
    {
        $member = $this->db->query('SELECT user_id, group_id FROM group_user_map LIMIT 1')->fetch();
        if ($member === false) {
            $this->markTestSkipped('Needs a group with a member.');
        }
        $userId     = (int) $member['user_id'];
        $theirs     = $this->type('TTT Theirs', (int) $member['group_id'], 0, 1);
        $notTheirs  = $this->type('TTT Not Theirs', $this->group('TTT Empty'), 0, 1);
        $open       = $this->type('TTT Open', null, 0, 0);

        $this->assertTrue(userCanCreateTicketType($this->db, $theirs, $userId));
        $this->assertFalse(userCanCreateTicketType($this->db, $notTheirs, $userId));
        $this->assertTrue(userCanCreateTicketType($this->db, $open, $userId));
        $this->assertTrue(userCanCreateTicketType($this->db, null, $userId));

        $rows = $this->db->query("SELECT * FROM ticket_types WHERE name LIKE 'TTT %'")->fetchAll();
        $names = array_column(creatableTicketTypes($this->db, $rows, $userId), 'name');
        $this->assertContains('TTT Theirs', $names);
        $this->assertNotContains('TTT Not Theirs', $names);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database;
use PDO;
use PHPUnit\Framework\TestCase;
use Sla;

/**
 * Ticket Reminders (v2.188.0): who gets the overdue / update-due emails, that
 * each fires once per due date or quiet period, and that the due date follows
 * the SLA only while nobody has set it by hand.
 *
 * Every test runs inside a transaction that is rolled back, so the tickets,
 * timeline rows and settings it writes never persist. Mail is suppressed by
 * the test instance's MAIL_ENABLED=false.
 */
class TicketRemindersTest extends TestCase
{
    private PDO $db;
    private int $agent;
    private int $requester;
    private int $group;

    protected function setUp(): void
    {
        require_once ROOT_DIR . '/src/Sla.php';
        $this->db = Database::connect();
        $agent     = $this->db->query("SELECT id FROM users WHERE role = 'agent' AND notify_assigned_to_me = 1 LIMIT 1")->fetchColumn();
        $requester = $this->db->query("SELECT id FROM users WHERE role = 'user' LIMIT 1")->fetchColumn();
        $group     = $this->db->query('SELECT id FROM `groups` LIMIT 1')->fetchColumn();
        if ($agent === false || $requester === false || $group === false) {
            $this->markTestSkipped('Needs an agent, a portal user and a group.');
        }
        [$this->agent, $this->requester, $this->group] = [(int) $agent, (int) $requester, (int) $group];

        $this->db->beginTransaction();
        setSetting('email_notify:ticket_overdue', '1');
        setSetting('email_notify:ticket_update_due', '1');
        setSetting('ticket_update_due_days', '15');
        setSetting('ticket_reminders_managers', '0');
        setSetting('ticket_reminders_scope', 'all');
        setSetting('due_date_follows_sla', '1');
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function ticket(string $status, ?int $assignee, ?string $due, int $ageDays): int
    {
        $this->db->prepare(
            "INSERT INTO tickets (subject, description, created_by, status, assigned_to, group_id, due_date, created_at)
             VALUES ('reminder test', 'x', ?, ?, ?, ?, ?, NOW() - INTERVAL ? DAY)"
        )->execute([$this->requester, $status, $assignee, $this->group, $due, $ageDays]);
        return (int) $this->db->lastInsertId();
    }

    private function reminders(int $ticketId, string $action): int
    {
        $s = $this->db->prepare('SELECT COUNT(*) FROM ticket_timeline WHERE ticket_id = ? AND action = ?');
        $s->execute([$ticketId, $action]);
        return (int) $s->fetchColumn();
    }

    public function test_owner_is_reminded_once_and_the_rest_are_left_alone(): void
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $late      = $this->ticket('open', $this->agent, $yesterday, 20);
        $noOwner   = $this->ticket('open', null, $yesterday, 20);
        $waiting   = $this->ticket('waiting_on_customer', $this->agent, $yesterday, 20);
        $recent    = $this->ticket('open', $this->agent, date('Y-m-d', strtotime('+5 days')), 20);
        $this->db->prepare(
            "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal, created_at)
             VALUES (?, ?, 'comment', 'update', 0, NOW() - INTERVAL 2 DAY)"
        )->execute([$recent, $this->agent]);

        processTicketReminders($this->db);
        processTicketReminders($this->db); // a second run must not repeat anything

        $this->assertSame(1, $this->reminders($late, 'overdue_reminder_sent'));
        $this->assertSame(1, $this->reminders($late, 'update_reminder_sent'));
        foreach (['unassigned' => $noOwner, 'waiting on customer' => $waiting, 'replied 2 days ago' => $recent] as $why => $id) {
            $this->assertSame(0, $this->reminders($id, 'overdue_reminder_sent') + $this->reminders($id, 'update_reminder_sent'), $why);
        }
    }

    public function test_unassigned_tickets_go_to_group_managers_when_enabled(): void
    {
        $this->db->prepare('DELETE FROM group_user_map WHERE group_id = ? AND user_id = ?')->execute([$this->group, $this->agent]);
        $this->db->prepare('INSERT INTO group_user_map (group_id, user_id, is_manager) VALUES (?, ?, 1)')->execute([$this->group, $this->agent]);
        setSetting('ticket_reminders_managers', '1');
        $noOwner = $this->ticket('open', null, date('Y-m-d', strtotime('-1 day')), 2);

        processTicketReminders($this->db);

        $this->assertSame(1, $this->reminders($noOwner, 'overdue_reminder_sent'));
    }

    public function test_moving_the_due_date_later_rearms_the_overdue_reminder(): void
    {
        $id = $this->ticket('open', $this->agent, date('Y-m-d', strtotime('-1 day')), 2);
        processTicketReminders($this->db);
        // That reminder was for an earlier due date; the date was then moved and has now passed too.
        $this->db->prepare("UPDATE ticket_timeline SET created_at = NOW() - INTERVAL 10 DAY WHERE ticket_id = ? AND action = 'overdue_reminder_sent'")
            ->execute([$id]);

        processTicketReminders($this->db);

        $this->assertSame(2, $this->reminders($id, 'overdue_reminder_sent'));
    }

    public function test_new_tickets_only_coverage_skips_the_backlog_and_needs_a_cutoff(): void
    {
        $old = $this->ticket('open', $this->agent, date('Y-m-d', strtotime('-1 day')), 20);
        setSetting('ticket_reminders_scope', 'new');

        setSetting('ticket_reminders_scope_cutoff', '');
        $this->assertSame(['overdue' => 0, 'update' => 0], processTicketReminders($this->db), 'no cutoff yet sends nothing');

        setSetting('ticket_reminders_scope_cutoff', (string) $this->db->query('SELECT NOW()')->fetchColumn());
        processTicketReminders($this->db);
        $this->assertSame(0, $this->reminders($old, 'overdue_reminder_sent'));
    }

    public function test_due_date_follows_the_sla_until_someone_sets_it(): void
    {
        $id  = $this->ticket('open', $this->agent, null, 1);
        $due = fn (): string => (string) $this->db->query("SELECT due_date FROM tickets WHERE id = {$id}")->fetchColumn();
        $sla = fn (string $at) => $this->db->prepare('UPDATE tickets SET resolution_due_at = ? WHERE id = ?')->execute([$at, $id]);

        $sla('2026-11-02 15:00:00');
        Sla::syncDueDate($this->db, $id, null);
        $this->assertSame('2026-11-02', $due(), 'an empty due date takes the SLA date');

        $sla('2026-11-09 10:00:00');
        Sla::syncDueDate($this->db, $id, '2026-11-02 15:00:00');
        $this->assertSame('2026-11-09', $due(), 'a due date on the old SLA date moves with it');

        $this->db->prepare("UPDATE tickets SET due_date = '2027-01-15' WHERE id = ?")->execute([$id]);
        $sla('2026-11-20 10:00:00');
        Sla::syncDueDate($this->db, $id, '2026-11-09 10:00:00');
        $this->assertSame('2027-01-15', $due(), 'a hand-set due date is left alone');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * The ticket list's "Updated" time and "Customer replied" marker
 * (ticketListActivitySelectSql / ticketAwaitingStaffReply).
 *
 * One ticket opened by the portal user is walked through a conversation; after
 * each step the two derived columns are read back the way the list reads them.
 */
class TicketListActivityTest extends TestCase
{
    private const SUBJECT = '[TEST] list activity conversation';

    private static int $id = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::removeFixture();
        $db = \Database::connect();
        $db->prepare(
            "INSERT INTO tickets (subject, description, created_by, status, created_at)
             VALUES (?, 'List activity fixture.', ?, 'open', NOW() - INTERVAL 10 DAY)"
        )->execute([self::SUBJECT, DatabaseSeeder::$portalId]);
        self::$id = (int) $db->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        self::removeFixture();
        parent::tearDownAfterClass();
    }

    private static function removeFixture(): void
    {
        try {
            $db  = \Database::connect();
            $ids = $db->prepare('SELECT id FROM tickets WHERE subject = ?');
            $ids->execute([self::SUBJECT]);
            foreach ($ids->fetchAll(\PDO::FETCH_COLUMN) as $id) {
                $db->prepare('DELETE FROM ticket_timeline WHERE ticket_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM tickets         WHERE id = ?')->execute([$id]);
            }
        } catch (\Throwable) {
            // never mask a real failure
        }
    }

    /** Add a timeline row $daysAgo days back and return its created_at. */
    private function entry(?int $userId, string $action, int $internal, int $daysAgo): string
    {
        $db = \Database::connect();
        $db->prepare(
            "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal, created_at)
             VALUES (?, ?, ?, 'fixture', ?, NOW() - INTERVAL ? DAY)"
        )->execute([self::$id, $userId, $action, $internal, $daysAgo]);
        $q = $db->prepare('SELECT created_at FROM ticket_timeline WHERE id = ?');
        $q->execute([(int) $db->lastInsertId()]);
        return (string) $q->fetchColumn();
    }

    /** The row exactly as the ticket list selects it. */
    private function row(): array
    {
        $q = \Database::connect()->prepare(
            'SELECT t.id, t.status, t.created_at ' . ticketListActivitySelectSql('t') . ' FROM tickets t WHERE t.id = ?'
        );
        $q->execute([self::$id]);
        return $q->fetch(\PDO::FETCH_ASSOC);
    }

    public function test_conversation_drives_updated_time_and_customer_replied_marker(): void
    {
        // Untouched ticket: updated = opened, nobody has replied.
        $r = $this->row();
        $this->assertSame($r['created_at'], $r['last_activity_at']);
        $this->assertNull($r['customer_replied_at']);
        $this->assertFalse(ticketAwaitingStaffReply($r));

        // Agent replies publicly: updated moves, still not the customer's word.
        $agentAt = $this->entry(DatabaseSeeder::$agentId, 'comment', 0, 8);
        $r = $this->row();
        $this->assertSame($agentAt, $r['last_activity_at']);
        $this->assertNull($r['customer_replied_at']);

        // Customer answers: now it is staff's turn.
        $custAt = $this->entry(DatabaseSeeder::$portalId, 'comment', 0, 6);
        $r = $this->row();
        $this->assertSame($custAt, $r['last_activity_at']);
        $this->assertSame($custAt, $r['customer_replied_at']);
        $this->assertTrue(ticketAwaitingStaffReply($r));
        $this->assertStringContainsString('Customer replied', ticketCustomerRepliedBadgeHtml($r));

        // The profile switch hides the markers for that user only; it is on by default.
        $prefKey = 'customer_waiting_marker:' . (int) \Auth::id();
        setSetting($prefKey, '0');
        $this->assertSame('', ticketCustomerRepliedBadgeHtml($r));
        $this->assertStringNotContainsString('bi-reply-fill', ticketUpdatedCellHtml($r));
        \Database::connect()->prepare('DELETE FROM settings WHERE setting_key = ?')->execute([$prefKey]);
        $cache = &settingsCache();
        unset($cache[$prefKey]);
        $this->assertStringContainsString('bi-reply-fill', ticketUpdatedCellHtml($r));

        // An internal note is activity, but the customer is still waiting.
        $noteAt = $this->entry(DatabaseSeeder::$agentId, 'comment', 1, 4);
        $r = $this->row();
        $this->assertSame($noteAt, $r['last_activity_at']);
        $this->assertSame($custAt, $r['customer_replied_at']);

        // Automation (no user) never makes a ticket look freshly updated.
        $this->entry(null, 'automation', 1, 3);
        $this->assertSame($noteAt, $this->row()['last_activity_at']);

        // Agent replies publicly: marker clears.
        $this->entry(DatabaseSeeder::$agentId, 'comment', 0, 2);
        $r = $this->row();
        $this->assertNull($r['customer_replied_at']);
        $this->assertSame('', ticketCustomerRepliedBadgeHtml($r));

        // Customer's "thanks" on a closed ticket is not a call to action.
        $this->entry(DatabaseSeeder::$portalId, 'comment', 0, 1);
        $r = $this->row();
        $this->assertTrue(ticketAwaitingStaffReply($r));
        $r['status'] = ticketClosedBucketSlugs()[0];
        $this->assertFalse(ticketAwaitingStaffReply($r));
    }
}

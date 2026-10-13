<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database;
use Tests\Support\TestCase;

/**
 * notifyGroupMembers() scoping (2.176.1, 2.180.1).
 *
 * A ticket's own group wins over its type's group; either notifies only that
 * group's members (if the group opted in). A ticket with no group and no
 * grouped type still notifies every group with notify_new_ticket = 1.
 * Nobody gets two emails for one new ticket (2.180.2): the broadcast skips the
 * requester and anyone already emailed, and a CC rule skips the requester.
 * Observed through mail_queue with the MAIL_ENABLED kill switch on.
 */
class GroupNewTicketScopeTest extends TestCase
{
    private const PREFIX = '[TEST] gnts ';
    private const EMAIL_A = 'gnts-a@test.local';
    private const EMAIL_B = 'gnts-b@test.local';

    private static int $groupA;
    private static int $groupB;
    private static int $groupOff;
    private static int $typeA;
    private static int $userA;
    private static int $userB;
    private static int $creator;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::removeFixtures();
        $db = Database::connect();

        foreach (['A' => self::EMAIL_A, 'B' => self::EMAIL_B] as $k => $email) {
            $db->prepare('INSERT INTO `groups` (name, notify_new_ticket) VALUES (?, 1)')->execute([self::PREFIX . $k]);
            $gid = (int) $db->lastInsertId();
            $db->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES ('Gnts', ?, ?, 'x', 'agent')")
               ->execute([$k, $email]);
            $uid = (int) $db->lastInsertId();
            $db->prepare('INSERT INTO group_user_map (group_id, user_id) VALUES (?, ?)')->execute([$gid, $uid]);
            if ($k === 'A') { self::$groupA = $gid; self::$userA = $uid; } else { self::$groupB = $gid; self::$userB = $uid; }
        }
        $db->prepare('INSERT INTO `groups` (name, notify_new_ticket) VALUES (?, 0)')->execute([self::PREFIX . 'off']);
        self::$groupOff = (int) $db->lastInsertId();
        $db->prepare('INSERT INTO ticket_types (name, group_id) VALUES (?, ?)')->execute([self::PREFIX . 'type', self::$groupA]);
        self::$typeA = (int) $db->lastInsertId();
        self::$creator = (int) $db->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
    }

    public static function tearDownAfterClass(): void
    {
        self::removeFixtures();
        parent::tearDownAfterClass();
    }

    private static function removeFixtures(): void
    {
        $db = Database::connect();
        $db->prepare('DELETE FROM mail_queue WHERE to_email IN (?, ?)')->execute([self::EMAIL_A, self::EMAIL_B]);
        $db->prepare('DELETE FROM notifications WHERE user_id IN (SELECT id FROM users WHERE email IN (?, ?))')->execute([self::EMAIL_A, self::EMAIL_B]);
        $db->prepare('DELETE FROM tickets WHERE subject LIKE ?')->execute([self::PREFIX . '%']);
        $db->prepare('DELETE FROM ticket_types WHERE name LIKE ?')->execute([self::PREFIX . '%']);
        $db->prepare('DELETE FROM group_user_map WHERE group_id IN (SELECT id FROM `groups` WHERE name LIKE ?)')->execute([self::PREFIX . '%']);
        $db->prepare('DELETE FROM `groups` WHERE name LIKE ?')->execute([self::PREFIX . '%']);
        $db->prepare('DELETE FROM users WHERE email IN (?, ?)')->execute([self::EMAIL_A, self::EMAIL_B]);
    }

    private function makeTicket(?int $typeId, ?int $groupId = null, ?int $createdBy = null): int
    {
        $_ENV['MAIL_ENABLED'] = 'false';
        $db = Database::connect();
        $db->prepare("INSERT INTO tickets (subject, description, created_by, status, type_id, group_id) VALUES (?, 'x', ?, 'open', ?, ?)")
           ->execute([self::PREFIX . ($typeId ? 'typed' : 'untyped'), $createdBy ?? self::$creator, $typeId, $groupId]);
        return (int) $db->lastInsertId();
    }

    /** @return string[] recipient emails queued for the ticket, one per email */
    private function queued(int $tid): array
    {
        $stmt = Database::connect()->prepare('SELECT to_email FROM mail_queue WHERE ticket_id = ? ORDER BY to_email');
        $stmt->execute([$tid]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** @return string[] recipient emails queued for the ticket */
    private function recipientsFor(?int $typeId, ?int $groupId = null): array
    {
        $tid = $this->makeTicket($typeId, $groupId);
        notifyGroupMembers(Database::connect(), $tid);
        return $this->queued($tid);
    }

    public function testTypedTicketNotifiesOnlyItsGroup(): void
    {
        $this->assertSame([self::EMAIL_A], $this->recipientsFor(self::$typeA));
    }

    public function testUntypedTicketNotifiesAllOptedInGroups(): void
    {
        $got = $this->recipientsFor(null);
        $this->assertContains(self::EMAIL_A, $got);
        $this->assertContains(self::EMAIL_B, $got);
    }

    public function testTicketGroupWinsOverTypeGroup(): void
    {
        $this->assertSame([self::EMAIL_B], $this->recipientsFor(self::$typeA, self::$groupB));
    }

    /** Ticket #67737: ungrouped type, ticket in an opted-out group — no broadcast. */
    public function testTicketInOptedOutGroupNotifiesNobody(): void
    {
        $this->assertSame([], $this->recipientsFor(null, self::$groupOff));
    }

    public function testBroadcastSkipsRequester(): void
    {
        $tid = $this->makeTicket(null, self::$groupA, self::$userA);
        notifyGroupMembers(Database::connect(), $tid);
        $this->assertSame([], $this->queued($tid));
    }

    /** A CC rule that matches the requester's own ticket sends them nothing extra. */
    public function testCcRuleSkipsRequester(): void
    {
        $db = Database::connect();
        $tid = $this->makeTicket(null, self::$groupOff, self::$userA);
        notifyCcAdded($db, $tid, self::$userA, 'Automation "x"', 0);
        notifyCcAdded($db, $tid, self::$userB, 'Automation "x"', 0);
        $this->assertSame([self::EMAIL_B], $this->queued($tid));
    }

    /** CC'd by a rule and a member of the notified group: one email, not two. */
    public function testCcdGroupMemberGetsOneEmail(): void
    {
        $db = Database::connect();
        $tid = $this->makeTicket(null, self::$groupA);
        notifyCcAdded($db, $tid, self::$userA, 'Automation "x"', 0);
        notifyGroupMembers($db, $tid);
        $this->assertSame([self::EMAIL_A], $this->queued($tid));
    }
}

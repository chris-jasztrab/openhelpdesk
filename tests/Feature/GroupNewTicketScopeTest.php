<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database;
use Tests\Support\TestCase;

/**
 * notifyGroupMembers() scoping (2.176.1).
 *
 * A ticket whose type is tied to a group notifies only that group's members.
 * A ticket with no type still notifies every group with notify_new_ticket = 1.
 * Observed through mail_queue with the MAIL_ENABLED kill switch on.
 */
class GroupNewTicketScopeTest extends TestCase
{
    private const PREFIX = '[TEST] gnts ';
    private const EMAIL_A = 'gnts-a@test.local';
    private const EMAIL_B = 'gnts-b@test.local';

    private static int $groupA;
    private static int $groupB;
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

    /** @return string[] recipient emails queued for the ticket */
    private function recipientsFor(?int $typeId): array
    {
        $_ENV['MAIL_ENABLED'] = 'false';
        $db = Database::connect();
        $db->prepare("INSERT INTO tickets (subject, description, created_by, status, type_id) VALUES (?, 'x', ?, 'open', ?)")
           ->execute([self::PREFIX . ($typeId ? 'typed' : 'untyped'), self::$creator, $typeId]);
        $tid = (int) $db->lastInsertId();
        notifyGroupMembers($db, $tid);
        $stmt = $db->prepare('SELECT to_email FROM mail_queue WHERE ticket_id = ? ORDER BY to_email');
        $stmt->execute([$tid]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
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
}

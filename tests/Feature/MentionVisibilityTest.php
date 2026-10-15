<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database;
use Tests\Support\TestCase;

/**
 * @mention access + ticket list (2.190.0). A group-restricted agent mentioned on
 * another group's ticket can open it, sees it on their ticket list flagged as
 * mention-only, and can hide it. A mention never opens a confidential ticket.
 */
class MentionVisibilityTest extends TestCase
{
    private const PREFIX = '[TEST] mnvis ';
    private const EMAIL  = 'mnvis@test.local';

    private static int $agent;
    private static int $own;        // ticket in the agent's group, mentioned
    private static int $other;      // other group, mentioned
    private static int $unmentioned; // other group, not mentioned
    private static int $confidential; // confidential type, mentioned

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::removeFixtures();
        $db = Database::connect();
        $db->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES ('Mnvis', 'Agent', ?, 'x', 'agent')")->execute([self::EMAIL]);
        self::$agent = (int) $db->lastInsertId();
        $author = (int) $db->query('SELECT id FROM users WHERE id <> ' . self::$agent . ' ORDER BY id LIMIT 1')->fetchColumn();

        $addGroup = $db->prepare('INSERT INTO `groups` (name) VALUES (?)');
        $addGroup->execute([self::PREFIX . 'mine']);
        $mine = (int) $db->lastInsertId();
        $addGroup->execute([self::PREFIX . 'other']);
        $other = (int) $db->lastInsertId();
        $db->prepare('INSERT INTO group_user_map (group_id, user_id) VALUES (?, ?)')->execute([$mine, self::$agent]);
        $db->prepare('INSERT INTO ticket_types (name, group_id, is_confidential) VALUES (?, ?, 1)')->execute([self::PREFIX . 'conf', $other]);
        $confType = (int) $db->lastInsertId();

        $ticket = static function (?int $groupId, ?int $typeId, bool $mention) use ($db, $author): int {
            $db->prepare("INSERT INTO tickets (subject, description, created_by, status, group_id, type_id) VALUES (?, 'x', ?, 'open', ?, ?)")
               ->execute([self::PREFIX . 'subj', $author, $groupId, $typeId]);
            $tid = (int) $db->lastInsertId();
            if ($mention) {
                $db->prepare("INSERT INTO notifications (user_id, ticket_id, type, mentioned_by) VALUES (?, ?, 'mention', ?)")
                   ->execute([self::$agent, $tid, $author]);
            }
            return $tid;
        };
        self::$own          = $ticket($mine, null, true);
        self::$other        = $ticket($other, null, true);
        self::$unmentioned  = $ticket($other, null, false);
        self::$confidential = $ticket($other, $confType, true);
    }

    public static function tearDownAfterClass(): void
    {
        self::removeFixtures();
        parent::tearDownAfterClass();
    }

    private static function removeFixtures(): void
    {
        $db = Database::connect();
        $db->prepare('DELETE FROM tickets WHERE subject LIKE ?')->execute([self::PREFIX . '%']);
        $db->prepare('DELETE FROM ticket_types WHERE name LIKE ?')->execute([self::PREFIX . '%']);
        $db->prepare('DELETE FROM group_user_map WHERE group_id IN (SELECT id FROM `groups` WHERE name LIKE ?)')->execute([self::PREFIX . '%']);
        $db->prepare('DELETE FROM `groups` WHERE name LIKE ?')->execute([self::PREFIX . '%']);
        $db->prepare('DELETE FROM users WHERE email = ?')->execute([self::EMAIL]);
    }

    /** @return int[] fixture ticket ids the agent's list shows */
    private function listed(bool $withMentions): array
    {
        $db  = Database::connect();
        $vis = ticketStaffVisibilitySql($db, self::$agent, 'agent', 't', $withMentions);
        $stmt = $db->prepare("SELECT t.id FROM tickets t WHERE t.subject LIKE ? AND {$vis['sql']} ORDER BY t.id");
        $stmt->execute(array_merge([self::PREFIX . '%'], $vis['params']));
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function testMentionGrantsAccessOutsideGroup(): void
    {
        $db = Database::connect();
        $this->assertTrue(ticketAccessExempt($db, self::$agent, self::$other));
        $this->assertFalse(ticketAccessExempt($db, self::$agent, self::$unmentioned));
    }

    public function testListShowsMentionedTicketsUnlessHidden(): void
    {
        $this->assertSame([self::$own, self::$other], $this->listed(true));
        $this->assertSame([self::$own], $this->listed(false));
    }

    public function testOnlyOutOfGroupMentionIsFlagged(): void
    {
        $flagged = mentionOnlyTicketIds(Database::connect(), [self::$own, self::$other], self::$agent, 'agent');
        $this->assertSame([self::$other => true], $flagged);
    }

    public function testMentionNeverRevealsConfidentialTicket(): void
    {
        $this->assertNotContains(self::$confidential, $this->listed(true));
    }
}

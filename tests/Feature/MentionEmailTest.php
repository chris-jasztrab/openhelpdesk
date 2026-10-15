<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database;
use Tests\Support\TestCase;

/**
 * @mention emails (2.189.0). processAtMentions() emails each mentioned staff
 * member unless they turned off notify_mention, and never leaks the subject or
 * message of a confidential ticket they can't see. Observed through mail_queue
 * with the MAIL_ENABLED kill switch on.
 */
class MentionEmailTest extends TestCase
{
    private const PREFIX  = '[TEST] mnem ';
    private const EMAIL_A = 'mnem-a@test.local';
    private const EMAIL_B = 'mnem-b@test.local';
    private const SECRET  = 'mnem-secret-body';

    private static int $userA;
    private static int $userB;
    private static int $confType;
    private static int $author;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::removeFixtures();
        $db = Database::connect();
        $add = $db->prepare("INSERT INTO users (first_name, last_name, email, password, role, notify_mention) VALUES ('Mnem', ?, ?, 'x', 'agent', ?)");
        $add->execute(['Alpha', self::EMAIL_A, 1]);
        self::$userA = (int) $db->lastInsertId();
        $add->execute(['Bravo', self::EMAIL_B, 0]);
        self::$userB = (int) $db->lastInsertId();

        // Confidential type owned by a group neither test user belongs to.
        $db->prepare('INSERT INTO `groups` (name) VALUES (?)')->execute([self::PREFIX . 'conf']);
        $gid = (int) $db->lastInsertId();
        $db->prepare('INSERT INTO ticket_types (name, group_id, is_confidential) VALUES (?, ?, 1)')->execute([self::PREFIX . 'conf', $gid]);
        self::$confType = (int) $db->lastInsertId();
        self::$author = (int) $db->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
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
        $db->prepare('DELETE FROM `groups` WHERE name LIKE ?')->execute([self::PREFIX . '%']);
        $db->prepare('DELETE FROM users WHERE email IN (?, ?)')->execute([self::EMAIL_A, self::EMAIL_B]);
    }

    /** Post a note mentioning both users on a fresh ticket; return its queued mail rows. */
    private function mentionBoth(?int $typeId): array
    {
        $_ENV['MAIL_ENABLED'] = 'false';
        $db = Database::connect();
        $db->prepare("INSERT INTO tickets (subject, description, created_by, status, type_id) VALUES (?, 'x', ?, 'open', ?)")
           ->execute([self::PREFIX . 'subj', self::$author, $typeId]);
        $tid = (int) $db->lastInsertId();
        $message = '<p>@Mnem Alpha @Mnem Bravo ' . self::SECRET . '</p>';
        $db->prepare("INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal) VALUES (?, ?, 'comment', ?, 1)")
           ->execute([$tid, self::$author, $message]);
        processAtMentions($db, $message, $tid, (int) $db->lastInsertId(), self::$author);

        $stmt = $db->prepare('SELECT to_email, subject, html_body FROM mail_queue WHERE ticket_id = ?');
        $stmt->execute([$tid]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function testMentionEmailsOnlyOptedInUser(): void
    {
        $rows = $this->mentionBoth(null);
        $this->assertSame([self::EMAIL_A], array_column($rows, 'to_email'));
        $this->assertStringContainsString('mentioned you', $rows[0]['subject']);
        $this->assertStringContainsString(self::PREFIX . 'subj', $rows[0]['subject']);
        $this->assertStringContainsString(self::SECRET, $rows[0]['html_body']);
    }

    public function testOptedOutUserStillGetsInAppNotification(): void
    {
        $this->mentionBoth(null);
        $n = Database::connect()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'mention'");
        $n->execute([self::$userB]);
        $this->assertGreaterThan(0, (int) $n->fetchColumn());
    }

    public function testConfidentialTicketIsRedacted(): void
    {
        $rows = $this->mentionBoth(self::$confType);
        $this->assertSame([self::EMAIL_A], array_column($rows, 'to_email'));
        $this->assertStringNotContainsString(self::PREFIX . 'subj', $rows[0]['subject']);
        $this->assertStringNotContainsString(self::PREFIX . 'subj', $rows[0]['html_body']);
        $this->assertStringNotContainsString(self::SECRET, $rows[0]['html_body']);
    }
}

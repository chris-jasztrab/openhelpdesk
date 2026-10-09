<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database;
use Tests\Support\TestCase;

/**
 * Outbound mail queue (sendMail() → mail_queue).
 *
 * Locks down the one promise the Email Queue page depends on: an email blocked
 * by the MAIL_ENABLED kill switch is parked as a row, not dropped. Deliberately
 * never exercises mailQueueSendAll() — the local DB carries live SMTP
 * credentials and that function bypasses the kill switch on purpose.
 */
class MailQueueTest extends TestCase
{
    private const TO = 'mailqueue-test@test.local';

    protected function tearDown(): void
    {
        Database::connect()->prepare('DELETE FROM mail_queue WHERE to_email = ?')->execute([self::TO]);
        unset($_ENV['MAIL_ENABLED']);
        parent::tearDown();
    }

    public function testKillSwitchParksEmailInQueue(): void
    {
        $_ENV['MAIL_ENABLED'] = 'false';

        $result = sendMail(self::TO, 'Queue Test', 'Queued subject', '<p>hi</p>', 'hi', 4242, [], false);
        $this->assertFalse($result);

        $stmt = Database::connect()->prepare('SELECT * FROM mail_queue WHERE to_email = ?');
        $stmt->execute([self::TO]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(1, $rows);
        $this->assertSame('Queued subject', $rows[0]['subject']);
        $this->assertSame('MAIL_ENABLED=false', $rows[0]['reason']);
        $this->assertSame(4242, (int) $rows[0]['ticket_id']);
        $this->assertSame(0, (int) $rows[0]['attempts']);
    }
}

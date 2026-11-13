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

    /**
     * The timed retry must never release kill-switch rows or stale ones, and a
     * row that keeps failing goes to the back. Selection only — nothing is sent.
     */
    public function testRetryPicksRecentFailuresLeastTriedFirst(): void
    {
        $db  = Database::connect();
        $ins = $db->prepare(
            "INSERT INTO mail_queue (to_email, subject, html_body, reason, attempts, created_at)
             VALUES (?, ?, '<p>x</p>', ?, ?, NOW() - INTERVAL ? DAY)"
        );
        $ins->execute([self::TO, 'kill switch', 'MAIL_ENABLED=false', 0, 0]);
        $ins->execute([self::TO, 'too old', 'Graph POST HTTP 502', 0, 4]);
        $ins->execute([self::TO, 'tried twice', 'Graph POST HTTP 502', 2, 0]);
        $ins->execute([self::TO, 'fresh', 'Graph POST HTTP 502', 0, 1]);

        $mine = array_values(array_filter(mailQueueRetryRows($db), fn ($r) => $r['to_email'] === self::TO));

        $this->assertSame(['fresh', 'tried twice'], array_column($mine, 'subject'));
    }

    public function testDelayNoticeSaysHowLongTheEmailWaited(): void
    {
        [$html, $text] = mailQueueDelayNotice([
            'html_body'      => '<html><body style="margin:0"><p>hi</p></body></html>',
            'text_body'      => 'hi',
            'queued_minutes' => 135,
            'created_at'     => '2026-11-12 14:14:00',
        ]);

        $this->assertStringContainsString('waited 2 hours 15 minutes in the email queue (queued Nov 12 at 2:14 pm)', $text);
        $this->assertStringEndsWith("\n\nhi", $text);
        // The note goes right after <body>, before the original content.
        $this->assertMatchesRegularExpression('#^<html><body style="margin:0"><table.*was delayed.*</table><p>hi</p>#s', $html);
        // Multi-day waits read in days, and a body with no <body> tag still gets the note.
        [$html2, $text2] = mailQueueDelayNotice(['html_body' => '<p>x</p>', 'text_body' => '', 'queued_minutes' => 4400, 'created_at' => '2026-11-10 08:00:00']);
        $this->assertStringContainsString('waited 3 days', $text2);
        $this->assertStringEndsWith('<p>x</p>', $html2);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Pure helpers behind scripts/process-replies.php: which inbound mail is dropped
 * as machine-sent, and where a person's reply goes when it can't land on the
 * ticket it names.
 */
class InboundReplyHelpersTest extends TestCase
{
    private static function msg(string $subject, array $headers = [], string $from = 'patron@example.com'): array
    {
        $h = [];
        foreach ($headers as $name => $value) {
            $h[] = ['name' => $name, 'value' => $value];
        }
        return ['subject' => $subject, 'internetMessageHeaders' => $h, 'from' => ['emailAddress' => ['address' => $from]]];
    }

    public function test_out_of_office_and_bounces_are_auto_replies(): void
    {
        $this->assertTrue(isAutoReply(self::msg('Re: printer', ['Auto-Submitted' => 'auto-replied'])));
        $this->assertTrue(isAutoReply(self::msg('Re: printer', ['X-Auto-Response-Suppress' => 'All'])));
        $this->assertTrue(isAutoReply(self::msg('x', ['X-MS-Exchange-Message-Is-Ndr' => ''])));
        $this->assertTrue(isAutoReply(self::msg('x', ['Content-Type' => 'multipart/report; report-type=delivery-status'])));
        $this->assertTrue(isAutoReply(self::msg('x', [], 'MAILER-DAEMON@mail.example.com')));
        $this->assertTrue(isAutoReply(self::msg('Automatic reply: [Ticket #5] Printer jam')), 'Exchange OOF, no headers');
        $this->assertTrue(isAutoReply(self::msg('Out of Office: [Ticket #5] Printer jam')));
        $this->assertTrue(isAutoReply(self::msg('Undeliverable: [Ticket #5] Closed: Printer jam')));
        $this->assertTrue(isAutoReply(self::msg('Delivery Status Notification (Failure)')));
        $this->assertTrue(isAutoReply(self::msg('Réponse automatique : [Ticket #5] Imprimante')));
    }

    public function test_a_person_writing_about_out_of_office_is_not_an_auto_reply(): void
    {
        $this->assertFalse(isAutoReply(self::msg('RE: [Ticket #12] Out of office not working')), 'keyword mid-subject');
        $this->assertFalse(isAutoReply(self::msg('Out of office message wont turn off')), 'no colon after the phrase');
        $this->assertFalse(isAutoReply(self::msg('RE: [Ticket #5] Closed: Printer jam', ['Auto-Submitted' => 'no'])));
        $this->assertFalse(isAutoReply(self::msg('Printer jam')));
    }

    public function test_spam_quarantine_report_is_dropped_but_a_forwarded_one_is_not(): void
    {
        $report = self::msg('Spam Quarantine Report', [], 'report@spamtitan.com');
        $this->assertTrue(isAutoReply($report + ['bodyPreview' => " \r\nThis is your Spam Quarantine Report. SpamTitan caught these spam…"]));
        $this->assertFalse(isAutoReply(self::msg('FW: Spam Quarantine Report') + [
            'bodyPreview' => "Can you release the invoice email?\r\nFrom: report@spamtitan.com\r\nThis is your Spam Quarantine Report.",
        ]), 'a person forwarding the report still opens a ticket');
    }

    public function test_follow_up_subject_drops_reply_prefixes_ticket_tag_and_status_label(): void
    {
        $this->assertSame('Printer jam', followUpTicketSubject('RE: [Ticket #123] Closed: Printer jam'));
        $this->assertSame('Printer jam', followUpTicketSubject('Re: RE: FW: [#123] Resolved: Printer jam'));
        $this->assertSame('Printer jam', followUpTicketSubject('[Ticket #123] Printer jam'));
        $this->assertSame('Closed branch hours', followUpTicketSubject('RE: [Ticket #9] Closed branch hours'), 'no colon: a word, not the label');
        $this->assertSame('(No Subject)', followUpTicketSubject('RE: [Ticket #123]'));
    }

    public function test_reply_routing_never_drops_only_redirects(): void
    {
        $open   = ['status' => 'open'];
        $closed = ['status' => 'closed'];
        $user   = ['id' => 7, 'role' => 'user'];

        $this->assertNull(inboundReplyNewTicketReason($open, $user, false, 'closed'), 'requester on an open ticket: comment');
        $this->assertSame('closed', inboundReplyNewTicketReason($closed, $user, false, 'closed'));
        $this->assertNull(inboundReplyNewTicketReason($closed, $user, true, 'closed'), 'staff land on the closed ticket');
        $this->assertNull(inboundReplyNewTicketReason(['status' => 'resolved'], $user, false, 'closed'), 'only Closed redirects');
        $this->assertSame('unknown_sender', inboundReplyNewTicketReason($open, null, false, 'closed'));
        $this->assertSame('missing', inboundReplyNewTicketReason(null, $user, false, 'closed'));
        $this->assertSame('missing', inboundReplyNewTicketReason(null, null, false, 'closed'));
    }
}

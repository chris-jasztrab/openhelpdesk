<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Closing a ticket whose status triggers the satisfaction survey sends the
 * requester ONE email — the closed notice carrying the survey — not two
 * (v2.221.0). Runs in a rolled-back transaction; mail lands in mail_queue
 * because the test instance has MAIL_ENABLED=false.
 */
class CsatCombinedEmailTest extends TestCase
{
    private PDO $db;
    private int $ticket;
    private string $closed;
    private array $settings;

    protected function setUp(): void
    {
        if (env('MAIL_ENABLED', 'true') !== 'false') {
            $this->markTestSkipped('Needs MAIL_ENABLED=false so nothing is really sent.');
        }
        $this->db = Database::connect();
        $ticket = $this->db->query(
            "SELECT t.id FROM tickets t JOIN users u ON u.id = t.created_by
             WHERE u.email <> '' AND u.notify_csat = 1 AND u.notify_ticket_closed = 1
               AND t.id NOT IN (SELECT ticket_id FROM csat_surveys) LIMIT 1"
        )->fetchColumn();
        if ($ticket === false) {
            $this->markTestSkipped('Needs an unsurveyed ticket whose requester takes both emails.');
        }
        $this->ticket   = (int) $ticket;
        $this->closed   = ticketDefaultClosedStatusSlug();
        $this->settings = settingsCache();
        $this->db->beginTransaction();
        setSetting('csat_enabled', '1');
        setSetting('csat_mode', 'internal');
        setSetting('csat_trigger_status', $this->closed);
        setSetting('email_notify:requester_ticket_closed', '1');
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
            $cache = &settingsCache();
            $cache = $this->settings;
        }
    }

    /** Bodies of the emails queued for the ticket by $send. */
    private function mailFrom(callable $send): array
    {
        $after = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) FROM mail_queue')->fetchColumn();
        $send();
        $s = $this->db->prepare('SELECT html_body FROM mail_queue WHERE ticket_id = ? AND id > ?');
        $s->execute([$this->ticket, $after]);
        return $s->fetchAll(PDO::FETCH_COLUMN);
    }

    public function testCloseSendsOneEmailCarryingTheSurvey(): void
    {
        $mail = $this->mailFrom(function () {
            notifyRequesterStatusChanged($this->db, $this->ticket, $this->closed);
            sendCsatSurvey($this->db, $this->ticket);
        });

        $this->assertCount(1, $mail);
        $this->assertStringContainsString('/survey/', $mail[0]);
        $this->assertStringContainsString('has been closed', $mail[0]);
    }

    public function testNoticeWithoutSurveyLeavesTheSurveyUnclaimed(): void
    {
        $mail = $this->mailFrom(fn () => notifyRequesterStatusChanged($this->db, $this->ticket, $this->closed, false));
        $this->assertCount(1, $mail);
        $this->assertStringNotContainsString('/survey/', $mail[0]);

        $s = $this->db->prepare('SELECT COUNT(*) FROM csat_surveys WHERE ticket_id = ?');
        $s->execute([$this->ticket]);
        $this->assertSame(0, (int) $s->fetchColumn());
    }
}

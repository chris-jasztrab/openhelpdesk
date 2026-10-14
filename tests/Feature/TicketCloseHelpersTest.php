<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Pure helpers behind the ticket page's Close split-button (v2.183.0): what the
 * modal's "Email the requester" box + message turn into, and the follow-up
 * reminder's datetime-local parser.
 */
class TicketCloseHelpersTest extends TestCase
{
    public function test_notify_action_covers_all_four_combinations(): void
    {
        $this->assertSame('reply', closeNotifyAction(true, 'We fixed it.'));
        $this->assertSame('note', closeNotifyAction(false, 'We fixed it.'), 'unticked keeps the message staff-only');
        $this->assertSame('closed_email', closeNotifyAction(true, ''), 'blank falls back to the standard closed email');
        $this->assertSame('none', closeNotifyAction(false, ''));
        $this->assertSame('closed_email', closeNotifyAction(true, "  \n "), 'whitespace is blank');
    }

    public function test_followup_due_at_accepts_a_future_time_within_a_year(): void
    {
        $now = strtotime('2026-10-14 10:00:00');
        $this->assertSame('2026-10-21 09:00:00', followupDueAt('2026-10-21T09:00', $now));
    }

    public function test_followup_due_at_rejects_bad_input(): void
    {
        $now = strtotime('2026-10-14 10:00:00');
        $this->assertNull(followupDueAt('', $now));
        $this->assertNull(followupDueAt('2026-10-14T09:59', $now), 'in the past');
        $this->assertNull(followupDueAt('2026-10-14T10:00', $now), 'now is not the future');
        $this->assertNull(followupDueAt('2028-01-01T09:00', $now), 'more than a year out');
        $this->assertNull(followupDueAt('2026-02-30T09:00', $now), 'impossible date does not roll over');
        $this->assertNull(followupDueAt('2026-10-21 09:00', $now), 'wrong format');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Pure helpers behind the ticket Time Tracking card (v2.181.0): the manual
 * entry parser, the display format, and the running-timer arithmetic.
 */
class TimeTrackingHelpersTest extends TestCase
{
    public function test_input_parser_accepts_whole_hours_and_minutes(): void
    {
        $this->assertSame(4500, timeEntryInputSeconds('1', '15'));
        $this->assertSame(1800, timeEntryInputSeconds('', '30'), 'blank hours means 0');
        $this->assertSame(0, timeEntryInputSeconds('0', '0'), 'zero is parsed; callers reject it');
        $this->assertSame(86400, timeEntryInputSeconds('24', '0'));
    }

    public function test_input_parser_rejects_bad_values(): void
    {
        $this->assertNull(timeEntryInputSeconds('1.5', '0'), 'no decimals');
        $this->assertNull(timeEntryInputSeconds('-1', '0'), 'no negatives');
        $this->assertNull(timeEntryInputSeconds('1', '60'), 'minutes cap at 59');
        $this->assertNull(timeEntryInputSeconds('24', '1'), 'one entry caps at 24h');
        $this->assertNull(timeEntryInputSeconds('abc', '0'));
    }

    public function test_tracked_time_format_never_rolls_into_days(): void
    {
        $this->assertSame('0m', formatTrackedTime(0));
        $this->assertSame('<1m', formatTrackedTime(20));
        $this->assertSame('45m', formatTrackedTime(2700));
        $this->assertSame('2h 05m', formatTrackedTime(7500));
        $this->assertSame('26h 00m', formatTrackedTime(26 * 3600));
    }

    public function test_elapsed_adds_running_timer_to_stored_seconds(): void
    {
        $now = strtotime('2026-10-14 10:00:00');
        $this->assertSame(600, timeEntryElapsed(['seconds' => 600, 'timer_started_at' => null], $now));
        $this->assertSame(690, timeEntryElapsed(['seconds' => 600, 'timer_started_at' => '2026-10-14 09:58:30'], $now));
        // A start time in the future (clock skew) never subtracts time.
        $this->assertSame(600, timeEntryElapsed(['seconds' => 600, 'timer_started_at' => '2026-10-14 10:05:00'], $now));
    }
}

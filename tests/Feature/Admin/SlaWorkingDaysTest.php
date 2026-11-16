<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Sla;

/**
 * An SLA target of a day or more counts working days, not 24 business hours:
 * "2d" on a ticket opened Monday 10:00 is due Wednesday 10:00 however long the
 * team works each day.
 */
final class SlaWorkingDaysTest extends TestCase
{
    private const TZ = 'America/Toronto';

    /** Long Mon–Thu, short Fri/Sat, afternoon-only Sunday. */
    private const SCHEDULE = [
        'mon' => ['09:30', '21:00'], 'tue' => ['09:30', '21:00'], 'wed' => ['09:30', '21:00'],
        'thu' => ['09:30', '21:00'], 'fri' => ['09:30', '17:30'], 'sat' => ['09:00', '17:30'],
        'sun' => ['13:00', '16:00'],
    ];
    private const MON_SAT = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    protected function setUp(): void
    {
        require_once ROOT_DIR . '/src/Sla.php';
    }

    /** @dataProvider cases */
    public function test_working_days(string $from, int $days, string $expected, array $holidays = []): void
    {
        $due = Sla::addWorkingDays(
            new DateTimeImmutable($from, new DateTimeZone(self::TZ)),
            $days,
            self::TZ,
            self::SCHEDULE,
            $holidays,
            self::MON_SAT
        );
        $this->assertSame($expected, $due->format('D Y-m-d H:i'));
    }

    public static function cases(): array
    {
        // 2026-11-16 is a Monday.
        return [
            'same time two days on'           => ['2026-11-16 10:00', 2, 'Wed 2026-11-18 10:00'],
            'skips the uncounted Sunday'      => ['2026-11-20 10:00', 2, 'Mon 2026-11-23 10:00'],
            'skips a holiday'                 => ['2026-11-16 10:00', 2, 'Thu 2026-11-19 10:00', ['2026-11-17']],
            'after closing starts next open'  => ['2026-11-16 22:00', 1, 'Wed 2026-11-18 09:30'],
            'before opening starts at open'   => ['2026-11-16 07:00', 1, 'Tue 2026-11-17 09:30'],
            'closed day starts next open'     => ['2026-11-22 14:00', 1, 'Tue 2026-11-24 09:30'],
            'capped at a short day\'s close'  => ['2026-11-19 20:00', 1, 'Fri 2026-11-20 17:30'],
            'thirty days is thirty, not 720h' => ['2026-11-16 10:00', 30, 'Mon 2026-12-21 10:00'],
        ];
    }
}

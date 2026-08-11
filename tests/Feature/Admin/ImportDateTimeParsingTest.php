<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use DateTimeImmutable;
use DateTimeZone;
use Tests\Support\TestCase;

/**
 * Timestamp parsing for CSV ticket imports.
 *
 * parseImportDateTime() must return **server local time**, which is the frame
 * every datetime in this database uses: `tickets.created_at` and `updated_at`
 * are MySQL TIMESTAMP columns, so the literal is read in MySQL's session
 * timezone, and the DATETIME columns are read back by PHP with a bare
 * `new DateTimeImmutable()`.
 *
 * It used to normalise to UTC, which shifted every imported timestamp by the
 * server's UTC offset — four hours in Toronto — with nothing to show for it. The
 * assertions below compare against the true instant, so they fail for any
 * offset in either direction.
 */
class ImportDateTimeParsingTest extends TestCase
{
    /** The instant a wall-clock reading in $tz actually refers to. */
    private function instant(string $wallClock, string $tz): int
    {
        return (new DateTimeImmutable($wallClock, new DateTimeZone($tz)))->getTimestamp();
    }

    public function test_a_source_timezone_reading_maps_to_the_correct_instant(): void
    {
        $stored = \parseImportDateTime('2022-08-22 12:31:35', 'America/Toronto');

        $this->assertNotNull($stored);
        $this->assertSame(
            $this->instant('2022-08-22 12:31:35', 'America/Toronto'),
            strtotime($stored),
            ' — the stored value must denote the same instant as the source reading'
        );
    }

    /**
     * The regression itself: a Toronto export stored as UTC read back four hours
     * late. Pinned as an explicit inequality so the old behaviour can't return.
     */
    public function test_the_value_is_not_normalised_to_utc(): void
    {
        $raw    = '2022-08-22 12:31:35';
        $stored = \parseImportDateTime($raw, 'America/Toronto');

        $asUtc = (new DateTimeImmutable($raw, new DateTimeZone('America/Toronto')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        if ($asUtc === $stored) {
            // Only legitimate when the server itself runs on UTC.
            $this->assertSame(
                'UTC',
                (new DateTimeZone(date_default_timezone_get()))->getName(),
                ' — a UTC-normalised result is only correct on a UTC server'
            );
        }
        $this->assertSame(
            $this->instant('2022-08-22 12:31:35', 'America/Toronto'),
            strtotime($stored)
        );
    }

    /** A value already in the server's own timezone must pass through unchanged. */
    public function test_server_timezone_source_round_trips_unchanged(): void
    {
        $serverTz = date_default_timezone_get();
        $this->assertSame(
            '2022-08-22 12:31:35',
            \parseImportDateTime('2022-08-22 12:31:35', $serverTz)
        );
    }

    /**
     * An export carrying its own offset needs no guessing: PHP ignores the
     * supplied source timezone when the string states one. This is the shape
     * worth asking a source system for.
     */
    public function test_an_explicit_offset_in_the_value_beats_the_source_timezone(): void
    {
        // Deliberately mismatched: the string says -04:00, the argument says Tokyo.
        $stored = \parseImportDateTime('2022-08-22T12:31:35-04:00', 'Asia/Tokyo');

        $this->assertSame(
            (new DateTimeImmutable('2022-08-22T12:31:35-04:00'))->getTimestamp(),
            strtotime($stored),
            ' — the offset in the value must win over the configured source timezone'
        );
    }

    public function test_blank_and_unparseable_values_yield_null(): void
    {
        $this->assertNull(\parseImportDateTime('', 'America/Toronto'));
        $this->assertNull(\parseImportDateTime('   ', 'America/Toronto'));
        $this->assertNull(\parseImportDateTime('not a date at all', 'America/Toronto'));
    }

    /** DST matters: the offset differs by date, so a fixed shift would be wrong. */
    public function test_winter_and_summer_readings_use_their_own_offsets(): void
    {
        foreach (['2022-01-15 09:00:00', '2022-07-15 09:00:00'] as $raw) {
            $this->assertSame(
                $this->instant($raw, 'America/Toronto'),
                strtotime((string) \parseImportDateTime($raw, 'America/Toronto')),
                " — $raw must use the offset in force on its own date"
            );
        }
    }
}

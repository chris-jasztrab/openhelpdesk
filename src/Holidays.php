<?php

/**
 * Holiday calculator for auto-populating federal/provincial/state holidays.
 * Supports: Canada, United States, United Kingdom, Australia, New Zealand, Ireland.
 */
class Holidays
{
    /**
     * Returns an array of ['date' => 'YYYY-MM-DD', 'name' => 'Holiday Name']
     * for the given ISO country code and year.
     */
    public static function getForYear(string $country, int $year): array
    {
        return match (strtoupper($country)) {
            'CA' => self::canada($year),
            'US' => self::usa($year),
            'GB' => self::unitedKingdom($year),
            'AU' => self::australia($year),
            'NZ' => self::newZealand($year),
            'IE' => self::ireland($year),
            default => [],
        };
    }

    /** Supported countries for the UI dropdown. */
    public static function supportedCountries(): array
    {
        return [
            'CA' => 'Canada',
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'IE' => 'Ireland',
        ];
    }

    // -----------------------------------------------------------------------
    // Easter (Meeus/Jones/Butcher algorithm)
    // -----------------------------------------------------------------------

    private static function easter(int $year): DateTime
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;
        return new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    // -----------------------------------------------------------------------
    // Date helpers
    // -----------------------------------------------------------------------

    /** Format a DateTime as Y-m-d string. */
    private static function fmt(DateTime $dt): string
    {
        return $dt->format('Y-m-d');
    }

    /** Fixed calendar date as Y-m-d string. */
    private static function fixed(int $year, int $month, int $day): string
    {
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Nth occurrence of a weekday in a given month/year.
     * $weekday: 1=Mon … 7=Sun (ISO-8601).
     * e.g. nthWeekday(3, 1, 2025, 1) → 3rd Monday of January 2025.
     */
    private static function nthWeekday(int $n, int $weekday, int $year, int $month): DateTime
    {
        $first    = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $firstDow = (int) $first->format('N'); // 1=Mon … 7=Sun
        $diff     = ($weekday - $firstDow + 7) % 7;
        $day      = 1 + $diff + ($n - 1) * 7;
        return new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    /**
     * Last occurrence of a weekday in a given month/year.
     * $weekday: 1=Mon … 7=Sun (ISO-8601).
     */
    private static function lastWeekday(int $weekday, int $year, int $month): DateTime
    {
        $lastDay = (int) (new DateTime(sprintf('%04d-%02d-01', $year, $month)))->format('t');
        $last    = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $lastDay));
        $lastDow = (int) $last->format('N');
        $diff    = ($lastDow - $weekday + 7) % 7;
        $last->modify("-{$diff} days");
        return $last;
    }

    /**
     * Last Monday on or before a given date (used for Victoria Day).
     */
    private static function lastMondayOnOrBefore(int $year, int $month, int $day): DateTime
    {
        $dt = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $day));
        while ((int) $dt->format('N') !== 1) {
            $dt->modify('-1 day');
        }
        return $dt;
    }

    // -----------------------------------------------------------------------
    // Country definitions
    // -----------------------------------------------------------------------

    private static function canada(int $year): array
    {
        $easter      = self::easter($year);
        $goodFriday  = (clone $easter)->modify('-2 days');
        $victoriaDay = self::lastMondayOnOrBefore($year, 5, 24);

        $holidays = [
            ['date' => self::fixed($year, 1, 1),                              'name' => "New Year's Day"],
            ['date' => self::fmt($goodFriday),                                 'name' => 'Good Friday'],
            ['date' => self::fmt($victoriaDay),                                'name' => 'Victoria Day'],
            ['date' => self::fixed($year, 7, 1),                              'name' => 'Canada Day'],
            ['date' => self::fmt(self::nthWeekday(1, 1, $year, 9)),           'name' => 'Labour Day'],
            ['date' => self::fmt(self::nthWeekday(2, 1, $year, 10)),          'name' => 'Thanksgiving Day'],
            ['date' => self::fixed($year, 11, 11),                            'name' => 'Remembrance Day'],
            ['date' => self::fixed($year, 12, 25),                            'name' => 'Christmas Day'],
            ['date' => self::fixed($year, 12, 26),                            'name' => 'Boxing Day'],
        ];

        // National Day for Truth and Reconciliation enacted 2021
        if ($year >= 2021) {
            $holidays[] = ['date' => self::fixed($year, 9, 30), 'name' => 'National Day for Truth and Reconciliation'];
        }

        usort($holidays, fn($a, $b) => strcmp($a['date'], $b['date']));
        return $holidays;
    }

    private static function usa(int $year): array
    {
        $holidays = [
            ['date' => self::fixed($year, 1, 1),                              'name' => "New Year's Day"],
            ['date' => self::fmt(self::nthWeekday(3, 1, $year, 1)),           'name' => 'Martin Luther King Jr. Day'],
            ['date' => self::fmt(self::nthWeekday(3, 1, $year, 2)),           'name' => "Presidents' Day"],
            ['date' => self::fmt(self::lastWeekday(1, $year, 5)),             'name' => 'Memorial Day'],
            ['date' => self::fixed($year, 7, 4),                              'name' => 'Independence Day'],
            ['date' => self::fmt(self::nthWeekday(1, 1, $year, 9)),           'name' => 'Labor Day'],
            ['date' => self::fmt(self::nthWeekday(2, 1, $year, 10)),          'name' => 'Columbus Day'],
            ['date' => self::fixed($year, 11, 11),                            'name' => 'Veterans Day'],
            ['date' => self::fmt(self::nthWeekday(4, 4, $year, 11)),          'name' => 'Thanksgiving Day'],
            ['date' => self::fixed($year, 12, 25),                            'name' => 'Christmas Day'],
        ];

        // Juneteenth enacted 2021
        if ($year >= 2021) {
            $holidays[] = ['date' => self::fixed($year, 6, 19), 'name' => 'Juneteenth National Independence Day'];
        }

        usort($holidays, fn($a, $b) => strcmp($a['date'], $b['date']));
        return $holidays;
    }

    private static function unitedKingdom(int $year): array
    {
        $easter       = self::easter($year);
        $goodFriday   = (clone $easter)->modify('-2 days');
        $easterMonday = (clone $easter)->modify('+1 day');

        return [
            ['date' => self::fixed($year, 1, 1),                              'name' => "New Year's Day"],
            ['date' => self::fmt($goodFriday),                                 'name' => 'Good Friday'],
            ['date' => self::fmt($easterMonday),                               'name' => 'Easter Monday'],
            ['date' => self::fmt(self::nthWeekday(1, 1, $year, 5)),           'name' => 'Early May Bank Holiday'],
            ['date' => self::fmt(self::lastWeekday(1, $year, 5)),             'name' => 'Spring Bank Holiday'],
            ['date' => self::fmt(self::lastWeekday(1, $year, 8)),             'name' => 'Summer Bank Holiday'],
            ['date' => self::fixed($year, 12, 25),                            'name' => 'Christmas Day'],
            ['date' => self::fixed($year, 12, 26),                            'name' => 'Boxing Day'],
        ];
    }

    private static function australia(int $year): array
    {
        $easter         = self::easter($year);
        $goodFriday     = (clone $easter)->modify('-2 days');
        $easterSaturday = (clone $easter)->modify('-1 day');
        $easterMonday   = (clone $easter)->modify('+1 day');

        return [
            ['date' => self::fixed($year, 1, 1),                              'name' => "New Year's Day"],
            ['date' => self::fixed($year, 1, 26),                             'name' => 'Australia Day'],
            ['date' => self::fmt($goodFriday),                                 'name' => 'Good Friday'],
            ['date' => self::fmt($easterSaturday),                             'name' => 'Easter Saturday'],
            ['date' => self::fmt($easter),                                     'name' => 'Easter Sunday'],
            ['date' => self::fmt($easterMonday),                               'name' => 'Easter Monday'],
            ['date' => self::fixed($year, 4, 25),                             'name' => 'ANZAC Day'],
            ['date' => self::fmt(self::nthWeekday(2, 1, $year, 6)),           'name' => "King's Birthday"], // Most states (not QLD/WA)
            ['date' => self::fixed($year, 12, 25),                            'name' => 'Christmas Day'],
            ['date' => self::fixed($year, 12, 26),                            'name' => 'Boxing Day'],
        ];
    }

    private static function newZealand(int $year): array
    {
        $easter       = self::easter($year);
        $goodFriday   = (clone $easter)->modify('-2 days');
        $easterMonday = (clone $easter)->modify('+1 day');

        $holidays = [
            ['date' => self::fixed($year, 1, 1),                              'name' => "New Year's Day"],
            ['date' => self::fixed($year, 1, 2),                              'name' => "Day after New Year's Day"],
            ['date' => self::fixed($year, 2, 6),                              'name' => 'Waitangi Day'],
            ['date' => self::fmt($goodFriday),                                 'name' => 'Good Friday'],
            ['date' => self::fmt($easterMonday),                               'name' => 'Easter Monday'],
            ['date' => self::fixed($year, 4, 25),                             'name' => 'ANZAC Day'],
            ['date' => self::fmt(self::nthWeekday(1, 1, $year, 6)),           'name' => "King's Birthday"],
            ['date' => self::fmt(self::nthWeekday(4, 1, $year, 10)),          'name' => 'Labour Day'],
            ['date' => self::fixed($year, 12, 25),                            'name' => 'Christmas Day'],
            ['date' => self::fixed($year, 12, 26),                            'name' => 'Boxing Day'],
        ];

        // Matariki (official dates published by NZ government; variable Māori lunar calendar)
        $matariki = [
            2022 => '2022-06-24', 2023 => '2023-07-14', 2024 => '2024-06-28',
            2025 => '2025-06-20', 2026 => '2026-07-10', 2027 => '2027-06-25',
            2028 => '2028-07-14', 2029 => '2029-07-06', 2030 => '2030-06-21',
        ];
        if (isset($matariki[$year])) {
            $holidays[] = ['date' => $matariki[$year], 'name' => 'Matariki'];
        }

        usort($holidays, fn($a, $b) => strcmp($a['date'], $b['date']));
        return $holidays;
    }

    private static function ireland(int $year): array
    {
        $easter       = self::easter($year);
        $easterMonday = (clone $easter)->modify('+1 day');

        $holidays = [
            ['date' => self::fixed($year, 1, 1),                              'name' => "New Year's Day"],
            ['date' => self::fixed($year, 3, 17),                             'name' => "St. Patrick's Day"],
            ['date' => self::fmt($easterMonday),                               'name' => 'Easter Monday'],
            ['date' => self::fmt(self::nthWeekday(1, 1, $year, 5)),           'name' => 'May Bank Holiday'],
            ['date' => self::fmt(self::nthWeekday(1, 1, $year, 6)),           'name' => 'June Bank Holiday'],
            ['date' => self::fmt(self::nthWeekday(1, 1, $year, 8)),           'name' => 'August Bank Holiday'],
            ['date' => self::fmt(self::lastWeekday(1, $year, 10)),            'name' => 'October Bank Holiday'],
            ['date' => self::fixed($year, 12, 25),                            'name' => 'Christmas Day'],
            ['date' => self::fixed($year, 12, 26),                            'name' => "St. Stephen's Day"],
        ];

        // St. Brigid's Day enacted 2023 — first Monday of February
        if ($year >= 2023) {
            $holidays[] = ['date' => self::fmt(self::nthWeekday(1, 1, $year, 2)), 'name' => "St. Brigid's Day"];
        }

        usort($holidays, fn($a, $b) => strcmp($a['date'], $b['date']));
        return $holidays;
    }
}

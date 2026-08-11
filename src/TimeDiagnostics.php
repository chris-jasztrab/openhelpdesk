<?php

declare(strict_types=1);

/**
 * Reports every clock the application depends on, and whether they agree.
 *
 * Time is configured in at least four independent places — the operating
 * system, MySQL, PHP's web SAPI and PHP's CLI SAPI — and nothing surfaces a
 * disagreement between them. That silence is the problem: a mismatch does not
 * raise an error, it just writes timestamps that are wrong by a fixed offset.
 * Two shipped bugs (2.168.1, 2.168.2) came from exactly this.
 *
 * Two distinct failure modes are checked, and they are not the same thing:
 *
 *   - **Timezone disagreement** — the clocks name different zones, so a datetime
 *     written by one component is misread by another.
 *   - **Clock drift** — the zones agree but the machines disagree about the
 *     actual instant, which is an NTP problem rather than a config one.
 *
 * Everything here is read-only.
 */
class TimeDiagnostics
{
    /** Drift beyond this many seconds is a warning. */
    private const DRIFT_WARN_SECONDS = 30;

    /** Drift beyond this many seconds is a failure. */
    private const DRIFT_FAIL_SECONDS = 300;

    /**
     * Collect every clock plus the derived checks.
     *
     * @return array{php: array, php_cli: array, mysql: array, os: array, app: array, checks: array, summary: array}
     */
    public static function collect(PDO $db): array
    {
        $php    = self::php();
        $cli    = self::phpCli();
        $mysql  = self::mysql($db);
        $os     = self::operatingSystem();
        $app    = self::appSettings();
        $checks = self::checks($php, $cli, $mysql, $os, $app);

        return [
            'php'     => $php,
            'php_cli' => $cli,
            'mysql'   => $mysql,
            'os'      => $os,
            'app'     => $app,
            'checks'  => $checks,
            'summary' => self::summarise($checks),
        ];
    }

    /** Format a UTC offset in seconds as "+05:30" / "-04:00". */
    public static function formatOffset(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }
        $sign = $seconds < 0 ? '-' : '+';
        $abs  = abs($seconds);
        return sprintf('%s%02d:%02d', $sign, intdiv($abs, 3600), intdiv($abs % 3600, 60));
    }

    /** The web SAPI's view — this is the process rendering the page. */
    private static function php(): array
    {
        $now = new DateTimeImmutable('now');
        return [
            'timezone'   => date_default_timezone_get(),
            'ini_value'  => (string) ini_get('date.timezone'),
            'ini_file'   => (string) (php_ini_loaded_file() ?: ''),
            'sapi'       => PHP_SAPI,
            'now'        => $now->format('Y-m-d H:i:s'),
            'utc'        => $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'offset'     => $now->getOffset(),
            'timestamp'  => $now->getTimestamp(),
            'version'    => PHP_VERSION,
        ];
    }

    /**
     * The CLI SAPI's view, which cron jobs and migrations run under.
     *
     * PHP reads a *different* php.ini per SAPI, so the web server can be pinned
     * correctly while scheduled jobs still write timestamps in another zone.
     * Requires shelling out; degrades to "unavailable" rather than failing.
     */
    private static function phpCli(): array
    {
        $unavailable = static fn (string $why): array
            => ['available' => false, 'reason' => $why, 'timezone' => null, 'offset' => null];

        if (PHP_OS_FAMILY === 'Windows') {
            return $unavailable('Only probed on Unix-like servers.');
        }
        if (!function_exists('shell_exec')) {
            return $unavailable('shell_exec() is not available.');
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('shell_exec', $disabled, true)) {
            return $unavailable('shell_exec() is disabled by php.ini.');
        }

        // `timeout` bounds a wedged interpreter; the probe prints nothing else.
        $raw = @shell_exec("timeout 5 php -r 'echo date_default_timezone_get();' 2>/dev/null");
        $tz  = is_string($raw) ? trim($raw) : '';

        if ($tz === '') {
            return $unavailable('Could not run the PHP CLI binary.');
        }
        try {
            $zone   = new DateTimeZone($tz);
            $offset = (new DateTimeImmutable('now', $zone))->getOffset();
        } catch (Exception $e) {
            return $unavailable('CLI returned an unrecognised timezone: ' . $tz);
        }

        return ['available' => true, 'reason' => '', 'timezone' => $tz, 'offset' => $offset];
    }

    /**
     * MySQL's view.
     *
     * `@@session.time_zone` is commonly the literal 'SYSTEM', in which case the
     * effective zone is `@@system_time_zone`. Rather than untangle the naming,
     * the offset is measured directly from the server's own clocks, which is
     * what actually governs how TIMESTAMP columns are read and written.
     */
    private static function mysql(PDO $db): array
    {
        $row = $db->query(
            'SELECT @@session.time_zone  AS session_tz,
                    @@global.time_zone   AS global_tz,
                    @@system_time_zone   AS system_tz,
                    NOW()                AS now_local,
                    UTC_TIMESTAMP()      AS now_utc,
                    TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS offset_seconds,
                    VERSION()            AS version'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        // CONVERT_TZ returns NULL unless the named-timezone tables are loaded.
        // Without them a zone name like 'America/Toronto' cannot be resolved.
        $tzTables = false;
        try {
            $tzTables = $db->query(
                "SELECT CONVERT_TZ('2024-01-01 12:00:00','UTC','America/Toronto')"
            )->fetchColumn() !== null;
        } catch (PDOException $e) {
            $tzTables = false;
        }

        return [
            'session_tz'    => (string) ($row['session_tz'] ?? ''),
            'global_tz'     => (string) ($row['global_tz'] ?? ''),
            'system_tz'     => (string) ($row['system_tz'] ?? ''),
            'now'           => (string) ($row['now_local'] ?? ''),
            'utc'           => (string) ($row['now_utc'] ?? ''),
            'offset'        => isset($row['offset_seconds']) ? (int) $row['offset_seconds'] : null,
            'version'       => (string) ($row['version'] ?? ''),
            'tz_tables'     => $tzTables,
        ];
    }

    /**
     * The host's own zone, as configured for the OS.
     *
     * Informational: PHP's date.timezone overrides it for the application. It is
     * shown because it is what PHP falls back to when date.timezone is unset,
     * and because it is usually what an admin means by "the server's time".
     */
    private static function operatingSystem(): array
    {
        if (is_readable('/etc/timezone')) {
            $tz = trim((string) @file_get_contents('/etc/timezone'));
            if ($tz !== '') {
                return ['timezone' => $tz, 'source' => '/etc/timezone', 'available' => true];
            }
        }
        if (is_link('/etc/localtime')) {
            $target = (string) @readlink('/etc/localtime');
            if (preg_match('#zoneinfo/(.+)$#', $target, $m)) {
                return ['timezone' => $m[1], 'source' => '/etc/localtime', 'available' => true];
            }
        }
        return ['timezone' => null, 'source' => '', 'available' => false];
    }

    /** The timezones the application itself is configured with. */
    private static function appSettings(): array
    {
        $mode = getSetting('location_timezone_mode', 'shared');
        return [
            'business_hours_timezone' => getSetting('business_hours_timezone'),
            'import_mode'             => $mode,
            'import_source_timezone'  => $mode === 'shared'
                ? getSetting('location_timezone_shared', 'UTC')
                : null,
        ];
    }

    /**
     * Derive pass/fail checks from the collected clocks.
     *
     * Comparisons are made on **current UTC offset**, not on zone names: two
     * different names can describe the same offset, and it is the offset that
     * determines whether a stored timestamp round-trips correctly.
     *
     * @return array<int, array{id: string, label: string, status: string, detail: string, fix: string}>
     */
    private static function checks(array $php, array $cli, array $mysql, array $os, array $app): array
    {
        $checks = [];

        // The invariant the schema depends on. TIMESTAMP columns are written and
        // read in MySQL's zone; everything else is interpreted in PHP's.
        if ($mysql['offset'] === null) {
            $checks[] = self::check('mysql_php_offset', 'MySQL and PHP agree on timezone', 'warn',
                'Could not read the database timezone.', '');
        } elseif ($mysql['offset'] === $php['offset']) {
            $checks[] = self::check('mysql_php_offset', 'MySQL and PHP agree on timezone', 'ok',
                'Both are at UTC' . self::formatOffset($php['offset']) . '.', '');
        } else {
            $delta = ($php['offset'] - $mysql['offset']) / 3600;
            $checks[] = self::check('mysql_php_offset', 'MySQL and PHP agree on timezone', 'fail',
                sprintf(
                    'PHP is at UTC%s but MySQL is at UTC%s — a %s hour difference. Dates written by one are misread by the other, so stored timestamps are wrong by that amount.',
                    self::formatOffset($php['offset']),
                    self::formatOffset($mysql['offset']),
                    rtrim(rtrim(number_format(abs($delta), 1), '0'), '.')
                ),
                "Set MySQL's timezone to match PHP (my.cnf: default-time-zone = '" . $php['timezone'] . "'), or change PHP's date.timezone. They must agree."
            );
        }

        // Same instant, or is a machine's clock actually wrong?
        $drift = null;
        if (($mysql['utc'] ?? '') !== '') {
            $mysqlUtc = strtotime($mysql['utc'] . ' UTC');
            if ($mysqlUtc !== false) {
                $drift = abs($mysqlUtc - $php['timestamp']);
            }
        }
        if ($drift === null) {
            $checks[] = self::check('clock_drift', 'Database and web server clocks are in sync', 'warn',
                'Could not compare the two clocks.', '');
        } elseif ($drift >= self::DRIFT_FAIL_SECONDS) {
            $checks[] = self::check('clock_drift', 'Database and web server clocks are in sync', 'fail',
                "The two clocks are {$drift} seconds apart. This is a clock problem, not a timezone one — the machines disagree about the current instant.",
                'Enable NTP time synchronisation on both hosts (e.g. `timedatectl set-ntp true`).');
        } elseif ($drift >= self::DRIFT_WARN_SECONDS) {
            $checks[] = self::check('clock_drift', 'Database and web server clocks are in sync', 'warn',
                "The two clocks are {$drift} seconds apart.",
                'Enable NTP time synchronisation on both hosts.');
        } else {
            $checks[] = self::check('clock_drift', 'Database and web server clocks are in sync', 'ok',
                "Within {$drift} second(s) of each other.", '');
        }

        // Cron and migrations run under the CLI SAPI, which loads its own php.ini.
        if (!$cli['available']) {
            $checks[] = self::check('php_cli_web', 'Scheduled jobs use the same timezone as the site', 'info',
                'Not checked. ' . $cli['reason'], '');
        } elseif ($cli['offset'] === $php['offset']) {
            $checks[] = self::check('php_cli_web', 'Scheduled jobs use the same timezone as the site', 'ok',
                'Command-line PHP is also ' . $cli['timezone'] . '.', '');
        } else {
            $checks[] = self::check('php_cli_web', 'Scheduled jobs use the same timezone as the site', 'fail',
                sprintf(
                    'The website runs as %s (UTC%s) but command-line PHP runs as %s (UTC%s). Cron jobs and database migrations use the command-line setting, so they write timestamps in a different zone than the site reads them in.',
                    $php['timezone'], self::formatOffset($php['offset']),
                    $cli['timezone'], self::formatOffset($cli['offset'])
                ),
                'Set the same date.timezone in BOTH php.ini files — the web SAPI (apache2/fpm) and the cli one.');
        }

        // Named zones need the timezone tables; without them CONVERT_TZ is NULL.
        $checks[] = $mysql['tz_tables']
            ? self::check('mysql_tz_tables', 'MySQL knows named timezones', 'ok',
                'The timezone tables are loaded.', '')
            : self::check('mysql_tz_tables', 'MySQL knows named timezones', 'warn',
                'The timezone tables are not loaded, so MySQL cannot resolve names like "America/Toronto" (CONVERT_TZ returns nothing).',
                'Load them once: mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql');

        // Informational: PHP's setting wins, but an unset date.timezone is worth flagging.
        if ($php['ini_value'] === '') {
            $checks[] = self::check('php_ini_pinned', 'PHP has an explicit timezone', 'warn',
                'date.timezone is not set in php.ini, so PHP falls back to a default that can change with the operating system.',
                'Pin date.timezone explicitly in php.ini (both the web and cli files).');
        } else {
            $checks[] = self::check('php_ini_pinned', 'PHP has an explicit timezone', 'ok',
                'date.timezone is pinned to ' . $php['ini_value'] . '.', '');
        }

        if ($os['available'] && $os['timezone'] !== $php['timezone']) {
            $checks[] = self::check('os_php', 'Operating system zone matches PHP', 'info',
                sprintf('The server is set to %s while PHP uses %s. PHP\'s setting is what the application follows, so this is not itself a fault — but it is worth knowing they differ.',
                    $os['timezone'], $php['timezone']),
                '');
        }

        // Safe to differ since 2.168.1 — say so, so nobody "fixes" it needlessly.
        $biz = $app['business_hours_timezone'];
        if ($biz !== '' && $biz !== $php['timezone']) {
            $checks[] = self::check('business_hours_tz', 'Business Hours timezone', 'info',
                sprintf('Business Hours is set to %s while the server runs as %s. This is supported: the Business Hours zone decides which hours SLA timers count, and due dates are stored in server time regardless.',
                    $biz, $php['timezone']),
                '');
        }

        return $checks;
    }

    /** @return array{id: string, label: string, status: string, detail: string, fix: string} */
    private static function check(string $id, string $label, string $status, string $detail, string $fix): array
    {
        return ['id' => $id, 'label' => $label, 'status' => $status, 'detail' => $detail, 'fix' => $fix];
    }

    /** @return array{status: string, failures: int, warnings: int, headline: string} */
    private static function summarise(array $checks): array
    {
        $failures = count(array_filter($checks, fn ($c) => $c['status'] === 'fail'));
        $warnings = count(array_filter($checks, fn ($c) => $c['status'] === 'warn'));

        if ($failures > 0) {
            return [
                'status'   => 'fail',
                'failures' => $failures,
                'warnings' => $warnings,
                'headline' => $failures === 1
                    ? 'One clock is misconfigured'
                    : "{$failures} clocks are misconfigured",
            ];
        }
        if ($warnings > 0) {
            return [
                'status'   => 'warn',
                'failures' => 0,
                'warnings' => $warnings,
                'headline' => $warnings === 1
                    ? 'One thing worth checking'
                    : "{$warnings} things worth checking",
            ];
        }
        return [
            'status'   => 'ok',
            'failures' => 0,
            'warnings' => 0,
            'headline' => 'All clocks agree',
        ];
    }
}

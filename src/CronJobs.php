<?php

declare(strict_types=1);

require_once __DIR__ . '/CronRun.php'; // for EXIT_ALREADY_RUNNING; boot() is a no-op off the CLI

/**
 * The scheduled-job registry, plus the machinery behind the Dry run / Run now
 * buttons on Admin → Settings → Cron Jobs.
 *
 * This used to live inline in templates/pages/admin/settings/cron-jobs.php. It
 * moved here because the run endpoint has to resolve a job by key and know its
 * script path, and two copies of that list would drift.
 *
 * A manual run spawns the same PHP CLI process cron would, with the same
 * arguments (plus --dry-run when previewing). It deliberately does NOT include
 * the script in-process: the scripts define ROOT_DIR, call exit(), and write to
 * stdout, so including one would kill the web request. Shelling out also means a
 * manual run and a scheduled run are the same code path, which is the point.
 */
final class CronJobs
{
    /** Hard cap on captured output, so a runaway job can't exhaust memory. */
    private const MAX_OUTPUT_BYTES = 256 * 1024;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $registry = null;

    /* ───────────────────────── Registry ───────────────────────── */

    /**
     * Every job, keyed by slug. Keys are the contract with the run endpoint —
     * they are whitelist values, so never build one from request input.
     *
     * Per-job fields that drive the runner (as opposed to the display):
     *   script      Path relative to ROOT_DIR — the thing that actually runs.
     *   log_name    Filename under storage/logs/.
     *   sends_mail  True if a live run can deliver email to real people. Drives
     *               the confirmation wording; dry runs never send regardless.
     *   self_logs   True if the script appends its own output to log_name. The
     *               three that do also suppress that append on a dry run, so a
     *               preview never disturbs the status badge.
     *   timeout     Seconds before the child is killed. Sized to the job: the
     *               two that make per-item Graph/AI calls get the longest.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$registry !== null) {
            return self::$registry;
        }

        // Detected platform — drives the default-selected tab on the settings
        // page. Both platforms' commands are always rendered; the tab only
        // controls which one is visible.
        $isWindows = self::isWindows();

        // When we know the install lives on this OS, emit its real absolute
        // paths (copy-and-paste ready). For the *other* platform's tab, fall
        // back to a conventional placeholder so the command is at least shaped
        // correctly — the user replaces the path with their own.
        $linuxRoot   = $isWindows ? '/var/www/openhelpdesk' : rtrim(ROOT_DIR, '/');
        $windowsRoot = $isWindows ? str_replace('/', '\\', rtrim(ROOT_DIR, '/')) : 'C:\\xampp\\htdocs\\freshwpl';
        $windowsPhp  = $isWindows ? str_replace('/', '\\', PHP_BINARY) : 'C:\\xampp\\php\\php.exe';

        // Linux cron line: `*/5 * * * * php /path/script.php >> /path/log 2>&1`
        $buildLinux = static function (string $cron, string $scriptRel, string $logName) use ($linuxRoot): string {
            return $cron . ' php ' . $linuxRoot . '/' . $scriptRel
                 . ' >> ' . $linuxRoot . '/storage/logs/' . $logName . ' 2>&1';
        };

        // Windows schtasks command. The `/TR "'php' 'script'"` pattern (single
        // quotes inside double quotes) is schtasks' supported way to embed two
        // space-containing paths in one /TR value, and it parses identically in
        // cmd.exe and PowerShell. /F overwrites a task with the same name.
        $buildWindows = static function (string $schedArgs, string $scriptRel, string $taskName)
            use ($windowsRoot, $windowsPhp): string {
            return sprintf(
                "schtasks /Create /TN \"%s\" /TR \"'%s' '%s'\" %s /F",
                $taskName,
                $windowsPhp,
                $windowsRoot . '\\' . str_replace('/', '\\', $scriptRel),
                $schedArgs
            );
        };

        $jobs = [
            'sla' => [
                'title'            => 'SLA Recalculation',
                'icon'             => 'bi-stopwatch',
                'description'      => 'Recalculates SLA status (breached / at-risk) for all active tickets. Should run frequently so SLA breaches are detected promptly.',
                'frequency'        => 'Every 5 minutes',
                'interval_seconds' => 300,
                'script'           => 'public/sla-cron.php',
                'log_name'         => 'sla-cron.log',
                'sends_mail'       => false,
                'self_logs'        => false,
                'timeout'          => 120,
                'cron_linux'       => $buildLinux('*/5 * * * *', 'public/sla-cron.php', 'sla-cron.log'),
                'cron_windows'     => $buildWindows('/SC MINUTE /MO 5', 'public/sla-cron.php', 'OpenHelpDesk SLA Recalculation'),
                'required'         => true,
                'note'             => 'Can also be triggered via HTTP: <code>GET /sla-cron.php?token=YOUR_SECRET_TOKEN</code>. Set <code>SLA_CRON_TOKEN</code> in your <code>.env</code> file when using HTTP mode.',
            ],
            'replies' => [
                'title'            => 'Inbound Email Replies',
                'icon'             => 'bi-envelope-arrow-down',
                'description'      => 'Polls the configured Microsoft 365 mailbox via the Graph API for new replies and appends them to the matching ticket timeline.',
                'frequency'        => 'Every 5 minutes',
                'interval_seconds' => 300,
                'script'           => 'scripts/process-replies.php',
                'log_name'         => 'graph-mail.log',
                'sends_mail'       => true,
                'self_logs'        => false,
                'timeout'          => 240,
                'cron_linux'       => $buildLinux('*/5 * * * *', 'scripts/process-replies.php', 'graph-mail.log'),
                'cron_windows'     => $buildWindows('/SC MINUTE /MO 5', 'scripts/process-replies.php', 'OpenHelpDesk Inbound Email'),
                'required'         => false,
                'note'             => 'Only required if you have Microsoft Graph / inbound email configured in Admin → Settings → Email / SMTP. A dry run reads the mailbox but does not mark anything as read, so the next real run still sees every reply.',
            ],
            'escalations' => [
                'title'            => 'Escalation Rules',
                'icon'             => 'bi-alarm',
                'description'      => 'Evaluates all enabled escalation rules against open tickets and fires any configured actions (reassign, notify, change priority, etc.).',
                'frequency'        => 'Every 15 minutes',
                'interval_seconds' => 900,
                'script'           => 'scripts/process-escalations.php',
                'log_name'         => 'escalations.log',
                'sends_mail'       => true,
                'self_logs'        => true,
                'timeout'          => 180,
                'cron_linux'       => $buildLinux('*/15 * * * *', 'scripts/process-escalations.php', 'escalations.log'),
                'cron_windows'     => $buildWindows('/SC MINUTE /MO 15', 'scripts/process-escalations.php', 'OpenHelpDesk Escalations'),
                'required'         => false,
                'note'             => 'Only required if you have escalation rules configured in Admin → Settings → Escalations.',
            ],
            'scheduled-reports' => [
                'title'            => 'Scheduled Reports',
                'icon'             => 'bi-envelope-paper',
                'description'      => 'Checks for any scheduled reports that are due and emails summaries to the configured recipients.',
                'frequency'        => 'Every 30 minutes',
                'interval_seconds' => 1800,
                'script'           => 'scripts/process-scheduled-reports.php',
                'log_name'         => 'scheduled-reports.log',
                'sends_mail'       => true,
                'self_logs'        => true,
                'timeout'          => 240,
                'cron_linux'       => $buildLinux('*/30 * * * *', 'scripts/process-scheduled-reports.php', 'scheduled-reports.log'),
                'cron_windows'     => $buildWindows('/SC MINUTE /MO 30', 'scripts/process-scheduled-reports.php', 'OpenHelpDesk Scheduled Reports'),
                'required'         => false,
                'note'             => 'Only required if you have scheduled reports configured in Admin → Reports → Scheduled Reports.',
            ],
            'recurring-tickets' => [
                'title'            => 'Recurring / Preventive-Maintenance Tickets',
                'icon'             => 'bi-arrow-repeat',
                'description'      => 'Mints tickets from active recurring schedules whose next_run_at has passed (e.g. monthly toner audit, quarterly HVAC, annual fire inspection), then advances each schedule to its next firing slot.',
                'frequency'        => 'Every 15 minutes',
                'interval_seconds' => 900,
                'script'           => 'scripts/process-recurring-tickets.php',
                'log_name'         => 'recurring-tickets.log',
                'sends_mail'       => true,
                'self_logs'        => false,
                'timeout'          => 180,
                'cron_linux'       => $buildLinux('*/15 * * * *', 'scripts/process-recurring-tickets.php', 'recurring-tickets.log'),
                'cron_windows'     => $buildWindows('/SC MINUTE /MO 15', 'scripts/process-recurring-tickets.php', 'OpenHelpDesk Recurring Tickets'),
                'required'         => false,
                'note'             => 'Only required if you have recurring schedules configured in Admin → Recurring Tickets. Missed-tick safe — does not back-fill if cron pauses.',
            ],
            'stale-tickets' => [
                'title'            => 'Stale Ticket Notifications',
                'icon'             => 'bi-hourglass-split',
                'description'      => 'Finds active tickets that have had no activity for longer than the configured stale threshold and emails both the assigned agent and the requester. Skips resolved, closed, and waiting-on-customer/third-party statuses.',
                'frequency'        => 'Every hour',
                'interval_seconds' => 3600,
                'script'           => 'scripts/process-stale-tickets.php',
                'log_name'         => 'stale-tickets.log',
                'sends_mail'       => true,
                'self_logs'        => true,
                'timeout'          => 240,
                'cron_linux'       => $buildLinux('0 * * * *', 'scripts/process-stale-tickets.php', 'stale-tickets.log'),
                'cron_windows'     => $buildWindows('/SC HOURLY', 'scripts/process-stale-tickets.php', 'OpenHelpDesk Stale Tickets'),
                'required'         => false,
                'note'             => 'Configure the threshold and per-type overrides in Admin → Settings → Stale Tickets. Dry-run this one before its first live run — on a large backlog it can notify a lot of people at once.',
            ],
            'oof-coverage' => [
                'title'            => 'Out-of-Office Coverage',
                'icon'             => 'bi-person-x',
                'description'      => 'Reads each group member\'s Outlook out-of-office state via Microsoft Graph, then reassigns away agents\' unanswered tickets to an available colleague — or auto-replies the requester when there is nobody to reassign to (single-person groups).',
                'frequency'        => 'Every 15 minutes',
                'interval_seconds' => 900,
                'script'           => 'scripts/process-oof-coverage.php',
                'log_name'         => 'oof-coverage.log',
                'sends_mail'       => true,
                'self_logs'        => false,
                'timeout'          => 240,
                'cron_linux'       => $buildLinux('*/15 * * * *', 'scripts/process-oof-coverage.php', 'oof-coverage.log'),
                'cron_windows'     => $buildWindows('/SC MINUTE /MO 15', 'scripts/process-oof-coverage.php', 'OpenHelpDesk OOF Coverage'),
                'required'         => false,
                'note'             => 'Only required if you have enabled Out-of-Office coverage in Admin → Settings → Out of Office. Requires the <code>MailboxSettings.Read</code> Graph permission.',
            ],
            'secret-reminders' => [
                'title'            => 'App Secret Expiry Reminders',
                'icon'             => 'bi-key',
                'description'      => 'Sends email reminders to all administrators when the Microsoft Graph app secret is approaching its expiry date. Reminds at 30 days, 7 days, and on the day of expiry.',
                'frequency'        => 'Once daily',
                'interval_seconds' => 86400,
                'script'           => 'scripts/process-secret-reminders.php',
                'log_name'         => 'secret-reminders.log',
                'sends_mail'       => true,
                'self_logs'        => false,
                'timeout'          => 120,
                'cron_linux'       => $buildLinux('0 8 * * *', 'scripts/process-secret-reminders.php', 'secret-reminders.log'),
                'cron_windows'     => $buildWindows('/SC DAILY /ST 08:00', 'scripts/process-secret-reminders.php', 'OpenHelpDesk Secret Reminders'),
                'required'         => false,
                'note'             => 'Only required if you have a Microsoft Graph app secret expiry date configured in Admin → Settings → Email / SMTP.',
            ],
        ];

        // Derive display paths from log_name so there's one source of truth.
        foreach ($jobs as $key => $job) {
            $jobs[$key]['key']         = $key;
            $jobs[$key]['log_linux']   = $linuxRoot . '/storage/logs/' . $job['log_name'];
            $jobs[$key]['log_windows'] = $windowsRoot . '\\storage\\logs\\' . str_replace('/', '\\', $job['log_name']);
        }

        return self::$registry = $jobs;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /* ───────────────────────── Status badge ───────────────────────── */

    /**
     * Health of a job, inferred from its log file's modified time. Only
     * meaningful for THIS server's log — we can't know a remote machine's log
     * state if a Linux admin is viewing the Windows tab.
     *
     * @param array<string, mixed> $job
     * @return array{status: string, age: int|null, mtime: int|null}
     */
    public static function status(array $job): array
    {
        $path = ROOT_DIR . '/storage/logs/' . $job['log_name'];
        if (!is_file($path)) {
            return ['status' => 'missing', 'age' => null, 'mtime' => null];
        }

        $mtime = @filemtime($path);
        if ($mtime === false) {
            return ['status' => 'missing', 'age' => null, 'mtime' => null];
        }

        $age       = max(0, time() - $mtime);
        $threshold = (int) (($job['interval_seconds'] ?? 3600) * 2);

        return [
            'status' => $age <= $threshold ? 'ok' : 'stale',
            'age'    => $age,
            'mtime'  => $mtime,
        ];
    }

    /* ───────────────────────── Runner ───────────────────────── */

    /**
     * Locate a PHP *CLI* binary we can spawn.
     *
     * PHP_BINARY is only the CLI binary when we already are the CLI — under
     * Apache/mod_php it is the httpd binary and under php-fpm it is php-fpm,
     * neither of which can run a script. So: an explicit PHP_CLI_BINARY from
     * .env wins, then the usual siblings of PHP_BINDIR, then conventional
     * locations. Returns null when nothing usable is found.
     */
    public static function phpBinary(): ?string
    {
        $explicit = trim((string) env('PHP_CLI_BINARY', ''));
        if ($explicit !== '') {
            // Configured but broken is a configuration error, not a cue to go
            // guessing — report it rather than silently running a different PHP.
            return self::isRunnableBinary($explicit) ? $explicit : null;
        }

        if (PHP_SAPI === 'cli' && PHP_BINARY !== '' && self::isRunnableBinary(PHP_BINARY)) {
            return PHP_BINARY;
        }

        $candidates = self::isWindows()
            ? [PHP_BINDIR . '\\php.exe', 'C:\\xampp\\php\\php.exe']
            : [PHP_BINDIR . '/php', '/usr/bin/php', '/usr/local/bin/php'];

        foreach ($candidates as $candidate) {
            if (self::isRunnableBinary($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Can this server run a job at all? Checked before every run and used to
     * render the buttons disabled-with-a-reason, so a hosting restriction shows
     * up as an explanation instead of a mystery failure.
     *
     * @return array{ok: bool, reason: string, binary: string|null}
     */
    public static function runnability(): array
    {
        if (!function_exists('proc_open')) {
            return ['ok' => false, 'binary' => null, 'reason' => 'PHP\'s proc_open() is unavailable on this server, so the web process cannot start a background script.'];
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('proc_open', $disabled, true)) {
            return ['ok' => false, 'binary' => null, 'reason' => 'proc_open() is listed in this server\'s php.ini disable_functions, so the web process cannot start a background script.'];
        }

        $binary = self::phpBinary();
        if ($binary === null) {
            $explicit = trim((string) env('PHP_CLI_BINARY', ''));
            return [
                'ok'     => false,
                'binary' => null,
                'reason' => $explicit !== ''
                    ? 'PHP_CLI_BINARY in your .env points at "' . $explicit . '", which is not an executable file.'
                    : 'No PHP command-line binary could be found. Set PHP_CLI_BINARY in your .env to its full path (e.g. /usr/bin/php).',
            ];
        }

        return ['ok' => true, 'binary' => $binary, 'reason' => ''];
    }

    /**
     * Spawn a job and wait for it, capturing output.
     *
     * Synchronous on purpose: these jobs finish in seconds under normal load, and
     * a blocking request that streams the result back into a modal is far less
     * machinery than a background spawn plus a log-tail poller. The per-job
     * timeout is the backstop — if a job legitimately needs longer than its
     * budget, raise `timeout` in the registry rather than reaching for async.
     *
     * @param array<string, mixed> $job   A registry entry (never request input).
     * @param bool                 $live  False = --dry-run.
     * @param string               $actor Who pressed the button, for the audit log.
     * @return array{success: bool, exit_code: int, output: string, duration: float, timed_out: bool, already_running: bool, truncated: bool}
     */
    public static function run(array $job, bool $live, string $actor): array
    {
        $binary = self::phpBinary();
        if ($binary === null) {
            throw new RuntimeException('No PHP CLI binary available.');
        }

        $command = [$binary, ROOT_DIR . '/' . $job['script']];
        if (!$live) {
            $command[] = '--dry-run';
        }

        $started  = microtime(true);
        $timeout  = (int) ($job['timeout'] ?? 120);
        $timedOut = false;

        // Capture output to a temp file rather than a pipe. A pipe would be the
        // obvious choice, but its OS buffer is small (~4 KB on Windows) and a
        // child that fills it blocks on write until the parent drains it — and
        // stream_select(), the portable way to know when to drain, does not work
        // on pipes on Windows. That combination deadlocks: the child waits to
        // write, the parent waits to be told there's something to read. A file
        // has no such buffer, so the child runs to completion untouched and we
        // read the result afterwards. This is also what the crontab lines do
        // (`>> log 2>&1`), which keeps the two paths alike.
        $capture = tempnam(sys_get_temp_dir(), 'ohdcron');
        if ($capture === false) {
            throw new RuntimeException('Could not create a temporary file to capture output.');
        }

        // Array form of proc_open: the args are passed to the OS directly, so
        // there is no shell and nothing to quote or escape.
        $process = proc_open(
            $command,
            [
                0 => ['file', self::nullDevice(), 'r'], // no input; immediate EOF
                1 => ['file', $capture, 'w'],
                2 => ['file', $capture, 'a'],           // stderr into the same file
            ],
            $pipes,
            ROOT_DIR
        );

        if (!is_resource($process)) {
            @unlink($capture);
            throw new RuntimeException('Could not start ' . $job['script'] . '.');
        }

        // proc_close() can't report the exit code once proc_get_status() has
        // reaped it, so take the code from the status call that sees it exit.
        $exitCode = -1;
        $deadline = $started + $timeout;

        while (true) {
            $status = proc_get_status($process);
            if ($status['running'] === false) {
                $exitCode = (int) $status['exitcode'];
                break;
            }
            if (microtime(true) >= $deadline) {
                $timedOut = true;
                proc_terminate($process, 9);
                break;
            }
            usleep(100000);
        }

        proc_close($process);
        $duration = round(microtime(true) - $started, 2);

        $output    = (string) @file_get_contents($capture, false, null, 0, self::MAX_OUTPUT_BYTES + 1);
        $truncated = strlen($output) > self::MAX_OUTPUT_BYTES;
        if ($truncated) {
            $output = substr($output, 0, self::MAX_OUTPUT_BYTES);
        }
        @unlink($capture);

        $alreadyRunning = ($exitCode === CronRun::EXIT_ALREADY_RUNNING);
        $success        = !$timedOut && $exitCode === 0;

        self::writeAuditLine($job, $live, $actor, $exitCode, $duration, $timedOut);

        if ($live) {
            self::appendToJobLog($job, $actor, $exitCode, $duration, $timedOut, $output);
        }

        return [
            'success'         => $success,
            'exit_code'       => $exitCode,
            'output'          => $output,
            'duration'        => $duration,
            'timed_out'       => $timedOut,
            'already_running' => $alreadyRunning,
            'truncated'       => $truncated,
        ];
    }

    /**
     * Audit trail for every manual run, dry or live, in its own file so it never
     * affects the status badges. This is the record of who pressed what.
     *
     * @param array<string, mixed> $job
     */
    private static function writeAuditLine(
        array $job,
        bool $live,
        string $actor,
        int $exitCode,
        float $duration,
        bool $timedOut
    ): void {
        $line = sprintf(
            "[%s] job=%s mode=%s actor=%s exit=%d duration=%.2fs%s\n",
            date('Y-m-d H:i:s'),
            $job['key'],
            $live ? 'live' : 'dry-run',
            $actor,
            $exitCode,
            $duration,
            $timedOut ? ' TIMED-OUT' : ''
        );

        self::appendLog('cron-manual-runs.log', $line);
    }

    /**
     * Mark a live manual run in the job's own log, so the run is visible to
     * anyone reading the log later and the "Last run" badge updates. Dry runs
     * never reach here.
     *
     * Output is included only for the jobs that don't append it themselves —
     * cron redirects their stdout to the log, but a manual run has no shell to
     * do that, so without this their output would be lost.
     *
     * @param array<string, mixed> $job
     */
    private static function appendToJobLog(
        array $job,
        string $actor,
        int $exitCode,
        float $duration,
        bool $timedOut,
        string $output
    ): void {
        $block = sprintf(
            "[%s] ──── MANUAL RUN by %s — exit %d, %.2fs%s ────\n",
            date('Y-m-d H:i:s'),
            $actor,
            $exitCode,
            $duration,
            $timedOut ? ', TIMED OUT and was killed' : ''
        );

        if (empty($job['self_logs']) && trim($output) !== '') {
            $block .= rtrim($output) . "\n";
        }

        self::appendLog($job['log_name'], $block);
    }

    private static function appendLog(string $name, string $contents): void
    {
        $dir = ROOT_DIR . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/' . $name, $contents, FILE_APPEND | LOCK_EX);
    }

    private static function nullDevice(): string
    {
        return self::isWindows() ? 'NUL' : '/dev/null';
    }

    private static function isRunnableBinary(string $path): bool
    {
        return $path !== '' && @is_file($path) && @is_executable($path);
    }

    private static function isWindows(): bool
    {
        return stripos(PHP_OS, 'WIN') === 0;
    }
}

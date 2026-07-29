<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/**
 * Shared preamble for the scheduled background scripts (scripts/process-*.php
 * and public/sla-cron.php).
 *
 * Every one of those scripts calls CronRun::boot($argv ?? []) right after
 * loadEnv() and before Database::connect(). That buys two things:
 *
 *  1. --dry-run. The script runs its real code path end to end but nothing
 *     escapes the process: DB writes happen inside a transaction that is never
 *     committed (see DryRunPdo), sendMail() reports what it *would* have sent
 *     instead of sending it, and remote side effects (marking a Graph mailbox
 *     message read) are skipped. This is what the "Dry run" button on
 *     Admin → Settings → Cron Jobs uses, so an admin can see what a job would
 *     do before letting it loose on real recipients.
 *
 *  2. One instance at a time. Each script takes a non-blocking lock on its own
 *     name and exits 75 (EX_TEMPFAIL) if a copy is already running. Without it,
 *     a manual run fired while the scheduled run is mid-flight could double-send
 *     — both passes read the dedup table before either writes to it. It also
 *     fixes the pre-existing case of a slow job overlapping its own next tick.
 *
 * Non-CLI callers are ignored entirely: public/sla-cron.php can be triggered
 * over HTTP with a token, and that path keeps its previous behaviour (no dry
 * run, no lock).
 */
final class CronRun
{
    /** Exit code when another copy of this script already holds the lock. */
    public const EXIT_ALREADY_RUNNING = 75; // EX_TEMPFAIL

    private static bool $booted = false;
    private static bool $dryRun = false;

    /** Kept in a static so the lock isn't released by the handle going out of scope. */
    private static $lockHandle = null;

    /** @var array<int, array{to: string, subject: string}> */
    private static array $suppressedMail = [];

    /**
     * @param array<int, string> $argv The script's $argv (pass `$argv ?? []`).
     */
    public static function boot(array $argv = []): void
    {
        if (self::$booted || PHP_SAPI !== 'cli') {
            return;
        }
        self::$booted = true;

        self::$dryRun = in_array('--dry-run', $argv, true);

        self::acquireLock(basename($argv[0] ?? 'cron-job'));

        if (self::$dryRun) {
            Database::useDryRun();
            self::emit('=== DRY RUN — no database changes will be saved, no email will be sent. ===');
            register_shutdown_function([self::class, 'finish']);
        }
    }

    public static function isDryRun(): bool
    {
        return self::$dryRun;
    }

    /**
     * Called by sendMail() instead of delivering. Logged to stdout so it lands
     * in the run output the admin is looking at.
     */
    public static function noteSuppressedMail(string $toEmail, string $subject): void
    {
        self::$suppressedMail[] = ['to' => $toEmail, 'subject' => $subject];
        self::emit('[DRY RUN] WOULD EMAIL ' . $toEmail . ' — "' . $subject . '"');
    }

    /** Log a side effect that was skipped because it can't be rolled back. */
    public static function noteSkipped(string $what): void
    {
        self::emit('[DRY RUN] SKIPPED (not reversible): ' . $what);
    }

    /**
     * Shutdown handler for a dry run: discard the transaction and summarise.
     * Registered only in dry-run mode, and reached even via exit().
     */
    public static function finish(): void
    {
        Database::rollBackDryRun();

        $mailCount = count(self::$suppressedMail);
        self::emit(sprintf(
            '=== DRY RUN COMPLETE — all database changes discarded; %d email(s) suppressed. ===',
            $mailCount
        ));
    }

    /**
     * Take an exclusive, non-blocking lock keyed on the script name. Exits the
     * process (75) rather than returning if another copy holds it.
     */
    private static function acquireLock(string $scriptName): void
    {
        $lockDir = ROOT_DIR . '/storage/locks';
        if (!is_dir($lockDir) && !@mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
            // Can't create the lock directory (read-only deploy, bad perms).
            // Locking is a safety net, not the job — carry on unlocked rather
            // than refusing to run at all.
            self::emit('WARN: could not create ' . $lockDir . ' — running without an instance lock.');
            return;
        }

        $lockFile = $lockDir . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $scriptName) . '.lock';
        $handle   = @fopen($lockFile, 'c');
        if ($handle === false) {
            self::emit('WARN: could not open ' . $lockFile . ' — running without an instance lock.');
            return;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            self::emit('Another instance of ' . $scriptName . ' is already running — exiting.');
            exit(self::EXIT_ALREADY_RUNNING);
        }

        self::$lockHandle = $handle; // held until the process ends
    }

    private static function emit(string $line): void
    {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL;
    }
}

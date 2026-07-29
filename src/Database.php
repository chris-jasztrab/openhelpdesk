<?php

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    /** Set by CronRun::boot() on a --dry-run. Must precede the first connect(). */
    private static bool $dryRun = false;

    /**
     * Open the connection in throw-everything-away mode: writes are wrapped in
     * a transaction that never commits. Called by CronRun::boot() before any
     * script touches the database. See DryRunPdo for the mechanics.
     */
    public static function useDryRun(): void
    {
        self::$dryRun = true;
    }

    public static function connect(): PDO
    {
        if (self::$instance === null) {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $name = env('DB_NAME', 'localdesk');
            $user = env('DB_USER', 'root');
            $pass = env('DB_PASS', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Use real server-side prepared statements (not client-side
                // emulation) so parameter binding is enforced by the driver.
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            if (self::$dryRun) {
                require_once __DIR__ . '/DryRunPdo.php';
                $pdo = new DryRunPdo($dsn, $user, $pass, $options);
                $pdo->realBeginTransaction();
                self::$instance = $pdo;
            } else {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            }
        }

        return self::$instance;
    }

    /**
     * Discard everything a dry run wrote. Called from CronRun's shutdown
     * handler; MySQL would also roll back on disconnect, but doing it
     * explicitly releases the row locks a beat sooner and is greppable.
     */
    public static function rollBackDryRun(): void
    {
        if (self::$instance instanceof DryRunPdo) {
            self::$instance->realRollBack();
        }
    }
}

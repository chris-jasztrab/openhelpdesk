<?php

declare(strict_types=1);

/**
 * A PDO handle that can never persist anything.
 *
 * How it works: Database::connect() opens this instead of a plain PDO when the
 * process is a dry run, and immediately calls realBeginTransaction(). Every
 * write the script performs happens for real *inside* that transaction — so
 * lastInsertId() works, dedup SELECTs see the rows just written, and the script
 * takes exactly the code path it would on a live run — but the transaction is
 * never committed, so the moment the process ends MySQL throws all of it away.
 *
 * The three transaction methods are neutered rather than removed:
 *
 *   beginTransaction() — a no-op. Helpers that open their own transaction
 *                        (helpers.php reorder, ticket-create paths) would
 *                        otherwise throw "There is already an active
 *                        transaction" against our outer one.
 *   commit()          — a no-op. This is the whole point: an inner commit must
 *                        not make anything durable.
 *   rollBack()        — a no-op, so a helper's error path can't tear down the
 *                        outer transaction and let subsequent writes commit.
 *
 * Consequence worth knowing: because inner rollBack() does nothing, writes from
 * a *failed* inner transaction stay visible for the rest of the dry run, where a
 * live run would have discarded them. Nothing persists either way, so the only
 * effect is that a dry run's log can over-report on a path that errors midway.
 *
 * Also note the run holds row locks on everything it touches until it exits.
 * Dry-running a slow job on a busy server can briefly block writers on those
 * same rows, so these are meant to be short.
 */
final class DryRunPdo extends PDO
{
    public function realBeginTransaction(): bool
    {
        return parent::beginTransaction();
    }

    public function realRollBack(): bool
    {
        return parent::inTransaction() ? parent::rollBack() : true;
    }

    #[\ReturnTypeWillChange]
    public function beginTransaction(): bool
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function commit(): bool
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function rollBack(): bool
    {
        return true;
    }
}

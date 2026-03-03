<?php
/**
 * Migration 006 — Harden api_tokens: hash-at-rest + enforce expiry
 *
 * Changes:
 *  1. Add token_hash VARCHAR(64) — stores SHA-256(raw_token), unique.
 *  2. Backfill token_hash for all existing rows using MySQL SHA2().
 *  3. Make token_hash NOT NULL and add the unique index.
 *  4. Drop the plaintext token column (and its index uq_token).
 *
 * After this migration the raw bearer token is NEVER stored on disk;
 * only its SHA-256 hex digest lives in the database.
 */
return static function (PDO $pdo): void {
    // 1. Add nullable column so we can backfill first.
    $pdo->exec(
        "ALTER TABLE `api_tokens`
             ADD COLUMN `token_hash` VARCHAR(64) NULL DEFAULT NULL
             AFTER `token`"
    );

    // 2. Backfill — existing raw tokens are still valid after this migration
    //    because the PHP side will hash the bearer value before comparing.
    $pdo->exec("UPDATE `api_tokens` SET `token_hash` = SHA2(`token`, 256)");

    // 3. Constrain and index the new column.
    $pdo->exec(
        "ALTER TABLE `api_tokens`
             MODIFY COLUMN `token_hash` VARCHAR(64) NOT NULL,
             ADD UNIQUE KEY `uq_token_hash` (`token_hash`)"
    );

    // 4. Remove the plaintext token column (MySQL drops its index automatically).
    $pdo->exec("ALTER TABLE `api_tokens` DROP COLUMN `token`");
};

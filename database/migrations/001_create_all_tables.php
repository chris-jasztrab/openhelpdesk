<?php
/**
 * Migration 001 — Create all tables
 *
 * Applies the full current schema with FK checks disabled so table-creation
 * order doesn't matter. Every statement is CREATE TABLE IF NOT EXISTS, making
 * this safe to run against any existing database — it will only create tables
 * that are actually missing.
 */
return static function (PDO $pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec(file_get_contents(ROOT_DIR . '/database/schema.sql'));
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

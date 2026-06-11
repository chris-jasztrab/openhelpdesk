<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Creates (and later removes) the fixtures needed by the test suite.
 *
 * All test records are identified by emails ending in @test.local and
 * names/subjects prefixed with [TEST].  The seeder is idempotent — running it
 * twice will not duplicate records.
 */
final class DatabaseSeeder
{
    // ── Credentials ────────────────────────────────────────────────────────────
    public const ADMIN_EMAIL   = 'test_admin@test.local';
    public const AGENT_EMAIL   = 'test_agent@test.local';
    public const PORTAL_EMAIL  = 'test_portal@test.local';
    public const TEST_PASSWORD = 'TestPass123!';

    // ── IDs set after seed() runs ─────────────────────────────────────────────
    public static int $adminId    = 0;
    public static int $agentId    = 0;
    public static int $portalId   = 0;
    public static int $ticketId   = 0;
    public static int $templateId = 0;

    // ── Tracking which rows we actually inserted (for cleanup) ────────────────
    private static array $insertedUserIds     = [];
    private static array $insertedTicketIds   = [];
    private static array $insertedTemplateIds = [];

    // ──────────────────────────────────────────────────────────────────────────

    public static function seed(): void
    {
        $db   = \Database::connect();
        $hash = password_hash(self::TEST_PASSWORD, PASSWORD_DEFAULT);

        // ── Users ──────────────────────────────────────────────────────────────
        $users = [
            [self::ADMIN_EMAIL,  'TestAdmin',  'User', 'admin', &self::$adminId],
            [self::AGENT_EMAIL,  'TestAgent',  'User', 'agent', &self::$agentId],
            [self::PORTAL_EMAIL, 'TestPortal', 'User', 'user',  &self::$portalId],
        ];

        foreach ($users as [$email, $first, $last, $role, &$id]) {
            $row = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $row->execute([$email]);
            $existing = $row->fetch();

            if ($existing) {
                $id = (int) $existing['id'];
                // Self-heal: these fixture rows persist between runs, and tests
                // (e.g. the user-edit suite) can mutate a fixture's role or
                // password. Reset the load-bearing fields to their expected
                // values on every seed so a leftover row can't silently break
                // the next run (a test_admin left as role='user' would 403 every
                // admin test). Idempotent.
                $db->prepare(
                    'UPDATE users SET first_name = ?, last_name = ?, password = ?, role = ? WHERE id = ?'
                )->execute([$first, $last, $hash, $role, $id]);
            } else {
                $db->prepare(
                    'INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)'
                )->execute([$first, $last, $email, $hash, $role]);
                $id = (int) $db->lastInsertId();
                self::$insertedUserIds[] = $id;
            }
        }

        // ── Ticket ─────────────────────────────────────────────────────────────
        $row = $db->prepare("SELECT id FROM tickets WHERE subject = '[TEST] Test Ticket' AND created_by = ? LIMIT 1");
        $row->execute([self::$portalId]);
        $existing = $row->fetch();

        if ($existing) {
            self::$ticketId = (int) $existing['id'];
        } else {
            $db->prepare(
                "INSERT INTO tickets (subject, description, created_by, status) VALUES (?, ?, ?, 'open')"
            )->execute(['[TEST] Test Ticket', 'Automated test fixture — do not edit.', self::$portalId]);
            self::$ticketId = (int) $db->lastInsertId();
            self::$insertedTicketIds[] = self::$ticketId;
        }

        // Ticket visibility is fail-closed and the test agent belongs to no group, so
        // make them a watcher of the fixture ticket. Watching grants visibility and —
        // unlike assigned_to / group_id — survives the partial-update tests that null
        // those columns, so the agent can reliably open this ticket throughout the run.
        $db->prepare('INSERT IGNORE INTO ticket_watchers (ticket_id, user_id) VALUES (?, ?)')
           ->execute([self::$ticketId, self::$agentId]);

        // ── Template (shared so portal tests can see it) ───────────────────────
        $row = $db->prepare("SELECT id FROM ticket_templates WHERE name = '[TEST] Shared Template' AND created_by = ? LIMIT 1");
        $row->execute([self::$adminId]);
        $existing = $row->fetch();

        if ($existing) {
            self::$templateId = (int) $existing['id'];
        } else {
            $db->prepare(
                'INSERT INTO ticket_templates (name, description, subject, body, is_shared, created_by) VALUES (?, ?, ?, ?, 1, ?)'
            )->execute(['[TEST] Shared Template', 'Test fixture', '[TEST] Template Subject', 'Template body text.', self::$adminId]);
            self::$templateId = (int) $db->lastInsertId();
            self::$insertedTemplateIds[] = self::$templateId;
        }
    }

    /**
     * Remove only the rows that THIS run of the seeder inserted.
     * Rows that already existed before the tests are left untouched.
     */
    public static function cleanup(): void
    {
        try {
            $db = \Database::connect();

            foreach (self::$insertedTemplateIds as $id) {
                $db->prepare('DELETE FROM ticket_templates WHERE id = ?')->execute([$id]);
            }
            foreach (self::$insertedTicketIds as $id) {
                $db->prepare('DELETE FROM ticket_watchers  WHERE ticket_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM ticket_timeline   WHERE ticket_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM tickets           WHERE id = ?')->execute([$id]);
            }
            foreach (self::$insertedUserIds as $id) {
                $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            }
        } catch (\Throwable) {
            // Never let cleanup crash the test runner
        }
    }
}

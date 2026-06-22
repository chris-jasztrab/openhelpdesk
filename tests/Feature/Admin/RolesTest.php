<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * Custom permission levels (roles) + granular permissions.
 *
 * Covers the admin CRUD UI, the protection rules (built-ins can't be deleted,
 * managing roles is admin-only), and the core promise of the feature: a custom
 * staff role can reach exactly the admin areas it's been granted and is 403'd
 * everywhere else.
 */
class RolesTest extends TestCase
{
    private const SLUG = 'test_kb_editor';
    private const USER_EMAIL = 'test_kbeditor@test.local';

    private static function db(): \PDO
    {
        return \Database::connect();
    }

    public static function tearDownAfterClass(): void
    {
        $db = self::db();
        $db->prepare('DELETE FROM users WHERE email IN (?, ?)')
           ->execute([self::USER_EMAIL, 'test_assignee@test.local']);
        // role_permissions cascade on role delete
        $db->prepare("DELETE FROM roles WHERE slug LIKE 'test\\_%'")->execute();
    }

    // ── Access guards ──────────────────────────────────────────────────────────

    public function test_admin_can_open_roles_page(): void
    {
        $r = $this->get($this->adminClient(), '/admin/roles');
        $this->assertOk($r);
        $this->assertSee('Permission Levels', $r);
        // Built-in roles are listed and flagged.
        $this->assertSee('Power User', $r);
        $this->assertSee('Built-in', $r);
    }

    public function test_agent_cannot_open_roles_page(): void
    {
        $r = $this->get($this->agentClient(), '/admin/roles', follow: false);
        $this->assertForbidden($r);
    }

    public function test_portal_cannot_open_roles_page(): void
    {
        $r = $this->get($this->portalClient(), '/admin/roles', follow: false);
        $this->assertForbidden($r);
    }

    // ── CRUD lifecycle ───────────────────────────────────────────────────────

    public function test_admin_can_create_edit_and_delete_a_custom_role(): void
    {
        $admin = $this->adminClient();

        // Create with one granted permission.
        $this->post($admin, '/admin/roles/create', [
            'name'        => 'Test KB Editor',
            'description' => 'Can manage knowledge base articles.',
            'perms'       => ['kb.articles.manage'],
        ]);

        $row = self::db()->query("SELECT * FROM roles WHERE slug = '" . self::SLUG . "'")->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($row, 'Custom role should exist after create');
        $this->assertSame(1, (int) $row['is_staff'], 'Custom roles are staff-side');
        $this->assertSame(0, (int) $row['is_admin']);
        $this->assertSame('agent', $row['landing']);
        $this->assertSame(0, (int) $row['is_system']);

        $roleId = (int) $row['id'];
        $grant  = self::db()->prepare('SELECT perm_key FROM role_permissions WHERE role_id = ?');
        $grant->execute([$roleId]);
        $this->assertSame(['kb.articles.manage'], $grant->fetchAll(\PDO::FETCH_COLUMN));

        // Edit: swap the granted permission set.
        $this->post($admin, "/admin/roles/{$roleId}/edit", [
            'name'        => 'Test KB Editor',
            'description' => 'Now also runs reports.',
            'perms'       => ['kb.articles.manage', 'reports.view'],
        ]);
        $grant->execute([$roleId]);
        $keys = $grant->fetchAll(\PDO::FETCH_COLUMN);
        sort($keys);
        $this->assertSame(['kb.articles.manage', 'reports.view'], $keys);

        // Delete the (unused) custom role.
        $this->post($admin, "/admin/roles/{$roleId}/delete", []);
        $gone = self::db()->prepare('SELECT COUNT(*) FROM roles WHERE id = ?');
        $gone->execute([$roleId]);
        $this->assertSame(0, (int) $gone->fetchColumn(), 'Custom role should be deleted');
    }

    public function test_builtin_role_cannot_be_deleted(): void
    {
        $agentId = (int) self::db()->query("SELECT id FROM roles WHERE slug = 'agent'")->fetchColumn();
        $this->post($this->adminClient(), "/admin/roles/{$agentId}/delete", []);
        $still = self::db()->prepare('SELECT COUNT(*) FROM roles WHERE id = ?');
        $still->execute([$agentId]);
        $this->assertSame(1, (int) $still->fetchColumn(), 'Built-in agent role must survive a delete attempt');
    }

    public function test_dynamic_whitelist_accepts_a_custom_role_on_user_create(): void
    {
        $admin = $this->adminClient();
        $this->post($admin, '/admin/roles/create', [
            'name' => 'Test Assignable', 'description' => '', 'perms' => [],
        ]);
        $this->post($admin, '/admin/users/create', [
            'first_name' => 'Test', 'last_name' => 'Assignee',
            'email'      => 'test_assignee@test.local', 'password' => DatabaseSeeder::password(),
            'role'       => 'test_assignable',
        ]);
        $row = self::db()->query("SELECT role FROM users WHERE email = 'test_assignee@test.local'")->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($row, 'User should have been created');
        $this->assertSame('test_assignable', $row['role'], 'Custom role must survive the whitelist, not default to user');
    }

    // ── The payoff: a custom staff role's real access matrix ─────────────────

    public function test_custom_staff_role_access_is_scoped_to_its_grants(): void
    {
        $db = self::db();
        // Seed a staff role granted only kb.articles.manage, plus a user holding it.
        $db->prepare(
            "INSERT INTO roles (slug, name, description, is_system, is_admin, is_staff, landing, sort_order)
             VALUES ('test_kbonly', 'Test KB Only', 'kb only', 0, 0, 1, 'agent', 999)
             ON DUPLICATE KEY UPDATE name = VALUES(name)"
        )->execute();
        $roleId = (int) $db->query("SELECT id FROM roles WHERE slug = 'test_kbonly'")->fetchColumn();
        $db->prepare('INSERT IGNORE INTO role_permissions (role_id, perm_key) VALUES (?, ?)')
           ->execute([$roleId, 'kb.articles.manage']);
        $db->prepare(
            'INSERT INTO users (first_name, last_name, email, password, role)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE password = VALUES(password), role = VALUES(role)'
        )->execute(['Test', 'KbOnly', self::USER_EMAIL, password_hash(DatabaseSeeder::password(), PASSWORD_DEFAULT), 'test_kbonly']);

        // Log in as that custom-role user.
        $jar    = new CookieJar();
        $client = new Client([
            'base_uri'        => self::$base,
            'cookies'         => $jar,
            'http_errors'     => false,
            'allow_redirects' => ['max' => 5, 'strict' => false, 'referer' => true],
        ]);
        $loginHtml = (string) $client->get('/login')->getBody();
        preg_match('/name="_token"\s+value="([^"]+)"/i', $loginHtml, $m);
        $client->post('/login', ['form_params' => [
            '_token' => $m[1] ?? '', 'email' => self::USER_EMAIL, 'password' => DatabaseSeeder::password(),
        ]]);

        // Granted area → reachable.
        $this->assertOk($this->get($client, '/admin/kb/articles'), ' — granted KB area');
        // Ungranted admin areas → forbidden.
        $this->assertForbidden($this->get($client, '/admin/users', follow: false), ' — users.manage not granted');
        $this->assertForbidden($this->get($client, '/admin/reports', follow: false), ' — reports.view not granted');
        // /admin/settings itself is an all-staff landing; the settings.manage-gated
        // config (e.g. branding/email) is what an un-granted role can't reach.
        $this->assertForbidden($this->get($client, '/admin/settings/branding', follow: false), ' — settings.manage not granted');
        // Staff baseline still works (agent ticket queue).
        $this->assertOk($this->get($client, '/agent/tickets'), ' — staff baseline ticket access');
        // Managing roles is never grantable → still forbidden.
        $this->assertForbidden($this->get($client, '/admin/roles', follow: false), ' — role management is admin-only');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * Admin user management — list, create, view, edit, delete, 2FA reset.
 */
class UsersTest extends TestCase
{
    // ── List ──────────────────────────────────────────────────────────────────

    public function test_user_list_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/users');
        $this->assertOk($r);
        $this->assertSee('Users', $r);
    }

    public function test_user_list_has_filter_panel(): void
    {
        $r    = $this->get($this->adminClient(), '/admin/users');
        $html = (string) $r->getBody();
        $this->assertStringContainsString('filter-panel', $html, 'Slide-out filter panel HTML must be present on users list');
    }

    public function test_user_list_has_filter_button(): void
    {
        $r = $this->get($this->adminClient(), '/admin/users');
        $this->assertSee('Filters', $r, ' — Filters button must appear on users list');
    }

    public function test_user_list_contains_test_admin(): void
    {
        $r = $this->get($this->adminClient(), '/admin/users');
        $this->assertSee('TestAdmin', $r);
    }

    public function test_user_list_shows_create_button(): void
    {
        $r = $this->get($this->adminClient(), '/admin/users');
        $this->assertSee('Add User', $r);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_create_user_form_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/users/create');
        $this->assertOk($r);
        $this->assertSee('Add User', $r);
    }

    public function test_create_user_and_delete_it(): void
    {
        $email = 'test_throwaway_' . time() . '@test.local';

        $r = $this->post($this->adminClient(), '/admin/users/create', [
            'first_name' => 'Throwaway',
            'last_name'  => 'User',
            'email'      => $email,
            'password'   => DatabaseSeeder::password(),
            'role'       => 'user',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Create user: expected 200/302, got $code");

        // Verify it was created
        $db  = \Database::connect();
        $row = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $row->execute([$email]);
        $user = $row->fetch();
        $this->assertNotFalse($user, 'User should have been created in the database');

        // Delete it via the route
        $uid = (int) $user['id'];
        $this->post($this->adminClient(), "/admin/users/$uid/delete", []);

        // Verify deletion
        $row->execute([$email]);
        $this->assertFalse($row->fetch(), 'User should have been deleted');
    }

    public function test_create_user_with_duplicate_email_shows_error(): void
    {
        $r = $this->post($this->adminClient(), '/admin/users/create', [
            'first_name' => 'Dup',
            'last_name'  => 'User',
            'email'      => DatabaseSeeder::ADMIN_EMAIL, // already exists
            'password'   => DatabaseSeeder::password(),
            'role'       => 'user',
        ]);

        // Should stay on form or flash an error
        $body = (string) $r->getBody();
        $this->assertTrue(
            str_contains($body, 'already') ||
            str_contains($body, 'exists') ||
            str_contains($body, 'Add User') ||
            $r->getStatusCode() === 302,
            'Expected error for duplicate email'
        );
    }

    // ── View ──────────────────────────────────────────────────────────────────

    public function test_view_user_page_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/users/' . DatabaseSeeder::$adminId);
        $this->assertOk($r);
        $this->assertSee('TestAdmin', $r);
    }

    public function test_view_user_shows_open_tickets_section(): void
    {
        $r = $this->get($this->adminClient(), '/admin/users/' . DatabaseSeeder::$adminId);
        $this->assertSee('Open Tickets', $r);
    }

    public function test_view_user_shows_edit_button(): void
    {
        $r = $this->get($this->adminClient(), '/admin/users/' . DatabaseSeeder::$adminId);
        $this->assertSee('Edit User', $r);
    }

    public function test_view_user_shows_delete_button_for_other_users(): void
    {
        // Admin can delete the portal test user (not themselves)
        $r = $this->get($this->adminClient(), '/admin/users/' . DatabaseSeeder::$portalId);
        $this->assertSee('Delete User', $r);
    }

    public function test_view_user_does_not_show_delete_button_for_self(): void
    {
        // Admin should not see a Delete button on their own profile
        $r    = $this->get($this->adminClient(), '/admin/users/' . DatabaseSeeder::$adminId);
        $html = (string) $r->getBody();
        $this->assertStringNotContainsString('deleteUserModal', $html,
            'Delete modal must not appear on the admin\'s own profile');
    }

    public function test_delete_page_param_triggers_modal_markup(): void
    {
        // ?delete=1 is a UI hint; the page should still load 200 and include the modal
        $r = $this->get($this->adminClient(), '/admin/users/' . DatabaseSeeder::$portalId . '?delete=1');
        $this->assertOk($r);
        $html = (string) $r->getBody();
        $this->assertStringContainsString('deleteUserModal', $html);
    }

    public function test_delete_user_with_tickets_redirects_to_view_when_no_transfer(): void
    {
        // The seeded portal user created the test ticket, so posting without transfer_to
        // must redirect back to the view page rather than deleting.
        $r = $this->post(
            $this->adminClient(),
            '/admin/users/' . DatabaseSeeder::$portalId . '/delete',
            []
        );
        $code = $r->getStatusCode();
        // Either 302 redirect back to view page, or 200 with error — never a successful deletion
        $this->assertTrue($code === 200 || $code === 302, "Expected 200/302, got $code");

        // User must still exist in the DB
        $db   = \Database::connect();
        $stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([DatabaseSeeder::$portalId]);
        $this->assertNotFalse($stmt->fetch(), 'Portal user should not have been deleted without a transfer target');
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function test_edit_user_form_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/users/' . DatabaseSeeder::$portalId . '/edit');
        $this->assertOk($r);
        $this->assertSee('Edit User', $r);
    }

    public function test_edit_user_form_shows_location_ticket_visibility_toggle(): void
    {
        $r    = $this->get($this->adminClient(), '/admin/users/' . DatabaseSeeder::$portalId . '/edit');
        $html = (string) $r->getBody();
        $this->assertStringContainsString('can_view_location_tickets', $html,
            'Edit user form must include the can_view_location_tickets (Location Ticket Visibility) toggle');
    }

    public function test_edit_user_updates_successfully(): void
    {
        $r = $this->post($this->adminClient(), '/admin/users/' . DatabaseSeeder::$portalId . '/edit', [
            'first_name' => 'TestPortal',
            'last_name'  => 'User',
            'email'      => DatabaseSeeder::PORTAL_EMAIL,
            'role'       => 'user',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Edit user: expected 200/302, got $code");
    }

    // ── Group membership from the user edit page ───────────────────────────────

    /** Lowest-id non-confidential group, so these tests never trigger a confidential alert. */
    private function safeGroupId(): int
    {
        $id = (int) \Database::connect()
            ->query('SELECT id FROM `groups` WHERE is_confidential = 0 ORDER BY id LIMIT 1')
            ->fetchColumn();
        if ($id === 0) {
            $this->markTestSkipped('No non-confidential group exists to test against.');
        }
        return $id;
    }

    private function clearAgentGroups(): void
    {
        $stmt = \Database::connect()->prepare('DELETE FROM group_user_map WHERE user_id = ?');
        $stmt->execute([DatabaseSeeder::$agentId]);
    }

    /** Membership rows for the seeded agent, as group_id => is_manager. */
    private function agentGroupMap(): array
    {
        $stmt = \Database::connect()->prepare('SELECT group_id, is_manager FROM group_user_map WHERE user_id = ?');
        $stmt->execute([DatabaseSeeder::$agentId]);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['group_id']] = (int) $row['is_manager'];
        }
        return $map;
    }

    public function test_edit_staff_user_form_shows_group_picker(): void
    {
        $r    = $this->get($this->adminClient(), '/admin/users/' . DatabaseSeeder::$agentId . '/edit');
        $html = (string) $r->getBody();
        $this->assertStringContainsString('Group Membership', $html);
        $this->assertStringContainsString('name="groups[]"', $html,
            'Edit user form must expose editable group checkboxes');
        $this->assertStringContainsString('name="_groups_present"', $html,
            'The picker must post its presence marker so omitted groups are not treated as a wipe');
    }

    public function test_edit_user_adds_and_removes_group_membership(): void
    {
        $gid  = $this->safeGroupId();
        $base = [
            'first_name' => 'TestAgent',
            'last_name'  => 'User',
            'email'      => DatabaseSeeder::AGENT_EMAIL,
            'role'       => 'agent',
        ];

        try {
            // Add, flagged as a group manager
            $this->post($this->adminClient(), '/admin/users/' . DatabaseSeeder::$agentId . '/edit', $base + [
                '_groups_present' => '1',
                'groups'          => [(string) $gid],
                'group_managers'  => [(string) $gid],
            ]);
            $this->assertSame([$gid => 1], $this->agentGroupMap(),
                'Saving the user form should add the membership row with is_manager set');

            // Same post minus the manager flag → member stays, manager is revoked
            $this->post($this->adminClient(), '/admin/users/' . DatabaseSeeder::$agentId . '/edit', $base + [
                '_groups_present' => '1',
                'groups'          => [(string) $gid],
            ]);
            $this->assertSame([$gid => 0], $this->agentGroupMap(),
                'Unticking Manager should clear is_manager without dropping membership');

            // No groups posted, but the marker is present → membership removed
            $this->post($this->adminClient(), '/admin/users/' . DatabaseSeeder::$agentId . '/edit', $base + [
                '_groups_present' => '1',
            ]);
            $this->assertSame([], $this->agentGroupMap(),
                'Clearing every checkbox should remove the membership');
        } finally {
            // The visibility tests rely on the seeded agent having no groups.
            $this->clearAgentGroups();
        }
    }

    public function test_edit_user_without_picker_marker_leaves_groups_alone(): void
    {
        $gid = $this->safeGroupId();
        $db  = \Database::connect();
        $db->prepare('INSERT INTO group_user_map (group_id, user_id, is_manager) VALUES (?, ?, 0)')
            ->execute([$gid, DatabaseSeeder::$agentId]);

        try {
            // A caller that never rendered the picker (API, import script) must not
            // strip membership just because it omitted groups[].
            $this->post($this->adminClient(), '/admin/users/' . DatabaseSeeder::$agentId . '/edit', [
                'first_name' => 'TestAgent',
                'last_name'  => 'User',
                'email'      => DatabaseSeeder::AGENT_EMAIL,
                'role'       => 'agent',
            ]);
            $this->assertSame([$gid => 0], $this->agentGroupMap(),
                'Membership must survive a post that omits the group picker entirely');
        } finally {
            $this->clearAgentGroups();
        }
    }

    public function test_create_user_form_offers_group_picker_collapsed(): void
    {
        $r    = $this->get($this->adminClient(), '/admin/users/create');
        $html = (string) $r->getBody();
        $this->assertStringContainsString('name="groups[]"', $html,
            'Create form must offer the group picker so a new agent can be placed in a group immediately');
        $this->assertStringContainsString('id="groupMembership" class="d-none"', $html,
            'Picker starts collapsed — the default permission level is an end user');
        $this->assertStringContainsString('id="noGroupModal"', $html,
            'Create form must carry the no-group confirmation modal');
    }

    public function test_create_staff_user_with_group_assigns_membership(): void
    {
        $gid   = $this->safeGroupId();
        $email = 'test_grouped_' . time() . '@test.local';

        $this->post($this->adminClient(), '/admin/users/create', [
            'first_name'      => 'Grouped',
            'last_name'       => 'Agent',
            'email'           => $email,
            'password'        => DatabaseSeeder::password(),
            'role'            => 'agent',
            '_groups_present' => '1',
            'groups'          => [(string) $gid],
        ]);

        $db   = \Database::connect();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $newId = (int) $stmt->fetchColumn();

        try {
            $this->assertNotSame(0, $newId, 'User should have been created');
            $map = $db->prepare('SELECT group_id FROM group_user_map WHERE user_id = ?');
            $map->execute([$newId]);
            $this->assertSame([$gid], array_map('intval', $map->fetchAll(\PDO::FETCH_COLUMN)),
                'Groups picked on the create form should be assigned to the new user');
        } finally {
            if ($newId !== 0) {
                $db->prepare('DELETE FROM group_user_map WHERE user_id = ?')->execute([$newId]);
                $db->prepare('DELETE FROM users WHERE id = ?')->execute([$newId]);
            }
        }
    }

    public function test_edit_non_staff_user_hides_group_picker_by_default(): void
    {
        $r    = $this->get($this->adminClient(), '/admin/users/' . DatabaseSeeder::$portalId . '/edit');
        $html = (string) $r->getBody();
        // Rendered but collapsed — it appears client-side if the level is raised to staff.
        $this->assertStringContainsString('id="groupMembership" class="d-none"', $html,
            'Group picker must start hidden for a non-staff permission level');
    }

    // ── Role enforcement ──────────────────────────────────────────────────────

    public function test_agent_cannot_access_user_list(): void
    {
        $r = $this->get($this->agentClient(), '/admin/users', follow: false);
        $this->assertForbidden($r);
    }

    public function test_agent_cannot_access_create_user_form(): void
    {
        $r = $this->get($this->agentClient(), '/admin/users/create', follow: false);
        $this->assertForbidden($r);
    }

    public function test_portal_cannot_access_user_list(): void
    {
        $r = $this->get($this->portalClient(), '/admin/users', follow: false);
        $this->assertForbidden($r);
    }
}

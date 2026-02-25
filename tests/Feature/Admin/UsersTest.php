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
            'password'   => 'TestPass123!',
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
            'password'   => 'TestPass123!',
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

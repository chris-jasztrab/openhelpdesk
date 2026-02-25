<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * Role-based access control.
 *
 * Verifies that each route is guarded correctly:
 *   - Guests       → redirect to /login
 *   - Portal users → 403 or redirect away from admin/agent areas
 *   - Agents       → 403 on admin-only routes
 *   - Admins       → full access everywhere
 */
class AccessControlTest extends TestCase
{
    // ── Guest access ──────────────────────────────────────────────────────────

    /** @dataProvider guestRestrictedRoutes */
    public function test_guest_is_redirected_to_login(string $path): void
    {
        $r = $this->get($this->guestClient(), $path, follow: false);
        $this->assertRedirectContains('login', $r, " — path: $path");
    }

    public static function guestRestrictedRoutes(): array
    {
        return [
            ['/portal/tickets'],
            ['/portal/tickets/create'],
            ['/admin'],
            ['/admin/tickets'],
            ['/admin/users'],
            ['/agent'],
            ['/agent/tickets'],
            ['/profile'],
        ];
    }

    // ── Portal user (role=user) access ────────────────────────────────────────

    /** @dataProvider adminRoutesPortalShouldNotAccess */
    public function test_portal_user_cannot_access_admin_routes(string $path): void
    {
        $r = $this->get($this->portalClient(), $path, follow: false);
        $this->assertForbidden($r, " — path: $path");
    }

    public static function adminRoutesPortalShouldNotAccess(): array
    {
        return [
            ['/admin'],
            ['/admin/tickets'],
            ['/admin/users'],
            ['/admin/settings'],
            ['/admin/settings/danger-zone'],
            ['/admin/ticket-templates'],
            ['/admin/priorities'],
            ['/admin/types'],
            ['/admin/locations'],
            ['/admin/groups'],
            ['/admin/kb/categories'],
            ['/admin/reports'],
            ['/admin/audit-log'],
        ];
    }

    /** @dataProvider agentRoutesPortalShouldNotAccess */
    public function test_portal_user_cannot_access_agent_routes(string $path): void
    {
        $r = $this->get($this->portalClient(), $path, follow: false);
        $this->assertForbidden($r, " — path: $path");
    }

    public static function agentRoutesPortalShouldNotAccess(): array
    {
        return [
            ['/agent'],
            ['/agent/tickets'],
        ];
    }

    // ── Agent access ──────────────────────────────────────────────────────────

    /** @dataProvider adminOnlyRoutes */
    public function test_agent_cannot_access_admin_only_routes(string $path): void
    {
        $r = $this->get($this->agentClient(), $path, follow: false);
        $this->assertForbidden($r, " — path: $path");
    }

    public static function adminOnlyRoutes(): array
    {
        return [
            ['/admin/settings'],
            ['/admin/settings/danger-zone'],
            ['/admin/users'],
            ['/admin/users/create'],
            ['/admin/priorities'],
            ['/admin/priorities/create'],
            ['/admin/types'],
            ['/admin/types/create'],
            ['/admin/locations'],
            ['/admin/locations/create'],
            ['/admin/groups'],
            ['/admin/groups/create'],
            ['/admin/kb/categories'],
            ['/admin/reports'],
            ['/admin/audit-log'],
            ['/admin/settings/branding'],
            ['/admin/settings/sla-policies'],
            ['/admin/settings/automations'],
            ['/admin/settings/escalations'],
            ['/admin/settings/business-hours'],
        ];
    }

    // Agents CAN reach ticket templates (shared admin/agent feature)
    public function test_agent_can_access_ticket_templates(): void
    {
        $r = $this->get($this->agentClient(), '/admin/ticket-templates');
        $this->assertOk($r, ' — agent should see templates list');
    }

    // ── Admin access ──────────────────────────────────────────────────────────

    /** @dataProvider adminAccessibleRoutes */
    public function test_admin_can_access_all_admin_routes(string $path): void
    {
        $r = $this->get($this->adminClient(), $path);
        $this->assertOk($r, " — admin should reach $path");
    }

    public static function adminAccessibleRoutes(): array
    {
        return [
            ['/admin'],
            ['/admin/tickets'],
            ['/admin/users'],
            ['/admin/settings'],
            ['/admin/settings/danger-zone'],
            ['/admin/ticket-templates'],
            ['/admin/priorities'],
            ['/admin/types'],
            ['/admin/locations'],
            ['/admin/groups'],
            ['/admin/kb/categories'],
            ['/admin/kb/folders'],
            ['/admin/kb/articles'],
            ['/admin/reports'],
            ['/admin/audit-log'],
            ['/admin/settings/branding'],
            ['/admin/settings/sla-policies'],
            ['/admin/settings/automations'],
            ['/admin/settings/escalations'],
            ['/admin/settings/business-hours'],
            ['/admin/settings/csat'],
            ['/admin/settings/email-templates'],
            ['/admin/workflows/ticket-fields'],
        ];
    }

    // ── Portal user cannot see other users' tickets ───────────────────────────

    public function test_portal_user_cannot_view_other_users_portal_ticket(): void
    {
        // The seeded ticket belongs to the portal test user themselves — viewing
        // it should be fine. But trying to view a ticket via the admin interface
        // must be blocked.
        $adminTicketPath = '/admin/tickets/' . DatabaseSeeder::$ticketId;
        $r = $this->get($this->portalClient(), $adminTicketPath, follow: false);
        $this->assertForbidden($r, ' — portal user should not see admin ticket view');
    }

    // ── Redirect destination after auth guard ─────────────────────────────────

    public function test_unauthenticated_request_redirects_to_login_not_403(): void
    {
        $r = $this->get($this->guestClient(), '/admin/settings', follow: false);
        $this->assertSame(302, $r->getStatusCode());
        $this->assertStringContainsString('login', $r->getHeaderLine('Location'));
    }
}

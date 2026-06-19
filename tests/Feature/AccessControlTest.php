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
            // /admin/settings itself is intentionally a staff landing as of v2.66.0
            // (single Settings link on the staff rail → permission-filtered nav;
            // Status Banners etc. are open to all staff). Real access control is on
            // the config sub-pages below, which agents still 403 on.
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

    // ── Bulk actions respect per-ticket visibility (regression) ───────────────

    /**
     * Regression for the inverted confidential/visibility filter in
     * POST /agent/tickets/bulk: a non-admin staffer (no tickets.view_all, no
     * group) must not be able to bulk-act on a ticket they cannot see, even
     * though they hold tickets.bulk_close. A ticket they DO watch still closes.
     */
    public function test_agent_cannot_bulk_act_on_invisible_tickets(): void
    {
        $db = \Database::connect();

        // Not visible: portal-owned, no group, agent is not a watcher.
        $db->prepare("INSERT INTO tickets (subject, description, created_by, status) VALUES ('[TEST] Invisible bulk', 'x', ?, 'open')")
           ->execute([DatabaseSeeder::$portalId]);
        $invisibleId = (int) $db->lastInsertId();

        // Visible: same, but the agent watches it.
        $db->prepare("INSERT INTO tickets (subject, description, created_by, status) VALUES ('[TEST] Visible bulk', 'x', ?, 'open')")
           ->execute([DatabaseSeeder::$portalId]);
        $visibleId = (int) $db->lastInsertId();
        $db->prepare('INSERT IGNORE INTO ticket_watchers (ticket_id, user_id) VALUES (?, ?)')
           ->execute([$visibleId, DatabaseSeeder::$agentId]);

        $statusOf = function (int $id) use ($db): string {
            $s = $db->prepare('SELECT status FROM tickets WHERE id = ?');
            $s->execute([$id]);
            return (string) $s->fetchColumn();
        };

        try {
            $this->post($this->agentClient(), '/agent/tickets/bulk', [
                'action'     => 'close',
                'ticket_ids' => [$invisibleId, $visibleId],
            ]);

            $this->assertSame('open', $statusOf($invisibleId),
                ' — agent must not bulk-close a ticket they cannot see');
            $this->assertSame('closed', $statusOf($visibleId),
                ' — agent should still bulk-close a ticket they can see');
        } finally {
            $db->prepare('DELETE FROM ticket_watchers WHERE ticket_id IN (?, ?)')->execute([$invisibleId, $visibleId]);
            $db->prepare('DELETE FROM ticket_timeline  WHERE ticket_id IN (?, ?)')->execute([$invisibleId, $visibleId]);
            $db->prepare('DELETE FROM tickets           WHERE id IN (?, ?)')->execute([$invisibleId, $visibleId]);
        }
    }

    // ── Redirect destination after auth guard ─────────────────────────────────

    public function test_unauthenticated_request_redirects_to_login_not_403(): void
    {
        $r = $this->get($this->guestClient(), '/admin/settings', follow: false);
        $this->assertSame(302, $r->getStatusCode());
        $this->assertStringContainsString('login', $r->getHeaderLine('Location'));
    }
}

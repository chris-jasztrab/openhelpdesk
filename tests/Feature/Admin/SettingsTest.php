<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\Support\TestCase;

/**
 * Admin settings pages — general, locations, priorities, types, groups,
 * business hours, branding, CSAT, automations, escalations, SLA, custom fields.
 * All of these are admin-only and must return 403 for agents/portal users.
 */
class SettingsTest extends TestCase
{
    // ── Settings pages load ───────────────────────────────────────────────────

    /** @dataProvider settingsPages */
    public function test_settings_page_loads_for_admin(string $path, string $keyword): void
    {
        $r = $this->get($this->adminClient(), $path);
        $this->assertOk($r, " — $path");
        $this->assertSee($keyword, $r, " — expected «{$keyword}» on {$path}");
    }

    public static function settingsPages(): array
    {
        return [
            ['/admin/settings',                     'Settings'],
            ['/admin/settings/branding',            'Branding'],
            ['/admin/settings/business-hours',      'Business Hours'],
            ['/admin/settings/csat',                'CSAT'],
            ['/admin/settings/email-templates',     'Email'],
            ['/admin/settings/sla-policies',        'SLA'],
            ['/admin/settings/automations',         'Automations'],
            ['/admin/settings/escalations',         'Escalation'],
            ['/admin/settings/scheduled-reports',   'Scheduled'],
            ['/admin/settings/backup',              'Backup'],
            ['/admin/workflows/ticket-fields',      'Custom'],
        ];
    }

    // ── Location CRUD ─────────────────────────────────────────────────────────

    public function test_locations_list_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/locations');
        $this->assertOk($r);
        $this->assertSee('Locations', $r);
    }

    public function test_create_location_form_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/locations/create');
        $this->assertOk($r);
    }

    public function test_create_and_delete_location(): void
    {
        $r = $this->post($this->adminClient(), '/admin/locations/create', [
            'name' => '[TEST] Auto Location',
        ]);
        $this->assertTrue(in_array($r->getStatusCode(), [200, 302]));

        $db  = \Database::connect();
        $row = $db->prepare("SELECT id FROM locations WHERE name = '[TEST] Auto Location' LIMIT 1");
        $row->execute();
        if ($loc = $row->fetch()) {
            $this->post($this->adminClient(), '/admin/locations/' . $loc['id'] . '/delete', []);
        }
    }

    // ── Priority CRUD ─────────────────────────────────────────────────────────

    public function test_priorities_list_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/priorities');
        $this->assertOk($r);
        $this->assertSee('Priorities', $r);
    }

    public function test_create_priority_form_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/priorities/create');
        $this->assertOk($r);
    }

    public function test_create_and_delete_priority(): void
    {
        $r = $this->post($this->adminClient(), '/admin/priorities/create', [
            'name'  => '[TEST] Auto Priority',
            'color' => '#aabbcc',
        ]);
        $this->assertTrue(in_array($r->getStatusCode(), [200, 302]));

        $db  = \Database::connect();
        $row = $db->prepare("SELECT id FROM ticket_priorities WHERE name = '[TEST] Auto Priority' LIMIT 1");
        $row->execute();
        if ($p = $row->fetch()) {
            $this->post($this->adminClient(), '/admin/priorities/' . $p['id'] . '/delete', []);
        }
    }

    // ── Type CRUD ─────────────────────────────────────────────────────────────

    public function test_types_list_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/types');
        $this->assertOk($r);
        $this->assertSee('Ticket Types', $r);
    }

    public function test_create_type_form_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/types/create');
        $this->assertOk($r);
    }

    public function test_create_and_delete_type(): void
    {
        $r = $this->post($this->adminClient(), '/admin/types/create', [
            'name' => '[TEST] Auto Type',
        ]);
        $this->assertTrue(in_array($r->getStatusCode(), [200, 302]));

        $db  = \Database::connect();
        $row = $db->prepare("SELECT id FROM ticket_types WHERE name = '[TEST] Auto Type' LIMIT 1");
        $row->execute();
        if ($t = $row->fetch()) {
            $this->post($this->adminClient(), '/admin/types/' . $t['id'] . '/delete', []);
        }
    }

    // ── Group CRUD ────────────────────────────────────────────────────────────

    public function test_groups_list_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/groups');
        $this->assertOk($r);
        $this->assertSee('Groups', $r);
    }

    public function test_create_group_form_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/groups/create');
        $this->assertOk($r);
    }

    public function test_create_and_delete_group(): void
    {
        $r = $this->post($this->adminClient(), '/admin/groups/create', [
            'name' => '[TEST] Auto Group',
        ]);
        $this->assertTrue(in_array($r->getStatusCode(), [200, 302]));

        $db  = \Database::connect();
        $row = $db->prepare("SELECT id FROM groups WHERE name = '[TEST] Auto Group' LIMIT 1");
        $row->execute();
        if ($g = $row->fetch()) {
            $this->post($this->adminClient(), '/admin/groups/' . $g['id'] . '/delete', []);
        }
    }

    // ── Automation CRUD ───────────────────────────────────────────────────────

    public function test_automations_list_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/settings/automations');
        $this->assertOk($r);
    }

    public function test_create_automation_form_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/settings/automations/create');
        $this->assertOk($r);
    }

    // ── Escalation CRUD ───────────────────────────────────────────────────────

    public function test_escalations_list_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/settings/escalations');
        $this->assertOk($r);
    }

    public function test_create_escalation_form_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/settings/escalations/create');
        $this->assertOk($r);
    }

    // ── Role enforcement ──────────────────────────────────────────────────────

    /** @dataProvider settingsPagePaths */
    public function test_agent_cannot_access_settings_pages(string $path): void
    {
        $r = $this->get($this->agentClient(), $path, follow: false);
        $this->assertForbidden($r, " — agent should not access $path");
    }

    /** @dataProvider settingsPagePaths */
    public function test_portal_cannot_access_settings_pages(string $path): void
    {
        $r = $this->get($this->portalClient(), $path, follow: false);
        $this->assertForbidden($r, " — portal should not access $path");
    }

    public static function settingsPagePaths(): array
    {
        return [
            ['/admin/settings'],
            ['/admin/settings/branding'],
            ['/admin/settings/business-hours'],
            ['/admin/settings/sla-policies'],
            ['/admin/settings/automations'],
            ['/admin/settings/escalations'],
            ['/admin/locations'],
            ['/admin/priorities'],
            ['/admin/types'],
            ['/admin/groups'],
        ];
    }
}

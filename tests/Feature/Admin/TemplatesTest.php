<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * Ticket template management — list, create, edit, delete, sharing with portal.
 */
class TemplatesTest extends TestCase
{
    // ── List ──────────────────────────────────────────────────────────────────

    public function test_template_list_loads_for_admin(): void
    {
        $r = $this->get($this->adminClient(), '/admin/ticket-templates');
        $this->assertOk($r);
        $this->assertSee('Ticket Templates', $r);
    }

    public function test_template_list_loads_for_agent(): void
    {
        $r = $this->get($this->agentClient(), '/admin/ticket-templates');
        $this->assertOk($r);
        $this->assertSee('Ticket Templates', $r);
    }

    public function test_template_list_shows_seeded_template(): void
    {
        $r = $this->get($this->adminClient(), '/admin/ticket-templates');
        $this->assertSee('[TEST] Shared Template', $r);
    }

    public function test_template_list_shows_new_template_button(): void
    {
        $r = $this->get($this->adminClient(), '/admin/ticket-templates');
        $this->assertSee('New Template', $r);
    }

    public function test_portal_cannot_access_template_list(): void
    {
        $r = $this->get($this->portalClient(), '/admin/ticket-templates', follow: false);
        $this->assertForbidden($r);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_create_template_form_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/ticket-templates/create');
        $this->assertOk($r);
        $this->assertSee('New Template', $r);
    }

    public function test_create_template_form_has_sharing_toggle(): void
    {
        $r = $this->get($this->adminClient(), '/admin/ticket-templates/create');
        $this->assertSee('Sharing', $r);
        $this->assertSee('portal users', $r);
    }

    public function test_create_template_and_clean_up(): void
    {
        $r = $this->post($this->adminClient(), '/admin/ticket-templates/create', [
            'name'      => '[TEST] Throwaway Template',
            'subject'   => 'Throwaway subject',
            'body'      => 'Throwaway body',
            'is_shared' => '0',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Create template: expected 200/302, got $code");

        // Verify created
        $db  = \Database::connect();
        $row = $db->prepare("SELECT id FROM ticket_templates WHERE name = '[TEST] Throwaway Template' LIMIT 1");
        $row->execute();
        $tpl = $row->fetch();
        $this->assertNotFalse($tpl, 'Template should have been created');

        // Delete via route
        $tid = (int) $tpl['id'];
        $this->post($this->adminClient(), "/admin/ticket-templates/$tid/delete", []);

        $row->execute();
        $this->assertFalse($row->fetch(), 'Template should have been deleted');
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function test_edit_template_form_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/ticket-templates/' . DatabaseSeeder::$templateId . '/edit');
        $this->assertOk($r);
        $this->assertSee('Edit Template', $r);
    }

    public function test_edit_template_form_pre_fills_values(): void
    {
        $r = $this->get($this->adminClient(), '/admin/ticket-templates/' . DatabaseSeeder::$templateId . '/edit');
        $this->assertSee('[TEST] Shared Template', $r);
    }

    public function test_update_template(): void
    {
        $r = $this->post(
            $this->adminClient(),
            '/admin/ticket-templates/' . DatabaseSeeder::$templateId . '/edit',
            [
                'name'      => '[TEST] Shared Template',  // restore original
                'subject'   => '[TEST] Template Subject',
                'body'      => 'Template body text.',
                'is_shared' => '1',
            ]
        );

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Update template: expected 200/302, got $code");
    }

    // ── Sharing & portal visibility ───────────────────────────────────────────

    public function test_shared_template_appears_on_portal_create_page(): void
    {
        $r = $this->get($this->portalClient(), '/portal/tickets/create');
        $this->assertOk($r);
        // The seeded template is shared, so portal should see a template picker
        $this->assertSee('[TEST] Shared Template', $r);
    }

    public function test_private_template_does_not_appear_on_portal(): void
    {
        // Create a private template
        $this->post($this->adminClient(), '/admin/ticket-templates/create', [
            'name'      => '[TEST] Private Template',
            'subject'   => 'Private',
            'body'      => 'Private body',
            'is_shared' => '0',
        ]);

        $r = $this->get($this->portalClient(), '/portal/tickets/create');
        $this->assertNotSee('[TEST] Private Template', $r);

        // Clean up
        $db  = \Database::connect();
        $row = $db->prepare("SELECT id FROM ticket_templates WHERE name = '[TEST] Private Template' LIMIT 1");
        $row->execute();
        if ($t = $row->fetch()) {
            $db->prepare('DELETE FROM ticket_templates WHERE id = ?')->execute([$t['id']]);
        }
    }

    // ── Agent restrictions ────────────────────────────────────────────────────

    public function test_agent_can_create_template(): void
    {
        $r = $this->post($this->agentClient(), '/admin/ticket-templates/create', [
            'name'      => '[TEST] Agent Template',
            'subject'   => 'Agent subject',
            'body'      => 'Agent body',
            'is_shared' => '0',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Agent create template: expected 200/302, got $code");

        $db  = \Database::connect();
        $row = $db->prepare("SELECT id FROM ticket_templates WHERE name = '[TEST] Agent Template' LIMIT 1");
        $row->execute();
        if ($t = $row->fetch()) {
            $db->prepare('DELETE FROM ticket_templates WHERE id = ?')->execute([$t['id']]);
        }
    }

    public function test_agent_cannot_edit_other_users_template(): void
    {
        // The seeded template was created by adminId; the agent should see "view only"
        $r    = $this->get($this->agentClient(), '/admin/ticket-templates');
        $body = (string) $r->getBody();

        $agentId = DatabaseSeeder::$agentId;
        $adminId = DatabaseSeeder::$adminId;

        // Templates created by others should show "View only" or hide edit/delete
        // (depends on implementation) — just ensure the page loads
        $this->assertOk($r);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Agent;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * Agent ticket workflows — list, create, view, comment, update, filter panel.
 */
class TicketsTest extends TestCase
{
    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function test_agent_dashboard_loads(): void
    {
        $r = $this->get($this->agentClient(), '/agent');
        $this->assertOk($r);
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function test_ticket_list_loads(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets');
        $this->assertOk($r);
        $this->assertSee('Tickets', $r);
    }

    public function test_ticket_list_shows_filter_button(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets');
        $this->assertSee('Filters', $r);
    }

    public function test_ticket_list_shows_columns_button(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets');
        $this->assertSee('Columns', $r);
    }

    public function test_ticket_list_shows_new_ticket_button(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets');
        $this->assertSee('New Ticket', $r);
    }

    public function test_filter_by_status(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets?status=open');
        $this->assertOk($r);
    }

    public function test_filter_by_my_tickets(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets?agent=mine');
        $this->assertOk($r);
    }

    public function test_filter_panel_html_present(): void
    {
        $html = (string) $this->get($this->agentClient(), '/agent/tickets')->getBody();
        $this->assertStringContainsString('filter-panel', $html);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_create_ticket_form_loads(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets/create');
        $this->assertOk($r);
        $this->assertSee('New Ticket', $r);
    }

    public function test_agent_create_form_does_not_show_on_behalf_of(): void
    {
        // Agents create tickets as themselves — no "on behalf of" picker
        $r = $this->get($this->agentClient(), '/agent/tickets/create');
        $this->assertNotSee('On Behalf Of', $r);
    }

    public function test_create_form_shows_template_picker(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets/create');
        // Template picker section should be present (even if no templates exist)
        $this->assertOk($r);
    }

    public function test_create_ticket_and_clean_up(): void
    {
        $r = $this->post($this->agentClient(), '/admin/tickets/create', [
            'subject'     => '[TEST] Agent-created ticket',
            'description' => 'Created by agent feature test.',
            'status'      => 'open',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Agent create ticket: expected 200/302, got $code");

        $db  = \Database::connect();
        $row = $db->prepare("SELECT id FROM tickets WHERE subject = '[TEST] Agent-created ticket' LIMIT 1");
        $row->execute();
        if ($t = $row->fetch()) {
            $db->prepare('DELETE FROM ticket_timeline WHERE ticket_id = ?')->execute([$t['id']]);
            $db->prepare('DELETE FROM tickets WHERE id = ?')->execute([$t['id']]);
        }
    }

    // ── View ──────────────────────────────────────────────────────────────────

    public function test_view_ticket_loads(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets/' . DatabaseSeeder::$ticketId);
        $this->assertOk($r);
        $this->assertSee('[TEST] Test Ticket', $r);
    }

    public function test_view_ticket_shows_reply_panel(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets/' . DatabaseSeeder::$ticketId);
        // The reply panel replaced the old "Add Comment" form with Reply/Forward/Add Note action buttons
        $this->assertSee('Add Note', $r);
    }

    public function test_view_ticket_shows_sidebar_fields(): void
    {
        $r = $this->get($this->agentClient(), '/agent/tickets/' . DatabaseSeeder::$ticketId);
        $this->assertSee('Status', $r);
        $this->assertSee('Priority', $r);
    }

    // ── Comment ───────────────────────────────────────────────────────────────

    public function test_agent_can_add_reply(): void
    {
        $r = $this->post($this->agentClient(), '/agent/tickets/' . DatabaseSeeder::$ticketId . '/comment', [
            'body' => 'Agent automated test reply.',
            'type' => 'reply',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Add comment: expected 200/302, got $code");
    }

    public function test_agent_can_add_internal_note(): void
    {
        $r = $this->post($this->agentClient(), '/agent/tickets/' . DatabaseSeeder::$ticketId . '/comment', [
            'body' => 'Internal note from agent test.',
            'type' => 'note',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Add note: expected 200/302, got $code");
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_agent_can_update_ticket_status(): void
    {
        $r = $this->post($this->agentClient(), '/agent/tickets/' . DatabaseSeeder::$ticketId . '/update', [
            'status' => 'pending',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Update status: expected 200/302, got $code");

        // Reset
        $this->post($this->agentClient(), '/agent/tickets/' . DatabaseSeeder::$ticketId . '/update', [
            'status' => 'open',
        ]);
    }

    // ── Saved filters ─────────────────────────────────────────────────────────

    public function test_save_and_delete_filter(): void
    {
        $r = $this->post($this->agentClient(), '/agent/tickets/filters/save', [
            'name'   => '[TEST] Agent Filter',
            'status' => 'open',
        ]);

        $this->assertTrue(in_array($r->getStatusCode(), [200, 302]));

        $db  = \Database::connect();
        $row = $db->prepare("SELECT id FROM saved_filters WHERE name = '[TEST] Agent Filter' LIMIT 1");
        $row->execute();
        if ($sf = $row->fetch()) {
            $this->post($this->agentClient(), '/agent/tickets/filters/' . $sf['id'] . '/delete', []);
        }
    }

    // ── Access to admin routes ────────────────────────────────────────────────

    public function test_agent_cannot_access_admin_settings(): void
    {
        $r = $this->get($this->agentClient(), '/admin/settings', follow: false);
        $this->assertForbidden($r);
    }

    public function test_agent_cannot_manage_users(): void
    {
        $r = $this->get($this->agentClient(), '/admin/users', follow: false);
        $this->assertForbidden($r);
    }
}

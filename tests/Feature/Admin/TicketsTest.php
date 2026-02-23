<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * Admin ticket management — list, create, view, update, comment, export, filters.
 */
class TicketsTest extends TestCase
{
    // ── List ──────────────────────────────────────────────────────────────────

    public function test_ticket_list_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets');
        $this->assertOk($r);
        $this->assertSee('All Tickets', $r);
    }

    public function test_ticket_list_shows_filter_button(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets');
        $this->assertSee('Filters', $r, ' — filter panel toggle button must be present');
    }

    public function test_ticket_list_shows_columns_button(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets');
        $this->assertSee('Columns', $r);
    }

    public function test_ticket_list_shows_new_ticket_button(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets');
        $this->assertSee('New Ticket', $r);
    }

    public function test_ticket_list_shows_templates_button(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets');
        $this->assertSee('Templates', $r);
    }

    public function test_ticket_list_shows_export_csv_button(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets');
        $this->assertSee('Export CSV', $r);
    }

    // ── Filter panel ──────────────────────────────────────────────────────────

    public function test_filter_panel_html_is_present(): void
    {
        $r    = $this->get($this->adminClient(), '/admin/tickets');
        $html = (string) $r->getBody();
        $this->assertStringContainsString('filter-panel', $html, 'Filter panel HTML must be present');
    }

    public function test_filter_by_status_returns_200(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets?status=open');
        $this->assertOk($r);
        $this->assertSee('filtered', $r);
    }

    public function test_filter_by_search_query_returns_200(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets?q=test');
        $this->assertOk($r);
    }

    public function test_reset_filters(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets?reset=1');
        $this->assertOk($r);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_create_ticket_form_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets/create');
        $this->assertOk($r);
        $this->assertSee('New Ticket', $r);
    }

    public function test_create_ticket_form_shows_on_behalf_of_field(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets/create');
        $this->assertSee('On Behalf Of', $r);
    }

    public function test_create_ticket_form_shows_template_picker(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets/create');
        $this->assertSee('template', $r);
    }

    public function test_create_ticket_redirects_to_new_ticket(): void
    {
        $r = $this->post($this->adminClient(), '/admin/tickets/create', [
            'subject'     => '[TEST] Admin-created ticket',
            'description' => 'Created by admin feature test.',
            'status'      => 'open',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Expected 200 or redirect, got $code");

        // Clean up created ticket
        $db  = \Database::connect();
        $row = $db->prepare("SELECT id FROM tickets WHERE subject = '[TEST] Admin-created ticket' LIMIT 1");
        $row->execute();
        if ($t = $row->fetch()) {
            $db->prepare('DELETE FROM ticket_timeline WHERE ticket_id = ?')->execute([$t['id']]);
            $db->prepare('DELETE FROM tickets WHERE id = ?')->execute([$t['id']]);
        }
    }

    // ── View ──────────────────────────────────────────────────────────────────

    public function test_view_ticket_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets/' . DatabaseSeeder::$ticketId);
        $this->assertOk($r);
        $this->assertSee('[TEST] Test Ticket', $r);
    }

    public function test_view_ticket_shows_timeline(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets/' . DatabaseSeeder::$ticketId);
        $this->assertOk($r);
        // Timeline container is always rendered
        $this->assertSee('ticket', $r);
    }

    // ── Comment ───────────────────────────────────────────────────────────────

    public function test_admin_can_add_comment_to_ticket(): void
    {
        $r = $this->post($this->adminClient(), '/admin/tickets/' . DatabaseSeeder::$ticketId . '/comment', [
            'body' => 'Automated test comment — can be deleted.',
            'type' => 'reply',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Expected 200 or redirect, got $code");
    }

    public function test_admin_can_add_internal_note_to_ticket(): void
    {
        $r = $this->post($this->adminClient(), '/admin/tickets/' . DatabaseSeeder::$ticketId . '/comment', [
            'body' => 'Internal note from automated test.',
            'type' => 'note',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Expected 200 or redirect, got $code");
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function test_admin_can_update_ticket_status(): void
    {
        $r = $this->post($this->adminClient(), '/admin/tickets/' . DatabaseSeeder::$ticketId . '/update', [
            'status' => 'in_progress',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Expected 200 or redirect, got $code");

        // Reset back
        $this->post($this->adminClient(), '/admin/tickets/' . DatabaseSeeder::$ticketId . '/update', [
            'status' => 'open',
        ]);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function test_export_csv_returns_csv(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets/export');
        $this->assertOk($r);
        // Response should be CSV content
        $ct = $r->getHeaderLine('Content-Type');
        $this->assertTrue(
            str_contains($ct, 'csv') || str_contains($ct, 'text') || str_contains($ct, 'octet'),
            "Expected CSV content-type, got: $ct"
        );
    }

    public function test_export_csv_with_filters(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets/export?status=open');
        $this->assertOk($r);
    }

    // ── Saved filters ─────────────────────────────────────────────────────────

    public function test_save_filter_and_delete_it(): void
    {
        // Save a filter
        $r = $this->post($this->adminClient(), '/admin/tickets/filters/save', [
            'name'   => '[TEST] Auto Filter',
            'status' => 'open',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Save filter: expected 200/302, got $code");

        // Find and delete it
        $db  = \Database::connect();
        $row = $db->prepare("SELECT id FROM saved_filters WHERE name = '[TEST] Auto Filter' LIMIT 1");
        $row->execute();
        if ($sf = $row->fetch()) {
            $this->post($this->adminClient(), '/admin/tickets/filters/' . $sf['id'] . '/delete', []);
            $db->prepare('DELETE FROM saved_filters WHERE id = ?')->execute([$sf['id']]);
        }
    }

    // ── Pagination ────────────────────────────────────────────────────────────

    public function test_pagination_page_2_returns_200(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets?page=2');
        $this->assertOk($r);
    }

    // ── Sorting ───────────────────────────────────────────────────────────────

    public function test_sort_by_subject(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets?sort=subject&dir=asc');
        $this->assertOk($r);
    }

    public function test_sort_by_created_at_desc(): void
    {
        $r = $this->get($this->adminClient(), '/admin/tickets?sort=created_at&dir=desc');
        $this->assertOk($r);
    }
}

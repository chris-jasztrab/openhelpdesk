<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * Portal (end-user) ticket workflows — list, create, view own, comment.
 * Also covers: template picker, isolation from other users' tickets.
 */
class TicketsTest extends TestCase
{
    // ── List ──────────────────────────────────────────────────────────────────

    public function test_portal_ticket_list_loads(): void
    {
        $r = $this->get($this->portalClient(), '/portal/tickets');
        $this->assertOk($r);
    }

    public function test_portal_ticket_list_shows_seeded_ticket(): void
    {
        $r = $this->get($this->portalClient(), '/portal/tickets');
        $this->assertSee('[TEST] Test Ticket', $r);
    }

    public function test_portal_ticket_list_shows_create_button(): void
    {
        $r = $this->get($this->portalClient(), '/portal/tickets');
        $this->assertSee('New Ticket', $r);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_create_ticket_form_loads(): void
    {
        $r = $this->get($this->portalClient(), '/portal/tickets/create');
        $this->assertOk($r);
        $this->assertSee('Subject', $r);
    }

    public function test_create_form_shows_shared_template_picker(): void
    {
        // The seeded template is shared, so the picker section should appear
        $r = $this->get($this->portalClient(), '/portal/tickets/create');
        $this->assertSee('[TEST] Shared Template', $r);
    }

    public function test_create_ticket_and_clean_up(): void
    {
        $r = $this->post($this->portalClient(), '/portal/tickets/create', [
            'subject'     => '[TEST] Portal-created ticket',
            'description' => 'Created by portal feature test.',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Portal create: expected 200/302, got $code");

        $db  = \Database::connect();
        $row = $db->prepare("SELECT id FROM tickets WHERE subject = '[TEST] Portal-created ticket' LIMIT 1");
        $row->execute();
        if ($t = $row->fetch()) {
            $db->prepare('DELETE FROM ticket_timeline WHERE ticket_id = ?')->execute([$t['id']]);
            $db->prepare('DELETE FROM tickets WHERE id = ?')->execute([$t['id']]);
        }
    }

    public function test_create_ticket_requires_subject(): void
    {
        $r = $this->post($this->portalClient(), '/portal/tickets/create', [
            'subject'     => '',    // empty — should fail validation
            'description' => 'Body without subject.',
        ]);

        // Should stay on form or redirect back with an error
        $body = (string) $r->getBody();
        $this->assertFalse(
            str_contains($body, 'Thank you') || str_contains($body, 'was created'),
            'Empty subject should not create a ticket'
        );
    }

    // ── View own ticket ───────────────────────────────────────────────────────

    public function test_portal_user_can_view_own_ticket(): void
    {
        $r = $this->get($this->portalClient(), '/portal/tickets/' . DatabaseSeeder::$ticketId);
        $this->assertOk($r);
        $this->assertSee('[TEST] Test Ticket', $r);
    }

    public function test_portal_ticket_view_shows_reply_form(): void
    {
        $r = $this->get($this->portalClient(), '/portal/tickets/' . DatabaseSeeder::$ticketId);
        $this->assertSee('Reply', $r);
    }

    // ── Comment ───────────────────────────────────────────────────────────────

    public function test_portal_user_can_add_comment_to_own_ticket(): void
    {
        $r = $this->post($this->portalClient(), '/portal/tickets/' . DatabaseSeeder::$ticketId . '/comment', [
            'body' => 'Portal user automated test comment.',
        ]);

        $code = $r->getStatusCode();
        $this->assertTrue($code === 200 || $code === 302, "Portal comment: expected 200/302, got $code");
    }

    // ── Isolation ─────────────────────────────────────────────────────────────

    public function test_portal_user_cannot_reach_admin_ticket_view(): void
    {
        $r = $this->get($this->portalClient(), '/admin/tickets/' . DatabaseSeeder::$ticketId, follow: false);
        $this->assertForbidden($r);
    }

    public function test_portal_user_cannot_reach_agent_ticket_view(): void
    {
        $r = $this->get($this->portalClient(), '/agent/tickets/' . DatabaseSeeder::$ticketId, follow: false);
        $this->assertForbidden($r);
    }

    public function test_portal_user_cannot_reach_other_users_portal_ticket(): void
    {
        // Create a ticket belonging to the ADMIN user
        $db = \Database::connect();
        $db->prepare(
            "INSERT INTO tickets (subject, description, created_by, status) VALUES ('[TEST] Admin ticket (portal isolation)', 'Isolation test', ?, 'open')"
        )->execute([DatabaseSeeder::$adminId]);
        $adminTicketId = (int) $db->lastInsertId();

        try {
            $r    = $this->get($this->portalClient(), "/portal/tickets/$adminTicketId", follow: false);
            $code = $r->getStatusCode();
            // Should 404 or redirect — portal users must not see others' tickets
            $this->assertTrue(
                $code === 302 || $code === 403 || $code === 404,
                "Expected 302/403/404, got $code"
            );
        } finally {
            $db->prepare('DELETE FROM ticket_timeline WHERE ticket_id = ?')->execute([$adminTicketId]);
            $db->prepare('DELETE FROM tickets WHERE id = ?')->execute([$adminTicketId]);
        }
    }

    // ── Portal user has no internal-note form ─────────────────────────────────

    public function test_portal_ticket_view_does_not_show_internal_note_option(): void
    {
        $r = $this->get($this->portalClient(), '/portal/tickets/' . DatabaseSeeder::$ticketId);
        // "Internal note" is an agent/admin concept
        $this->assertNotSee('Internal Note', $r);
    }
}

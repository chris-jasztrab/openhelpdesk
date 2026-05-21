<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * Admin control over AI-generated system notes in ticket timelines:
 * the profile toggle, the per-timeline slider, and the shared
 * `ai_notes_visible` preference that backs both.
 */
class AiTimelineNotesTest extends TestCase
{
    /** Timeline id of the AI note seeded for the slider tests. */
    private static ?int $aiNoteId = null;

    private static function db(): \PDO
    {
        return \Database::connect();
    }

    /** Insert one AI system note on the seeded test ticket (idempotent). */
    private function seedAiNote(): void
    {
        if (self::$aiNoteId !== null) {
            return;
        }
        $db = self::db();
        $db->prepare(
            "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
             VALUES (?, NULL, 'ai_classified', '[TEST] AI classified this ticket.', 1)"
        )->execute([DatabaseSeeder::$ticketId]);
        self::$aiNoteId = (int) $db->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        $db = self::db();
        if (self::$aiNoteId !== null) {
            $db->prepare('DELETE FROM ticket_timeline WHERE id = ?')->execute([self::$aiNoteId]);
            self::$aiNoteId = null;
        }
        // Drop the per-user preference so the suite leaves no residue.
        $db->prepare('DELETE FROM settings WHERE setting_key = ?')
           ->execute(['ai_notes_visible:' . DatabaseSeeder::$adminId]);
    }

    // ── Profile toggle ────────────────────────────────────────────────────────

    public function test_admin_profile_shows_ai_notes_toggle(): void
    {
        $r = $this->get($this->adminClient(), '/profile');
        $this->assertOk($r);
        $this->assertSee('AI Timeline Notes', $r);
        $this->assertSee('Show AI notes in ticket timelines', $r);
    }

    public function test_agent_profile_hides_ai_notes_toggle(): void
    {
        $r = $this->get($this->agentClient(), '/profile');
        $this->assertNotSee('AI Timeline Notes', $r, ' — only admins get this toggle');
    }

    public function test_portal_profile_hides_ai_notes_toggle(): void
    {
        $r = $this->get($this->portalClient(), '/profile');
        $this->assertNotSee('AI Timeline Notes', $r);
    }

    // ── Slider on the timeline ────────────────────────────────────────────────

    public function test_admin_ticket_view_shows_ai_note_and_slider(): void
    {
        $this->seedAiNote();
        $r = $this->get($this->adminClient(), '/admin/tickets/' . DatabaseSeeder::$ticketId);
        $this->assertOk($r);
        $this->assertSee('id="aiNotesToggle"', $r, ' — slider should appear when the ticket has an AI note');
        $this->assertSee('ld-timeline-system ld-timeline-ai', $r, ' — the AI note row should be tagged');
        $this->assertSee('[TEST] AI classified this ticket.', $r, ' — the note itself is still shown');
    }

    // ── Shared preference ─────────────────────────────────────────────────────

    public function test_endpoint_toggles_timeline_visibility(): void
    {
        $this->seedAiNote();
        $admin     = $this->adminClient();
        $ticketUrl = '/admin/tickets/' . DatabaseSeeder::$ticketId;

        // Default: visible — the list carries no hidden class.
        $this->post($admin, '/profile/ai-notes', ['visible' => '1']);
        $this->assertNotSee('list-group-flush ai-notes-hidden', $this->get($admin, $ticketUrl));

        // Hide: the timeline list is rendered with the hidden class.
        $this->post($admin, '/profile/ai-notes', ['visible' => '0']);
        $this->assertSee(
            'list-group-flush ai-notes-hidden',
            $this->get($admin, $ticketUrl),
            ' — hiding should persist to the next page load'
        );

        // Show again — and leave the preference at the visible default.
        $this->post($admin, '/profile/ai-notes', ['visible' => '1']);
        $this->assertNotSee('list-group-flush ai-notes-hidden', $this->get($admin, $ticketUrl));
    }

    public function test_ai_notes_endpoint_rejects_missing_csrf(): void
    {
        // Raw POST with no _token — the endpoint must refuse it.
        $r = $this->adminClient()->post('/profile/ai-notes', [
            'form_params'     => ['visible' => '0'],
            'allow_redirects' => false,
        ]);
        $this->assertSame(403, $r->getStatusCode());
    }
}

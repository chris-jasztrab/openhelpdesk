<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\DatabaseSeeder;
use Tests\Support\TestCase;

/**
 * Admin control over automated system notes in ticket timelines:
 * the profile toggle, the per-timeline slider, and the shared
 * `system_notes_visible` preference that backs both.
 *
 * Uses a non-AI system note (`sla_paused`) so the slider is exercised
 * independently of the AI-notes feature, which has its own test.
 */
class SystemTimelineNotesTest extends TestCase
{
    /** Timeline id of the system note seeded for the slider tests. */
    private static ?int $systemNoteId = null;

    private static function db(): \PDO
    {
        return \Database::connect();
    }

    /** Insert one non-AI system note on the seeded test ticket (idempotent). */
    private function seedSystemNote(): void
    {
        if (self::$systemNoteId !== null) {
            return;
        }
        $db = self::db();
        $db->prepare(
            "INSERT INTO ticket_timeline (ticket_id, user_id, action, details, is_internal)
             VALUES (?, NULL, 'sla_paused', '[TEST] SLA timers paused.', 1)"
        )->execute([DatabaseSeeder::$ticketId]);
        self::$systemNoteId = (int) $db->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        $db = self::db();
        if (self::$systemNoteId !== null) {
            $db->prepare('DELETE FROM ticket_timeline WHERE id = ?')->execute([self::$systemNoteId]);
            self::$systemNoteId = null;
        }
        // Drop the per-user preference so the suite leaves no residue.
        $db->prepare('DELETE FROM settings WHERE setting_key = ?')
           ->execute(['system_notes_visible:' . DatabaseSeeder::$adminId]);
    }

    // ── Profile toggle ────────────────────────────────────────────────────────

    public function test_admin_profile_shows_system_notes_toggle(): void
    {
        $r = $this->get($this->adminClient(), '/profile');
        $this->assertOk($r);
        $this->assertSee('System Timeline Notes', $r);
        $this->assertSee('Show system notes in ticket timelines', $r);
    }

    public function test_agent_profile_hides_system_notes_toggle(): void
    {
        $r = $this->get($this->agentClient(), '/profile');
        $this->assertNotSee('System Timeline Notes', $r, ' — only admins get this toggle');
    }

    public function test_portal_profile_hides_system_notes_toggle(): void
    {
        $r = $this->get($this->portalClient(), '/profile');
        $this->assertNotSee('System Timeline Notes', $r);
    }

    // ── Slider on the timeline ────────────────────────────────────────────────

    public function test_admin_ticket_view_shows_system_note_and_slider(): void
    {
        $this->seedSystemNote();
        $r = $this->get($this->adminClient(), '/admin/tickets/' . DatabaseSeeder::$ticketId);
        $this->assertOk($r);
        $this->assertSee('id="systemNotesToggle"', $r, ' — slider should appear when the ticket has a system note');
        $this->assertSee('[TEST] SLA timers paused.', $r, ' — the note itself is still shown');
    }

    // ── Shared preference ─────────────────────────────────────────────────────

    public function test_endpoint_toggles_timeline_visibility(): void
    {
        $this->seedSystemNote();
        $admin     = $this->adminClient();
        $ticketUrl = '/admin/tickets/' . DatabaseSeeder::$ticketId;

        // Default: visible — the slider renders checked.
        $this->post($admin, '/profile/system-notes', ['visible' => '1']);
        $this->assertSee('id="systemNotesToggle" checked', $this->get($admin, $ticketUrl));

        // Hide: the slider renders unchecked on the next page load.
        $this->post($admin, '/profile/system-notes', ['visible' => '0']);
        $this->assertNotSee(
            'id="systemNotesToggle" checked',
            $this->get($admin, $ticketUrl),
            ' — hiding should persist to the next page load'
        );

        // Show again — and leave the preference at the visible default.
        $this->post($admin, '/profile/system-notes', ['visible' => '1']);
        $this->assertSee('id="systemNotesToggle" checked', $this->get($admin, $ticketUrl));
    }

    public function test_system_notes_endpoint_rejects_missing_csrf(): void
    {
        // Raw POST with no _token — the endpoint must refuse it.
        $r = $this->adminClient()->post('/profile/system-notes', [
            'form_params'     => ['visible' => '0'],
            'allow_redirects' => false,
        ]);
        $this->assertSame(403, $r->getStatusCode());
    }
}

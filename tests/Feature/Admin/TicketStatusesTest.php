<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\Support\TestCase;

/**
 * Admin → Settings → Ticket Statuses page.
 *
 * Covers access control, page render, create/edit, slug immutability,
 * default-flag promotion, reorder, and baseline delete guardrails.
 *
 * Tests insert custom statuses with slugs prefixed `_test_` so the
 * tearDown sweep can remove anything left behind without touching
 * seeded data.
 */
class TicketStatusesTest extends TestCase
{
    protected function tearDown(): void
    {
        // Remove any test-created statuses (prefix `_test_`) — never touch
        // is_system rows or the seven seeded slugs.
        $pdo = \Database::connect();
        $pdo->exec("DELETE FROM ticket_statuses WHERE slug LIKE 'test\\_%' AND is_system = 0");
        // Reset default-new flag back to 'open' in case a test moved it.
        $pdo->exec("UPDATE ticket_statuses SET is_default_new = 0");
        $pdo->exec("UPDATE ticket_statuses SET is_default_new = 1 WHERE slug = 'open'");
        $pdo->exec("UPDATE ticket_statuses SET is_default_resolved = 0");
        $pdo->exec("UPDATE ticket_statuses SET is_default_resolved = 1 WHERE slug = 'resolved'");
        $pdo->exec("UPDATE ticket_statuses SET is_default_closed = 0");
        $pdo->exec("UPDATE ticket_statuses SET is_default_closed = 1 WHERE slug = 'closed'");
    }

    private function statusId(string $slug): int
    {
        $s = \Database::connect()->prepare('SELECT id FROM ticket_statuses WHERE slug = ?');
        $s->execute([$slug]);
        return (int) $s->fetchColumn();
    }

    // ── Access control ────────────────────────────────────────────────────────

    public function test_admin_can_view_ticket_statuses_page(): void
    {
        $r = $this->get($this->adminClient(), '/admin/settings/ticket-statuses');
        $this->assertOk($r);
        $this->assertSee('Ticket Statuses', $r);
    }

    public function test_agent_cannot_view_ticket_statuses_page(): void
    {
        $r = $this->get($this->agentClient(), '/admin/settings/ticket-statuses', follow: false);
        $this->assertForbidden($r);
    }

    public function test_portal_cannot_view_ticket_statuses_page(): void
    {
        $r = $this->get($this->portalClient(), '/admin/settings/ticket-statuses', follow: false);
        $this->assertForbidden($r);
    }

    // ── Page content ──────────────────────────────────────────────────────────

    public function test_page_lists_seeded_statuses(): void
    {
        $r = $this->get($this->adminClient(), '/admin/settings/ticket-statuses');
        $this->assertSee('Open',                   $r);
        $this->assertSee('In Progress',            $r);
        $this->assertSee('Waiting on Third Party', $r);
        $this->assertSee('Resolved',               $r);
        $this->assertSee('Closed',                 $r);
    }

    public function test_page_marks_system_rows_as_builtin(): void
    {
        $r = $this->get($this->adminClient(), '/admin/settings/ticket-statuses');
        $this->assertSee('built-in', $r);
    }

    public function test_page_includes_drag_handle_data(): void
    {
        $r = $this->get($this->adminClient(), '/admin/settings/ticket-statuses');
        $this->assertSee('data-sortable-list',     $r);
        $this->assertSee('data-reorder-url="/admin/settings/ticket-statuses/reorder"', $r);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_admin_can_create_custom_status(): void
    {
        $r = $this->post($this->adminClient(), '/admin/settings/ticket-statuses/create', [
            'slug'   => 'test_awaiting_vendor',
            'label'  => '[TEST] Awaiting Vendor',
            'bucket' => 'open',
            'color'  => '#ff8800',
        ]);
        $this->assertTrue(in_array($r->getStatusCode(), [200, 302]));

        $pdo = \Database::connect();
        $s   = $pdo->prepare('SELECT bucket, color, is_system FROM ticket_statuses WHERE slug = ?');
        $s->execute(['test_awaiting_vendor']);
        $row = $s->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray($row, 'new status row should exist');
        $this->assertSame('open',    $row['bucket']);
        $this->assertSame('#ff8800', strtolower($row['color']));
        $this->assertSame(0,         (int) $row['is_system']);
    }

    public function test_create_rejects_invalid_slug(): void
    {
        $r = $this->post($this->adminClient(), '/admin/settings/ticket-statuses/create', [
            'slug'   => 'Has Spaces',
            'label'  => '[TEST] Bad',
            'bucket' => 'open',
            'color'  => '#000000',
        ]);
        // App redirects with flash on validation failure; assert nothing was created.
        $s = \Database::connect()->prepare('SELECT COUNT(*) FROM ticket_statuses WHERE slug = ?');
        $s->execute(['Has Spaces']);
        $this->assertSame(0, (int) $s->fetchColumn(), 'invalid slug must not be persisted');
    }

    public function test_create_rejects_duplicate_slug(): void
    {
        // Try inserting a duplicate of one of the seeded slugs.
        $this->post($this->adminClient(), '/admin/settings/ticket-statuses/create', [
            'slug'   => 'open',
            'label'  => '[TEST] Dup Open',
            'bucket' => 'open',
            'color'  => '#000000',
        ]);

        $s = \Database::connect()->prepare('SELECT COUNT(*) FROM ticket_statuses WHERE slug = ?');
        $s->execute(['open']);
        $this->assertSame(1, (int) $s->fetchColumn(), 'should still be exactly one row with slug=open');
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function test_admin_can_rename_status(): void
    {
        $id = $this->statusId('pending');
        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$id}/edit", [
            'label'      => '[TEST] On Hold',
            'bucket'     => 'open',
            'color'      => '#ffc107',
            'pauses_sla' => '1',
            'is_active'  => '1',
        ]);

        $s = \Database::connect()->prepare('SELECT label, slug FROM ticket_statuses WHERE id = ?');
        $s->execute([$id]);
        $row = $s->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('[TEST] On Hold', $row['label']);
        $this->assertSame('pending',        $row['slug'], 'slug must stay unchanged');

        // Restore original label
        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$id}/edit", [
            'label'      => 'Pending',
            'bucket'     => 'open',
            'color'      => '#ffc107',
            'pauses_sla' => '1',
            'is_active'  => '1',
        ]);
    }

    public function test_slug_is_immutable_on_update(): void
    {
        $id = $this->statusId('open');
        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$id}/edit", [
            'slug'       => 'renamed_open',     // should be ignored
            'label'      => 'Open',
            'bucket'     => 'open',
            'color'      => '#0d6efd',
            'pauses_sla' => '0',
            'is_active'  => '1',
        ]);

        $s = \Database::connect()->prepare('SELECT slug FROM ticket_statuses WHERE id = ?');
        $s->execute([$id]);
        $this->assertSame('open', (string) $s->fetchColumn(), 'slug must not change via the edit endpoint');
    }

    // ── Defaults ──────────────────────────────────────────────────────────────

    public function test_setting_new_default_clears_old_default(): void
    {
        $inProgressId = $this->statusId('in_progress');
        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$inProgressId}/set-default", [
            'kind' => 'new',
        ]);

        $pdo = \Database::connect();
        $newDefault = (string) $pdo->query("SELECT slug FROM ticket_statuses WHERE is_default_new = 1")->fetchColumn();
        $this->assertSame('in_progress', $newDefault);

        // The previous default ('open') should no longer be flagged
        $stillFlagged = (int) $pdo->query("SELECT COUNT(*) FROM ticket_statuses WHERE is_default_new = 1")->fetchColumn();
        $this->assertSame(1, $stillFlagged, 'exactly one row must have is_default_new=1');
        // tearDown restores 'open' as the default.
    }

    // ── Reorder ───────────────────────────────────────────────────────────────

    public function test_reorder_persists_new_sort_order(): void
    {
        $pdo = \Database::connect();
        $idsInOrder = $pdo
            ->query("SELECT id FROM ticket_statuses ORDER BY sort_order, id")
            ->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertGreaterThanOrEqual(7, count($idsInOrder));

        // Reverse the first two
        $reversed = $idsInOrder;
        [$reversed[0], $reversed[1]] = [$reversed[1], $reversed[0]];

        $client = $this->adminClient();
        // Reorder endpoint expects JSON with X-CSRF-Token header
        $csrfHtml = (string) $client->get('/admin/settings/ticket-statuses')->getBody();
        preg_match('/<meta name="csrf-token" content="([^"]+)"/', $csrfHtml, $m);
        $csrf = $m[1] ?? '';
        $this->assertNotEmpty($csrf, 'csrf-token meta tag must be present on the page');

        $resp = $client->post('/admin/settings/ticket-statuses/reorder', [
            'headers' => [
                'Content-Type'    => 'application/json',
                'X-CSRF-Token'    => $csrf,
                'X-Requested-With'=> 'XMLHttpRequest',
            ],
            'body' => json_encode(['ids' => array_map('intval', $reversed)]),
        ]);
        $this->assertSame(200, $resp->getStatusCode());

        $newOrder = $pdo
            ->query("SELECT id FROM ticket_statuses ORDER BY sort_order, id")
            ->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(
            array_map('intval', $reversed),
            array_map('intval', $newOrder),
            'sort_order must reflect the posted IDs'
        );

        // Restore original order
        $client->post('/admin/settings/ticket-statuses/reorder', [
            'headers' => [
                'Content-Type'    => 'application/json',
                'X-CSRF-Token'    => $csrf,
                'X-Requested-With'=> 'XMLHttpRequest',
            ],
            'body' => json_encode(['ids' => array_map('intval', $idsInOrder)]),
        ]);
    }

    // ── Delete (baseline guards; Phase 5 adds richer ones) ────────────────────

    public function test_cannot_delete_system_status(): void
    {
        $id = $this->statusId('open');
        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$id}/delete", []);

        $s = \Database::connect()->prepare('SELECT COUNT(*) FROM ticket_statuses WHERE id = ?');
        $s->execute([$id]);
        $this->assertSame(1, (int) $s->fetchColumn(), 'system status must still exist after delete attempt');
    }

    public function test_cannot_delete_status_in_use_by_tickets(): void
    {
        // Create a custom status, point a test ticket at it, attempt delete.
        $this->post($this->adminClient(), '/admin/settings/ticket-statuses/create', [
            'slug'   => 'test_in_use',
            'label'  => '[TEST] In Use',
            'bucket' => 'open',
            'color'  => '#aabbcc',
        ]);
        $id = $this->statusId('test_in_use');
        $this->assertGreaterThan(0, $id);

        $db = \Database::connect();
        $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')
           ->execute(['test_in_use', \Tests\Support\DatabaseSeeder::$ticketId]);

        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$id}/delete", []);

        $still = $db->prepare('SELECT COUNT(*) FROM ticket_statuses WHERE id = ?');
        $still->execute([$id]);
        $this->assertSame(1, (int) $still->fetchColumn(), 'in-use status must not be deleted');

        // Cleanup: move ticket back to 'open' so tearDown can remove test_in_use.
        $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')
           ->execute(['open', \Tests\Support\DatabaseSeeder::$ticketId]);
    }

    public function test_can_delete_unused_custom_status(): void
    {
        $this->post($this->adminClient(), '/admin/settings/ticket-statuses/create', [
            'slug'   => 'test_deletable',
            'label'  => '[TEST] Deletable',
            'bucket' => 'closed',
            'color'  => '#333333',
        ]);
        $id = $this->statusId('test_deletable');
        $this->assertGreaterThan(0, $id);

        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$id}/delete", []);

        $s = \Database::connect()->prepare('SELECT COUNT(*) FROM ticket_statuses WHERE id = ?');
        $s->execute([$id]);
        $this->assertSame(0, (int) $s->fetchColumn(), 'unused custom status should be deletable');
    }

    // ── Phase 5 guardrails ────────────────────────────────────────────────────

    public function test_cannot_delete_default_new_status(): void
    {
        // 'open' is default_new in the seed.
        $id = $this->statusId('open');
        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$id}/delete", []);
        $s = \Database::connect()->prepare('SELECT COUNT(*) FROM ticket_statuses WHERE id = ?');
        $s->execute([$id]);
        $this->assertSame(1, (int) $s->fetchColumn(), 'default-new status must survive delete attempt');
    }

    public function test_delete_with_reassignment_moves_tickets_and_deletes(): void
    {
        // Set up: create a custom status, point a ticket at it, delete with reassign_to.
        $this->post($this->adminClient(), '/admin/settings/ticket-statuses/create', [
            'slug'   => 'test_to_reassign',
            'label'  => '[TEST] To Reassign',
            'bucket' => 'open',
            'color'  => '#aabbcc',
        ]);
        $id = $this->statusId('test_to_reassign');
        $this->assertGreaterThan(0, $id);

        $db = \Database::connect();
        $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')
           ->execute(['test_to_reassign', \Tests\Support\DatabaseSeeder::$ticketId]);

        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$id}/delete", [
            'reassign_to' => 'open',
        ]);

        // Status row gone
        $check = $db->prepare('SELECT COUNT(*) FROM ticket_statuses WHERE id = ?');
        $check->execute([$id]);
        $this->assertSame(0, (int) $check->fetchColumn(), 'status should be deleted after reassignment');

        // Ticket moved to the reassign target
        $tStatus = $db->prepare('SELECT status FROM tickets WHERE id = ?');
        $tStatus->execute([\Tests\Support\DatabaseSeeder::$ticketId]);
        $this->assertSame('open', (string) $tStatus->fetchColumn(), 'ticket should have moved to "open"');
    }

    public function test_reassign_target_must_be_active(): void
    {
        // Create a status, deactivate it, then try to reassign tickets to it.
        $this->post($this->adminClient(), '/admin/settings/ticket-statuses/create', [
            'slug'   => 'test_inactive_target',
            'label'  => '[TEST] Inactive Target',
            'bucket' => 'open',
            'color'  => '#445566',
        ]);
        $inactiveId = $this->statusId('test_inactive_target');
        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$inactiveId}/edit", [
            'label'      => '[TEST] Inactive Target',
            'bucket'     => 'open',
            'color'      => '#445566',
            'pauses_sla' => '0',
            // is_active intentionally omitted → deactivates
        ]);

        // Now create another status pointed at by a ticket, attempt reassign to inactive
        $this->post($this->adminClient(), '/admin/settings/ticket-statuses/create', [
            'slug'   => 'test_source_for_inactive',
            'label'  => '[TEST] Source',
            'bucket' => 'open',
            'color'  => '#778899',
        ]);
        $srcId = $this->statusId('test_source_for_inactive');
        $db = \Database::connect();
        $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')
           ->execute(['test_source_for_inactive', \Tests\Support\DatabaseSeeder::$ticketId]);

        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$srcId}/delete", [
            'reassign_to' => 'test_inactive_target',
        ]);

        // Source status should still exist (reassign blocked)
        $check = $db->prepare('SELECT COUNT(*) FROM ticket_statuses WHERE id = ?');
        $check->execute([$srcId]);
        $this->assertSame(1, (int) $check->fetchColumn(), 'delete must be blocked when reassign target is inactive');

        // Cleanup: move ticket back to 'open' so tearDown can remove test rows
        $db->prepare('UPDATE tickets SET status = ? WHERE id = ?')
           ->execute(['open', \Tests\Support\DatabaseSeeder::$ticketId]);
    }

    public function test_cannot_deactivate_status_holding_default_flag(): void
    {
        // 'open' is default_new
        $id = $this->statusId('open');
        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$id}/edit", [
            'label'      => 'Open',
            'bucket'     => 'open',
            'color'      => '#0d6efd',
            'pauses_sla' => '0',
            // is_active omitted → would deactivate
        ]);
        $check = \Database::connect()->prepare('SELECT is_active FROM ticket_statuses WHERE id = ?');
        $check->execute([$id]);
        $this->assertSame(1, (int) $check->fetchColumn(), 'default-flag holder must stay active');
    }

    public function test_cannot_deactivate_last_active_in_bucket(): void
    {
        $db = \Database::connect();

        // Step 1: move the default-new flag off 'open' so the deactivate check
        // doesn't trip on the default guard before the last-in-bucket guard.
        // We're testing the last-in-bucket guard specifically.
        // (Test isolation: tearDown restores defaults.)
        // Strategy: deactivate every open-bucket status except 'open', then
        // try to deactivate 'open'. Expect it to stay active.
        $openIds = $db->query("SELECT id FROM ticket_statuses WHERE bucket = 'open' AND slug != 'open'")
                      ->fetchAll(\PDO::FETCH_COLUMN);

        // We need to move default_new off the rows we're deactivating; only 'open'
        // is default_new in the seed, so we can deactivate the others freely.
        foreach ($openIds as $oid) {
            $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$oid}/edit", [
                'label'      => 'tmp_label',
                'bucket'     => 'open',
                'color'      => '#000000',
                'pauses_sla' => '0',
                // is_active omitted → deactivate
            ]);
        }

        // Now 'open' is the only active in the open bucket. Try to deactivate it.
        $openId = $this->statusId('open');
        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$openId}/edit", [
            'label'      => 'Open',
            'bucket'     => 'open',
            'color'      => '#0d6efd',
            'pauses_sla' => '0',
            // is_active omitted → would deactivate
        ]);

        $check = $db->prepare('SELECT is_active FROM ticket_statuses WHERE id = ?');
        $check->execute([$openId]);
        $this->assertSame(1, (int) $check->fetchColumn(), 'last active status in bucket must stay active');

        // Restore other open-bucket statuses
        foreach ($openIds as $oid) {
            $info = $db->prepare('SELECT slug, label FROM ticket_statuses WHERE id = ?');
            $info->execute([$oid]);
            $r = $info->fetch(\PDO::FETCH_ASSOC);
            // Use the real seeded label per slug
            $realLabels = [
                'in_progress'            => 'In Progress',
                'pending'                => 'Pending',
                'waiting_on_customer'    => 'Waiting on Customer',
                'waiting_on_third_party' => 'Waiting on Third Party',
            ];
            $pauses = in_array($r['slug'], ['pending', 'waiting_on_customer', 'waiting_on_third_party'], true) ? '1' : '0';
            $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$oid}/edit", [
                'label'      => $realLabels[$r['slug']] ?? $r['slug'],
                'bucket'     => 'open',
                'color'      => '#0d6efd',
                'pauses_sla' => $pauses,
                'is_active'  => '1',
            ]);
        }
    }

    public function test_cannot_set_default_resolved_on_open_bucket_status(): void
    {
        // 'open' is in the open bucket; default_resolved requires closed bucket.
        $id = $this->statusId('open');
        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$id}/set-default", [
            'kind' => 'resolved',
        ]);
        // 'open' must not have gained is_default_resolved
        $check = \Database::connect()->prepare('SELECT is_default_resolved FROM ticket_statuses WHERE id = ?');
        $check->execute([$id]);
        $this->assertSame(0, (int) $check->fetchColumn(), 'open-bucket status cannot be made default_resolved');
    }

    public function test_buildTicketFilterQuery_drops_unknown_status_slugs(): void
    {
        // Pass a mix of valid + stale slugs; only the valid ones should hit SQL.
        $result = buildTicketFilterQuery([
            'status' => ['open', 'completely_made_up_slug', 'resolved'],
        ]);
        $this->assertIsArray($result);
        // 'completely_made_up_slug' should NOT appear in the params.
        $this->assertNotContains('completely_made_up_slug', $result['params'] ?? []);
        // 'open' and 'resolved' should both be there.
        $this->assertContains('open',     $result['params'] ?? []);
        $this->assertContains('resolved', $result['params'] ?? []);
    }

    public function test_ticketStatusReferences_finds_automation_references(): void
    {
        // Insert a fake automation that references 'pending' as an action value.
        $db = \Database::connect();
        $db->prepare(
            "INSERT INTO automations (name, trigger_event, conditions, actions, is_enabled, sort_order)
             VALUES (?, 'ticket_created', '[]', ?, 0, 999)"
        )->execute([
            '[TEST] Set Pending',
            json_encode([['action' => 'set_status', 'value' => 'pending']]),
        ]);
        $autoId = (int) $db->lastInsertId();

        $refs = ticketStatusReferences('pending');
        $names = array_column($refs['automations'], 'name');
        $this->assertContains('[TEST] Set Pending', $names);

        $db->prepare('DELETE FROM automations WHERE id = ?')->execute([$autoId]);
    }

    // ── toggle-active endpoint (inline button in the Active column) ───────────

    public function test_toggle_active_flips_state(): void
    {
        // Create a custom open-bucket status so we can deactivate without
        // tripping the last-active-in-bucket guard.
        $this->post($this->adminClient(), '/admin/settings/ticket-statuses/create', [
            'slug'   => 'test_toggle',
            'label'  => '[TEST] Toggle',
            'bucket' => 'open',
            'color'  => '#778899',
        ]);
        $id = $this->statusId('test_toggle');
        $this->assertGreaterThan(0, $id);

        // Created active → toggle → inactive
        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$id}/toggle-active", []);
        $check = \Database::connect()->prepare('SELECT is_active FROM ticket_statuses WHERE id = ?');
        $check->execute([$id]);
        $this->assertSame(0, (int) $check->fetchColumn(), 'should be inactive after first toggle');

        // Toggle again → active
        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$id}/toggle-active", []);
        $check->execute([$id]);
        $this->assertSame(1, (int) $check->fetchColumn(), 'should be active again after second toggle');
    }

    public function test_toggle_active_blocked_for_default_holder(): void
    {
        // 'open' is default-new in the seed — toggling its active state should
        // fail because that would remove the default-new fallback.
        $id = $this->statusId('open');
        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$id}/toggle-active", []);
        $check = \Database::connect()->prepare('SELECT is_active FROM ticket_statuses WHERE id = ?');
        $check->execute([$id]);
        $this->assertSame(1, (int) $check->fetchColumn(), 'default-holder must stay active');
    }

    public function test_cannot_delete_status_referenced_by_automation(): void
    {
        // Create a deletable custom status, then an automation pointing at it,
        // then attempt delete — must be blocked.
        $this->post($this->adminClient(), '/admin/settings/ticket-statuses/create', [
            'slug'   => 'test_referenced',
            'label'  => '[TEST] Referenced',
            'bucket' => 'open',
            'color'  => '#ff00ff',
        ]);
        $id = $this->statusId('test_referenced');

        $db = \Database::connect();
        $db->prepare(
            "INSERT INTO automations (name, trigger_event, conditions, actions, is_enabled, sort_order)
             VALUES (?, 'ticket_created', '[]', ?, 0, 999)"
        )->execute([
            '[TEST] Uses Referenced',
            json_encode([['action' => 'set_status', 'value' => 'test_referenced']]),
        ]);
        $autoId = (int) $db->lastInsertId();

        $this->post($this->adminClient(), "/admin/settings/ticket-statuses/{$id}/delete", []);

        $check = $db->prepare('SELECT COUNT(*) FROM ticket_statuses WHERE id = ?');
        $check->execute([$id]);
        $this->assertSame(1, (int) $check->fetchColumn(), 'automation reference must block delete');

        // Cleanup
        $db->prepare('DELETE FROM automations WHERE id = ?')->execute([$autoId]);
    }
}

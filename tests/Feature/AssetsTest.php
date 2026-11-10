<?php

declare(strict_types=1);

namespace Tests\Feature;

use Assets;
use Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Asset inventory core (v2.210.0): tag numbering and the ticket ⇄ asset link
 * that must write to both sides (ticket timeline + asset history).
 *
 * Every test runs inside a transaction that is rolled back.
 */
class AssetsTest extends TestCase
{
    private PDO $db;
    private int $userId;

    protected function setUp(): void
    {
        require_once ROOT_DIR . '/src/Assets.php';
        $this->db = Database::connect();
        $user = $this->db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
        if ($user === false) {
            $this->markTestSkipped('Needs an admin.');
        }
        $this->userId = (int) $user;
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function asset(string $tag): int
    {
        $this->db->exec("INSERT INTO asset_categories (name) VALUES ('AT Laptops')");
        $cat = (int) $this->db->lastInsertId();
        $this->db->prepare("INSERT INTO asset_models (name, category_id) VALUES ('AT Latitude', ?)")->execute([$cat]);
        $model = (int) $this->db->lastInsertId();
        $status = (int) $this->db->query('SELECT id FROM asset_statuses ORDER BY id LIMIT 1')->fetchColumn();
        $this->db->prepare('INSERT INTO assets (asset_tag, name, model_id, status_id) VALUES (?, ?, ?, ?)')
            ->execute([$tag, 'AT-HOST', $model, $status]);
        return (int) $this->db->lastInsertId();
    }

    private function ticket(): int
    {
        $this->db->prepare("INSERT INTO tickets (subject, description, created_by, status) VALUES ('AT ticket', 'x', ?, 'open')")
            ->execute([$this->userId]);
        return (int) $this->db->lastInsertId();
    }

    private function n(string $sql, array $params): int
    {
        $s = $this->db->prepare($sql);
        $s->execute($params);
        return (int) $s->fetchColumn();
    }

    public function test_next_tag_zero_fills_skips_taken_tags_and_advances_counter(): void
    {
        $this->db->exec("REPLACE INTO settings (setting_key, setting_value) VALUES ('asset_tag_prefix', 'AT-'), ('asset_tag_zerofill', '4'), ('asset_tag_next', '7')");
        $this->asset('AT-0007'); // already taken by hand

        $this->assertSame('AT-0008', Assets::nextTag($this->db));
        $this->assertSame('AT-0009', Assets::nextTag($this->db));
        $this->assertSame('10', $this->db->query("SELECT setting_value FROM settings WHERE setting_key = 'asset_tag_next'")->fetchColumn());
    }

    public function test_resolve_scan_matches_snipe_url_our_url_tag_padded_tag_and_serial(): void
    {
        $id = $this->asset('09926');
        $this->db->prepare('UPDATE assets SET snipeit_id = 999026, serial = ?, deleted_at = NULL WHERE id = ?')->execute(['SN-AT-26', $id]);
        $this->assertSame($id, Assets::resolveScan($this->db, 'https://inventory.wpl.ca/hardware/999026'));
        $this->assertSame($id, Assets::resolveScan($this->db, 'https://helpdesk.wpl.ca/inventory/' . $id));
        $this->assertSame($id, Assets::resolveScan($this->db, ' 09926 '));
        $this->assertSame($id, Assets::resolveScan($this->db, '9926'), 'digits zero-pad to the configured tag width');
        $this->assertSame($id, Assets::resolveScan($this->db, 'SN-AT-26'));
        $this->assertNull(Assets::resolveScan($this->db, 'https://inventory.wpl.ca/hardware/999027'));
        $this->assertNull(Assets::resolveScan($this->db, 'nope'));
        $this->db->prepare('UPDATE assets SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
        $this->assertNull(Assets::resolveScan($this->db, '09926'), 'deleted assets never match');
    }

    public function test_audit_stamps_dates_logs_history_and_flags_location_mismatch(): void
    {
        $id = $this->asset('09927');
        $this->db->exec("INSERT INTO locations (name) VALUES ('AT Here')");
        $here = (int) $this->db->lastInsertId();
        $this->db->exec("INSERT INTO locations (name) VALUES ('AT There')");
        $there = (int) $this->db->lastInsertId();
        $this->db->prepare('UPDATE assets SET location_id = ? WHERE id = ?')->execute([$there, $id]);

        $d = Assets::audit($this->db, $id, $here, null, $this->userId);
        $this->assertTrue($d['mismatch']);
        $this->assertSame('AT Here', $d['at']['name']);
        $row = $this->db->query("SELECT last_audit_at, next_audit_date, location_id FROM assets WHERE id = {$id}")->fetch();
        $this->assertNotNull($row['last_audit_at']);
        $this->assertSame(date('Y-m-d', strtotime('+' . (int) getSetting('asset_audit_interval_months', '12') . ' months')), $row['next_audit_date']);
        $this->assertSame($there, (int) $row['location_id'], 'audit never moves the asset');
        $this->assertSame(1, $this->n("SELECT COUNT(*) FROM asset_history WHERE asset_id = ? AND action = 'audit' AND user_id = ?", [$id, $this->userId]));

        $d = Assets::audit($this->db, $id, $there, '2030-01-15', $this->userId);
        $this->assertArrayNotHasKey('mismatch', $d);
        $this->assertSame('2030-01-15', $this->db->query("SELECT next_audit_date FROM assets WHERE id = {$id}")->fetchColumn());
    }

    public function test_link_writes_map_timeline_and_history_and_is_idempotent(): void
    {
        $ticket = $this->ticket();
        $asset  = $this->asset('AT-LINK');

        $this->assertTrue(Assets::link($this->db, $ticket, $asset, $this->userId));
        $this->assertFalse(Assets::link($this->db, $ticket, $asset, $this->userId), 'second link is a no-op');

        $this->assertSame(1, $this->n('SELECT COUNT(*) FROM ticket_asset_map WHERE ticket_id = ? AND asset_id = ?', [$ticket, $asset]));
        $this->assertSame(1, $this->n("SELECT COUNT(*) FROM ticket_timeline WHERE ticket_id = ? AND action = 'asset_linked' AND is_internal = 1", [$ticket]));
        $this->assertSame(1, $this->n("SELECT COUNT(*) FROM asset_history WHERE asset_id = ? AND action = 'ticket_linked'", [$asset]));

        $s = $this->db->prepare("SELECT details FROM ticket_timeline WHERE ticket_id = ? AND action = 'asset_linked'");
        $s->execute([$ticket]);
        $this->assertSame('Linked asset AT-LINK — AT Latitude (AT-HOST)', $s->fetchColumn());
    }

    public function test_unlink_removes_map_and_logs_both_sides(): void
    {
        $ticket = $this->ticket();
        $asset  = $this->asset('AT-UNLINK');
        Assets::link($this->db, $ticket, $asset, $this->userId);

        $this->assertTrue(Assets::unlink($this->db, $ticket, $asset, $this->userId));
        $this->assertFalse(Assets::unlink($this->db, $ticket, $asset, $this->userId), 'nothing left to unlink');

        $this->assertSame(0, $this->n('SELECT COUNT(*) FROM ticket_asset_map WHERE ticket_id = ? AND asset_id = ?', [$ticket, $asset]));
        $this->assertSame(1, $this->n("SELECT COUNT(*) FROM ticket_timeline WHERE ticket_id = ? AND action = 'asset_unlinked'", [$ticket]));
        $this->assertSame(1, $this->n("SELECT COUNT(*) FROM asset_history WHERE asset_id = ? AND action = 'ticket_unlinked'", [$asset]));
    }

    public function test_history_keeps_imported_actor_and_timestamp(): void
    {
        $asset = $this->asset('AT-HIST');
        $id = Assets::logHistory($this->db, $asset, 'checkout', ['to' => 'jdoe'], null, 'superadmin', '2024-03-01 09:30:00');

        $s = $this->db->prepare('SELECT user_id, actor_name, details, created_at FROM asset_history WHERE id = ?');
        $s->execute([$id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($row['user_id']);
        $this->assertSame('superadmin', $row['actor_name']);
        $this->assertSame('{"to":"jdoe"}', $row['details']);
        $this->assertSame('2024-03-01 09:30:00', $row['created_at']);
    }
}

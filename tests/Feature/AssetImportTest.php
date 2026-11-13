<?php

declare(strict_types=1);

namespace Tests\Feature;

use AssetDigest;
use AssetImport;
use Assets;
use Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Inventory bulk edit, CSV import and the expiry digest (v2.237.0–2.239.0).
 *
 * Every test runs inside a transaction that is rolled back.
 */
class AssetImportTest extends TestCase
{
    private PDO $db;
    private int $userId;
    private int $model;
    private int $status;

    protected function setUp(): void
    {
        require_once ROOT_DIR . '/src/AssetImport.php';
        require_once ROOT_DIR . '/src/AssetDigest.php';
        $this->db = Database::connect();
        $user = $this->db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
        if ($user === false) {
            $this->markTestSkipped('Needs an admin.');
        }
        $this->userId = (int) $user;
        $this->db->beginTransaction();
        $this->db->exec("INSERT INTO asset_categories (name) VALUES ('AI Laptops')");
        $cat = (int) $this->db->lastInsertId();
        $this->db->prepare("INSERT INTO asset_models (name, model_number, category_id) VALUES ('AI ThinkPad', 'AI-21ML', ?)")->execute([$cat]);
        $this->model  = (int) $this->db->lastInsertId();
        $this->status = (int) $this->db->query('SELECT id FROM asset_statuses ORDER BY id LIMIT 1')->fetchColumn();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function asset(string $tag, array $more = []): int
    {
        $row  = ['asset_tag' => $tag, 'model_id' => $this->model, 'status_id' => $this->status] + $more;
        $cols = array_keys($row);
        $this->db->prepare('INSERT INTO assets (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
            ->execute(array_values($row));
        return (int) $this->db->lastInsertId();
    }

    private function row(string $sql, array $params): array
    {
        $s = $this->db->prepare($sql);
        $s->execute($params);
        return $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function test_auto_map_matches_the_list_export_and_the_sample_file(): void
    {
        // The headers the inventory list's CSV button writes.
        $map = AssetImport::autoMap(['Tag', 'Name', 'Serial', 'Model', 'Model No.', 'Category', 'Manufacturer', 'Status', 'Location',
            'Assigned To', 'Purchase Date', 'Cost', 'Warranty (months)', 'EOL', 'Notes']);
        $this->assertSame('Tag', $map['asset_tag']);
        $this->assertSame('Model No.', $map['model_number']);
        $this->assertSame('Cost', $map['purchase_cost']);
        $this->assertSame('EOL', $map['eol_date']);
        $this->assertNull($map['supplier']);

        $parsed = AssetImport::parse(ROOT_DIR . '/public/samples/import-assets-sample.csv');
        $this->assertIsArray($parsed);
        [$headers, $rows] = $parsed;
        $this->assertCount(5, $rows);
        $this->assertNotContains(null, AssetImport::autoMap($headers), 'every field finds its column in the sample file');
    }

    public function test_plan_then_apply_creates_updates_skips_and_reports_problems(): void
    {
        $existing = $this->asset('AI-EXIST', ['serial' => 'AI-OLD']);
        $map  = ['asset_tag' => 'Tag', 'serial' => 'Serial', 'model' => 'Model', 'category' => 'Category', 'manufacturer' => 'Maker',
                 'purchase_date' => 'Bought', 'purchase_cost' => 'Cost'];
        $rows = [
            ['Tag' => '', 'Serial' => 'AI-S1', 'Model' => 'ai thinkpad', 'Bought' => '3/15/2026', 'Cost' => '$1,489.00'],
            ['Tag' => 'AI-EXIST', 'Serial' => 'AI-S2', 'Model' => 'AI ThinkPad'],
            ['Tag' => 'AI-NEW1', 'Serial' => '', 'Model' => 'AI Brand New', 'Category' => 'AI Laptops', 'Maker' => 'AI Maker'],
            ['Tag' => '', 'Serial' => '', 'Model' => 'AI Unknown', 'Category' => ''],
            ['Tag' => 'ai-new1', 'Serial' => '', 'Model' => 'AI ThinkPad'],
        ];

        $skip = AssetImport::plan($this->db, $rows, $map, ['default_status_id' => $this->status, 'existing' => 'skip']);
        $this->assertSame(['create' => 2, 'update' => 0, 'skip' => 1, 'error' => 2], $skip['counts']);
        $this->assertSame('2026-03-15', $skip['rows'][0]['set']['purchase_date']);
        $this->assertSame('1489.00', $skip['rows'][0]['set']['purchase_cost']);
        $this->assertSame($this->model, $skip['rows'][0]['model'], 'model names match whatever the case');
        $this->assertCount(1, $skip['new']['models']);
        $this->assertStringContainsString('no category', $skip['rows'][3]['why']);
        $this->assertStringContainsString('also on line 4', $skip['rows'][4]['why']);

        $plan = AssetImport::plan($this->db, $rows, $map, ['default_status_id' => $this->status, 'existing' => 'update']);
        $this->assertSame(1, $plan['counts']['update']);
        $this->assertSame([2, 1], AssetImport::apply($this->db, $plan, $this->userId));

        $this->assertSame('AI-S2', $this->row('SELECT serial FROM assets WHERE id = ?', [$existing])['serial']);
        $this->assertSame(1, (int) $this->row("SELECT COUNT(*) AS n FROM asset_history WHERE asset_id = ? AND action = 'update'", [$existing])['n']);
        $auto = $this->row("SELECT asset_tag, purchase_cost, status_id FROM assets WHERE serial = 'AI-S1'", []);
        $this->assertNotSame('', $auto['asset_tag']);
        $this->assertSame('1489.00', $auto['purchase_cost']);
        $this->assertSame($this->status, (int) $auto['status_id']);
        $new = $this->row("SELECT m.name, mf.name AS maker FROM assets a JOIN asset_models m ON m.id = a.model_id
                           LEFT JOIN asset_manufacturers mf ON mf.id = m.manufacturer_id WHERE a.asset_tag = 'AI-NEW1'", []);
        $this->assertSame(['name' => 'AI Brand New', 'maker' => 'AI Maker'], $new);
    }

    public function test_bulk_update_changes_only_assets_that_differ_and_logs_each(): void
    {
        $a = $this->asset('AI-B1', ['order_number' => 'PO-1']);
        $b = $this->asset('AI-B2', ['order_number' => 'PO-9', 'purchase_date' => '2026-01-05']);
        $gone = $this->asset('AI-B3', ['deleted_at' => '2026-01-01 00:00:00']);

        $n = Assets::bulkUpdate($this->db, [$a, $b, $gone], ['order_number' => 'PO-9', 'purchase_date' => '2026-01-05'], $this->userId);

        $this->assertSame(1, $n, 'B2 already had both values; the deleted asset is never touched');
        $this->assertSame(['order_number' => 'PO-9', 'purchase_date' => '2026-01-05'], $this->row('SELECT order_number, purchase_date FROM assets WHERE id = ?', [$a]));
        $this->assertNull($this->row('SELECT order_number FROM assets WHERE id = ?', [$gone])['order_number']);
        $h = json_decode((string) $this->row("SELECT details FROM asset_history WHERE asset_id = ? AND action = 'update'", [$a])['details'], true);
        $this->assertSame(['old' => 'PO-1', 'new' => 'PO-9'], $h['changes']['order_number']);
    }

    public function test_digest_schedule_slots(): void
    {
        // 2026-11-13 is a Friday.
        $monthly = ['enabled' => true, 'frequency' => 'monthly', 'day' => 15, 'last_sent' => '2026-10-15'];
        $this->assertSame('2026-10-15', AssetDigest::lastSlot($monthly, '2026-11-13'));
        $this->assertSame('2026-11-15', AssetDigest::nextSlot($monthly, '2026-11-13'));
        $this->assertFalse(AssetDigest::isDue($monthly, '2026-11-14'));
        $this->assertTrue(AssetDigest::isDue($monthly, '2026-11-15'));
        $this->assertTrue(AssetDigest::isDue($monthly, '2026-11-18'), 'a missed day is caught up');

        $weekly = ['enabled' => true, 'frequency' => 'weekly', 'day' => 1, 'last_sent' => '2026-11-09'];
        $this->assertSame('2026-11-09', AssetDigest::lastSlot($weekly, '2026-11-13'));
        $this->assertSame('2026-11-16', AssetDigest::nextSlot($weekly, '2026-11-13'));
        $this->assertFalse(AssetDigest::isDue($weekly, '2026-11-13'));
        $this->assertTrue(AssetDigest::isDue($weekly, '2026-11-16'));
        $this->assertFalse(AssetDigest::isDue(['enabled' => false] + $weekly, '2026-11-16'));
    }

    public function test_digest_lists_warranties_ending_and_just_ended(): void
    {
        $this->asset('AI-D-SOON', ['warranty_end' => '2030-06-10']);
        $this->asset('AI-D-ENDED', ['warranty_end' => '2030-05-28']);
        $this->asset('AI-D-LATER', ['warranty_end' => '2031-06-01']);
        $this->asset('AI-D-AUDIT', ['next_audit_date' => '2030-05-01']);

        $d    = AssetDigest::data($this->db, 30, '2030-05-20', '2030-06-01');
        $tags = static fn(array $rows): array => array_values(array_filter(array_column($rows, 'asset_tag'), static fn($t) => str_starts_with($t, 'AI-D-')));
        $this->assertSame(['AI-D-SOON'], $tags($d['ending']));
        $this->assertSame(['AI-D-ENDED'], $tags($d['expired']));
        $this->assertGreaterThanOrEqual(1, $d['audit_overdue']);

        [$subject, $html, $text] = AssetDigest::message($d);
        $this->assertStringContainsString('Inventory digest', $subject);
        $this->assertStringContainsString('AI-D-SOON', $html);
        $this->assertStringContainsString('AI-D-ENDED', $text);
        $this->assertStringNotContainsString('AI-D-LATER', $html);
    }
}

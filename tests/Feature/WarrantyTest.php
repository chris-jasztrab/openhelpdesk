<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database;
use PDO;
use PHPUnit\Framework\TestCase;
use Warranty;

/**
 * Warranty lookup (v2.230.0): serial clean-up for the shapes found in the
 * Snipe-IT import, the Lenovo / Dell answer parsers (fixtures trimmed from
 * real answers), and saving a result onto an asset. No network calls.
 */
class WarrantyTest extends TestCase
{
    protected function setUp(): void
    {
        require_once ROOT_DIR . '/src/Warranty.php';
    }

    public function test_vendor_and_serial_clean_up(): void
    {
        $this->assertSame('lenovo', Warranty::vendor('LENOVO'));
        $this->assertSame('dell', Warranty::vendor('Dell Inc.'));
        $this->assertNull(Warranty::vendor('HP'));
        $this->assertNull(Warranty::vendor(null));

        $this->assertSame('MJ0ABCDE', Warranty::serial('lenovo', ' mj0abcde '), 'trimmed and upper-cased');
        $this->assertSame('MJ0ABCDE', Warranty::serial('lenovo', '1S20XWS0AB00MJ0ABCDE'), '1S + machine type model + serial');
        $this->assertSame('V5AB1234', Warranty::serial('lenovo', '61B1JAR1USV5AB1234'), 'model + serial without 1S');
        $this->assertNull(Warranty::serial('lenovo', 'N/A'));
        $this->assertSame('PF4NRA05', Warranty::serial('lenovo', 'PF-4NRA05'), 'typed hyphen');
        $this->assertSame('VTHM3190', Warranty::serial('lenovo', '1S61A6HAR3USVTHM3190/VTHM3190'), 'scan with a separator');
        $this->assertSame('BKL1234', Warranty::serial('dell', 'BKL-1234'));
        $this->assertSame('BKL1234', Warranty::serial('dell', 'bkl1234'));
        $this->assertNull(Warranty::serial('dell', 'VN0ABC123456789012345678'), 'a monitor PPID is not a service tag');
        $this->assertNull(Warranty::serial(null, 'MJ0ABCDE'));
    }

    public function test_lenovo_support_answer(): void
    {
        $ok = Warranty::parseLenovoSupport(['code' => 0, 'msg' => ['desc' => 'Success'], 'data' => [
            'baseWarranties'     => [['name' => '1Y Depot', 'startDate' => '2015-06-18', 'endDate' => '2016-06-17']],
            'upgradeWarranties'  => [['name' => '3Y Onsite', 'startDate' => '2016-06-18', 'endDate' => '2018-06-17']],
            'contractWarranties' => [],
        ]]);
        $this->assertSame(['ok' => true, 'start' => '2015-06-18', 'end' => '2018-06-17', 'service' => '1Y Depot; 3Y Onsite'], $ok);

        $bad = Warranty::parseLenovoSupport(['code' => 100, 'msg' => ['desc' => 'Machine Type and Serial Number Not Found in IBase']]);
        $this->assertFalse($bad['ok']);
        $this->assertTrue($bad['final'], 'an unknown serial waits 30 days instead of retrying daily');
        $this->assertStringContainsString('Not Found', $bad['error']);
    }

    public function test_dell_answer_skips_non_hardware_entitlements(): void
    {
        $r = Warranty::parseDell(['serviceTag' => 'BKL1234', 'invalid' => false, 'entitlements' => [
            ['startDate' => '2022-03-10T00:00:00Z', 'endDate' => '2025-03-11T04:59:59Z', 'serviceLevelCode' => 'ND', 'serviceLevelDescription' => 'Next Business Day Onsite'],
            ['startDate' => '2022-03-10T00:00:00Z', 'endDate' => '2030-01-01T00:00:00Z', 'serviceLevelCode' => 'D', 'serviceLevelDescription' => 'Dell Digitial Delivery'],
        ]]);
        $this->assertSame(['ok' => true, 'start' => '2022-03-10', 'end' => '2025-03-11', 'service' => 'Next Business Day Onsite'], $r);
        $this->assertFalse(Warranty::parseDell(['serviceTag' => 'X', 'invalid' => true])['ok']);
        $this->assertFalse(Warranty::parseDell(['serviceTag' => 'X', 'entitlements' => []])['ok'], 'no coverage rows = no warranty on record');
    }

    public function test_scan_ids_cover_lenovo_with_a_serial_and_due_only_skips_fresh_answers(): void
    {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $db->exec("INSERT INTO asset_categories (name) VALUES ('WT Scan')");
            $cat  = (int) $db->lastInsertId();
            $mk   = static function (string $mfName) use ($db, $cat): int {
                $db->prepare('INSERT INTO asset_manufacturers (name) VALUES (?)')->execute([$mfName]);
                $db->prepare('INSERT INTO asset_models (name, category_id, manufacturer_id) VALUES (?, ?, ?)')->execute([$mfName . ' model', $cat, (int) $db->lastInsertId()]);
                return (int) $db->lastInsertId();
            };
            $lenovo = $mk('WT Lenovo Inc');
            $hp     = $mk('WT Hewlett');
            $add    = static function (string $tag, int $model, ?string $serial, ?string $checked = null) use ($db): int {
                $db->prepare('INSERT INTO assets (asset_tag, model_id, status_id, serial, warranty_checked_at) VALUES (?, ?, (SELECT id FROM asset_statuses ORDER BY id LIMIT 1), ?, ?)')
                   ->execute([$tag, $model, $serial, $checked]);
                return (int) $db->lastInsertId();
            };
            $never  = $add('WT-S1', $lenovo, 'MJ0AAAA1');
            $fresh  = $add('WT-S2', $lenovo, 'MJ0AAAA2', date('Y-m-d H:i:s'));
            $stale  = $add('WT-S3', $lenovo, 'MJ0AAAA3', date('Y-m-d H:i:s', strtotime('-40 days')));
            $noSer  = $add('WT-S4', $lenovo, null);
            $other  = $add('WT-S5', $hp, 'CND12345');

            $all = Warranty::scanIds($db, false);
            $due = Warranty::scanIds($db, true);
            foreach ([$never, $fresh, $stale] as $id) {
                $this->assertContains($id, $all);
            }
            $this->assertNotContains($noSer, $all, 'no serial, nothing to ask');
            $this->assertNotContains($other, $all, 'HP has no lookup');
            $this->assertContains($never, $due);
            $this->assertContains($stale, $due, 'answered over 30 days ago');
            $this->assertNotContains($fresh, $due, 'answered today');

            $this->assertSame(['checked' => 0, 'found' => 0, 'failed' => 0, 'stalled' => false], Warranty::refreshIds($db, [$noSer, $other, 0], 1),
                'ids that are not candidates are ignored without a network call');
        } finally {
            $db->rollBack();
        }
    }

    public function test_save_writes_the_asset_and_logs_a_changed_end_date(): void
    {
        $db = Database::connect();
        $db->beginTransaction();
        try {
            $db->exec("INSERT INTO asset_categories (name) VALUES ('WT Laptops')");
            $db->prepare("INSERT INTO asset_models (name, category_id) VALUES ('WT ThinkPad', ?)")->execute([(int) $db->lastInsertId()]);
            $model = (int) $db->lastInsertId();
            $db->prepare('INSERT INTO assets (asset_tag, model_id, status_id) VALUES (?, ?, (SELECT id FROM asset_statuses ORDER BY id LIMIT 1))')->execute(['WT-1', $model]);
            $id = (int) $db->lastInsertId();
            $row = static fn() => $db->query("SELECT warranty_start, warranty_end, warranty_service, warranty_checked_at, warranty_error FROM assets WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
            $hist = static fn() => (int) $db->query("SELECT COUNT(*) FROM asset_history WHERE asset_id = $id AND action = 'update'")->fetchColumn();

            Warranty::save($db, $id, ['ok' => false, 'final' => false, 'error' => 'Lenovo answered HTTP 503']);
            $this->assertNull($row()['warranty_checked_at'], 'a transient failure is retried on the next run');
            $this->assertSame('Lenovo answered HTTP 503', $row()['warranty_error']);

            Warranty::save($db, $id, ['ok' => true, 'start' => '2023-01-01', 'end' => '2026-01-01', 'service' => '3Y Premier']);
            $r = $row();
            $this->assertSame(['2023-01-01', '2026-01-01', '3Y Premier', null], [$r['warranty_start'], $r['warranty_end'], $r['warranty_service'], $r['warranty_error']]);
            $this->assertNotNull($r['warranty_checked_at']);
            $this->assertSame(1, $hist());

            Warranty::save($db, $id, ['ok' => true, 'start' => '2023-01-01', 'end' => '2026-01-01', 'service' => '3Y Premier']);
            $this->assertSame(1, $hist(), 'the same end date is not logged again');
        } finally {
            $db->rollBack();
        }
    }
}

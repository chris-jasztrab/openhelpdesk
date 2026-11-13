<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\TestCase;

/**
 * Inventory reports (v2.233.0): every tab renders, the list takes the filters
 * the reports link to, and the Age report's buckets agree with the list's
 * age= filter (two code paths, assetAgeCase() vs assetAgeWhere()).
 */
class InventoryReportsTest extends TestCase
{
    /** @dataProvider tabs */
    public function test_admin_sees_each_tab(string $query, string $text): void
    {
        $r = $this->get($this->adminClient(), '/inventory/reports?' . $query);
        $this->assertOk($r, " — $query");
        $this->assertSee($text, $r, " — $query");
    }

    public static function tabs(): array
    {
        return [
            ['tab=overview', 'Warranty expired'],
            ['tab=overview&by=location', 'Not audited'],
            ['tab=warranty&days=30', 'Ends within 30 days'],
            ['tab=audit&since=2025-01-01', 'Audited since Jan 1, 2025'],
            ['tab=age&by=model', 'Under 1 year'],
            ['tab=people&min=2', 'holding at least'],
            ['tab=activity&from=2020-01-01', 'Action'],
            ['tab=quality', 'Duplicate serial numbers'],
        ];
    }

    public function test_list_takes_the_report_filters(): void
    {
        foreach (['warranty=soon&warranty_days=30', 'warranty=error', 'audit=never', 'audit=overdue', 'audit_after=2025-01-01',
                  'age=3-4', 'missing=serial', 'manufacturer[]=none', 'model=1'] as $q) {
            $this->assertOk($this->get($this->adminClient(), '/inventory?' . $q), " — /inventory?$q");
        }
        $this->assertSee('Warranty ends', $this->get($this->adminClient(), '/inventory?warranty=expired'));
        $this->assertSee('Last audit', $this->get($this->adminClient(), '/inventory?audit=never'));
    }

    /** CSV body → rows (fgetcsv, so quoted newlines in notes stay inside their row). */
    private function csvRows(string $path): array
    {
        $h = fopen('php://memory', 'r+');
        fwrite($h, (string) $this->get($this->adminClient(), $path)->getBody());
        rewind($h);
        $rows = [];
        while (($row = fgetcsv($h, null, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function test_age_report_buckets_match_the_list_filter(): void
    {
        $csv  = $this->csvRows('/inventory/reports?tab=age&export=csv');
        $head = array_shift($csv);
        foreach (['Under 1 year' => 'lt1', '3–4 years' => '3-4', '7+ years' => '7plus', 'Unknown' => 'unknown'] as $label => $key) {
            $col = array_search($label, $head, true);
            $this->assertNotFalse($col, "CSV has a «{$label}» column");
            $fromReport = array_sum(array_map(static fn($row) => (int) $row[$col], $csv));
            $this->assertSame($fromReport, count($this->csvRows('/inventory?export=csv&age=' . $key)) - 1, "bucket {$key}: report and list agree");
        }
    }

    public function test_portal_is_kept_out(): void
    {
        $this->assertForbidden($this->get($this->portalClient(), '/inventory/reports', follow: false));
    }

    public function test_scan_needs_a_token_and_ignores_ids_that_are_not_candidates(): void
    {
        $bad = $this->adminClient()->post('/inventory/warranty/scan', ['form_params' => ['_token' => 'nope', 'ids' => [1]], 'http_errors' => false]);
        $this->assertSame(403, $bad->getStatusCode());

        $this->get($this->adminClient(), '/inventory/reports'); // primes the cached CSRF token
        $r = $this->post($this->adminClient(), '/inventory/warranty/scan', ['ids' => [0, -5]]);
        $this->assertSame(['checked' => 0, 'found' => 0, 'failed' => 0, 'stalled' => false, 'lines' => []], json_decode((string) $r->getBody(), true));
    }
}

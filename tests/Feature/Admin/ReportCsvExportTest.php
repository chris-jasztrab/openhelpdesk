<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Psr\Http\Message\ResponseInterface;
use Tests\Support\TestCase;

/**
 * ?export=csv on the six tabular admin reports.
 *
 * Exporting through the page's own route means the permission gate is shared by
 * construction — so the role assertions here are checking that claim, not just
 * re-testing ReportsTest.
 */
class ReportCsvExportTest extends TestCase
{
    /**
     * path suffix => [querystring, expected header row, expected filename prefix]
     *
     * @return array<string, array{0:string, 1:string, 2:array<int,string>, 3:string}>
     */
    public static function csvReports(): array
    {
        return [
            'agent-performance' => [
                '/admin/reports/agent-performance',
                '',
                ['Agent', 'Assigned', 'Resolved', 'Open', 'Avg First Response', 'Avg Resolution', 'SLA Compliance %'],
                'agent-performance-',
            ],
            'sla-violations' => [
                '/admin/reports/sla-violations',
                '',
                ['ID', 'Subject', 'Status', 'Priority', 'Agent', 'Breach', 'Overdue By', 'Created'],
                'sla-violations-',
            ],
            'unresolved' => [
                '/admin/reports/unresolved',
                '',
                ['ID', 'Subject', 'Status', 'Priority', 'Agent', 'SLA', 'Age', 'Created'],
                'unresolved-tickets-',
            ],
            'location' => [
                '/admin/reports/location',
                '',
                ['Location', 'Total', 'Open', 'Resolved', 'Resolution Rate %', 'Avg Resolution', 'SLA Compliance %'],
                'tickets-by-location-',
            ],
            'group-coverage' => [
                '/admin/reports/group-coverage',
                '',
                ['Ticket Type', 'Default Group', 'Member', 'Email', 'Role', 'Group Manager'],
                'group-coverage-',
            ],
            'custom' => [
                '/admin/reports/custom',
                'metric=ticket_count&group_by=type',
                ['Ticket Type', 'Ticket Count'],
                'custom-report-type-ticket_count-',
            ],
        ];
    }

    private static function url(string $path, string $qs): string
    {
        return $path . '?export=csv' . ($qs !== '' ? '&' . $qs : '');
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    /** @dataProvider csvReports */
    public function test_export_returns_a_csv_attachment(
        string $path,
        string $qs,
        array  $expectedHeader,
        string $filenamePrefix
    ): void {
        $r = $this->get($this->adminClient(), self::url($path, $qs));

        $this->assertSame(200, $r->getStatusCode(), " — $path?export=csv");
        $this->assertStringContainsString('text/csv', $r->getHeaderLine('Content-Type'), " — $path");
        $this->assertStringContainsString('charset=utf-8', $r->getHeaderLine('Content-Type'), " — $path");

        $disposition = $r->getHeaderLine('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition, " — $path");
        $this->assertStringContainsString($filenamePrefix, $disposition, " — $path filename");
        $this->assertStringContainsString('.csv"', $disposition, " — $path filename");
    }

    /** @dataProvider csvReports */
    public function test_export_starts_with_the_excel_bom(
        string $path,
        string $qs
    ): void {
        $body = (string) $this->get($this->adminClient(), self::url($path, $qs))->getBody();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, " — $path is missing the UTF-8 BOM");
    }

    /** @dataProvider csvReports */
    public function test_export_first_row_is_the_expected_header(
        string $path,
        string $qs,
        array  $expectedHeader
    ): void {
        $rows = $this->csvRows($this->get($this->adminClient(), self::url($path, $qs)));

        $this->assertNotEmpty($rows, " — $path produced no CSV rows at all");
        $this->assertSame($expectedHeader, $rows[0], " — $path header row");
    }

    /** @dataProvider csvReports */
    public function test_export_body_is_not_html(string $path, string $qs): void
    {
        $body = (string) $this->get($this->adminClient(), self::url($path, $qs))->getBody();

        // Guards against the route falling through to render() and handing the
        // user a .csv full of markup.
        $this->assertStringNotContainsString('<!DOCTYPE', $body, " — $path");
        $this->assertStringNotContainsString('<html', $body, " — $path");
        $this->assertStringNotContainsString('</body>', $body, " — $path");
    }

    /** @dataProvider csvReports */
    public function test_every_data_row_has_the_same_column_count_as_the_header(
        string $path,
        string $qs,
        array  $expectedHeader
    ): void {
        $rows   = $this->csvRows($this->get($this->adminClient(), self::url($path, $qs)));
        $header = array_shift($rows);
        $width  = count($header);

        foreach ($rows as $i => $row) {
            $this->assertCount($width, $row, " — $path data row $i is ragged");
        }
        $this->assertSame(count($expectedHeader), $width);
    }

    // ── Filters carry through ─────────────────────────────────────────────────

    /**
     * The four date-windowed reports name their file after the resolved window,
     * so an explicit ?from/&to must show up there.  If the export ignored the
     * querystring this would keep the default last-30-days window instead.
     *
     * @dataProvider datedReports
     */
    public function test_explicit_from_and_to_reach_the_export(string $path, string $extraQs): void
    {
        $qs = 'from=2026-01-05&to=2026-01-19' . ($extraQs !== '' ? '&' . $extraQs : '');
        $r  = $this->get($this->adminClient(), $path . '?export=csv&' . $qs);

        $this->assertSame(200, $r->getStatusCode(), " — $path");
        $this->assertStringContainsString(
            '2026-01-05-to-2026-01-19.csv',
            $r->getHeaderLine('Content-Disposition'),
            " — $path did not carry ?from/&to into the export"
        );
    }

    /** @dataProvider datedReports */
    public function test_range_preset_reaches_the_export(string $path, string $extraQs): void
    {
        $qs = 'range=today' . ($extraQs !== '' ? '&' . $extraQs : '');
        $r  = $this->get($this->adminClient(), $path . '?export=csv&' . $qs);

        $this->assertSame(200, $r->getStatusCode(), " — $path");
        // range=today resolves from == to, whatever "today" is on the server.
        $this->assertMatchesRegularExpression(
            '/(\d{4}-\d{2}-\d{2})-to-\1\.csv/',
            $r->getHeaderLine('Content-Disposition'),
            " — $path did not carry ?range=today into the export"
        );
    }

    /** @return array<string, array{0:string,1:string}> */
    public static function datedReports(): array
    {
        return [
            'agent-performance' => ['/admin/reports/agent-performance', ''],
            'sla-violations'    => ['/admin/reports/sla-violations', ''],
            'location'          => ['/admin/reports/location', ''],
            'custom'            => ['/admin/reports/custom', 'metric=ticket_count&group_by=type'],
        ];
    }

    /**
     * unresolved names its file after today, so prove filter carry-through from
     * the data instead: an age filter must actually narrow the row set.
     */
    public function test_unresolved_export_respects_the_age_filter(): void
    {
        $all = $this->csvRows($this->get($this->adminClient(), '/admin/reports/unresolved?export=csv'));
        $old = $this->csvRows($this->get($this->adminClient(), '/admin/reports/unresolved?export=csv&age=4'));

        $this->assertNotEmpty($all, 'No unresolved tickets at all — this assertion would be vacuous.');
        $this->assertLessThanOrEqual(
            count($all),
            count($old),
            'age=4 returned more rows than the unfiltered export.'
        );

        // Every id in the filtered file must exist in the unfiltered one.
        $this->assertEmpty(
            array_diff($this->idColumn($old), $this->idColumn($all)),
            'Filtered unresolved export contained ids absent from the unfiltered export.'
        );
    }

    /**
     * The export deliberately ignores LIMIT: it must not be capped at one page.
     */
    public function test_unresolved_export_ignores_pagination(): void
    {
        $page1 = $this->csvRows($this->get($this->adminClient(), '/admin/reports/unresolved?export=csv&per_page=10&page=1'));
        $plain = $this->csvRows($this->get($this->adminClient(), '/admin/reports/unresolved?export=csv'));

        $this->assertSame(
            count($plain),
            count($page1),
            'per_page/page changed the export size — the export should ignore pagination.'
        );
    }

    /**
     * ?export=csv without metric/group_by must NOT emit a header-only CSV; the
     * route deliberately falls through to the HTML page instead.
     */
    public function test_custom_report_without_a_metric_does_not_export(): void
    {
        $r = $this->get($this->adminClient(), '/admin/reports/custom?export=csv');

        $this->assertSame(200, $r->getStatusCode());
        $this->assertStringNotContainsString('text/csv', $r->getHeaderLine('Content-Type'));
        $this->assertStringNotContainsString('attachment', $r->getHeaderLine('Content-Disposition'));
    }

    // ── The button on the page ────────────────────────────────────────────────

    /** @dataProvider csvReports */
    public function test_page_renders_an_export_button_pointing_at_export_csv(
        string $path,
        string $qs
    ): void {
        $r = $this->get($this->adminClient(), $path . ($qs !== '' ? '?' . $qs : ''));
        $this->assertOk($r, " — $path");
        $this->assertStringContainsString('export=csv', (string) $r->getBody(), " — $path has no export button");
    }

    /** The button must drop pagination/ajax params (reportExportCsvUrl contract). */
    public function test_export_button_drops_pagination_params(): void
    {
        $r    = $this->get($this->adminClient(), '/admin/reports/unresolved?per_page=10&page=2');
        $body = (string) $r->getBody();

        $this->assertMatchesRegularExpression(
            '/href="[^"]*export=csv[^"]*"/',
            $body,
            'No export=csv link on the unresolved report.'
        );
        preg_match_all('/href="([^"]*export=csv[^"]*)"/', $body, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $href) {
            $this->assertStringNotContainsString('per_page=', $href, "Export href kept per_page: $href");
            $this->assertStringNotContainsString('page=', str_replace('per_page=', '', $href), "Export href kept page: $href");
            $this->assertStringNotContainsString('ajax=', $href, "Export href kept ajax: $href");
        }
    }

    // ── Role enforcement (the shared-gate claim) ──────────────────────────────

    /** @dataProvider csvReports */
    public function test_agent_cannot_export(string $path, string $qs): void
    {
        $r = $this->get($this->agentClient(), self::url($path, $qs), follow: false);
        $this->assertForbidden($r, " — agent on $path?export=csv");
        $this->assertStringNotContainsString('text/csv', $r->getHeaderLine('Content-Type'), " — $path");
        $this->assertStringNotContainsString('attachment', $r->getHeaderLine('Content-Disposition'), " — $path");
    }

    /** @dataProvider csvReports */
    public function test_portal_user_cannot_export(string $path, string $qs): void
    {
        $r = $this->get($this->portalClient(), self::url($path, $qs), follow: false);
        $this->assertForbidden($r, " — portal on $path?export=csv");
        $this->assertStringNotContainsString('text/csv', $r->getHeaderLine('Content-Type'), " — $path");
    }

    /** @dataProvider csvReports */
    public function test_guest_cannot_export(string $path, string $qs): void
    {
        $r = $this->get($this->guestClient(), self::url($path, $qs), follow: false);
        $this->assertForbidden($r, " — guest on $path?export=csv");
        $this->assertStringNotContainsString('text/csv', $r->getHeaderLine('Content-Type'), " — $path");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array<int, array<int, string>> */
    private function csvRows(ResponseInterface $r): array
    {
        $body = (string) $r->getBody();
        if (str_starts_with($body, "\xEF\xBB\xBF")) {
            $body = substr($body, 3);
        }

        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $body);
        rewind($fh);

        $rows = [];
        while (($row = fgetcsv($fh)) !== false) {
            if ($row === [null] || $row === ['']) {
                continue;
            }
            $rows[] = array_map(static fn($v) => (string) $v, $row);
        }
        fclose($fh);

        return $rows;
    }

    /** @param array<int, array<int, string>> $rows */
    private function idColumn(array $rows): array
    {
        array_shift($rows);
        return array_map(static fn($r) => $r[0] ?? '', $rows);
    }
}

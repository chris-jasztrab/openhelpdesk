<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\Support\TestCase;

/**
 * Admin reports — every report page must return HTTP 200 for an admin and
 * HTTP 403 (or redirect) for agents and portal users.
 */
class ReportsTest extends TestCase
{
    // ── Admin can access every report ─────────────────────────────────────────

    /** @dataProvider reportPaths */
    public function test_admin_can_view_report(string $path): void
    {
        $r = $this->get($this->adminClient(), $path);
        $this->assertOk($r, " — $path");
    }

    public static function reportPaths(): array
    {
        return [
            ['/admin/reports'],
            ['/admin/reports/agent-performance'],
            ['/admin/reports/response-times'],
            ['/admin/reports/sla'],
            ['/admin/reports/unresolved'],
            ['/admin/reports/ticket-volume'],
            ['/admin/reports/lifecycle'],
            ['/admin/reports/location'],
            ['/admin/reports/csat'],
            ['/admin/reports/workload'],
            ['/admin/reports/trends'],
            ['/admin/reports/fcr'],
            ['/admin/reports/custom'],
        ];
    }

    // ── Reports have meaningful content ───────────────────────────────────────

    public function test_reports_dashboard_shows_nav_links(): void
    {
        $r = $this->get($this->adminClient(), '/admin/reports');
        $this->assertSee('report', $r);
    }

    public function test_agent_performance_report_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/reports/agent-performance');
        $this->assertOk($r);
    }

    public function test_csat_report_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/reports/csat');
        $this->assertOk($r);
        $this->assertSee('CSAT', $r);
    }

    // ── Role enforcement ──────────────────────────────────────────────────────

    /** @dataProvider reportPaths */
    public function test_agent_cannot_access_reports(string $path): void
    {
        $r = $this->get($this->agentClient(), $path, follow: false);
        $this->assertForbidden($r, " — agent blocked from $path");
    }

    /** @dataProvider reportPaths */
    public function test_portal_cannot_access_reports(string $path): void
    {
        $r = $this->get($this->portalClient(), $path, follow: false);
        $this->assertForbidden($r, " — portal blocked from $path");
    }

    // ── Audit log ─────────────────────────────────────────────────────────────

    public function test_audit_log_loads(): void
    {
        $r = $this->get($this->adminClient(), '/admin/audit-log');
        $this->assertOk($r);
        $this->assertSee('Audit', $r);
    }

    public function test_audit_log_filters_by_action(): void
    {
        $r = $this->get($this->adminClient(), '/admin/audit-log?action=login');
        $this->assertOk($r);
    }

    public function test_audit_log_filters_by_date_range(): void
    {
        $r = $this->get($this->adminClient(), '/admin/audit-log?date_from=2020-01-01&date_to=2099-12-31');
        $this->assertOk($r);
    }

    public function test_agent_cannot_access_audit_log(): void
    {
        $r = $this->get($this->agentClient(), '/admin/audit-log', follow: false);
        $this->assertForbidden($r);
    }

    public function test_portal_cannot_access_audit_log(): void
    {
        $r = $this->get($this->portalClient(), '/admin/audit-log', follow: false);
        $this->assertForbidden($r);
    }
}

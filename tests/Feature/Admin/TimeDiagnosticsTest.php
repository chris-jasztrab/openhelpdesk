<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Database;
use Tests\Support\TestCase;
use TimeDiagnostics;

/**
 * The Server Time diagnostics page.
 *
 * Assertions are deliberately environment-independent: whether the clocks on a
 * given machine actually agree is exactly what varies between the dev box, CI
 * and production, so the suite pins the *shape* of the report and the internal
 * consistency of its verdict rather than any particular timezone.
 */
class TimeDiagnosticsTest extends TestCase
{
    private const PATH = '/admin/settings/time';

    protected function setUp(): void
    {
        require_once ROOT_DIR . '/src/TimeDiagnostics.php';
    }

    // ── Offset formatting ─────────────────────────────────────────────────────

    public function test_offset_formatting_covers_sign_zero_and_half_hours(): void
    {
        $this->assertSame('+00:00', TimeDiagnostics::formatOffset(0));
        $this->assertSame('-04:00', TimeDiagnostics::formatOffset(-4 * 3600));
        $this->assertSame('+02:00', TimeDiagnostics::formatOffset(2 * 3600));
        $this->assertSame('+05:30', TimeDiagnostics::formatOffset(5 * 3600 + 1800), ' — India is a half-hour offset');
        $this->assertSame('+05:45', TimeDiagnostics::formatOffset(5 * 3600 + 2700), ' — Nepal is a quarter-hour offset');
        $this->assertSame('-03:30', TimeDiagnostics::formatOffset(-(3 * 3600 + 1800)), ' — Newfoundland is negative and half-hour');
        $this->assertSame('—', TimeDiagnostics::formatOffset(null));
    }

    // ── Report shape ──────────────────────────────────────────────────────────

    public function test_collect_reports_every_clock(): void
    {
        $diag = TimeDiagnostics::collect(Database::connect());

        foreach (['php', 'php_cli', 'mysql', 'os', 'app', 'checks', 'summary'] as $key) {
            $this->assertArrayHasKey($key, $diag, " — the report must include '$key'");
        }

        $this->assertNotSame('', $diag['php']['timezone'], ' — PHP always knows its own timezone');
        $this->assertIsInt($diag['php']['offset']);
        $this->assertIsInt($diag['php']['timestamp']);
        $this->assertNotSame('', $diag['php']['utc'], ' — the UTC cross-check needs a value');
    }

    public function test_mysql_readings_are_present_and_typed(): void
    {
        $mysql = TimeDiagnostics::collect(Database::connect())['mysql'];

        $this->assertNotSame('', $mysql['now'], ' — MySQL NOW() must be readable');
        $this->assertNotSame('', $mysql['utc'], ' — MySQL UTC_TIMESTAMP() must be readable');
        $this->assertIsInt($mysql['offset'], ' — the offset is measured, not parsed from a name');
        $this->assertIsBool($mysql['tz_tables']);
    }

    /**
     * The CLI probe shells out and is expected to be unavailable in plenty of
     * environments. It must degrade rather than throw or report a bogus zone.
     */
    public function test_cli_probe_degrades_cleanly_when_unavailable(): void
    {
        $cli = TimeDiagnostics::collect(Database::connect())['php_cli'];

        $this->assertIsBool($cli['available']);
        if ($cli['available']) {
            $this->assertNotSame('', (string) $cli['timezone']);
            $this->assertIsInt($cli['offset']);
        } else {
            $this->assertNotSame('', $cli['reason'], ' — an unavailable probe must say why');
            $this->assertNull($cli['timezone']);
        }
    }

    // ── Verdict consistency ───────────────────────────────────────────────────

    public function test_every_check_is_well_formed(): void
    {
        $checks = TimeDiagnostics::collect(Database::connect())['checks'];

        $this->assertNotEmpty($checks, ' — the page is pointless with no checks');
        foreach ($checks as $check) {
            foreach (['id', 'label', 'status', 'detail', 'fix'] as $key) {
                $this->assertArrayHasKey($key, $check);
            }
            $this->assertContains($check['status'], ['ok', 'warn', 'fail', 'info'], " — '{$check['id']}' has an unknown status");
            $this->assertNotSame('', $check['detail'], " — '{$check['id']}' must explain itself");
            // A problem the admin can't act on is just noise.
            if ($check['status'] === 'fail') {
                $this->assertNotSame('', $check['fix'], " — '{$check['id']}' fails but offers no remedy");
            }
        }
    }

    public function test_the_two_load_bearing_checks_are_always_run(): void
    {
        $ids = array_column(TimeDiagnostics::collect(Database::connect())['checks'], 'id');

        $this->assertContains('mysql_php_offset', $ids, ' — the MySQL/PHP invariant is the one that corrupts stored dates');
        $this->assertContains('clock_drift', $ids, ' — drift is a distinct failure from timezone mismatch');
    }

    public function test_the_summary_agrees_with_the_checks(): void
    {
        $diag     = TimeDiagnostics::collect(Database::connect());
        $statuses = array_column($diag['checks'], 'status');
        $summary  = $diag['summary'];

        $expected = in_array('fail', $statuses, true) ? 'fail'
            : (in_array('warn', $statuses, true) ? 'warn' : 'ok');

        $this->assertSame($expected, $summary['status'], ' — the banner must reflect the worst check');
        $this->assertSame(count(array_filter($statuses, fn ($s) => $s === 'fail')), $summary['failures']);
        $this->assertNotSame('', $summary['headline']);
    }

    /** 'info' rows are context, not problems, and must never colour the banner. */
    public function test_info_checks_alone_do_not_raise_an_alarm(): void
    {
        $diag = TimeDiagnostics::collect(Database::connect());
        $nonInfo = array_filter(array_column($diag['checks'], 'status'), fn ($s) => $s !== 'info' && $s !== 'ok');

        if ($nonInfo === []) {
            $this->assertSame('ok', $diag['summary']['status'], ' — info and pass rows must leave the verdict green');
        } else {
            $this->assertContains($diag['summary']['status'], ['warn', 'fail']);
        }
    }

    // ── Route ─────────────────────────────────────────────────────────────────

    public function test_admin_can_open_the_page(): void
    {
        $r = $this->get($this->adminClient(), self::PATH);
        $this->assertOk($r);
        $this->assertSee('Server Time', $r);
        $this->assertSee('What each clock reports', $r);
    }

    public function test_the_page_is_reachable_from_the_settings_nav(): void
    {
        $this->assertSee(self::PATH, $this->get($this->adminClient(), '/admin/settings/business-hours'));
    }

    public function test_a_requester_cannot_open_the_page(): void
    {
        $this->assertForbidden($this->get($this->portalClient(), self::PATH, false));
    }
}

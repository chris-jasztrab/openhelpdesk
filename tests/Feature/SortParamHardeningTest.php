<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\TestCase;

/**
 * Sortable list pages must survive an array-valued `sort` / `dir` parameter.
 *
 * PHP parses `?sort[]=x` into an array, and in PHP 8 using an array as an array
 * offset is a fatal `TypeError: Illegal offset type`. Critically, neither of the
 * idioms that *look* defensive suppresses it:
 *
 *     $sortableColumns[$_GET['sort'] ?? 'created_at'] ?? 'default'   // throws
 *     isset($sortableColumns[$_GET['sort'] ?? ''])                   // throws
 *
 * `strtolower($_GET['dir'] ?? 'desc')` fails the same way — TypeError on an
 * array argument. So every sortable list in the app returned a 500 for a
 * one-character change to the query string, on both `sort` and `dir`.
 *
 * This was never an injection risk: the column whitelists are real and `$dir` is
 * coerced to the literals ASC/DESC. The requests simply crashed before reaching
 * the whitelist. The fix routes these reads through `requestScalar()`, which
 * returns the default for anything non-scalar.
 *
 * These assertions are deliberately loose about *which* non-error response comes
 * back — a list page, a redirect and a 403 are all fine depending on the role.
 * The only unacceptable answer is 5xx.
 */
class SortParamHardeningTest extends TestCase
{
    /** Hostile query strings covering both parameters, together and apart. */
    private const HOSTILE = [
        'sort-array'      => 'sort[]=x',
        'dir-array'       => 'dir[]=x',
        'both-arrays'     => 'sort[]=x&dir[]=y',
        'nested-array'    => 'sort[a][b]=x',
        'sort-array-valid-dir' => 'sort[]=created_at&dir=asc',
    ];

    /** @return iterable<string, array{string, string}> */
    public static function sortableEndpoints(): iterable
    {
        // role => paths that build an ORDER BY from `sort`/`dir`
        $endpoints = [
            'admin' => ['/admin/tickets', '/admin/tickets/export', '/admin/users'],
            'agent' => ['/agent/tickets', '/agent/tickets/export'],
            'portal' => ['/portal/tickets'],
        ];

        foreach ($endpoints as $role => $paths) {
            foreach ($paths as $path) {
                foreach (self::HOSTILE as $label => $qs) {
                    yield "{$role} {$path} [{$label}]" => [$role, $path . '?' . $qs];
                }
            }
        }
    }

    /**
     * @dataProvider sortableEndpoints
     */
    public function test_array_valued_sort_and_dir_do_not_crash(string $role, string $path): void
    {
        $client = match ($role) {
            'admin'  => $this->adminClient(),
            'agent'  => $this->agentClient(),
            'portal' => $this->portalClient(),
        };

        $code = $this->get($client, $path, false)->getStatusCode();

        $this->assertLessThan(
            500,
            $code,
            "{$path} returned {$code} — an array-valued sort/dir must fall back to the "
            . 'default column, not raise a TypeError'
        );
    }

    /**
     * The hardening must not have broken ordinary sorting: a valid sort key still
     * has to change the order, otherwise requestScalar() could be swallowing
     * every value and quietly pinning one column.
     */
    public function test_valid_sort_still_orders_results(): void
    {
        $asc  = $this->get($this->adminClient(), '/admin/tickets/export?sort=id&dir=asc');
        $desc = $this->get($this->adminClient(), '/admin/tickets/export?sort=id&dir=desc');

        $this->assertOk($asc);
        $this->assertOk($desc);

        $ids = static function (string $csv): array {
            $rows = array_values(array_filter(explode("\n", trim($csv))));
            array_shift($rows); // header
            return array_map(static fn(string $r): string => strtok($r, ','), $rows);
        };

        $ascIds  = $ids((string) $asc->getBody());
        $descIds = $ids((string) $desc->getBody());

        if (count($ascIds) < 2) {
            $this->markTestSkipped('Needs at least two visible tickets to compare orderings.');
        }

        $this->assertSame(
            $ascIds,
            array_reverse($descIds),
            'sort=id&dir=asc should be the exact reverse of dir=desc'
        );
    }
}

<?php

declare(strict_types=1);

/**
 * Real-time wallboard / dashboard.
 *
 * Computes the live metrics behind /agent/wallboard. Every query is scoped
 * through ticketStaffVisibilitySql() so a user only ever sees counts for
 * tickets they are allowed to open — the wallboard can never leak a confidential
 * or out-of-group ticket, even in an aggregate.
 *
 * Per-user customisation (which widgets, in what order, default filters, refresh
 * interval) is a JSON blob in the settings table under `dashboard_config:{uid}`,
 * mirroring how ticket column preferences are stored (getUserColumns).
 *
 * Metrics are split into two families:
 *   - "current state" widgets (open counts, by-status, workload, queues) honour
 *     the entity filters (location/group/type/priority) only.
 *   - "historical" widgets (volume trend, avg response, CSAT) additionally honour
 *     the time-range filter.
 */
class Dashboard
{
    /**
     * Every widget the wallboard can show. `default` flags the ones enabled for
     * a brand-new user — the standard set seen on most helpdesk wallboards.
     *
     * kind:  'kpi' single number | 'chart' Chart.js canvas | 'list' HTML list
     * size:  Bootstrap column span hint used by the front-end grid (3/4/6/12).
     */
    public static function catalog(): array
    {
        return [
            // ── KPI stat cards ────────────────────────────────────────────
            'open'           => ['title' => 'Open tickets',        'kind' => 'kpi',  'size' => 3, 'default' => true,  'group' => 'Counts'],
            'unassigned'     => ['title' => 'Unassigned',          'kind' => 'kpi',  'size' => 3, 'default' => true,  'group' => 'Counts'],
            'breached'       => ['title' => 'SLA breached',        'kind' => 'kpi',  'size' => 3, 'default' => true,  'group' => 'Counts', 'sla' => true],
            'at_risk'        => ['title' => 'SLA at risk',         'kind' => 'kpi',  'size' => 3, 'default' => false, 'group' => 'Counts', 'sla' => true],
            'due_today'      => ['title' => 'Due today',           'kind' => 'kpi',  'size' => 3, 'default' => false, 'group' => 'Counts'],
            'created_today'  => ['title' => 'Created today',       'kind' => 'kpi',  'size' => 3, 'default' => true,  'group' => 'Counts'],
            'resolved_today' => ['title' => 'Resolved today',      'kind' => 'kpi',  'size' => 3, 'default' => true,  'group' => 'Counts'],
            'avg_response'   => ['title' => 'Avg first response',  'kind' => 'kpi',  'size' => 3, 'default' => false, 'group' => 'Counts'],
            'csat'           => ['title' => 'CSAT',                'kind' => 'kpi',  'size' => 3, 'default' => false, 'group' => 'Counts'],
            'sla_compliance' => ['title' => 'SLA compliance',      'kind' => 'kpi',  'size' => 3, 'default' => false, 'group' => 'Counts', 'sla' => true],

            // ── Charts ────────────────────────────────────────────────────
            'by_status'      => ['title' => 'Open by status',      'kind' => 'chart', 'size' => 4, 'default' => true,  'group' => 'Breakdowns', 'chart' => 'doughnut'],
            'by_priority'    => ['title' => 'Open by priority',    'kind' => 'chart', 'size' => 4, 'default' => true,  'group' => 'Breakdowns', 'chart' => 'bar'],
            'by_type'        => ['title' => 'Open by type',        'kind' => 'chart', 'size' => 4, 'default' => false, 'group' => 'Breakdowns', 'chart' => 'bar'],
            'by_group'       => ['title' => 'Open by group',       'kind' => 'chart', 'size' => 4, 'default' => false, 'group' => 'Breakdowns', 'chart' => 'bar'],
            'by_location'    => ['title' => 'Open by location',    'kind' => 'chart', 'size' => 4, 'default' => false, 'group' => 'Breakdowns', 'chart' => 'bar'],
            'volume_trend'   => ['title' => 'Created vs resolved', 'kind' => 'chart', 'size' => 8, 'default' => true,  'group' => 'Trends',    'chart' => 'line'],

            // ── Tables / live lists ───────────────────────────────────────
            'agent_workload'   => ['title' => 'Agent workload',       'kind' => 'list', 'size' => 6, 'default' => true,  'group' => 'Live'],
            'unassigned_queue' => ['title' => 'Unassigned queue',     'kind' => 'list', 'size' => 6, 'default' => true,  'group' => 'Live'],
            'recent_activity'  => ['title' => 'Recently updated',     'kind' => 'list', 'size' => 6, 'default' => false, 'group' => 'Live'],
        ];
    }

    /** Default config for a user who has never customised their wallboard. */
    public static function defaultConfig(): array
    {
        $widgets = [];
        foreach (self::catalog() as $id => $meta) {
            if ($meta['default']) {
                $widgets[] = $id;
            }
        }
        return [
            'widgets'     => $widgets,
            'filters'     => ['location_id' => 0, 'group_id' => 0, 'type_id' => 0, 'priority_id' => 0, 'range' => 30],
            'interval'    => 30,
            'heights'     => [],   // widgetId => pixel height override (defaults applied client-side per kind)
            'columnCount' => self::DEFAULT_COLUMNS,
            'columns'     => self::normalizeColumns(null, $widgets, self::DEFAULT_COLUMNS),
        ];
    }

    /** Allowed range for a custom widget height, in pixels. */
    private const MIN_WIDGET_HEIGHT = 90;
    private const MAX_WIDGET_HEIGHT = 900;

    /** Column-count bounds for the independent-column layout. */
    private const MIN_COLUMNS = 1;   // collapses to a single stack on narrow screens anyway
    private const MAX_COLUMNS = 4;
    private const DEFAULT_COLUMNS = 2;

    /**
     * Distribute the enabled widgets into exactly $count columns.
     *
     * Honours an incoming layout ($rawCols) where given, keeping each widget in
     * its column and original order; overflow columns merge into the last kept
     * one and short layouts are padded. Any enabled widget not present in the
     * incoming layout (new widget, or legacy config with no columns at all) is
     * appended to the shortest column so the result is always complete and
     * deduplicated.
     *
     * @param mixed    $rawCols  array-of-arrays from the client, or null
     * @param string[] $widgets  the enabled widget ids (source of truth for membership)
     */
    private static function normalizeColumns($rawCols, array $widgets, int $count): array
    {
        $count = max(self::MIN_COLUMNS, min(self::MAX_COLUMNS, $count));
        $cols = array_fill(0, $count, []);
        $placed = [];

        if (is_array($rawCols)) {
            $ci = 0;
            foreach ($rawCols as $col) {
                if (!is_array($col)) { continue; }
                $target = min($ci, $count - 1);   // overflow merges into the last column
                foreach ($col as $w) {
                    if (is_string($w) && in_array($w, $widgets, true) && !in_array($w, $placed, true)) {
                        $cols[$target][] = $w;
                        $placed[] = $w;
                    }
                }
                $ci++;
            }
        }

        // Place any still-unplaced enabled widget into the shortest column.
        foreach ($widgets as $w) {
            if (in_array($w, $placed, true)) { continue; }
            $shortest = 0;
            for ($i = 1; $i < $count; $i++) {
                if (count($cols[$i]) < count($cols[$shortest])) { $shortest = $i; }
            }
            $cols[$shortest][] = $w;
            $placed[] = $w;
        }

        return $cols;
    }

    /** Read a user's saved config, validated and merged over the defaults. */
    public static function userConfig(int $userId): array
    {
        $defaults = self::defaultConfig();
        $json = getSetting("dashboard_config:{$userId}", '');
        if ($json === '') {
            return $defaults;
        }
        $saved = json_decode($json, true);
        if (!is_array($saved)) {
            return $defaults;
        }
        return self::sanitizeConfig($saved, $defaults);
    }

    /** Validate + persist a user's config blob. Returns the stored value. */
    public static function saveUserConfig(int $userId, array $incoming): array
    {
        $clean = self::sanitizeConfig($incoming, self::defaultConfig());
        setSetting("dashboard_config:{$userId}", json_encode($clean));
        return $clean;
    }

    /** Coerce arbitrary input into a safe config shape. */
    private static function sanitizeConfig(array $in, array $defaults): array
    {
        $valid = array_keys(self::catalog());

        $widgets = [];
        if (isset($in['widgets']) && is_array($in['widgets'])) {
            foreach ($in['widgets'] as $w) {
                if (is_string($w) && in_array($w, $valid, true) && !in_array($w, $widgets, true)) {
                    $widgets[] = $w;
                }
            }
        }
        if ($widgets === []) {
            $widgets = $defaults['widgets'];
        }

        $f = is_array($in['filters'] ?? null) ? $in['filters'] : [];
        $filters = [
            'location_id' => (int) ($f['location_id'] ?? 0),
            'group_id'    => (int) ($f['group_id'] ?? 0),
            'type_id'     => (int) ($f['type_id'] ?? 0),
            'priority_id' => (int) ($f['priority_id'] ?? 0),
            'range'       => max(1, min(365, (int) ($f['range'] ?? 30))),
        ];

        $interval = (int) ($in['interval'] ?? 30);
        if (!in_array($interval, [10, 15, 30, 60, 120], true)) {
            $interval = 30;
        }

        $heights = [];
        if (is_array($in['heights'] ?? null)) {
            foreach ($in['heights'] as $id => $px) {
                if (in_array($id, $valid, true) && is_numeric($px)) {
                    $heights[$id] = max(self::MIN_WIDGET_HEIGHT, min(self::MAX_WIDGET_HEIGHT, (int) $px));
                }
            }
        }

        $columnCount = max(self::MIN_COLUMNS, min(self::MAX_COLUMNS, (int) ($in['columnCount'] ?? self::DEFAULT_COLUMNS)));
        $columns = self::normalizeColumns($in['columns'] ?? null, $widgets, $columnCount);

        return [
            'widgets'     => $widgets,
            'filters'     => $filters,
            'interval'    => $interval,
            'heights'     => $heights,
            'columnCount' => $columnCount,
            'columns'     => $columns,
        ];
    }

    /**
     * Reference data for the filter dropdowns. Shows every active option;
     * picking one the viewer can't see simply yields an empty result set
     * (the visibility clause still applies on top).
     */
    public static function filterOptions(PDO $db): array
    {
        $q = static fn(string $sql) => $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return [
            'locations'  => $q("SELECT id, name FROM locations ORDER BY name"),
            'groups'     => $q("SELECT id, name FROM `groups` ORDER BY name"),
            'types'      => $q("SELECT id, name FROM ticket_types ORDER BY sort_order, name"),
            'priorities' => $q("SELECT id, name FROM ticket_priorities ORDER BY sort_order, name"),
        ];
    }

    /* ─────────────────────────────────────────────────────────────────────
     * Metrics
     * ──────────────────────────────────────────────────────────────────── */

    /**
     * Compute the requested widgets' data. $only restricts work to the widget
     * ids the front-end currently has on screen, so a poll never runs queries
     * for hidden widgets.
     *
     * @param string[] $only widget ids to compute (empty = all in catalog)
     */
    public static function metrics(PDO $db, int $userId, ?string $role, array $filters, array $only = []): array
    {
        $catalog = self::catalog();
        $ids = $only !== []
            ? array_values(array_intersect($only, array_keys($catalog)))
            : array_keys($catalog);

        $out = [];
        foreach ($ids as $id) {
            try {
                $out[$id] = self::widget($db, $id, $userId, $role, $filters);
            } catch (\Throwable $e) {
                $out[$id] = ['error' => true];
            }
        }
        return $out;
    }

    /** Visibility + entity-filter WHERE fragment for the `tickets t` table. */
    private static function scope(PDO $db, int $userId, ?string $role, array $f, string $t = 't'): array
    {
        $vis = ticketStaffVisibilitySql($db, $userId, $role, $t);
        $sql = $vis['sql'];
        $params = $vis['params'];
        foreach (['location_id', 'group_id', 'type_id', 'priority_id'] as $col) {
            if ((int) ($f[$col] ?? 0) > 0) {
                $sql .= " AND {$t}.{$col} = ?";
                $params[] = (int) $f[$col];
            }
        }
        return ['sql' => $sql, 'params' => $params];
    }

    private static function rangeDays(array $f): int
    {
        return max(1, min(365, (int) ($f['range'] ?? 30)));
    }

    private static function scalar(PDO $db, string $sql, array $params): float
    {
        $st = $db->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false || $v === null ? 0.0 : (float) $v;
    }

    /** Dispatch one widget id to its query. */
    private static function widget(PDO $db, string $id, int $userId, ?string $role, array $f): array
    {
        $sc      = self::scope($db, $userId, $role, $f);
        $where   = $sc['sql'];
        $p       = $sc['params'];
        $openIn  = ticketStatusSqlIn(ticketOpenBucketSlugs(), 't.status');
        $closeIn = ticketStatusSqlIn(ticketClosedBucketSlugs(), 't.status');
        $days    = self::rangeDays($f);

        switch ($id) {
            case 'open':
                return ['value' => (int) self::scalar($db,
                    "SELECT COUNT(*) FROM tickets t WHERE {$where} AND {$openIn}", $p)];

            case 'unassigned':
                return ['value' => (int) self::scalar($db,
                    "SELECT COUNT(*) FROM tickets t WHERE {$where} AND {$openIn} AND t.assigned_to IS NULL", $p)];

            case 'breached':
                return ['value' => (int) self::scalar($db,
                    "SELECT COUNT(*) FROM tickets t WHERE {$where} AND {$openIn} AND t.sla_state = 'breached'", $p),
                    'tone' => 'danger'];

            case 'at_risk':
                return ['value' => (int) self::scalar($db,
                    "SELECT COUNT(*) FROM tickets t WHERE {$where} AND {$openIn} AND t.sla_state = 'warning'", $p),
                    'tone' => 'warning'];

            case 'due_today':
                return ['value' => (int) self::scalar($db,
                    "SELECT COUNT(*) FROM tickets t WHERE {$where} AND {$openIn} AND t.due_date = CURDATE()", $p)];

            case 'created_today':
                return ['value' => (int) self::scalar($db,
                    "SELECT COUNT(*) FROM tickets t WHERE {$where} AND DATE(t.created_at) = CURDATE()", $p)];

            case 'resolved_today':
                return ['value' => (int) self::scalar($db,
                    "SELECT COUNT(*) FROM tickets t WHERE {$where} AND {$closeIn} AND DATE(t.updated_at) = CURDATE()", $p),
                    'tone' => 'success'];

            case 'avg_response': {
                $mins = self::scalar($db,
                    "SELECT AVG(TIMESTAMPDIFF(MINUTE, t.created_at, t.first_responded_at))
                       FROM tickets t
                      WHERE {$where} AND t.first_responded_at IS NOT NULL
                        AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)",
                    array_merge($p, [$days]));
                return ['value' => $mins > 0 ? formatMinutes((int) round($mins)) : '—', 'raw' => (int) round($mins)];
            }

            case 'csat': {
                $st = $db->prepare(
                    "SELECT AVG(cs.rating) avg_rating, COUNT(cs.rating) responses
                       FROM csat_surveys cs JOIN tickets t ON t.id = cs.ticket_id
                      WHERE {$where} AND cs.rating IS NOT NULL
                        AND cs.sent_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)");
                $st->execute(array_merge($p, [$days]));
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                $avg = (float) ($row['avg_rating'] ?? 0);
                return [
                    'value' => $avg > 0 ? number_format($avg, 1) . ' / 5' : '—',
                    'sub'   => ((int) ($row['responses'] ?? 0)) . ' responses',
                ];
            }

            case 'sla_compliance': {
                $st = $db->prepare(
                    "SELECT
                        SUM(CASE WHEN t.first_responded_at IS NOT NULL
                                  AND t.first_responded_at <= t.first_response_due_at THEN 1 ELSE 0 END) AS met,
                        COUNT(CASE WHEN t.first_response_due_at IS NOT NULL THEN 1 END) AS total
                       FROM tickets t
                      WHERE {$where} AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)");
                $st->execute(array_merge($p, [$days]));
                $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
                $total = (int) ($row['total'] ?? 0);
                $met   = (int) ($row['met'] ?? 0);
                $pct   = $total > 0 ? round($met / $total * 100) : null;
                return [
                    'value' => $pct === null ? '—' : $pct . '%',
                    'sub'   => $total > 0 ? "{$met} of {$total} met" : 'No SLA tickets',
                    'tone'  => $pct === null ? null : ($pct >= 90 ? 'success' : ($pct >= 75 ? 'warning' : 'danger')),
                ];
            }

            case 'by_status':
                return ['series' => self::breakdown($db,
                    "SELECT t.status AS gid, COALESCE(ts.label, t.status) AS label, COALESCE(ts.color, '#6c757d') AS color, COUNT(*) AS n
                       FROM tickets t LEFT JOIN ticket_statuses ts ON ts.slug = t.status
                      WHERE {$where} AND {$openIn}
                      GROUP BY t.status, ts.label, ts.color
                      ORDER BY n DESC", $p)];

            case 'by_priority':
                return ['series' => self::breakdown($db,
                    "SELECT tp.id AS gid, COALESCE(tp.name, 'None') AS label, COALESCE(tp.color, '#6c757d') AS color, COUNT(*) AS n
                       FROM tickets t LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
                      WHERE {$where} AND {$openIn}
                      GROUP BY tp.id, tp.name, tp.color
                      ORDER BY tp.sort_order", $p)];

            case 'by_type':
                return ['series' => self::breakdown($db,
                    "SELECT tt.id AS gid, COALESCE(tt.name, 'Untyped') AS label, COALESCE(tt.color, '#6c757d') AS color, COUNT(*) AS n
                       FROM tickets t LEFT JOIN ticket_types tt ON tt.id = t.type_id
                      WHERE {$where} AND {$openIn}
                      GROUP BY tt.id, tt.name, tt.color
                      ORDER BY n DESC LIMIT 12", $p)];

            case 'by_group':
                return ['series' => self::breakdown($db,
                    "SELECT g.id AS gid, COALESCE(g.name, 'No group') AS label, '#0d6efd' AS color, COUNT(*) AS n
                       FROM tickets t LEFT JOIN `groups` g ON g.id = t.group_id
                      WHERE {$where} AND {$openIn}
                      GROUP BY g.id, g.name
                      ORDER BY n DESC LIMIT 12", $p)];

            case 'by_location':
                return ['series' => self::breakdown($db,
                    "SELECT l.id AS gid, COALESCE(l.name, 'No location') AS label, '#6610f2' AS color, COUNT(*) AS n
                       FROM tickets t LEFT JOIN locations l ON l.id = t.location_id
                      WHERE {$where} AND {$openIn}
                      GROUP BY l.id, l.name
                      ORDER BY n DESC LIMIT 12", $p)];

            case 'volume_trend':
                return self::volumeTrend($db, $where, $p, $closeIn, $days);

            case 'agent_workload': {
                $st = $db->prepare(
                    "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name,
                            COUNT(*) AS open_total,
                            SUM(CASE WHEN t.sla_state = 'breached' THEN 1 ELSE 0 END) AS breached
                       FROM tickets t JOIN users u ON u.id = t.assigned_to
                      WHERE {$where} AND {$openIn}
                      GROUP BY u.id, name
                      ORDER BY open_total DESC LIMIT 15");
                $st->execute($p);
                return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
            }

            case 'unassigned_queue': {
                $st = $db->prepare(
                    "SELECT t.id, t.subject, t.created_at,
                            COALESCE(tp.name, 'None') AS priority, COALESCE(tp.color, '#6c757d') AS color
                       FROM tickets t LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
                      WHERE {$where} AND {$openIn} AND t.assigned_to IS NULL
                      ORDER BY t.created_at ASC LIMIT 12");
                $st->execute($p);
                return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
            }

            case 'recent_activity': {
                $st = $db->prepare(
                    "SELECT t.id, t.subject, t.updated_at,
                            COALESCE(ts.label, t.status) AS status, COALESCE(ts.color, '#6c757d') AS color
                       FROM tickets t LEFT JOIN ticket_statuses ts ON ts.slug = t.status
                      WHERE {$where}
                      ORDER BY t.updated_at DESC LIMIT 12");
                $st->execute($p);
                return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC)];
            }
        }

        return ['error' => true];
    }

    /** Run a label/color/count breakdown query into Chart.js-friendly arrays. */
    private static function breakdown(PDO $db, string $sql, array $params): array
    {
        $st = $db->prepare($sql);
        $st->execute($params);
        $labels = $colors = $data = $ids = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $labels[] = $r['label'];
            $colors[] = $r['color'];
            $data[]   = (int) $r['n'];
            $ids[]    = array_key_exists('gid', $r) && $r['gid'] !== null ? (string) $r['gid'] : null;
        }
        return ['labels' => $labels, 'colors' => $colors, 'data' => $data, 'ids' => $ids];
    }

    /** Created-per-day vs resolved-per-day over the range, gap-filled. */
    private static function volumeTrend(PDO $db, string $where, array $p, string $closeIn, int $days): array
    {
        $created = $resolved = [];

        $st = $db->prepare(
            "SELECT DATE(t.created_at) d, COUNT(*) n FROM tickets t
              WHERE {$where} AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              GROUP BY DATE(t.created_at)");
        $st->execute(array_merge($p, [$days]));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $created[$r['d']] = (int) $r['n'];
        }

        $st = $db->prepare(
            "SELECT DATE(t.updated_at) d, COUNT(*) n FROM tickets t
              WHERE {$where} AND {$closeIn} AND t.updated_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
              GROUP BY DATE(t.updated_at)");
        $st->execute(array_merge($p, [$days]));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $resolved[$r['d']] = (int) $r['n'];
        }

        $labels = $createdSeries = $resolvedSeries = [];
        $start = new DateTimeImmutable('-' . ($days - 1) . ' days');
        for ($i = 0; $i < $days; $i++) {
            $day = $start->modify("+{$i} days")->format('Y-m-d');
            $labels[]         = $day;
            $createdSeries[]  = $created[$day]  ?? 0;
            $resolvedSeries[] = $resolved[$day] ?? 0;
        }
        return ['labels' => $labels, 'created' => $createdSeries, 'resolved' => $resolvedSeries];
    }
}

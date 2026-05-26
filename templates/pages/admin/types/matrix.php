<?php
$layout       = 'app';
$pageTitle    = 'Ticket Types — Settings Matrix';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',        'url' => '/admin'],
    ['label' => 'Settings',     'url' => '/admin/settings'],
    ['label' => 'Ticket Types', 'url' => '/admin/types'],
    ['label' => 'Settings Matrix'],
];

$columns = [
    'group_assigned'      => 'Group assigned',
    'is_confidential'     => 'Confidential',
    'ai_route_group'      => 'AI route to group',
    'ai_dup_check'        => 'AI dup-check',
    'show_to_loc_vis'     => 'Show "To" / Location',
    'custom_dup_thr'      => 'Custom dup threshold',
    'custom_stale_thr'    => 'Custom stale threshold',
];

function tt_cell_yes(): string { return '<span class="mx-on">&#10003;</span>'; }
function tt_cell_no(): string  { return '<span class="mx-off">&minus;</span>'; }
?>
<div class="mb-4 d-print-none">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-start mb-3 d-print-none">
    <div>
        <h5 class="fw-semibold mb-1"><i class="bi bi-grid-3x3-gap me-2"></i>Ticket Types &mdash; Settings Matrix</h5>
        <p class="text-muted mb-0" style="font-size:.875rem;">
            Live view of every ticket type and which optional settings are enabled. Click <strong>Print</strong> to save a hard copy.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/types" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i>Edit ticket types
        </a>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print();">
            <i class="bi bi-printer me-1"></i>Print
        </button>
    </div>
</div>

<!-- Print-only title — short, single line, replaces the screen header above. -->
<div class="d-none d-print-block mb-2" style="font-weight:600;font-size:11pt;">
    Ticket Types &mdash; Settings Matrix
    <span class="text-muted" style="font-weight:400;font-size:9pt;">&nbsp;&middot;&nbsp; <?= date('Y-m-d') ?></span>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-3">
        <?php if (empty($types)): ?>
            <p class="text-muted mb-0 py-4 text-center">No ticket types configured yet.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 tt-matrix">
                <thead>
                    <tr>
                        <th class="tt-id">#</th>
                        <th class="tt-name">Ticket type</th>
                        <?php foreach ($columns as $label): ?>
                        <th class="tt-rot"><div class="tt-rot-inner"><span><?= e($label) ?></span></div></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($types as $t):
                    $color   = $t['color'] ?: '#6c757d';
                    $thr     = (float) ($t['ai_dup_threshold'] ?? 0.75);
                    $dupOn   = (int) ($t['ai_dup_check_enabled'] ?? 0) === 1;
                    $custThr = $dupOn && abs($thr - 0.75) > 0.001;
                    $stale   = $t['stale_threshold_hours'];
                    $custSt  = $stale !== null;
                ?>
                <tr>
                    <td class="tt-id text-muted"><?= (int) $t['id'] ?></td>
                    <td class="tt-name">
                        <span class="tt-swatch" style="background:<?= e($color) ?>"></span>
                        <span class="fw-semibold"><?= e($t['name']) ?></span>
                        <?php if (!empty($t['group_name'])): ?>
                        <div class="text-muted small mt-1 ms-4"><?= e($t['group_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="tt-c"><?= !empty($t['group_id']) ? tt_cell_yes() : tt_cell_no() ?></td>
                    <td class="tt-c"><?= !empty($t['is_confidential']) ? tt_cell_yes() : tt_cell_no() ?></td>
                    <td class="tt-c"><?= !empty($t['ai_route_group']) ? tt_cell_yes() : tt_cell_no() ?></td>
                    <td class="tt-c"><?= $dupOn ? tt_cell_yes() : tt_cell_no() ?></td>
                    <td class="tt-c"><?= !empty($t['show_to_location_visibility']) ? tt_cell_yes() : tt_cell_no() ?></td>
                    <td class="tt-c">
                        <?php if ($custThr): ?>
                            <span class="mx-on" title="ai_dup_threshold"><?= number_format($thr, 2) ?></span>
                        <?php else: ?>
                            <?= tt_cell_no() ?>
                        <?php endif; ?>
                    </td>
                    <td class="tt-c">
                        <?php if ($custSt): ?>
                            <span class="mx-on" title="stale_threshold_hours"><?= (int) $stale ?>h</span>
                        <?php else: ?>
                            <?= tt_cell_no() ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex gap-3 align-items-center mt-3 text-muted small flex-wrap">
            <span><span class="mx-on">&#10003;</span> enabled</span>
            <span><span class="mx-off">&minus;</span> not enabled</span>
            <span>&middot; "Custom dup threshold" = <code>ai_dup_threshold</code> &ne; 0.75 (default).</span>
            <span>&middot; "Custom stale threshold" = a per-type override of the site-wide stale-ticket cutoff.</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

<style>
    table.tt-matrix th, table.tt-matrix td { border-bottom: 1px solid var(--bs-border-color-translucent, rgba(0,0,0,.075)); }
    table.tt-matrix thead th { background: var(--bs-tertiary-bg, #f4f4f5); border-bottom-width: 2px; vertical-align: bottom; font-weight: 600; }
    table.tt-matrix th.tt-id, table.tt-matrix td.tt-id { width: 38px; text-align: right; font-variant-numeric: tabular-nums; }
    table.tt-matrix th.tt-name { text-align: left; }
    table.tt-matrix td.tt-name { white-space: nowrap; }
    table.tt-matrix th.tt-rot { height: 150px; padding: 6px 4px; text-align: center; }
    table.tt-matrix th.tt-rot .tt-rot-inner { height: 140px; display: flex; align-items: flex-end; justify-content: center; }
    table.tt-matrix th.tt-rot .tt-rot-inner span {
        writing-mode: vertical-rl; transform: rotate(180deg);
        font-size: .82rem; white-space: nowrap;
    }
    table.tt-matrix td.tt-c { text-align: center; font-size: 1.05rem; width: 64px; }
    table.tt-matrix tbody tr:nth-child(even) td { background: var(--bs-tertiary-bg, #fafafa); }
    .tt-swatch { display: inline-block; width: 12px; height: 12px; border-radius: 3px; border: 1px solid rgba(0,0,0,.15); margin-right: 8px; vertical-align: -1px; }
    .mx-on  { color: #0a7d2c; font-weight: 700; }
    .mx-off { color: #cbd0d5; font-weight: 700; }
    @media print {
        @page { size: Letter landscape; margin: 0.4in; }

        /* Hide app chrome and the page bits that don't belong on paper. */
        aside, nav, header, .breadcrumb, .btn, .docs-nav,
        .ld-banner,
        .d-print-none {
            display: none !important;
        }
        /* Hide the settings sub-nav sidebar (the only .flex-shrink-0 sibling of .flex-grow-1 in this layout). */
        .d-flex > .flex-shrink-0 { display: none !important; }

        /* Body / page-shell resets so the table can use the full sheet. */
        body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
        .container, .container-fluid, main { width: 100% !important; max-width: none !important; padding: 0 !important; margin: 0 !important; }
        .d-flex > .flex-grow-1 { width: 100% !important; max-width: none !important; flex: 1 1 100% !important; }
        .card { border: 0 !important; box-shadow: none !important; }
        .card-body { padding: 0 !important; }

        /* Make the matrix itself compact enough for one page. */
        table.tt-matrix { font-size: 9pt; }
        table.tt-matrix th, table.tt-matrix td { padding: 2px 6px !important; line-height: 1.25 !important; }
        table.tt-matrix th.tt-rot { height: 130px !important; padding-bottom: 2px !important; }
        table.tt-matrix th.tt-rot .tt-rot-inner { height: 122px !important; }
        table.tt-matrix th.tt-rot .tt-rot-inner span { font-size: 8.5pt !important; }
        table.tt-matrix td.tt-c { font-size: 10.5pt !important; width: 42px !important; }
        /* Hide the secondary group-name line under each ticket type — saves ~12 rows of vertical space. */
        table.tt-matrix td.tt-name .text-muted { display: none !important; }

        /* Tighten the legend below the table. */
        .card-body > .d-flex.mt-3 { margin-top: 4px !important; font-size: 7.5pt !important; gap: .5rem !important; }

        /* Preserve striping, swatches, check colours. */
        table.tt-matrix thead th { background: #f4f4f5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        table.tt-matrix tbody tr:nth-child(even) td { background: #fafafa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .tt-swatch, .mx-on, .mx-off { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        /* Prevent row splitting across page breaks. */
        table.tt-matrix tr, table.tt-matrix td, table.tt-matrix th { page-break-inside: avoid; }
        thead { display: table-header-group; }
    }
</style>

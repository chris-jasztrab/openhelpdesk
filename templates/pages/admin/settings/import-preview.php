<?php
$layout       = 'app';
$pageTitle    = 'Import Preview';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Import Tickets', 'url' => '/admin/settings/import'],
    ['label' => 'Preview'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-search me-2"></i>Import Preview</h5>
    </div>
    <div class="card-body p-4">
        <!-- Summary -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="fs-3 fw-bold text-primary"><?= (int) $summary['total_tickets'] ?></div>
                    <div class="text-muted small">Tickets to Import</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="fs-3 fw-bold text-success"><?= (int) $summary['new_users'] ?></div>
                    <div class="text-muted small">New Users to Create</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="fs-3 fw-bold text-info"><?= (int) $summary['new_agents'] ?></div>
                    <div class="text-muted small">New Agents to Create</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="fs-3 fw-bold text-warning"><?= (int) $summary['new_locations'] ?></div>
                    <div class="text-muted small">New <?= label('location.plural') ?> to Create</div>
                </div>
            </div>
        </div>

        <?php
        $slaChoice = $summary['sla_handling'] ?? 'exclude';
        $slaNote = match ($slaChoice) {
            'start_now'  => ['info', 'bi-stopwatch', 'SLA clocks start now',
                'Every imported ticket with a priority gets a fresh SLA window measured from this import, not from its original open date.'],
            'historical' => ['danger', 'bi-exclamation-octagon-fill', 'SLA applied from the original open date',
                'Any still-open ticket already past its target will be imported in a breached state and will appear in SLA violation reports immediately.'],
            default      => ['secondary', 'bi-slash-circle', 'Excluded from SLA tracking',
                'These tickets get no SLA clock and stay out of SLA reports and compliance figures. Later priority or type changes will not start one.'],
        };
        ?>
        <div class="alert alert-<?= e($slaNote[0]) ?> d-flex align-items-start mb-4" role="alert">
            <i class="bi <?= e($slaNote[1]) ?> me-2 mt-1"></i>
            <div>
                <strong><?= e($slaNote[2]) ?></strong>
                <div class="small mt-1"><?= e($slaNote[3]) ?></div>
                <a href="/admin/settings/import/map" class="small">Change this</a>
            </div>
        </div>

        <?php if (!empty($summary['new_user_list']) || !empty($summary['new_agent_list']) || !empty($summary['new_location_list'])): ?>
        <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
            <div>
                <strong>The following will be auto-created:</strong>
                <?php if (!empty($summary['new_location_list'])): ?>
                    <div class="mt-2"><strong><?= label('location.plural') ?>:</strong> <?= e(implode(', ', $summary['new_location_list'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($summary['new_agent_list'])): ?>
                    <div class="mt-1"><strong>Agents:</strong> <?= e(implode(', ', $summary['new_agent_list'])) ?></div>
                <?php endif; ?>
                <?php if (!empty($summary['new_user_list'])): ?>
                    <div class="mt-1"><strong>Users:</strong> <?= e(implode(', ', array_slice($summary['new_user_list'], 0, 20))) ?>
                    <?php if (count($summary['new_user_list']) > 20): ?>
                        <span class="text-muted">and <?= count($summary['new_user_list']) - 20 ?> more...</span>
                    <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sample rows -->
        <h6 class="fw-semibold mb-3">Preview (first <?= count($previewRows) ?> rows)</h6>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Legacy ID</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Type</th>
                        <th><?= label('location.singular') ?></th>
                        <th>Agent</th>
                        <th>Submitter</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewRows as $row): ?>
                    <tr>
                        <td class="text-muted">#<?= e($row['legacy_id']) ?></td>
                        <td><?= e(mb_strimwidth($row['subject'], 0, 50, '...')) ?></td>
                        <td><span class="badge bg-secondary"><?= e($row['status']) ?></span></td>
                        <td><?= e($row['priority']) ?></td>
                        <td><?= e($row['type']) ?></td>
                        <td><?= e($row['location']) ?></td>
                        <td><?= e($row['agent']) ?></td>
                        <td><?= e($row['submitter_name']) ?></td>
                        <td class="text-nowrap"><?= e($row['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <form method="POST" action="/admin/settings/import/confirm">
            <?= csrfField() ?>

            <?php
            // The scope picker only exists to narrow a handling that starts clocks.
            // "Exclude from SLA" already covers the whole file, so narrowing it
            // would be a choice with no effect.
            $typeStats   = $summary['type_stats'] ?? [];
            $showSlaScope = $slaChoice !== 'exclude'
                && !empty($typeStats)
                && empty($summary['type_stats_capped']);
            ?>

            <?php if ($slaChoice !== 'exclude' && !empty($summary['type_stats_capped'])): ?>
            <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
                <div>
                    <strong>Per-type SLA scope unavailable for this file.</strong>
                    <div class="small mt-1">
                        The mapped Ticket Type column holds more than 60 distinct values, which
                        usually means it is pointed at the wrong CSV column. The SLA choice above
                        will apply to every imported ticket. <a href="/admin/settings/import/map">Check the mapping</a>.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($showSlaScope): ?>
            <input type="hidden" name="sla_scope_present" value="1">
            <div class="border rounded p-3 mb-4">
                <div class="fw-semibold mb-1"><i class="bi bi-funnel me-2"></i>Which tickets get an SLA clock</div>
                <p class="text-muted small mb-3">
                    Everything unticked, and anything opened before the cutoff, is imported
                    <strong>SLA-exempt</strong> instead &mdash; no clock now, and no clock later if
                    someone edits its priority or type. Both filters apply together.
                </p>

                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:2.5rem;"></th>
                                <th>Ticket Type</th>
                                <th class="text-end">Rows</th>
                                <th>Opened between</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($typeStats as $key => $stat): ?>
                            <tr>
                                <td>
                                    <input class="form-check-input sla-type-check" type="checkbox"
                                           name="sla_types[]" value="<?= e((string) $key) ?>"
                                           data-count="<?= (int) $stat['count'] ?>"
                                           id="sla-type-<?= e(md5((string) $key)) ?>" checked>
                                </td>
                                <td>
                                    <label class="form-check-label" for="sla-type-<?= e(md5((string) $key)) ?>">
                                        <?php if (trim((string) $stat['label']) === ''): ?>
                                            <span class="text-muted fst-italic">(no ticket type)</span>
                                        <?php else: ?>
                                            <?= e($stat['label']) ?>
                                        <?php endif; ?>
                                    </label>
                                </td>
                                <td class="text-end font-monospace"><?= number_format((int) $stat['count']) ?></td>
                                <td class="text-muted small text-nowrap">
                                    <?php if (!empty($stat['oldest'])): ?>
                                        <?= e(date('M j, Y', strtotime((string) $stat['oldest']))) ?>
                                        &ndash;
                                        <?= e(date('M j, Y', strtotime((string) $stat['newest']))) ?>
                                    <?php else: ?>
                                        <span class="fst-italic">no dates</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex gap-3 align-items-end flex-wrap mb-2">
                    <div>
                        <label for="sla_start_after" class="form-label small fw-semibold mb-1">
                            Only start clocks on tickets opened on or after
                        </label>
                        <input type="date" class="form-control form-control-sm" style="max-width:12rem;"
                               id="sla_start_after" name="sla_start_after">
                        <div class="form-text">Leave blank for no date cutoff.</div>
                    </div>
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="slaTypesAll">Select all</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="slaTypesNone">Select none</button>
                    </div>
                </div>

                <div class="small text-muted" id="slaScopeCount"></div>
            </div>
            <?php endif; ?>

            <!-- Action buttons -->
            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);"
                        onclick="this.disabled=true; this.innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span>Importing...'; this.form.submit();">
                    <i class="bi bi-check-lg me-1"></i>Confirm Import (<?= (int) $summary['total_tickets'] ?> tickets)
                </button>
                <a href="/admin/settings/import/map" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Mapping
                </a>
                <a href="/admin/settings/import" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i>Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php if ($showSlaScope): ?>
<script>
(function () {
    const boxes   = Array.from(document.querySelectorAll('.sla-type-check'));
    const cutoff  = document.getElementById('sla_start_after');
    const readout = document.getElementById('slaScopeCount');
    const total   = <?= (int) $summary['total_tickets'] ?>;

    function update() {
        // Counts the ticked types exactly; the date cutoff is applied per row at
        // import time, so it can only reduce this further — say so rather than
        // showing a number that quietly ignores it.
        const inScope = boxes
            .filter(b => b.checked)
            .reduce((sum, b) => sum + Number(b.dataset.count || 0), 0);
        let text = inScope.toLocaleString() + ' of ' + total.toLocaleString()
                 + ' ticket(s) are in the ticked types';
        text += cutoff.value
            ? ', before the ' + cutoff.value + ' cutoff is applied.'
            : '.';
        if (inScope === 0) {
            text += ' Nothing will get an SLA clock — the whole import will be SLA-exempt.';
        }
        readout.textContent = text;
    }

    boxes.forEach(b => b.addEventListener('change', update));
    cutoff.addEventListener('change', update);
    document.getElementById('slaTypesAll').addEventListener('click', () => {
        boxes.forEach(b => { b.checked = true; }); update();
    });
    document.getElementById('slaTypesNone').addEventListener('click', () => {
        boxes.forEach(b => { b.checked = false; }); update();
    });
    update();
})();
</script>
<?php endif; ?>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

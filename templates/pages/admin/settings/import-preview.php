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
                    <div class="text-muted small">New Locations to Create</div>
                </div>
            </div>
        </div>

        <?php if (!empty($summary['new_user_list']) || !empty($summary['new_agent_list']) || !empty($summary['new_location_list'])): ?>
        <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
            <div>
                <strong>The following will be auto-created:</strong>
                <?php if (!empty($summary['new_location_list'])): ?>
                    <div class="mt-2"><strong>Locations:</strong> <?= e(implode(', ', $summary['new_location_list'])) ?></div>
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
                        <th>Location</th>
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

        <!-- Action buttons -->
        <div class="d-flex gap-2">
            <form method="POST" action="/admin/settings/import/confirm">
                <?= csrfField() ?>
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);"
                        onclick="this.disabled=true; this.innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span>Importing...'; this.form.submit();">
                    <i class="bi bi-check-lg me-1"></i>Confirm Import (<?= (int) $summary['total_tickets'] ?> tickets)
                </button>
            </form>
            <a href="/admin/settings/import" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg me-1"></i>Cancel
            </a>
        </div>
    </div>
</div>

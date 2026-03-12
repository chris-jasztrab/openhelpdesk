<?php
$layout       = 'app';
$pageTitle    = 'Import Users – Preview';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Import Users', 'url' => '/admin/settings/import-users'],
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

        <!-- Summary boxes -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="fs-3 fw-bold text-primary"><?= (int) $summary['total_rows'] ?></div>
                    <div class="text-muted small">Rows in File</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="fs-3 fw-bold text-success"><?= (int) $summary['new_users'] ?></div>
                    <div class="text-muted small">Users to Create</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="fs-3 fw-bold text-warning"><?= (int) $summary['duplicates'] ?></div>
                    <div class="text-muted small">Duplicates (will skip)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="fs-3 fw-bold text-info"><?= (int) $summary['new_locations'] ?></div>
                    <div class="text-muted small">New <?= label('location.plural') ?> to Create</div>
                </div>
            </div>
        </div>

        <?php if (!empty($summary['duplicate_list']) || !empty($summary['new_location_list'])): ?>
        <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
            <div>
                <?php if (!empty($summary['duplicate_list'])): ?>
                <strong>The following emails already exist and will be skipped:</strong>
                <div class="mt-1">
                    <?= e(implode(', ', array_slice($summary['duplicate_list'], 0, 20))) ?>
                    <?php if (count($summary['duplicate_list']) > 20): ?>
                        <span class="text-muted">and <?= count($summary['duplicate_list']) - 20 ?> more...</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($summary['new_location_list'])): ?>
                <div class="mt-2"><strong>New <?= label('location.plural') ?> to create:</strong> <?= e(implode(', ', $summary['new_location_list'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Preview table -->
        <h6 class="fw-semibold mb-3">Preview (first <?= count($previewRows) ?> rows)</h6>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Email</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th><?= label('location.singular') ?></th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewRows as $row): ?>
                    <tr class="<?= $row['is_duplicate'] ? 'table-warning' : '' ?>">
                        <td><?= e($row['email']) ?></td>
                        <td><?= e($row['first_name']) ?></td>
                        <td><?= e($row['last_name']) ?></td>
                        <td><span class="badge bg-secondary"><?= e($row['role']) ?></span></td>
                        <td><?= e($row['work_phone']) ?></td>
                        <td><?= e($row['location']) ?></td>
                        <td>
                            <?php if ($row['is_duplicate']): ?>
                            <span class="badge bg-warning text-dark">Skip (exists)</span>
                            <?php else: ?>
                            <span class="badge bg-success">New</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Action buttons -->
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($summary['new_users'] > 0): ?>
            <form method="POST" action="/admin/settings/import-users/confirm">
                <?= csrfField() ?>
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);"
                        onclick="this.disabled=true; this.innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span>Importing...'; this.form.submit();">
                    <i class="bi bi-check-lg me-1"></i>Confirm Import (<?= (int) $summary['new_users'] ?> users)
                </button>
            </form>
            <?php endif; ?>
            <a href="/admin/settings/import-users/map" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Mapping
            </a>
            <a href="/admin/settings/import-users" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg me-1"></i>Cancel
            </a>
        </div>

    </div>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

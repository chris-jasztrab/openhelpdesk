<?php
$layout       = 'app';
$pageTitle    = label('location.plural') . ' – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => label('location.plural')],
];
$isShared = ($tzMode ?? 'shared') === 'shared';
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<!-- Timezone Settings -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h6 class="fw-bold mb-1"><i class="bi bi-clock me-1"></i>Timezone Settings</h6>
        <p class="text-muted small mb-3">
            Controls how ticket timestamps are displayed and how imported ticket dates are interpreted.
        </p>
        <form method="POST" action="/admin/locations/timezone-settings">
            <?= csrfField() ?>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="location_timezone_mode"
                           id="tz_shared" value="shared" <?= $isShared ? 'checked' : '' ?>
                           onchange="document.getElementById('shared-tz-row').classList.toggle('d-none', false); document.getElementById('per-location-note').classList.toggle('d-none', true);">
                    <label class="form-check-label fw-semibold" for="tz_shared">
                        All my <?= label('location.plural', 'locations') ?> are in the same timezone
                    </label>
                </div>
                <div class="form-check mt-1">
                    <input class="form-check-input" type="radio" name="location_timezone_mode"
                           id="tz_per" value="per_location" <?= !$isShared ? 'checked' : '' ?>
                           onchange="document.getElementById('shared-tz-row').classList.toggle('d-none', true); document.getElementById('per-location-note').classList.toggle('d-none', false);">
                    <label class="form-check-label fw-semibold" for="tz_per">
                        Each <?= label('location.singular', 'location') ?> has its own timezone
                    </label>
                </div>
            </div>

            <div id="shared-tz-row" class="mb-3 <?= !$isShared ? 'd-none' : '' ?>" style="max-width:320px;">
                <label for="location_timezone_shared" class="form-label fw-semibold">Shared Timezone</label>
                <select class="form-select" name="location_timezone_shared" id="location_timezone_shared">
                    <?php foreach ($timezones as $tz): ?>
                    <option value="<?= e($tz) ?>" <?= ($sharedTz ?? 'UTC') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="per-location-note" class="mb-3 text-muted small <?= $isShared ? 'd-none' : '' ?>">
                <i class="bi bi-info-circle me-1"></i>Set the timezone for each <?= label('location.singular', 'location') ?> using the Edit button below.
            </div>

            <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Timezone Settings
            </button>
        </form>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><?= label('location.plural') ?></h5>
    <a href="/admin/locations/create" class="btn text-white" style="background:var(--ld-primary);">
        <i class="bi bi-geo-alt me-1"></i>Add <?= label('location.singular') ?>
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Address</th>
                    <th>Description</th>
                    <?php if (!$isShared): ?>
                    <th>Timezone</th>
                    <?php endif; ?>
                    <th>Created</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($locations)): ?>
                <tr><td colspan="<?= $isShared ? 5 : 6 ?>" class="text-center py-4 text-muted">No <?= label('location.plural', 'locations') ?> found.</td></tr>
                <?php else: ?>
                    <?php foreach ($locations as $loc): ?>
                    <tr>
                        <td class="fw-semibold">
                            <i class="bi bi-geo-alt text-muted me-1"></i><?= e($loc['name']) ?>
                        </td>
                        <td class="text-muted"><?= e($loc['address'] ?: '—') ?></td>
                        <td class="text-muted small" style="max-width:300px;"><?= e($loc['description'] ? mb_strimwidth($loc['description'], 0, 80, '...') : '—') ?></td>
                        <?php if (!$isShared): ?>
                        <td class="text-muted small">
                            <?php if (!empty($loc['timezone'])): ?>
                                <?= e($loc['timezone']) ?>
                            <?php else: ?>
                                <span class="text-warning">Not set</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($loc['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/locations/<?= $loc['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="/admin/locations/<?= $loc['id'] ?>/delete-confirm" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

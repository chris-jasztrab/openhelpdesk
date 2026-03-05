<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = $isEdit ? ('Edit ' . label('location.singular')) : ('Add ' . label('location.singular'));
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => label('location.plural'), 'url' => '/admin/locations'],
    ['label' => $isEdit ? 'Edit' : 'Add'],
];
$action = $isEdit ? "/admin/locations/{$editing['id']}/edit" : '/admin/locations/create';
$isPerLocation = ($tzMode ?? 'shared') === 'per_location';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? ('Edit ' . label('location.singular')) : ('Add ' . label('location.singular')) ?></h2>
    <a href="/admin/locations" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="name" class="form-label fw-semibold"><?= label('location.singular') ?> Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= e(old('name', $editing['name'] ?? '')) ?>" required>
            </div>

            <div class="mb-3">
                <label for="address" class="form-label fw-semibold">Address</label>
                <textarea class="form-control" id="address" name="address" rows="2"><?= e(old('address', $editing['address'] ?? '')) ?></textarea>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label fw-semibold">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= e(old('description', $editing['description'] ?? '')) ?></textarea>
            </div>

            <?php if ($isPerLocation): ?>
            <div class="mb-3">
                <label for="timezone" class="form-label fw-semibold">
                    <i class="bi bi-clock me-1"></i>Timezone
                </label>
                <select class="form-select" name="timezone" id="timezone" style="max-width:320px;">
                    <option value="">Use default (UTC)</option>
                    <?php foreach ($timezones as $tz): ?>
                    <option value="<?= e($tz) ?>" <?= old('timezone', $editing['timezone'] ?? '') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Ticket timestamps for this <?= label('location.singular', 'location') ?> will be displayed in this timezone.</div>
            </div>
            <?php else: ?>
            <div class="mb-3 p-3 bg-light rounded">
                <i class="bi bi-clock text-muted me-1"></i>
                <span class="text-muted small">
                    All <?= label('location.plural', 'locations') ?> are using the shared timezone: <strong><?= e($sharedTz ?? 'UTC') ?></strong>.
                    To set per-<?= label('location.singular', 'location') ?> timezones, change the timezone mode on the
                    <a href="/admin/locations"><?= label('location.plural', 'locations') ?> settings page</a>.
                </span>
            </div>
            <?php endif; ?>

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? ('Update ' . label('location.singular')) : ('Create ' . label('location.singular')) ?>
                </button>
                <a href="/admin/locations" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

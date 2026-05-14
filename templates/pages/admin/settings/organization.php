<?php
$layout       = 'app';
$pageTitle    = 'Organization';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Organization'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-building me-2"></i>Organization</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-4">
            Tell <?= e(getSetting('branding_app_name', 'OpenHelpDesk')) ?> what kind of organization you are.
            This helps tailor terminology, default ticket types, and AI suggestions to your sector.
        </p>

        <form method="POST" action="/admin/settings/organization">
            <?= csrfField() ?>

            <div class="mb-4" style="max-width:480px;">
                <label for="organization_type" class="form-label fw-semibold">Organization Type</label>
                <select class="form-select" id="organization_type" name="organization_type">
                    <?php foreach ($orgTypeGroups as $groupLabel => $options): ?>
                    <optgroup label="<?= e($groupLabel) ?>">
                        <?php foreach ($options as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $settings['organization_type'] === $value ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">
                    Pick the closest match. Choose <em>Other</em> if nothing fits — you can always change this later.
                </div>
            </div>

            <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Settings
            </button>
        </form>
    </div>
</div>
<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

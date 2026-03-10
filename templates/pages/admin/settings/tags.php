<?php
$layout       = 'app';
$pageTitle    = 'Tags';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Tags'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-hash me-2"></i>Tags</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-4">
            When enabled, ticket forms show a tag input that lets agents and portal users
            add hashtag labels to tickets. Disable this if your team does not use tags.
        </p>

        <form method="POST" action="/admin/settings/tags">
            <?= csrfField() ?>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="tags_enabled" name="tags_enabled" value="1"
                           <?= $settings['tags_enabled'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="tags_enabled">
                        Enable tags on tickets
                    </label>
                </div>
                <div class="form-text">When disabled, the tag input is hidden from all ticket forms and views.</div>
            </div>

            <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Settings
            </button>
        </form>
    </div>
</div>
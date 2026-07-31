<?php
$layout       = 'app';
$pageTitle    = 'Attachments';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Attachments'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-paperclip me-2"></i>Attachments</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-4">
            Screenshots are most of what people attach to a ticket. With previews on, an image
            attachment renders as a thumbnail wherever attachments are listed, so you can see
            what it is without downloading it first. With previews off, every attachment stays a
            plain icon and filename.
        </p>

        <form method="POST" action="/admin/settings/attachments">
            <?= csrfField() ?>

            <div class="mb-4">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="attachment_image_previews" name="attachment_image_previews" value="1"
                           <?= $settings['attachment_image_previews'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="attachment_image_previews">
                        Show image attachment previews
                    </label>
                </div>
                <div class="form-text">
                    Applies to the staff, admin and portal ticket views. SVG files are never previewed
                    regardless of this setting &mdash; they can carry scripts, so they always stay plain
                    download links.
                </div>
            </div>

            <div class="alert alert-light border small text-muted">
                <i class="bi bi-info-circle me-1"></i>
                This only controls the <strong>attachment list</strong>. An image pasted directly into a
                ticket description or reply is part of the message body and always renders where it was
                placed &mdash; turning previews off will not hide it.
            </div>

            <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Settings
            </button>
        </form>
    </div>
</div>
<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

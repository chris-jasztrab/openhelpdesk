<?php
$layout       = 'app';
$pageTitle    = 'Undo Send';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Undo Send'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-arrow-counterclockwise me-2"></i>Undo Send</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-4">
            When enabled, submitting a new ticket or a reply shows a short countdown toast with an
            <strong>Undo</strong> button. The message is only sent — and notification emails only go
            out — after the countdown finishes, so an "oops" can be taken back before anyone sees it.
        </p>

        <form method="POST" action="/admin/settings/undo-send">
            <?= csrfField() ?>

            <div class="mb-4">
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="undo_send_enabled" name="undo_send_enabled" value="1"
                               <?= $settings['undo_send_enabled'] === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="undo_send_enabled">
                            Enable undo send
                        </label>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="form-label mb-0 small fw-semibold" for="undo_send_seconds">Undo window</label>
                        <input type="number" class="form-control form-control-sm" style="width:90px;"
                               id="undo_send_seconds" name="undo_send_seconds"
                               min="3" max="120" step="1"
                               value="<?= (int) $settings['undo_send_seconds'] ?>">
                        <span class="text-muted small">seconds</span>
                    </div>
                </div>
                <div class="form-text">
                    How long the sender has to click Undo before the message actually goes out (3–120 seconds).
                    Applies to new tickets and replies on both the staff and portal sides.
                </div>
            </div>

            <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Settings
            </button>
        </form>
    </div>
</div>
<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

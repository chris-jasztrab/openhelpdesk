<?php
$layout       = 'app';
$pageTitle    = 'Danger Zone – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Danger Zone'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="mb-4">
    <h5 class="fw-bold mb-1 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Danger Zone</h5>
    <p class="text-muted mb-0">Destructive actions that cannot be undone. Proceed with caution.</p>
</div>

<div class="card border-danger border-opacity-50 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold mb-1">Delete All Tickets</h6>
                <p class="text-muted mb-0 small">
                    Permanently removes all <?= $ticketCount ?> ticket(s) and their associated timeline entries,
                    attachments, and notifications. This action cannot be undone.
                </p>
            </div>
            <div class="ms-4">
                <?php if ($ticketCount > 0): ?>
                <form method="POST" action="/admin/tickets/delete-all" class="d-inline"
                      onsubmit="return prompt('Type DELETE to confirm removing all <?= $ticketCount ?> tickets:') === 'DELETE';">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete All Tickets
                    </button>
                </form>
                <?php else: ?>
                <button class="btn btn-outline-secondary" disabled>No Tickets</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

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

<div class="card border-danger border-opacity-50 shadow-sm mt-3">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h6 class="fw-bold mb-1">Reset to Fresh State</h6>
                <p class="text-muted mb-0 small">
                    Permanently deletes <strong>all</strong> data — users, tickets, KB articles, automations,
                    escalations, settings, and more. The system will be restored to its initial state and you
                    will be walked through the setup wizard to create a new admin account. This action
                    <strong>cannot be undone</strong>.
                </p>
            </div>
            <div class="ms-4 flex-shrink-0">
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#resetModal">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Everything
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reset Confirmation Modal -->
<div class="modal fade" id="resetModal" tabindex="-1" aria-labelledby="resetModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="resetModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i>Reset to Fresh State
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/admin/settings/danger-zone/reset" id="resetForm">
                <?= csrfField() ?>
                <div class="modal-body">
                    <div class="alert alert-danger mb-3">
                        <strong>Warning:</strong> This will permanently delete <em>all</em> users, tickets,
                        knowledge base articles, automations, escalations, SLA policies, locations, groups,
                        and all settings. You will be logged out immediately.
                    </div>
                    <p class="mb-3">To confirm, type <strong>RESET</strong> in the box below:</p>
                    <input type="text" class="form-control" id="resetConfirmInput"
                           placeholder="Type RESET to confirm" autocomplete="off">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="resetSubmitBtn" disabled>
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Everything
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('resetConfirmInput').addEventListener('input', function () {
    document.getElementById('resetSubmitBtn').disabled = this.value !== 'RESET';
});
</script>

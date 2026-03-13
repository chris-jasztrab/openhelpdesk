<?php
$layout       = 'app';
$pageTitle    = 'Delete Ticket Type – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Ticket Types', 'url' => '/admin/types'],
    ['label' => 'Delete "' . e($type['name']) . '"'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/admin/types" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <h5 class="fw-bold mb-0">Delete Ticket Type: <?= e($type['name']) ?></h5>
</div>

<?php if ($ticketCount === 0): ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="alert alert-success mb-4">
            <i class="bi bi-check-circle me-2"></i>
            No tickets currently use the <strong><?= e($type['name']) ?></strong> type. It is safe to delete.
        </div>
        <form method="POST" action="/admin/types/<?= (int) $type['id'] ?>/delete">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-trash me-1"></i>Delete "<?= e($type['name']) ?>"
            </button>
            <a href="/admin/types" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>
    </div>
</div>

<?php else: ?>

<div class="alert alert-warning d-flex gap-2 align-items-start">
    <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 flex-shrink-0"></i>
    <div>
        <strong><?= number_format($ticketCount) ?> ticket<?= $ticketCount !== 1 ? 's' : '' ?></strong>
        currently <?= $ticketCount !== 1 ? 'use' : 'uses' ?> the <strong><?= e($type['name']) ?></strong> type.
        Choose how to handle them before deleting this type.
    </div>
</div>

<div class="row g-4 mb-4">

    <!-- Option 1: Reassign -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-1"><i class="bi bi-arrow-repeat text-primary me-2"></i>Bulk Reassign</h6>
                <p class="text-muted small mb-3">
                    Change all <?= number_format($ticketCount) ?> affected ticket<?= $ticketCount !== 1 ? 's' : '' ?> to a different type, then delete this one.
                </p>
                <?php if (empty($otherTypes)): ?>
                <p class="text-muted small fst-italic">No other ticket types exist. Create one first.</p>
                <?php else: ?>
                <form method="POST" action="/admin/types/<?= (int) $type['id'] ?>/delete">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reassign">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reassign to</label>
                        <select name="new_type_id" class="form-select" required>
                            <option value="">— Select a type —</option>
                            <?php foreach ($otherTypes as $ot): ?>
                            <option value="<?= (int) $ot['id'] ?>"><?= e($ot['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-arrow-repeat me-1"></i>Reassign & Delete
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Option 2: Delete tickets -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100 border-danger border-opacity-25">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-1 text-danger"><i class="bi bi-trash text-danger me-2"></i>Delete All Affected Tickets</h6>
                <p class="text-muted small mb-3">
                    Permanently delete all <?= number_format($ticketCount) ?> ticket<?= $ticketCount !== 1 ? 's' : '' ?> that use this type, then delete the type. <strong class="text-danger">This cannot be undone.</strong>
                </p>
                <button type="button" class="btn btn-danger"
                        data-bs-toggle="modal" data-bs-target="#deleteTicketsTypeModal">
                    <i class="bi bi-trash me-1"></i>Delete Tickets & Type
                </button>
            </div>
        </div>
    </div>

</div>

<!-- Option 3: Edit individually -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-pencil-square text-secondary me-2"></i>Edit Tickets Individually</h6>
    </div>
    <div class="card-body p-0">
        <p class="text-muted small px-4 pt-3 mb-2">
            Open each ticket and change its type manually. The type will not be deleted until all tickets are reassigned or removed.
        </p>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td class="text-muted small">#<?= (int) $t['id'] ?></td>
                        <td><?= e($t['subject']) ?></td>
                        <td><span class="badge bg-secondary"><?= e($t['status']) ?></span></td>
                        <td class="text-muted small">
                            <?= $t['first_name'] ? e($t['first_name'] . ' ' . $t['last_name']) : '—' ?>
                        </td>
                        <td>
                            <a href="/admin/tickets/<?= (int) $t['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Open
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<a href="/admin/types" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Cancel — Keep This Type
</a>

<?php endif; ?>

<!-- Delete Tickets & Type Modal -->
<div class="modal fade" id="deleteTicketsTypeModal" tabindex="-1" aria-labelledby="deleteTicketsTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteTicketsTypeModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Tickets &amp; Type
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete <strong><?= number_format($ticketCount) ?> ticket<?= $ticketCount !== 1 ? 's' : '' ?></strong> and this ticket type? <span class="text-danger fw-semibold">This cannot be undone.</span></p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="/admin/types/<?= (int) $type['id'] ?>/delete">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_tickets">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-trash me-1"></i>Delete Tickets &amp; Type
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

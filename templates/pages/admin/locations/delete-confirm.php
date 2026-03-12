<?php
$layout       = 'app';
$pageTitle    = 'Delete Location – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Locations', 'url' => '/admin/locations'],
    ['label' => 'Delete "' . e($location['name']) . '"'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/admin/locations" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <h5 class="fw-bold mb-0">Delete Location: <?= e($location['name']) ?></h5>
</div>

<?php if ($ticketCount === 0): ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="alert alert-success mb-4">
            <i class="bi bi-check-circle me-2"></i>
            No tickets currently use the <strong><?= e($location['name']) ?></strong> location. It is safe to delete.
        </div>
        <form method="POST" action="/admin/locations/<?= (int) $location['id'] ?>/delete">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-danger">
                <i class="bi bi-trash me-1"></i>Delete "<?= e($location['name']) ?>"
            </button>
            <a href="/admin/locations" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>
    </div>
</div>

<?php else: ?>

<div class="alert alert-warning d-flex gap-2 align-items-start">
    <i class="bi bi-exclamation-triangle-fill fs-5 mt-1 flex-shrink-0"></i>
    <div>
        <strong><?= number_format($ticketCount) ?> ticket<?= $ticketCount !== 1 ? 's' : '' ?></strong>
        currently <?= $ticketCount !== 1 ? 'use' : 'uses' ?> the <strong><?= e($location['name']) ?></strong> location.
        Choose how to handle them before deleting this location.
    </div>
</div>

<div class="row g-4 mb-4">

    <!-- Option 1: Reassign -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-1"><i class="bi bi-arrow-repeat text-primary me-2"></i>Bulk Reassign</h6>
                <p class="text-muted small mb-3">
                    Change all <?= number_format($ticketCount) ?> affected ticket<?= $ticketCount !== 1 ? 's' : '' ?> to a different location, then delete this one.
                </p>
                <?php if (empty($otherLocations)): ?>
                <p class="text-muted small fst-italic">No other locations exist. Create one first.</p>
                <?php else: ?>
                <form method="POST" action="/admin/locations/<?= (int) $location['id'] ?>/delete">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reassign">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reassign to</label>
                        <select name="new_location_id" class="form-select" required>
                            <option value="">— Select a location —</option>
                            <?php foreach ($otherLocations as $ol): ?>
                            <option value="<?= (int) $ol['id'] ?>"><?= e($ol['name']) ?></option>
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

    <!-- Option 2: Set to none -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-1"><i class="bi bi-x-circle text-secondary me-2"></i>Clear Location</h6>
                <p class="text-muted small mb-3">
                    Remove the location from all <?= number_format($ticketCount) ?> affected ticket<?= $ticketCount !== 1 ? 's' : '' ?> (set to none), then delete this location.
                </p>
                <form method="POST" action="/admin/locations/<?= (int) $location['id'] ?>/delete"
                      onsubmit="return confirm('Clear the location from <?= $ticketCount ?> ticket<?= $ticketCount !== 1 ? 's' : '' ?> and delete this location?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-1"></i>Clear & Delete
                    </button>
                </form>
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
            Open each ticket and change its location manually. The location will not be deleted until all tickets are reassigned or cleared.
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

<a href="/admin/locations" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Cancel — Keep This Location
</a>

<?php endif; ?>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

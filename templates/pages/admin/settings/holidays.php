<?php
$layout       = 'app';
$pageTitle    = 'Holidays – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Holidays'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-calendar-x me-2"></i>Holidays &amp; Closed Days</h5>
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#autoPopulateModal">
        <i class="bi bi-magic me-1"></i>Auto-Populate Year
    </button>
</div>

<!-- Auto-Populate Modal -->
<div class="modal fade" id="autoPopulateModal" tabindex="-1" aria-labelledby="autoPopulateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="autoPopulateModalLabel">
                    <i class="bi bi-magic me-2"></i>Auto-Populate Holidays
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="/admin/settings/holidays/auto-populate">
                <?= csrfField() ?>
                <div class="modal-body">
                    <p class="text-muted small mb-4">
                        The system will add all federal and provincial/state public holidays for the
                        selected country and year. Variable-date holidays like Easter are calculated
                        for the exact year. Any dates already in your list will be skipped.
                    </p>

                    <div class="mb-3">
                        <label for="auto_country" class="form-label fw-semibold">Country</label>
                        <select class="form-select" id="auto_country" name="country" required>
                            <option value="" disabled selected>Select a country…</option>
                            <option value="CA">Canada</option>
                            <option value="US">United States</option>
                            <option value="GB">United Kingdom</option>
                            <option value="AU">Australia</option>
                            <option value="NZ">New Zealand</option>
                            <option value="IE">Ireland</option>
                        </select>
                    </div>

                    <div class="mb-1">
                        <label for="auto_year" class="form-label fw-semibold">Year</label>
                        <input type="number" class="form-control" id="auto_year" name="year"
                               value="<?= date('Y') ?>" min="1990" max="2100" required>
                    </div>
                    <div class="form-text mb-2">All added holidays will be set to exclude from SLA by default.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white px-4" style="background:var(--ld-primary);">
                        <i class="bi bi-calendar-plus me-1"></i>Populate Holidays
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row g-4">
<div class="col-lg-5">

<!-- Add Holiday -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-plus-circle me-1"></i>Add Holiday or Closed Day
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Define specific dates your organisation is closed. Optionally exclude these days
            from SLA timers so the clock does not run while you are closed.
        </p>
        <form method="POST" action="/admin/settings/holidays">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="holiday_date" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="holiday_date" name="holiday_date"
                       value="<?= e($_POST['holiday_date'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label for="holiday_name" class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="holiday_name" name="name"
                       value="<?= e($_POST['name'] ?? '') ?>"
                       placeholder="e.g. Christmas Day" required>
            </div>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="exclude_from_sla" name="exclude_from_sla" value="1" checked>
                    <label class="form-check-label fw-semibold" for="exclude_from_sla">
                        Exclude from SLA
                    </label>
                </div>
                <div class="form-text">
                    When on, SLA timers skip this date — the clock does not run and due dates
                    are pushed forward accordingly. When off, the date is recorded for reference
                    but SLA timers continue as normal.
                </div>
            </div>

            <button type="submit" class="btn text-white px-4" style="background:var(--ld-primary);">
                <i class="bi bi-plus-lg me-1"></i>Add
            </button>
        </form>
    </div>
</div>

</div><!-- /col -->
<div class="col-lg-7">

<!-- Holiday List -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-calendar-event me-1"></i>Defined Holidays
    </div>
    <?php if (empty($holidays)): ?>
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-calendar-x fs-2 d-block mb-2 opacity-50"></i>
        No holidays defined yet. Add one using the form.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th class="text-center">Exclude from SLA</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($holidays as $h): ?>
            <tr>
                <td class="fw-semibold text-nowrap">
                    <?= e(date('M j, Y', strtotime($h['holiday_date']))) ?>
                </td>
                <td><?= e($h['name']) ?></td>
                <td class="text-center">
                    <form method="POST" action="/admin/settings/holidays/toggle" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
                        <button type="submit"
                                class="btn btn-sm <?= $h['exclude_from_sla'] ? 'btn-success' : 'btn-outline-secondary' ?>"
                                title="<?= $h['exclude_from_sla'] ? 'SLA excluded — click to count this day' : 'SLA counts — click to exclude this day' ?>">
                            <?php if ($h['exclude_from_sla']): ?>
                                <i class="bi bi-shield-check me-1"></i>Excluded
                            <?php else: ?>
                                <i class="bi bi-shield me-1"></i>Counts
                            <?php endif; ?>
                        </button>
                    </form>
                </td>
                <td class="text-end">
                    <form method="POST" action="/admin/settings/holidays/delete"
                          onsubmit="return confirm('Remove <?= e(addslashes($h['name'])) ?> from holidays?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</div><!-- /col -->
</div><!-- /row -->

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

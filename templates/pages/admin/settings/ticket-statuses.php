<?php
$layout       = 'app';
$pageTitle    = 'Ticket Statuses';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Ticket Statuses'],
];

/** @var array<int,array<string,mixed>> $statuses — from ticketStatuses(); */
$old = $_SESSION['_old_input'] ?? [];
unset($_SESSION['_old_input']);
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5 class="fw-semibold mb-1"><i class="bi bi-circle-half me-2"></i>Ticket Statuses</h5>
        <p class="text-muted small mb-0">
            Add, remove, recolor, or re&dash;order the statuses tickets can move through.
            Drag the grip in any row to change ordering — the order here drives every status dropdown across the app.
        </p>
    </div>
    <button type="button" class="btn text-white" style="background:var(--ld-primary);"
            data-bs-toggle="modal" data-bs-target="#addStatusModal">
        <i class="bi bi-plus-circle me-1"></i>Add Status
    </button>
</div>

<div class="alert alert-info py-2 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Buckets</strong> determine business logic: tickets in the <em>open</em> bucket count toward "open" dashboard tiles and SLA tracking; tickets in the <em>closed</em> bucket are treated as resolved (and trigger the resolved/closed email + CSAT flows when set as the default for those kinds).
    Built-in statuses can be relabeled or recolored but not deleted &mdash; to <strong>hide one from dropdowns</strong>, click the green checkmark in the <em>Active</em> column to toggle it off. Existing tickets keep their stored status either way.
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0"
               data-sortable-list data-reorder-url="/admin/settings/ticket-statuses/reorder">
            <thead class="table-light">
                <tr>
                    <th style="width:90px">Color</th>
                    <th>Status</th>
                    <th style="width:90px">Bucket</th>
                    <th style="width:90px;text-align:center">Pauses SLA</th>
                    <th style="width:220px">Defaults</th>
                    <th style="width:90px;text-align:center">Active</th>
                    <th style="width:130px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($statuses as $s): ?>
                <tr data-id="<?= (int) $s['id'] ?>" data-slug="<?= e($s['slug']) ?>">
                    <td>
                        <?= ticketStatusBadgeHtml($s['slug']) ?>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= e($s['label']) ?></div>
                        <div class="text-muted small font-monospace"><?= e($s['slug']) ?><?php if ($s['is_system']): ?> <span class="badge bg-secondary ms-1" style="font-size:.65rem;">built-in</span><?php endif; ?></div>
                    </td>
                    <td>
                        <?php if ($s['bucket'] === 'open'): ?>
                            <span class="badge bg-primary-subtle text-primary">Open</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary">Closed</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($s['pauses_sla']): ?>
                            <i class="bi bi-pause-circle-fill text-warning" title="SLA pauses while in this status"></i>
                        <?php else: ?>
                            <i class="bi bi-dash text-muted"></i>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <?php if ($s['is_default_new']): ?>
                                <span class="badge bg-info-subtle text-info" title="New tickets land in this status">New</span>
                            <?php endif; ?>
                            <?php if ($s['is_default_resolved']): ?>
                                <span class="badge bg-success-subtle text-success" title="Fires the 'resolved' email + CSAT trigger">Resolved</span>
                            <?php endif; ?>
                            <?php if ($s['is_default_closed']): ?>
                                <span class="badge bg-secondary-subtle text-secondary" title="Fires the 'closed' email">Closed</span>
                            <?php endif; ?>
                            <?php
                            $eligibleNew      = $s['bucket'] === 'open';
                            $eligibleResolved = $s['bucket'] === 'closed';
                            $eligibleClosed   = $s['bucket'] === 'closed';
                            $canSetAny = ($eligibleNew && !$s['is_default_new'])
                                || ($eligibleResolved && !$s['is_default_resolved'])
                                || ($eligibleClosed && !$s['is_default_closed']);
                            ?>
                            <?php if ($canSetAny): ?>
                                <div class="dropdown">
                                    <button class="btn btn-link btn-sm p-0 text-muted small" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-gear"></i> set
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if ($eligibleNew && !$s['is_default_new']): ?>
                                        <li>
                                            <form method="POST" action="/admin/settings/ticket-statuses/<?= (int) $s['id'] ?>/set-default" class="m-0">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="kind" value="new">
                                                <button class="dropdown-item" type="submit">Make default for new tickets</button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                        <?php if ($eligibleResolved && !$s['is_default_resolved']): ?>
                                        <li>
                                            <form method="POST" action="/admin/settings/ticket-statuses/<?= (int) $s['id'] ?>/set-default" class="m-0">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="kind" value="resolved">
                                                <button class="dropdown-item" type="submit">Make default for resolved</button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                        <?php if ($eligibleClosed && !$s['is_default_closed']): ?>
                                        <li>
                                            <form method="POST" action="/admin/settings/ticket-statuses/<?= (int) $s['id'] ?>/set-default" class="m-0">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="kind" value="closed">
                                                <button class="dropdown-item" type="submit">Make default for closed</button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-center">
                        <form method="POST" action="/admin/settings/ticket-statuses/<?= (int) $s['id'] ?>/toggle-active" class="m-0">
                            <?= csrfField() ?>
                            <?php if ($s['is_active']): ?>
                                <button type="submit" class="btn btn-sm btn-link p-0 text-success" title="Active &mdash; click to deactivate">
                                    <i class="bi bi-check-circle-fill fs-5"></i>
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-sm btn-link p-0 text-muted" title="Inactive &mdash; click to activate">
                                    <i class="bi bi-eye-slash fs-5"></i>
                                </button>
                            <?php endif; ?>
                        </form>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary" title="Edit"
                                    data-bs-toggle="modal" data-bs-target="#editStatusModal"
                                    data-id="<?= (int) $s['id'] ?>"
                                    data-slug="<?= e($s['slug']) ?>"
                                    data-label="<?= e($s['label']) ?>"
                                    data-bucket="<?= e($s['bucket']) ?>"
                                    data-color="<?= e($s['color']) ?>"
                                    data-pauses="<?= $s['pauses_sla'] ? '1' : '0' ?>"
                                    data-active="<?= $s['is_active'] ? '1' : '0' ?>"
                                    data-system="<?= $s['is_system'] ? '1' : '0' ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if (!$s['is_system']): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                    data-bs-toggle="modal" data-bs-target="#deleteStatusModal"
                                    data-id="<?= (int) $s['id'] ?>"
                                    data-slug="<?= e($s['slug']) ?>"
                                    data-label="<?= e($s['label']) ?>"
                                    data-bucket="<?= e($s['bucket']) ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Add Status modal ──────────────────────────────────────────── -->
<div class="modal fade" id="addStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="/admin/settings/ticket-statuses/create">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Add Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="addLabel" class="form-label fw-semibold">Label</label>
                        <input type="text" class="form-control" id="addLabel" name="label" required maxlength="64"
                               value="<?= e($old['label'] ?? '') ?>"
                               placeholder="e.g., Awaiting Vendor">
                        <div class="form-text">Shown on badges and dropdowns. Can be changed later.</div>
                    </div>
                    <div class="mb-3">
                        <label for="addSlug" class="form-label fw-semibold">Slug</label>
                        <input type="text" class="form-control font-monospace" id="addSlug" name="slug" required
                               maxlength="64" pattern="[a-z][a-z0-9_]*"
                               value="<?= e($old['slug'] ?? '') ?>"
                               placeholder="awaiting_vendor">
                        <div class="form-text">
                            Permanent identifier &mdash; <strong>cannot be changed after create</strong>.
                            Lowercase letters, digits, and underscores only. Start with a letter.
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="addBucket" class="form-label fw-semibold">Bucket</label>
                            <select class="form-select" id="addBucket" name="bucket">
                                <option value="open"<?= ($old['bucket'] ?? 'open') === 'open' ? ' selected' : '' ?>>Open (active)</option>
                                <option value="closed"<?= ($old['bucket'] ?? '') === 'closed' ? ' selected' : '' ?>>Closed (done)</option>
                            </select>
                            <div class="form-text">Drives dashboard counters and SLA filters.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="addColor" class="form-label fw-semibold">Color</label>
                            <input type="color" class="form-control form-control-color" id="addColor" name="color"
                                   value="<?= e($old['color'] ?? '#0d6efd') ?>" title="Badge color">
                            <div class="form-text">Text contrast picks itself.</div>
                        </div>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="addPauses" name="pauses_sla" value="1"
                               <?= !empty($old['pauses_sla']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="addPauses">
                            Pause SLA timers while a ticket is in this status
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                        <i class="bi bi-check-lg me-1"></i>Create
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Edit Status modal ─────────────────────────────────────────── -->
<div class="modal fade" id="editStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="editStatusForm" action="">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>Edit Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editLabel" class="form-label fw-semibold">Label</label>
                        <input type="text" class="form-control" id="editLabel" name="label" required maxlength="64">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Slug</label>
                        <div class="form-control font-monospace bg-light text-muted" id="editSlugDisplay"
                             style="cursor:not-allowed;"></div>
                        <div class="form-text">
                            Slugs are permanent so existing tickets, automations, and integrations keep working.
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="editBucket" class="form-label fw-semibold">Bucket</label>
                            <select class="form-select" id="editBucket" name="bucket">
                                <option value="open">Open (active)</option>
                                <option value="closed">Closed (done)</option>
                            </select>
                            <div class="form-text" id="editBucketWarning" style="display:none;color:var(--bs-warning-text-emphasis);">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Changing the bucket recategorizes existing tickets in reports and dashboards.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="editColor" class="form-label fw-semibold">Color</label>
                            <input type="color" class="form-control form-control-color" id="editColor" name="color">
                        </div>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="editPauses" name="pauses_sla" value="1">
                        <label class="form-check-label" for="editPauses">Pause SLA timers while a ticket is in this status</label>
                    </div>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="editActive" name="is_active" value="1">
                        <label class="form-check-label" for="editActive">
                            Active (available in status dropdowns)
                        </label>
                        <div class="form-text">When inactive, this status is hidden from pickers. Existing tickets still display their stored status.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Delete Status modal ───────────────────────────────────────── -->
<div class="modal fade" id="deleteStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="deleteStatusForm" action="" class="m-0">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-trash me-2 text-danger"></i>Delete Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Delete status <strong id="deleteStatusName"></strong>? This cannot be undone.</p>
                    <div id="deleteReassignSection" style="display:none;" class="mt-3">
                        <div class="alert alert-warning py-2 small mb-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <span id="deleteTicketCount">0</span> ticket(s) currently use this status.
                            They'll be reassigned before the status is removed.
                        </div>
                        <label for="deleteReassignTo" class="form-label fw-semibold">Reassign tickets to:</label>
                        <select class="form-select" id="deleteReassignTo" name="reassign_to">
                            <!-- Filled by JS to active statuses minus the one being deleted, same bucket preferred -->
                        </select>
                        <div class="form-text">Active statuses only. The same-bucket option is recommended.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Build a JS-side lookup of (slug → {label, bucket}) for active statuses
// so the delete modal can populate its reassign dropdown without another
// round-trip. Tickets-per-slug count is also passed so the modal knows
// whether to show the reassign UI.
$activeForJs = [];
foreach (ticketActiveStatuses() as $__s) {
    $activeForJs[] = ['slug' => $__s['slug'], 'label' => $__s['label'], 'bucket' => $__s['bucket']];
}
$ticketCountsBySlug = [];
$pdo = \Database::connect();
foreach ($pdo->query("SELECT status, COUNT(*) AS n FROM tickets GROUP BY status")->fetchAll(\PDO::FETCH_ASSOC) as $__r) {
    $ticketCountsBySlug[$__r['status']] = (int) $__r['n'];
}
?>
<script>
window.ticketStatusActive       = <?= json_encode($activeForJs, JSON_UNESCAPED_SLASHES) ?>;
window.ticketStatusTicketCounts = <?= json_encode($ticketCountsBySlug, JSON_UNESCAPED_SLASHES) ?>;
</script>

<script>
(function () {
    var editModal = document.getElementById('editStatusModal');
    editModal.addEventListener('show.bs.modal', function (e) {
        var btn = e.relatedTarget;
        var id  = btn.dataset.id;
        document.getElementById('editStatusForm').action  = '/admin/settings/ticket-statuses/' + id + '/edit';
        document.getElementById('editLabel').value        = btn.dataset.label || '';
        document.getElementById('editSlugDisplay').textContent = btn.dataset.slug || '';
        document.getElementById('editBucket').value       = btn.dataset.bucket || 'open';
        document.getElementById('editColor').value        = btn.dataset.color || '#6c757d';
        document.getElementById('editPauses').checked     = btn.dataset.pauses === '1';
        document.getElementById('editActive').checked     = btn.dataset.active === '1';
        editModal.dataset.originalBucket = btn.dataset.bucket || 'open';
        document.getElementById('editBucketWarning').style.display = 'none';
    });
    document.getElementById('editBucket').addEventListener('change', function (e) {
        var orig = editModal.dataset.originalBucket || 'open';
        document.getElementById('editBucketWarning').style.display =
            e.target.value !== orig ? 'block' : 'none';
    });

    var deleteModal = document.getElementById('deleteStatusModal');
    deleteModal.addEventListener('show.bs.modal', function (e) {
        var btn  = e.relatedTarget;
        var slug = btn.dataset.slug || '';
        document.getElementById('deleteStatusName').textContent = btn.dataset.label || '';
        document.getElementById('deleteStatusForm').action =
            '/admin/settings/ticket-statuses/' + btn.dataset.id + '/delete';

        // Populate reassign dropdown if this status has tickets pointing at it.
        var counts  = window.ticketStatusTicketCounts || {};
        var n       = counts[slug] || 0;
        var section = document.getElementById('deleteReassignSection');
        var select  = document.getElementById('deleteReassignTo');
        select.innerHTML = '';
        if (n > 0) {
            section.style.display = 'block';
            document.getElementById('deleteTicketCount').textContent = String(n);
            var thisBucket = btn.dataset.bucket || '';
            var same = [], other = [];
            (window.ticketStatusActive || []).forEach(function (s) {
                if (s.slug === slug) return;            // can't reassign to itself
                (s.bucket === thisBucket ? same : other).push(s);
            });
            same.forEach(function (s) {
                var opt = document.createElement('option');
                opt.value = s.slug;
                opt.textContent = s.label + ' (' + s.bucket + ')';
                select.appendChild(opt);
            });
            other.forEach(function (s) {
                var opt = document.createElement('option');
                opt.value = s.slug;
                opt.textContent = s.label + ' (' + s.bucket + ')';
                select.appendChild(opt);
            });
            select.required = true;
        } else {
            section.style.display = 'none';
            select.required = false;
        }
    });

    // Auto-suggest a slug from the label when the user types in Add modal
    var addLabel = document.getElementById('addLabel');
    var addSlug  = document.getElementById('addSlug');
    var slugTouched = false;
    addSlug.addEventListener('input', function () { slugTouched = addSlug.value.length > 0; });
    addLabel.addEventListener('input', function () {
        if (slugTouched) return;
        addSlug.value = (addLabel.value || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .replace(/^[^a-z]+/, '')
            .slice(0, 64);
    });
    document.getElementById('addStatusModal').addEventListener('hidden.bs.modal', function () {
        slugTouched = false;
    });
})();
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
<?php require ROOT_DIR . '/templates/partials/sortable-list.php'; ?>

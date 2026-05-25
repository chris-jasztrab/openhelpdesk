<?php
$layout       = 'app';
$pageTitle    = 'Canned Responses – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Canned Responses'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1"><i class="bi bi-chat-square-text me-2"></i>Global Canned Responses</h5>
        <p class="text-muted small mb-0">Saved reply snippets available to all agents. Agents can also create their own personal responses.</p>
    </div>
    <a href="/admin/settings/canned-responses/create" class="btn text-white" style="background:var(--ld-primary);">
        <i class="bi bi-plus-lg me-1"></i>Add Response
    </a>
</div>

<!-- Personal responses callout for admins -->
<div class="alert alert-light border d-flex align-items-center justify-content-between mb-4 py-2 px-3">
    <div class="small">
        <i class="bi bi-person-circle me-1 text-muted"></i>
        Want to create responses only you can see?
    </div>
    <a href="/agent/canned-responses" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-person-lines-fill me-1"></i>My Personal Responses
    </a>
</div>

<?php if (empty($responses)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-chat-square-text fs-1 d-block mb-3 opacity-25"></i>
        <p class="mb-3">No global canned responses yet.</p>
        <a href="/admin/settings/canned-responses/create" class="btn text-white btn-sm" style="background:var(--ld-primary);">
            <i class="bi bi-plus-lg me-1"></i>Create first response
        </a>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0"
               data-sortable-list data-reorder-url="/admin/settings/canned-responses/reorder">
            <thead class="table-light">
                <tr>
                    <th data-sort-col="title">Title</th>
                    <th>Preview</th>
                    <th style="width:110px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($responses as $r): ?>
                <tr data-id="<?= (int) $r['id'] ?>">
                    <td class="fw-semibold" data-sort-value="<?= e($r['title']) ?>"><?= e($r['title']) ?></td>
                    <td class="text-muted small" style="max-width:380px;">
                        <span style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                            <?= e($r['body']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/admin/settings/canned-responses/<?= (int) $r['id'] ?>/edit"
                               class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                    data-bs-toggle="modal" data-bs-target="#deleteCannedResponseModal"
                                    data-id="<?= (int) $r['id'] ?>"
                                    data-title="<?= e($r['title']) ?>"
                                    data-url="/admin/settings/canned-responses/<?= (int) $r['id'] ?>/delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Delete Canned Response Modal -->
<div class="modal fade" id="deleteCannedResponseModal" tabindex="-1" aria-labelledby="deleteCannedResponseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteCannedResponseModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Canned Response
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete canned response <strong id="deleteCannedResponseTitle"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteCannedResponseForm" action="">
                    <?= csrfField() ?>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('deleteCannedResponseModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteCannedResponseTitle').textContent = btn.dataset.title;
    document.getElementById('deleteCannedResponseForm').action = btn.dataset.url;
});
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
<?php require ROOT_DIR . '/templates/partials/sortable-list.php'; ?>

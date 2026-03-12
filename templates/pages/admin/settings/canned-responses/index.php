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
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Preview</th>
                    <th style="width:90px;">Sort</th>
                    <th style="width:110px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($responses as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['title']) ?></td>
                    <td class="text-muted small" style="max-width:380px;">
                        <span style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                            <?= e($r['body']) ?>
                        </span>
                    </td>
                    <td class="text-muted"><?= (int) $r['sort_order'] ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/admin/settings/canned-responses/<?= (int) $r['id'] ?>/edit"
                               class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="/admin/settings/canned-responses/<?= (int) $r['id'] ?>/delete"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete canned response \'<?= e(addslashes($r['title'])) ?>\'?')">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

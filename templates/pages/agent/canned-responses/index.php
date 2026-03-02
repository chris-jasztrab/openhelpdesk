<?php
$layout       = 'app';
$pageTitle    = 'Canned Responses';
$sidebarItems = agentSidebar('canned-responses');
$breadcrumbs  = [
    ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Canned Responses'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-chat-square-text me-2"></i>Canned Responses</h2>
        <p class="text-muted mb-0">Manage your personal reply snippets, and view global responses created by admins.</p>
    </div>
    <a href="/agent/canned-responses/create" class="btn text-white" style="background:var(--ld-primary);">
        <i class="bi bi-plus-lg me-1"></i>Add Response
    </a>
</div>

<!-- My Personal Responses -->
<h6 class="fw-semibold text-muted text-uppercase small mb-2">My Responses</h6>
<?php if (empty($myResponses)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-chat-square-text fs-1 d-block mb-3 opacity-25"></i>
        <p class="mb-3">You haven't created any personal canned responses yet.</p>
        <a href="/agent/canned-responses/create" class="btn text-white btn-sm" style="background:var(--ld-primary);">
            <i class="bi bi-plus-lg me-1"></i>Create first response
        </a>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm mb-4">
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
                <?php foreach ($myResponses as $r): ?>
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
                            <a href="/agent/canned-responses/<?= (int) $r['id'] ?>/edit"
                               class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="/agent/canned-responses/<?= (int) $r['id'] ?>/delete"
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

<!-- Global Admin Responses (read-only) -->
<?php if (!empty($globalResponses)): ?>
<h6 class="fw-semibold text-muted text-uppercase small mb-2">Global Responses <span class="badge bg-secondary fw-normal ms-1">Admin-managed</span></h6>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Title</th>
                    <th>Preview</th>
                    <th style="width:90px;">Sort</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($globalResponses as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['title']) ?></td>
                    <td class="text-muted small" style="max-width:480px;">
                        <span style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
                            <?= e($r['body']) ?>
                        </span>
                    </td>
                    <td class="text-muted"><?= (int) $r['sort_order'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

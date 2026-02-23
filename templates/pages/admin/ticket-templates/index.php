<?php
$layout       = 'app';
$pageTitle    = 'Ticket Templates';
$sidebarItems = adminSidebar('tickets');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Tickets', 'url' => '/admin/tickets'],
    ['label' => 'Templates'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Ticket Templates</h2>
    <div class="d-flex gap-2">
        <a href="/admin/tickets" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Tickets
        </a>
        <a href="/admin/ticket-templates/create" class="btn btn-sm text-white" style="background:var(--ld-primary);">
            <i class="bi bi-plus-lg me-1"></i>New Template
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Subject Preview</th>
                    <th>Type</th>
                    <th>Priority</th>
                    <th>Shared</th>
                    <th>Created By</th>
                    <th style="width:120px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($templates)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-collection" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">No templates yet. Create one to speed up ticket creation.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($templates as $tpl): ?>
                    <tr>
                        <td class="fw-semibold">
                            <i class="bi bi-collection text-muted me-1"></i><?= e($tpl['name']) ?>
                            <?php if ($tpl['description']): ?>
                                <div class="text-muted small"><?= e(mb_strimwidth($tpl['description'], 0, 80, '…')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small" style="max-width:220px;">
                            <?= e($tpl['subject'] ? mb_strimwidth($tpl['subject'], 0, 60, '…') : '—') ?>
                        </td>
                        <td class="small"><?= e($tpl['type_name'] ?? '—') ?></td>
                        <td class="small"><?= e($tpl['priority_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($tpl['is_shared']): ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-people me-1"></i>Shared
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Private</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= e($tpl['creator_name'] ?? '—') ?></td>
                        <td>
                            <?php
                            $canEdit = (Auth::role() === 'admin' || (int)$tpl['created_by'] === Auth::id());
                            ?>
                            <div class="d-flex gap-1">
                                <?php if ($canEdit): ?>
                                <a href="/admin/ticket-templates/<?= $tpl['id'] ?>/edit"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="/admin/ticket-templates/<?= $tpl['id'] ?>/delete"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this template?')">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-muted small fst-italic">View only</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

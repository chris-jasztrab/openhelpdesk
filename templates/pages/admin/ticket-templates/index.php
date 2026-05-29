<?php
$isAgentView  = !Auth::isAdmin();
$ticketsUrl   = $isAgentView ? '/agent/tickets' : '/admin/tickets';
$layout       = 'app';
$pageTitle    = 'Ticket Templates';
$sidebarItems = Auth::isAdmin() ? adminSidebar('tickets') : staffSidebar('tickets');
$breadcrumbs  = $isAgentView ? [
    ['label' => 'Agent',     'url' => '/agent'],
    ['label' => 'Tickets',   'url' => '/agent/tickets'],
    ['label' => 'Templates'],
] : [
    ['label' => 'Admin',     'url' => '/admin'],
    ['label' => 'Tickets',   'url' => '/admin/tickets'],
    ['label' => 'Templates'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Ticket Templates</h2>
    <div class="d-flex gap-2">
        <a href="<?= $ticketsUrl ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Tickets
        </a>
        <a id="tour-create-template-btn" href="/admin/ticket-templates/create" class="btn btn-sm text-white" style="background:var(--ld-primary);">
            <i class="bi bi-plus-lg me-1"></i>New Template
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm" id="tour-templates-card">
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
                            $canEdit = (Auth::isAdmin() || (int)$tpl['created_by'] === Auth::id());
                            ?>
                            <div class="d-flex gap-1">
                                <?php if ($canEdit): ?>
                                <a href="/admin/ticket-templates/<?= $tpl['id'] ?>/edit"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#deleteTemplateModal"
                                        data-id="<?= $tpl['id'] ?>"
                                        data-name="<?= e($tpl['name']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
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

<!-- Delete Template Modal -->
<div class="modal fade" id="deleteTemplateModal" tabindex="-1" aria-labelledby="deleteTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteTemplateModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Template
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete template <strong id="deleteTemplateName"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteTemplateForm" action="">
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
document.getElementById('deleteTemplateModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteTemplateName').textContent = btn.dataset.name;
    document.getElementById('deleteTemplateForm').action = '/admin/ticket-templates/' + btn.dataset.id + '/delete';
});
</script>

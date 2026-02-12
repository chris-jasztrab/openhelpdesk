<?php
$layout       = 'app';
$pageTitle    = 'Priorities';
$sidebarItems = adminSidebar('priorities');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Priorities'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Ticket Priorities</h2>
    <a href="/admin/priorities/create" class="btn text-white" style="background:#4f46e5;">
        <i class="bi bi-flag me-1"></i>Add Priority
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">Color</th>
                    <th>Name</th>
                    <th>Sort Order</th>
                    <th>Created</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($priorities)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No priorities found.</td></tr>
                <?php else: ?>
                    <?php foreach ($priorities as $p): ?>
                    <tr>
                        <td>
                            <span class="badge rounded-pill" style="background:<?= e($p['color']) ?>;min-width:28px;">&nbsp;</span>
                        </td>
                        <td class="fw-semibold">
                            <span class="badge" style="background:<?= e($p['color']) ?>;"><?= e($p['name']) ?></span>
                        </td>
                        <td class="text-muted"><?= (int) $p['sort_order'] ?></td>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/priorities/<?= $p['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="/admin/priorities/<?= $p['id'] ?>/delete" class="d-inline"
                                      onsubmit="return confirm('Delete this priority?')">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

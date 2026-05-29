<?php
$layout       = 'app';
$pageTitle    = 'Article History';
$sidebarItems = Auth::isAdmin() ? adminSidebar('kb') : staffSidebar('kb-articles');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Knowledge Base'],
    ['label' => 'Articles',       'url' => '/admin/kb/articles'],
    ['label' => e($article['title']), 'url' => '/admin/kb/articles/' . $article['id'] . '/edit'],
    ['label' => 'History'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Revision History</h2>
        <p class="text-muted mb-0"><?= e($article['title']) ?></p>
    </div>
    <a href="/admin/kb/articles/<?= $article['id'] ?>/edit" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Article
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Title at snapshot</th>
                    <th>Saved by</th>
                    <th>Snapshot time</th>
                    <th style="width:120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($revisions)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No revisions recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($revisions as $i => $rev): ?>
                    <tr>
                        <td class="text-muted small"><?= (int)$rev['id'] ?></td>
                        <td class="fw-semibold"><?= e($rev['title']) ?></td>
                        <td class="text-muted small"><?= e($rev['editor_name']) ?></td>
                        <td class="text-muted small"><?= date('M j, Y g:i a', strtotime($rev['created_at'])) ?></td>
                        <td>
                            <a href="/admin/kb/articles/<?= $article['id'] ?>/history/<?= (int)$rev['id'] ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-file-diff me-1"></i>View Diff
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

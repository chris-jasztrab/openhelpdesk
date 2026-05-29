<?php
$layout       = 'app';
$pageTitle    = 'Revision Diff';
$sidebarItems = Auth::isAdmin() ? adminSidebar('kb') : staffSidebar('kb-articles');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Knowledge Base'],
    ['label' => 'Articles',       'url' => '/admin/kb/articles'],
    ['label' => e($article['title']), 'url' => '/admin/kb/articles/' . $article['id'] . '/edit'],
    ['label' => 'History',        'url' => '/admin/kb/articles/' . $article['id'] . '/history'],
    ['label' => 'Diff'],
];
$isTooLarge = count($diff) === 1 && ($diff[0]['type'] ?? '') === 'too_large';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Revision #<?= (int)$revision['id'] ?></h2>
        <p class="text-muted mb-0">
            Snapshot from <?= date('M j, Y g:i a', strtotime($revision['created_at'])) ?>
            &nbsp;&mdash;&nbsp;saved by <strong><?= e($revision['editor_name']) ?></strong>
        </p>
    </div>
    <a href="/admin/kb/articles/<?= $article['id'] ?>/history" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to History
    </a>
</div>

<?php if ($isTooLarge): ?>
<!-- Side-by-side fallback when content is too large to diff line-by-line -->
<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-danger bg-opacity-10 fw-semibold text-danger">
                Revision snapshot (<?= date('M j, Y', strtotime($revision['created_at'])) ?>)
            </div>
            <div class="card-body p-0">
                <pre class="p-3 m-0" style="max-height:600px;overflow:auto;font-size:.82rem;white-space:pre-wrap;word-break:break-word;"><?= e($revision['body_markdown']) ?></pre>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-success bg-opacity-10 fw-semibold text-success">
                Current version
            </div>
            <div class="card-body p-0">
                <pre class="p-3 m-0" style="max-height:600px;overflow:auto;font-size:.82rem;white-space:pre-wrap;word-break:break-word;"><?= e($article['body_markdown']) ?></pre>
            </div>
        </div>
    </div>
</div>
<p class="text-muted small mt-3">
    <i class="bi bi-info-circle me-1"></i>
    The content is too large for an inline diff. Side-by-side view is shown instead.
</p>

<?php else: ?>

<!-- Revision title comparison -->
<?php if ($revision['title'] !== $article['title']): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header fw-semibold">Title change</div>
    <div class="card-body py-2 px-3">
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-danger">Old</span>
            <code><?= e($revision['title']) ?></code>
        </div>
        <div class="d-flex gap-2 align-items-center mt-1">
            <span class="badge bg-success">New</span>
            <code><?= e($article['title']) ?></code>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Line diff -->
<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Body diff — revision vs current</span>
        <span class="small text-muted">
            <span class="badge bg-success">+</span> added &nbsp;
            <span class="badge bg-danger">−</span> removed
        </span>
    </div>
    <div class="card-body p-0">
        <pre class="diff-view m-0" style="font-size:.82rem;overflow-x:auto;"><code><?php
foreach ($diff as $line):
    if ($line['type'] === 'eq'):
        echo '<span class="diff-eq">' . e($line['line']) . '</span>' . "\n";
    elseif ($line['type'] === 'del'):
        echo '<span class="diff-del">- ' . e($line['line']) . '</span>' . "\n";
    elseif ($line['type'] === 'add'):
        echo '<span class="diff-add">+ ' . e($line['line']) . '</span>' . "\n";
    endif;
endforeach;
?></code></pre>
    </div>
</div>

<?php endif; ?>

<style>
.diff-view { background: #1e1e1e; color: #d4d4d4; padding: 1rem; border-radius: 0 0 .5rem .5rem; }
.diff-del  { display: block; background: rgba(248, 113, 113, .18); color: #f87171; }
.diff-add  { display: block; background: rgba( 52, 211, 153, .18); color: #34d399; }
.diff-eq   { display: block; }
</style>

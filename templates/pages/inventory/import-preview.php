<?php
$layout       = 'app';
$pageTitle    = 'Preview – Import – ' . label('nav.inventory', 'Inventory');
$sidebarItems = adminSidebar('inventory');
$breadcrumbs  = [
    ['label' => label('nav.inventory', 'Inventory'), 'url' => '/inventory'],
    ['label' => 'Import', 'url' => '/inventory/import'],
    ['label' => 'Match columns', 'url' => '/inventory/import/map'],
    ['label' => 'Preview'],
];
$c     = $plan['counts'];
$doing = $c['create'] + $c['update'];
$badge = ['create' => ['success', 'New'], 'update' => ['primary', 'Update'], 'skip' => ['secondary', 'Skip'], 'error' => ['danger', 'Left out']];
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="fw-bold mb-0 min-w-0"><i class="bi bi-search me-2 text-muted"></i>Import preview</h2>
    <span class="badge bg-secondary fs-6"><?= e($file) ?></span>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['Rows in file', count($plan['rows']), 'body'], ['Assets to create', $c['create'], 'success'], ['Assets to update', $c['update'], 'primary'],
        ['Skipped (tag exists)', $c['skip'], 'secondary'], ['Left out (problem)', $c['error'], 'danger'], ['Rows with a warning', $plan['warnings'], 'warning'],
    ] as [$t, $n, $tone]): ?>
    <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
        <div class="text-muted small"><?= $t ?></div><div class="fs-3 fw-bold text-<?= $tone ?>"><?= number_format($n) ?></div>
    </div></div></div>
    <?php endforeach; ?>
</div>

<?php if ($plan['new']['models'] || $plan['new']['suppliers']): ?>
<div class="alert alert-info">
    <strong>This import also adds to the catalog:</strong>
    <?php if ($plan['new']['models']): ?>
    <div class="mt-1"><?= count($plan['new']['models']) ?> model<?= count($plan['new']['models']) === 1 ? '' : 's' ?>:
        <?php foreach ($plan['new']['models'] as $m): ?><span class="badge text-bg-light border me-1 mb-1"><?= e($m['name']) ?> <span class="text-muted">— <?= e($m['category']) ?><?= $m['manufacturer'] !== '' ? ', ' . e($m['manufacturer']) : '' ?></span></span><?php endforeach; ?>
    </div>
    <div class="small text-muted">If one of these is a misspelling of a model you already have, fix the file (or go back and match the right column) so it is not created twice.</div>
    <?php endif; ?>
    <?php if ($plan['new']['suppliers']): ?>
    <div class="mt-1"><?= count($plan['new']['suppliers']) ?> supplier<?= count($plan['new']['suppliers']) === 1 ? '' : 's' ?>:
        <?php foreach ($plan['new']['suppliers'] as $s): ?><span class="badge text-bg-light border me-1 mb-1"><?= e($s) ?></span><?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($problems): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-exclamation-triangle text-warning me-1"></i>Rows to look at <span class="text-muted fw-normal small">(<?= number_format(count($problems)) ?>)</span></div>
    <div class="table-responsive" style="max-height:22rem;overflow:auto;">
        <table class="table table-sm align-middle mb-0" data-no-resize>
            <thead class="table-light"><tr><th style="width:5rem;">Line</th><th style="width:7rem;">Result</th><th>Why</th></tr></thead>
            <tbody>
                <?php foreach (array_slice($problems, 0, 200) as $r): ?>
                <tr>
                    <td class="text-muted"><?= (int) $r['line'] ?></td>
                    <td><span class="badge bg-<?= $r['action'] === 'error' ? 'danger' : 'warning text-dark' ?>"><?= $r['action'] === 'error' ? 'Left out' : 'Warning' ?></span></td>
                    <td class="small"><?= e($r['action'] === 'error' ? $r['why'] : implode('; ', $r['warn'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($problems) > 200): ?><div class="card-footer bg-white small text-muted">Showing the first 200.</div><?php endif; ?>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">First <?= min(25, count($plan['rows'])) ?> rows as they will be imported</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" data-no-resize>
            <thead class="table-light"><tr><th>Line</th><th>Tag</th><th>Name</th><th>Serial</th><th>Model</th><th>Status</th><th><?= e(label('location.singular')) ?></th><th>Purchased</th><th>Result</th></tr></thead>
            <tbody>
                <?php foreach (array_slice($plan['rows'], 0, 25) as $r): [$tone, $text] = $badge[$r['action']]; ?>
                <tr>
                    <td class="text-muted"><?= (int) $r['line'] ?></td>
                    <td class="font-monospace"><?= $r['tag'] !== '' ? e($r['tag']) : '<span class="text-muted fst-italic">automatic</span>' ?></td>
                    <td><?= e($r['show']['name']) ?></td>
                    <td class="font-monospace small"><?= e($r['show']['serial']) ?></td>
                    <td><?= e($r['show']['model']) ?><?php if (is_string($r['model'])): ?> <span class="badge text-bg-info">new</span><?php endif; ?></td>
                    <td><?= e($r['show']['status']) ?></td>
                    <td><?= e($r['show']['location']) ?></td>
                    <td class="text-nowrap small"><?= e($r['set']['purchase_date'] ?? '') ?></td>
                    <td><span class="badge bg-<?= $tone ?>" title="<?= e($r['why']) ?>"><?= $text ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex gap-2 flex-wrap">
    <?php if ($doing > 0): ?>
    <form method="POST" action="/inventory/import/confirm" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Importing…';">
        <?= csrfField() ?>
        <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
            <i class="bi bi-check-lg me-1"></i>Import — <?= $c['create'] ? number_format($c['create']) . ' new' : '' ?><?= $c['create'] && $c['update'] ? ', ' : '' ?><?= $c['update'] ? number_format($c['update']) . ' updated' : '' ?>
        </button>
    </form>
    <?php else: ?>
    <div class="alert alert-warning mb-0 py-2">Nothing in this file would be imported.</div>
    <?php endif; ?>
    <a href="/inventory/import/map" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to matching</a>
    <a href="/inventory/import" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Cancel</a>
</div>

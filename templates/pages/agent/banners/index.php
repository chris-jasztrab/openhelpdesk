<?php
$layout       = 'app';
$pageTitle    = 'Status Banners';
$sidebarItems = Auth::role() === 'power_user' ? powerUserSidebar('banners') : agentSidebar('banners');
$breadcrumbs  = [
    ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Status Banners'],
];

$severityBadge = [
    'info'     => ['class' => 'bg-info text-dark',     'label' => 'Info'],
    'warning'  => ['class' => 'bg-warning text-dark',  'label' => 'Warning'],
    'critical' => ['class' => 'bg-danger',             'label' => 'Critical'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-megaphone me-2"></i>Status Banners</h2>
        <p class="text-muted mb-0">
            Pin an incident notice to the top of every help page so staff and patrons stop filing duplicate tickets when the network's down or a system is slow.
        </p>
    </div>
    <a href="/agent/banners/create" class="btn text-white" style="background:var(--ld-primary);">
        <i class="bi bi-plus-circle me-1"></i>Post Incident
    </a>
</div>

<?php if (empty($banners)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-megaphone fs-1 d-block mb-2"></i>
        <p class="mb-1 fw-semibold">No banners posted.</p>
        <p class="mb-3">When something breaks at a branch, post a quick banner here so the next person to open the portal sees it before opening a ticket.</p>
        <a href="/agent/banners/create" class="btn btn-sm text-white" style="background:var(--ld-primary);">
            <i class="bi bi-plus-circle me-1"></i>Post your first banner
        </a>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:90px">Status</th>
                    <th style="width:110px">Severity</th>
                    <th>Title / Body</th>
                    <th style="width:140px">Branch</th>
                    <th style="width:160px">Window</th>
                    <th style="width:160px">Posted</th>
                    <th style="width:200px" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($banners as $b):
                $sev   = $severityBadge[$b['severity']] ?? $severityBadge['warning'];
                $title = $b['title'] ?: '(untitled)';
                // Trim HTML for the preview cell.
                $preview = trim(preg_replace('/\s+/', ' ', strip_tags((string) $b['body_html'])));
                if (mb_strlen($preview) > 140) {
                    $preview = mb_substr($preview, 0, 140) . '…';
                }
                $isActive = (int) $b['is_active'] === 1;
                $expired  = !empty($b['expires_at']) && strtotime($b['expires_at']) <= time();
                $pending  = !empty($b['starts_at'])  && strtotime($b['starts_at'])  > time();
            ?>
                <tr<?= $isActive ? '' : ' class="text-muted"' ?>>
                    <td>
                        <?php if (!$isActive): ?>
                            <span class="badge bg-secondary">Cleared</span>
                        <?php elseif ($expired): ?>
                            <span class="badge bg-secondary">Expired</span>
                        <?php elseif ($pending): ?>
                            <span class="badge bg-info text-dark">Scheduled</span>
                        <?php else: ?>
                            <span class="badge bg-success">Live</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $sev['class'] ?>"><?= $sev['label'] ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= e($title) ?></div>
                        <?php if ($preview !== ''): ?>
                            <div class="small text-muted"><?= e($preview) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?php if ($b['location_name']): ?>
                            <i class="bi bi-geo-alt me-1"></i><?= e($b['location_name']) ?>
                        <?php else: ?>
                            <span class="text-muted"><i class="bi bi-globe me-1"></i>All</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted">
                        <?php if (!$b['starts_at'] && !$b['expires_at']): ?>
                            Always
                        <?php else: ?>
                            <?= $b['starts_at']  ? e(date('M j, g:i A', strtotime($b['starts_at'])))  : 'Now' ?>
                            <i class="bi bi-arrow-right mx-1"></i>
                            <?= $b['expires_at'] ? e(date('M j, g:i A', strtotime($b['expires_at']))) : 'Until cleared' ?>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted">
                        <?= e($b['posted_by_name'] ?: '—') ?><br>
                        <?= e(date('M j, g:i A', strtotime($b['updated_at']))) ?>
                    </td>
                    <td class="text-end">
                        <a href="/agent/banners/<?= (int) $b['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if ($isActive): ?>
                            <form method="POST" action="/agent/banners/<?= (int) $b['id'] ?>/clear" class="d-inline"
                                  onsubmit="return confirm('Clear this banner? Portal users will no longer see it.');">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Clear (hide on portal)">
                                    <i class="bi bi-check2-circle"></i> Clear
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="/agent/banners/<?= (int) $b['id'] ?>/reactivate" class="d-inline">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Re-post">
                                    <i class="bi bi-arrow-clockwise"></i> Re-post
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="/agent/banners/<?= (int) $b['id'] ?>/delete" class="d-inline"
                              onsubmit="return confirm('Permanently delete this banner? This cannot be undone.');">
                            <?= csrfField() ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Delete permanently">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$layout       = 'app';
$pageTitle    = 'Audit Log';
$sidebarItems = adminSidebar('audit-log');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Audit Log'],
];

// Action badge colours
$actionColors = [
    'login'       => 'success',
    'logout'      => 'secondary',
    'user.create' => 'primary',
    'user.update' => 'warning',
    'user.delete' => 'danger',
];

function auditBadge(string $action, array $colors): string {
    $colour = $colors[$action] ?? 'info';
    return '<span class="badge bg-' . $colour . '">' . htmlspecialchars($action, ENT_QUOTES) . '</span>';
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Audit Log</h2>
    <span class="text-muted small"><?= number_format($total) ?> record<?= $total === 1 ? '' : 's' ?></span>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="/admin/audit-log" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Actor</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">All users</option>
                    <?php foreach ($actorList as $actor): ?>
                        <option value="<?= $actor['user_id'] ?>"
                            <?= $filterUser === (int) $actor['user_id'] ? 'selected' : '' ?>>
                            <?= e($actor['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Action</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">All actions</option>
                    <?php foreach ($actionList as $act): ?>
                        <option value="<?= e($act) ?>" <?= $filterAction === $act ? 'selected' : '' ?>>
                            <?= e($act) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm"
                       value="<?= e($filterFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm"
                       value="<?= e($filterTo) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <?php if ($filterUser || $filterAction !== '' || $filterFrom !== '' || $filterTo !== ''): ?>
                    <a href="/admin/audit-log" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i>Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Results table -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th style="width:170px">When</th>
                    <th style="width:180px">Actor</th>
                    <th style="width:140px">Action</th>
                    <th>Detail</th>
                    <th style="width:140px">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entries)): ?>
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">No audit entries found.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td class="text-muted text-nowrap">
                            <?= date('M j, Y', strtotime($entry['created_at'])) ?>
                            <span class="d-block text-muted" style="font-size:.75rem;">
                                <?= date('g:i:s A', strtotime($entry['created_at'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($entry['user_id']): ?>
                                <a href="/admin/users/<?= $entry['user_id'] ?>" class="text-decoration-none fw-semibold">
                                    <?= e($entry['actor_name']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted fst-italic">System</span>
                            <?php endif; ?>
                        </td>
                        <td><?= auditBadge($entry['action'], $actionColors) ?></td>
                        <td class="text-muted">
                            <?php if ($entry['target_type'] && $entry['target_id']): ?>
                                <span class="badge bg-light text-dark border me-1">
                                    <?= e($entry['target_type']) ?> #<?= $entry['target_id'] ?>
                                </span>
                            <?php endif; ?>
                            <?= e($entry['detail'] ?? '') ?>
                        </td>
                        <td class="text-muted text-nowrap"><?= e($entry['ip_address'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-footer bg-white py-2">
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php
                $qs = http_build_query(array_filter([
                    'user_id' => $filterUser ?: null,
                    'action'  => $filterAction ?: null,
                    'from'    => $filterFrom ?: null,
                    'to'      => $filterTo ?: null,
                ]));
                $qsSep = $qs ? '&' : '';
                ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="/admin/audit-log?<?= $qs . $qsSep ?>page=<?= $page - 1 ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="/admin/audit-log?<?= $qs . $qsSep ?>page=<?= $p ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="/admin/audit-log?<?= $qs . $qsSep ?>page=<?= $page + 1 ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

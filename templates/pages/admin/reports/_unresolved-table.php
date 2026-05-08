<?php
// Partial: filtered + paginated unresolved-ticket table.
// Required vars: $tickets, $page, $perPage, $totalPages, $totalFiltered,
//                $statusFilter, $ageFilter
$ageLabels = ['< 1 day', '1–3 days', '3–7 days', '7–14 days', '> 14 days'];
$rangeStart = $totalFiltered === 0 ? 0 : ($page - 1) * $perPage + 1;
$rangeEnd   = min($page * $perPage, $totalFiltered);
$hasFilters = $statusFilter !== '' || $ageFilter !== null;
?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-2"></i>Unresolved Tickets</h5>
            <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= number_format($totalFiltered) ?></span>

            <?php if ($statusFilter !== ''): ?>
            <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary d-inline-flex align-items-center gap-1">
                Status: <?= e(ucfirst(str_replace('_', ' ', $statusFilter))) ?>
                <button type="button" class="btn-close btn-close-sm" aria-label="Clear status filter"
                        data-drill="status" data-value="<?= e($statusFilter) ?>" style="font-size:.55rem;"></button>
            </span>
            <?php endif; ?>

            <?php if ($ageFilter !== null): ?>
            <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary d-inline-flex align-items-center gap-1">
                Age: <?= e($ageLabels[$ageFilter]) ?>
                <button type="button" class="btn-close btn-close-sm" aria-label="Clear age filter"
                        data-drill="age" data-value="<?= (int) $ageFilter ?>" style="font-size:.55rem;"></button>
            </span>
            <?php endif; ?>

            <?php if ($hasFilters): ?>
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-drill-clear-all>Clear all</button>
            <?php endif; ?>
        </div>

        <div class="d-flex align-items-center gap-2">
            <label for="unresolvedPerPage" class="text-muted small mb-0">Show</label>
            <select id="unresolvedPerPage" name="per_page" class="form-select form-select-sm" style="width:auto;">
                <?php foreach ([10, 25, 50, 100] as $opt): ?>
                <option value="<?= $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Agent</th>
                    <th>SLA</th>
                    <th>Age</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">No unresolved tickets match the current filter.</td></tr>
                <?php else: foreach ($tickets as $t): ?>
                <tr>
                    <td><a href="/admin/tickets/<?= (int) $t['id'] ?>" class="fw-semibold">#<?= (int) $t['id'] ?></a></td>
                    <td class="text-truncate" style="max-width:220px;"><?= e($t['subject']) ?></td>
                    <td><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e(ucfirst(str_replace('_', ' ', $t['status']))) ?></span></td>
                    <td>
                        <?php if ($t['priority_name']): ?>
                        <span class="badge" style="background:<?= e($t['priority_color']) ?>;"><?= e($t['priority_name']) ?></span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($t['agent_name'] ?? 'Unassigned') ?></td>
                    <td>
                        <?php if ($t['sla_state']):
                            $slaColor = match ($t['sla_state']) {
                                'on_track' => 'success',
                                'warning'  => 'warning',
                                'breached' => 'danger',
                                default    => 'secondary',
                            }; ?>
                            <span class="badge bg-<?= $slaColor ?> bg-opacity-10 text-<?= $slaColor ?>"><?= e(ucfirst(str_replace('_', ' ', $t['sla_state']))) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= e($t['age_display']) ?></td>
                    <td class="text-muted small"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalFiltered > 0): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="text-muted small">
            Showing <?= $rangeStart ?>–<?= $rangeEnd ?> of <?= number_format($totalFiltered) ?>
        </span>
        <?php if ($totalPages > 1): ?>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="#" data-page="<?= max(1, $page - 1) ?>" aria-label="Previous"><i class="bi bi-chevron-left"></i></a>
            </li>
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            if ($start > 1): ?>
                <li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>
                <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <?php endif; ?>
            <?php for ($p = $start; $p <= $end; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="#" data-page="<?= $p ?>"
                   <?= $p === $page ? 'style="background:var(--ld-primary);border-color:var(--ld-primary);"' : '' ?>><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item"><a class="page-link" href="#" data-page="<?= $totalPages ?>"><?= $totalPages ?></a></li>
            <?php endif; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="#" data-page="<?= min($totalPages, $page + 1) ?>" aria-label="Next"><i class="bi bi-chevron-right"></i></a>
            </li>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

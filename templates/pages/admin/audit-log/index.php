<?php
$layout       = 'app';
$pageTitle    = 'Audit Log';
$sidebarItems = adminSidebar('audit-log');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Audit Log'],
];

// Action badge colours — keyed by canonical (post-Phase-4) action names.
// Legacy rows are canonicalized in the route before render(), so this map
// covers both old and new entries without duplicate keys.
$actionColors = [
    'auth.login'                  => 'success',
    'auth.logout'                 => 'secondary',
    'auth.login_failed'           => 'danger',
    'auth.api_login'              => 'success',
    'auth.api_login_failed'       => 'danger',
    'auth.api_logout'             => 'secondary',
    'auth.password_changed'       => 'warning',
    'auth.2fa_enabled'            => 'success',
    'auth.2fa_disabled'           => 'warning',
    'user.create'                 => 'primary',
    'user.update'                 => 'warning',
    'user.delete'                 => 'danger',
    'user.profile_updated'        => 'warning',
    'user.2fa_reset_by_admin'     => 'warning',
    'api_token.created'           => 'primary',
    'api_token.rotated'           => 'warning',
    'sso.settings_changed'        => 'warning',
    'settings.default_group_changed' => 'warning',
    'ticket.bulk_closed'          => 'secondary',
    'ticket.bulk_assigned'        => 'warning',
    'ticket.bulk_merged'          => 'warning',
    'ticket.bulk_deleted'         => 'danger',
    'ticket.delete_all'           => 'danger',
    'ticket.confidential_viewed'  => 'info',
    'backup.created'              => 'primary',
    'backup.deleted'              => 'danger',
    'audit_log.pruned'            => 'danger',
    'ticket.created'              => 'success',
    'ticket.status_changed'       => 'warning',
    'ticket.priority_changed'     => 'warning',
    'ticket.assigned'             => 'primary',
    'ticket.group_changed'        => 'warning',
    'ticket.type_changed'         => 'warning',
    'ticket.merged'               => 'info',
    'ticket.escalated'            => 'danger',
    'group.managers_changed'      => 'warning',
    'ai.settings_changed'         => 'warning',
    'ai.classification_override'  => 'warning',
    'ai.backfill_run'             => 'primary',
    'manager.skill_assignments_changed' => 'warning',
    'manager.skill_created'       => 'primary',
    'manager.skill_updated'       => 'warning',
    'manager.skill_deleted'       => 'danger',
    'escalation_path.saved'       => 'primary',
];

function auditBadge(string $action, array $colors): string {
    $colour = $colors[$action] ?? 'info';
    return '<span class="badge bg-' . $colour . '">' . htmlspecialchars($action, ENT_QUOTES) . '</span>';
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Audit Log</h2>
    <div class="d-flex align-items-center gap-3">
        <span class="text-muted small"><?= number_format($total) ?> record<?= $total === 1 ? '' : 's' ?></span>
        <?php
            // Carry the active filters onto the export so the download matches
            // what's on screen. Empty values are dropped by array_filter.
            $exportParams = array_filter([
                'user_id' => $filterUser,
                'action'  => $filterAction,
                'from'    => $filterFrom,
                'to'      => $filterTo,
                'source'  => $filterSource,
            ], fn($v) => $v !== '' && $v !== null);
        ?>
        <a href="/admin/audit-log/export<?= $exportParams ? '?' . http_build_query($exportParams) : '' ?>"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i>Export to Excel
        </a>
        <button type="button"
                class="btn btn-sm btn-outline-danger"
                data-bs-toggle="collapse"
                data-bs-target="#auditPruneCard"
                aria-expanded="false"
                aria-controls="auditPruneCard">
            <i class="bi bi-eraser me-1"></i>Prune older entries
        </button>
    </div>
</div>

<!-- Prune card (hidden by default, expanded via the header button) -->
<div class="collapse mb-4" id="auditPruneCard">
    <div class="card border-danger shadow-sm">
        <div class="card-body p-3">
            <h5 class="card-title text-danger mb-2">
                <i class="bi bi-exclamation-triangle me-1"></i>Prune audit log
            </h5>
            <p class="text-muted small mb-3">
                Permanently delete <strong>system audit events</strong> (logins, user / group / setting
                changes, etc.) created <strong>before</strong> the date you pick. Events from the chosen
                day onward are kept, and the prune action itself is logged so the record of pruning
                survives in the audit trail.
            </p>
            <p class="text-muted small mb-3">
                <i class="bi bi-info-circle me-1"></i>Ticket-history events shown here (created, status
                changed, assigned, &hellip;) are <strong>not</strong> removed by pruning &mdash; they
                belong to each ticket and stay on the ticket's own timeline. They may continue to appear
                in this list after a prune.
            </p>
            <form method="POST" action="/admin/audit-log/prune" class="row g-2 align-items-end"
                  onsubmit="return confirm('Permanently delete all audit entries before ' + this.before_date.value + '?\n\nThis cannot be undone.');">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1" for="auditPruneCutoff">
                        Delete entries before
                    </label>
                    <input type="date" id="auditPruneCutoff" name="before_date" required
                           class="form-control form-control-sm"
                           <?php if (!empty($oldestEntry)): ?>min="<?= e(substr($oldestEntry, 0, 10)) ?>"<?php endif; ?>
                           max="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4 text-muted small">
                    <?php if (!empty($oldestEntry)): ?>
                        Oldest entry: <?= e(date('M j, Y', strtotime($oldestEntry))) ?>
                    <?php else: ?>
                        No entries to prune.
                    <?php endif; ?>
                </div>
                <div class="col-md-5 d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="collapse" data-bs-target="#auditPruneCard">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="bi bi-trash me-1"></i>Prune
                    </button>
                </div>
            </form>
        </div>
    </div>
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
                <label class="form-label small fw-semibold mb-1">Source</label>
                <select name="source" class="form-select form-select-sm">
                    <option value=""        <?= $filterSource === ''        ? 'selected' : '' ?>>All sources</option>
                    <option value="audit"   <?= $filterSource === 'audit'   ? 'selected' : '' ?>>Audit log only</option>
                    <option value="history" <?= $filterSource === 'history' ? 'selected' : '' ?>>Ticket history only</option>
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
            <div class="col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-funnel"></i>
                </button>
                <?php if ($filterUser || $filterAction !== '' || $filterFrom !== '' || $filterTo !== '' || $filterSource !== ''): ?>
                    <a href="/admin/audit-log" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
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
                    <th style="width:90px">Source</th>
                    <th style="width:180px">Actor</th>
                    <th style="width:160px">Action</th>
                    <th>Detail</th>
                    <th style="width:140px">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entries)): ?>
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">No audit entries found.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($entries as $entry):
                        $isTimeline   = ($entry['source'] ?? 'audit') === 'ticket_history';
                        $isTicketRef  = $entry['target_type'] === 'ticket' && $entry['target_id'];
                    ?>
                    <tr>
                        <td class="text-muted text-nowrap">
                            <?= date('M j, Y', strtotime($entry['created_at'])) ?>
                            <span class="d-block text-muted" style="font-size:.75rem;">
                                <?= date('g:i:s A', strtotime($entry['created_at'])) ?>
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($isTimeline): ?>
                                <span class="badge bg-info-subtle text-info border border-info-subtle" title="Sourced from ticket_timeline">
                                    <i class="bi bi-clock-history me-1"></i>Timeline
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border" title="Sourced from audit_log">
                                    <i class="bi bi-shield-check me-1"></i>Audit
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($entry['user_id']): ?>
                                <a href="/admin/audit-log?user_id=<?= $entry['user_id'] ?>"
                                   class="text-decoration-none fw-semibold"
                                   title="Filter the audit log to <?= e($entry['actor_name']) ?>">
                                    <?= e($entry['actor_name']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted fst-italic">System</span>
                            <?php endif; ?>
                        </td>
                        <td><?= auditBadge($entry['action'], $actionColors) ?></td>
                        <td class="text-muted">
                            <?php if ($isTicketRef): ?>
                                <a href="/admin/tickets/<?= (int) $entry['target_id'] ?>"
                                   class="badge bg-light text-dark border me-1 text-decoration-none"
                                   title="Open ticket #<?= (int) $entry['target_id'] ?>">
                                    ticket #<?= (int) $entry['target_id'] ?>
                                </a>
                            <?php elseif ($entry['target_type'] && $entry['target_id']): ?>
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
                    'source'  => $filterSource ?: null,
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

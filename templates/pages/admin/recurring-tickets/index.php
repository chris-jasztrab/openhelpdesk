<?php
$layout       = 'app';
$pageTitle    = 'Recurring Tickets';
$sidebarItems = adminSidebar('recurring-tickets');
$breadcrumbs  = [
    ['label' => 'Admin',   'url' => '/admin'],
    ['label' => 'Recurring Tickets'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Recurring Tickets</h2>
        <div class="text-muted small">
            Schedule preventive-maintenance work — monthly toner audits, quarterly HVAC, annual fire inspection — and let LocalDesk file the ticket on cadence.
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/recurring-tickets/create" class="btn btn-sm text-white" style="background:var(--ld-primary);">
            <i class="bi bi-plus-lg me-1"></i>New Recurring Ticket
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Cadence</th>
                    <th>Type</th>
                    <th>Group / Assignee</th>
                    <th>Next Run</th>
                    <th>Last Run</th>
                    <th>Runs</th>
                    <th class="text-center">Active</th>
                    <th style="width:160px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($schedules)): ?>
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <i class="bi bi-arrow-clockwise" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">
                            No recurring schedules yet.
                            <a href="/admin/recurring-tickets/create">Create one</a> to automate preventive-maintenance work.
                        </p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($schedules as $s): ?>
                    <?php
                    $cadence = RecurringTickets::describeCadence($s);
                    $isOverdue = $s['is_active'] && strtotime((string) $s['next_run_at']) < time();
                    ?>
                    <tr<?= $s['is_active'] ? '' : ' class="text-muted"' ?>>
                        <td class="fw-semibold">
                            <i class="bi bi-arrow-clockwise text-muted me-1"></i><?= e($s['name']) ?>
                            <?php if (!empty($s['description_internal'])): ?>
                                <div class="text-muted small"><?= e(mb_strimwidth($s['description_internal'], 0, 80, '…')) ?></div>
                            <?php endif; ?>
                            <div class="text-muted small">Subject: <span class="fst-italic"><?= e(mb_strimwidth($s['subject'], 0, 50, '…')) ?></span></div>
                        </td>
                        <td class="small">
                            <span class="badge rounded-pill" style="background:rgba(99,102,241,.12);color:var(--ld-primary);">
                                <i class="bi bi-calendar-event me-1"></i><?= e($cadence) ?>
                            </span>
                        </td>
                        <td class="small"><?= e($s['type_name'] ?? '—') ?></td>
                        <td class="small">
                            <?= e($s['group_name'] ?? '—') ?>
                            <?php if (!empty($s['assignee_name']) && trim($s['assignee_name']) !== ''): ?>
                                <div class="text-muted">→ <?= e($s['assignee_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if ($s['is_active']): ?>
                                <span class="<?= $isOverdue ? 'text-danger fw-semibold' : '' ?>">
                                    <?= e(date('M j, Y', strtotime((string) $s['next_run_at']))) ?>
                                    <?php if ($isOverdue): ?><i class="bi bi-exclamation-triangle-fill ms-1" title="Overdue — will fire on next cron run"></i><?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted fst-italic">paused</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?php if (!empty($s['last_run_at'])): ?>
                                <?= e(date('M j, Y', strtotime((string) $s['last_run_at']))) ?>
                                <?php if (!empty($s['last_ticket_id'])): ?>
                                    <div><a href="/admin/tickets/<?= (int) $s['last_ticket_id'] ?>" class="small">#<?= (int) $s['last_ticket_id'] ?></a></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="fst-italic">never</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-center"><?= (int) $s['run_count'] ?></td>
                        <td class="text-center">
                            <form method="POST" action="/admin/recurring-tickets/<?= (int) $s['id'] ?>/toggle" class="d-inline">
                                <?= csrfField() ?>
                                <button type="submit"
                                        class="btn btn-sm <?= $s['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>"
                                        title="<?= $s['is_active'] ? 'Pause schedule' : 'Resume schedule' ?>"
                                        style="min-width:64px;">
                                    <i class="bi <?= $s['is_active'] ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                    <?= $s['is_active'] ? 'On' : 'Off' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary" title="Run now"
                                        data-bs-toggle="modal" data-bs-target="#runNowModal"
                                        data-id="<?= (int) $s['id'] ?>"
                                        data-name="<?= e($s['name']) ?>">
                                    <i class="bi bi-lightning-charge"></i>
                                </button>
                                <a href="/admin/recurring-tickets/<?= (int) $s['id'] ?>/edit"
                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#deleteRecurringModal"
                                        data-id="<?= (int) $s['id'] ?>"
                                        data-name="<?= e($s['name']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Run Now Modal -->
<div class="modal fade" id="runNowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-lightning-charge me-2 text-primary"></i>Run Schedule Now</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Create a ticket from <strong id="runNowName"></strong> right now?</p>
                <p class="text-muted small mb-0">
                    The schedule's normal next run isn't affected — this is a manual one-off mint.
                </p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="runNowForm" action="">
                    <?= csrfField() ?>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white px-4" style="background:var(--ld-primary);">
                        <i class="bi bi-lightning-charge me-1"></i>Create Ticket
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteRecurringModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-trash me-2 text-danger"></i>Delete Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete schedule <strong id="deleteRecurringName"></strong>? Already-fired tickets stay; the schedule itself is removed.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" id="deleteRecurringForm" action="">
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
document.getElementById('runNowModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('runNowName').textContent = btn.dataset.name;
    document.getElementById('runNowForm').action = '/admin/recurring-tickets/' + btn.dataset.id + '/run-now';
});
document.getElementById('deleteRecurringModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteRecurringName').textContent = btn.dataset.name;
    document.getElementById('deleteRecurringForm').action = '/admin/recurring-tickets/' + btn.dataset.id + '/delete';
});
</script>

<?php
$layout       = 'app';
$pageTitle    = 'Tickets';
$sidebarItems = agentSidebar('tickets');
$breadcrumbs  = [
    ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Tickets'],
];
$statusColors = ['open' => 'primary', 'in_progress' => 'warning', 'pending' => 'info', 'resolved' => 'success', 'closed' => 'secondary'];
$statusLabels = ['open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$slaStateColors = ['on_track' => 'success', 'warning' => 'warning', 'breached' => 'danger'];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Tickets</h2>
    <span class="badge bg-secondary fs-6"><?= count($tickets) ?> total</span>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">#</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Type</th>
                    <th>Assigned To</th>
                    <th>Created By</th>
                    <th>Location</th>
                    <th>SLA</th>
                    <th>Created</th>
                    <th>Due</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                <tr><td colspan="11" class="text-center py-4 text-muted">No tickets found.</td></tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                    <?php $isAssignedToMe = ($t['assigned_to'] == Auth::id()); ?>
                    <tr style="cursor:pointer;<?= $isAssignedToMe ? 'background:#eef2ff;' : '' ?>"
                        onclick="window.location='/agent/tickets/<?= $t['id'] ?>'">
                        <td class="text-muted fw-bold"><?= $t['id'] ?></td>
                        <td>
                            <a href="/agent/tickets/<?= $t['id'] ?>" class="text-decoration-none fw-semibold text-dark">
                                <?= e($t['subject']) ?>
                            </a>
                            <?php if ($isAssignedToMe): ?>
                            <span class="badge bg-primary bg-opacity-10 text-primary ms-1">Mine</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $statusColors[$t['status']] ?? 'secondary' ?>">
                                <?= e($statusLabels[$t['status']] ?? $t['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($t['priority_name']): ?>
                            <span class="badge" style="background:<?= e($t['priority_color']) ?>;">
                                <?= e($t['priority_name']) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= e($t['type_name'] ?? '—') ?></td>
                        <td><?= e($t['agent_name'] ?: '— Unassigned —') ?></td>
                        <td class="text-muted"><?= e($t['creator_name'] ?? '—') ?></td>
                        <td class="text-muted"><?= e($t['location_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($t['sla_state']): ?>
                            <span class="badge bg-<?= $slaStateColors[$t['sla_state']] ?? 'secondary' ?>" title="<?= e(ucfirst(str_replace('_', ' ', $t['sla_state']))) ?>">
                                <i class="bi bi-stopwatch"></i>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                        <td class="text-muted small">
                            <?php if ($t['due_date']): ?>
                                <?php
                                $due = strtotime($t['due_date']);
                                $overdue = $due < time() && !in_array($t['status'], ['resolved', 'closed']);
                                ?>
                                <span class="<?= $overdue ? 'text-danger fw-bold' : '' ?>">
                                    <?= date('M j, Y', $due) ?>
                                </span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$layout       = 'app';
$pageTitle    = $profileUser['first_name'] . ' ' . $profileUser['last_name'];
$sidebarItems = adminSidebar('users');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Users',  'url' => '/admin/users'],
    ['label' => $profileUser['first_name'] . ' ' . $profileUser['last_name']],
];

$badgeColors = ['admin' => 'danger', 'agent' => 'primary', 'user' => 'secondary'];
$bc = $badgeColors[$profileUser['role']] ?? 'secondary';

$statusLabels = [
    'open'                   => ['Open',                'primary'],
    'in_progress'            => ['In Progress',         'warning'],
    'pending'                => ['Pending',             'secondary'],
    'waiting_on_customer'    => ['Waiting on Customer', 'info'],
    'waiting_on_third_party' => ['Waiting on 3rd Party','info'],
];
?>

<!-- User info card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div class="d-flex align-items-center gap-4">
                <!-- Avatar -->
                <?php if ($profileUser['avatar'] ?? null): ?>
                    <img src="/uploads/avatars/<?= e($profileUser['avatar']) ?>"
                         class="rounded-circle" width="72" height="72" style="object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold"
                         style="width:72px;height:72px;font-size:1.5rem;">
                        <?= strtoupper(mb_substr($profileUser['first_name'], 0, 1) . mb_substr($profileUser['last_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <!-- Details -->
                <div>
                    <h3 class="fw-bold mb-1">
                        <?= e($profileUser['first_name'] . ' ' . $profileUser['last_name']) ?>
                        <span class="badge bg-<?= $bc ?> ms-2 align-middle" style="font-size:.65rem;">
                            <?= e(ucfirst($profileUser['role'])) ?>
                        </span>
                    </h3>
                    <div class="text-muted small mb-1">
                        <i class="bi bi-envelope me-1"></i><?= e($profileUser['email']) ?>
                    </div>
                    <?php if ($profileUser['work_phone'] ?? null): ?>
                    <div class="text-muted small mb-1">
                        <i class="bi bi-telephone me-1"></i><?= e($profileUser['work_phone']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap gap-3 mt-2 small text-muted">
                        <?php if ($profileUser['location_name'] ?? null): ?>
                        <span><i class="bi bi-geo-alt me-1"></i><?= e($profileUser['location_name']) ?></span>
                        <?php endif; ?>
                        <?php if ($profileUser['created_at'] ?? null): ?>
                        <span><i class="bi bi-calendar3 me-1"></i>Member since <?= date('M j, Y', strtotime($profileUser['created_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-flex gap-2">
                <a href="/admin/users/<?= (int)$profileUser['id'] ?>/edit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-pencil me-1"></i>Edit User
                </a>
                <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Open tickets -->
<h5 class="fw-semibold mb-3">
    Open Tickets
    <span class="badge bg-secondary ms-1"><?= count($openTickets) ?></span>
</h5>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:70px">#</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Type</th>
                    <th>Assigned To</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($openTickets)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-check-circle" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">No open tickets.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($openTickets as $t):
                        [$statusLabel, $statusColor] = $statusLabels[$t['status']] ?? [ucfirst(str_replace('_', ' ', $t['status'])), 'secondary'];
                    ?>
                    <tr style="cursor:pointer;" onclick="window.location='/admin/tickets/<?= (int)$t['id'] ?>'">
                        <td class="text-muted small fw-semibold">#<?= (int)$t['id'] ?></td>
                        <td class="fw-semibold"><?= e($t['subject']) ?></td>
                        <td>
                            <span class="badge bg-<?= $statusColor ?>"><?= $statusLabel ?></span>
                        </td>
                        <td>
                            <?php if ($t['priority_name'] ?? null): ?>
                                <span class="badge" style="background:<?= e($t['priority_color'] ?: '#6c757d') ?>;">
                                    <?= e($t['priority_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= e($t['type_name'] ?? '—') ?></td>
                        <td class="text-muted small"><?= e($t['assigned_name'] ?: 'Unassigned') ?></td>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

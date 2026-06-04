<?php
/**
 * Open tickets for one user — reached from the inbox-view person card.
 *
 * Lists the target user's open tickets (ones they created) plus any open
 * tickets where they were @mentioned, with the @mentioned ones badged so it's
 * obvious they aren't the requester. Staff-only; the route guard and the
 * visibility predicate in userOpenTicketsForStaff() handle access.
 *
 * Expects: $targetUser, $targetUserId, $tickets, $base,
 *          $confidentialTypeIds, $adminGroupIds
 */
$isAdmin     = ($base === '/admin/tickets');
$layout      = 'app';
$fullName    = trim(($targetUser['first_name'] ?? '') . ' ' . ($targetUser['last_name'] ?? '')) ?: 'User';
$pageTitle   = 'Open tickets — ' . $fullName;
$sidebarItems = $isAdmin ? adminSidebar('tickets') : agentSidebar('tickets');
$breadcrumbs = [
    ['label' => $isAdmin ? 'Admin' : 'Agent', 'url' => $isAdmin ? '/admin' : '/agent'],
    ['label' => 'Tickets', 'url' => $base],
    ['label' => $fullName],
];

$mentionCount = 0;
foreach ($tickets as $t) {
    if ((int) $t['created_by'] !== (int) $targetUserId) { $mentionCount++; }
}
?>
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h2 class="fw-bold mb-1"><?= e($fullName) ?></h2>
        <?php if (!empty($targetUser['email'])): ?>
        <a href="mailto:<?= e($targetUser['email']) ?>" class="text-decoration-none text-muted">
            <i class="bi bi-envelope me-1"></i><?= e($targetUser['email']) ?>
        </a>
        <?php endif; ?>
    </div>
    <a href="<?= $base ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>All tickets
    </a>
</div>

<p class="text-muted small mb-3">
    <?= count($tickets) ?> open <?= count($tickets) === 1 ? 'ticket' : 'tickets' ?>
    <?php if ($mentionCount > 0): ?>
        &mdash; including <?= $mentionCount ?> where this user was
        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="bi bi-at"></i> mentioned</span>
    <?php endif; ?>
</p>

<div class="card border-0 shadow-sm">
    <div style="overflow-x:auto;">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:72px;">#</th>
                    <th>Subject</th>
                    <th style="white-space:nowrap;">Status</th>
                    <th style="white-space:nowrap;">Assigned To</th>
                    <th style="white-space:nowrap;">Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tickets)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No open tickets for this user.</td></tr>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                    <?php
                        $isMention  = ((int) $t['created_by'] !== (int) $targetUserId);
                        $isRedacted = isTicketRedactedForUser($t, $confidentialTypeIds ?? [], $adminGroupIds ?? []);
                    ?>
                    <tr style="cursor:pointer;<?= $isRedacted ? 'opacity:0.75;' : '' ?>"
                        onclick="window.location='<?= $base ?>/<?= (int) $t['id'] ?>'">
                        <td class="text-muted fw-bold"><?= (int) $t['id'] ?></td>
                        <td style="overflow:hidden;text-overflow:ellipsis;">
                            <?php if ($isRedacted): ?>
                                <span class="text-muted fst-italic"><i class="bi bi-shield-lock me-1"></i>[Confidential]</span>
                            <?php else: ?>
                                <a href="<?= $base ?>/<?= (int) $t['id'] ?>" class="text-decoration-none fw-semibold text-dark"><?= e($t['subject']) ?></a>
                                <?php if ($isMention): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1" title="This user was @mentioned on this ticket — they are not the requester">
                                    <i class="bi bi-at"></i> Mentioned
                                </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;"><?= ticketStatusBadgeHtml($t['status']) ?></td>
                        <td class="text-muted" style="white-space:nowrap;"><?= e($t['agent_name'] ?: 'Unassigned') ?></td>
                        <td class="text-muted small" style="white-space:nowrap;"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

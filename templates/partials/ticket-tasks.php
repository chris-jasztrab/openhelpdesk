<?php
/**
 * Task tickets on the ticket page (agent + admin views): the ticket this one is
 * a task for, and the task tickets this one spawned. Expects $taskLinks from
 * ticketTaskLinks().
 */
$taskLinks  = $taskLinks ?? ['parent_id' => null, 'tasks' => []];
$ticketBase = (Auth::isStaff() && !Auth::isAdmin()) ? '/agent' : '/admin';
?>
<?php if ($taskLinks['parent_id']): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-4 small" role="note">
    <i class="bi bi-diagram-3" aria-hidden="true"></i>
    <div>This is a task ticket for <a href="<?= $ticketBase ?>/tickets/<?= (int) $taskLinks['parent_id'] ?>">Ticket #<?= (int) $taskLinks['parent_id'] ?></a>.</div>
</div>
<?php endif; ?>
<?php if ($taskLinks['tasks']):
    $openSlugs = ticketOpenBucketSlugs();
    $doneCount = count(array_filter($taskLinks['tasks'], static fn(array $t) => !in_array($t['status'], $openSlugs, true)));
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center gap-2">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-diagram-3 me-2"></i>Task Tickets</h5>
        <span class="badge text-bg-light"><?= $doneCount ?> of <?= count($taskLinks['tasks']) ?> done</span>
    </div>
    <ul class="list-group list-group-flush small">
        <?php foreach ($taskLinks['tasks'] as $task): ?>
        <li class="list-group-item">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <a href="<?= $ticketBase ?>/tickets/<?= (int) $task['id'] ?>" class="fw-medium text-break min-w-0">#<?= (int) $task['id'] ?> <?= e($task['subject']) ?></a>
                <?= ticketStatusBadgeHtml($task['status']) ?>
            </div>
            <div class="text-muted"><?= e($task['group_name'] ?? 'No group') ?> · <?= e($task['agent_name'] ?? 'Unassigned') ?></div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php
$layout       = 'app';
$pageTitle    = 'Split Ticket #' . $ticket['id'];
$sidebarItems = agentSidebar('tickets');
$breadcrumbs  = [
    ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Tickets', 'url' => '/agent/tickets'],
    ['label' => '#' . $ticket['id'], 'url' => '/agent/tickets/' . $ticket['id']],
    ['label' => 'Split'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-scissors me-2 text-warning"></i>Split Ticket #<?= (int) $ticket['id'] ?></h2>
        <p class="text-muted mb-0 small">Select comments to move to a new ticket, then fill in the new ticket's details.</p>
    </div>
    <a href="/agent/tickets/<?= (int) $ticket['id'] ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Cancel
    </a>
</div>

<form method="POST" action="/agent/tickets/<?= (int) $ticket['id'] ?>/split">
    <?= csrfField() ?>

    <div class="row g-4">

        <!-- Left column: original ticket + comments to move -->
        <div class="col-lg-6">

            <!-- Original ticket info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-ticket-detailed me-1"></i>Original Ticket
                </div>
                <div class="card-body">
                    <p class="fw-semibold mb-1"><?= e($ticket['subject']) ?></p>
                    <div class="text-muted small mb-2">
                        Submitted by <?= e($ticket['creator_name']) ?>
                        <?php if ($ticket['location_name']): ?>
                        &middot; <?= e($ticket['location_name']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="border rounded p-2 bg-light small" style="max-height:120px;overflow-y:auto;white-space:pre-wrap;"><?= e($ticket['description']) ?></div>
                </div>
            </div>

            <!-- Comments to optionally move -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="bi bi-chat-dots me-1"></i>Comments to Move</span>
                    <?php if ($comments): ?>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllBtn">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllBtn">Clear</button>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (!$comments): ?>
                    <p class="text-muted small p-3 mb-0">This ticket has no public comments to move.</p>
                    <?php else: ?>
                    <div class="list-group list-group-flush" id="commentList">
                        <?php foreach ($comments as $c): ?>
                        <label class="list-group-item list-group-item-action d-flex gap-3 py-3 cursor-pointer comment-row">
                            <input class="form-check-input flex-shrink-0 mt-1 comment-check"
                                   type="checkbox" name="move_comments[]" value="<?= (int) $c['id'] ?>">
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold small"><?= e($c['user_name']) ?></span>
                                    <span class="text-muted small"><?= e(date('M j, Y g:i a', strtotime($c['created_at']))) ?></span>
                                </div>
                                <div class="text-muted small" style="overflow:hidden;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;"><?= e($c['details']) ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right column: new ticket form -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-plus-circle me-1 text-success"></i>New Ticket Details
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="subject" class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="subject" name="subject"
                               value="<?= e($_POST['subject'] ?? '') ?>"
                               placeholder="Brief summary of the new issue" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="description" name="description"
                                  rows="5"
                                  placeholder="Describe the issue in detail..."><?= e($_POST['description'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="type_id" class="form-label fw-semibold">Type</label>
                            <select class="form-select" id="type_id" name="type_id">
                                <option value="">— Unclassified —</option>
                                <?php foreach ($types as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"
                                        <?= ($ticket['type_id'] == $t['id']) ? 'selected' : '' ?>>
                                    <?= e($t['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="priority_id" class="form-label fw-semibold">Priority</label>
                            <select class="form-select" id="priority_id" name="priority_id">
                                <option value="">— None —</option>
                                <?php foreach ($priorities as $pri): ?>
                                <option value="<?= (int) $pri['id'] ?>"
                                        <?= ($ticket['priority_id'] == $pri['id']) ? 'selected' : '' ?>>
                                    <?= e($pri['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="assigned_to" class="form-label fw-semibold">Assign To</label>
                            <select class="form-select" id="assigned_to" name="assigned_to">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($agents as $ag): ?>
                                <option value="<?= (int) $ag['id'] ?>">
                                    <?= e($ag['first_name'] . ' ' . $ag['last_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="group_id" class="form-label fw-semibold">Group</label>
                            <select class="form-select" id="group_id" name="group_id">
                                <option value="">— None —</option>
                                <?php foreach ($groups as $grp): ?>
                                <option value="<?= (int) $grp['id'] ?>">
                                    <?= e($grp['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info small py-2">
                <i class="bi bi-info-circle me-1"></i>
                The new ticket will inherit the <strong><?= e($ticket['location_name'] ?? 'same') ?></strong> location.
                Its status will be set to <strong>Open</strong>.
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white px-4" style="background:var(--ld-primary);">
                    <i class="bi bi-scissors me-1"></i>Split &amp; Create New Ticket
                </button>
                <a href="/agent/tickets/<?= (int) $ticket['id'] ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>

    </div>
</form>

<script>
document.getElementById('selectAllBtn')?.addEventListener('click', function () {
    document.querySelectorAll('.comment-check').forEach(cb => cb.checked = true);
    document.querySelectorAll('.comment-row').forEach(r => r.classList.add('active'));
});
document.getElementById('clearAllBtn')?.addEventListener('click', function () {
    document.querySelectorAll('.comment-check').forEach(cb => cb.checked = false);
    document.querySelectorAll('.comment-row').forEach(r => r.classList.remove('active'));
});
document.querySelectorAll('.comment-row').forEach(row => {
    row.addEventListener('change', function () {
        this.classList.toggle('active', this.querySelector('.comment-check').checked);
    });
});
</script>

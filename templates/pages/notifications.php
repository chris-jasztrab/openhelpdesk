<?php
$layout       = 'app';
$pageTitle    = 'Notifications';
$sidebarItems = $sidebarFn();
$breadcrumbs  = [
    ['label' => 'Notifications'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Notifications</h2>
    <?php if (!empty($notifications)): ?>
    <form method="POST" action="/notifications/read-all" class="d-inline">
        <?= csrfField() ?>
        <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-check2-all me-1"></i>Mark All Read
        </button>
    </form>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <?php if (empty($notifications)): ?>
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-bell fs-1 d-block mb-2"></i>
        <p class="mb-0">No notifications.</p>
    </div>
    <?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($notifications as $n): ?>
        <?php
            $type = $n['type'] ?? 'mention';
            $meta = notificationMeta($type);
            // The actor is only present for human-driven types (mention/comment).
            $actor = trim((string) ($n['mentioned_by_name'] ?? ''));
            $hasActor = $actor !== '' && $actor !== ' ';
        ?>
        <div class="list-group-item px-4 py-3 <?= $n['is_read'] ? '' : 'bg-light' ?>">
            <div class="d-flex gap-3 align-items-start">
                <div class="pt-1">
                    <i class="bi <?= e($meta['icon']) ?> fs-4 <?= $n['is_read'] ? 'text-muted' : e($meta['color']) ?>"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <?php if ($hasActor): ?>
                            <span class="fw-semibold"><?= e($actor) ?></span>
                            <?php endif; ?>
                            <span class="text-muted"><?= e($meta['label']) ?></span>
                            <a href="<?= e($areaPrefix) ?>/tickets/<?= $n['ticket_id'] ?>" class="text-decoration-none fw-semibold">
                                <?= e($n['ticket_subject']) ?>
                            </a>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <small class="text-muted text-nowrap"><?= date('M j, g:i A', strtotime($n['created_at'])) ?></small>
                            <?php if (!$n['is_read']): ?>
                            <form method="POST" action="/notifications/<?= $n['id'] ?>/read" class="d-inline">
                                <?= csrfField() ?>
                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Mark read">
                                    <i class="bi bi-check2"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php $excerpt = trim(html_entity_decode(strip_tags((string) ($n['message'] ?? '')), ENT_QUOTES, 'UTF-8')); ?>
                    <?php if ($excerpt !== ''): ?>
                    <div class="mt-1 text-muted small" style="white-space:pre-wrap;"><?= e($excerpt) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

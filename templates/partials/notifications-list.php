<?php
/**
 * Notifications feed list — the inner content of the notifications card.
 *
 * Shared by the full page (templates/pages/notifications.php) and the AJAX
 * polling endpoint (GET /notifications/feed), so the markup has a single
 * source of truth. Expects:
 *   $notifications — array of feed rows (see the /notifications query)
 *   $areaPrefix    — '/admin' | '/agent' | '/portal' for ticket links
 */
?>
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
    <div class="list-group-item px-4 py-3 <?= $n['is_read'] ? '' : 'bg-light' ?>" data-notif-id="<?= (int) $n['id'] ?>">
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
                        <form method="POST" action="/notifications/<?= $n['id'] ?>/read" class="d-inline js-notif-read">
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

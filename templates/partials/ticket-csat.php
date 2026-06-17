<?php
/**
 * Ticket CSAT panel — shared by the agent and admin ticket views.
 *
 * Expects:
 *   $csat — the csat_surveys row for this ticket, or null/empty if none sent.
 *
 * Shows the rating + comment once a response lands (built-in survey or external
 * webhook), or an "awaiting response" state while it's outstanding. The stored
 * survey_url gives one-click access to the exact link sent to the requester —
 * the public page for built-in surveys, the third-party survey for external.
 */
if (empty($csat)) {
    return;
}

$csatResponded = !empty($csat['responded_at']);
$csatRating    = (int) ($csat['rating'] ?? 0);
$csatExternal  = getSetting('csat_mode', 'internal') === 'external';
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-star me-2 text-warning"></i>Satisfaction Survey</h5>
    </div>
    <div class="card-body p-3 small">
        <?php if ($csatResponded && $csatRating > 0): ?>
            <div class="d-flex align-items-center mb-1">
                <span class="me-2" style="font-size:1.05rem; letter-spacing:1px;">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi <?= $i <= $csatRating ? 'bi-star-fill text-warning' : 'bi-star text-muted' ?>"></i>
                    <?php endfor; ?>
                </span>
                <span class="fw-semibold"><?= $csatRating ?>/5</span>
            </div>
            <?php if (!empty($csat['comment'])): ?>
                <div class="mt-2 p-2 bg-light rounded" style="white-space:pre-wrap;"><?= e($csat['comment']) ?></div>
            <?php endif; ?>
            <div class="text-muted mt-2" style="font-size:.8rem;">
                Responded <?= date('M j, Y g:i A', strtotime($csat['responded_at'])) ?>
            </div>
        <?php else: ?>
            <div class="text-muted">
                <i class="bi bi-hourglass-split me-1"></i>
                Survey sent <?= date('M j, Y', strtotime($csat['sent_at'])) ?> &mdash; awaiting response.
                <?php if ($csatExternal): ?>
                    <div class="mt-1">Responses are recorded here only if the external tool's webhook is configured.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($csat['reopened_at'])): ?>
            <div class="mt-2">
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reopened by requester
                </span>
            </div>
        <?php endif; ?>

        <?php if (!empty($csat['survey_url'])): ?>
            <div class="mt-2">
                <a href="<?= e($csat['survey_url']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                    <i class="bi bi-box-arrow-up-right me-1"></i><?= $csatExternal ? 'Open survey in external tool' : 'Open survey page' ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($msg = getFlash('success')): ?>
<div class="alert alert-success alert-dismissible fade show" role="status" aria-live="polite" aria-atomic="true">
    <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i><?= e($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
</div>
<?php endif; ?>
<?php if ($msg = getFlash('info')): ?>
<div class="alert alert-info alert-dismissible fade show" role="status" aria-live="polite" aria-atomic="true">
    <i class="bi bi-info-circle-fill me-2" aria-hidden="true"></i><?= e($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
</div>
<?php endif; ?>
<?php if ($msg = getFlash('error')): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert" aria-live="assertive" aria-atomic="true">
    <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i><?= e($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss"></button>
</div>
<?php endif; ?>
<?php
// SLA commitment modal — queued by flashSlaNotice() right after a ticket is
// created with a priority that has SLA targets. Shows the real due dates.
$slaNotice = ($raw = getFlash('sla_notice')) ? (json_decode($raw, true) ?: []) : [];
if ($slaNotice): ?>
<div class="modal fade" id="slaNoticeModal" tabindex="-1" aria-labelledby="slaNoticeTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="slaNoticeTitle">
                    <i class="bi bi-stopwatch me-2" aria-hidden="true"></i><?= e(label('sla.notice_title', 'What happens next')) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3"><?= e(label('sla.notice_intro', 'Based on the priority of this request, here is when you can expect to hear from us:')) ?></p>
                <dl class="row mb-2">
                    <?php if (!empty($slaNotice['response'])): ?>
                    <dt class="col-sm-5"><?= e(label('sla.notice_response', 'First response by')) ?></dt>
                    <dd class="col-sm-7 fw-semibold"><?= e($slaNotice['response']) ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($slaNotice['resolution'])): ?>
                    <dt class="col-sm-5"><?= e(label('sla.notice_resolution', 'Resolution by')) ?></dt>
                    <dd class="col-sm-7 fw-semibold"><?= e($slaNotice['resolution']) ?></dd>
                    <?php endif; ?>
                </dl>
                <p class="text-muted small mb-0"><?= e(label('sla.notice_footer', 'These targets count business hours only.')) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.bootstrap) new bootstrap.Modal(document.getElementById('slaNoticeModal')).show();
});
</script>
<?php endif; ?>

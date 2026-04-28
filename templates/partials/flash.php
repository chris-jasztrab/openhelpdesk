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

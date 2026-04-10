<?php
$layout       = 'app';
$pageTitle    = 'Confirm Confidential Change';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Confirm Change'],
];
?>

<div class="row justify-content-center mt-5">
    <div class="col-lg-5 col-md-7">
        <div class="card border-danger shadow-sm">
            <div class="card-header bg-danger bg-opacity-10 text-center py-3">
                <i class="bi bi-shield-exclamation text-danger" style="font-size:2.5rem;"></i>
                <h4 class="fw-bold mt-2 mb-0">Confirm Security Change</h4>
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-3">
                    You are about to <strong class="text-danger"><?= $action === 'delete' ? 'delete' : 'remove the confidential flag from' ?></strong>
                    the <?= e($targetType) ?> <strong>"<?= e($targetName) ?>"</strong>.
                </p>

                <div class="alert alert-danger d-flex align-items-start gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                    <div>
                        <strong>Security Notice:</strong>
                        <?php if ($action === 'delete'): ?>
                            Deleting this confidential <?= e($targetType) ?> will permanently remove it and all associated security protections.
                        <?php else: ?>
                            Removing confidential status will disable re-authentication gates, access alerts, ticket redaction, and membership-change notifications for this <?= e($targetType) ?>.
                        <?php endif; ?>
                        <br><br>
                        All members of the associated group will be notified via email. This action and your identity (name, email, IP address) will be recorded in the audit log.
                    </div>
                </div>

                <p class="fw-semibold mb-3">Please re-enter your password to continue:</p>

                <form method="POST" action="<?= e($formAction) ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="_confidential_reauth" value="1">
                    <?php foreach ($hiddenFields as $fname => $fval): ?>
                    <input type="hidden" name="<?= e($fname) ?>" value="<?= e($fval) ?>">
                    <?php endforeach; ?>
                    <?php if (!empty($hiddenArrayFields)): ?>
                        <?php foreach ($hiddenArrayFields as $fname => $values): ?>
                            <?php foreach ($values as $v): ?>
                            <input type="hidden" name="<?= e($fname) ?>[]" value="<?= e($v) ?>">
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="reauth_password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="reauth_password" name="reauth_password"
                               required autofocus placeholder="Enter your password">
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger fw-semibold flex-fill">
                            <i class="bi bi-unlock me-1"></i>Authenticate &amp; Confirm
                        </button>
                        <a href="<?= e($cancelUrl) ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * App Secret Expiry Reminder Modal
 *
 * Shown once per login session per threshold when the Microsoft Graph
 * app secret is approaching its expiry date.
 *
 * Thresholds: 'month' (≤30 days), 'week' (≤7 days), 'day' (≤0 days)
 * Session key: _secret_remind_shown stores the last threshold shown.
 * A new reminder fires whenever the threshold level escalates.
 */

$secretExpiry = getSetting('graph_secret_expires_at', '');
if ($secretExpiry === '') {
    return; // No expiry date configured — nothing to show
}

$daysLeft = (int) ceil((strtotime($secretExpiry) - time()) / 86400);

if ($daysLeft > 30) {
    return; // Not within reminder window
}

// Determine which threshold bucket we're in
if ($daysLeft <= 0) {
    $threshold = 'day';
} elseif ($daysLeft <= 7) {
    $threshold = 'week';
} else {
    $threshold = 'month';
}

// Only show once per login session per threshold level
if (($_SESSION['_secret_remind_shown'] ?? '') === $threshold) {
    return;
}
$_SESSION['_secret_remind_shown'] = $threshold;

// Build modal content based on threshold
$expiryFormatted = date('F j, Y', strtotime($secretExpiry));

if ($threshold === 'day') {
    $modalStyle  = 'danger';
    $icon        = 'bi-exclamation-octagon-fill';
    $title       = 'App Secret Has Expired';
    $message     = 'Your Microsoft Graph app secret expired on <strong>' . htmlspecialchars($expiryFormatted) . '</strong>. '
                 . 'Reply-by-email processing has stopped working. '
                 . 'Rotate the secret in Azure Portal immediately and update it in your inbound mail settings.';
    $badgeClass  = 'bg-danger';
    $badgeText   = 'Expired';
} elseif ($threshold === 'week') {
    $modalStyle  = 'danger';
    $icon        = 'bi-exclamation-triangle-fill';
    $title       = 'App Secret Expiring Soon';
    $message     = 'Your Microsoft Graph app secret expires in <strong>' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . '</strong> '
                 . '(on ' . htmlspecialchars($expiryFormatted) . '). '
                 . 'Rotate it now to avoid interruption to reply-by-email processing.';
    $badgeClass  = 'bg-danger';
    $badgeText   = 'Expires in ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's');
} else {
    $modalStyle  = 'warning';
    $icon        = 'bi-clock-history';
    $title       = 'App Secret Expiry Reminder';
    $message     = 'Your Microsoft Graph app secret expires in <strong>' . $daysLeft . ' days</strong> '
                 . '(on ' . htmlspecialchars($expiryFormatted) . '). '
                 . 'Plan to rotate the secret in Azure Portal and update your inbound mail settings before it expires.';
    $badgeClass  = 'bg-warning text-dark';
    $badgeText   = 'Expires in ' . $daysLeft . ' days';
}
?>

<!-- App Secret Expiry Reminder Modal -->
<div class="modal fade" id="secretExpiryModal" tabindex="-1" aria-labelledby="secretExpiryModalLabel" aria-modal="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-1
                <?= $modalStyle === 'danger' ? 'bg-danger bg-opacity-10' : 'bg-warning bg-opacity-10' ?>">
                <h5 class="modal-title fw-semibold d-flex align-items-center gap-2" id="secretExpiryModalLabel">
                    <i class="bi <?= $icon ?>
                        <?= $modalStyle === 'danger' ? 'text-danger' : 'text-warning' ?>"></i>
                    <?= $title ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-3"><?= $message ?></p>
                <p class="mb-0 small text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Go to <strong>Azure Portal → App Registration → Certificates &amp; secrets</strong> to create a new secret,
                    then update it under <a href="/admin/settings#graph-secret" class="alert-link">Inbound Mail Settings</a>.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <a href="/admin/settings#graph-secret" class="btn btn-<?= $modalStyle ?> text-white">
                    <i class="bi bi-gear me-1"></i>Go to Settings
                </a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Dismiss
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('secretExpiryModal');
    if (modalEl) {
        new bootstrap.Modal(modalEl).show();
    }
});
</script>

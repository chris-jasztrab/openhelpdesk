<?php
$layout       = 'app';
$pageTitle    = 'CSAT Surveys';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'CSAT Surveys'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-star me-2"></i>Customer Satisfaction (CSAT) Surveys</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-4">
            When enabled, a satisfaction survey email is automatically sent to the ticket creator
            when their ticket reaches the selected status. The email contains a 1–5 star rating link
            that opens a public page — no login required.
        </p>

        <form method="POST" action="/admin/settings/csat">
            <?= csrfField() ?>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="csat_enabled" name="csat_enabled" value="1"
                           <?= $settings['csat_enabled'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="csat_enabled">
                        Enable CSAT surveys
                    </label>
                </div>
                <div class="form-text">When enabled, a rating email is sent each time a ticket reaches the trigger status below.</div>
            </div>

            <div class="mb-4">
                <label for="csat_trigger_status" class="form-label fw-semibold">Send survey when ticket is</label>
                <select class="form-select" id="csat_trigger_status" name="csat_trigger_status" style="max-width:220px;">
                    <option value="resolved" <?= $settings['csat_trigger_status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    <option value="closed"   <?= $settings['csat_trigger_status'] === 'closed'   ? 'selected' : '' ?>>Closed</option>
                </select>
                <div class="form-text">
                    <strong>Resolved</strong> — survey sent as soon as the agent marks the ticket resolved (recommended).<br>
                    <strong>Closed</strong> — survey sent only when the ticket is fully closed.
                </div>
            </div>

            <div class="alert alert-info" style="font-size:.875rem;">
                <i class="bi bi-info-circle me-2"></i>
                Only one survey is sent per ticket. If a ticket is re-resolved after being re-opened, no duplicate survey is sent.
                View survey results under <a href="/admin/reports/csat" class="alert-link">Reports &rarr; Satisfaction</a>.
            </div>

            <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Settings
            </button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-send me-2"></i>Send a Test Survey</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-4" style="font-size:.9rem;">
            Send a sample CSAT survey email to any address so you can preview the design and test the full rating flow.
        </p>
        <form method="POST" action="/admin/settings/csat/test" class="row g-3 align-items-end">
            <?= csrfField() ?>
            <div class="col-sm-6">
                <label for="test_email" class="form-label fw-semibold">Send to</label>
                <input type="email" class="form-control" id="test_email" name="test_email"
                       placeholder="you@example.com" required>
            </div>
            <div class="col-sm-auto">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-envelope me-1"></i>Send Test Survey
                </button>
            </div>
        </form>
    </div>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

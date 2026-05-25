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

            <hr class="my-4">

            <div class="mb-4">
                <label class="form-label fw-semibold mb-2">Survey type</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="csat_mode" id="csat_mode_internal"
                           value="internal" <?= $settings['csat_mode'] !== 'external' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="csat_mode_internal">
                        <strong>Built-in</strong> — recipients rate their experience on a 1–5 star page hosted by this app.
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="csat_mode" id="csat_mode_external"
                           value="external" <?= $settings['csat_mode'] === 'external' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="csat_mode_external">
                        <strong>External</strong> — link recipients to a survey hosted elsewhere (HappyOrNot, SurveyGizmo, Jotform, Typeform, etc.).
                    </label>
                </div>
            </div>

            <div id="external-csat-fields" class="mb-4 ps-4 border-start"
                 style="<?= $settings['csat_mode'] === 'external' ? '' : 'display:none;' ?>">
                <div class="mb-3">
                    <label for="csat_external_url" class="form-label fw-semibold">External survey URL</label>
                    <input type="url" class="form-control" id="csat_external_url" name="csat_external_url"
                           value="<?= htmlspecialchars($settings['csat_external_url'], ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="https://survey.example.com/start?ticket={ticket_id}&amp;email={user_email}">
                    <div class="form-text">
                        Where the rating button in the email will send the recipient. You can use these placeholders
                        (each value is URL-encoded for safe inclusion in a query string):
                        <code>{ticket_id}</code>, <code>{user_email}</code>, <code>{first_name}</code>,
                        <code>{last_name}</code>, <code>{user_name}</code>, <code>{subject}</code>.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="csat_external_dashboard_url" class="form-label fw-semibold">External dashboard URL <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="url" class="form-control" id="csat_external_dashboard_url" name="csat_external_dashboard_url"
                           value="<?= htmlspecialchars($settings['csat_external_dashboard_url'], ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="https://app.surveygizmo.com/reports/...">
                    <div class="form-text">
                        Shown as a link on the Satisfaction report so admins can jump to the external service to see ratings &amp; comments.
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="csat_show_reopen" name="csat_show_reopen" value="1"
                           <?= $settings['csat_show_reopen'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="csat_show_reopen">
                        Include &ldquo;No, please reopen it&rdquo; button in the email
                    </label>
                </div>
                <div class="form-text">
                    With this on, the email has two buttons &mdash; one to reopen the ticket (always handled by this app)
                    and one to rate the experience. With it off, the email has a single &ldquo;Rate your experience&rdquo;
                    button. Recommended on for built-in surveys; with external surveys it&rsquo;s the only way recipients
                    can reopen a ticket from the email.
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

<script>
(function () {
    const fields = document.getElementById('external-csat-fields');
    const urlInput = document.getElementById('csat_external_url');
    document.querySelectorAll('input[name="csat_mode"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            const external = radio.value === 'external' && radio.checked;
            fields.style.display = external ? '' : 'none';
            // Only require the URL when External is the selected mode; otherwise
            // the browser blocks save with an empty URL field that's not visible.
            if (urlInput) urlInput.required = external;
        });
    });
})();
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

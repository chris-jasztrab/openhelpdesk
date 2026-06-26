<?php
$layout       = 'app';
$pageTitle    = 'Microsoft Teams';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Microsoft Teams'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-microsoft-teams me-2"></i>Microsoft Teams notifications</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-4">
            Post ticket activity to a Microsoft Teams channel. When enabled, the events you
            select below are sent as cards to a channel <strong>Incoming Webhook</strong> URL.
        </p>

        <form method="POST" action="/admin/settings/teams">
            <?= csrfField() ?>

            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="teams_enabled" name="teams_enabled" value="1"
                       <?= $settings['teams_enabled'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="teams_enabled">Enable Teams notifications</label>
            </div>

            <div class="mb-4">
                <label for="teams_webhook_url" class="form-label fw-semibold">Webhook URL</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                    <input type="url" class="form-control" id="teams_webhook_url" name="teams_webhook_url"
                           placeholder="https://prod-00.westus.logic.azure.com:443/workflows/..."
                           value="<?= e($settings['teams_webhook_url']) ?>">
                    <button type="button" class="btn btn-outline-secondary" id="teams_test">
                        <i class="bi bi-send me-1"></i>Send test message
                    </button>
                </div>
                <div id="teams_test_result" class="form-text mt-2"></div>
                <div class="form-text">The channel webhook URL — see the setup steps below. Stored securely; only admins can view this page.</div>
            </div>

            <hr class="my-4">

            <h6 class="fw-semibold mb-3">Which events to post</h6>
            <?php
            $events = [
                'teams_event_created'  => ['New ticket created',  'Posted whenever a ticket is opened (any channel — agent, portal, email, floor).'],
                'teams_event_assigned' => ['Ticket assigned',     'Posted when a ticket is assigned or reassigned to an agent.'],
                'teams_event_status'   => ['Status changed',      'Posted when a ticket moves between statuses (shows the old → new status).'],
                'teams_event_sla'      => ['SLA breached',        'Posted the moment a ticket breaches its SLA (detected during SLA recalculation).'],
            ];
            foreach ($events as $key => [$label, $desc]):
            ?>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="<?= $key ?>" name="<?= $key ?>" value="1"
                       <?= $settings[$key] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= $key ?>">
                    <span class="fw-semibold"><?= e($label) ?></span>
                    <span class="d-block text-muted small"><?= e($desc) ?></span>
                </label>
            </div>
            <?php endforeach; ?>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save settings</button>
            </div>
        </form>

        <hr class="my-4">

        <div class="bg-light rounded p-3">
            <h6 class="fw-semibold"><i class="bi bi-info-circle me-2"></i>How to get a webhook URL</h6>
            <p class="text-muted small mb-2">
                Microsoft is retiring the old "Office 365 connector" webhooks in favour of
                <strong>Workflows</strong> (Power Automate). Create one in a few clicks:
            </p>
            <ol class="text-muted small mb-0">
                <li>In Teams, open the channel you want notifications in → <strong>⋯</strong> → <strong>Workflows</strong>.</li>
                <li>Choose the template <strong>“Post to a channel when a webhook request is received”</strong>.</li>
                <li>Confirm the team &amp; channel, then <strong>Add workflow</strong>.</li>
                <li>Copy the generated <strong>HTTP POST URL</strong> and paste it above.</li>
                <li>Click <strong>Send test message</strong> to confirm it works, then <strong>Save settings</strong>.</li>
            </ol>
        </div>
    </div>
</div>

<script>
(function () {
    const btn = document.getElementById('teams_test');
    const out = document.getElementById('teams_test_result');
    const urlInput = document.getElementById('teams_webhook_url');
    const token = document.querySelector('meta[name="csrf-token"]').content;

    btn.addEventListener('click', function () {
        const url = urlInput.value.trim();
        if (!url) { out.innerHTML = '<span class="text-danger">Enter a webhook URL first.</span>'; return; }
        btn.disabled = true;
        out.innerHTML = '<span class="text-muted">Sending…</span>';
        fetch('/admin/settings/teams/test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
            body: JSON.stringify({ webhook_url: url }),
        })
        .then(r => r.json())
        .then(res => {
            out.innerHTML = res.ok
                ? '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Test message sent — check the channel.</span>'
                : '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + (res.error || 'Failed to send.') + '</span>';
        })
        .catch(() => { out.innerHTML = '<span class="text-danger">Request failed.</span>'; })
        .finally(() => { btn.disabled = false; });
    });
})();
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

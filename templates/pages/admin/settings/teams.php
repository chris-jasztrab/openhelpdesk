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
                <label for="teams_webhook_url" class="form-label fw-semibold">Default channel webhook</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                    <input type="url" class="form-control teams-hook" id="teams_webhook_url" name="teams_webhook_url"
                           placeholder="https://prod-00.westus.logic.azure.com:443/workflows/..."
                           value="<?= e($settings['teams_webhook_url']) ?>">
                    <button type="button" class="btn btn-outline-secondary teams-test" data-target="teams_webhook_url">
                        <i class="bi bi-send me-1"></i>Test
                    </button>
                </div>
                <div class="teams-test-result form-text mt-2" data-for="teams_webhook_url"></div>
                <div class="form-text">
                    Tickets are posted here <strong>unless</strong> their type has its own channel below.
                    Leave blank to only notify the types you route explicitly. See the setup steps at the bottom.
                </div>
            </div>

            <hr class="my-4">

            <h6 class="fw-semibold mb-1">Route by ticket type <span class="text-muted fw-normal small">(optional)</span></h6>
            <p class="text-muted small mb-3">
                Give a ticket type its own channel webhook to send <em>that type's</em> notifications to a different
                Teams channel — e.g. IT tickets to the IT channel, Lifelong Learning tickets to theirs. Any type left
                blank uses the default channel above.
            </p>
            <?php if (empty($types)): ?>
                <p class="text-muted small fst-italic">No ticket types defined yet.</p>
            <?php else: ?>
            <div class="table-responsive mb-2">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr class="text-muted small">
                            <th style="width:180px;">Ticket type</th>
                            <th>Channel webhook URL</th>
                            <th style="width:80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($types as $t): $fid = 'teams_type_' . (int) $t['id']; ?>
                        <tr>
                            <td><span class="badge text-bg-light border"><i class="bi bi-tag me-1"></i><?= e($t['name']) ?></span></td>
                            <td>
                                <input type="url" class="form-control form-control-sm teams-hook" id="<?= e($fid) ?>"
                                       name="teams_webhook_type[<?= (int) $t['id'] ?>]"
                                       placeholder="Uses the default channel"
                                       value="<?= e($t['webhook']) ?>">
                                <div class="teams-test-result form-text" data-for="<?= e($fid) ?>"></div>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary teams-test" data-target="<?= e($fid) ?>">
                                    <i class="bi bi-send"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

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
                <li>Search for <code>webhook</code> and choose the template <strong>“Send webhook alerts to a channel”</strong> (formerly “Post to a channel when a webhook request is received”).</li>
                <li>Confirm the team &amp; channel, then <strong>Add workflow</strong>.</li>
                <li>Copy the generated <strong>HTTP POST URL</strong> and paste it above.</li>
                <li>Click <strong>Send test message</strong> to confirm it works, then <strong>Save settings</strong>.</li>
            </ol>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4" id="bot">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-robot me-2"></i>Helpdesk bot</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-4">
            A bot staff can add to any Teams chat or channel to <strong>open tickets, check on them, reply and close them</strong>
            without leaving Teams. It needs an <strong>Azure Bot</strong> resource pointed at this helpdesk &mdash; the steps are at the bottom.
        </p>

        <form method="POST" action="/admin/settings/teams/bot">
            <?= csrfField() ?>

            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="teams_bot_enabled" name="teams_bot_enabled" value="1"
                       <?= $bot['enabled'] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="teams_bot_enabled">Enable the helpdesk bot</label>
            </div>

            <div class="mb-4">
                <label for="teams_bot_endpoint" class="form-label fw-semibold">Messaging endpoint</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                    <input type="text" class="form-control" id="teams_bot_endpoint" readonly value="<?= e($bot['endpoint']) ?>">
                    <button type="button" class="btn btn-outline-secondary" id="teams-bot-copy" title="Copy"><i class="bi bi-clipboard"></i></button>
                </div>
                <div class="form-text">Paste this into the Azure Bot's <strong>Configuration &rarr; Messaging endpoint</strong>. It must be reachable from the internet over HTTPS.</div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="teams_bot_app_id" class="form-label fw-semibold">Microsoft App ID</label>
                    <input type="text" class="form-control" id="teams_bot_app_id" name="teams_bot_app_id"
                           value="<?= e($bot['app_id']) ?>"
                           placeholder="<?= $bot['sso_app_id'] !== '' ? 'Blank = SSO app ' . e($bot['sso_app_id']) : '00000000-0000-0000-0000-000000000000' ?>">
                </div>
                <div class="col-md-6">
                    <label for="teams_bot_tenant_id" class="form-label fw-semibold">Tenant ID</label>
                    <input type="text" class="form-control" id="teams_bot_tenant_id" name="teams_bot_tenant_id"
                           value="<?= e($bot['tenant_id']) ?>"
                           placeholder="<?= $bot['sso_tenant_id'] !== '' ? 'Blank = SSO tenant ' . e($bot['sso_tenant_id']) : 'Blank = multi-tenant app' ?>">
                </div>
                <div class="col-12">
                    <label for="teams_bot_app_secret" class="form-label fw-semibold">Client secret</label>
                    <input type="password" class="form-control" id="teams_bot_app_secret" name="teams_bot_app_secret" autocomplete="new-password"
                           placeholder="<?= $bot['has_secret'] ? '•••••••• saved — leave blank to keep it' : ($bot['sso_has_secret'] ? 'Blank = SSO secret' : '') ?>">
                    <?php if ($bot['has_secret']): ?>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="teams_bot_clear_secret" name="teams_bot_clear_secret" value="1">
                        <label class="form-check-label small text-muted" for="teams_bot_clear_secret">Clear the saved secret</label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="form-text mt-2">
                Leave all three blank to reuse the Microsoft 365 <a href="/admin/settings/sso">SSO</a> app registration &mdash; simplest when the Azure Bot is created on that app.
            </div>

            <div class="mt-4 d-flex flex-wrap gap-2 align-items-center">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save bot settings</button>
                <button type="button" class="btn btn-outline-secondary" id="teams-bot-test"><i class="bi bi-key me-1"></i>Check saved credentials</button>
                <a class="btn btn-outline-secondary" href="/admin/settings/teams/bot/manifest"><i class="bi bi-download me-1"></i>Download Teams app package</a>
            </div>
            <div id="teams-bot-test-result" class="form-text mt-2"></div>
        </form>

        <hr class="my-4">

        <div class="bg-light rounded p-3">
            <h6 class="fw-semibold"><i class="bi bi-info-circle me-2"></i>How to set up the bot</h6>
            <ol class="text-muted small mb-0">
                <li>In the Azure portal create an <strong>Azure Bot</strong> resource. For <em>Type of app</em> choose <strong>Single Tenant</strong>; either let it create a Microsoft App ID or pick <em>Use existing app registration</em> and enter the SSO app's ID.</li>
                <li>Create a client secret on that app registration (Entra ID &rarr; App registrations &rarr; Certificates &amp; secrets) and enter the App ID, tenant ID and secret above. Enable the bot and <strong>Save</strong>, then <strong>Check saved credentials</strong>.</li>
                <li>In the bot's <strong>Configuration</strong>, set the Messaging endpoint to the URL above.</li>
                <li>Under <strong>Channels</strong>, add <strong>Microsoft Teams</strong>.</li>
                <li><strong>Download the Teams app package</strong> and upload it in Teams (<em>Apps &rarr; Manage your apps &rarr; Upload an app</em>), or have a Teams admin publish it to your organisation's app catalogue so anyone can add it.</li>
                <li>Add the app to a chat or channel and say <strong>help</strong> (in a channel, @mention it first).</li>
            </ol>
        </div>
    </div>
</div>

<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]').content;

    const copyBtn = document.getElementById('teams-bot-copy');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            navigator.clipboard.writeText(document.getElementById('teams_bot_endpoint').value).then(function () {
                copyBtn.innerHTML = '<i class="bi bi-check"></i>';
                setTimeout(function () { copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1500);
            });
        });
    }

    const botBtn = document.getElementById('teams-bot-test');
    const botOut = document.getElementById('teams-bot-test-result');
    if (botBtn) {
        botBtn.addEventListener('click', function () {
            botBtn.disabled = true;
            botOut.innerHTML = '<span class="text-muted">Asking Microsoft for a bot token…</span>';
            fetch('/admin/settings/teams/bot/test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
                body: '{}',
            })
            .then(r => r.json())
            .then(res => {
                botOut.textContent = '';
                const span = document.createElement('span');
                span.className = res.ok ? 'text-success' : 'text-danger';
                span.textContent = res.ok
                    ? '✓ Microsoft accepted the saved App ID and secret. If you haven\'t yet, set the messaging endpoint and add the Teams channel in Azure.'
                    : '✗ ' + (res.error || 'Failed.');
                botOut.appendChild(span);
            })
            .catch(() => { botOut.innerHTML = '<span class="text-danger">Request failed.</span>'; })
            .finally(() => { botBtn.disabled = false; });
        });
    }

    function resultEl(targetId) {
        return document.querySelector('.teams-test-result[data-for="' + targetId + '"]');
    }

    document.querySelectorAll('.teams-test').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = btn.dataset.target;
            const input = document.getElementById(targetId);
            const out = resultEl(targetId);
            const url = (input.value || '').trim();
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
    });
})();
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

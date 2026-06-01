<?php
$layout       = 'app';
$pageTitle    = 'Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = Auth::isAdmin()
    ? [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Settings']]
    : [['label' => 'Settings']];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<!-- Email / SMTP Configuration -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-envelope me-2"></i>Email / SMTP Configuration</h5>
    </div>
    <div class="card-body p-4">
        <form method="POST" action="/admin/settings/email">
            <?= csrfField() ?>

            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label for="smtp_host" class="form-label fw-semibold">SMTP Host</label>
                    <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                           value="<?= e($settings['smtp_host']) ?>"
                           placeholder="e.g. smtp.gmail.com">
                </div>
                <div class="col-md-4">
                    <label for="smtp_port" class="form-label fw-semibold">Port</label>
                    <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                           value="<?= e($settings['smtp_port'] ?: '587') ?>"
                           placeholder="587">
                </div>
            </div>

            <div class="mb-3">
                <label for="smtp_encryption" class="form-label fw-semibold">Encryption</label>
                <select class="form-select" id="smtp_encryption" name="smtp_encryption" style="max-width:200px;">
                    <option value="tls" <?= ($settings['smtp_encryption'] ?: 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= $settings['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value="none" <?= $settings['smtp_encryption'] === 'none' ? 'selected' : '' ?>>None</option>
                </select>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="smtp_username" class="form-label fw-semibold">Username</label>
                    <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                           value="<?= e($settings['smtp_username']) ?>"
                           placeholder="e.g. user@example.com" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label for="smtp_password" class="form-label fw-semibold">Password</label>
                    <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                           value="" placeholder="<?= $settings['smtp_password'] !== '' ? '••••••••' : '' ?>"
                           autocomplete="new-password">
                    <?php if ($settings['smtp_password'] !== ''): ?>
                        <div class="form-text">Leave blank to keep the current password.</div>
                    <?php endif; ?>
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="mail_from_address" class="form-label fw-semibold">From Address</label>
                    <input type="email" class="form-control" id="mail_from_address" name="mail_from_address"
                           value="<?= e($settings['mail_from_address']) ?>"
                           placeholder="e.g. noreply@example.com">
                </div>
                <div class="col-md-6">
                    <label for="mail_from_name" class="form-label fw-semibold">From Name</label>
                    <input type="text" class="form-control" id="mail_from_name" name="mail_from_name"
                           value="<?= e($settings['mail_from_name']) ?>"
                           placeholder="e.g. OpenHelpDesk">
                </div>
            </div>

            <hr class="my-4">

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="smtp_debug" name="smtp_debug" value="1"
                           <?= ($settings['smtp_debug'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="smtp_debug">
                        SMTP Debug Logging
                    </label>
                </div>
                <div class="form-text">
                    When enabled, every outgoing mail attempt is logged in full detail to
                    <code>storage/logs/smtp.log</code> — useful for diagnosing connection issues.
                    Disable once SMTP is working correctly.
                </div>
            </div>

            <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Settings
            </button>
        </form>
    </div>
</div>

<!-- Test Email -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-send me-2"></i>Test Email</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-3">Send a test email to verify your SMTP configuration.</p>
        <form method="POST" action="/admin/settings/test-email" class="d-flex gap-2 align-items-start" style="max-width:420px;">
            <?= csrfField() ?>
            <input type="email" name="to_email" class="form-control" required
                   value="<?= e(Auth::user()['email'] ?? '') ?>"
                   placeholder="recipient@example.com">
            <button type="submit" class="btn btn-outline-primary text-nowrap">
                <i class="bi bi-envelope-arrow-up me-1"></i>Send
            </button>
        </form>
    </div>
</div>

<!-- Ticket Routing Defaults -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-diagram-3 me-2"></i>Ticket Routing Defaults</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-3">
            The catch-all queue for any ticket that ends up without a group. Every creation path
            (portal, agent &amp; admin forms, ticket splits, public REST API, email-to-ticket, legacy CSV import)
            checks the picked group, then the ticket type's default group, and finally
            <strong>this setting</strong>. Tickets land here only when none of the earlier rules matched —
            ensuring no ticket ever sits invisible in a "no group" queue. Your team should treat this group
            as a triage queue and either work or re-route what arrives.
        </p>
        <form method="POST" action="/admin/settings/ticket-routing">
            <?= csrfField() ?>
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="default_group_id" class="form-label fw-semibold">Default Group</label>
                    <select class="form-select" id="default_group_id" name="default_group_id">
                        <option value="">— None (tickets may sit unrouted) —</option>
                        <?php foreach (($groups ?? []) as $g): ?>
                            <option value="<?= (int) $g['id'] ?>"
                                <?= ((string) ($settings['default_group_id'] ?? '') === (string) $g['id']) ? 'selected' : '' ?>>
                                <?= e($g['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        Best practice: pick (or create) a generic <em>Triage</em> or <em>Service Desk</em> group
                        and set its assignment strategy at <a href="/admin/groups">Admin → Settings → Groups</a>
                        so the catch-all queue is also auto-distributed to a human.
                    </div>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                        <i class="bi bi-check-lg me-1"></i>Save
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Email-to-Ticket -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-envelope-plus me-2"></i>Email-to-Ticket</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-3">
            When enabled, emails sent to the helpdesk mailbox that are <strong>not</strong> replies to existing
            tickets automatically become new tickets. Uses the same Microsoft Graph mailbox configured below —
            enable <em>Inbound Mail</em> first.
        </p>

        <form method="POST" action="/admin/settings/email-to-ticket">
            <?= csrfField() ?>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="email_to_ticket_enabled" name="email_to_ticket_enabled" value="1"
                           <?= ($settings['email_to_ticket_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="email_to_ticket_enabled">
                        Enable Email-to-Ticket
                    </label>
                </div>
                <div class="form-text">Inbound emails without a ticket reference in the subject will create a new ticket.</div>
            </div>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="email_to_ticket_auto_create_users" name="email_to_ticket_auto_create_users" value="1"
                           <?= ($settings['email_to_ticket_auto_create_users'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="email_to_ticket_auto_create_users">
                        Auto-create user accounts
                    </label>
                </div>
                <div class="form-text">If the sender's email is not a registered user, automatically create a portal account for them. If disabled, emails from unknown senders are skipped.</div>
            </div>

            <hr class="my-4">

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="email_to_ticket_default_type_id" class="form-label fw-semibold">Default Ticket Type</label>
                    <select class="form-select" id="email_to_ticket_default_type_id" name="email_to_ticket_default_type_id">
                        <option value="">— Unclassified —</option>
                        <?php foreach ($types as $t): ?>
                        <option value="<?= (int) $t['id'] ?>"
                                <?= ($settings['email_to_ticket_default_type_id'] == $t['id']) ? 'selected' : '' ?>>
                            <?= e($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Applied to all tickets created via email.</div>
                </div>
                <div class="col-md-6">
                    <label for="email_to_ticket_default_priority_id" class="form-label fw-semibold">Default Priority</label>
                    <select class="form-select" id="email_to_ticket_default_priority_id" name="email_to_ticket_default_priority_id">
                        <option value="">— None —</option>
                        <?php foreach ($priorities as $pri): ?>
                        <option value="<?= (int) $pri['id'] ?>"
                                <?= ($settings['email_to_ticket_default_priority_id'] == $pri['id']) ? 'selected' : '' ?>>
                            <?= e($pri['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Applied to all tickets created via email.</div>
                </div>
            </div>

            <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Email-to-Ticket Settings
            </button>
        </form>
    </div>
</div>

<!-- Inbound Mail / Reply Processing (Microsoft Graph API) -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-envelope-arrow-down me-2"></i>Inbound Mail — Reply Processing</h5>
        <a href="/admin/settings/email-reply-help" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-question-circle me-1"></i>Setup Guide
        </a>
    </div>
    <div class="card-body p-4">
        <p class="text-muted mb-3">
            When enabled, users can reply directly to ticket notification emails and their reply will be
            added as a comment on the ticket. This uses the <strong>Microsoft Graph API</strong> — no
            App Password or IMAP access required.
        </p>

        <div class="alert alert-info mb-4" style="font-size:.875rem;">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Prerequisites:</strong> You need an <strong>Azure App Registration</strong> with
            <code>Mail.Read</code> and <code>Mail.ReadWrite</code> application permissions granted by
            your tenant admin. Click <a href="/admin/settings/email-reply-help" class="alert-link">Setup Guide</a>
            for step-by-step instructions.
        </div>

        <form method="POST" action="/admin/settings/graph">
            <?= csrfField() ?>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="graph_enabled" name="graph_enabled" value="1"
                           <?= $settings['graph_enabled'] === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="graph_enabled">Enable reply-by-email</label>
                </div>
                <div class="form-text">When enabled, outgoing ticket emails will include a Reply-To address pointing to the mailbox below.</div>
            </div>

            <hr class="my-4">

            <div class="mb-3">
                <label for="graph_reply_to" class="form-label fw-semibold">Reply-To Address</label>
                <input type="email" class="form-control" id="graph_reply_to" name="graph_reply_to"
                       value="<?= e($settings['graph_reply_to']) ?>"
                       placeholder="tickets@yourdomain.com" style="max-width:360px;">
                <div class="form-text">The address users reply to. Must match the Mailbox Address below.</div>
            </div>

            <div class="mb-3">
                <label for="graph_mailbox" class="form-label fw-semibold">Mailbox Address</label>
                <input type="email" class="form-control" id="graph_mailbox" name="graph_mailbox"
                       value="<?= e($settings['graph_mailbox']) ?>"
                       placeholder="tickets@yourdomain.com" style="max-width:360px;">
                <div class="form-text">The Microsoft 365 mailbox the cron job will poll for new replies.</div>
            </div>

            <div class="mb-3">
                <label for="graph_tenant_id" class="form-label fw-semibold">Tenant ID</label>
                <input type="text" class="form-control font-monospace" id="graph_tenant_id" name="graph_tenant_id"
                       value="<?= e($settings['graph_tenant_id']) ?>"
                       placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" style="max-width:420px;" autocomplete="off">
                <div class="form-text">Found in Azure Portal → Azure Active Directory → Overview.</div>
            </div>

            <div class="mb-3">
                <label for="graph_client_id" class="form-label fw-semibold">Client ID (Application ID)</label>
                <input type="text" class="form-control font-monospace" id="graph_client_id" name="graph_client_id"
                       value="<?= e($settings['graph_client_id']) ?>"
                       placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" style="max-width:420px;" autocomplete="off">
                <div class="form-text">Found in your App Registration → Overview.</div>
            </div>

            <div class="mb-3" id="graph-secret">
                <label for="graph_client_secret" class="form-label fw-semibold">Client Secret</label>
                <input type="password" class="form-control" id="graph_client_secret" name="graph_client_secret"
                       value="" placeholder="<?= $settings['graph_client_secret'] !== '' ? '••••••••' : '' ?>"
                       style="max-width:420px;" autocomplete="new-password">
                <?php if ($settings['graph_client_secret'] !== ''): ?>
                    <div class="form-text">Leave blank to keep the current secret.</div>
                <?php else: ?>
                    <div class="form-text">Created under App Registration → Certificates &amp; secrets.</div>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label for="graph_secret_expires_at" class="form-label fw-semibold">App Secret Expiry Date</label>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <input type="date" class="form-control" id="graph_secret_expires_at" name="graph_secret_expires_at"
                           value="<?= e($settings['graph_secret_expires_at'] ?? '') ?>"
                           style="max-width:220px;">
                    <?php
                    $secretExpiry = $settings['graph_secret_expires_at'] ?? '';
                    if ($secretExpiry !== '') {
                        $daysLeft = (int) ceil((strtotime($secretExpiry) - time()) / 86400);
                        if ($daysLeft <= 0) {
                            echo '<span class="badge bg-danger fs-6"><i class="bi bi-exclamation-triangle-fill me-1"></i>EXPIRED</span>';
                        } elseif ($daysLeft <= 7) {
                            echo '<span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Expires in ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' — action required</span>';
                        } elseif ($daysLeft <= 30) {
                            echo '<span class="badge bg-warning text-dark"><i class="bi bi-clock-history me-1"></i>Expires in ' . $daysLeft . ' days — rotate soon</span>';
                        } else {
                            echo '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Expires ' . htmlspecialchars(date('M j, Y', strtotime($secretExpiry))) . '</span>';
                        }
                    }
                    ?>
                </div>
                <div class="form-text">Enter the exact expiry date shown in Azure Portal → App Registration → Certificates &amp; secrets. You'll receive reminders 30 days, 7 days, and on the day of expiry.</div>
            </div>

            <hr class="my-4">

            <div class="mb-4">
                <h6 class="fw-semibold mb-1">Cron Job</h6>
                <p class="text-muted small mb-2">Add this to your server's crontab to poll for new replies every 5 minutes:</p>
                <code class="d-block bg-light border rounded p-2 small user-select-all">*/5 * * * * php <?= e(ROOT_DIR) ?>/scripts/process-replies.php &gt;&gt; <?= e(ROOT_DIR) ?>/storage/logs/graph-mail.log 2&gt;&amp;1</code>
            </div>

            <div class="d-flex gap-2 align-items-center flex-wrap">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i>Save Inbound Settings
                </button>
            </div>
        </form>

        <!-- Run processor immediately -->
        <hr class="my-4">
        <div>
            <h6 class="fw-semibold mb-1">Run Now</h6>
            <p class="text-muted small mb-3">Execute the reply processor immediately without waiting for the cron schedule. Useful for testing your configuration.</p>
            <form method="POST" action="/admin/settings/run-reply-processor">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="bi bi-play-circle me-1"></i>Run Now
                </button>
            </form>

            <?php if (!empty($runOutput)): ?>
            <div class="mt-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="small fw-semibold text-muted">Last run: <?= e($runOutput['time']) ?></span>
                    <?php if ($runOutput['code'] === 0): ?>
                        <span class="badge bg-success">Exit 0 — OK</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Exit <?= (int) $runOutput['code'] ?> — Error</span>
                    <?php endif; ?>
                </div>
                <pre class="bg-dark text-light rounded p-3 small mb-0" style="max-height:300px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;"><?= e(implode("\n", $runOutput['lines'])) ?></pre>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

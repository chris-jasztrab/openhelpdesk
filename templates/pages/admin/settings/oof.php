<?php
$layout       = 'app';
$pageTitle    = 'Out of Office – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Out of Office'],
];

// Mirror of DEFAULT_OOF_TEMPLATE in scripts/process-oof-coverage.php — shown as
// the textarea placeholder so admins can see what's used when the field is blank.
$defaultTemplate =
    "Hello {requester_name},\n\n" .
    "Thank you for your request (ticket #{ticket_id}). The staff member handling it, " .
    "{agent_name}, is currently out of office until {return_date}. Your ticket remains " .
    "open and will be addressed as soon as possible.\n\n" .
    "We appreciate your patience.";
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0"><i class="bi bi-person-x me-2"></i>Out of Office Coverage</h5>
    <a href="/admin/settings/oof/help" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-question-circle me-1"></i>Setup Guide
    </a>
</div>

<p class="text-muted mb-4" style="max-width:48rem;">
    Keeps tickets moving when an agent is away. A scheduled job reads each agent's
    Outlook <strong>automatic-replies (out-of-office)</strong> state via Microsoft Graph and,
    for their unanswered tickets, hands the work to an available group member — or, when there's
    nobody to hand it to (single-person groups, or everyone away), auto-replies the requester
    with the agent's out-of-office message.
</p>

<?php if (!$graphConfigured): ?>
<div class="alert alert-warning d-flex gap-2 align-items-start mb-4">
    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
    <div class="small">
        Microsoft Graph credentials aren't configured yet. This feature reuses the same Azure app
        registration as inbound email — set the Tenant ID, Client ID and Client Secret under
        <a href="/admin/settings" class="alert-link">Email / SMTP</a> first, and grant the app the
        <code>MailboxSettings.Read</code> application permission. See the
        <a href="/admin/settings/oof/help" class="alert-link">Setup Guide</a>.
    </div>
</div>
<?php endif; ?>

<form method="POST" action="/admin/settings/oof">
    <?= csrfField() ?>

    <!-- Enable / Disable -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-toggle-on me-1"></i>Out-of-Office Coverage
        </div>
        <div class="card-body">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="oof_enabled" name="oof_enabled" value="1"
                       <?= ($oofEnabled ?? '0') === '1' ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="oof_enabled">
                    Enable out-of-office coverage
                </label>
            </div>
            <div class="form-text">
                Requires the <strong>OOF Coverage</strong> cron job (see
                <a href="/admin/settings/cron-jobs">Cron Jobs</a>) and the
                <code>MailboxSettings.Read</code> Graph permission.
            </div>
        </div>
    </div>

    <!-- Behaviour -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-diagram-3 me-1"></i>When an agent is out of office
        </div>
        <div class="card-body">
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="oof_action" id="oa_both"
                       value="reassign_then_reply" <?= ($oofAction ?? '') === 'reassign_then_reply' ? 'checked' : '' ?>>
                <label class="form-check-label" for="oa_both">
                    <strong>Reassign if possible, otherwise auto-reply</strong> <span class="badge bg-secondary">Recommended</span>
                    <span class="text-muted small d-block">Hand the ticket to an available group member. Only auto-reply the requester when there's nobody to reassign to (single-person groups, or everyone away).</span>
                </label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="oof_action" id="oa_reassign"
                       value="reassign_only" <?= ($oofAction ?? '') === 'reassign_only' ? 'checked' : '' ?>>
                <label class="form-check-label" for="oa_reassign">
                    <strong>Reassign only</strong>
                    <span class="text-muted small d-block">Move tickets to an available member, but never auto-reply. Single-person-group tickets are left untouched.</span>
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="oof_action" id="oa_reply"
                       value="reply_only" <?= ($oofAction ?? '') === 'reply_only' ? 'checked' : '' ?>>
                <label class="form-check-label" for="oa_reply">
                    <strong>Auto-reply only</strong>
                    <span class="text-muted small d-block">Never move tickets between agents; just let the requester know their agent is away.</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Scope -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-funnel me-1"></i>Which tickets to act on
        </div>
        <div class="card-body">
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="oof_scope" id="os_unanswered"
                       value="unanswered" <?= ($oofScope ?? 'unanswered') === 'unanswered' ? 'checked' : '' ?>>
                <label class="form-check-label" for="os_unanswered">
                    <strong>Unanswered tickets only</strong> <span class="badge bg-secondary">Recommended</span>
                    <span class="text-muted small d-block">Only tickets with no agent reply yet — the ones that would otherwise sit untouched. Won't disturb conversations already in progress.</span>
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="oof_scope" id="os_all"
                       value="all" <?= ($oofScope ?? 'unanswered') === 'all' ? 'checked' : '' ?>>
                <label class="form-check-label" for="os_all">
                    <strong>All active tickets</strong>
                    <span class="text-muted small d-block">Every open ticket owned by the away agent, including ones already mid-conversation.</span>
                </label>
            </div>
        </div>
    </div>

    <!-- Auto-reply template -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent fw-semibold">
            <i class="bi bi-chat-left-text me-1"></i>Auto-reply message
        </div>
        <div class="card-body">
            <label for="oof_reply_template" class="form-label fw-semibold">Message to the requester</label>
            <textarea class="form-control font-monospace" id="oof_reply_template" name="oof_reply_template"
                      rows="6" placeholder="<?= e($defaultTemplate) ?>"><?= e($oofReplyTemplate ?? '') ?></textarea>
            <div class="form-text">
                Leave blank to use the default shown above. Available tokens:
                <code>{requester_name}</code>, <code>{ticket_id}</code>, <code>{agent_name}</code>,
                <code>{return_date}</code>. The agent's Outlook out-of-office message is appended
                below this text automatically when present.
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn text-white px-4" style="background:var(--ld-primary);">
            <i class="bi bi-check-lg me-1"></i>Save Settings
        </button>
        <a href="/admin/settings" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<!-- Current status -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people me-1"></i>Current agent status</span>
        <span class="text-muted small fw-normal">From the last cron run</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($oofStatuses)): ?>
            <p class="text-muted small p-3 mb-0">
                No status recorded yet. Once the OOF Coverage cron job runs, each group member's
                out-of-office state appears here.
            </p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr class="small text-muted">
                        <th class="ps-3">Agent</th>
                        <th>Status</th>
                        <th>Until</th>
                        <th class="pe-3">Last checked</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($oofStatuses as $s): ?>
                    <tr>
                        <td class="ps-3">
                            <div class="fw-semibold"><?= e(trim($s['first_name'] . ' ' . $s['last_name'])) ?></div>
                            <div class="text-muted small"><?= e($s['email']) ?></div>
                        </td>
                        <td>
                            <?php if ((int) $s['is_oof'] === 1): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-person-x me-1"></i>Out of office</span>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border">Available</span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?= $s['scheduled_end'] ? e(date('M j, Y', strtotime((string) $s['scheduled_end']))) : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td class="pe-3 text-muted small">
                            <?= $s['checked_at'] ? e(date('M j, g:i a', strtotime((string) $s['checked_at']))) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

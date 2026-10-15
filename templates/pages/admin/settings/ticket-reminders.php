<?php
$layout       = 'app';
$pageTitle    = 'Ticket Reminders';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Ticket Reminders'],
];

$on          = fn (string $k): string => ($settings[$k] ?? '0') === '1' ? 'checked' : '';
$scope       = ($settings['ticket_reminders_scope'] ?? 'new') === 'all' ? 'all' : 'new';
$scopeCutoff = (string) ($settings['ticket_reminders_scope_cutoff'] ?? '');
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="mb-4">
    <h5 class="fw-semibold mb-1"><i class="bi bi-alarm me-2"></i>Ticket Reminders</h5>
    <p class="text-muted mb-0" style="font-size:.875rem;">
        Keep customers informed without automated filler. Everything here nudges the <strong>ticket owner</strong> to contact the customer;
        nothing on this page emails the customer by itself.
    </p>
</div>

<form method="POST" action="/admin/settings/ticket-reminders">
    <?= csrfField() ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-event me-2"></i>Due Dates</h6>
        </div>
        <div class="card-body p-4">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="due_date_follows_sla" name="due_date_follows_sla" <?= $on('due_date_follows_sla') ?>>
                <label class="form-check-label" for="due_date_follows_sla">
                    <span class="fw-semibold">Due date follows the SLA</span>
                    <span class="d-block text-muted small mt-1">
                        A ticket's due date is set to its <a href="/admin/settings/sla-policies">SLA</a> resolution date, and moves with it when the SLA moves
                        (a pause ends, or the priority or type changes). A due date someone set by hand is never overwritten.
                    </span>
                </label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="due_date_extension_prompt" name="due_date_extension_prompt" <?= $on('due_date_extension_prompt') ?>>
                <label class="form-check-label" for="due_date_extension_prompt">
                    <span class="fw-semibold">Ask for a customer reply when a due date moves past the SLA date</span>
                    <span class="d-block text-muted small mt-1">
                        On the ticket page, saving a due date later than the SLA target opens a box for a reply telling the customer why it's taking longer
                        and when to expect it. The reply goes out with the save. Staff can still save without replying.
                    </span>
                </label>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-envelope-exclamation me-2"></i>Owner Reminder Emails</h6>
        </div>
        <div class="card-body p-4">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="notify_overdue" name="notify_overdue" <?= $on('email_notify:ticket_overdue') ?>>
                <label class="form-check-label" for="notify_overdue">
                    <span class="fw-semibold">Email the owner when a ticket passes its due date</span>
                    <span class="d-block text-muted small mt-1">
                        Sent once, the day after the due date, if the ticket is still open. It asks them to contact the customer, and to set a new due date
                        if the work needs longer. Moving the due date later sets up a new reminder for the new date.
                    </span>
                </label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="notify_update" name="notify_update" <?= $on('email_notify:ticket_update_due') ?>>
                <label class="form-check-label" for="notify_update">
                    <span class="fw-semibold">Email the owner when the customer hasn't heard from us in</span>
                    <input type="number" class="form-control form-control-sm d-inline-block mx-1" style="width:5rem;" min="1" max="365"
                           id="ticket_update_due_days" name="ticket_update_due_days" value="<?= (int) ($settings['ticket_update_due_days'] ?? 15) ?>"
                           aria-label="Days without a reply">
                    <span class="fw-semibold">days</span>
                    <span class="d-block text-muted small mt-1">
                        Counted from the last public reply by staff, or from when the ticket was opened if nobody has replied yet. Any public reply resets it.
                        While the customer still hasn't heard anything, it repeats every that-many days.
                    </span>
                </label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="notify_managers" name="notify_managers" <?= $on('ticket_reminders_managers') ?>>
                <label class="form-check-label" for="notify_managers">
                    <span class="fw-semibold">If a ticket has no owner, email its group's manager(s) instead</span>
                    <span class="d-block text-muted small mt-1">
                        Managers are the group members ticked <strong>Manager</strong> under <a href="/admin/groups">Groups</a>.
                        With this off, unassigned tickets get no reminder.
                        <?php if (($openUnassigned ?? 0) > 0): ?>
                            <span class="d-block mt-1">There are <strong><?= number_format((int) $openUnassigned) ?></strong> open unassigned tickets right now.</span>
                        <?php endif; ?>
                    </span>
                </label>
            </div>
            <p class="text-muted small mb-0">
                Tickets in <strong>Waiting on Customer</strong> are skipped. An owner who has turned off "a ticket is assigned to me" emails on their
                profile doesn't get these either. Wording is under <a href="/admin/settings/email-templates?tab=ticket_overdue">Email Templates</a>.
            </p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-semibold"><i class="bi bi-funnel me-2"></i>Coverage</h6>
        </div>
        <div class="card-body p-4">
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="ticket_reminders_scope" id="ticket_reminders_scope_new" value="new" <?= $scope === 'new' ? 'checked' : '' ?>>
                <label class="form-check-label" for="ticket_reminders_scope_new">
                    <span class="fw-semibold">New tickets only</span> &mdash; only tickets created after this is saved get reminders. The existing backlog is left alone.
                    <?php if ($scope === 'new' && $scopeCutoff !== ''): ?>
                        <span class="d-block text-muted small mt-1">Covering tickets created after <strong><?= e($scopeCutoff) ?></strong>. Saving again keeps this date.</span>
                    <?php else: ?>
                        <span class="d-block text-muted small mt-1">The start date is set when you save. Until then no reminders go out.</span>
                    <?php endif; ?>
                </label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="ticket_reminders_scope" id="ticket_reminders_scope_all" value="all" <?= $scope === 'all' ? 'checked' : '' ?>>
                <label class="form-check-label" for="ticket_reminders_scope_all">
                    <span class="fw-semibold">All open tickets</span> &mdash; including ones created before reminders were turned on.
                    <span class="d-block text-warning-emphasis small mt-1"><i class="bi bi-exclamation-triangle me-1"></i>The first run emails about every open ticket that's already overdue or quiet. On an old backlog that can be a lot of email at once.</span>
                </label>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
            <i class="bi bi-check-lg me-1"></i>Save Settings
        </button>
        <span class="text-muted small">
            Reminders run with the SLA job every 5 minutes, so no extra cron line is needed. To preview what would be sent, use
            <a href="/admin/settings/cron-jobs">Cron Jobs</a> &rarr; SLA Recalculation &rarr; Dry run.
        </span>
    </div>
</form>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

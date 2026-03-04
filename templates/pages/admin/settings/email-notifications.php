<?php
$layout       = 'app';
$pageTitle    = 'Email Notifications';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Email Notifications'],
];

$on = function (string $key) use ($settings): string {
    return ($settings[$key] ?? '1') === '1' ? 'checked' : '';
};
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<form method="POST" action="/admin/settings/email-notifications">
    <?= csrfField() ?>

    <!-- Agent Notifications -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-person-badge me-2"></i>Agent Notifications</h5>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">

                <li class="list-group-item px-4 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">New Ticket Created</div>
                            <div class="text-muted" style="font-size:.875rem;">Notify group members when a new ticket is created in a group that has alerts enabled.</div>
                        </div>
                        <div class="form-check form-switch ms-4 mt-1">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="agent_new_ticket" name="agent_new_ticket"
                                   <?= $on('agent_new_ticket') ?>>
                        </div>
                    </div>
                </li>

                <li class="list-group-item px-4 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">Ticket Assigned to Group</div>
                            <div class="text-muted" style="font-size:.875rem;">Notify group members when a ticket is assigned to their group.</div>
                        </div>
                        <div class="form-check form-switch ms-4 mt-1">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="agent_assigned_group" name="agent_assigned_group"
                                   <?= $on('agent_assigned_group') ?>>
                        </div>
                    </div>
                </li>

                <li class="list-group-item px-4 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">Ticket Assigned to Agent</div>
                            <div class="text-muted" style="font-size:.875rem;">Notify an agent when a ticket is assigned directly to them.</div>
                        </div>
                        <div class="form-check form-switch ms-4 mt-1">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="agent_assigned_agent" name="agent_assigned_agent"
                                   <?= $on('agent_assigned_agent') ?>>
                        </div>
                    </div>
                </li>

                <li class="list-group-item px-4 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">Requester Replies to Ticket</div>
                            <div class="text-muted" style="font-size:.875rem;">Notify the assigned agent when a portal user adds a comment to their ticket.</div>
                        </div>
                        <div class="form-check form-switch ms-4 mt-1">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="agent_requester_reply" name="agent_requester_reply"
                                   <?= $on('agent_requester_reply') ?>>
                        </div>
                    </div>
                </li>

                <li class="list-group-item px-4 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">Note Added to Ticket</div>
                            <div class="text-muted" style="font-size:.875rem;">Notify the assigned agent when an internal note is added to a ticket assigned to them.</div>
                        </div>
                        <div class="form-check form-switch ms-4 mt-1">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="agent_note_added" name="agent_note_added"
                                   <?= $on('agent_note_added') ?>>
                        </div>
                    </div>
                </li>

            </ul>
        </div>
    </div>

    <!-- Ticket Requester Notifications -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-person me-2"></i>Ticket Requester Notifications</h5>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">

                <li class="list-group-item px-4 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">New Ticket Created (Confirmation)</div>
                            <div class="text-muted" style="font-size:.875rem;">Send the ticket creator a confirmation email when their ticket is submitted.</div>
                        </div>
                        <div class="form-check form-switch ms-4 mt-1">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="requester_new_ticket" name="requester_new_ticket"
                                   <?= $on('requester_new_ticket') ?>>
                        </div>
                    </div>
                </li>

                <li class="list-group-item px-4 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">Agent Adds Comment to Ticket</div>
                            <div class="text-muted" style="font-size:.875rem;">Notify the ticket creator when an agent adds a public comment.</div>
                        </div>
                        <div class="form-check form-switch ms-4 mt-1">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="requester_agent_comment" name="requester_agent_comment"
                                   <?= $on('requester_agent_comment') ?>>
                        </div>
                    </div>
                </li>

                <li class="list-group-item px-4 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">Ticket Resolved</div>
                            <div class="text-muted" style="font-size:.875rem;">Notify the ticket creator when their ticket is marked as resolved.</div>
                        </div>
                        <div class="form-check form-switch ms-4 mt-1">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="requester_ticket_resolved" name="requester_ticket_resolved"
                                   <?= $on('requester_ticket_resolved') ?>>
                        </div>
                    </div>
                </li>

                <li class="list-group-item px-4 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">Ticket Closed</div>
                            <div class="text-muted" style="font-size:.875rem;">Notify the ticket creator when their ticket is marked as closed.</div>
                        </div>
                        <div class="form-check form-switch ms-4 mt-1">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="requester_ticket_closed" name="requester_ticket_closed"
                                   <?= $on('requester_ticket_closed') ?>>
                        </div>
                    </div>
                </li>

            </ul>
        </div>
    </div>

    <!-- CC Notifications -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-semibold"><i class="bi bi-envelope me-2"></i>CC Notifications</h5>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">

                <li class="list-group-item px-4 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">New Ticket Created</div>
                            <div class="text-muted" style="font-size:.875rem;">Notify CC'd users when a new ticket is submitted.</div>
                        </div>
                        <div class="form-check form-switch ms-4 mt-1">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="cc_new_ticket" name="cc_new_ticket"
                                   <?= $on('cc_new_ticket') ?>>
                        </div>
                    </div>
                </li>

                <li class="list-group-item px-4 py-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold">Comment Added to Ticket</div>
                            <div class="text-muted" style="font-size:.875rem;">Notify CC'd users when a comment is added to the ticket.</div>
                        </div>
                        <div class="form-check form-switch ms-4 mt-1">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   id="cc_note_added" name="cc_note_added"
                                   <?= $on('cc_note_added') ?>>
                        </div>
                    </div>
                </li>

            </ul>
        </div>
    </div>

    <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
        <i class="bi bi-check-lg me-1"></i>Save Settings
    </button>
</form>

<?php
/**
 * Email-notification opt-out switches, shared by My Profile and the admin user
 * edit page. Expects $notifyUser (a users row) and $notifyRole (its role slug).
 * Ends with a `_notify_fields` list so saveNotifyPrefsFromPost() only writes the
 * columns actually shown for this role and never zeroes a hidden one.
 */
if (roleIsStaff($notifyRole)) {
    $notifyGroups = [
        'Agent Notifications' => [
            ['key' => 'notify_group_new_ticket',  'icon' => 'bi-people',               'label' => 'New ticket in my group',      'desc' => 'Email when a new ticket is submitted in a group you belong to.'],
            ['key' => 'notify_assigned_to_group', 'icon' => 'bi-people-fill',           'label' => 'Ticket assigned to my group', 'desc' => 'Email when a ticket is assigned to a group you belong to.'],
            ['key' => 'notify_assigned_to_me',    'icon' => 'bi-person-check',          'label' => 'Ticket assigned to me',       'desc' => 'Email when a ticket is assigned directly to you.'],
            ['key' => 'notify_requester_replied', 'icon' => 'bi-chat-left-text',        'label' => 'Requester replies',           'desc' => 'Email when a portal user replies to a ticket assigned to you.'],
            ['key' => 'notify_note_added',        'icon' => 'bi-sticky',                'label' => 'Internal note added',         'desc' => 'Email when an internal note is added to a ticket assigned to you.'],
            ['key' => 'notify_mention',           'icon' => 'bi-at',                    'label' => '@Mentions',                   'desc' => 'Email when someone @mentions you in a ticket reply or note.'],
        ],
        'Other Notifications' => [
            ['key' => 'notify_ticket_cc',         'icon' => 'bi-person-lines-fill',    'label' => 'CC updates',              'desc' => "Email when a ticket you're CC'd on receives a reply."],
            ['key' => 'notify_escalation',        'icon' => 'bi-exclamation-triangle', 'label' => 'Escalation alerts',       'desc' => 'Email when an escalation rule fires and targets you.'],
            ['key' => 'notify_ticket_assigned',   'icon' => 'bi-person-check',         'label' => 'My ticket assigned',      'desc' => 'Email when a ticket you submitted is assigned to an agent, letting you know who is handling it.'],
        ],
        // Staff submit tickets too — these mirror the portal-user requester
        // notifications for tickets the agent/admin opens.
        'Tickets You Submit' => [
            ['key' => 'notify_ticket_created',    'icon' => 'bi-ticket-detailed',      'label' => 'Ticket submitted',        'desc' => 'Confirmation email when you open a new ticket.'],
            ['key' => 'notify_ticket_updated',    'icon' => 'bi-chat-left-text',       'label' => 'Agent replies',           'desc' => 'Email when an agent replies to a ticket you submitted.'],
            ['key' => 'notify_ticket_merged',     'icon' => 'bi-arrows-collapse',      'label' => 'Ticket merged',           'desc' => 'Email when a ticket you submitted is merged into another.'],
            ['key' => 'notify_ticket_solved',     'icon' => 'bi-check-circle',         'label' => 'Ticket resolved',         'desc' => 'Email when a ticket you submitted is marked as resolved.'],
            ['key' => 'notify_ticket_closed',     'icon' => 'bi-x-circle',             'label' => 'Ticket closed',           'desc' => 'Email when a ticket you submitted is marked as closed.'],
            ['key' => 'notify_csat',              'icon' => 'bi-star',                 'label' => 'Satisfaction surveys',    'desc' => 'Survey email after a ticket you submitted is resolved.'],
        ],
    ];
} else {
    $notifyGroups = [
        '' => [
            ['key' => 'notify_ticket_created',    'icon' => 'bi-ticket-detailed',     'label' => 'Ticket submitted',       'desc' => 'Confirmation email when you open a new ticket.'],
            ['key' => 'notify_ticket_assigned',   'icon' => 'bi-person-check',         'label' => 'Ticket assigned',        'desc' => 'Email when one of your tickets is assigned to an agent, letting you know who is handling it.'],
            ['key' => 'notify_ticket_updated',    'icon' => 'bi-chat-left-text',       'label' => 'Agent replies',          'desc' => 'Email when an agent replies to one of your tickets.'],
            ['key' => 'notify_ticket_cc',         'icon' => 'bi-person-lines-fill',    'label' => 'CC updates',             'desc' => "Email when a ticket you're CC'd on receives a reply."],
            ['key' => 'notify_ticket_merged',     'icon' => 'bi-arrows-collapse',      'label' => 'Ticket merged',          'desc' => 'Email when one of your tickets is merged into another.'],
            ['key' => 'notify_ticket_solved',     'icon' => 'bi-check-circle',         'label' => 'Ticket resolved',        'desc' => 'Email when your ticket is marked as resolved.'],
            ['key' => 'notify_ticket_closed',     'icon' => 'bi-x-circle',             'label' => 'Ticket closed',          'desc' => 'Email when your ticket is marked as closed.'],
            ['key' => 'notify_csat',              'icon' => 'bi-star',                 'label' => 'Satisfaction surveys',   'desc' => 'Survey email after your ticket is resolved.'],
        ],
    ];
}

$notifyKeys = [];
foreach ($notifyGroups as $groupLabel => $opts):
    if ($groupLabel !== ''): ?>
        <p class="text-muted small fw-semibold mb-1 <?= $notifyKeys ? 'mt-3' : 'mt-1' ?>"><?= $groupLabel ?></p>
    <?php endif;
    foreach ($opts as $opt):
        $notifyKeys[] = $opt['key'];
        $checked = ($notifyUser[$opt['key']] ?? 1) ? 'checked' : ''; ?>
        <div class="d-flex align-items-start gap-3 py-2 border-bottom">
            <div class="form-check form-switch mb-0 pt-1">
                <input class="form-check-input" type="checkbox"
                       name="<?= $opt['key'] ?>" id="<?= $opt['key'] ?>" value="1" <?= $checked ?>>
            </div>
            <label class="mb-0 flex-grow-1" for="<?= $opt['key'] ?>" style="cursor:pointer;">
                <div class="fw-semibold small">
                    <i class="bi <?= $opt['icon'] ?> me-1 text-muted"></i><?= $opt['label'] ?>
                </div>
                <div class="text-muted" style="font-size:.8rem;"><?= $opt['desc'] ?></div>
            </label>
        </div>
    <?php endforeach;
endforeach; ?>
<input type="hidden" name="_notify_fields" value="<?= e(implode(',', $notifyKeys)) ?>">

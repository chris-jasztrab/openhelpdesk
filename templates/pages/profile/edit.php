<?php
$layout      = 'app';
$pageTitle   = 'My Profile';
$breadcrumbs = [
    ['label' => 'My Profile'],
];
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex align-items-center gap-3 mb-4">
            <div class="rounded-circle bg-light text-dark d-flex align-items-center justify-content-center"
                 style="width:56px;height:56px;font-size:1.25rem;font-weight:600;border:2px solid var(--ld-primary);">
                <?= Auth::initials() ?>
            </div>
            <div>
                <h2 class="fw-bold mb-0">My Profile</h2>
                <span class="text-muted small"><?= e($user['email']) ?></span>
                <span class="badge bg-primary ms-1"><?= e(ucfirst($user['role'])) ?></span>
            </div>
        </div>

        <!-- Main profile form: name, appearance, notifications (no password fields) -->
        <form method="POST" action="/profile">
            <?= csrfField() ?>

            <!-- Name -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-person me-1"></i>Personal Information
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="first_name"
                                   value="<?= e(old('first_name', $user['first_name'])) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="last_name"
                                   value="<?= e(old('last_name', $user['last_name'])) ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (in_array($user['role'], ['agent', 'admin', 'power_user'], true)): ?>
            <!-- Availability (drives "First Available" group auto-assign) -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-toggle-on me-1"></i>Availability
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="is_available" name="is_available" value="1"
                               <?= !empty($profileUser['is_available'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="is_available">
                            I'm available for new tickets
                        </label>
                    </div>
                    <div class="form-text">
                        When this is on, groups configured for the <strong>First Available</strong> auto-assign strategy can route new tickets to you. Turn it off when you're on break, in a meeting, or out of office. Direct assignments and the other strategies (round-robin, load-based, skill-based) are unaffected.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Appearance -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-moon-stars me-1"></i>Appearance
                </div>
                <div class="card-body">
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="theme" id="themeLight"
                                   value="light" <?= ($theme ?? 'light') === 'light' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="themeLight">
                                <i class="bi bi-sun me-1"></i>Light Mode
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="theme" id="themeDark"
                                   value="dark" <?= ($theme ?? 'light') === 'dark' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="themeDark">
                                <i class="bi bi-moon me-1"></i>Dark Mode
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notification Preferences -->
            <div id="tour-portal-notifications" class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-bell me-1"></i>Email Notifications
                </div>
                <div class="card-body pb-1">
                    <p class="text-muted small mb-3">Choose which events you want to receive emails about.</p>
                    <?php
                    $isAgent = in_array($user['role'], ['agent', 'admin'], true);

                    if ($isAgent):
                        $agentOptions = [
                            ['key' => 'notify_group_new_ticket',  'icon' => 'bi-people',               'label' => 'New ticket in my group',      'desc' => 'Email when a new ticket is submitted in a group you belong to.'],
                            ['key' => 'notify_assigned_to_group', 'icon' => 'bi-people-fill',           'label' => 'Ticket assigned to my group', 'desc' => 'Email when a ticket is assigned to a group you belong to.'],
                            ['key' => 'notify_assigned_to_me',    'icon' => 'bi-person-check',          'label' => 'Ticket assigned to me',       'desc' => 'Email when a ticket is assigned directly to you.'],
                            ['key' => 'notify_requester_replied', 'icon' => 'bi-chat-left-text',        'label' => 'Requester replies',           'desc' => 'Email when a portal user replies to a ticket assigned to you.'],
                            ['key' => 'notify_note_added',        'icon' => 'bi-sticky',                'label' => 'Internal note added',         'desc' => 'Email when an internal note is added to a ticket assigned to you.'],
                        ];
                        $otherOptions = [
                            ['key' => 'notify_ticket_cc',         'icon' => 'bi-person-lines-fill',    'label' => 'CC updates',              'desc' => "Email when a ticket you're CC'd on receives a reply."],
                            ['key' => 'notify_escalation',        'icon' => 'bi-exclamation-triangle', 'label' => 'Escalation alerts',       'desc' => 'Email when an escalation rule fires and targets you.'],
                            ['key' => 'notify_ticket_assigned',   'icon' => 'bi-person-check',         'label' => 'My ticket assigned',      'desc' => 'Email when a ticket you submitted is assigned to an agent, letting you know who is handling it.'],
                        ];
                    ?>
                        <p class="text-muted small fw-semibold mb-1 mt-1">Agent Notifications</p>
                        <?php foreach ($agentOptions as $opt): $checked = ($profileUser[$opt['key']] ?? 1) ? 'checked' : ''; ?>
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
                        <?php endforeach; ?>

                        <p class="text-muted small fw-semibold mb-1 mt-3">Other Notifications</p>
                        <?php foreach ($otherOptions as $opt): $checked = ($profileUser[$opt['key']] ?? 1) ? 'checked' : ''; ?>
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
                        <?php endforeach; ?>

                    <?php else: // portal user
                        $portalOptions = [
                            ['key' => 'notify_ticket_created',    'icon' => 'bi-ticket-detailed',     'label' => 'Ticket submitted',       'desc' => 'Confirmation email when you open a new ticket.'],
                            ['key' => 'notify_ticket_assigned',   'icon' => 'bi-person-check',         'label' => 'Ticket assigned',        'desc' => 'Email when one of your tickets is assigned to an agent, letting you know who is handling it.'],
                            ['key' => 'notify_ticket_updated',    'icon' => 'bi-chat-left-text',       'label' => 'Agent replies',          'desc' => 'Email when an agent replies to one of your tickets.'],
                            ['key' => 'notify_ticket_cc',         'icon' => 'bi-person-lines-fill',    'label' => 'CC updates',             'desc' => "Email when a ticket you're CC'd on receives a reply."],
                            ['key' => 'notify_ticket_merged',     'icon' => 'bi-arrows-collapse',      'label' => 'Ticket merged',          'desc' => 'Email when one of your tickets is merged into another.'],
                            ['key' => 'notify_ticket_solved',     'icon' => 'bi-check-circle',         'label' => 'Ticket resolved',        'desc' => 'Email when your ticket is marked as resolved.'],
                            ['key' => 'notify_ticket_closed',     'icon' => 'bi-x-circle',             'label' => 'Ticket closed',          'desc' => 'Email when your ticket is marked as closed.'],
                            ['key' => 'notify_csat',              'icon' => 'bi-star',                 'label' => 'Satisfaction surveys',   'desc' => 'Survey email after your ticket is resolved.'],
                        ];
                        foreach ($portalOptions as $opt): $checked = ($profileUser[$opt['key']] ?? 1) ? 'checked' : ''; ?>
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
                    endif; ?>
                </div>
            </div>

            <button type="submit" class="btn text-white px-4" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Changes
            </button>
        </form>

        <!-- Separate form for password change to prevent autofill interference -->
        <form method="POST" action="/profile/password" class="mt-4">
            <?= csrfField() ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-shield-lock me-1"></i>Change Password
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Leave blank to keep your current password.</p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="currentPassword" name="current_password"
                                   autocomplete="current-password">
                        </div>
                        <div class="col-md-6">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="newPassword" name="new_password"
                                   minlength="8" autocomplete="new-password">
                            <div class="form-text">Minimum 8 characters.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirmPassword" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password"
                                   autocomplete="new-password">
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-outline-secondary px-4">
                <i class="bi bi-key me-1"></i>Update Password
            </button>
        </form>

        <?php if (in_array($user['role'], ['admin', 'agent'], true)): ?>
        <!-- Two-Factor Authentication -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-shield-lock me-1"></i>Two-Factor Authentication
            </div>
            <div class="card-body">
                <?php if ($profileUser['totp_enabled'] ?? false): ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="badge bg-success fs-6 px-3 py-2">
                            <i class="bi bi-shield-check me-1"></i>2FA is enabled
                        </span>
                    </div>
                    <p class="text-muted small mb-3">
                        Your account is protected with a time-based one-time password (TOTP) authenticator app.
                        To disable 2FA, enter the 6-digit code from your authenticator app below.
                    </p>
                    <form method="POST" action="/profile/2fa/disable">
                        <?= csrfField() ?>
                        <div class="d-flex gap-2 align-items-end">
                            <div>
                                <label for="disableCode" class="form-label small fw-semibold mb-1">
                                    Authenticator Code
                                </label>
                                <input type="text" class="form-control text-center fw-bold"
                                       id="disableCode" name="code"
                                       placeholder="000000"
                                       maxlength="6"
                                       inputmode="numeric"
                                       pattern="\d{6}"
                                       autocomplete="one-time-code"
                                       required
                                       style="letter-spacing:.3em;max-width:140px;">
                            </div>
                            <button type="button" class="btn btn-outline-danger"
                                    data-bs-toggle="modal" data-bs-target="#disable2faModal">
                                <i class="bi bi-shield-x me-1"></i>Disable 2FA
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="text-muted small mb-3">
                        Add an extra layer of security to your account by enabling two-factor authentication.
                        You will be prompted for a 6-digit code from your authenticator app each time you sign in.
                    </p>
                    <a href="/profile/2fa/setup" class="btn text-white" style="background:var(--ld-primary);">
                        <i class="bi bi-shield-plus me-1"></i>Set Up 2FA
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Disable 2FA Confirmation Modal -->
<div class="modal fade" id="disable2faModal" tabindex="-1" aria-labelledby="disable2faModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="disable2faModalLabel">
                    <i class="bi bi-shield-x me-2 text-danger"></i>Disable 2FA
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to disable two-factor authentication? Your account will be less secure.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4" id="confirmDisable2fa">
                    <i class="bi bi-shield-x me-1"></i>Disable 2FA
                </button>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('confirmDisable2fa').addEventListener('click', function () {
    document.getElementById('disableCode').closest('form').submit();
});
</script>

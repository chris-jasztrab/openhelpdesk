<?php
$layout       = 'app';
$pageTitle    = 'Escalation Paths';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Escalation Paths'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="mb-4">
    <h5 class="fw-semibold mb-1"><i class="bi bi-signpost-split me-2"></i>Escalation Paths</h5>
    <p class="text-muted mb-0" style="font-size:.875rem;">
        Define a manual escalation chain per ticket type. When an agent clicks
        <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-arrow-up-circle me-1"></i>Escalate</span>
        on a ticket, it will be reassigned to the next person in this chain. Distinct from
        <a href="/admin/settings/escalations">Escalation Rules</a>, which fire automatically based on SLA and time.
    </p>
</div>

<div class="card border-0 shadow-sm mb-4" id="escalate_button_visibility">
    <div class="card-body p-4">
        <h6 class="fw-semibold mb-1"><i class="bi bi-eye me-2"></i>Escalate Button Visibility</h6>
        <p class="text-muted mb-3" style="font-size:.875rem;">
            Choose when the <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-arrow-up-circle me-1"></i>Escalate</span>
            button appears on tickets that have an escalation path.
        </p>
        <form method="POST" action="/admin/settings/escalation-paths/visibility">
            <?= csrfField() ?>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="escalate_button_visibility"
                       id="visAlways" value="always" <?= ($visibility ?? 'always') !== 'breached' ? 'checked' : '' ?>>
                <label class="form-check-label" for="visAlways">
                    <span class="fw-semibold">Always show</span>
                    <span class="text-muted d-block" style="font-size:.825rem;">Show the button on every ticket with an escalation path (default).</span>
                </label>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="radio" name="escalate_button_visibility"
                       id="visBreached" value="breached" <?= ($visibility ?? 'always') === 'breached' ? 'checked' : '' ?>>
                <label class="form-check-label" for="visBreached">
                    <span class="fw-semibold">Only after SLA breach</span>
                    <span class="text-muted d-block" style="font-size:.825rem;">
                        Hide the button until the ticket's SLA timer has breached.
                        <?php if (!slaEnabled()): ?>
                        <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>SLA tracking is currently off &mdash; the button will always show until you enable it.</span>
                        <?php endif; ?>
                    </span>
                </label>
            </div>
            <button type="submit" class="btn btn-sm text-white" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save
            </button>
        </form>
    </div>
</div>

<?php if (empty($types)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-tags fs-1 d-block mb-3 opacity-25"></i>
        <p class="mb-3">No ticket types exist yet. Create some in <a href="/admin/types">Ticket Types</a> first.</p>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Ticket Type</th>
                    <th style="width:140px;">Steps Configured</th>
                    <th style="width:140px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($types as $t): ?>
                <tr>
                    <td>
                        <span class="badge" style="background:<?= e($t['color'] ?: '#6c757d') ?>;">
                            <?= e($t['name']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ((int) $t['step_count'] > 0): ?>
                        <span class="text-success"><i class="bi bi-check-circle me-1"></i><?= (int) $t['step_count'] ?> step<?= (int) $t['step_count'] === 1 ? '' : 's' ?></span>
                        <?php else: ?>
                        <span class="text-muted"><i class="bi bi-dash-circle me-1"></i>None</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/admin/settings/escalation-paths/<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil me-1"></i>Configure
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

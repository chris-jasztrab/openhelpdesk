<?php
$layout       = 'app';
$pageTitle    = 'Ticket Routing';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = Auth::isAdmin()
    ? [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Settings', 'url' => '/admin/settings'], ['label' => 'Ticket Routing']]
    : [['label' => 'Settings', 'url' => '/admin/settings'], ['label' => 'Ticket Routing']];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Ticket Routing</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

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
            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="agents_assign_any_group" name="agents_assign_any_group" value="1"
                           <?= ($settings['agents_assign_any_group'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="agents_assign_any_group">
                        Allow agents to assign tickets to groups they're not part of
                    </label>
                </div>
                <div class="form-text">
                    When off, the group picker on the agent ticket list only offers the groups an agent
                    belongs to. Turn this on to let agents move tickets into <strong>any</strong> group.
                    Admins always see every group.
                </div>
            </div>
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

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

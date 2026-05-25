<?php
$layout       = 'app';
$pageTitle    = 'SLA Policies – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'SLA Policies'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<!-- SLA master toggle -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form method="POST" action="/admin/settings/sla-toggle" class="d-flex justify-content-between align-items-start gap-3">
            <?= csrfField() ?>
            <div>
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="sla_enabled" id="sla_enabled" value="1"
                           <?= slaEnabled() ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="sla_enabled">Enable SLA tracking</label>
                </div>
                <p class="text-muted small mb-0">
                    When disabled, SLA timers are not started, recalculated, or shown anywhere &mdash;
                    on tickets, ticket lists, or reports. Existing policies and past SLA data are kept,
                    so you can re-enable at any time.
                </p>
            </div>
            <button type="submit" class="btn btn-outline-secondary flex-shrink-0">
                <i class="bi bi-check-lg me-1"></i>Save
            </button>
        </form>
    </div>
</div>

<?php if (!slaEnabled()): ?>
<div class="alert alert-warning d-flex align-items-center">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <div>SLA tracking is currently <strong>disabled</strong>. The policies below are saved but not applied to tickets.</div>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1">SLA Policies</h5>
        <p class="text-muted mb-0">Define response and resolution targets per ticket type and priority. Times are in business minutes.</p>
    </div>
    <button type="button" class="btn btn-outline-secondary"
            data-bs-toggle="modal" data-bs-target="#recalcSlaModal">
        <i class="bi bi-arrow-clockwise me-1"></i>Recalculate All
    </button>
</div>

<?php if (empty($priorities)): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>No priorities configured yet. <a href="/admin/priorities/create">Create a priority</a> first, then configure SLA policies.
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="/admin/settings/sla-policies">
            <?= csrfField() ?>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="sla-tab-default" data-bs-toggle="tab" data-bs-target="#sla-pane-default"
                            type="button" role="tab" aria-controls="sla-pane-default" aria-selected="true">
                        Default
                    </button>
                </li>
                <?php foreach ($types as $type): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sla-tab-<?= $type['id'] ?>" data-bs-toggle="tab" data-bs-target="#sla-pane-<?= $type['id'] ?>"
                            type="button" role="tab" aria-controls="sla-pane-<?= $type['id'] ?>" aria-selected="false">
                        <span class="badge badge-vivid me-1" style="background:<?= e($type['color']) ?>;">&nbsp;</span><?= e($type['name']) ?>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>

            <div class="tab-content">
                <!-- Default tab -->
                <div class="tab-pane fade show active" id="sla-pane-default" role="tabpanel" aria-labelledby="sla-tab-default">
                    <p class="text-muted small mb-3">These are the fallback SLA targets used when no type-specific policy is defined.</p>
                    <?php renderSlaPriorityTable($priorities, $policies[0] ?? [], 0); ?>
                </div>

                <!-- Per-type tabs -->
                <?php foreach ($types as $type): ?>
                <div class="tab-pane fade" id="sla-pane-<?= $type['id'] ?>" role="tabpanel" aria-labelledby="sla-tab-<?= $type['id'] ?>">
                    <p class="text-muted small mb-3">Override SLA targets for <strong><?= e($type['name']) ?></strong> tickets. Leave at 0 to use the default policy.</p>
                    <?php renderSlaPriorityTable($priorities, $policies[(int) $type['id']] ?? [], (int) $type['id'], $policies[0] ?? []); ?>
                </div>
                <?php endforeach; ?>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between align-items-center">
                <div class="form-text">
                    Set both values to 0 (or leave empty) to disable SLA for a priority. Type-specific values of 0 inherit the default. Times count only business hours.
                </div>
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i>Save Policies
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
/**
 * Render the priority grid table for a given type tab.
 *
 * @param array    $priorities   All priorities
 * @param array    $typePolicies Policies for this type, keyed by priority_id
 * @param int      $typeKey      0 for default, or type_id
 * @param array    $defaults     Default policies (for showing inheritance hints), empty for the default tab
 */
function renderSlaPriorityTable(array $priorities, array $typePolicies, int $typeKey, array $defaults = []): void {
    $isDefault = $typeKey === 0;
?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Priority</th>
                    <th style="width:200px">First Response (minutes)</th>
                    <th style="width:200px">Resolution (minutes)</th>
                    <th style="width:200px">Human-readable</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($priorities as $pri): ?>
                <?php
                $policy = $typePolicies[(int) $pri['id']] ?? null;
                $frMin = $policy ? (int) $policy['first_response_minutes'] : 0;
                $resMin = $policy ? (int) $policy['resolution_minutes'] : 0;
                $defPolicy = $defaults[(int) $pri['id']] ?? null;
                $defFr = $defPolicy ? (int) $defPolicy['first_response_minutes'] : 0;
                $defRes = $defPolicy ? (int) $defPolicy['resolution_minutes'] : 0;
                $uid = $typeKey . '_' . $pri['id'];
                ?>
                <tr>
                    <td>
                        <span class="badge" style="background:<?= e($pri['color']) ?>;"><?= e($pri['name']) ?></span>
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm sla-input"
                               name="policies[<?= $typeKey ?>][<?= $pri['id'] ?>][first_response_minutes]"
                               value="<?= $frMin ?>" min="0"
                               placeholder="<?= !$isDefault && $defFr > 0 ? e($defFr) : 'e.g. 60' ?>"
                               data-target="fr_<?= $uid ?>"
                               data-type-key="<?= $typeKey ?>"
                               data-default-val="<?= $defFr ?>">
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm sla-input"
                               name="policies[<?= $typeKey ?>][<?= $pri['id'] ?>][resolution_minutes]"
                               value="<?= $resMin ?>" min="0"
                               placeholder="<?= !$isDefault && $defRes > 0 ? e($defRes) : 'e.g. 480' ?>"
                               data-target="res_<?= $uid ?>"
                               data-type-key="<?= $typeKey ?>"
                               data-default-val="<?= $defRes ?>">
                    </td>
                    <td class="text-muted small">
                        <?php if (!$isDefault && $frMin === 0 && $resMin === 0 && ($defFr > 0 || $defRes > 0)): ?>
                            <span id="fr_<?= $uid ?>" class="text-info"><?= $defFr > 0 ? formatMinutes($defFr) : '—' ?></span> /
                            <span id="res_<?= $uid ?>" class="text-info"><?= $defRes > 0 ? formatMinutes($defRes) : '—' ?></span>
                            <span class="badge bg-info bg-opacity-10 text-info ms-1">default</span>
                        <?php else: ?>
                            <span id="fr_<?= $uid ?>"><?= $frMin > 0 ? formatMinutes($frMin) : '—' ?></span> /
                            <span id="res_<?= $uid ?>"><?= $resMin > 0 ? formatMinutes($resMin) : '—' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<script>
function formatMins(m) {
    m = parseInt(m) || 0;
    if (m <= 0) return '—';
    if (m < 60) return m + 'm';
    var h = Math.floor(m / 60);
    var r = m % 60;
    if (h < 24) return h + 'h' + (r > 0 ? ' ' + r + 'm' : '');
    var d = Math.floor(h / 8); // 8 business hours per day
    var rh = h % 8;
    return d + 'd' + (rh > 0 ? ' ' + rh + 'h' : '') + (r > 0 ? ' ' + r + 'm' : '');
}
document.querySelectorAll('.sla-input').forEach(function(input) {
    input.addEventListener('input', function() {
        var target = document.getElementById(this.dataset.target);
        if (!target) return;
        var val = parseInt(this.value) || 0;
        var typeKey = this.dataset.typeKey;
        var defVal = parseInt(this.dataset.defaultVal) || 0;

        if (val > 0) {
            target.textContent = formatMins(val);
            target.className = 'text-muted small';
        } else if (typeKey !== '0' && defVal > 0) {
            target.textContent = formatMins(defVal);
            target.className = 'text-info';
        } else {
            target.textContent = '—';
            target.className = 'text-muted small';
        }
    });
});
</script>

<!-- Recalculate SLA Modal -->
<div class="modal fade" id="recalcSlaModal" tabindex="-1" aria-labelledby="recalcSlaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="recalcSlaModalLabel">
                    <i class="bi bi-arrow-clockwise me-2"></i>Recalculate SLA
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Recalculate SLA state for all active tickets? This may take a moment.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="/admin/settings/sla-recalculate">
                    <?= csrfField() ?>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-arrow-clockwise me-1"></i>Recalculate All
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

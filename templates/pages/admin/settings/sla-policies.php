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

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-1">SLA Policies</h5>
        <p class="text-muted mb-0">Define response and resolution targets for each priority level. Times are in business minutes.</p>
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

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Priority</th>
                            <th style="width:200px">First Response (minutes)</th>
                            <th style="width:200px">Resolution (minutes)</th>
                            <th style="width:180px">Human-readable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($priorities as $pri): ?>
                        <?php
                        $policy = $policies[(int) $pri['id']] ?? null;
                        $frMin = $policy ? (int) $policy['first_response_minutes'] : 0;
                        $resMin = $policy ? (int) $policy['resolution_minutes'] : 0;
                        ?>
                        <tr>
                            <td>
                                <span class="badge" style="background:<?= e($pri['color']) ?>;"><?= e($pri['name']) ?></span>
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm sla-input"
                                       name="policies[<?= $pri['id'] ?>][first_response_minutes]"
                                       value="<?= $frMin ?>" min="0" placeholder="e.g. 60"
                                       data-target="fr_<?= $pri['id'] ?>">
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm sla-input"
                                       name="policies[<?= $pri['id'] ?>][resolution_minutes]"
                                       value="<?= $resMin ?>" min="0" placeholder="e.g. 480"
                                       data-target="res_<?= $pri['id'] ?>">
                            </td>
                            <td class="text-muted small">
                                <span id="fr_<?= $pri['id'] ?>"><?= $frMin > 0 ? formatMinutes($frMin) : '—' ?></span> /
                                <span id="res_<?= $pri['id'] ?>"><?= $resMin > 0 ? formatMinutes($resMin) : '—' ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between align-items-center">
                <div class="form-text">
                    Set both values to 0 to disable SLA for a priority. Times count only business hours.
                </div>
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i>Save Policies
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

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
        if (target) target.textContent = formatMins(this.value);
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

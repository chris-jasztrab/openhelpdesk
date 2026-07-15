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
        <p class="text-muted mb-0">Define response and resolution targets per ticket type and priority. Enter times in days, hours or minutes &mdash; type <code>d</code>, <code>h</code> or <code>m</code> (e.g. <code>90m</code>, <code>8h</code>, <code>2d</code>); a bare number means minutes. These count business time only.</p>
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
                    <?php
                    // Decode this type's business-hours override (NULL = inherit global).
                    $rawTs = $type['business_hours_schedule'] ?? null;
                    $typeSchedule = (is_string($rawTs) && trim($rawTs) !== '')
                        ? (json_decode($rawTs, true) ?: null)
                        : null;
                    renderTypeBusinessHours((int) $type['id'], is_array($typeSchedule) ? $typeSchedule : null, $globalSchedule);
                    ?>
                    <p class="text-muted small mb-3">Override SLA targets for <strong><?= e($type['name']) ?></strong> tickets. Leave at 0 to use the default policy.</p>
                    <?php renderSlaPriorityTable($priorities, $policies[(int) $type['id']] ?? [], (int) $type['id'], $policies[0] ?? []); ?>
                </div>
                <?php endforeach; ?>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between align-items-center">
                <div class="form-text">
                    Set both values to 0 (or leave empty) to disable SLA for a priority. Type-specific values of 0 inherit the default.
                    Times count only business hours, and only on the days selected under <strong>SLA counts on</strong> &mdash;
                    deselect a day (e.g. Sunday) to freeze the timer then, even if you're open that day.
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
 * Render the per-type business-hours override panel for a type tab. When the
 * toggle is off the type inherits the global business hours; when on, the SLA
 * timers for this type count only the weekly hours entered here. Timezone
 * always follows the global Business Hours setting.
 *
 * @param int         $typeId         The ticket type id
 * @param array|null  $typeSchedule   The type's stored schedule (null = inherit)
 * @param array       $globalSchedule Global schedule, used to prefill defaults
 */
function renderTypeBusinessHours(int $typeId, ?array $typeSchedule, array $globalSchedule): void {
    $days = [
        'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
        'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday',
    ];
    $enabled = $typeSchedule !== null;
    $src     = $enabled ? $typeSchedule : $globalSchedule;
?>
    <div class="border rounded p-3 mb-4">
        <div class="form-check form-switch mb-1">
            <input class="form-check-input type-hours-toggle" type="checkbox" role="switch"
                   id="th_enabled_<?= $typeId ?>" name="type_hours[<?= $typeId ?>][enabled]" value="1"
                   data-type="<?= $typeId ?>" <?= $enabled ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="th_enabled_<?= $typeId ?>">
                <i class="bi bi-clock-history me-1"></i>Use custom business hours for this type
            </label>
        </div>
        <p class="text-muted small mb-2">
            For a department that doesn't keep the branch's hours &mdash; e.g. a back-office team that
            only works 9AM&ndash;5PM. SLA timers for this type then count only the hours below.
            Timezone always follows the global <a href="/admin/settings/business-hours">Business Hours</a> setting.
            Leave off to inherit the global schedule.
        </p>
        <div class="type-hours-body" id="th_body_<?= $typeId ?>" style="<?= $enabled ? '' : 'display:none;' ?>">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" style="max-width:520px;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:120px">Day</th>
                            <th style="width:60px">Open</th>
                            <th>Start</th>
                            <th>End</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days as $key => $label):
                            $dd     = $src[$key] ?? null;
                            $active = is_array($dd) && count($dd) >= 2;
                            $startV = $active ? $dd[0] : '09:00';
                            $endV   = $active ? $dd[1] : '17:00';
                            $grp    = "th_time_{$typeId}_{$key}";
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= e($label) ?></td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input th-day-toggle" type="checkbox"
                                           name="type_hours[<?= $typeId ?>][days][<?= $key ?>][active]" value="1"
                                           id="th_<?= $typeId ?>_<?= $key ?>" data-target="<?= $grp ?>"
                                           <?= $active ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <td>
                                <input type="time" class="form-control form-control-sm th-time" data-group="<?= $grp ?>"
                                       name="type_hours[<?= $typeId ?>][days][<?= $key ?>][start]"
                                       value="<?= e($startV) ?>" <?= $active ? '' : 'disabled' ?>>
                            </td>
                            <td>
                                <input type="time" class="form-control form-control-sm th-time" data-group="<?= $grp ?>"
                                       name="type_hours[<?= $typeId ?>][days][<?= $key ?>][end]"
                                       value="<?= e($endV) ?>" <?= $active ? '' : 'disabled' ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php
}

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
                    <th style="width:170px">First Response</th>
                    <th style="width:170px">Resolution</th>
                    <th style="width:160px">Rolled up</th>
                    <th>SLA counts on</th>
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
                // Which weekdays this policy's timer counts. NULL/unset → all days.
                $counted = $policy ? Sla::parseCountedDays($policy['counted_days'] ?? null) : null;
                $dayLabels = ['mon' => 'M', 'tue' => 'T', 'wed' => 'W', 'thu' => 'T', 'fri' => 'F', 'sat' => 'S', 'sun' => 'S'];
                $dayNames  = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
                ?>
                <tr>
                    <td>
                        <span class="badge" style="background:<?= e($pri['color']) ?>;"><?= e($pri['name']) ?></span>
                    </td>
                    <td>
                        <input type="text" inputmode="text" class="form-control form-control-sm sla-input"
                               name="policies[<?= $typeKey ?>][<?= $pri['id'] ?>][first_response_minutes]"
                               value="<?= $frMin > 0 ? e(formatDuration($frMin)) : '' ?>"
                               placeholder="<?= !$isDefault && $defFr > 0 ? e(formatDuration($defFr)) : 'e.g. 1h' ?>"
                               data-target="fr_<?= $uid ?>"
                               data-type-key="<?= $typeKey ?>"
                               data-default-val="<?= $defFr ?>">
                    </td>
                    <td>
                        <input type="text" inputmode="text" class="form-control form-control-sm sla-input"
                               name="policies[<?= $typeKey ?>][<?= $pri['id'] ?>][resolution_minutes]"
                               value="<?= $resMin > 0 ? e(formatDuration($resMin)) : '' ?>"
                               placeholder="<?= !$isDefault && $defRes > 0 ? e(formatDuration($defRes)) : 'e.g. 8h' ?>"
                               data-target="res_<?= $uid ?>"
                               data-type-key="<?= $typeKey ?>"
                               data-default-val="<?= $defRes ?>">
                    </td>
                    <td class="text-muted small">
                        <?php if (!$isDefault && $frMin === 0 && $resMin === 0 && ($defFr > 0 || $defRes > 0)): ?>
                            <span id="fr_<?= $uid ?>" class="text-info"><?= $defFr > 0 ? formatDuration($defFr) : '—' ?></span> /
                            <span id="res_<?= $uid ?>" class="text-info"><?= $defRes > 0 ? formatDuration($defRes) : '—' ?></span>
                            <span class="badge bg-info bg-opacity-10 text-info ms-1">default</span>
                        <?php else: ?>
                            <span id="fr_<?= $uid ?>"><?= $frMin > 0 ? formatDuration($frMin) : '—' ?></span> /
                            <span id="res_<?= $uid ?>"><?= $resMin > 0 ? formatDuration($resMin) : '—' ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm sla-day-group" role="group" aria-label="Days this SLA counts">
                            <?php foreach ($dayLabels as $dayKey => $label): ?>
                            <?php $checked = ($counted === null) || in_array($dayKey, $counted, true); ?>
                            <input type="checkbox" class="btn-check"
                                   name="policies[<?= $typeKey ?>][<?= $pri['id'] ?>][days][<?= $dayKey ?>]" value="1"
                                   id="day_<?= $uid ?>_<?= $dayKey ?>" autocomplete="off" <?= $checked ? 'checked' : '' ?>>
                            <label class="btn btn-outline-secondary px-2 py-1" for="day_<?= $uid ?>_<?= $dayKey ?>"
                                   title="<?= e($dayNames[$dayKey]) ?>"><?= $label ?></label>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php } ?>

<script>
// Parse a typed duration ("8h", "2d", "90m", "1d 2h", bare = minutes) → minutes.
function parseDur(raw) {
    raw = String(raw).toLowerCase().trim();
    if (raw === '' || !/^(\s*\d+\s*[dhm]?\s*)+$/.test(raw)) return 0;
    var factor = { d: 1440, h: 60, m: 1 };
    var total = 0, re = /(\d+)\s*([dhm]?)/g, mt;
    while ((mt = re.exec(raw)) !== null) {
        total += parseInt(mt[1], 10) * factor[mt[2] || 'm'];
    }
    return total;
}
// Compact, calendar-based roll-up (1d = 24h): 80 → "1h 20m", 1440 → "1d".
function formatMins(m) {
    m = parseInt(m) || 0;
    if (m <= 0) return '—';
    var d = Math.floor(m / 1440);
    var h = Math.floor((m % 1440) / 60);
    var r = m % 60;
    var parts = [];
    if (d > 0) parts.push(d + 'd');
    if (h > 0) parts.push(h + 'h');
    if (r > 0) parts.push(r + 'm');
    return parts.join(' ');
}
// Per-type business-hours override: toggle the schedule table, and enable/disable
// each day's time inputs from its Open switch.
document.querySelectorAll('.type-hours-toggle').forEach(function(t) {
    t.addEventListener('change', function() {
        var body = document.getElementById('th_body_' + this.dataset.type);
        if (body) body.style.display = this.checked ? '' : 'none';
    });
});
document.querySelectorAll('.th-day-toggle').forEach(function(t) {
    t.addEventListener('change', function() {
        var group = this.dataset.target;
        document.querySelectorAll('.th-time[data-group="' + group + '"]').forEach(function(inp) {
            inp.disabled = !t.checked;
        });
    });
});
document.querySelectorAll('.sla-input').forEach(function(input) {
    input.addEventListener('input', function() {
        var target = document.getElementById(this.dataset.target);
        if (!target) return;
        var val = parseDur(this.value);
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

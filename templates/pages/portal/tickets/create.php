<?php
$layout       = 'app';
$pageTitle    = 'New Ticket';
$sidebarItems = portalSidebar('tickets');
$breadcrumbs  = [
    ['label' => 'Portal', 'url' => '/portal'],
    ['label' => 'My Tickets', 'url' => '/portal/tickets'],
    ['label' => 'New Ticket'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">New Ticket</h2>
    <a href="/portal/tickets" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="/portal/tickets/create">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="subject" class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="subject" name="subject"
                       value="<?= e(old('subject')) ?>" required
                       placeholder="Brief summary of your issue">
            </div>

            <div class="mb-3">
                <label for="description" class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                <textarea class="form-control" id="description" name="description" rows="5" required
                          placeholder="Please describe your issue in detail..."><?= e(old('description')) ?></textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="type_id" class="form-label fw-semibold">Ticket Type</label>
                    <select class="form-select" id="type_id" name="type_id">
                        <option value="">— Select type —</option>
                        <?php foreach ($types as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= old('type_id') == $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="location_id" class="form-label fw-semibold">Location</label>
                    <select class="form-select" id="location_id" name="location_id">
                        <option value="">— Select location —</option>
                        <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['id'] ?>" <?= old('location_id') == $loc['id'] ? 'selected' : '' ?>>
                            <?= e($loc['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="priority_id" class="form-label fw-semibold">Priority</label>
                    <select class="form-select" id="priority_id" name="priority_id">
                        <option value="">— Select priority —</option>
                        <?php foreach ($priorities as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= old('priority_id') == $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="assigned_to" class="form-label fw-semibold">Assign To</label>
                    <select class="form-select" id="assigned_to" name="assigned_to">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($agents as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= old('assigned_to') == $a['id'] ? 'selected' : '' ?>>
                            <?= e($a['first_name'] . ' ' . $a['last_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if (!empty($tags)): ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Tags</label>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($tags as $tag): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tags[]"
                               value="<?= $tag['id'] ?>" id="tag_<?= $tag['id'] ?>">
                        <label class="form-check-label" for="tag_<?= $tag['id'] ?>">
                            <span class="badge bg-light text-dark border">#<?= e($tag['name']) ?></span>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Hidden fields for browser/OS auto-detection -->
            <input type="hidden" id="browser_info" name="browser_info" value="">
            <input type="hidden" id="os_info" name="os_info" value="">

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:#4f46e5;">
                    <i class="bi bi-send me-1"></i>Submit Ticket
                </button>
                <a href="/portal/tickets" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-detect browser and OS info
(function() {
    var ua = navigator.userAgent;
    document.getElementById('browser_info').value = ua;

    var os = 'Unknown';
    if (ua.indexOf('Win') !== -1) os = 'Windows';
    else if (ua.indexOf('Mac') !== -1) os = 'macOS';
    else if (ua.indexOf('Linux') !== -1) os = 'Linux';
    else if (ua.indexOf('Android') !== -1) os = 'Android';
    else if (ua.indexOf('iPhone') !== -1 || ua.indexOf('iPad') !== -1) os = 'iOS';
    document.getElementById('os_info').value = os + ' (' + navigator.platform + ')';
})();
</script>

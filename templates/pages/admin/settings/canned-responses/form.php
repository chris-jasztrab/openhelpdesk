<?php
$isEdit  = !empty($editing);
$action  = $isEdit
    ? '/admin/settings/canned-responses/' . (int) $editing['id'] . '/edit'
    : '/admin/settings/canned-responses/create';

$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Canned Response' : 'New Canned Response';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Canned Responses', 'url' => '/admin/settings/canned-responses'],
    ['label' => $isEdit ? 'Edit' : 'New'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/admin/settings/canned-responses" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <h5 class="fw-semibold mb-0"><?= $isEdit ? 'Edit Canned Response' : 'New Canned Response' ?></h5>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="title" class="form-label fw-semibold">Title</label>
                <input type="text" class="form-control" id="title" name="title" required maxlength="255"
                       value="<?= e($isEdit ? $editing['title'] : old('title')) ?>"
                       placeholder="e.g. Password Reset Instructions">
                <div class="form-text">A short, searchable name for this snippet.</div>
            </div>

            <div class="mb-3">
                <label for="body" class="form-label fw-semibold">Body</label>
                <textarea class="form-control font-monospace" id="body" name="body" rows="10" required
                          placeholder="Type your reply template here…"><?= e($isEdit ? $editing['body'] : old('body')) ?></textarea>
            </div>

            <!-- Token Reference -->
            <div class="mb-4">
                <p class="small fw-semibold mb-2">Available tokens <span class="text-muted fw-normal">(click to insert at cursor)</span></p>
                <div class="d-flex flex-wrap gap-1" id="tokenBar">
                    <?php foreach ([
                        '{{customer_first_name}}' => "Customer's first name",
                        '{{customer_last_name}}'  => "Customer's last name",
                        '{{customer_full_name}}'  => "Customer's full name",
                        '{{customer_email}}'      => "Customer's email",
                        '{{ticket_id}}'           => 'Ticket ID (e.g. #42)',
                        '{{ticket_subject}}'      => 'Ticket subject',
                        '{{agent_first_name}}'    => "Replying agent's first name",
                        '{{agent_full_name}}'     => "Replying agent's full name",
                        '{{org_name}}'            => 'Organisation name',
                    ] as $token => $desc): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary token-chip"
                            data-token="<?= e($token) ?>" title="<?= e($desc) ?>">
                        <?= e($token) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mb-4" style="max-width:180px;">
                <label for="sort_order" class="form-label fw-semibold">Sort Order</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order"
                       min="0" value="<?= $isEdit ? (int) $editing['sort_order'] : (int) old('sort_order', '0') ?>">
                <div class="form-text">Lower numbers appear first.</div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Changes' : 'Create Response' ?>
                </button>
                <a href="/admin/settings/canned-responses" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<script>
document.querySelectorAll('#tokenBar .token-chip').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var ta    = document.getElementById('body');
        var token = this.dataset.token;
        var start = ta.selectionStart, end = ta.selectionEnd;
        ta.value = ta.value.substring(0, start) + token + ta.value.substring(end);
        ta.selectionStart = ta.selectionEnd = start + token.length;
        ta.focus();
    });
});
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

<?php
$layout       = 'app';
$pageTitle    = 'Ticket Types – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Ticket Types'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="fw-bold mb-0">Ticket Types</h5>
    <a href="/admin/types/create" class="btn text-white" style="background:var(--ld-primary);">
        <i class="bi bi-plus-lg me-1"></i>Add Type
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0"
               data-sortable-list data-reorder-url="/admin/types/reorder">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">Color</th>
                    <th data-sort-col="name">Name</th>
                    <th>Created</th>
                    <th style="min-width:280px">Direct Link</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($types)): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No ticket types found.</td></tr>
                <?php else: ?>
                    <?php foreach ($types as $t):
                        $directPath = '/portal/tickets/create?type_id=' . (int) $t['id'];
                    ?>
                    <tr data-id="<?= (int) $t['id'] ?>">
                        <td>
                            <span class="badge badge-vivid rounded-pill" style="background:<?= e($t['color'] ?: '#6c757d') ?>;min-width:28px;">&nbsp;</span>
                        </td>
                        <td class="fw-semibold" data-sort-value="<?= e($t['name']) ?>">
                            <span class="badge" style="background:<?= e($t['color'] ?: '#6c757d') ?>;"><?= e($t['name']) ?></span>
                            <?php if (!empty($t['is_confidential'])): ?>
                            <i class="bi bi-shield-lock text-warning ms-1" title="Confidential"></i>
                            <?php endif; ?>
                            <?php if (!empty($t['ai_route_group'])): ?>
                            <i class="bi bi-signpost-split text-info ms-1" title="AI routes to the best group (No Wrong Door)"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                        <td>
                            <div class="input-group input-group-sm direct-link-group" style="max-width:420px;">
                                <input type="text" class="form-control font-monospace direct-link-input"
                                       value="<?= e($directPath) ?>" readonly
                                       aria-label="Direct link to <?= e($t['name']) ?> ticket form"
                                       title="Click to select. Anonymous visitors are sent to login first, then bounced back here.">
                                <button type="button" class="btn btn-outline-secondary copy-link-btn"
                                        data-path="<?= e($directPath) ?>"
                                        title="Copy full URL to clipboard">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                                <a href="<?= e($directPath) ?>" target="_blank" rel="noopener"
                                   class="btn btn-outline-secondary"
                                   title="Open in a new tab">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="/admin/types/<?= $t['id'] ?>/edit" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="/admin/types/<?= $t['id'] ?>/delete" class="d-inline">
                                    <?= csrfField() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>
<?php require ROOT_DIR . '/templates/partials/sortable-list.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Click the URL input to select all — easy "copy with Ctrl+C"
    document.querySelectorAll('.direct-link-input').forEach(function (inp) {
        inp.addEventListener('focus', function () { inp.select(); });
        inp.addEventListener('click', function () { inp.select(); });
    });

    // Copy-to-clipboard with absolute URL + transient feedback
    document.querySelectorAll('.copy-link-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var path = btn.dataset.path || '';
            var absolute = window.location.origin + path;
            var done = function () {
                var icon = btn.querySelector('i');
                if (!icon) return;
                var prev = icon.className;
                icon.className = 'bi bi-check-lg text-success';
                btn.setAttribute('title', 'Copied!');
                setTimeout(function () {
                    icon.className = prev;
                    btn.setAttribute('title', 'Copy full URL to clipboard');
                }, 1400);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(absolute).then(done).catch(function () {
                    fallbackCopy(absolute); done();
                });
            } else {
                fallbackCopy(absolute); done();
            }
        });
    });

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }
});
</script>

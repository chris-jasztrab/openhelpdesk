<?php
$layout       = 'app';
$pageTitle    = 'Backup – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Backup'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<?php if (!$zipAvailable): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>ZipArchive not available.</strong> The PHP <code>zip</code> extension is required to create backups.
    Enable it in your <code>php.ini</code> (<code>extension=zip</code>) and restart your web server.
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">

        <!-- Create Backup -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-archive me-1"></i>Create Backup
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    Generates a <code>.zip</code> file containing a full SQL dump of your database
                    plus a complete snapshot of the entire website directory &mdash; source code,
                    templates, configuration, dependencies, and all uploaded files.
                    The backup is saved to <code>storage/backups/</code> on the server and can be downloaded below.
                    Large backups may take several minutes.
                </p>
                <form method="POST" action="/admin/settings/backup/create" id="createBackupForm">
                    <?= csrfField() ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="skip_attachments" value="1" id="skipAttachments">
                        <label class="form-check-label" for="skipAttachments">
                            Leave out ticket attachments
                            <span class="d-block text-muted small">Smaller and faster. Everything else is still backed up, but files attached to tickets (<code>storage/attachments/</code>) can't be restored from this backup.</span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary" <?= !$zipAvailable ? 'disabled' : '' ?>>
                        <i class="bi bi-archive me-1"></i>Create Backup Now
                    </button>
                </form>
                <div id="backupProgress" class="mt-3 d-none">
                    <div class="progress" style="height:1.5rem;" role="progressbar" aria-label="Backup progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0%</div>
                    </div>
                    <div id="backupProgressMsg" class="text-muted small mt-2" aria-live="polite">Starting backup…</div>
                </div>
            </div>
        </div>

        <!-- Existing Backups -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-folder2-open me-1"></i>Stored Backups</span>
                <span class="badge bg-secondary"><?= count($backups) ?></span>
            </div>
            <?php if (empty($backups)): ?>
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-archive fs-1 d-block mb-2"></i>
                <p class="mb-0">No backups yet. Click <strong>Create Backup Now</strong> to generate one.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Filename</th>
                            <th>Created</th>
                            <th>Size</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $b): ?>
                        <tr>
                            <td class="font-monospace small">
                                <?= e($b['name']) ?>
                                <?php if (str_contains($b['name'], '_no-attachments')): ?>
                                <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1" style="font-family:var(--bs-body-font-family);">No attachments</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= date('M j, Y g:i a', $b['created']) ?></td>
                            <td class="text-muted small"><?= number_format($b['size'] / 1048576, 1) ?> MB</td>
                            <td class="text-end">
                                <a href="/admin/settings/backup/download?file=<?= urlencode($b['name']) ?>"
                                   class="btn btn-sm btn-outline-primary me-1">
                                    <i class="bi bi-download me-1"></i>Download
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal" data-bs-target="#deleteBackupModal"
                                        data-filename="<?= e($b['name']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>
    <div class="col-lg-4">

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-info-circle me-1"></i>What's Included
            </div>
            <div class="card-body">
                <ul class="text-muted small mb-0" style="line-height:1.9;">
                    <li><strong>database.sql</strong> — full dump of all tables including tickets, users, settings, SLA policies, KB articles, and automations.</li>
                    <li><strong>website/</strong> — complete copy of the application directory: PHP source, templates, <code>config/</code>, <code>.env</code>, <code>vendor/</code>, scripts, ticket attachments, branding assets, avatars, and logs.</li>
                </ul>
                <p class="text-muted small mb-0 mt-2">
                    <em>Excluded:</em> <code>storage/backups/</code> itself (prevents recursion), plus
                    <code>storage/attachments/</code> when <strong>Leave out ticket attachments</strong> is ticked.
                </p>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Restoring
            </div>
            <div class="card-body text-muted small">
                <ol class="mb-0" style="line-height:1.9;">
                    <li>Extract the <code>.zip</code> file.</li>
                    <li>Import <code>database.sql</code> into your MySQL database:<br>
                        <code class="d-block mt-1 p-1 bg-light rounded">mysql -u user -p dbname &lt; database.sql</code>
                    </li>
                    <li>Copy the contents of <code>website/</code> to your site directory (e.g. <code>/var/www/localdesk/</code>).</li>
                    <li>Restore file ownership so the web server can read/write uploads:<br>
                        <code class="d-block mt-1 p-1 bg-light rounded">chown -R www-data:www-data /var/www/localdesk</code>
                    </li>
                    <li>Confirm <code>.env</code> still matches the target environment (DB credentials, mail, URL).</li>
                </ol>
            </div>
        </div>

        <div class="alert alert-warning small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Store backups off-server. Backups saved in <code>storage/backups/</code> are only as safe as the server itself.
        </div>

    </div>
</div>

<!-- Delete Backup Modal -->
<div class="modal fade" id="deleteBackupModal" tabindex="-1" aria-labelledby="deleteBackupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteBackupModalLabel">
                    <i class="bi bi-trash me-2 text-danger"></i>Delete Backup
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete backup <strong id="deleteBackupFilename"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="/admin/settings/backup/delete" id="deleteBackupForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="filename" id="deleteBackupFilenameInput">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('deleteBackupModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteBackupFilename').textContent = btn.dataset.filename;
    document.getElementById('deleteBackupFilenameInput').value = btn.dataset.filename;
});

// Post the backup in the background and poll the server's progress file so the
// admin sees a real bar instead of a spinning tab. Without JS the form still
// posts normally.
document.getElementById('createBackupForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this;
    var btn  = form.querySelector('button[type="submit"]');
    var wrap = document.getElementById('backupProgress');
    var track = wrap.querySelector('.progress');
    var bar  = wrap.querySelector('.progress-bar');
    var msg  = document.getElementById('backupProgressMsg');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating backup…';
    wrap.classList.remove('d-none');

    var poll = setInterval(function () {
        fetch('/admin/settings/backup/progress', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (p) {
                if (p.pct === null) return;
                bar.style.width = p.pct + '%';
                bar.textContent = p.pct + '%';
                track.setAttribute('aria-valuenow', p.pct);
                msg.textContent = p.msg;
            })
            .catch(function () {}); // a poll can land mid-write; the next one catches up
    }, 1000);

    // redirect:'manual' so the success/error flash is shown by our own page
    // load rather than being consumed by fetch following the redirect.
    fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', redirect: 'manual' })
        .then(function (r) {
            if (r.type !== 'opaqueredirect') throw new Error('HTTP ' + r.status);
            clearInterval(poll);
            bar.style.width = '100%';
            bar.textContent = '100%';
            msg.textContent = 'Finishing…';
            window.location.href = '/admin/settings/backup';
        })
        .catch(function (err) {
            clearInterval(poll);
            bar.classList.remove('progress-bar-animated');
            bar.classList.add('bg-danger');
            msg.textContent = 'Backup did not finish (' + err.message + '). Check storage/logs/php-error.log, then try again.';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-archive me-1"></i>Create Backup Now';
        });
});
</script>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

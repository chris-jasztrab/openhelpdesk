<?php
$layout       = 'app';
$pageTitle    = 'Email Queue';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin',    'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Email Queue'],
];
$mailDisabled = env('MAIL_ENABLED', 'true') === 'false';
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-envelope-paper me-2"></i>Email Queue
            <span class="badge bg-secondary ms-2"><?= (int) $total ?></span>
        </h5>
        <?php if ($total > 0): ?>
        <div class="d-flex gap-2">
            <form method="POST" action="/admin/settings/email-queue/send-all"
                  onsubmit="return confirm('Send all <?= (int) $total ?> queued email(s) now?<?= $mailDisabled ? '\n\nMAIL_ENABLED is false, but this will deliver them anyway.' : '' ?>');">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Send all now</button>
            </form>
            <form method="POST" action="/admin/settings/email-queue/flush"
                  onsubmit="return confirm('Delete all <?= (int) $total ?> queued email(s) without sending? This cannot be undone.');">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Flush queue</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if ($mailDisabled): ?>
        <div class="alert alert-warning rounded-0 border-0 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Outbound mail is disabled</strong> (<code>MAIL_ENABLED=false</code> in <code>.env</code>).
            Every notification is being added to this queue instead of sent. <em>Send all now</em> delivers them regardless.
        </div>
        <?php endif; ?>
        <?php if ($total === 0): ?>
        <p class="text-muted p-4 mb-0">The queue is empty. Emails land here when mail is disabled, SMTP is not configured, or the SMTP server rejects a send.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Queued</th>
                        <th>To</th>
                        <th>Subject</th>
                        <th>Ticket</th>
                        <th>Reason</th>
                        <th class="text-end">Attempts</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="text-nowrap small"><?= e($r['created_at']) ?></td>
                        <td class="small"><?= e($r['to_name'] !== '' && $r['to_name'] !== $r['to_email'] ? $r['to_name'] . ' <' . $r['to_email'] . '>' : $r['to_email']) ?></td>
                        <td class="small"><?= e($r['subject']) ?></td>
                        <td class="small"><?= $r['ticket_id'] ? '<a href="/agent/tickets/' . (int) $r['ticket_id'] . '">#' . (int) $r['ticket_id'] . '</a>' : '&mdash;' ?></td>
                        <td class="small text-muted">
                            <?= e($r['reason']) ?>
                            <?php if ($r['last_error']): ?><br><span class="text-danger"><?= e($r['last_error']) ?></span><?php endif; ?>
                        </td>
                        <td class="text-end small"><?= (int) $r['attempts'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total > count($rows)): ?>
        <p class="text-muted small p-3 mb-0">Showing the newest <?= count($rows) ?> of <?= (int) $total ?>.</p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

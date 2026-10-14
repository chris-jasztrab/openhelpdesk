<?php
/**
 * Time Tracking card body. Rendered by time-tracking.php on the ticket page and
 * again by POST /agent/tickets/{id}/time after every change, whose response
 * replaces #ttCard's contents. Expects $ticket (id, type_id); optional $ttError.
 *
 * Outputs nothing when the ticket's type has time tracking off and nothing has
 * been logged — switching a type off later never hides time already recorded.
 */
$ttDb      = Database::connect();
$ttEnabled = timeTrackingEnabledForType($ttDb, $ticket['type_id'] !== null ? (int) $ticket['type_id'] : null);
$ttEntries = ticketTimeEntries($ttDb, (int) $ticket['id']);
$ttVisible = $ttEnabled || $ttEntries;
if (!$ttVisible) {
    return;
}
$ttMe      = (int) Auth::id();
$ttUrl     = '/agent/tickets/' . (int) $ticket['id'] . '/time';
$ttToday   = date('Y-m-d');
$ttTotal   = array_sum(array_column($ttEntries, 'elapsed'));
$ttRunning = null;
foreach ($ttEntries as $ttE) {
    if ($ttE['timer_started_at'] && (int) $ttE['user_id'] === $ttMe) {
        $ttRunning = $ttE;
    }
}
$ttClock = static fn(int $s): string => sprintf('%02d:%02d:%02d', intdiv($s, 3600), intdiv($s, 60) % 60, $s % 60);
?>
<div class="card border-0 shadow-sm mt-4" id="ttBody" data-enabled="<?= $ttEnabled ? '1' : '0' ?>" data-count="<?= count($ttEntries) ?>">
    <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-semibold"><i class="bi bi-hourglass-split me-2"></i>Time Tracking</h5>
        <span class="fw-semibold" title="Total time logged on this ticket"><?= e(formatTrackedTime($ttTotal)) ?></span>
    </div>
    <div class="card-body">
        <?php if (!empty($ttError)): ?>
        <div class="alert alert-danger py-2 small" role="alert"><?= e($ttError) ?></div>
        <?php endif; ?>

        <?php if ($ttRunning): ?>
        <form method="POST" action="<?= $ttUrl ?>" class="tt-form border border-success-subtle rounded p-2 mb-3 bg-success bg-opacity-10">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="stop">
            <input type="hidden" name="entry_id" value="<?= (int) $ttRunning['id'] ?>">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small text-success fw-semibold"><i class="bi bi-record-circle me-1"></i>Timer running</span>
                <span class="fs-5 fw-bold font-monospace" data-tt-elapsed="<?= (int) $ttRunning['elapsed'] ?>"><?= $ttClock((int) $ttRunning['elapsed']) ?></span>
            </div>
            <input type="text" name="note" class="form-control form-control-sm mb-2" maxlength="500"
                   placeholder="What did you work on? (optional)" aria-label="Note for this time entry" value="<?= e($ttRunning['note']) ?>">
            <button type="submit" class="btn btn-sm btn-danger w-100"><i class="bi bi-stop-fill me-1"></i>Stop timer</button>
        </form>
        <?php endif; ?>

        <?php if ($ttEnabled): ?>
        <div class="d-flex gap-2 mb-3">
            <?php if (!$ttRunning): ?>
            <form method="POST" action="<?= $ttUrl ?>" class="tt-form flex-fill">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="start">
                <button type="submit" class="btn btn-sm btn-outline-success w-100"><i class="bi bi-play-fill me-1"></i>Start timer</button>
            </form>
            <?php endif; ?>
            <button type="button" class="btn btn-sm text-white flex-fill" style="background:var(--ld-primary);"
                    data-bs-toggle="collapse" data-bs-target="#ttLogForm" aria-expanded="false" aria-controls="ttLogForm">
                <i class="bi bi-plus-lg me-1"></i>Log time
            </button>
        </div>
        <form method="POST" action="<?= $ttUrl ?>" class="tt-form collapse border rounded p-2 mb-3" id="ttLogForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label small mb-0" for="ttHours">Hours</label>
                    <input type="number" class="form-control form-control-sm" id="ttHours" name="hours" min="0" max="24" step="1" value="0">
                </div>
                <div class="col-6">
                    <label class="form-label small mb-0" for="ttMinutes">Minutes</label>
                    <input type="number" class="form-control form-control-sm" id="ttMinutes" name="minutes" min="0" max="59" step="1" value="0">
                </div>
            </div>
            <label class="form-label small mb-0" for="ttDate">Date worked</label>
            <input type="date" class="form-control form-control-sm mb-2" id="ttDate" name="work_date" max="<?= $ttToday ?>" value="<?= $ttToday ?>">
            <input type="text" class="form-control form-control-sm mb-2" name="note" maxlength="500" placeholder="Note (optional)" aria-label="Note">
            <button type="submit" class="btn btn-sm text-white w-100" style="background:var(--ld-primary);">Save</button>
        </form>
        <?php endif; ?>

        <?php if (!$ttEntries): ?>
        <p class="text-muted small mb-0">No time logged yet.</p>
        <?php else: ?>
        <ul class="list-unstyled mb-0">
            <?php foreach ($ttEntries as $ttI => $ttE):
                $ttEid     = (int) $ttE['id'];
                $ttLive    = !empty($ttE['timer_started_at']);
                $ttCanEdit = !$ttLive && ((int) $ttE['user_id'] === $ttMe || Auth::isAdmin());
                $ttMins    = intdiv((int) $ttE['seconds'] + 30, 60);
            ?>
            <li class="py-2<?= $ttI > 0 ? ' border-top' : '' ?>">
                <div class="d-flex justify-content-between gap-2">
                    <span class="fw-semibold small text-truncate"><?= e($ttE['user_name'] ?? 'Deleted user') ?></span>
                    <?php if ($ttLive): ?>
                    <span class="small font-monospace text-success" data-tt-elapsed="<?= (int) $ttE['elapsed'] ?>"><?= $ttClock((int) $ttE['elapsed']) ?></span>
                    <?php else: ?>
                    <span class="fw-semibold small text-nowrap"><?= e(formatTrackedTime((int) $ttE['elapsed'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="text-muted small">
                    <?= e(date('D, M j, Y', strtotime($ttE['work_date']))) ?>
                    <?php if ($ttLive): ?>&middot; <span class="text-success">running</span><?php endif; ?>
                </div>
                <?php if ($ttE['note'] !== ''): ?>
                <div class="small mt-1 text-break"><?= e($ttE['note']) ?></div>
                <?php endif; ?>
                <?php if ($ttCanEdit): ?>
                <div class="small mt-1">
                    <a href="#ttEdit<?= $ttEid ?>" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="ttEdit<?= $ttEid ?>">Edit</a>
                    <span class="text-muted mx-1">|</span>
                    <form method="POST" action="<?= $ttUrl ?>" class="tt-form d-inline" data-confirm="Delete this time entry?">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="entry_id" value="<?= $ttEid ?>">
                        <button type="submit" class="btn btn-link btn-sm p-0 align-baseline text-danger text-decoration-none">Delete</button>
                    </form>
                </div>
                <form method="POST" action="<?= $ttUrl ?>" class="tt-form collapse border rounded p-2 mt-2" id="ttEdit<?= $ttEid ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="entry_id" value="<?= $ttEid ?>">
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small mb-0" for="ttH<?= $ttEid ?>">Hours</label>
                            <input type="number" class="form-control form-control-sm" id="ttH<?= $ttEid ?>" name="hours" min="0" max="24" step="1" value="<?= intdiv($ttMins, 60) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-0" for="ttM<?= $ttEid ?>">Minutes</label>
                            <input type="number" class="form-control form-control-sm" id="ttM<?= $ttEid ?>" name="minutes" min="0" max="59" step="1" value="<?= $ttMins % 60 ?>">
                        </div>
                    </div>
                    <label class="form-label small mb-0" for="ttD<?= $ttEid ?>">Date worked</label>
                    <input type="date" class="form-control form-control-sm mb-2" id="ttD<?= $ttEid ?>" name="work_date" max="<?= $ttToday ?>" value="<?= e($ttE['work_date']) ?>">
                    <input type="text" class="form-control form-control-sm mb-2" name="note" maxlength="500" placeholder="Note (optional)" aria-label="Note" value="<?= e($ttE['note']) ?>">
                    <button type="submit" class="btn btn-sm text-white w-100" style="background:var(--ld-primary);">Save changes</button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

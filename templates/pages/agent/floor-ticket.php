<?php
/** @var array $ticket */
/** @var array $timeline */
/** @var bool  $isMine */

$createdTs = strtotime($ticket['created_at']);
$diff      = max(0, time() - $createdTs);
if ($diff < 60)            $age = 'just now';
elseif ($diff < 3600)      $age = floor($diff / 60) . 'm ago';
elseif ($diff < 86400)     $age = floor($diff / 3600) . 'h ago';
elseif ($diff < 86400 * 7) $age = floor($diff / 86400) . 'd ago';
else                       $age = date('M j', $createdTs);

$rail = $ticket['priority_color'] ?: '#94a3b8';

$statusLabels = [
    'open' => 'Open', 'in_progress' => 'In Progress', 'pending' => 'Pending',
    'waiting_on_customer' => 'Waiting (Customer)', 'waiting_on_third_party' => 'Waiting (3rd Party)',
    'resolved' => 'Resolved', 'closed' => 'Closed',
];
$status = $ticket['status'];
$ticketId = (int) $ticket['id'];
?>
<style>
.floor-detail { padding-bottom: 6rem; max-width: 760px; margin: 0 auto; }
.floor-detail .topbar {
    display: flex; align-items: center; justify-content: space-between;
    gap: .5rem; margin-bottom: 1rem;
}
.floor-detail .back-link {
    display: inline-flex; align-items: center; gap: .35rem;
    text-decoration: none; color: #475569; font-weight: 500;
    padding: .55rem .9rem; border-radius: 999px;
    background: #fff; border: 1px solid #cbd5e1; min-height: 44px;
}
[data-bs-theme="dark"] .floor-detail .back-link { background: #2b3035; color: #dee2e6; border-color: #495057; }
.floor-detail .ticket-id { color: #64748b; font-weight: 600; font-size: .85rem; }

.floor-detail .subject-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid <?= e($rail) ?>;
    border-radius: 14px;
    padding: 1.1rem 1.15rem;
    margin-bottom: 1rem;
}
[data-bs-theme="dark"] .floor-detail .subject-card { background: #212529; border-color: #373b3e; border-left-color: <?= e($rail) ?>; }
.floor-detail .subject-card h1 {
    font-size: 1.3rem; font-weight: 700; line-height: 1.3;
    margin: 0 0 .65rem; word-break: break-word;
}
.floor-detail .pill-row { display: flex; flex-wrap: wrap; gap: .35rem; }
.floor-detail .pill {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .2rem .6rem; border-radius: 999px; font-weight: 500;
    font-size: .78rem; border: 1px solid transparent;
}
.floor-detail .pill.status-open        { background: #dbeafe; color: #1e40af; }
.floor-detail .pill.status-in_progress { background: #fef3c7; color: #92400e; }
.floor-detail .pill.status-pending,
.floor-detail .pill.status-waiting_on_customer,
.floor-detail .pill.status-waiting_on_third_party { background: #f3e8ff; color: #6b21a8; }
.floor-detail .pill.status-resolved    { background: #dcfce7; color: #166534; }
.floor-detail .pill.status-closed      { background: #e2e8f0; color: #334155; }
[data-bs-theme="dark"] .floor-detail .pill.status-open        { background: #1e3a8a; color: #bfdbfe; }
[data-bs-theme="dark"] .floor-detail .pill.status-in_progress { background: #78350f; color: #fde68a; }
[data-bs-theme="dark"] .floor-detail .pill.status-pending,
[data-bs-theme="dark"] .floor-detail .pill.status-waiting_on_customer,
[data-bs-theme="dark"] .floor-detail .pill.status-waiting_on_third_party { background: #581c87; color: #e9d5ff; }
[data-bs-theme="dark"] .floor-detail .pill.status-resolved    { background: #064e3b; color: #a7f3d0; }
[data-bs-theme="dark"] .floor-detail .pill.status-closed      { background: #2b3035; color: #dee2e6; }
.floor-detail .pill.location, .floor-detail .pill.muted { background: #f1f5f9; color: #1e293b; }
[data-bs-theme="dark"] .floor-detail .pill.location, [data-bs-theme="dark"] .floor-detail .pill.muted { background: #2b3035; color: #dee2e6; }

.floor-detail .who-row {
    display: grid; grid-template-columns: 1fr 1fr; gap: .65rem;
    margin-bottom: 1rem;
}
.floor-detail .who-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    padding: .8rem .9rem;
}
[data-bs-theme="dark"] .floor-detail .who-card { background: #212529; border-color: #373b3e; }
.floor-detail .who-card .label { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; font-weight: 600; }
[data-bs-theme="dark"] .floor-detail .who-card .label { color: #adb5bd; }
.floor-detail .who-card .value { font-size: .95rem; font-weight: 600; margin-top: .2rem; word-break: break-word; }
.floor-detail .who-card .sub   { font-size: .78rem; color: #64748b; }
[data-bs-theme="dark"] .floor-detail .who-card .sub { color: #adb5bd; }
.floor-detail .who-card.unassigned .value { color: #b91c1c; }
[data-bs-theme="dark"] .floor-detail .who-card.unassigned .value { color: #fecaca; }
@media (max-width: 480px) {
    .floor-detail .who-row { grid-template-columns: 1fr; }
}

.floor-detail .section {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    padding: 1rem 1.05rem; margin-bottom: 1rem;
}
[data-bs-theme="dark"] .floor-detail .section { background: #212529; border-color: #373b3e; }
.floor-detail .section h3 {
    font-size: .95rem; font-weight: 700; margin: 0 0 .6rem;
    text-transform: uppercase; letter-spacing: .04em; color: #475569;
}
[data-bs-theme="dark"] .floor-detail .section h3 { color: #adb5bd; }
.floor-detail .description { font-size: .95rem; line-height: 1.55; word-break: break-word; }
.floor-detail .description.collapsed {
    max-height: 8.5em; overflow: hidden;
    -webkit-mask-image: linear-gradient(to bottom, #000 70%, transparent 100%);
            mask-image: linear-gradient(to bottom, #000 70%, transparent 100%);
}
.floor-detail .desc-toggle {
    display: inline-flex; align-items: center; gap: .25rem;
    margin-top: .55rem; background: none; border: none; padding: 0;
    color: var(--ld-primary, #4f46e5); font-weight: 600; font-size: .85rem;
    cursor: pointer; min-height: 32px;
}

.floor-detail .actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: .55rem;
}
.floor-detail .actions .ld-action {
    min-height: 56px; border-radius: 10px;
    border: 1px solid transparent;
    font-weight: 600; font-size: .95rem;
    display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
    cursor: pointer;
}
.floor-detail .actions .ld-action:disabled { opacity: .55; cursor: default; }
.floor-detail .actions .a-claim   { background: var(--ld-primary, #4f46e5); color: #fff; }
.floor-detail .actions .a-unclaim { background: #fee2e2; color: #991b1b; }
.floor-detail .actions .a-progress { background: #fef3c7; color: #92400e; }
.floor-detail .actions .a-pending  { background: #f3e8ff; color: #6b21a8; }
.floor-detail .actions .a-resolve  { background: #dcfce7; color: #166534; }
.floor-detail .actions .a-reopen   { background: #dbeafe; color: #1e40af; }
[data-bs-theme="dark"] .floor-detail .actions .a-unclaim  { background: #7f1d1d; color: #fecaca; }
[data-bs-theme="dark"] .floor-detail .actions .a-progress { background: #78350f; color: #fde68a; }
[data-bs-theme="dark"] .floor-detail .actions .a-pending  { background: #581c87; color: #e9d5ff; }
[data-bs-theme="dark"] .floor-detail .actions .a-resolve  { background: #064e3b; color: #a7f3d0; }
[data-bs-theme="dark"] .floor-detail .actions .a-reopen   { background: #1e3a8a; color: #bfdbfe; }

.floor-detail .timeline { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .55rem; }
.floor-detail .tl-item {
    border-left: 3px solid #e2e8f0; padding: .15rem .85rem;
    font-size: .9rem;
}
.floor-detail .tl-item.internal { border-left-color: #ca8a04; background: rgba(254,252,232,.55); border-radius: 0 6px 6px 0; }
[data-bs-theme="dark"] .floor-detail .tl-item.internal { background: rgba(120,85,12,.18); }
.floor-detail .tl-meta { font-size: .75rem; color: #64748b; }
[data-bs-theme="dark"] .floor-detail .tl-meta { color: #adb5bd; }
.floor-detail .tl-body { white-space: pre-wrap; word-break: break-word; }

.floor-detail textarea.note-input {
    width: 100%; min-height: 110px; border-radius: 10px;
    border: 1px solid #cbd5e1; padding: .65rem .75rem;
    font-size: 1rem; resize: vertical;
}
[data-bs-theme="dark"] .floor-detail textarea.note-input { background: #2b3035; color: #f8f9fa; border-color: #495057; }
.floor-detail .note-row { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-top: .55rem; }
.floor-detail .note-row label { display: inline-flex; align-items: center; gap: .35rem; font-size: .85rem; }
.floor-detail .note-photo-btn,
.floor-detail .note-submit {
    min-height: 48px; padding: .55rem 1rem; border-radius: 10px;
    border: none; font-weight: 600; font-size: .95rem; cursor: pointer;
    display: inline-flex; align-items: center; gap: .4rem;
}
.floor-detail .note-photo-btn { background: #f1f5f9; color: #1e293b; border: 1px dashed #94a3b8; }
[data-bs-theme="dark"] .floor-detail .note-photo-btn { background: #2b3035; color: #f8f9fa; border-color: #495057; }
.floor-detail .note-submit { background: var(--ld-primary, #4f46e5); color: #fff; margin-left: auto; }
.floor-detail .photo-thumbs { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .5rem; }
.floor-detail .photo-thumb {
    width: 56px; height: 56px; border-radius: 8px;
    background-size: cover; background-position: center;
    border: 1px solid #cbd5e1; position: relative;
}
.floor-detail .photo-thumb .remove {
    position: absolute; top: -6px; right: -6px;
    width: 22px; height: 22px; border-radius: 50%;
    background: #dc2626; color: #fff; border: none; font-size: .8rem; line-height: 1;
    display: inline-flex; align-items: center; justify-content: center;
}

.floor-detail .full-link {
    display: flex; align-items: center; justify-content: center; gap: .4rem;
    padding: .85rem 1rem; min-height: 52px;
    background: #fff; border: 1px solid #cbd5e1; border-radius: 12px;
    color: #1e293b; text-decoration: none; font-weight: 600;
}
[data-bs-theme="dark"] .floor-detail .full-link { background: #212529; border-color: #495057; color: #f8f9fa; }
.floor-detail .full-link .hint { font-weight: 400; font-size: .78rem; color: #64748b; margin-left: auto; }
[data-bs-theme="dark"] .floor-detail .full-link .hint { color: #adb5bd; }
</style>

<div class="floor-detail">
    <div class="topbar">
        <a href="/agent/floor" class="back-link"><i class="bi bi-arrow-left"></i> Floor queue</a>
        <span class="ticket-id">Ticket #<?= $ticketId ?> · <?= e($age) ?></span>
    </div>

    <section class="subject-card">
        <h1><?= e($ticket['subject']) ?></h1>
        <div class="pill-row">
            <span class="pill status-<?= e($status) ?>"><?= e($statusLabels[$status] ?? $status) ?></span>
            <?php if (!empty($ticket['priority_name'])): ?>
                <span class="pill" style="background:<?= e($rail) ?>22;color:<?= e($rail) ?>;"><i class="bi bi-flag-fill"></i><?= e($ticket['priority_name']) ?></span>
            <?php endif; ?>
            <?php if (!empty($ticket['type_name'])): ?>
                <span class="pill" style="background:<?= e($ticket['type_color'] ?: '#f1f5f9') ?>26;color:<?= e($ticket['type_color'] ?: '#1e293b') ?>;"><i class="bi bi-bookmark"></i><?= e($ticket['type_name']) ?></span>
            <?php endif; ?>
            <?php if (!empty($ticket['location_name'])): ?>
                <span class="pill location"><i class="bi bi-geo-alt"></i><?= e($ticket['location_name']) ?></span>
            <?php endif; ?>
            <?php if (!empty($ticket['group_name'])): ?>
                <span class="pill muted"><i class="bi bi-people"></i><?= e($ticket['group_name']) ?></span>
            <?php endif; ?>
        </div>
    </section>

    <div class="who-row">
        <div class="who-card">
            <div class="label">Reported by</div>
            <div class="value"><?= e(trim($ticket['creator_name'] ?? '') ?: '—') ?></div>
            <?php if (!empty($ticket['creator_email'])): ?>
                <div class="sub"><?= e($ticket['creator_email']) ?></div>
            <?php endif; ?>
        </div>
        <div class="who-card <?= empty($ticket['assigned_to']) ? 'unassigned' : '' ?>">
            <div class="label">Assigned to</div>
            <?php if (!empty($ticket['assigned_to'])): ?>
                <div class="value"><?= e(trim($ticket['agent_name'] ?? '')) ?><?= $isMine ? ' (you)' : '' ?></div>
            <?php else: ?>
                <div class="value">Unassigned</div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($ticket['description']) && trim(strip_tags((string) $ticket['description'])) !== ''): ?>
    <section class="section">
        <h3>Details</h3>
        <div class="description collapsed" id="floor-desc">
            <?= nl2br(e(strip_tags((string) $ticket['description']))) ?>
        </div>
        <button type="button" class="desc-toggle" id="floor-desc-toggle">
            <i class="bi bi-chevron-down"></i> Show all
        </button>
    </section>
    <?php endif; ?>

    <section class="section">
        <h3>Quick actions</h3>
        <div class="actions">
            <?php if (!$isMine): ?>
            <form method="post" action="/agent/floor/tickets/<?= $ticketId ?>/action">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="claim">
                <button type="submit" class="ld-action a-claim" style="width:100%;"><i class="bi bi-person-plus"></i> Claim</button>
            </form>
            <?php else: ?>
            <form method="post" action="/agent/floor/tickets/<?= $ticketId ?>/action">
                <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="unclaim">
                <button type="submit" class="ld-action a-unclaim" style="width:100%;"><i class="bi bi-person-dash"></i> Release</button>
            </form>
            <?php endif; ?>

            <?php
            $statusButtons = [
                'in_progress' => ['In progress',  'a-progress', 'bi-play-fill'],
                'pending'     => ['Pending',      'a-pending',  'bi-pause-fill'],
                'resolved'    => ['Resolve',      'a-resolve',  'bi-check2-circle'],
            ];
            if (in_array($status, ['resolved', 'closed'], true)) {
                $statusButtons = ['open' => ['Reopen', 'a-reopen', 'bi-arrow-counterclockwise']] + $statusButtons;
                unset($statusButtons['resolved']);
            }
            foreach ($statusButtons as $s => [$label, $cls, $icon]):
                if ($s === $status) continue; ?>
                <form method="post" action="/agent/floor/tickets/<?= $ticketId ?>/action">
                    <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="status" value="<?= e($s) ?>">
                    <button type="submit" class="ld-action <?= e($cls) ?>" style="width:100%;"><i class="bi <?= e($icon) ?>"></i> <?= e($label) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section">
        <h3>Recent activity</h3>
        <?php
        // Admins who hid AI notes (profile / ticket timeline slider) don't see
        // them here either. The floor view has no slider — it just follows the
        // profile preference. The notes are never deleted, only hidden.
        if (Auth::role() === 'admin' && !aiNotesVisible()) {
            $timeline = array_values(array_filter($timeline, fn($tl) =>
                !($tl['is_internal'] && trim($tl['user_name'] ?? '') === ''
                  && str_starts_with((string) $tl['action'], 'ai_'))
            ));
        }
        ?>
        <?php if (empty($timeline)): ?>
            <div style="color:#64748b;font-size:.9rem;">No activity yet.</div>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($timeline as $tl):
                    $tlTs   = strtotime($tl['created_at']);
                    $tlAge  = max(0, time() - $tlTs);
                    if ($tlAge < 60)            $tlAgeStr = 'just now';
                    elseif ($tlAge < 3600)      $tlAgeStr = floor($tlAge / 60) . 'm ago';
                    elseif ($tlAge < 86400)     $tlAgeStr = floor($tlAge / 3600) . 'h ago';
                    elseif ($tlAge < 86400 * 7) $tlAgeStr = floor($tlAge / 86400) . 'd ago';
                    else                        $tlAgeStr = date('M j', $tlTs);
                ?>
                    <li class="tl-item <?= !empty($tl['is_internal']) ? 'internal' : '' ?>">
                        <div class="tl-meta">
                            <strong><?= e(trim($tl['user_name'] ?? '') ?: 'System') ?></strong> ·
                            <?= e(str_replace('_', ' ', $tl['action'])) ?> ·
                            <?= e($tlAgeStr) ?>
                            <?php if (!empty($tl['is_internal'])): ?> · <span title="Internal note"><i class="bi bi-lock-fill"></i> internal</span><?php endif; ?>
                        </div>
                        <?php
                        // details holds raw HTML (e.g. comment bodies); strip tags and
                        // decode entities so the body shows plain text, not markup.
                        $tlBody = trim(preg_replace('/\s+/', ' ', html_entity_decode(
                            strip_tags(str_replace('<', ' <', (string) ($tl['details'] ?? ''))),
                            ENT_QUOTES | ENT_HTML5
                        )));
                        ?>
                        <?php if ($tlBody !== ''): ?>
                            <div class="tl-body"><?= e($tlBody) ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="section">
        <h3>Add a note</h3>
        <form method="post" action="/agent/floor/tickets/<?= $ticketId ?>/action" enctype="multipart/form-data" id="floor-note-form">
            <input type="hidden" name="_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="comment">
            <textarea class="note-input" name="message" placeholder="What did you find or do?" required></textarea>
            <input type="file" id="floor-note-photo" name="attachments[]" accept="image/*" capture="environment" multiple style="display:none;">
            <div class="photo-thumbs" id="floor-note-thumbs"></div>
            <div class="note-row">
                <button type="button" class="note-photo-btn" id="floor-note-photo-trigger"><i class="bi bi-camera-fill"></i> Photo</button>
                <label><input type="checkbox" name="is_internal" value="1"> Internal only</label>
                <button type="submit" class="note-submit"><i class="bi bi-send-fill"></i> Send</button>
            </div>
        </form>
    </section>

    <a href="/agent/tickets/<?= $ticketId ?>?from=floor" class="full-link">
        <i class="bi bi-list-ul"></i> Full ticket details
        <span class="hint">Edit fields, merge, escalate &rarr;</span>
    </a>
</div>

<script>
(function () {
    var desc       = document.getElementById('floor-desc');
    var descToggle = document.getElementById('floor-desc-toggle');
    if (desc && descToggle) {
        // Hide the toggle if the description doesn't actually overflow.
        if (desc.scrollHeight <= desc.clientHeight + 4) {
            descToggle.style.display = 'none';
        }
        descToggle.addEventListener('click', function () {
            var collapsed = desc.classList.toggle('collapsed');
            descToggle.innerHTML = collapsed
                ? '<i class="bi bi-chevron-down"></i> Show all'
                : '<i class="bi bi-chevron-up"></i> Show less';
        });
    }

    var photoTrig  = document.getElementById('floor-note-photo-trigger');
    var photoInput = document.getElementById('floor-note-photo');
    var thumbsBox  = document.getElementById('floor-note-thumbs');
    if (photoTrig && photoInput && thumbsBox) {
        photoTrig.addEventListener('click', function () { photoInput.click(); });
        photoInput.addEventListener('change', function () {
            thumbsBox.innerHTML = '';
            Array.from(photoInput.files || []).forEach(function (file, idx) {
                if (!file.type.startsWith('image/')) return;
                var url = URL.createObjectURL(file);
                var div = document.createElement('div');
                div.className = 'photo-thumb';
                div.style.backgroundImage = 'url(' + url + ')';
                var rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'remove';
                rm.setAttribute('aria-label', 'Remove photo');
                rm.textContent = '×';
                rm.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var dt = new DataTransfer();
                    Array.from(photoInput.files).forEach(function (f, i) { if (i !== idx) dt.items.add(f); });
                    photoInput.files = dt.files;
                    photoInput.dispatchEvent(new Event('change'));
                });
                div.appendChild(rm);
                thumbsBox.appendChild(div);
            });
        });
    }
})();
</script>

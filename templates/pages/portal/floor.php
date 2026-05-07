<?php
/** @var array $tickets */
?>
<style>
.floor-portal-shell { padding-bottom: 6rem; }
.floor-portal-cta {
    display: flex; align-items: center; gap: 1rem;
    background: linear-gradient(135deg, var(--ld-primary, #4f46e5), var(--ld-primary-hover, #4338ca));
    color: #fff;
    padding: 1.1rem 1.25rem;
    border-radius: 14px;
    text-decoration: none;
    margin-bottom: 1.25rem;
    box-shadow: 0 8px 22px rgba(79,70,229,.25);
    min-height: 80px;
}
.floor-portal-cta:hover { color: #fff; filter: brightness(1.05); }
.floor-portal-cta .icon {
    width: 48px; height: 48px; border-radius: 12px;
    background: rgba(255,255,255,.18);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 1.6rem; flex-shrink: 0;
}
.floor-portal-cta strong { display: block; font-size: 1.05rem; font-weight: 600; }
.floor-portal-cta small  { display: block; opacity: .85; }

.floor-portal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: .85rem;
}
.floor-portal-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #94a3b8;
    border-radius: 12px;
    padding: 1rem;
    text-decoration: none;
    color: inherit;
    display: flex; flex-direction: column; gap: .55rem;
    min-height: 120px;
    transition: transform .12s ease, box-shadow .12s ease;
    -webkit-tap-highlight-color: rgba(79,70,229,.18);
}
.floor-portal-card:active { transform: scale(.98); }
.floor-portal-card:hover  { box-shadow: 0 4px 14px rgba(15,23,42,.08); color: inherit; }
[data-bs-theme="dark"] .floor-portal-card { background: #212529; border-color: #373b3e; color: #f8f9fa; }
[data-bs-theme="dark"] .floor-portal-card:hover { color: #fff; }

.floor-portal-card .top-row {
    display: flex; justify-content: space-between; align-items: center;
    font-size: .75rem; color: #64748b; font-weight: 500;
}
[data-bs-theme="dark"] .floor-portal-card .top-row { color: #adb5bd; }
.floor-portal-card .subject {
    font-weight: 600; font-size: 1rem; line-height: 1.3;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.floor-portal-card .meta-row {
    display: flex; flex-wrap: wrap; gap: .35rem; align-items: center;
    font-size: .75rem;
}
.floor-portal-card .pill {
    display: inline-flex; align-items: center; gap: .3rem;
    padding: .15rem .55rem; border-radius: 999px; font-weight: 500;
}
.floor-portal-card .pill.status-open        { background: #dbeafe; color: #1e40af; }
.floor-portal-card .pill.status-in_progress { background: #fef3c7; color: #92400e; }
.floor-portal-card .pill.status-pending     { background: #f3e8ff; color: #6b21a8; }
.floor-portal-card .pill.status-resolved    { background: #ecfdf5; color: #065f46; }
.floor-portal-card .pill.status-closed      { background: #e2e8f0; color: #334155; }
[data-bs-theme="dark"] .floor-portal-card .pill.status-open        { background: #1e3a8a; color: #bfdbfe; }
[data-bs-theme="dark"] .floor-portal-card .pill.status-in_progress { background: #78350f; color: #fde68a; }
[data-bs-theme="dark"] .floor-portal-card .pill.status-pending     { background: #581c87; color: #e9d5ff; }
[data-bs-theme="dark"] .floor-portal-card .pill.status-resolved    { background: #064e3b; color: #a7f3d0; }
[data-bs-theme="dark"] .floor-portal-card .pill.status-closed      { background: #495057; color: #ced4da; }
.floor-portal-card .pill.location { background: #f1f5f9; color: #1e293b; }
[data-bs-theme="dark"] .floor-portal-card .pill.location { background: #2b3035; color: #dee2e6; }

.floor-portal-empty {
    text-align: center; padding: 2.5rem 1rem; color: #64748b;
    background: #fff; border: 1px dashed #cbd5e1; border-radius: 12px;
}
[data-bs-theme="dark"] .floor-portal-empty { background: #212529; border-color: #495057; color: #adb5bd; }
.floor-portal-empty i { font-size: 2.5rem; opacity: .35; }

@media (max-width: 768px) {
    .main-content { padding: 1rem !important; }
    .floor-portal-grid { grid-template-columns: 1fr; }
}
</style>

<div class="floor-portal-shell">
    <h2 class="mb-3" style="font-size:1.4rem;font-weight:700;">My help requests</h2>

    <a href="/portal/tickets/create" class="floor-portal-cta">
        <span class="icon" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
        <span>
            <strong>New help request</strong>
            <small>Tell us what's going on. Use the camera to attach a photo.</small>
        </span>
    </a>

    <?php if (empty($tickets)): ?>
        <div class="floor-portal-empty">
            <i class="bi bi-clipboard-check"></i>
            <p class="mt-2 mb-0">No requests yet.</p>
            <small>When you submit a help request, it'll appear here so you can check on it.</small>
        </div>
    <?php else: ?>
        <div class="floor-portal-grid">
        <?php foreach ($tickets as $t):
            $createdTs = strtotime($t['created_at']);
            $diff      = max(0, time() - $createdTs);
            if ($diff < 60)            $age = 'just now';
            elseif ($diff < 3600)      $age = floor($diff / 60) . 'm ago';
            elseif ($diff < 86400)     $age = floor($diff / 3600) . 'h ago';
            elseif ($diff < 86400 * 7) $age = floor($diff / 86400) . 'd ago';
            else                       $age = date('M j', $createdTs);

            $rail = $t['priority_color'] ?: '#94a3b8';
        ?>
            <a class="floor-portal-card" href="/portal/tickets/<?= (int) $t['id'] ?>" style="border-left-color:<?= e($rail) ?>;">
                <div class="top-row">
                    <span>#<?= (int) $t['id'] ?></span>
                    <span><?= e($age) ?></span>
                </div>
                <div class="subject"><?= e($t['subject']) ?></div>
                <div class="meta-row">
                    <span class="pill status-<?= e($t['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $t['status']))) ?></span>
                    <?php if (!empty($t['type_name'])): ?>
                        <span class="pill" style="background:<?= e($t['type_color'] ?: '#f1f5f9') ?>26;color:<?= e($t['type_color'] ?: '#1e293b') ?>;"><i class="bi bi-bookmark"></i><?= e($t['type_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($t['location_name'])): ?>
                        <span class="pill location"><i class="bi bi-geo-alt"></i><?= e($t['location_name']) ?></span>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

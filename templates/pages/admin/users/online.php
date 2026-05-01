<?php
$layout       = 'app';
$pageTitle    = "Who's Online";
$sidebarItems = adminSidebar('users');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Users', 'url' => '/admin/users'],
    ['label' => "Who's Online"],
];

/**
 * Render a UA string as a short "Chrome on Windows"-style label. Best-effort:
 * UA strings are infamously unreliable, but for an in-house admin panel
 * spotting "Edge" vs "Chrome" vs "iPhone" is enough.
 */
$prettyUserAgent = static function (?string $ua): string {
    if ($ua === null || $ua === '') {
        return '—';
    }
    $browser = 'Browser';
    if (stripos($ua, 'Edg/') !== false || stripos($ua, 'Edge') !== false)        $browser = 'Edge';
    elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false)   $browser = 'Opera';
    elseif (stripos($ua, 'Firefox') !== false)                                   $browser = 'Firefox';
    elseif (stripos($ua, 'Chrome') !== false)                                    $browser = 'Chrome';
    elseif (stripos($ua, 'Safari') !== false)                                    $browser = 'Safari';
    $os = 'Unknown OS';
    if (stripos($ua, 'Windows') !== false)                                       $os = 'Windows';
    elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false)  $os = 'iOS';
    elseif (stripos($ua, 'Android') !== false)                                   $os = 'Android';
    elseif (stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false) $os = 'macOS';
    elseif (stripos($ua, 'Linux') !== false)                                     $os = 'Linux';
    return $browser . ' on ' . $os;
};

$secondsAgo = static function (int $s): string {
    if ($s < 5)   return 'just now';
    if ($s < 60)  return $s . 's ago';
    $m = (int) floor($s / 60);
    return $m . 'm ago';
};
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-0">Who's Online</h2>
        <p class="text-muted small mb-0">Users with the app open in a browser tab right now (heartbeat within the last <?= (int) $onlineWindow ?>s).</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge bg-success fs-6">
            <i class="bi bi-circle-fill me-1" style="font-size:.55rem;animation:ld-online-pulse 1.6s ease-in-out infinite;"></i>
            <?= count($online) ?> online
        </span>
        <a href="/admin/users" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-people me-1"></i>All Users
        </a>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()" title="Refresh now">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>
</div>

<style>
    @keyframes ld-online-pulse {
        0%, 100% { opacity: 1; }
        50%      { opacity: .35; }
    }
</style>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px"></th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Last Seen</th>
                    <th>IP</th>
                    <th>Browser</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($online)): ?>
                <tr><td colspan="7" class="text-center py-4 text-muted">Nobody is online right now.</td></tr>
                <?php else: ?>
                    <?php foreach ($online as $u): ?>
                    <tr style="cursor:pointer;" onclick="window.location='/admin/users/<?= (int) $u['id'] ?>'">
                        <td>
                            <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center fw-bold position-relative" style="width:36px;height:36px;font-size:.8rem;">
                                <?= strtoupper(mb_substr($u['first_name'], 0, 1) . mb_substr($u['last_name'], 0, 1)) ?>
                                <span class="position-absolute" style="bottom:-2px;right:-2px;width:10px;height:10px;background:#10b981;border:2px solid #fff;border-radius:50%;"></span>
                            </div>
                        </td>
                        <td class="fw-semibold">
                            <a href="/admin/users/<?= (int) $u['id'] ?>" class="text-decoration-none text-dark">
                                <?= e($u['first_name'] . ' ' . $u['last_name']) ?>
                            </a>
                        </td>
                        <td><span class="text-muted"><?= e($u['email']) ?></span></td>
                        <td>
                            <?php
                            $badgeColors = ['admin' => 'danger', 'agent' => 'primary', 'power_user' => 'info', 'user' => 'secondary'];
                            $bc = $badgeColors[$u['role']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $bc ?>"><?= e(ucfirst($u['role'])) ?></span>
                        </td>
                        <td class="text-muted small"><?= e($secondsAgo((int) $u['seconds_ago'])) ?></td>
                        <td class="text-muted small font-monospace"><?= e($u['ip_address'] ?? '—') ?></td>
                        <td class="text-muted small"><?= e($prettyUserAgent($u['user_agent'] ?? null)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
/* Auto-refresh every 15s so admins don't have to hit reload to watch the
   online list change. The base layout already heartbeats this user, so
   they won't accidentally appear offline by sitting on this page. */
setTimeout(function () { window.location.reload(); }, 15000);
</script>

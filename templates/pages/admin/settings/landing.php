<?php
$layout       = 'app';
$pageTitle    = 'Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = Auth::isAdmin()
    ? [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Settings']]
    : [['label' => 'Settings']];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-5 text-center text-muted">
        <i class="bi bi-sliders d-block mb-3" style="font-size:2.5rem;opacity:.4;"></i>
        <h5 class="fw-semibold mb-2">Choose a setting to manage</h5>
        <p class="mb-0">Pick an item from the menu on the left. You'll see only the areas your permission level can access.</p>
    </div>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav-end.php'; ?>

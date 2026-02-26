<?php
$layout    = 'auth';
$pageTitle = 'Choose your ' . label('location.singular', 'Location');
?>
<div style="max-width:480px;width:100%;">
    <div class="text-center mb-4">
        <h1 class="text-white fw-bold">
            <i class="bi <?= e(getSetting('branding_navbar_icon', 'bi-headset')) ?>"></i>
            <?= e(getSetting('branding_app_name', 'LocalDesk')) ?>
        </h1>
        <p class="text-white-50">Almost there!</p>
    </div>

    <div class="card shadow-lg border-0" style="border-radius:1rem;">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                     style="width:60px;height:60px;background:#eff6ff;font-size:1.6rem;color:var(--ld-primary);">
                    <i class="bi bi-geo-alt-fill"></i>
                </div>
                <h5 class="fw-bold mb-1">Choose your <?= e(label('location.singular', 'Location')) ?></h5>
                <p class="text-muted small mb-0">
                    Select the <?= e(label('location.singular', 'location')) ?> you work from.
                    Your administrator can update this later if needed.
                </p>
            </div>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger small py-2">
                <i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?>
            </div>
            <?php endif; ?>

            <?php if (empty($locations)): ?>
            <div class="alert alert-warning small py-2 mb-3">
                <i class="bi bi-exclamation-triangle me-1"></i>
                No <?= e(label('location.plural', 'locations')) ?> have been set up yet.
                Please ask your administrator to add <?= e(label('location.plural', 'locations')) ?> first.
            </div>
            <form method="POST" action="/sso/pick-location">
                <?= csrfField() ?>
                <input type="hidden" name="location_id" value="">
                <button type="submit" class="btn w-100 text-white py-2"
                        style="background:var(--ld-primary);">
                    <i class="bi bi-arrow-right me-1"></i>Continue without a <?= e(label('location.singular', 'location')) ?>
                </button>
            </form>
            <?php else: ?>
            <form method="POST" action="/sso/pick-location">
                <?= csrfField() ?>
                <div class="d-flex flex-column gap-2 mb-4" id="locationList">
                    <?php foreach ($locations as $loc): ?>
                    <label class="location-option d-flex align-items-center gap-3 border rounded p-3 cursor-pointer"
                           style="cursor:pointer;transition:border-color .15s,background .15s;"
                           for="loc_<?= $loc['id'] ?>">
                        <input class="form-check-input m-0 flex-shrink-0" type="radio"
                               name="location_id" id="loc_<?= $loc['id'] ?>"
                               value="<?= (int)$loc['id'] ?>" required>
                        <div>
                            <div class="fw-semibold"><?= e($loc['name']) ?></div>
                            <?php if (!empty($loc['address'])): ?>
                            <div class="text-muted small"><?= e($loc['address']) ?></div>
                            <?php endif; ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn w-100 py-2 fw-semibold text-white"
                        style="background:var(--ld-primary);">
                    <i class="bi bi-arrow-right me-1"></i>Continue
                </button>
            </form>
            <form method="POST" action="/sso/pick-location" class="mt-2">
                <?= csrfField() ?>
                <input type="hidden" name="location_id" value="0">
                <button type="submit" class="btn btn-link w-100 text-muted small py-1">
                    Skip for now
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-center text-white-50 mt-3 small">
        <?= e(getSetting('branding_app_name', 'LocalDesk')) ?> &copy; <?= date('Y') ?>
    </p>
</div>

<style>
.location-option:has(input:checked) {
    border-color: var(--ld-primary) !important;
    background: #eff6ff;
}
.location-option:hover {
    border-color: #94a3b8;
}
</style>

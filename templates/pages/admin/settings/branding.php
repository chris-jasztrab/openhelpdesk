<?php
$layout       = 'app';
$pageTitle    = 'Branding – Settings';
$sidebarItems = adminSidebar('settings');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Settings', 'url' => '/admin/settings'],
    ['label' => 'Branding'],
];
?>
<div class="mb-4">
    <h2 class="fw-bold mb-0">Settings</h2>
</div>

<?php require ROOT_DIR . '/templates/partials/settings-nav.php'; ?>

<form method="POST" action="/admin/settings/branding" enctype="multipart/form-data">
    <?= csrfField() ?>

    <div class="row g-4">
        <!-- Left column: Form -->
        <div class="col-lg-7">
            <!-- Logo -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-image me-1"></i>Logo
                </div>
                <div class="card-body">
                    <!-- Hidden remove flag; toggled by the Remove button -->
                    <input type="hidden" name="remove_logo" id="removeLogoFlag" value="0">

                    <?php $hasLogo = $logo && file_exists(ROOT_DIR . '/public/uploads/branding/' . $logo); ?>

                    <!-- Current logo row (hidden when removed) -->
                    <div id="logoCurrentRow" class="mb-3" <?= $hasLogo ? '' : 'style="display:none;"' ?>>
                        <label class="form-label text-muted small">Current Logo</label>
                        <div class="d-flex align-items-center gap-3">
                            <div class="p-3 rounded flex-shrink-0" style="background:#1e1b4b;">
                                <?php if ($hasLogo): ?>
                                <img id="logoCurrentImg" src="/uploads/branding/<?= e($logo) ?>" alt="Logo" style="height:40px;">
                                <?php else: ?>
                                <img id="logoCurrentImg" src="" alt="Logo" style="height:40px; display:none;">
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="removeLogoBtn">
                                <i class="bi bi-trash me-1"></i>Remove logo
                            </button>
                        </div>
                    </div>

                    <!-- Default fallback preview (shown when no logo / after removal) -->
                    <div id="logoDefaultRow" class="mb-3" <?= $hasLogo ? 'style="display:none;"' : '' ?>>
                        <label class="form-label text-muted small">Current Logo</label>
                        <div class="p-3 rounded d-inline-flex align-items-center gap-2" style="background:#1e1b4b; color:#fff;">
                            <i class="bi bi-headset fs-4"></i>
                            <span class="fw-bold"><?= e($appName) ?></span>
                        </div>
                        <div class="form-text mt-1">Default — upload a logo to replace this.</div>
                    </div>

                    <div>
                        <label for="logo" class="form-label">Upload New Logo</label>
                        <input type="file" class="form-control" id="logo" name="logo" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">
                        <div class="form-text">JPG, PNG, GIF, WEBP, or SVG. Max 2 MB. Recommended height: 32–40px.</div>
                    </div>
                </div>
            </div>

            <!-- App Name -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-type me-1"></i>Application Name
                </div>
                <div class="card-body">
                    <div>
                        <label for="appName" class="form-label">App Name</label>
                        <input type="text" class="form-control" id="appName" name="app_name"
                               value="<?= e($appName) ?>" maxlength="100" required>
                        <div class="form-text">Displayed in the navbar, page titles, login page, and emails.</div>
                    </div>
                </div>
            </div>

            <!-- Colors -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-palette me-1"></i>Color Scheme
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="primaryColor" class="form-label">Primary Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="primaryColorPicker"
                                       value="<?= e($primaryColor) ?>" title="Pick primary color">
                                <input type="text" class="form-control" id="primaryColor" name="primary_color"
                                       value="<?= e($primaryColor) ?>" pattern="^#[0-9a-fA-F]{6}$" required>
                            </div>
                            <div class="form-text">Buttons, links, active states.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="primaryHover" class="form-label">Primary Hover Color</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="primaryHoverPicker"
                                       value="<?= e($primaryHover) ?>" title="Pick hover color">
                                <input type="text" class="form-control" id="primaryHover" name="primary_hover"
                                       value="<?= e($primaryHover) ?>" pattern="^#[0-9a-fA-F]{6}$" required>
                            </div>
                            <div class="form-text">Button hover and focus states.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="navbarStart" class="form-label">Navbar Gradient Start</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="navbarStartPicker"
                                       value="<?= e($navbarStart) ?>" title="Pick start color">
                                <input type="text" class="form-control" id="navbarStart" name="navbar_start"
                                       value="<?= e($navbarStart) ?>" pattern="^#[0-9a-fA-F]{6}$" required>
                            </div>
                            <div class="form-text">Left side of the navbar gradient.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="navbarEnd" class="form-label">Navbar Gradient End</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="navbarEndPicker"
                                       value="<?= e($navbarEnd) ?>" title="Pick end color">
                                <input type="text" class="form-control" id="navbarEnd" name="navbar_end"
                                       value="<?= e($navbarEnd) ?>" pattern="^#[0-9a-fA-F]{6}$" required>
                            </div>
                            <div class="form-text">Right side of the navbar gradient.</div>
                        </div>
                    </div>

                    <hr>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="resetDefaults">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Defaults
                    </button>
                </div>
            </div>

            <!-- Timeline Colors -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-clock-history me-1"></i>Timeline Colors
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Customise the highlight colors for internal notes (added by agents) and system automation entries (SLA changes, auto-assignments, etc.).
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Internal Note Background</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="timelineNoteBgPicker"
                                       value="<?= e($timelineNoteBg) ?>" title="Pick color">
                                <input type="text" class="form-control" id="timelineNoteBg" name="timeline_note_bg"
                                       value="<?= e($timelineNoteBg) ?>" pattern="^#[0-9a-fA-F]{6}$" required>
                            </div>
                            <div class="form-text">Row background for internal agent notes.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Internal Note Accent</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="timelineNoteAccentPicker"
                                       value="<?= e($timelineNoteAccent) ?>" title="Pick color">
                                <input type="text" class="form-control" id="timelineNoteAccent" name="timeline_note_accent"
                                       value="<?= e($timelineNoteAccent) ?>" pattern="^#[0-9a-fA-F]{6}$" required>
                            </div>
                            <div class="form-text">Left border and icon color for internal notes.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">System Entry Background</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="timelineSystemBgPicker"
                                       value="<?= e($timelineSystemBg) ?>" title="Pick color">
                                <input type="text" class="form-control" id="timelineSystemBg" name="timeline_system_bg"
                                       value="<?= e($timelineSystemBg) ?>" pattern="^#[0-9a-fA-F]{6}$" required>
                            </div>
                            <div class="form-text">Row background for automated system entries.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">System Entry Accent</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="timelineSystemAccentPicker"
                                       value="<?= e($timelineSystemAccent) ?>" title="Pick color">
                                <input type="text" class="form-control" id="timelineSystemAccent" name="timeline_system_accent"
                                       value="<?= e($timelineSystemAccent) ?>" pattern="^#[0-9a-fA-F]{6}$" required>
                            </div>
                            <div class="form-text">Left border color for automated system entries.</div>
                        </div>
                    </div>

                    <!-- Timeline preview -->
                    <div class="mt-4 border rounded overflow-hidden" style="font-size:.875rem;">
                        <div class="px-3 py-2 border-bottom bg-white fw-semibold text-muted small">Preview</div>
                        <div class="px-3 py-2 border-bottom" id="previewNoteRow"
                             style="background:<?= e($timelineNoteBg) ?>; border-left:3px solid <?= e($timelineNoteAccent) ?> !important;">
                            <i class="bi bi-lock-fill me-2" id="previewNoteIcon" style="color:<?= e($timelineNoteAccent) ?>;"></i>
                            <strong>Agent Name</strong> <span class="text-muted ms-1">Internal Note</span>
                            <span class="badge ms-1 small" id="previewNoteBadge" style="background:<?= e($timelineNoteAccent) ?>; color:#fff;">Internal</span>
                        </div>
                        <div class="px-3 py-2 border-bottom" id="previewSystemRow"
                             style="background:<?= e($timelineSystemBg) ?>; border-left:3px solid <?= e($timelineSystemAccent) ?> !important;">
                            <i class="bi bi-stopwatch me-2" style="color:#6c757d;"></i>
                            <strong>System</strong> <span class="text-muted ms-1">SLA Set</span>
                        </div>
                        <div class="px-3 py-2">
                            <i class="bi bi-chat-dots text-info me-2"></i>
                            <strong>Alex Johnson</strong> <span class="text-muted ms-1">Comment</span>
                        </div>
                    </div>

                    <hr>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="resetTimelineDefaults">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Timeline to Defaults
                    </button>
                </div>
            </div>

            <button type="submit" class="btn text-white px-4" style="background:var(--ld-primary);">
                <i class="bi bi-check-lg me-1"></i>Save Branding
            </button>
        </div>

        <!-- Right column: Live Preview -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm sticky-top" style="top:80px;">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-eye me-1"></i>Live Preview
                </div>
                <div class="card-body p-0">
                    <!-- Navbar preview -->
                    <div id="previewNavbar" class="d-flex align-items-center px-3 text-white"
                         style="height:48px; border-radius:.375rem .375rem 0 0; background:linear-gradient(135deg, <?= e($navbarStart) ?> 0%, <?= e($navbarEnd) ?> 100%);">
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($logo && file_exists(ROOT_DIR . '/public/uploads/branding/' . $logo)): ?>
                                <img id="previewLogoImg" src="/uploads/branding/<?= e($logo) ?>" alt="" style="height:24px;">
                            <?php else: ?>
                                <i class="bi bi-headset" id="previewLogoIcon"></i>
                            <?php endif; ?>
                            <span class="fw-bold" id="previewAppName"><?= e($appName) ?></span>
                        </div>
                        <div class="ms-auto d-flex gap-2 small opacity-75">
                            <span>Admin</span>
                            <span>Agent</span>
                            <span>Portal</span>
                        </div>
                    </div>
                    <!-- Content preview -->
                    <div class="p-3" style="background:#f1f5f9;">
                        <div class="mb-3">
                            <button type="button" class="btn btn-sm text-white preview-btn" id="previewPrimaryBtn"
                                    style="background:<?= e($primaryColor) ?>;">
                                Primary Button
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary ms-1">Secondary</button>
                        </div>
                        <div class="d-flex gap-2 mb-3">
                            <span class="badge preview-badge" id="previewBadge"
                                  style="background:<?= e($primaryColor) ?>; color:#fff;">Active</span>
                            <span class="badge bg-secondary">Inactive</span>
                        </div>
                        <div class="small text-muted">
                            Page title bar and accent elements will use the primary color.
                        </div>
                    </div>
                    <!-- Login preview -->
                    <div id="previewLogin" class="p-3 text-center text-white small"
                         style="border-radius:0 0 .375rem .375rem; background:linear-gradient(135deg, <?= e($navbarStart) ?> 0%, <?= e($navbarEnd) ?> 50%, <?= e($primaryColor) ?> 100%);">
                        Login page background preview
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    // Sync color pickers with text inputs
    var pairs = [
        ['primaryColorPicker',        'primaryColor'],
        ['primaryHoverPicker',        'primaryHover'],
        ['navbarStartPicker',         'navbarStart'],
        ['navbarEndPicker',           'navbarEnd'],
        ['timelineNoteBgPicker',      'timelineNoteBg'],
        ['timelineNoteAccentPicker',  'timelineNoteAccent'],
        ['timelineSystemBgPicker',    'timelineSystemBg'],
        ['timelineSystemAccentPicker','timelineSystemAccent'],
    ];

    pairs.forEach(function (pair) {
        var picker = document.getElementById(pair[0]);
        var text   = document.getElementById(pair[1]);
        if (!picker || !text) return;

        picker.addEventListener('input', function () {
            text.value = picker.value;
            updatePreview();
            updateTimelinePreview();
        });
        text.addEventListener('input', function () {
            if (/^#[0-9a-fA-F]{6}$/.test(text.value)) {
                picker.value = text.value;
                updatePreview();
                updateTimelinePreview();
            }
        });
    });

    // App name live preview
    var appNameInput = document.getElementById('appName');
    appNameInput.addEventListener('input', function () {
        document.getElementById('previewAppName').textContent = appNameInput.value || 'LocalDesk';
    });

    // Remove logo button
    var removeLogoBtn  = document.getElementById('removeLogoBtn');
    var removeLogoFlag = document.getElementById('removeLogoFlag');
    var logoCurrentRow = document.getElementById('logoCurrentRow');
    var logoDefaultRow = document.getElementById('logoDefaultRow');
    if (removeLogoBtn) {
        removeLogoBtn.addEventListener('click', function () {
            removeLogoFlag.value = '1';
            logoCurrentRow.style.display = 'none';
            logoDefaultRow.style.display = '';
            // Also revert the navbar preview to icon
            var previewImg  = document.getElementById('previewLogoImg');
            var previewIcon = document.getElementById('previewLogoIcon');
            if (previewImg)  { previewImg.src = ''; previewImg.style.display = 'none'; }
            if (previewIcon) previewIcon.style.display = '';
        });
    }

    // Logo file preview
    var logoInput = document.getElementById('logo');
    logoInput.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                var icon = document.getElementById('previewLogoIcon');
                var img  = document.getElementById('previewLogoImg');
                if (icon) icon.style.display = 'none';
                if (img) {
                    img.src = e.target.result;
                } else {
                    var newImg = document.createElement('img');
                    newImg.id = 'previewLogoImg';
                    newImg.style.height = '24px';
                    newImg.src = e.target.result;
                    var nameEl = document.getElementById('previewAppName');
                    nameEl.parentNode.insertBefore(newImg, nameEl);
                }
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    function updatePreview() {
        var primary  = document.getElementById('primaryColor').value;
        var navStart = document.getElementById('navbarStart').value;
        var navEnd   = document.getElementById('navbarEnd').value;

        document.getElementById('previewNavbar').style.background =
            'linear-gradient(135deg, ' + navStart + ' 0%, ' + navEnd + ' 100%)';
        document.getElementById('previewPrimaryBtn').style.background = primary;
        document.getElementById('previewBadge').style.background = primary;
        document.getElementById('previewLogin').style.background =
            'linear-gradient(135deg, ' + navStart + ' 0%, ' + navEnd + ' 50%, ' + primary + ' 100%)';
    }

    function updateTimelinePreview() {
        var noteBg      = document.getElementById('timelineNoteBg').value;
        var noteAccent  = document.getElementById('timelineNoteAccent').value;
        var systemBg    = document.getElementById('timelineSystemBg').value;
        var systemAccent= document.getElementById('timelineSystemAccent').value;

        var noteRow = document.getElementById('previewNoteRow');
        if (noteRow) {
            noteRow.style.background   = noteBg;
            noteRow.style.borderLeft   = '3px solid ' + noteAccent;
        }
        var noteIcon = document.getElementById('previewNoteIcon');
        if (noteIcon) noteIcon.style.color = noteAccent;
        var noteBadge = document.getElementById('previewNoteBadge');
        if (noteBadge) noteBadge.style.background = noteAccent;

        var systemRow = document.getElementById('previewSystemRow');
        if (systemRow) {
            systemRow.style.background = systemBg;
            systemRow.style.borderLeft = '3px solid ' + systemAccent;
        }
    }

    // Reset main colors to defaults
    document.getElementById('resetDefaults').addEventListener('click', function () {
        var defaults = {
            primaryColor: '#4f46e5',
            primaryHover: '#4338ca',
            navbarStart:  '#1e1b4b',
            navbarEnd:    '#312e81'
        };
        for (var key in defaults) {
            document.getElementById(key).value = defaults[key];
            document.getElementById(key + 'Picker').value = defaults[key];
        }
        document.getElementById('appName').value = 'LocalDesk';
        document.getElementById('previewAppName').textContent = 'LocalDesk';
        updatePreview();
    });

    // Reset timeline colors to defaults
    document.getElementById('resetTimelineDefaults').addEventListener('click', function () {
        var defaults = {
            timelineNoteBg:       '#fefce8',
            timelineNoteAccent:   '#ca8a04',
            timelineSystemBg:     '#eff6ff',
            timelineSystemAccent: '#3b82f6'
        };
        for (var key in defaults) {
            document.getElementById(key).value = defaults[key];
            document.getElementById(key + 'Picker').value = defaults[key];
        }
        updateTimelinePreview();
    });
})();
</script>

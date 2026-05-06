<?php
$isEdit  = !empty($editing);
$action  = $isEdit
    ? '/agent/banners/' . (int) $editing['id'] . '/edit'
    : '/agent/banners/create';

$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Status Banner' : 'New Status Banner';
$sidebarItems = Auth::role() === 'power_user' ? powerUserSidebar('banners') : agentSidebar('banners');
$breadcrumbs  = [
    ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'Status Banners', 'url' => '/agent/banners'],
    ['label' => $isEdit ? 'Edit' : 'New'],
];

$titleVal     = old('title',       $isEdit ? (string) ($editing['title']     ?? '') : '');
$bodyVal      = old('body_html',   $isEdit ? (string)   $editing['body_html']       : '');
$severityVal  = old('severity',    $isEdit ? (string)   $editing['severity']        : 'warning');
$locationVal  = old('location_id', $isEdit ? (string) ($editing['location_id'] ?? '') : '');
$startsVal    = old('starts_at',   $isEdit && $editing['starts_at']  ? date('Y-m-d\TH:i', strtotime($editing['starts_at']))  : '');
$expiresVal   = old('expires_at',  $isEdit && $editing['expires_at'] ? date('Y-m-d\TH:i', strtotime($editing['expires_at'])) : '');
$activeVal    = $isEdit ? (int) $editing['is_active'] === 1 : true;
?>
<link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.css">
<script type="importmap">
{"imports":{"ckeditor5":"https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.js","ckeditor5/":"https://cdn.ckeditor.com/ckeditor5/43.3.1/"}}
</script>
<style>
.ck.ck-editor__editable { min-height: 220px; }
.ck.ck-toolbar { border-radius: .375rem .375rem 0 0 !important; border-color: #dee2e6 !important; }
.ck.ck-editor__editable { border-radius: 0 0 .375rem .375rem !important; border-color: #dee2e6 !important; }
[data-bs-theme="dark"] .ck.ck-toolbar,
[data-bs-theme="dark"] .ck.ck-toolbar__separator { background: #2b3035 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-button:not(.ck-disabled):hover,
[data-bs-theme="dark"] .ck.ck-button.ck-on { background: #373b3e !important; }
[data-bs-theme="dark"] .ck.ck-button { color: #dee2e6 !important; }
[data-bs-theme="dark"] .ck.ck-icon { color: #dee2e6 !important; }
[data-bs-theme="dark"] .ck.ck-editor__editable { background: #212529 !important; color: #dee2e6 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-editor__editable:not(.ck-focused) { border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-list { background: #2b3035 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-list__item .ck-button:hover { background: #373b3e !important; }
[data-bs-theme="dark"] .ck.ck-dropdown__panel { background: #2b3035 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-label,
[data-bs-theme="dark"] .ck.ck-heading_paragraph,
[data-bs-theme="dark"] .ck.ck-list__item .ck-button .ck-button__label { color: #dee2e6 !important; }
[data-bs-theme="dark"] .ck.ck-input { background: #212529 !important; color: #dee2e6 !important; border-color: #495057 !important; }
[data-bs-theme="dark"] .ck.ck-balloon-panel { background: #2b3035 !important; border-color: #495057 !important; }

.severity-card {
    cursor: pointer;
    border: 2px solid #dee2e6;
    border-radius: .5rem;
    padding: .75rem;
    transition: border-color .15s ease, background .15s ease;
}
.severity-card input { display: none; }
.severity-card .severity-icon { font-size: 1.5rem; }
.severity-card.is-info     { color: #055160; }
.severity-card.is-warning  { color: #664d03; }
.severity-card.is-critical { color: #842029; }
.severity-card:has(input:checked).is-info     { border-color: #0dcaf0; background: #cff4fc; }
.severity-card:has(input:checked).is-warning  { border-color: #ffc107; background: #fff3cd; }
.severity-card:has(input:checked).is-critical { border-color: #dc3545; background: #f8d7da; }
</style>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="/agent/banners" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit Status Banner' : 'Post Status Banner' ?></h2>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>" id="banner-form">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="title" class="form-label fw-semibold">Title <span class="text-muted fw-normal">(optional)</span></label>
                <input type="text" class="form-control" id="title" name="title" maxlength="255"
                       value="<?= e($titleVal) ?>"
                       placeholder="e.g. Network issue at Eastside, ETA 2pm">
                <div class="form-text">Shown in bold above the message body.</div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold d-block">Severity</label>
                <div class="row g-2">
                    <?php foreach ([
                        'info'     => ['label' => 'Info',     'icon' => 'bi-info-circle-fill',         'desc' => 'FYI / planned maintenance'],
                        'warning'  => ['label' => 'Warning',  'icon' => 'bi-exclamation-triangle-fill','desc' => 'Active issue, workarounds available'],
                        'critical' => ['label' => 'Critical', 'icon' => 'bi-exclamation-octagon-fill', 'desc' => 'Major outage, no workaround'],
                    ] as $key => $sev): ?>
                    <div class="col-md-4">
                        <label class="severity-card is-<?= $key ?> d-flex gap-2 align-items-start mb-0">
                            <input type="radio" name="severity" value="<?= $key ?>" <?= $severityVal === $key ? 'checked' : '' ?>>
                            <i class="bi <?= $sev['icon'] ?> severity-icon"></i>
                            <div>
                                <div class="fw-semibold"><?= e($sev['label']) ?></div>
                                <div class="small"><?= e($sev['desc']) ?></div>
                            </div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
                <div id="banner-editor"></div>
                <input type="hidden" id="body_html" name="body_html" value="<?= e($bodyVal) ?>">
                <div id="banner-editor-error" class="text-danger small mt-1" style="display:none;">Banner body is required.</div>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="location_id" class="form-label fw-semibold">Branch</label>
                    <select class="form-select" id="location_id" name="location_id">
                        <option value="">All branches (global)</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= (int) $loc['id'] ?>" <?= (string) $locationVal === (string) $loc['id'] ? 'selected' : '' ?>>
                                <?= e($loc['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Pick a branch to scope this banner to that location's portal users only.</div>
                </div>
                <div class="col-md-4">
                    <label for="starts_at" class="form-label fw-semibold">Show from <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="datetime-local" class="form-control" id="starts_at" name="starts_at" value="<?= e($startsVal) ?>">
                    <div class="form-text">Leave blank to show immediately.</div>
                </div>
                <div class="col-md-4">
                    <label for="expires_at" class="form-label fw-semibold">Auto-hide at <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="datetime-local" class="form-control" id="expires_at" name="expires_at" value="<?= e($expiresVal) ?>">
                    <div class="form-text">Leave blank to keep showing until cleared.</div>
                </div>
            </div>

            <?php if ($isEdit): ?>
            <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= $activeVal ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="is_active">Active (visible on portal)</label>
                <div class="form-text">Uncheck to clear the incident without deleting it.</div>
            </div>
            <?php endif; ?>

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-megaphone me-1"></i><?= $isEdit ? 'Save Changes' : 'Post Banner' ?>
                </button>
                <a href="/agent/banners" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script type="module">
import {
    ClassicEditor,
    Essentials,
    Bold, Italic, Underline,
    Link, AutoLink,
    List,
    BlockQuote,
    Heading,
    RemoveFormat,
    Paragraph
} from 'ckeditor5';

ClassicEditor.create(document.querySelector('#banner-editor'), {
    plugins: [
        Essentials, Paragraph, Heading,
        Bold, Italic, Underline,
        Link, AutoLink,
        List,
        BlockQuote,
        RemoveFormat
    ],
    toolbar: {
        items: [
            'heading', '|',
            'bold', 'italic', 'underline', 'removeFormat', '|',
            'bulletedList', 'numberedList', '|',
            'link', 'blockQuote', '|',
            'undo', 'redo'
        ]
    },
    heading: {
        options: [
            { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
            { model: 'heading2',  view: 'h3', title: 'Heading',     class: 'ck-heading_heading2' }
        ]
    },
    placeholder: 'e.g. Wi-Fi at Eastside is down. Tech is on site, ETA 2pm — please hold off opening duplicate tickets.',
    initialData: document.getElementById('body_html').value
}).then(editor => {
    window._bannerEditor = editor;
    document.getElementById('banner-form').addEventListener('submit', function (e) {
        const data = editor.getData();
        const text = data.replace(/<[^>]*>/g, '').trim();
        const err  = document.getElementById('banner-editor-error');
        if (!text) {
            e.preventDefault();
            err.style.display = '';
            editor.editing.view.focus();
            return;
        }
        err.style.display = 'none';
        document.getElementById('body_html').value = data;
    });
}).catch(console.error);
</script>

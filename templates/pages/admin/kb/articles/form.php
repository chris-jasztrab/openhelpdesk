<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Article' : 'Add Article';
$sidebarItems = Auth::isAdmin() ? adminSidebar('kb') : staffSidebar('kb-articles');
$breadcrumbs  = [
    Auth::isAdmin() ? ['label' => 'Admin', 'url' => '/admin'] : ['label' => 'Agent', 'url' => '/agent'],
    ['label' => 'KB Articles', 'url' => '/admin/kb/articles'],
    ['label' => $isEdit ? 'Edit' : 'Add'],
];
$action = $isEdit ? "/admin/kb/articles/{$editing['id']}/edit" : '/admin/kb/articles/create';

// Lazily migrate legacy markdown to HTML for the editor.
$rawBody = old('body_markdown', $editing['body_markdown'] ?? '');
if ($rawBody !== '' && ltrim($rawBody)[0] !== '<') {
    $rawBody = renderMarkdown($rawBody);
}
?>
<link rel="stylesheet" href="/assets/vendor/ckeditor5/ckeditor5.css">
<script type="importmap">
{"imports":{"ckeditor5":"/assets/vendor/ckeditor5/ckeditor5.js","ckeditor5/":"/assets/vendor/ckeditor5/"}}
</script>
<style>
.ck.ck-editor__editable { min-height: 420px; }
.ck.ck-toolbar { border-radius: .375rem .375rem 0 0 !important; border-color: #dee2e6 !important; }
.ck.ck-editor__editable { border-radius: 0 0 .375rem .375rem !important; border-color: #dee2e6 !important; }

/* Dark mode */
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
[data-bs-theme="dark"] .ck.ck-color-grid__tile:hover { border-color: #fff !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit Article' : 'Add Article' ?></h2>
    <a href="/admin/kb/articles" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="POST" action="<?= e($action) ?>" id="kb-form">
            <?= csrfField() ?>

            <div class="mb-3">
                <label for="title" class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title"
                       value="<?= e(old('title', $editing['title'] ?? '')) ?>" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="folder_id" class="form-label fw-semibold">Folder <span class="text-danger">*</span></label>
                    <select class="form-select" id="folder_id" name="folder_id" required>
                        <option value="">— Select folder —</option>
                        <?php foreach ($folders as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= old('folder_id', (string) ($editing['folder_id'] ?? '')) == $f['id'] ? 'selected' : '' ?>>
                            <?= e($f['category_name'] . ' / ' . $f['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label fw-semibold">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="draft" <?= old('status', $editing['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= old('status', $editing['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sort_order" class="form-label fw-semibold">Sort Order</label>
                    <input type="number" class="form-control" id="sort_order" name="sort_order" min="0"
                           value="<?= e(old('sort_order', (string) ($editing['sort_order'] ?? '0'))) ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Body <span class="text-danger">*</span></label>
                <div id="kb-editor"></div>
                <input type="hidden" id="body_markdown" name="body_markdown"
                       value="<?= e($rawBody) ?>">
                <div id="kb-editor-error" class="text-danger small mt-1" style="display:none;">Article body is required.</div>
            </div>

            <hr class="my-4">

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Article' : 'Create Article' ?>
                </button>
                <?php if ($isEdit): ?>
                <a href="/admin/kb/articles/<?= $editing['id'] ?>/preview" class="btn btn-outline-info">
                    <i class="bi bi-eye me-1"></i>Preview
                </a>
                <?php endif; ?>
                <a href="/admin/kb/articles" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script type="module">
import {
    ClassicEditor,
    Essentials,
    Heading,
    Bold, Italic, Underline, Strikethrough,
    FontColor, FontBackgroundColor, FontSize,
    Alignment,
    List, ListProperties,
    Link, AutoLink,
    Image, ImageUpload, Base64UploadAdapter,
    ImageCaption, ImageStyle, ImageToolbar, ImageResize,
    Table, TableToolbar, TableProperties, TableCellProperties,
    BlockQuote,
    Code, CodeBlock,
    HorizontalLine,
    Indent, IndentBlock,
    FindAndReplace,
    RemoveFormat
} from 'ckeditor5';

ClassicEditor.create(document.querySelector('#kb-editor'), {
    plugins: [
        Essentials,
        Heading,
        Bold, Italic, Underline, Strikethrough,
        FontColor, FontBackgroundColor, FontSize,
        Alignment,
        List, ListProperties,
        Link, AutoLink,
        Image, ImageUpload, Base64UploadAdapter,
        ImageCaption, ImageStyle, ImageToolbar, ImageResize,
        Table, TableToolbar, TableProperties, TableCellProperties,
        BlockQuote,
        Code, CodeBlock,
        HorizontalLine,
        Indent, IndentBlock,
        FindAndReplace,
        RemoveFormat
    ],
    toolbar: {
        items: [
            'heading', '|',
            'fontSize', 'fontColor', 'fontBackgroundColor', '|',
            'bold', 'italic', 'underline', 'strikethrough', 'removeFormat', '|',
            'alignment', '|',
            'bulletedList', 'numberedList', 'outdent', 'indent', '|',
            'link', 'insertImage', 'insertTable', 'blockQuote', 'codeBlock', 'horizontalLine', '|',
            'findAndReplace', 'undo', 'redo'
        ],
        shouldNotGroupWhenFull: true
    },
    heading: {
        options: [
            { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
            { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
            { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
            { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' }
        ]
    },
    image: {
        toolbar: [
            'imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|',
            'toggleImageCaption', 'imageTextAlternative', '|',
            'resizeImage'
        ]
    },
    table: {
        contentToolbar: [
            'tableColumn', 'tableRow', 'mergeTableCells', 'tableProperties', 'tableCellProperties'
        ]
    },
    initialData: document.getElementById('body_markdown').value
}).then(editor => {
    window._kbEditor = editor;

    document.getElementById('kb-form').addEventListener('submit', function (e) {
        const data  = editor.getData();
        const text  = data.replace(/<[^>]*>/g, '').trim();
        const errEl = document.getElementById('kb-editor-error');

        if (!text) {
            e.preventDefault();
            errEl.style.display = '';
            editor.editing.view.focus();
            return;
        }

        errEl.style.display = 'none';
        document.getElementById('body_markdown').value = data;
    });
}).catch(console.error);
</script>

<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Article' : 'Add Article';
$sidebarItems = adminSidebar('kb');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'KB Articles', 'url' => '/admin/kb/articles'],
    ['label' => $isEdit ? 'Edit' : 'Add'],
];
$action = $isEdit ? "/admin/kb/articles/{$editing['id']}/edit" : '/admin/kb/articles/create';

// Lazily migrate legacy markdown to HTML for the editor.
// If the stored content doesn't start with '<', it's still markdown — convert now.
$rawBody = old('body_markdown', $editing['body_markdown'] ?? '');
if ($rawBody !== '' && ltrim($rawBody)[0] !== '<') {
    $rawBody = renderMarkdown($rawBody);
}
?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<style>
/* Editor container sizing */
#kb-editor { min-height: 420px; font-size: 1rem; }
.ql-toolbar.ql-snow { border-radius: .375rem .375rem 0 0; border-color: #dee2e6; }
.ql-container.ql-snow { border-radius: 0 0 .375rem .375rem; border-color: #dee2e6; }
.ql-editor { min-height: 400px; }

/* Dark mode */
[data-bs-theme="dark"] .ql-toolbar.ql-snow,
[data-bs-theme="dark"] .ql-container.ql-snow { border-color: #495057; background: #2b3035; }
[data-bs-theme="dark"] .ql-editor { color: #dee2e6; background: #212529; }
[data-bs-theme="dark"] .ql-toolbar .ql-stroke { stroke: #adb5bd; }
[data-bs-theme="dark"] .ql-toolbar .ql-fill { fill: #adb5bd; }
[data-bs-theme="dark"] .ql-toolbar .ql-picker { color: #adb5bd; }
[data-bs-theme="dark"] .ql-toolbar .ql-picker-options { background: #2b3035; border-color: #495057; }
[data-bs-theme="dark"] .ql-toolbar button:hover .ql-stroke,
[data-bs-theme="dark"] .ql-toolbar button.ql-active .ql-stroke { stroke: #fff; }
[data-bs-theme="dark"] .ql-toolbar button:hover .ql-fill,
[data-bs-theme="dark"] .ql-toolbar button.ql-active .ql-fill { fill: #fff; }
[data-bs-theme="dark"] .ql-toolbar button:hover,
[data-bs-theme="dark"] .ql-toolbar button.ql-active,
[data-bs-theme="dark"] .ql-toolbar .ql-picker-label:hover,
[data-bs-theme="dark"] .ql-toolbar .ql-picker-label.ql-active { color: #fff; }
[data-bs-theme="dark"] .ql-snow .ql-tooltip { background: #2b3035; border-color: #495057; color: #dee2e6; box-shadow: none; }
[data-bs-theme="dark"] .ql-snow .ql-tooltip input[type="text"] { background: #212529; border-color: #495057; color: #dee2e6; }
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

<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
(function () {
    var quill = new Quill('#kb-editor', {
        theme: 'snow',
        placeholder: 'Write your article here…',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'code-block'],
                ['link'],
                ['clean']
            ]
        }
    });

    // Pre-populate editor with existing content
    var existing = document.getElementById('body_markdown').value;
    if (existing) {
        quill.clipboard.dangerouslyPasteHTML(existing);
    }

    // Sync editor content to hidden input on form submit
    document.getElementById('kb-form').addEventListener('submit', function (e) {
        var html   = quill.getSemanticHTML();
        var text   = quill.getText().trim();
        var errEl  = document.getElementById('kb-editor-error');

        if (!text) {
            e.preventDefault();
            errEl.style.display = '';
            quill.focus();
            return;
        }

        errEl.style.display = 'none';
        document.getElementById('body_markdown').value = html;
    });
})();
</script>

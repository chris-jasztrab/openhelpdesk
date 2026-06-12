<?php
$isEdit       = !empty($editing);
$isAgentView  = !Auth::isAdmin();
$ticketsUrl   = $isAgentView ? '/agent/tickets' : '/admin/tickets';
$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Template' : 'New Template';
$sidebarItems = Auth::isAdmin() ? adminSidebar('tickets') : staffSidebar('tickets');
$breadcrumbs  = $isAgentView ? [
    ['label' => 'Agent',     'url' => '/agent'],
    ['label' => 'Tickets',   'url' => '/agent/tickets'],
    ['label' => 'Templates', 'url' => '/admin/ticket-templates'],
    ['label' => $isEdit ? 'Edit' : 'New'],
] : [
    ['label' => 'Admin',     'url' => '/admin'],
    ['label' => 'Tickets',   'url' => '/admin/tickets'],
    ['label' => 'Templates', 'url' => '/admin/ticket-templates'],
    ['label' => $isEdit ? 'Edit' : 'New'],
];
$action = $isEdit
    ? "/admin/ticket-templates/{$editing['id']}/edit"
    : '/admin/ticket-templates/create';

// Lazily migrate legacy plain-text bodies to HTML for the editor.
$rawBody = old('body', $editing['body'] ?? '');
if ($rawBody !== '' && ltrim($rawBody)[0] !== '<') {
    $rawBody = '<p>' . nl2br(e($rawBody)) . '</p>';
}
?>
<link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.css">
<script type="importmap">
{"imports":{"ckeditor5":"https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.js","ckeditor5/":"https://cdn.ckeditor.com/ckeditor5/43.3.1/"}}
</script>
<style>
.ck.ck-editor__editable { min-height: 200px; }
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
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit Template' : 'New Template' ?></h2>
    <a href="/admin/ticket-templates" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <form method="POST" action="<?= e($action) ?>" id="tpl-form">
            <?= csrfField() ?>

            <!-- Template Meta -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-collection me-1"></i>Template Details
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">
                            Template Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?= e(old('name', $editing['name'] ?? '')) ?>"
                               placeholder="e.g. New Staff Onboarding" required>
                        <div class="form-text">A short, descriptive name shown in the template picker.</div>
                    </div>
                    <div class="mb-0">
                        <label for="description" class="form-label fw-semibold">Internal Notes</label>
                        <textarea class="form-control" id="description" name="description"
                                  rows="2" placeholder="When should this template be used? (optional)"><?= e(old('description', $editing['description'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Pre-filled Ticket Content -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-ticket-detailed me-1"></i>Pre-filled Ticket Content
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="subject" class="form-label fw-semibold">Subject</label>
                        <input type="text" class="form-control" id="subject" name="subject"
                               value="<?= e(old('subject', $editing['subject'] ?? '')) ?>"
                               placeholder="Pre-filled ticket subject">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <div id="tpl-body-editor"></div>
                        <input type="hidden" id="body" name="body" value="<?= e($rawBody) ?>">
                        <div class="form-text">Pre-filled ticket description. Use placeholders like [Employee Name] for fields the submitter should fill in. The submitter can edit this text before submitting.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="type_id" class="form-label fw-semibold">Ticket Type</label>
                            <select class="form-select" id="type_id" name="type_id">
                                <option value="">— None —</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t['id'] ?>"
                                        <?= (int) old('type_id', (string)($editing['type_id'] ?? '')) === (int) $t['id'] ? 'selected' : '' ?>>
                                        <?= e($t['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="priority_id" class="form-label fw-semibold">Priority</label>
                            <select class="form-select" id="priority_id" name="priority_id">
                                <option value="">— None —</option>
                                <?php foreach ($priorities as $pri): ?>
                                    <option value="<?= $pri['id'] ?>"
                                        <?= (int) old('priority_id', (string)($editing['priority_id'] ?? '')) === (int) $pri['id'] ? 'selected' : '' ?>>
                                        <?= e($pri['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sharing -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent fw-semibold">
                    <i class="bi bi-people me-1"></i>Sharing
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_shared" id="isShared"
                               value="1" <?= !empty($editing['is_shared']) || !empty($_POST['is_shared']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="isShared">
                            Share with portal users
                        </label>
                    </div>
                    <div class="form-text mt-1">
                        When enabled, portal users will see this template as a starting point when creating a new ticket.
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn text-white" style="background:var(--ld-primary);">
                    <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Update Template' : 'Create Template' ?>
                </button>
                <a href="/admin/ticket-templates" class="btn btn-outline-secondary">Cancel</a>
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

ClassicEditor.create(document.querySelector('#tpl-body-editor'), {
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
    initialData: document.getElementById('body').value
}).then(editor => {
    window._tplBodyEditor = editor;

    document.getElementById('tpl-form').addEventListener('submit', function () {
        const data = editor.getData();
        // An "empty" editor still emits markup; store a truly empty body instead.
        document.getElementById('body').value = data.replace(/<[^>]*>/g, '').trim() === '' && !data.includes('<img') ? '' : data;
    });
}).catch(console.error);
</script>

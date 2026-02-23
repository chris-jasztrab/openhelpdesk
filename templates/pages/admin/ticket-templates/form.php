<?php
$isEdit       = !empty($editing);
$layout       = 'app';
$pageTitle    = $isEdit ? 'Edit Template' : 'New Template';
$sidebarItems = adminSidebar('tickets');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Tickets', 'url' => '/admin/tickets'],
    ['label' => 'Templates', 'url' => '/admin/ticket-templates'],
    ['label' => $isEdit ? 'Edit' : 'New'],
];
$action = $isEdit
    ? "/admin/ticket-templates/{$editing['id']}/edit"
    : '/admin/ticket-templates/create';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0"><?= $isEdit ? 'Edit Template' : 'New Template' ?></h2>
    <a href="/admin/ticket-templates" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <form method="POST" action="<?= e($action) ?>">
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
                        <label for="body" class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="body" name="body"
                                  rows="8"
                                  placeholder="Pre-filled ticket description. Use placeholders like [Employee Name] for fields the submitter should fill in."><?= e(old('body', $editing['body'] ?? '')) ?></textarea>
                        <div class="form-text">The submitter can edit this text before submitting.</div>
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

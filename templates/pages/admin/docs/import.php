<?php
$layout       = 'app';
$pageTitle    = 'Docs: Ticket Import';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Ticket Import']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Ticket Import</h2>
<p class="text-muted mb-4">Migrate existing tickets from another system into LocalDesk using a CSV file.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-filetype-csv text-primary me-2"></i>Supported Format</h5>
<p class="text-muted mb-2">LocalDesk accepts any CSV file with a header row. The column names do not need to match exactly — you will map your columns to the correct LocalDesk fields in the next step.</p>
<p class="text-muted mb-0">The importer supports common exports from Freshdesk, Zendesk, and generic CSV formats. Files must be UTF-8 encoded.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-list-columns text-primary me-2"></i>LocalDesk Fields</h5>
<p class="text-muted mb-2">The following fields can be mapped from your CSV:</p>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Field</th><th>Required</th><th>Notes</th></tr></thead>
    <tbody class="text-muted">
        <tr><td class="fw-semibold">Subject</td><td><span class="badge bg-danger">Required</span></td><td>The ticket title.</td></tr>
        <tr><td class="fw-semibold">Requester Email</td><td><span class="badge bg-danger">Required</span></td><td>Used to look up or create the requester account.</td></tr>
        <tr><td class="fw-semibold">Description</td><td><span class="badge bg-secondary">Optional</span></td><td>Ticket body / description. Falls back to "(Imported from legacy system)" if blank.</td></tr>
        <tr><td class="fw-semibold">Status</td><td><span class="badge bg-secondary">Optional</span></td><td>Accepts open, in_progress, pending, resolved, closed. Defaults to <em>open</em>.</td></tr>
        <tr><td class="fw-semibold">Priority</td><td><span class="badge bg-secondary">Optional</span></td><td>Matched to an existing priority by name. Defaults to your first priority if not matched.</td></tr>
        <tr><td class="fw-semibold">Created Date</td><td><span class="badge bg-secondary">Optional</span></td><td>Preserves the original creation timestamp.</td></tr>
        <tr><td class="fw-semibold">Requester Name</td><td><span class="badge bg-secondary">Optional</span></td><td>Used when creating a new requester account.</td></tr>
        <tr><td class="fw-semibold">Assigned Agent Email</td><td><span class="badge bg-secondary">Optional</span></td><td>Must match an existing agent account email.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-card-checklist text-primary me-2"></i>Import Process</h5>
<ol class="text-muted mb-0">
    <li class="mb-2"><strong>Upload CSV</strong> — go to <a href="/admin/settings/import"><strong>Admin → Settings → Import Tickets</strong></a> and upload your CSV file.</li>
    <li class="mb-2"><strong>Map Columns</strong> — the importer shows all columns found in your CSV. Select which LocalDesk field each column should map to. Auto-detection will pre-select likely matches based on common column name patterns. Set any column to "— skip —" to ignore it.</li>
    <li class="mb-2"><strong>Preview</strong> — review a table of how your data will be imported. Any rows with problems (missing required fields, unmatched values) are highlighted.</li>
    <li><strong>Confirm Import</strong> — click <strong>Import Tickets</strong> to run the import. Results show how many tickets were created and how many were skipped.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-lines-fill text-primary me-2"></i>Requester Accounts</h5>
<p class="text-muted mb-2">During import, requester accounts are handled as follows:</p>
<ul class="text-muted mb-0">
    <li>If a user with the requester's email already exists, the ticket is linked to that account.</li>
    <li>If no account exists, a new <strong>User</strong> account is created using the email (and name if provided). The account will not have a password set — the user can reset their password via the portal's "Forgot Password" flow.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Important Notes</h5>
<ul class="text-muted mb-0">
    <li>Imported tickets do not trigger email notifications to requesters or agents.</li>
    <li>SLA timers are not automatically initialised on imported tickets — they will be applied if the priority is changed after import.</li>
    <li>The import cannot be undone from the UI — if you need to roll back, delete the imported tickets manually or restore your database backup.</li>
    <li>Test with a small sample file first to verify your column mapping is correct before importing a large dataset.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->

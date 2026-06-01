<?php
$layout       = 'app';
$pageTitle    = 'Help: Canned Responses';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/agent/help'], ['label' => 'Canned Responses']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Canned Responses</h2>
<p class="text-muted mb-4">Save and reuse reply templates to answer common questions faster and more consistently.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-collection text-primary me-2"></i>Personal vs Global Responses</h5>
<p class="text-muted mb-2">Canned responses come in two types:</p>
<ul class="text-muted mb-0">
    <li><strong>Personal</strong> — created by you, visible only to you. You can create, edit, and delete these.</li>
    <li><strong>Global</strong> — created by an admin and available to all agents. You can use these but cannot edit or delete them.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-plus-circle text-primary me-2"></i>Creating a Canned Response</h5>
<ol class="text-muted mb-3">
    <li>Open <strong>My Profile</strong> from your name in the top-right corner, then click <a href="/agent/canned-responses"><strong>Manage Canned Responses</strong></a>.</li>
    <li>Click <strong>New Response</strong>.</li>
    <li>Enter a <strong>Title</strong> — this is the name you search for when inserting the response.</li>
    <li>Write the <strong>Body</strong> — use the token bar to insert dynamic placeholders (see below).</li>
    <li>Set a <strong>Sort Order</strong> number to control where it appears in the list (lower numbers appear first).</li>
    <li>Click <strong>Save</strong>.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-braces text-primary me-2"></i>Available Tokens</h5>
<p class="text-muted mb-2">Tokens are placeholders replaced with real data when a response is inserted into a reply. Click a token button to insert it at the cursor.</p>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Token</th><th>Replaced with</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><code>{{customer_first_name}}</code></td><td>Requester's first name</td></tr>
        <tr><td><code>{{customer_last_name}}</code></td><td>Requester's last name</td></tr>
        <tr><td><code>{{customer_full_name}}</code></td><td>Requester's full name</td></tr>
        <tr><td><code>{{customer_email}}</code></td><td>Requester's email address</td></tr>
        <tr><td><code>{{ticket_id}}</code></td><td>Ticket number</td></tr>
        <tr><td><code>{{ticket_subject}}</code></td><td>Ticket subject line</td></tr>
        <tr><td><code>{{agent_first_name}}</code></td><td>Your first name</td></tr>
        <tr><td><code>{{agent_full_name}}</code></td><td>Your full name</td></tr>
        <tr><td><code>{{org_name}}</code></td><td>Your organisation name (from branding settings)</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-cursor-text text-primary me-2"></i>Inserting a Response in a Reply</h5>
<ol class="text-muted mb-0">
    <li>Open a ticket and click <strong>Reply</strong> (or <strong>Add Note</strong>) to open the composer.</li>
    <li>Click the <strong>Canned Response</strong> button above the editor.</li>
    <li>Search or scroll to find the response you want.</li>
    <li>Click the response — its content is inserted into the editor with all tokens replaced.</li>
    <li>Edit the text as needed before sending.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pencil text-primary me-2"></i>Editing &amp; Deleting</h5>
<p class="text-muted mb-2">From the <a href="/agent/canned-responses"><strong>Canned Responses</strong></a> page, click <strong>Edit</strong> next to any of your personal responses to update its title, body, or sort order. Click <strong>Delete</strong> to permanently remove it.</p>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Global responses (created by an admin) cannot be edited or deleted by agents.
</div>
</div>
</div>

</div><!-- col -->
</div><!-- row -->

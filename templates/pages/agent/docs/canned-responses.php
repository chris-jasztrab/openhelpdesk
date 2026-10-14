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
<p class="text-muted mb-4">Save and reuse replies to answer common questions faster and more consistently.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-collection text-primary me-2"></i>Personal vs Global Responses</h5>
<ul class="text-muted mb-0">
    <li><strong>Personal</strong> &mdash; created by you and only visible to you. You can add, edit and delete them.</li>
    <li><strong>Global</strong> &mdash; created by an administrator for everyone. You can use them but not change them; they're listed read-only under <em>Global Responses</em>.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-plus-circle text-primary me-2"></i>Creating a Canned Response</h5>
<ol class="text-muted mb-0">
    <li>Open your account menu (your name, top-right) and choose <strong>My Profile</strong>.</li>
    <li>In the <strong>Canned Responses</strong> card, click <a href="/agent/canned-responses"><strong>Manage Canned Responses</strong></a>.</li>
    <li>Click <strong>Add Response</strong>.</li>
    <li>Enter a <strong>Title</strong> &mdash; this is what you'll search for when inserting it.</li>
    <li>Write the <strong>Body</strong>. Click a token under the box to insert it where your cursor is (see below).</li>
    <li>Optionally set a <strong>Sort Order</strong> &mdash; lower numbers are listed first.</li>
    <li>Click <strong>Create Response</strong>.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-braces text-primary me-2"></i>Available Tokens</h5>
<p class="text-muted mb-2">Tokens are placeholders that are filled in with the ticket's details when you insert the response.</p>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Token</th><th>Replaced with</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><code>{{customer_first_name}}</code></td><td>Requester's first name</td></tr>
        <tr><td><code>{{customer_last_name}}</code></td><td>Requester's last name</td></tr>
        <tr><td><code>{{customer_full_name}}</code></td><td>Requester's full name</td></tr>
        <tr><td><code>{{customer_email}}</code></td><td>Requester's email address</td></tr>
        <tr><td><code>{{ticket_id}}</code></td><td>Ticket number (e.g. #42)</td></tr>
        <tr><td><code>{{ticket_subject}}</code></td><td>Ticket subject</td></tr>
        <tr><td><code>{{agent_first_name}}</code></td><td>Your first name</td></tr>
        <tr><td><code>{{agent_full_name}}</code></td><td>Your full name</td></tr>
        <tr><td><code>{{org_name}}</code></td><td>Your organisation's name</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-cursor-text text-primary me-2"></i>Inserting a Response</h5>
<ol class="text-muted mb-0">
    <li>On a ticket, click <strong>Reply</strong>, <strong>Add Note</strong> or <strong>Forward</strong> to open the message box.</li>
    <li>Click <strong><i class="bi bi-chat-square-text"></i> Canned</strong> in the box's header.</li>
    <li>In the <em>Insert Canned Response</em> window, type to search by title or content. Your own responses are listed under <em>My Responses</em>, the shared ones under <em>Global</em>.</li>
    <li>Click a response &mdash; it's inserted where your cursor is, with the tokens filled in.</li>
    <li>Edit the text as needed, then send.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pencil text-primary me-2"></i>Editing &amp; Deleting</h5>
<p class="text-muted mb-0">On the <a href="/agent/canned-responses">Canned Responses</a> page, use the <i class="bi bi-pencil"></i> button next to one of your responses to change its title, body or sort order, then click <strong>Save Changes</strong>. Use the <i class="bi bi-trash"></i> button to delete it (you'll be asked to confirm). Global responses can only be changed by an administrator.</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->

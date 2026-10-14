<?php
$layout       = 'app';
$pageTitle    = 'Help: Manager Tools';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/agent/help'], ['label' => 'Manager Tools']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Manager Tools</h2>
<p class="text-muted mb-4">For staff who manage a group: keep your team's skills up to date, and where to find reports.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-stars text-primary me-2"></i>Manage My Team</h5>
<p class="text-muted mb-2">If an administrator has made you a <strong>manager</strong> of one or more groups, your account menu (your name, top-right) shows <strong>Manage My Team</strong>. It lists each group you manage with its member and skill counts, and two buttons:</p>
<ul class="text-muted mb-0">
    <li><strong>Assign skills to team</strong> &mdash; see <em>Team Skills</em> below.</li>
    <li><strong>Curate group skills</strong> &mdash; see <em>Group Skills</em> below.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-person-check text-primary me-2"></i>Team Skills</h5>
<p class="text-muted mb-2">A grid with one row per team member and one column per skill &mdash; your group's own skills plus the organisation-wide (<em>Global</em>) ones. Tick a box to give that person the skill, untick to remove it, then click <strong>Save assignments</strong>. <strong>Discard changes</strong> throws away anything you haven't saved.</p>
<p class="text-muted mb-0">Skills tell the helpdesk who can handle what. If your group hands out new tickets by skill, they go to a member who has every skill the ticket needs.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-mortarboard text-primary me-2"></i>Group Skills</h5>
<p class="text-muted mb-2">The list of skills for your group. Click <strong>Add Skill</strong> to create one (a <strong>Skill Name</strong>, an optional description and a sort order), then <strong>Create Skill</strong>. Use the edit and delete buttons on your group's own skills.</p>
<p class="text-muted mb-0">Global skills are listed for reference with an <em>Admin-only</em> lock &mdash; only an administrator can change them. Managers also can't add or remove group members or other managers; ask an administrator for that.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bar-chart text-primary me-2"></i>Reports</h5>
<p class="text-muted mb-0">If your permission level includes reports, open <strong><i class="bi bi-sliders"></i> Settings</strong> and look under <strong>Reports</strong> &mdash; including <em>Time Spent</em> and <em>Agent Time</em> for tickets that track time. From any report you can also set up a <strong>Scheduled Report</strong> to have it emailed to you regularly. The <a href="/agent/help/wallboard">Live Wallboard</a> (account menu &rarr; Wallboard) gives a real-time view of your team's queue.</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->

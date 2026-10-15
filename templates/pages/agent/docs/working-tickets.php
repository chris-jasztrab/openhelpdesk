<?php
$layout       = 'app';
$pageTitle    = 'Help: Working on Tickets';
$sidebarItems = agentSidebar('help');
$breadcrumbs  = [['label' => 'Help', 'url' => '/agent/help'], ['label' => 'Working on Tickets']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/agent-help-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Working on Tickets</h2>
<p class="text-muted mb-4">Everything you can do once you have a ticket open.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-signpost-2 text-primary me-2"></i>On this page</h5>
<div class="row text-muted small">
    <div class="col-sm-6">
        <ul class="mb-0">
            <li><a href="#layout">Ticket layout</a></li>
            <li><a href="#replying">Replies, notes &amp; mentions</a></li>
            <li><a href="#forwarding">Forwarding a ticket</a></li>
            <li><a href="#drafts">Drafts, undo &amp; collisions</a></li>
            <li><a href="#updating">Updating ticket fields</a></li>
            <li><a href="#closing">Closing a ticket</a></li>
            <li><a href="#resolution-note">Resolution notes</a></li>
            <li><a href="#time-tracking">Time tracking</a></li>
        </ul>
    </div>
    <div class="col-sm-6">
        <ul class="mb-0">
            <li><a href="#cc-watch">CC &amp; watching</a></li>
            <li><a href="#escalating">Escalating</a></li>
            <li><a href="#timeline">The timeline</a></li>
            <li><a href="#solution">Marking the solution</a></li>
            <li><a href="#merging">Merging &amp; splitting</a></li>
            <li><a href="#other-panels">SLA, survey &amp; similar tickets</a></li>
            <li><a href="#confidential">Confidential tickets</a></li>
        </ul>
    </div>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="layout">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-layout-three-columns text-primary me-2"></i>Ticket Layout</h5>
<p class="text-muted mb-2"><strong>The header</strong> shows the subject, status, priority and SLA badges, and the ticket number. When a colleague has the same ticket open, a coloured pill appears under the subject (see <a href="#drafts">Drafts, undo &amp; collisions</a>). The action buttons sit at the top-right: <strong>Watch</strong>, <strong>Escalate</strong>, <strong>Split</strong>, <strong>Merge</strong>, <strong>Close</strong> and <strong>Back</strong>. The header stays pinned below the top bar as you scroll. <strong>Back</strong> returns you to wherever you opened the ticket from &mdash; the list, the dashboard or your notifications.</p>
<p class="text-muted mb-2">Below the header are three columns:</p>
<ul class="text-muted mb-0">
    <li><strong>Left</strong> &mdash; the description, the <strong>Reply</strong> / <strong>Forward</strong> / <strong>Add Note</strong> buttons, any files attached when the ticket was created, and the timeline.</li>
    <li><strong>Middle</strong> &mdash; <strong>Details</strong> (status, priority, type, assignee, group, escalation level, requester, location, created and due dates), then Escalation History, Satisfaction Survey and Custom Fields when they apply.</li>
    <li><strong>Right</strong> &mdash; <strong>CC</strong>, <strong>Update Ticket</strong>, <strong>Time Tracking</strong> (for some ticket types) and <strong>SLA</strong>.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="replying">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-chat-dots text-primary me-2"></i>Replies, Notes &amp; Mentions</h5>
<p class="text-muted mb-2">The same three buttons sit directly under the description and again below the last timeline entry. The message box opens under whichever set you click, and switching sets keeps what you've typed:</p>
<ul class="text-muted mb-3">
    <li><strong>Reply</strong> &mdash; a public message. The requester (and anyone CC'd) is emailed and sees it on their portal page.</li>
    <li><strong>Add Note</strong> &mdash; an internal note for staff only. The box turns yellow and is titled <em>Internal Note</em>. Requesters never see notes.</li>
    <li><strong>Forward</strong> &mdash; email the ticket to someone else (see <a href="#forwarding">below</a>). Only shown if you're allowed to forward.</li>
</ul>
<ul class="text-muted mb-0">
    <li><strong>Formatting &amp; files</strong> &mdash; use the toolbar for bold, lists and links. Attach files with the file picker under the editor, or paste/drop an image straight into the text.</li>
    <li><strong>Canned responses</strong> &mdash; click <strong>Canned</strong> in the box's header to insert a saved reply. See <a href="/agent/help/canned-responses">Canned Responses</a>.</li>
    <li><strong>@mentions</strong> &mdash; type <code>@</code> and start a colleague's name, then pick them from the list. They get a notification pointing to your message.</li>
    <li><strong>Taking the ticket</strong> &mdash; posting a public reply on an unassigned ticket assigns it to you.</li>
    <li><strong>Send &amp; change status</strong> &mdash; the arrow beside <strong>Send Reply</strong> lets you send and set the status in one step: <strong>Resolved</strong> (if your organisation uses it), <strong>Closed</strong>, <strong>Pending</strong>, <strong>Waiting on Customer</strong> or <strong>Waiting on Third Party</strong>.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="forwarding">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-forward text-primary me-2"></i>Forwarding a Ticket</h5>
<p class="text-muted mb-2"><strong>Forward</strong> emails the ticket's details, the full public conversation and <strong>all</strong> its attachments to one or more addresses &mdash; for example a vendor or another department. Enter the addresses separated by commas, add an optional message and extra files, then send.</p>
<ul class="text-muted mb-3">
    <li><strong>Replies come back to the ticket.</strong> Recipients are added to the ticket's CC list, so when they reply by email their message lands on the timeline. New addresses are saved as external contacts.</li>
    <li><strong>External replies are flagged</strong> with an <span class="badge bg-secondary">External</span> badge on the timeline, so you can tell they came from a third party.</li>
</ul>
<p class="text-muted mb-2">Forwarding is split into two permissions: <strong>internal contacts</strong> (people who already have an account) and <strong>external contacts</strong> (any other address). The hint under the address box tells you which you're allowed; if you include an address you can't forward to, the send is refused and those addresses are named.</p>
<div class="alert alert-warning small mb-0"><i class="bi bi-shield-lock me-2"></i>
    <strong>Confidential tickets can't be forwarded.</strong> If you need to share something, copy just the details you need into a separate email.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="drafts">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-broadcast text-primary me-2"></i>Drafts, Undo &amp; Collisions</h5>
<ul class="text-muted mb-3">
    <li><strong>Drafts save themselves.</strong> Whatever you type in the message box is saved as you go (<em>&ldquo;Draft saved&rdquo;</em> with the time). Come back later &mdash; even on another computer &mdash; and you'll see <em>&ldquo;Restored your unsent draft&rdquo;</em> with a <strong>Discard</strong> link. Tickets where you have a draft show a <span class="badge bg-warning bg-opacity-25 text-dark"><i class="bi bi-pencil-square me-1"></i>Draft</span> badge in the ticket list.</li>
    <li><strong>Undo send.</strong> If your organisation has turned it on, sending shows a countdown such as <em>&ldquo;Sending reply in 10s&hellip; Undo&rdquo;</em>. Click <strong>Undo</strong> or press <kbd>Esc</kbd> to get your message back. Nothing is emailed until the countdown ends.</li>
    <li><strong>The page updates itself.</strong> New replies, notes and changes from other people appear on the timeline with a brief highlight, without touching what you're typing.</li>
    <li><strong>See who else is here.</strong> A pill under the subject shows when a colleague has the ticket open &mdash; <span class="badge" style="background:#0d6efd;">blue</span> while they're viewing, <span class="badge" style="background:#b45309;">amber</span> while they're replying.</li>
</ul>
<h6 class="fw-semibold mb-2">Reply-collision warning</h6>
<p class="text-muted mb-2">If a colleague posts a public reply while you're writing yours, clicking <strong>Send Reply</strong> pauses and shows who replied, when, and part of what they said. Choose:</p>
<ul class="text-muted mb-0">
    <li><strong>Review their reply</strong> &mdash; opens the latest version of the ticket in a new tab. Your draft is kept.</li>
    <li><strong>Cancel</strong> &mdash; go back and adjust your reply.</li>
    <li><strong>Post anyway</strong> &mdash; send your reply as written.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="updating">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-sliders text-primary me-2"></i>Updating Ticket Fields</h5>
<p class="text-muted mb-2">Use the <strong>Update Ticket</strong> card on the right to change <strong>Status</strong>, <strong>Priority</strong>, <strong>Assigned To</strong>, <strong>Group</strong>, <strong>Type</strong> and <strong>Due Date</strong>. The <strong>Update</strong> button becomes clickable once you change something. Each change is recorded on the timeline. You can also reassign straight from the <strong>Assigned To</strong> line in the Details card.</p>
<ul class="text-muted mb-0">
    <li>Changing the <strong>Group</strong> immediately narrows <em>Assigned To</em> to that group's members.</li>
    <li>Changing the <strong>Type</strong> moves the ticket into that type's default group (if it has one). Moving it to a group that doesn't match its type clears the type to &ldquo;Not Set&rdquo;.</li>
    <li>Only the priorities allowed for the ticket's type are offered.</li>
    <li>The <strong>Due Date</strong> usually follows the SLA resolution date, shown as <em>SLA target</em> under the field. If you move it past the SLA target, a box asks you to tell the customer why and when to expect it, with a reply already drafted: <strong>Send reply &amp; save</strong> sends it as a normal public reply and saves the date, or choose <strong>Save without replying</strong>. Moving the due date doesn't change the SLA.</li>
    <li>If someone else changed the status after you opened the page, your status change is skipped and a message tells you what it was changed to. Your other changes are still saved.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="closing">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-check2-circle text-primary me-2"></i>Closing a Ticket</h5>
<p class="text-muted mb-2">The green <strong>Close</strong> button at the top-right closes the ticket and emails the requester the standard &ldquo;ticket closed&rdquo; notice. The arrow next to it offers other ways to close:</p>
<ul class="text-muted mb-3">
    <li><strong>Close without notifying</strong> &mdash; no email to the requester.</li>
    <li><strong>Close and notify</strong> &mdash; the same as the Close button.</li>
    <li><strong>Close and request feedback</strong> &mdash; sends the satisfaction survey instead (greyed out when surveys are turned off).</li>
    <li><strong>Close as duplicate&hellip;</strong> &mdash; search for the original ticket. This ticket then shows <em>&ldquo;Duplicate of #&hellip;&rdquo;</em> and the original gets an internal note.</li>
    <li><strong>Close as won&rsquo;t fix&hellip;</strong> &mdash; a reason is required.</li>
    <li><strong>Close as no response&hellip;</strong> &mdash; the requester stopped replying.</li>
    <li><strong>Close and merge&hellip;</strong> &mdash; opens <a href="#merging">Merge</a>.</li>
    <li><strong>Close and create KB article</strong> &mdash; closes the ticket, then opens a draft knowledge base article filled in with the problem and the resolution. Only shown if you can manage KB articles.</li>
    <li><strong>Close and reopen on reply&hellip;</strong> &mdash; if the requester replies within the number of days you choose (7 by default), the ticket reopens by itself. The header shows <em>&ldquo;Reopens on reply until &hellip;&rdquo;</em>.</li>
    <li><strong>Close and schedule follow-up&hellip;</strong> &mdash; pick a date and time (and an optional &ldquo;what to check&rdquo; note); you get a notification on this ticket when it's due.</li>
</ul>
<div class="alert alert-info small mb-2"><i class="bi bi-info-circle me-2"></i>
    Options ending in &hellip; open a box with a message. With <strong>Email the requester</strong> ticked, your message goes out as a reply (leave it blank to send the standard closed email). Unticked, the message is saved as an internal note and nothing is emailed.
</div>
<p class="text-muted small mb-0">Tickets closed as duplicate, won&rsquo;t fix or no response are not counted as &ldquo;resolved&rdquo; in reports. The Close button disappears once a ticket is Closed.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="resolution-note">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-journal-check text-primary me-2"></i>Resolution Notes</h5>
<p class="text-muted mb-2">Some ticket types ask the assigned person to record what fixed the problem. If you close a ticket assigned to you from the Update Ticket card or the Close button while it has no replies or notes yet, a box asks <em>&ldquo;Before you close &mdash; what resolved this?&rdquo;</em>, pre-filled with a short outline you can edit.</p>
<ul class="text-muted mb-0">
    <li><strong>Add resolution &amp; close</strong> &mdash; saves your note as an internal note (the requester isn't emailed it) and closes the ticket.</li>
    <li><strong>Close without a note</strong> &mdash; you'll be asked for a reason, such as <em>Duplicate of another ticket</em> or <em>Resolved elsewhere</em>.</li>
    <li><strong>Keep working</strong> &mdash; cancel and leave the ticket open.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="time-tracking">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hourglass-split text-primary me-2"></i>Time Tracking</h5>
<p class="text-muted mb-2">For ticket types that track time, a <strong>Time Tracking</strong> card appears under Update Ticket, showing the total and every entry with who logged it.</p>
<ul class="text-muted mb-0">
    <li><strong>Start timer</strong> / <strong>Stop timer</strong> &mdash; a live timer that keeps running if you reload or leave the page. You can only run one timer at a time: starting one on another ticket stops this one. Add an optional note before stopping.</li>
    <li><strong>Log time</strong> &mdash; enter hours, minutes, the date you did the work and an optional note, then <strong>Save</strong>.</li>
    <li><strong>Edit</strong> / <strong>Delete</strong> &mdash; available on your own entries.</li>
    <li>The card saves without reloading the page, so a half-written reply is safe.</li>
    <li>Closing a time-tracked ticket with no time logged asks whether to <strong>Log time</strong> first or <strong>Close without logging</strong>.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="cc-watch">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-people text-primary me-2"></i>CC &amp; Watching</h5>
<p class="text-muted mb-2"><strong>CC</strong> &mdash; in the CC card, type a name or email in <em>Search users to CC</em> and pick the person. They're emailed that they were added and then get email updates when the ticket is replied to. Click the &times; on a name to remove them. A colleague you CC can open the ticket even if it isn't in their group.</p>
<p class="text-muted mb-0"><strong>Watch</strong> &mdash; click <strong>Watch</strong> in the header to follow a ticket that isn't assigned to you; the button changes to <strong>Watching</strong>. Click it again to stop. Watched tickets appear under the <em>My Watched Tickets</em> filter. You can't watch confidential tickets.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="escalating">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-up-circle text-danger me-2"></i>Escalating</h5>
<p class="text-muted mb-2">The red <strong>Escalate</strong> button appears on ticket types that have an escalation path. Depending on your organisation's settings it may only appear once the ticket's SLA is breached.</p>
<ol class="text-muted mb-2">
    <li>Click <strong>Escalate</strong>. The box shows exactly who the ticket will go to and their level.</li>
    <li>Add a reason (optional, but it helps the next person).</li>
    <li>Click <strong>Escalate to <em>name</em></strong>. The ticket is reassigned to them and they're notified. The previous assignee is added as a watcher so they stay in the loop.</li>
</ol>
<p class="text-muted mb-0">The button is greyed out on resolved or closed tickets and when there's no one further up the path. Each escalation is listed in the <strong>Escalation History</strong> card, and the person it was escalated to sees it on their dashboard under <strong>Escalated to Me</strong>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="timeline">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clock-history text-primary me-2"></i>The Timeline</h5>
<p class="text-muted mb-2">The timeline lists the conversation and changes on the ticket:</p>
<ul class="text-muted mb-3">
    <li><strong>Replies</strong> &mdash; public messages from staff and the requester.</li>
    <li><strong>Internal notes</strong> &mdash; marked with a lock and an <strong>Internal</strong> badge. Never shown to the requester.</li>
    <li><strong>Changes</strong> &mdash; status, priority, assignment, merges and so on, with who made them. Automatic entries (such as SLA timer events) are only shown to admins.</li>
</ul>
<p class="text-muted mb-2">Files appear inside the entry they were attached to; images show as thumbnails you can click to open full size. Only the latest 10 entries are shown at first &mdash; click <strong>Show N older updates</strong> at the bottom to see the rest.</p>
<p class="text-muted mb-0"><strong>Changing the order:</strong> click the <strong>Timeline</strong> heading to switch between newest-first and oldest-first. Your choice is remembered for every ticket, and you can also set it under <em>Ticket Timeline</em> on your <a href="/profile">profile</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="solution">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-check-circle text-success me-2"></i>Marking the Solution</h5>
<p class="text-muted mb-2">When the answer is buried in a long conversation, flag the reply that solved it:</p>
<ul class="text-muted mb-2">
    <li>Click <strong>Mark as solution</strong> next to any public reply.</li>
    <li>A green <em>&ldquo;Solution available&rdquo;</em> bar with a <strong>Go to solution</strong> button appears at the top of the ticket &mdash; for staff and for the requester &mdash; and the reply gets a <span class="badge bg-success">Solution</span> badge.</li>
    <li>Only one reply can be the solution; marking another replaces it. <strong>Unmark solution</strong> clears it.</li>
</ul>
<p class="text-muted mb-0">Internal notes can't be marked. Marking a solution doesn't change the ticket's status &mdash; close it separately when you're ready.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="merging">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-intersect text-primary me-2"></i>Merging &amp; Splitting</h5>
<h6 class="fw-semibold mb-2">Merge</h6>
<p class="text-muted mb-2">Use <strong>Merge</strong> when the same request arrives as two tickets.</p>
<ol class="text-muted mb-3">
    <li>Click <strong>Merge</strong> (or choose <strong>Close and merge&hellip;</strong> from the Close menu).</li>
    <li>Search for the other ticket by number or subject and pick it.</li>
    <li>Choose which ticket should be the <strong>primary</strong> &mdash; the one you keep.</li>
    <li>Click <strong>Merge Tickets</strong>.</li>
</ol>
<p class="text-muted mb-3">The other ticket's replies, notes, attachments and original request <strong>move into the primary</strong> in date order, its CCs and tags are copied, and it's closed with a link to the primary. Public replies become visible to the primary's requester, and the original requester and anyone CC'd are emailed. Confidential tickets can only be merged with tickets of the same type.</p>
<h6 class="fw-semibold mb-2">Split</h6>
<p class="text-muted mb-2">Use <strong>Split</strong> when one ticket contains separate issues.</p>
<ol class="text-muted mb-0">
    <li>Click <strong>Split</strong>.</li>
    <li>Tick the public replies to move (<strong>Select All</strong> / <strong>Clear</strong> help).</li>
    <li>Fill in the new ticket's subject, description, type, priority, assignee and group.</li>
    <li>Click <strong>Split &amp; Create New Ticket</strong>. The ticked replies and their files move to the new ticket, which opens with status Open and the same location. Both timelines link to each other.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="other-panels">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-card-checklist text-primary me-2"></i>SLA, Survey &amp; Similar Tickets</h5>
<ul class="text-muted mb-0">
    <li><strong>SLA card</strong> &mdash; the SLA state (with a <em>Paused</em> badge while paused) and when the first response and resolution are due, or when you responded. Overdue times turn red. See <a href="/agent/help/sla">SLAs &amp; Response Times</a>.</li>
    <li><strong>Satisfaction Survey card</strong> &mdash; once a survey has been sent: the star rating and comment, or <em>&ldquo;awaiting response&rdquo;</em>, a <em>Reopened by requester</em> badge if they reopened it from the survey, and a link to the survey page.</li>
    <li><strong>Similar past tickets</strong> &mdash; if your organisation has turned this on for the ticket's type, a panel lists older tickets (including closed ones) that look like this one, with a short note on how each relates. Click <i class="bi bi-arrow-clockwise"></i> to search again.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="confidential">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Confidential Tickets</h5>
<p class="text-muted mb-2">Some ticket types (for example HR or payroll) are marked <strong>confidential</strong>. For those tickets:</p>
<ul class="text-muted mb-3">
    <li>Only members of the type's group &mdash; and the person who submitted it &mdash; can see or work on them. Being assigned, CC'd or holding &ldquo;view all tickets&rdquo; doesn't give access.</li>
    <li>They never appear in your lists, board or search if you don't have access.</li>
    <li>They can't be watched or forwarded, and can only be merged with tickets of the same type.</li>
    <li>An admin outside the group must re-enter their password to open one; the group is emailed when that happens.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    If you need access to a confidential ticket type, ask your administrator to add you to its group.
</div>
</div>
</div>

</div><!-- col -->
</div><!-- row -->

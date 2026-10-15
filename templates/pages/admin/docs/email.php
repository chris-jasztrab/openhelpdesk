<?php
$layout       = 'app';
$pageTitle    = 'Docs: Email & SMTP';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Email & SMTP']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Email &amp; SMTP</h2>
<p class="text-muted mb-4">Outgoing mail, replies by email, email-to-ticket, notification switches, templates and the email queue. Everything here lives under the <strong>Email</strong> group in Settings.</p>

<div class="card border-0 shadow-sm mb-4" id="smtp">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-check text-primary me-2"></i>SMTP Configuration</h5>
<p class="text-muted mb-2">Go to <a href="/admin/settings"><strong>Settings &rarr; Email &rarr; Email / SMTP</strong></a> and fill in <strong>Email / SMTP Configuration</strong>:</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Field</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td class="fw-semibold">SMTP Host</td><td class="text-muted">Your mail server hostname (e.g. <code>smtp.office365.com</code>).</td></tr>
        <tr><td class="fw-semibold">Port</td><td class="text-muted">Usually <code>587</code> for TLS or <code>465</code> for SSL.</td></tr>
        <tr><td class="fw-semibold">Encryption</td><td class="text-muted"><strong>TLS</strong> (recommended), <strong>SSL</strong> or <strong>None</strong>.</td></tr>
        <tr><td class="fw-semibold">Username / Password</td><td class="text-muted">The SMTP login. Leave Password blank to keep the saved one.</td></tr>
        <tr><td class="fw-semibold">From Address / From Name</td><td class="text-muted">What recipients see in the From line (e.g. "IT Support").</td></tr>
        <tr><td class="fw-semibold">SMTP Debug Logging</td><td class="text-muted">Logs every send attempt in detail to <code>storage/logs/smtp.log</code> (the SMTP password is masked). Turn it off once mail works.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted mb-0">Click <strong>Save Settings</strong>, then use the <strong>Test Email</strong> card to send a message to any address (it defaults to yours). If the test fails because outbound mail is switched off on the server, the message says so and the email waits in the <a href="#email-queue">Email Queue</a>.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-google text-primary me-2"></i>Common Providers</h5>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Provider</th><th>Host</th><th>Port</th><th>Encryption</th><th>Notes</th></tr></thead>
    <tbody>
        <tr>
            <td class="fw-semibold">Microsoft 365</td>
            <td><code>smtp.office365.com</code></td>
            <td><code>587</code></td>
            <td>TLS</td>
            <td class="text-muted">SMTP AUTH must be enabled for the mailbox in the Microsoft 365 admin centre.</td>
        </tr>
        <tr>
            <td class="fw-semibold">Gmail</td>
            <td><code>smtp.gmail.com</code></td>
            <td><code>587</code></td>
            <td>TLS</td>
            <td class="text-muted">Requires an <a href="https://support.google.com/accounts/answer/185833" target="_blank" rel="noopener">App Password</a> when 2FA is on.</td>
        </tr>
        <tr>
            <td class="fw-semibold">Mailgun</td>
            <td><code>smtp.mailgun.org</code></td>
            <td><code>587</code></td>
            <td>TLS</td>
            <td class="text-muted">Use the SMTP credentials from your Mailgun dashboard.</td>
        </tr>
        <tr>
            <td class="fw-semibold">SendGrid</td>
            <td><code>smtp.sendgrid.net</code></td>
            <td><code>587</code></td>
            <td>TLS</td>
            <td class="text-muted">Username is always <code>apikey</code>; the password is your API key.</td>
        </tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="outbound-switches">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-toggles text-primary me-2"></i>Outbound Mail Switches (<code>.env</code>)</h5>
<p class="text-muted mb-2">Two master switches decide whether mail leaves the server at all. They live in the server's <code>.env</code> file, not in the admin UI, so a stray click (or a compromised admin account) can't turn mail on for everyone.</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Setting</th><th>Default</th><th>Effect</th></tr></thead>
    <tbody>
        <tr>
            <td class="fw-semibold"><code>MAIL_ENABLED</code></td>
            <td><code>true</code></td>
            <td class="text-muted">Master kill switch. When <code>false</code>, outbound mail is held in the <a href="#email-queue">Email Queue</a> instead of being delivered, and each held message is noted in <code>storage/logs/smtp.log</code> as <code>SKIPPED</code>. Use <code>false</code> on dev, staging and test copies.</td>
        </tr>
        <tr>
            <td class="fw-semibold"><code>MAIL_TRANSACTIONAL_ENABLED</code></td>
            <td><code>false</code></td>
            <td class="text-muted">When <code>MAIL_ENABLED=false</code>, setting this to <code>true</code> still lets password-reset emails through (one recipient, requested by that person) while all ticket mail stays held. No effect when <code>MAIL_ENABLED=true</code>.</td>
        </tr>
    </tbody>
</table>
</div>
<p class="text-muted small mb-0">Both switches only decide <em>whether</em> mail is sent; delivery still uses the SMTP settings above.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="email-queue">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-paper text-primary me-2"></i>Email Queue</h5>
<p class="text-muted mb-2">Mail that can't be delivered is held rather than lost: mail blocked by <code>MAIL_ENABLED=false</code>, mail sent while SMTP isn't configured, and mail the SMTP server rejected. <a href="/admin/settings/email-queue"><strong>Settings &rarr; Email &rarr; Email Queue</strong></a> lists the newest 500 held messages with when they were queued, recipient, subject, ticket, reason and attempts.</p>
<ul class="text-muted mb-2">
    <li><strong>Send all now</strong> tries to deliver everything in the queue (even while <code>MAIL_ENABLED=false</code>, after you confirm). Sent messages leave the queue; failures stay.</li>
    <li><strong>Flush queue</strong> deletes every held message without sending it.</li>
</ul>
<div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-2"></i>
    Nothing drains the queue automatically, so turning <code>MAIL_ENABLED</code> back on never sends the backlog by itself. Review it before pressing <strong>Send all now</strong>: a backlog can hold weeks of stale notifications.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="inbound">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-arrow-down text-primary me-2"></i>Inbound Email (Reply by Email)</h5>
<p class="text-muted mb-2">Requesters and staff can reply to a ticket notification from their inbox, and the reply is added to the ticket. Inbound mail is read from a Microsoft 365 mailbox through the <strong>Microsoft Graph API</strong> (no IMAP or app passwords).</p>
<p class="text-muted mb-2"><strong>Nothing a person writes is dropped.</strong> A reply that can't go on its ticket opens a <strong>new ticket</strong> with their message instead: a requester replying to a <strong>Closed</strong> ticket (unless it was closed with <em>reopen on reply</em> and the window is still open), a reply from an address with no account, or a reply to a ticket that no longer exists. The new ticket keeps the original's type and group, and both tickets get an internal note linking them. Staff replies always go on the ticket, even a closed one. A reply to a merged ticket goes on the ticket it was merged into. When the requester replies, the assigned agent gets the same alert as for a portal comment.</p>
<ol class="text-muted mb-3">
    <li>Register an app in Microsoft Entra ID with the <code>Mail.Read</code> and <code>Mail.ReadWrite</code> <strong>application</strong> permissions and admin consent. The <a href="/admin/settings/email-reply-help">Email Reply Setup Guide</a> walks through it.</li>
    <li>On <a href="/admin/settings"><strong>Email / SMTP</strong></a>, in <strong>Inbound Mail &mdash; Reply Processing</strong>, tick <strong>Enable reply-by-email</strong> and fill in <strong>Reply-To Address</strong>, <strong>Mailbox Address</strong>, <strong>Tenant ID</strong>, <strong>Client ID (Application ID)</strong>, <strong>Client Secret</strong> and <strong>App Secret Expiry Date</strong>. Then click <strong>Save Inbound Settings</strong>.</li>
    <li>Schedule the <strong>Inbound Email Replies</strong> cron job (every 5 minutes; see <a href="/admin/settings/cron-jobs">Cron Jobs</a>). <strong>Run Now</strong> on the same card processes the mailbox immediately and shows the output.</li>
</ol>
<p class="text-muted mb-0"><strong>Secret expiry:</strong> with an App Secret Expiry Date saved, the card shows how long is left, admins see a reminder pop-up after signing in during the last 30 days, and the <strong>App Secret Expiry Reminders</strong> cron job emails all admins 30 days, 7 days and on the day of expiry.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="email-to-ticket">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-plus text-primary me-2"></i>Email-to-Ticket</h5>
<p class="text-muted mb-2">With <strong>Enable Email-to-Ticket</strong> switched on (Email-to-Ticket card), a message to the same mailbox that isn't a reply to an existing ticket becomes a new ticket. The subject becomes the ticket subject and the body becomes the description. Inbound mail must be configured first.</p>
<ul class="text-muted mb-0">
    <li><strong>Auto-create user accounts</strong>: unknown senders get an End User account. When this is off, new mail from unknown addresses is skipped. A <em>reply</em> to a ticket from an unknown address still gets an account and a ticket, so it isn't lost.</li>
    <li><strong>Default Ticket Type</strong> and <strong>Default Priority</strong> are applied to every emailed ticket. The group follows the type's default group, then the system default group from <a href="/admin/settings/ticket-routing">Ticket Routing</a>.</li>
    <li>New tickets run through AI routing and classification, ticket-created automations and auto-assignment, and the sender gets the usual confirmation email. If <em>Classify tickets created from inbound email</em> is off under <a href="/admin/settings/ai">AI Classification</a>, emailed tickets are only auto-assigned.</li>
    <li>Automatic mail is the only mail ignored, and it never creates a ticket: out-of-office and vacation replies, bounces and delivery reports, and bulk and list mail.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-reply text-primary me-2"></i>How Replies Are Matched &amp; Added</h5>
<ul class="text-muted mb-0">
    <li>The ticket is found from <code>[#123]</code> or <code>[Ticket #123]</code> in the subject. If that has been stripped, the <code>In-Reply-To</code> / <code>References</code> headers of the original notification are used instead.</li>
    <li>The sender's address must match a user account; replies from unknown addresses are skipped. Replies from forwarded third parties work because forwarding creates an <a href="/admin/docs/users">external contact</a> for them.</li>
    <li>Quoted text and signatures are stripped, and the new text is added to the ticket as a <strong>public</strong> comment.</li>
    <li>Replies to a <strong>Closed</strong> ticket are skipped, unless an agent sends <code>#open</code>, or the ticket was closed with <em>Close and reopen on reply</em> and the requester replies within that window (the ticket reopens).</li>
    <li>Processed messages are marked read in the mailbox.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="reply-commands">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hash text-primary me-2"></i>Email Reply Commands</h5>
<p class="text-muted mb-3">Users at the <strong>Admin</strong> or <strong>Agent</strong> level can change a ticket from their mail client by putting <strong>hashtag commands</strong> on their own line at the end of the reply. For anyone else, the hashtags are treated as plain text.</p>

<h6 class="fw-semibold mb-2">Status Commands</h6>
<div class="table-responsive mb-4">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Hashtag</th><th>Sets ticket status to</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><code>#open</code></td><td>Open (also reopens a closed ticket)</td></tr>
        <tr><td><code>#close</code> or <code>#closed</code></td><td>Closed</td></tr>
        <tr><td><code>#resolve</code> or <code>#resolved</code></td><td>Resolved (ignored while the Resolved status is inactive)</td></tr>
        <tr><td><code>#pending</code></td><td>Pending</td></tr>
        <tr><td><code>#in_progress</code> or <code>#inprogress</code></td><td>In Progress</td></tr>
        <tr><td><code>#waiting_on_customer</code> or <code>#waitingoncustomer</code></td><td>Waiting on Customer</td></tr>
        <tr><td><code>#waiting_on_third_party</code> or <code>#waitingonthirdparty</code></td><td>Waiting on Third Party</td></tr>
    </tbody>
</table>
</div>

<h6 class="fw-semibold mb-2">Priority Commands</h6>
<p class="text-muted mb-4"><code>#low</code>, <code>#medium</code>, <code>#high</code> and <code>#critical</code> set the priority with that name. They do nothing if no priority by that name exists.</p>

<h6 class="fw-semibold mb-2">Usage Rules</h6>
<ul class="text-muted mb-3">
    <li>Commands go on their own line(s) at the end of your text: several on one line (<code>#resolve #high</code>) or on consecutive lines. Your email signature can follow them.</li>
    <li>Hashtags inside sentences (<em>"see ticket #123"</em>) are never commands. Unknown hashtags, and commands for a status that has been deactivated, are ignored.</li>
    <li>Command lines are removed from the stored comment. A reply that contains only commands changes the ticket without adding a comment.</li>
    <li>If Exchange Online marks the message as failing sender authentication (DMARC / compauth), commands are ignored and the reply is posted as plain text, so a spoofed agent address can't change tickets.</li>
</ul>

<h6 class="fw-semibold mb-2">Example</h6>
<pre class="bg-light p-3 rounded mb-0" style="font-size:.85rem;">Hi,

I've confirmed the fix is deployed and the user has verified it works.

#close #high</pre>
<p class="text-muted mt-2 mb-0">This posts the comment, sets the status to <strong>Closed</strong> and the priority to <strong>High</strong>. The command line doesn't appear in the comment.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="notifications">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bell text-primary me-2"></i>Email Notifications</h5>
<p class="text-muted mb-2"><a href="/admin/settings/email-notifications"><strong>Settings &rarr; Email &rarr; Email Notifications</strong></a> holds the site-wide switch for each notification. An email goes out only when its switch is on <em>and</em> the recipient hasn't opted out on their profile (see <a href="/admin/docs/users#notification-preferences">notification preferences</a>).</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:34%;">Switch</th><th>Who gets the email</th></tr></thead>
    <tbody class="text-muted">
        <tr><td colspan="2" class="fw-semibold text-body">Agent Notifications</td></tr>
        <tr><td>New Ticket Created</td><td>Members of the ticket's group, if that group has <em>Notify members when a new ticket is created</em> ticked. If the ticket has no group, the ticket type's group is used; with neither, every opted-in group is notified. The requester, assignee and anyone already emailed about the ticket are skipped.</td></tr>
        <tr><td>Ticket Assigned to Group</td><td>Members of the group a ticket is assigned to.</td></tr>
        <tr><td>Ticket Assigned to Agent</td><td>The agent a ticket is assigned to.</td></tr>
        <tr><td>Requester Replies to Ticket</td><td>The assigned agent, when the requester (or another staff member, publicly) replies.</td></tr>
        <tr><td>Note Added to Ticket</td><td>The assigned agent, when an internal note is added.</td></tr>
        <tr><td>Overdue &amp; Update-Due Reminders</td><td>The ticket's owner when it passes its due date, or when the customer hasn't had a public reply in N days. If the ticket has no owner, the group's managers (only when that option is on). Both are off by default and switched on at <a href="/admin/docs/automations#ticket-reminders">Ticket Reminders</a>, not on this page.</td></tr>
        <tr><td colspan="2" class="fw-semibold text-body">Ticket Requester Notifications</td></tr>
        <tr><td>New Ticket Created (Confirmation)</td><td>The requester, from every channel (portal, staff-created, email, API).</td></tr>
        <tr><td>Ticket Assigned to Agent</td><td>The requester, naming who is handling it.</td></tr>
        <tr><td>Agent Adds Comment to Ticket</td><td>The requester, on each public staff reply.</td></tr>
        <tr><td>Ticket Resolved / Ticket Closed</td><td>The requester, when the ticket enters the default resolved or closed status.</td></tr>
        <tr><td colspan="2" class="fw-semibold text-body">CC Notifications</td></tr>
        <tr><td>Comment Added to Ticket</td><td>CC'd people: a one-time "you were added as a CC" email (also when an automation adds them), then an email for each public reply.</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted mb-2">Emails without a switch on that page, which still respect the recipient's own preference where one exists:</p>
<ul class="text-muted mb-0">
    <li><strong>Ticket merged</strong> and <strong>satisfaction survey</strong> (see <a href="/admin/docs/csat">Satisfaction Surveys</a>) to the requester.</li>
    <li><strong>Escalation alert</strong> and <strong>customer reminder</strong> from <a href="/admin/docs/automations#escalation-rules">escalation rules</a>, and <strong>Ticket escalated</strong> to the next person on an <a href="/admin/docs/automations#escalation-paths">escalation path</a>.</li>
    <li><strong>Password reset</strong>, <strong>confidential-group security alerts</strong>, <strong>scheduled reports</strong>, <strong>app-secret expiry reminders</strong> and <strong>ticket forwards</strong>.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="templates">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-paper text-primary me-2"></i>Email Templates</h5>
<p class="text-muted mb-2">Change the wording of the main emails, with no code, at <a href="/admin/settings/email-templates"><strong>Settings &rarr; Email &rarr; Email Templates</strong></a>. Each tab has a <strong>Subject</strong>, an <strong>Intro Message</strong> (rich text: bold, italic, lists, links) and usually a <strong>Button Label</strong>, with a live subject preview and a <strong>Reset to Defaults</strong> button. Leave a field blank to use the built-in wording.</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th style="width:30%;">Tab</th><th>Sent when&hellip;</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Ticket Created</strong></td><td>A ticket is submitted (requester confirmation).</td></tr>
        <tr><td><strong>Ticket Updated</strong></td><td>Staff post a public reply.</td></tr>
        <tr><td><strong>Ticket Merged</strong></td><td>A requester's ticket is merged into another.</td></tr>
        <tr><td><strong>CSAT Survey</strong></td><td>A satisfaction survey is sent.</td></tr>
        <tr><td><strong>Customer Reminder</strong></td><td>An escalation rule's <em>Send reminder email to customer</em> action fires.</td></tr>
        <tr><td><strong>Group Alerts</strong></td><td>A new ticket alert goes to a group.</td></tr>
        <tr><td><strong>Agent Assigned</strong> / <strong>Group Assigned</strong></td><td>A ticket is assigned to an agent or a group.</td></tr>
        <tr><td><strong>Escalation Alert</strong></td><td>An escalation rule notifies an agent.</td></tr>
        <tr><td><strong>Overdue Reminder</strong> / <strong>Update Due Reminder</strong></td><td>A <a href="/admin/docs/automations#ticket-reminders">ticket reminder</a> goes to the ticket's owner (or group managers). Extra tokens: <code>{{requester}}</code>, <code>{{due_date}}</code>, <code>{{days_since_contact}}</code>.</td></tr>
        <tr><td><strong>Shared Footer</strong></td><td>Footer text on every ticket email.</td></tr>
    </tbody>
</table>
</div>
<h6 class="fw-semibold mb-2">Tokens</h6>
<p class="text-muted mb-2">Each tab lists the tokens it supports. Click a token chip to insert it at the cursor in the field you're editing. Common ones:</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Token</th><th>Replaced with</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><code>{{first_name}}</code>, <code>{{last_name}}</code>, <code>{{user_name}}</code></td><td>Recipient's name</td></tr>
        <tr><td><code>{{ticket_id}}</code>, <code>{{subject}}</code></td><td>Ticket number and subject</td></tr>
        <tr><td><code>{{type}}</code>, <code>{{priority}}</code>, <code>{{location}}</code>, <code>{{group}}</code>, <code>{{submitter}}</code></td><td>Ticket details (on the templates that offer them)</td></tr>
        <tr><td><code>{{message}}</code>, <code>{{author}}</code></td><td>The new comment and who wrote it (Ticket Updated)</td></tr>
        <tr><td><code>{{sla}}</code></td><td>A sentence such as "First response within 4 hours and resolution within 16 hours (business hours)"</td></tr>
        <tr><td><code>{{sla_response}}</code>, <code>{{sla_resolution}}</code></td><td>The individual targets, e.g. "4 hours"</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted mb-0">The SLA tokens are offered on Ticket Created, Group Alerts, Agent Assigned and Group Assigned. They come from the SLA policy matching the ticket's type and priority, and are empty when SLA tracking is off or no policy applies.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-people text-primary me-2"></i>Per-Group Email Templates</h5>
<p class="text-muted mb-2">The four requester-facing emails (<strong>Ticket Created</strong>, <strong>Ticket Updated</strong>, <strong>Customer Reminder</strong> and <strong>CSAT Survey</strong>) can be worded differently for each group. On those tabs, use the <strong>Editing for:</strong> picker to choose a group and give it its own subject, intro and button. The picker shows which groups already have their own version.</p>
<ul class="text-muted mb-0">
    <li>Blank fields inherit. Each field resolves as <strong>group override &rarr; global custom value &rarr; built-in default</strong>. <strong>Reset to Global Default</strong> clears a group's override.</li>
    <li>The footer is shared by all groups.</li>
    <li>The ticket's group is read when the email is sent, so a reassigned ticket uses its new group's wording.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Troubleshooting</h5>
<ul class="text-muted mb-0">
    <li><strong>No mail at all:</strong> check the <a href="/admin/settings/email-queue">Email Queue</a>. If messages are piling up with the reason <code>MAIL_ENABLED=false</code>, outbound mail is switched off in the server's <code>.env</code>. Other reasons show the SMTP error.</li>
    <li><strong>Test email fails:</strong> check host, port, encryption and credentials, and that the server can reach the SMTP port outbound. Turn on SMTP Debug Logging and read <code>storage/logs/smtp.log</code>.</li>
    <li><strong>Microsoft 365 authentication error:</strong> make sure SMTP AUTH is enabled for the sending mailbox.</li>
    <li><strong>Sent but not arriving:</strong> the From Address usually has to match the authenticated mailbox. Also check SPF, DKIM and DMARC for your domain.</li>
    <li><strong>Replies not appearing on tickets:</strong> confirm the Inbound Email Replies cron job is running (<a href="/admin/settings/cron-jobs">Cron Jobs</a>), press <strong>Run Now</strong> and read the output (<code>storage/logs/graph-mail.log</code>). "Not a registered user" means the sender's address doesn't match any account; "is closed" means the ticket is Closed.</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->

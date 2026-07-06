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
<p class="text-muted mb-4">Configure outgoing email so OpenHelpDesk can notify users and agents about ticket activity.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-check text-primary me-2"></i>SMTP Configuration</h5>
<p class="text-muted mb-2">Go to <a href="/admin/settings"><strong>Admin → Settings → Email / SMTP</strong></a> and enter your mail server details:</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Field</th><th>Description</th></tr></thead>
    <tbody>
        <tr><td class="fw-semibold">SMTP Host</td><td class="text-muted">Your mail server hostname (e.g. <code>smtp.gmail.com</code>).</td></tr>
        <tr><td class="fw-semibold">SMTP Port</td><td class="text-muted">Usually <code>587</code> for TLS or <code>465</code> for SSL.</td></tr>
        <tr><td class="fw-semibold">Encryption</td><td class="text-muted">Choose <strong>TLS</strong> (recommended), <strong>SSL</strong>, or <strong>None</strong>.</td></tr>
        <tr><td class="fw-semibold">Username</td><td class="text-muted">Your SMTP login — usually the sending email address.</td></tr>
        <tr><td class="fw-semibold">Password</td><td class="text-muted">SMTP password or app-specific password (see below).</td></tr>
        <tr><td class="fw-semibold">From Address</td><td class="text-muted">The email address recipients will see in the From field.</td></tr>
        <tr><td class="fw-semibold">From Name</td><td class="text-muted">The display name recipients will see (e.g. "IT Support").</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted mb-0">After saving, use the <strong>Send Test Email</strong> button to verify the configuration by sending a test message to the logged-in admin's address.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-arrow-down text-primary me-2"></i>Inbound Email (Email Reply Integration)</h5>
<p class="text-muted mb-2">OpenHelpDesk can process replies sent directly to your support email address and automatically add them as ticket comments — so agents and requesters can reply from their inbox without logging in.</p>

<h6 class="fw-semibold mt-3 mb-2">Option 1 — Microsoft Graph API (Microsoft 365 / Exchange Online)</h6>
<p class="text-muted mb-2">Configure at <a href="/admin/settings"><strong>Admin → Settings → Email → Graph API</strong></a>. You will need a registered Azure AD application with <code>Mail.Read</code> permissions.</p>
<ol class="text-muted mb-3">
    <li>Enter your Azure <strong>Client ID</strong>, <strong>Tenant ID</strong>, and <strong>Client Secret</strong>.</li>
    <li>Enter the <strong>mailbox address</strong> to monitor.</li>
    <li>Save, then use the <strong>Run Now</strong> button to test immediately.</li>
    <li>Set up a cron job to run <code>scripts/process-replies.php</code> periodically (e.g. every 5 minutes).</li>
</ol>

<h6 class="fw-semibold mt-3 mb-2">Option 2 — IMAP</h6>
<p class="text-muted mb-2">Configure IMAP credentials in the Email settings tab. Inbound messages are matched to tickets via <code>In-Reply-To</code> / <code>Message-ID</code> headers and a special <code>X-Ticket-ID</code> header added to outgoing emails.</p>

<h6 class="fw-semibold mt-3 mb-2">How replies are processed</h6>
<ul class="text-muted mb-0">
    <li>Email signatures and quoted text are stripped — only the new reply content is saved.</li>
    <li>Replies from unknown senders are rejected.</li>
    <li>Portal user replies become public comments; agent/admin replies follow their default visibility setting.</li>
    <li>Agents and admins can include <strong>hashtag commands</strong> (e.g. <code>#resolve</code>) at the end of their reply to update ticket status or priority — see the <em>Email Reply Commands</em> section below.</li>
</ul>
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
            <td class="fw-semibold">Gmail</td>
            <td><code>smtp.gmail.com</code></td>
            <td><code>587</code></td>
            <td>TLS</td>
            <td class="text-muted">Requires an <a href="https://support.google.com/accounts/answer/185833" target="_blank">App Password</a> if 2FA is enabled.</td>
        </tr>
        <tr>
            <td class="fw-semibold">Microsoft 365</td>
            <td><code>smtp.office365.com</code></td>
            <td><code>587</code></td>
            <td>TLS</td>
            <td class="text-muted">SMTP AUTH must be enabled on the mailbox in the M365 admin centre.</td>
        </tr>
        <tr>
            <td class="fw-semibold">Outlook.com</td>
            <td><code>smtp-mail.outlook.com</code></td>
            <td><code>587</code></td>
            <td>TLS</td>
            <td class="text-muted">Free Outlook accounts; same M365 auth requirements.</td>
        </tr>
        <tr>
            <td class="fw-semibold">Mailgun</td>
            <td><code>smtp.mailgun.org</code></td>
            <td><code>587</code></td>
            <td>TLS</td>
            <td class="text-muted">Use your Mailgun SMTP credentials from the dashboard.</td>
        </tr>
        <tr>
            <td class="fw-semibold">SendGrid</td>
            <td><code>smtp.sendgrid.net</code></td>
            <td><code>587</code></td>
            <td>TLS</td>
            <td class="text-muted">Username is always <code>apikey</code>; password is your API key.</td>
        </tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-hash text-primary me-2"></i>Email Reply Commands (Agents &amp; Admins)</h5>
<p class="text-muted mb-3">Agents and admins can take actions on a ticket directly from their email client by adding <strong>hashtag commands</strong> on their own line at the very end of the reply. Commands placed in the middle of a message are never processed — only lines at the bottom of the reply count.</p>

<h6 class="fw-semibold mb-2">Status Commands</h6>
<div class="table-responsive mb-4">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Hashtag</th><th>Sets ticket status to</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><code>#open</code></td><td>Open — also re-opens a closed ticket</td></tr>
        <tr><td><code>#close</code> or <code>#closed</code></td><td>Closed</td></tr>
        <tr><td><code>#resolve</code> or <code>#resolved</code></td><td>Resolved</td></tr>
        <tr><td><code>#pending</code></td><td>Pending</td></tr>
        <tr><td><code>#in_progress</code> or <code>#inprogress</code></td><td>In Progress</td></tr>
        <tr><td><code>#waiting_on_customer</code> or <code>#waitingoncustomer</code></td><td>Waiting on Customer</td></tr>
        <tr><td><code>#waiting_on_third_party</code> or <code>#waitingonthirdparty</code></td><td>Waiting on Third Party</td></tr>
    </tbody>
</table>
</div>

<h6 class="fw-semibold mb-2">Priority Commands</h6>
<div class="table-responsive mb-4">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Hashtag</th><th>Sets ticket priority to</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><code>#low</code></td><td>Low</td></tr>
        <tr><td><code>#medium</code></td><td>Medium</td></tr>
        <tr><td><code>#high</code></td><td>High</td></tr>
        <tr><td><code>#critical</code></td><td>Critical</td></tr>
    </tbody>
</table>
</div>

<h6 class="fw-semibold mb-2">Usage Rules</h6>
<ul class="text-muted mb-3">
    <li>Commands must be on their <strong>own line at the very end</strong> of the reply — not in the middle of a sentence.</li>
    <li>Multiple commands can appear on the <strong>same line</strong> (e.g. <code>#resolve #high</code>) or on <strong>consecutive lines</strong>.</li>
    <li>Blank lines between your message and the command line are fine — they are automatically skipped.</li>
    <li>Commands are <strong>only processed for agents and admins</strong>. If a regular portal user includes a hashtag command, it is treated as plain text and stored in the comment with no action taken.</li>
    <li>Hashtags embedded in normal sentences (e.g. <em>"check ticket #123"</em>) are never treated as commands.</li>
    <li>Unknown hashtags are silently ignored — they will not affect the ticket or appear in the comment.</li>
</ul>

<h6 class="fw-semibold mb-2">Example</h6>
<pre class="bg-light p-3 rounded mb-0" style="font-size:.85rem;">Hi,

I've confirmed the fix is deployed and the user has verified it works.

#resolve #high</pre>
<p class="text-muted mt-2 mb-0">This reply posts the comment, sets the ticket status to <strong>Resolved</strong>, and raises the priority to <strong>High</strong>. The <code>#resolve #high</code> line does not appear in the stored comment.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-send-check text-primary me-2"></i>When Emails Are Sent</h5>
<p class="text-muted mb-2">OpenHelpDesk sends emails automatically for the following events:</p>
<ul class="text-muted mb-0">
    <li><strong>New ticket submitted</strong> — confirmation sent to the requester.</li>
    <li><strong>Agent reply</strong> — notification sent to the requester with the reply content.</li>
    <li><strong>Ticket resolved</strong> — notification sent to the requester.</li>
    <li><strong>Ticket assigned</strong> — notification sent to the assigned agent.</li>
    <li><strong>Escalation alert</strong> — sent to the assigned agent when an escalation rule fires.</li>
    <li><strong>Customer reminder</strong> — sent to the requester when an escalation rule with the "Notify ticket creator" action fires (e.g. after 3 days without a reply).</li>
    <li><strong>Welcome email</strong> — sent to new users created by an admin.</li>
    <li><strong>Password reset</strong> — sent when a user requests a password reset.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-toggles text-primary me-2"></i>Outbound Mail Switches (<code>.env</code>)</h5>
<p class="text-muted mb-2">Two master switches control whether any mail leaves the server at all. They live in the application's <code>.env</code> file on the server &mdash; <strong>not</strong> in the admin UI &mdash; so changing them requires shell access to the host. This is deliberate: it keeps the "stop all email" control off the web interface, where an accidental click (or a compromised admin account) could otherwise flip mail on for every recipient at once.</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Setting</th><th>Default</th><th>Effect</th></tr></thead>
    <tbody>
        <tr>
            <td class="fw-semibold"><code>MAIL_ENABLED</code></td>
            <td><code>true</code></td>
            <td class="text-muted">Master kill switch. When <code>false</code>, <strong>all</strong> outbound mail is silently skipped (each attempt is logged to <code>storage/logs/smtp.log</code>). Set to <code>false</code> on dev, staging, and test instances so they never deliver real mail; leave <code>true</code> on production.</td>
        </tr>
        <tr>
            <td class="fw-semibold"><code>MAIL_TRANSACTIONAL_ENABLED</code></td>
            <td><code>false</code></td>
            <td class="text-muted">Transactional bypass. When <code>MAIL_ENABLED=false</code>, setting this to <code>true</code> still lets <strong>user-initiated, single-recipient</strong> mail through &mdash; currently <strong>password-reset</strong> emails &mdash; while bulk ticket-notification mail stays suppressed. Has no effect when <code>MAIL_ENABLED=true</code> (everything already sends).</td>
        </tr>
    </tbody>
</table>
</div>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    The transactional bypass still needs SMTP configured above &mdash; it changes <em>whether</em> the kill switch blocks the message, not how it is delivered. Use it when you want password resets to work on an instance that intentionally keeps all other outbound mail turned off.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-envelope-paper text-primary me-2"></i>Customisable Email Templates</h5>
<p class="text-muted mb-2">The subject line, introductory message, button label and footer text for several system emails can be customised without editing code. Go to <a href="/admin/settings/email-templates"><strong>Admin → Settings → Email Templates</strong></a>.</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Template</th><th>Sent when…</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><strong>Ticket Created</strong></td><td>A new ticket is submitted by a portal user or created by an agent.</td></tr>
        <tr><td><strong>Ticket Updated</strong></td><td>An agent posts a public reply on the ticket.</td></tr>
        <tr><td><strong>Ticket Merged</strong></td><td>Two tickets are merged and the requester is notified.</td></tr>
        <tr><td><strong>CSAT Survey</strong></td><td>A satisfaction survey is sent after a ticket is resolved/closed.</td></tr>
        <tr><td><strong>Customer Reminder</strong></td><td>An escalation rule with the "Notify ticket creator" action fires (e.g. follow-up after no reply).</td></tr>
        <tr><td><strong>Group Alerts</strong></td><td>A new ticket is created and the ticket's assigned group has email alerts enabled.</td></tr>
    </tbody>
</table>
</div>

<h6 class="fw-semibold mb-2">Rich Text Intro Messages</h6>
<p class="text-muted mb-2">The <strong>Intro Message</strong> field on each template uses a rich text editor (CKEditor 5). You can apply bold, italic, bullet lists, numbered lists, and hyperlinks — the formatted HTML is rendered directly in the outgoing email. Tokens (see below) work inside the rich text editor as normal.</p>

<h6 class="fw-semibold mb-2">Using Tokens</h6>
<p class="text-muted mb-2">Template fields support dynamic tokens that are replaced with live ticket data when the email is sent. Wrap tokens in double curly braces:</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Token</th><th>Replaced with</th></tr></thead>
    <tbody class="text-muted">
        <tr><td><code>{{first_name}}</code></td><td>Recipient's first name</td></tr>
        <tr><td><code>{{last_name}}</code></td><td>Recipient's last name</td></tr>
        <tr><td><code>{{user_name}}</code></td><td>Recipient's full name</td></tr>
        <tr><td><code>{{ticket_id}}</code></td><td>Ticket number</td></tr>
        <tr><td><code>{{subject}}</code></td><td>Ticket subject line</td></tr>
        <tr><td><code>{{sla}}</code></td><td>SLA summary for the ticket's type + priority, e.g. "First response within 4 hours and resolution within 16 hours (business hours)" — empty when no SLA policy applies</td></tr>
        <tr><td><code>{{sla_response}}</code></td><td>SLA first-response target, e.g. "4 hours" (business hours)</td></tr>
        <tr><td><code>{{sla_resolution}}</code></td><td>SLA resolution target, e.g. "16 hours" (business hours)</td></tr>
    </tbody>
</table>
</div>
<p class="text-muted mb-3">The SLA tokens are available on the <strong>Ticket Created</strong>, <strong>Group Alerts</strong>, <strong>Assigned to Agent</strong>, and <strong>Assigned to Group</strong> templates. They resolve from the SLA policy matching the ticket's type and priority (falling back to the default policy), and render as empty text when SLA tracking is disabled or no policy matches — so a sentence like "{{sla}}" simply disappears rather than showing a blank value.</p>
<p class="text-muted mb-0">Each template also has a <strong>Reset to default</strong> button to restore the original built-in content if needed. A shared <strong>Footer Text</strong> tab lets you customise the footer that appears on all outgoing ticket emails.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-people text-primary me-2"></i>Per-Group Email Templates</h5>
<p class="text-muted mb-2">The four requester-facing emails &mdash; <strong>Ticket Created</strong>, <strong>Ticket Updated</strong>, <strong>Customer Reminder</strong>, and <strong>CSAT Survey</strong> &mdash; can be worded differently per group, so each team can speak in its own voice.</p>
<p class="text-muted mb-2">On <a href="/admin/settings/email-templates"><strong>Admin → Settings → Email Templates</strong></a>, use the <strong>"Editing for:"</strong> picker to choose a group and give that team its own subject, intro, and button. For example, the IT group's <em>Ticket Created</em> email can quote its SLA targets with <code>{{sla_response}}</code> / <code>{{sla_resolution}}</code> while Facilities keeps the simpler default.</p>
<ul class="text-muted mb-2">
    <li><strong>Leave any field blank to inherit.</strong> The resolution order for each field is <strong>group override → global custom value → built-in default</strong>, so you only override the pieces a team actually needs.</li>
    <li><strong>The footer stays shared</strong> across all groups and all emails.</li>
    <li><strong>The handling group is read at send time</strong>, so if a ticket is reassigned to another group, its later emails follow whichever group owns it then.</li>
</ul>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    Overrides are created only when a group is actually customised &mdash; groups with no overrides behave exactly as before, so turning this on changes nothing until you use it.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Troubleshooting</h5>
<ul class="text-muted mb-0">
    <li><strong>Test email fails:</strong> Double-check host, port, and credentials. Ensure your firewall allows outbound connections on the SMTP port.</li>
    <li><strong>Emails going to spam:</strong> Configure SPF, DKIM, and DMARC records for your sending domain.</li>
    <li><strong>Gmail "Less secure app" error:</strong> Use an App Password instead of your regular Gmail password when 2FA is enabled.</li>
    <li><strong>Microsoft 365 authentication error:</strong> Ensure SMTP AUTH is enabled for the mailbox in the Exchange admin centre under <em>Mail flow → Connectors</em>.</li>
    <li><strong>No errors but emails not arriving:</strong> Check that your From Address matches the authenticated SMTP user — mismatches can cause silent rejection.</li>
    <li><strong>No mail at all (and nothing in the send log):</strong> Confirm <code>MAIL_ENABLED=true</code> in the server's <code>.env</code> — when it is <code>false</code>, every message is skipped and logged to <code>storage/logs/smtp.log</code> as <code>SKIPPED</code>. If you intend to keep general mail off but need password resets to work, set <code>MAIL_TRANSACTIONAL_ENABLED=true</code> (see <em>Outbound Mail Switches</em> above).</li>
</ul>
</div>
</div>

</div><!-- col -->
</div><!-- row -->

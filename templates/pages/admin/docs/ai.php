<?php
$layout       = 'app';
$pageTitle    = 'Docs: AI Classification';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'AI Classification']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">AI Classification</h2>
<p class="text-muted mb-4">Use a large language model to read each new ticket and decide which agent skills it needs, then route it via the existing <a href="/admin/docs/automations#group-auto-assign">Skill-Based</a> auto-assign machinery. Same flow as having a senior agent triage every ticket on arrival — only faster and 24/7.</p>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-diagram-3 text-primary me-2"></i>How It Works</h5>
<ol class="text-muted mb-0">
    <li>A new ticket arrives (portal, admin, API, or inbound email).</li>
    <li>If AI is enabled and the ticket type is <strong>not</strong> confidential, OpenHelpDesk sends the subject + body plus the candidate skill list (global skills + the destination group's skills) to the configured provider.</li>
    <li>The provider returns JSON: <code>skill_ids</code>, a confidence score, and a sentiment label.</li>
    <li>Result is stored in <code>ai_classifications</code> and pointed at by <code>tickets.ai_classification_id</code>. The ticket also gets a denormalised <code>ai_sentiment</code> for fast filtering.</li>
    <li>If the group's auto-assign strategy is <strong>AI Skill-Based</strong> AND confidence ≥ threshold, the routing engine picks the least-loaded group member who holds <em>all</em> the suggested skills. Otherwise it falls through to the group's configured fallback (load-based, round-robin, or leave unassigned).</li>
    <li>If the AI flagged sentiment as "angry" or "urgent" and the bump toggle is on, ticket priority is bumped one notch and a timeline entry is recorded.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="no-wrong-door">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-signpost-split text-info me-2"></i>"No Wrong Door" — Let AI Pick the Group</h5>
<p class="text-muted mb-3">Some patron requests don't fit a clear category — the patron knows they need help but doesn't know which team handles it. The skill classifier above picks <em>which agent</em> handles a ticket within a group it's already in; the <strong>No Wrong Door</strong> flag adds a layer above that, picking <em>which group</em> handles the ticket in the first place.</p>

<h6 class="fw-semibold mt-3 mb-2">When to use it</h6>
<ul class="text-muted mb-3">
    <li>You have a generic portal entry like <em>"I'm not sure who handles this"</em> or <em>"Help — anything"</em> and want patrons to use it without picking a category.</li>
    <li>You want a single inbox for ambiguous requests but don't want a human triager to read each one — AI fans them out to the right team automatically.</li>
    <li>Your library has departments with clearly distinct domains (Branch IT, Cataloguing, Facilities, HR, Programming, etc.) and the body of a ticket usually makes the right group obvious.</li>
</ul>

<h6 class="fw-semibold mt-3 mb-2">How it works</h6>
<ol class="text-muted mb-3">
    <li>Edit a ticket type at <a href="/admin/types"><strong>Admin → Settings → Ticket Types</strong></a> and tick <strong><i class="bi bi-signpost-split me-1"></i>Let AI route this to the best group ("No Wrong Door")</strong>. The flag is mutually exclusive with <strong>Confidential</strong> — confidential bodies are never sent to a third-party provider.</li>
    <li>Set the type's <strong>Default Group</strong> to your <em>fallback queue</em> — the team whose members handle anything AI couldn't route. This doubles as the "No Wrong Door" team. Make sure it has agents in it.</li>
    <li>At <a href="/admin/groups"><strong>Admin → Settings → Groups</strong></a>, give every group that should be a routing candidate a <strong>clear, specific description</strong>. <em>This is the only signal AI uses to pick.</em> Groups with an empty description are excluded from the candidate pool.</li>
    <li>When a patron submits a ticket of that type, AI receives the subject + body plus every non-confidential group's name &amp; description and returns one of: a chosen <code>group_id</code>, or <code>null</code> if it can't decide. The ticket is moved to the chosen group <strong>only if</strong> the model's confidence clears the same threshold the skill classifier uses (default 0.7). Below that — or if AI returns null — the ticket stays in the <strong>Default Group</strong> queue.</li>
    <li>Once the group is settled, the existing skill classifier runs against <em>that</em> group's skills, and auto-assignment proceeds normally. So a ticket can be routed to "Branch IT" by AI, then assigned to the on-shift Branch IT agent who holds the matching skill.</li>
</ol>

<h6 class="fw-semibold mt-3 mb-2">Audit trail</h6>
<p class="text-muted mb-2">Every routing decision — apply or skip — is recorded in <code>ai_group_classifications</code> and surfaced as an <strong>AI Group Routing</strong> card on the ticket detail page (above the existing AI Classification card). The card shows:</p>
<ul class="text-muted mb-3">
    <li><span class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle me-1"></i>Routed to <em>Group</em></span> — AI picked confidently and the ticket was moved.</li>
    <li><span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-question-circle me-1"></i>No confident match</span> — AI returned <code>null</code>; ticket stayed in the Default Group.</li>
    <li><span class="badge bg-warning bg-opacity-10 text-warning"><i class="bi bi-dash-circle me-1"></i>Suggested <em>Group</em> (below threshold)</span> — AI picked a group but its confidence was below the threshold, so the ticket stayed in the Default Group.</li>
</ul>
<p class="text-muted mb-0">The card also shows the AI's one-sentence reasoning, the provider/model, and call latency. Use the audit trail to tune your group descriptions: if you keep seeing "Suggested X (below threshold)" pointing at the right group, your description for X is too vague to satisfy the threshold — sharpen it. If you see confident routes to the wrong group, the descriptions are misleading or overlapping.</p>

<h6 class="fw-semibold mt-3 mb-2">Spotting AI-routed types in the list</h6>
<p class="text-muted mb-0">The <a href="/admin/types">Ticket Types</a> list shows a small <i class="bi bi-signpost-split text-info"></i> icon beside every type with <strong>No Wrong Door</strong> on, alongside the existing <i class="bi bi-shield-lock text-warning"></i> for confidential types. Hover for a tooltip.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="duplicate-detection">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-files text-info me-2"></i>Duplicate-Ticket Detection</h5>
<p class="text-muted mb-3">Sites with multiple shifts often file the same ticket twice — staff don't routinely scan every open ticket before opening their own. Duplicate detection asks the AI, at submit time, whether the new ticket looks like one that already exists, and warns the submitter before the duplicate is created.</p>

<h6 class="fw-semibold mt-3 mb-2">How it works</h6>
<ol class="text-muted mb-3">
    <li>When someone clicks <strong>Submit</strong> on a ticket type that has duplicate detection enabled, the new subject + description are run through AI against open, non-confidential tickets at the same location (last 14 days, up to 30 candidates).</li>
    <li>Any match scoring above the type's confidence threshold is shown in a warning card on the create form: <em>"Oops! It looks like someone else might have already submitted a ticket for this issue."</em></li>
    <li>The submitter can click <strong>Click here to see this ticket</strong> to preview the matched ticket — subject, status, when it was opened, who reported it, description, and reply count — in a modal, without leaving the form.</li>
    <li>If it really is a different issue, they click <strong>Create anyway — This is a Different Issue</strong> to override and submit normally.</li>
</ol>

<h6 class="fw-semibold mt-3 mb-2">Turning it on</h6>
<p class="text-muted mb-2">Duplicate detection is configured <strong>per ticket type</strong>, not globally. Edit a ticket type at <a href="/admin/types"><strong>Admin → Settings → Ticket Types</strong></a>:</p>
<ul class="text-muted mb-3">
    <li>Tick <strong>AI duplicate check</strong> to enable it for that type.</li>
    <li>Set the <strong>threshold</strong> (0.50–0.99, default 0.75) — higher means only very close matches are flagged.</li>
    <li>The toggle is blocked while <strong>Confidential</strong> is checked — confidential bodies are never sent to an AI provider, and confidential tickets never appear as candidate matches.</li>
</ul>

<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
    The check is wired into all three create paths (portal, agent/admin form, and floor-mode quick-create) — it is <strong>not</strong> a customer-only feature; agents creating a ticket see the same warning. The popup only appears when <strong>both</strong> conditions hold for the chosen ticket type: <strong>AI duplicate check</strong> is enabled on that type, <em>and</em> an AI provider/key is configured. If either is missing — or if a near-duplicate simply isn't found — no popup shows for anyone, agent or portal. The check also <strong>soft-fails</strong>: if the provider times out or errors, the ticket is created normally — duplicate detection never blocks submission. When a submitter overrides the warning, an <code>ai_duplicate_warned</code> note is added to the new ticket's timeline (visible to admins) recording which tickets it was flagged against.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-success me-2"></i>Privacy &amp; Confidential Tickets</h5>
<ul class="text-muted mb-0">
    <li>Tickets whose <strong>type is marked Confidential</strong> are <em>never</em> sent to the AI provider — the classifier short-circuits before any HTTP call. No exception, no override, no admin opt-in.</li>
    <li>Ticket subjects are truncated to 200 characters and bodies to 4,000 before sending. HTML is stripped.</li>
    <li>API keys are stored in the <code>settings</code> table (same place as your SMTP password) and never displayed in clear text after first save.</li>
    <li>The classifier uses HTTPS to <code>api.anthropic.com</code> or <code>api.openai.com</code> — review each provider's data-handling policy for retention details.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-broadcast text-primary me-2"></i>Choosing a Provider</h5>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Provider</th><th>Default model</th><th>Notes</th></tr></thead>
    <tbody class="text-muted">
        <tr>
            <td><strong>Anthropic Claude</strong> <span class="badge bg-secondary ms-1">recommended</span></td>
            <td><code>claude-haiku-4-5</code></td>
            <td>Fast, low cost, strong on classification. Switch to Sonnet 4.6 for trickier libraries with overlapping skill names.</td>
        </tr>
        <tr>
            <td><strong>OpenAI</strong></td>
            <td><code>gpt-4o-mini</code></td>
            <td>Use if you already have an OpenAI account or need GPT-specific behaviours. Forces <code>response_format=json_object</code>.</td>
        </tr>
    </tbody>
</table>
</div>
<div class="alert alert-info small mt-3 mb-0"><i class="bi bi-info-circle me-2"></i>
    The model dropdown is auto-populated from each provider's API. Click <strong>Refresh model list</strong> on <a href="/admin/settings/ai">Settings → AI Classification</a> after a provider releases something new — no code change needed.
</div>

<h6 class="fw-semibold mt-4 mb-2">Cost comparison (Anthropic)</h6>
<p class="text-muted small mb-2">Approximate per-classification cost at ~500 input + ~100 output tokens (typical ticket).</p>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Model</th><th>Per ticket</th><th>Per 1,000 tickets</th><th>When to use</th></tr></thead>
    <tbody class="text-muted">
        <tr>
            <td><code>claude-haiku-4-5</code> <span class="badge bg-secondary ms-1">recommended</span></td>
            <td>~$0.001</td>
            <td>~$1</td>
            <td>Day-to-day classification. Fast and accurate enough for the skill-matching task.</td>
        </tr>
        <tr>
            <td><code>claude-sonnet-4-6</code></td>
            <td>~$0.005</td>
            <td>~$5</td>
            <td>If Haiku misclassifies repeatedly on tickets with overlapping skill names or subtle context.</td>
        </tr>
        <tr>
            <td><code>claude-opus-4-7</code></td>
            <td>~$0.015</td>
            <td>~$15</td>
            <td>Overkill for routine classification — use only if you've measured Sonnet getting things wrong on real tickets.</td>
        </tr>
    </tbody>
</table>
</div>
<div class="alert alert-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-2"></i>
    Default to Haiku unless you have evidence it's wrong. A fresh install picks Haiku, but if you accidentally tested with Opus and saved that, your $25 of Anthropic credit will burn through ~10× faster.
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-sliders text-primary me-2"></i>Setting It Up</h5>
<ol class="text-muted mb-0">
    <li>Go to <a href="/admin/settings/ai"><strong>Admin → Settings → AI Classification</strong></a>.</li>
    <li>Pick your provider, paste the API key, and click <strong>Refresh model list</strong> (this also validates the key).</li>
    <li>Pick a model from the dropdown and click <strong>Test connection</strong> — you should see a green success flash with the round-trip time.</li>
    <li>Tune the confidence threshold (default <strong>0.7</strong>). Tickets below this stay unassigned for an agent to pick up.</li>
    <li>Tick <strong>Enable AI ticket classification</strong> at the top, save.</li>
    <li>Edit each group whose tickets should be auto-routed via AI: <a href="/admin/groups">Admin → Settings → Groups → Edit</a> → set strategy to <strong>AI Skill-Based</strong>.</li>
    <li>Optionally run <strong>Classify existing tickets</strong> at the bottom of the AI settings page to back-fill open tickets created before the feature was on.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-flask text-primary me-2"></i>Testing It Works</h5>
<p class="text-muted mb-2">Three ways to validate the integration in escalating order of scope:</p>
<ol class="text-muted mb-0">
    <li><strong>Single-ticket smoke test.</strong> Open any ticket detail page → look at the right-hand sidebar → click <strong>Classify now</strong> (or <strong>Re-classify</strong>) on the AI Classification card. The card refreshes with the suggestion, confidence, sentiment, and AI reasoning. Best for "does this work the way I think it does?" before you spend any volume.</li>
    <li><strong>Small batch (10–25 tickets).</strong> On <a href="/admin/settings/ai">Settings → AI Classification</a>, scroll to <strong>Classify existing tickets</strong>, set the count to 25, click <strong>Run backfill</strong>. Picks up open / in-progress / pending tickets that don't already have a classification, skips confidential types automatically, returns a summary of <code>classified: X / failed: Y</code>.</li>
    <li><strong>Full sweep via cron.</strong> For larger backfills run the CLI directly: <code>php scripts/ai-classify-backfill.php --limit=200 --dry-run</code> first to preview, then drop <code>--dry-run</code>. Use <code>--statuses=open,in_progress,pending,resolved,closed</code> to include closed tickets if you're testing classification accuracy against historical data.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bug text-warning me-2"></i>Debugging Connection Issues</h5>
<p class="text-muted mb-2">When the regular <strong>Test connection</strong> button comes back with a generic message and you can't tell why, use the <strong>Debug</strong> page (orange button on the AI settings page, or visit <a href="/admin/settings/ai/debug">/admin/settings/ai/debug</a>). It bypasses the classifier abstraction and makes raw HTTP calls so you see the full response — status code, response headers, body, latency, cURL error.</p>

<h6 class="fw-semibold mt-3 mb-2">What it can tell you</h6>
<ul class="text-muted mb-3">
    <li><strong>Models list only</strong> — uses a free <code>GET /v1/models</code> endpoint that doesn't consume credits. If this works, your key is valid and your auth is fine. If it doesn't, the problem is the key itself or your network.</li>
    <li><strong>Message only</strong> — sends a tiny <code>POST /v1/messages</code> probe. Verifies actual generation billing. If this fails after Models works, the problem is billing/quota/spend-cap, not auth.</li>
    <li><strong>Both</strong> — runs both calls in sequence so you can see which one breaks.</li>
</ul>

<h6 class="fw-semibold mt-3 mb-2">Decoding common errors</h6>
<div class="table-responsive">
<table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>Status</th><th>Message</th><th>Root cause</th></tr></thead>
    <tbody class="text-muted">
        <tr><td>200</td><td>—</td><td>Working. If OpenHelpDesk's classifier still fails, the issue is in the OpenHelpDesk side — check error logs.</td></tr>
        <tr><td>401</td><td>Unauthorized</td><td>Wrong key, revoked key, or whitespace in the pasted value. Generate a new one.</td></tr>
        <tr><td>403</td><td>Forbidden</td><td>Workspace permission issue or region block.</td></tr>
        <tr><td>400</td><td>"credit balance is too low"</td><td>Workspace spend cap is $0 even though the org has credits. See the next card for the fix.</td></tr>
        <tr><td>404</td><td>"model_not_found"</td><td>The saved model name is wrong/deprecated. Run a Models test alone to see the valid list, then update the dropdown.</td></tr>
        <tr><td>429</td><td>"rate_limit"</td><td>Hitting the workspace's per-minute cap. Slow down or raise the cap.</td></tr>
        <tr><td>5xx</td><td>—</td><td>Provider is having problems. Try again in a few minutes.</td></tr>
        <tr><td>0 / cURL error</td><td>—</td><td>DNS, firewall, or outbound HTTPS blocked from the server. Test <code>curl https://api.anthropic.com</code> from the host shell.</td></tr>
    </tbody>
</table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-wallet2 text-warning me-2"></i>Workspace Spend Caps (the gotcha)</h5>
<p class="text-muted mb-2">Anthropic supports <strong>workspaces</strong> under your organization. The org-level credit balance shows up on the main billing page, but each workspace can have its own monthly spend limit — and a freshly-created workspace defaults to <strong>$0</strong>. The result: you add $25 to your org, see "$25 remaining," but the API still returns "credit balance is too low" because your API key is scoped to a workspace whose spend cap blocks every billable call.</p>
<p class="text-muted mb-2">If <strong>Models list</strong> works on the debug page but <strong>Message</strong> returns the credit error, this is almost certainly the cause. Two ways to fix it:</p>
<ol class="text-muted mb-0">
    <li><strong>Raise the workspace's spend limit.</strong> In <a href="https://console.anthropic.com/" target="_blank" rel="noopener">Anthropic Console</a> → <strong>Workspaces</strong> → click your workspace → <strong>Limits</strong> tab → set a monthly cap (or leave blank to inherit the org limit).</li>
    <li><strong>Generate a new key in the Default workspace.</strong> Click <strong>API keys</strong> in the Console → <strong>Create Key</strong> → make sure the workspace selector says Default → paste the new key into <a href="/admin/settings/ai">OpenHelpDesk's AI settings page</a>.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-thermometer-half text-primary me-2"></i>Confidence Threshold</h5>
<p class="text-muted mb-2">The model returns a self-rated confidence between 0.0 and 1.0. Below your configured threshold, the suggestion is <strong>discarded</strong> for routing purposes — the ticket falls back to the group's fallback strategy or stays unassigned.</p>
<ul class="text-muted mb-0">
    <li><strong>0.5</strong> — accept most suggestions. Good while you're tuning.</li>
    <li><strong>0.7</strong> (default) — only act on confident matches. Recommended starting point.</li>
    <li><strong>0.85+</strong> — very strict. Most ambiguous tickets fall through to manual triage.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-emoji-frown text-warning me-2"></i>Sentiment-Driven Priority Bump</h5>
<p class="text-muted mb-2">When the AI flags sentiment as <strong>angry</strong> or <strong>urgent</strong>, OpenHelpDesk can automatically bump priority up one level (e.g. Medium → High). Toggle this on the AI settings page.</p>
<ul class="text-muted mb-0">
    <li>Bump fires on the AI verdict, regardless of group strategy — useful even when the destination group uses Round Robin or Manual.</li>
    <li>Each ticket is bumped at most once (timeline entry <code>ai_priority_bumped</code> is the dedup marker).</li>
    <li>Tickets at the highest priority can't be bumped further. Tickets with no priority are jumped to the highest.</li>
    <li>"Frustrated" alone does not bump — that's a softer signal we surface for reporting only.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-pencil-square text-primary me-2"></i>Override &amp; Re-Classify</h5>
<p class="text-muted mb-2">Every ticket detail page has an <strong>AI Classification</strong> card in the sidebar (when enabled or when a verdict exists). From there:</p>
<ul class="text-muted mb-0">
    <li><strong>Override</strong> — open a modal and tick the skills the ticket actually needs. Your selection replaces the AI's for routing; the original verdict stays on the record. The override is audit-logged as <code>ai.classification_override</code> and posted as an internal timeline entry.</li>
    <li><strong>Re-classify</strong> — re-runs the AI call (e.g. after editing the subject / body). Creates a fresh <code>ai_classifications</code> row; history is preserved.</li>
    <li><strong>Classify now</strong> — appears when a ticket has no classification yet (e.g. AI was disabled when it was created).</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-repeat text-primary me-2"></i>Backfilling Older Tickets</h5>
<p class="text-muted mb-2">Two ways to classify tickets created before AI was enabled:</p>
<ol class="text-muted mb-0">
    <li><strong>UI button</strong> — at the bottom of <a href="/admin/settings/ai">Settings → AI Classification</a>. Pick a batch size (1–200), click Run. Bounded by PHP's request timeout, but fine for batches up to ~25 with the default 5-second per-call cap.</li>
    <li><strong>Cron / CLI</strong> — for larger backfills, run <code>php scripts/ai-classify-backfill.php --limit=N --statuses=open,in_progress,pending</code>. Add <code>--dry-run</code> to see what <em>would</em> be classified without making any API calls. The script logs to stdout; pipe to a logfile if scheduling.</li>
</ol>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-check text-primary me-2"></i>Failure Modes &amp; Resilience</h5>
<ul class="text-muted mb-0">
    <li><strong>Provider down / slow</strong> — the call has a hard wall-clock timeout (default 5s). If exceeded, the ticket is created normally and routing falls back. No portal user ever sees an error from a misbehaving AI provider.</li>
    <li><strong>Bad JSON from the model</strong> — falls back. Logged to PHP error log.</li>
    <li><strong>Skill ID hallucination</strong> — the parser strips any IDs not in the candidate list before persisting. The model can't invent skills.</li>
    <li><strong>Confidence below threshold</strong> — verdict is stored (for reporting / override) but skipped for routing.</li>
    <li><strong>API key missing</strong> — the factory returns null; the feature is silently disabled until configured.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-clipboard-data text-primary me-2"></i>Audit &amp; Telemetry</h5>
<ul class="text-muted mb-0">
    <li>Every classification persists provider, model, latency, prompt + output token counts, and the raw provider response (for debugging).</li>
    <li>Internal timeline entries: <code>ai_classified</code> on every classification, <code>ai_priority_bumped</code> when sentiment triggers a bump, <code>ai_override</code> when a human edits the suggestion. Portal users never see these.</li>
    <li>Audit log entries: <code>ai.settings_changed</code>, <code>ai.backfill_run</code>, <code>ai.classification_override</code> (visible in the <a href="/admin/audit-log">Audit Log</a>).</li>
    <li>The Recent Classifications strip on the AI settings page surfaces the last 10 runs at a glance — handy for confirming the integration is healthy after a model swap.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-currency-dollar text-primary me-2"></i>Cost Sanity Check</h5>
<p class="text-muted mb-0">A typical classification uses ~500 prompt tokens + ~100 output tokens. At the default Anthropic Haiku pricing (a fraction of a cent per call) that's well under $1 per 1,000 tickets. Monitor the latency / token counts on the Recent Classifications strip — anything wildly off suggests an unusually long ticket body or a prompt-injection attempt and is worth investigating.</p>
</div>
</div>

</div><!-- col -->
</div><!-- row -->

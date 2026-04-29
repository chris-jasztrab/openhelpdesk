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
    <li>If AI is enabled and the ticket type is <strong>not</strong> confidential, LocalDesk sends the subject + body plus the candidate skill list (global skills + the destination group's skills) to the configured provider.</li>
    <li>The provider returns JSON: <code>skill_ids</code>, a confidence score, and a sentiment label.</li>
    <li>Result is stored in <code>ai_classifications</code> and pointed at by <code>tickets.ai_classification_id</code>. The ticket also gets a denormalised <code>ai_sentiment</code> for fast filtering.</li>
    <li>If the group's auto-assign strategy is <strong>AI Skill-Based</strong> AND confidence ≥ threshold, the routing engine picks the least-loaded group member who holds <em>all</em> the suggested skills. Otherwise it falls through to the group's configured fallback (load-based, round-robin, or leave unassigned).</li>
    <li>If the AI flagged sentiment as "angry" or "urgent" and the bump toggle is on, ticket priority is bumped one notch and a timeline entry is recorded.</li>
</ol>
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
<p class="text-muted mb-2">When the AI flags sentiment as <strong>angry</strong> or <strong>urgent</strong>, LocalDesk can automatically bump priority up one level (e.g. Medium → High). Toggle this on the AI settings page.</p>
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
    <li><strong>Override</strong> — open a modal and tick the skills the ticket actually needs. Your selection replaces the AI's for routing; the original verdict stays on the record. The override is audit-logged as <code>ai_classification_override</code> and posted as an internal timeline entry.</li>
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
    <li>Audit log entries: <code>ai_settings_saved</code>, <code>ai_backfill_run</code>, <code>ai_classification_override</code>.</li>
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

<?php
$layout       = 'app';
$pageTitle    = 'Docs: Satisfaction Surveys';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [['label'=>'Admin','url'=>'/admin'],['label'=>'Docs','url'=>'/admin/docs'],['label'=>'Satisfaction Surveys']];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Satisfaction Surveys (CSAT)</h2>
<p class="text-muted mb-4">Automatically email a rating request when a ticket is resolved or closed. You can use the built-in 1&ndash;5 star page or link recipients to an external survey tool (HappyOrNot, SurveyGizmo / Alchemer, Jotform, Typeform, Microsoft Forms, Google Forms, etc.). Results &mdash; from either mode &mdash; now appear <strong>right on the ticket</strong>, and external tools can push responses back through a signed <a href="#webhook">webhook</a> so they land in your reports just like built-in ratings.</p>

<div class="alert alert-info small mb-4"><i class="bi bi-info-circle me-2"></i>
    Configure everything described here at <a href="/admin/settings/csat"><strong>Admin &rarr; Settings &rarr; CSAT Surveys</strong></a>. View collected results at <a href="/admin/reports/csat"><strong>Admin &rarr; Settings &rarr; Reports &rarr; Satisfaction</strong></a>.
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-signpost-2 text-primary me-2"></i>On this page</h5>
<ul class="text-muted mb-0">
    <li><a href="#sending">How and when a survey is sent</a></li>
    <li><a href="#modes">Survey type: Built-in vs. External</a></li>
    <li><a href="#placeholders">External URL placeholders</a> &mdash; passing the ticket through to your survey tool</li>
    <li><a href="#on-ticket">Results on the ticket</a> &mdash; the new Satisfaction panel</li>
    <li><a href="#webhook"><strong>External response webhook</strong></a> &mdash; full setup, signature, payload, examples, per-provider notes, troubleshooting</li>
    <li><a href="#reopen-button">The reopen button</a></li>
    <li><a href="#reports">Where the results show up</a> (reports)</li>
    <li><a href="#settings-ref">Settings reference</a></li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="sending">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-send text-primary me-2"></i>How and when a survey is sent</h5>
<p class="text-muted mb-2">When CSAT is enabled, the app watches for tickets reaching the <strong>trigger status</strong> (default <em>Resolved</em>, or you can switch to <em>Closed</em>). The moment a ticket lands on that status &mdash; whether the agent posted a public reply with a status change or used the quick-status modal &mdash; a survey email is queued to the ticket's <strong>requester</strong> (the user in <code>created_by</code>, not whoever submitted the ticket on their behalf).</p>
<p class="text-muted mb-2"><strong>Guards that keep things sane:</strong></p>
<ul class="text-muted mb-0">
    <li>Only <strong>one survey per ticket</strong> &mdash; if a ticket is re-resolved after being reopened, no duplicate email is sent.</li>
    <li>The requester's <strong>"Send me satisfaction surveys"</strong> notification preference is respected. If they've turned it off in their profile, no email goes out.</li>
    <li>If <strong>External</strong> mode is selected but the URL is blank, the send is skipped entirely (and doesn't burn the one-survey-per-ticket slot) so the misconfiguration is fixable.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="modes">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-toggle2-on text-primary me-2"></i>Survey type: Built-in vs. External</h5>
<p class="text-muted mb-3">Pick one of two modes:</p>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-2"><i class="bi bi-star me-1 text-warning"></i>Built-in</div>
            <p class="text-muted small mb-2">The rating button in the email opens a public page hosted by this app (no login required). Recipients pick 1&ndash;5 stars and can leave an optional comment.</p>
            <p class="text-muted small mb-0"><strong>Results live in this app</strong> &mdash; visible on the Satisfaction report with average rating, response rate, distribution, and full per-ticket comments.</p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-2"><i class="bi bi-box-arrow-up-right me-1 text-primary"></i>External</div>
            <p class="text-muted small mb-2">The rating button links to a URL you provide &mdash; typically a HappyOrNot smiley page, a SurveyGizmo / Alchemer link, a Jotform, a Typeform, or any other web survey.</p>
            <p class="text-muted small mb-0">Results live in the external service by default. This app always tracks <em>that</em> a survey was sent (so the "Surveys Sent" count and the one-per-ticket guard keep working). <strong>If you wire up the <a href="#webhook">webhook</a></strong>, the rating and comment also flow back onto the ticket and into your reports &mdash; closing the gap between the two modes.</p>
        </div>
    </div>
</div>

<p class="text-muted mb-2 small"><i class="bi bi-lightbulb text-warning me-1"></i> Picking External does not delete any previously collected built-in ratings &mdash; they remain visible on the report under their original dates.</p>
<p class="text-muted mb-0 small"><i class="bi bi-shield-check text-success me-1"></i> <strong>Which should you choose?</strong> Built-in is zero-setup and keeps all data in one place. Choose External when you already run a survey programme (HappyOrNot kiosks, a corporate Typeform/Qualtrics account, brand-controlled survey design) and want CSAT to feed it &mdash; then add the webhook so you don't lose ticket-level reporting.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="placeholders">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-braces text-primary me-2"></i>External URL placeholders</h5>
<p class="text-muted mb-2">When you paste an external survey URL, you can include any of these <code>{placeholder}</code> tokens. Each value is URL-encoded at send time, so they're safe to drop straight into a query string.</p>
<div class="table-responsive mb-3">
    <table class="table table-sm mb-0">
        <thead class="table-light">
            <tr><th style="width:170px;">Placeholder</th><th>Replaced with</th></tr>
        </thead>
        <tbody>
            <tr><td><code>{ticket_id}</code></td><td>The numeric ticket ID (e.g. <code>1234</code>). Enumerable &mdash; fine to use because the webhook is signed, but <code>{token}</code> is preferred for matching responses back.</td></tr>
            <tr><td><code>{token}</code></td><td>The survey's unique 64-character token. Unguessable, one per ticket. Pass this through if your tool can carry a hidden field, then echo it back via the webhook to match the response to the right ticket.</td></tr>
            <tr><td><code>{user_email}</code></td><td>The requester's email address.</td></tr>
            <tr><td><code>{first_name}</code></td><td>Requester's first name.</td></tr>
            <tr><td><code>{last_name}</code></td><td>Requester's last name.</td></tr>
            <tr><td><code>{user_name}</code></td><td>First + last name combined.</td></tr>
            <tr><td><code>{subject}</code></td><td>The ticket subject line.</td></tr>
        </tbody>
    </table>
</div>
<p class="text-muted mb-2"><strong>Example:</strong></p>
<div class="bg-light rounded px-3 py-2 font-monospace small mb-3" style="word-break:break-all;">
    https://survey.example.com/start?tid={ticket_id}&amp;ref={token}&amp;email={user_email}&amp;subj={subject}
</div>
<p class="text-muted small mb-2"><i class="bi bi-arrow-return-right me-1"></i>Whatever you pass as <code>{ticket_id}</code> or <code>{token}</code> here is exactly what your survey tool should send back in the <a href="#webhook">webhook</a> so the response can be matched to its ticket.</p>
<p class="text-muted mb-2"><strong>Per-provider notes:</strong></p>
<ul class="text-muted mb-0">
    <li><strong>HappyOrNot, single-link smiley surveys</strong> &mdash; usually one shared URL per kiosk/page. Paste it as-is; you can ignore the placeholders.</li>
    <li><strong>SurveyGizmo / Alchemer, Jotform, Typeform, Microsoft Forms (with pre-fill enabled)</strong> &mdash; use placeholders in the query string to pre-fill hidden fields so each response can be linked back to the originating ticket.</li>
    <li><strong>Unknown placeholders are left intact</strong> in the rendered URL (not stripped or blanked) so a typo like <code>{ticked_id}</code> is visible in the email for debugging.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="on-ticket">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-window-stack text-primary me-2"></i>Results on the ticket</h5>
<p class="text-muted mb-2">Once a survey has been sent for a ticket, a <strong>Satisfaction Survey</strong> panel appears in the details column of both the <strong>agent</strong> and <strong>admin</strong> ticket views. It shows one of these states:</p>
<ul class="text-muted mb-3">
    <li><strong>Responded</strong> &mdash; the 1&ndash;5 star rating, the score (e.g. <em>4/5</em>), the requester's comment if they left one, and when they responded.</li>
    <li><strong>Awaiting response</strong> &mdash; &ldquo;Survey sent &lt;date&gt; &mdash; awaiting response.&rdquo; In External mode this also reminds you that ratings only land here if the webhook is wired up.</li>
    <li><strong>Reopened by requester</strong> &mdash; a badge appears if the recipient used the email's reopen button.</li>
</ul>
<p class="text-muted mb-2">The panel also has an <strong>Open survey</strong> button linking to the <em>exact</em> URL that was emailed &mdash; the built-in star page, or your external survey with its placeholders filled in &mdash; so you can see precisely what the requester received.</p>
<p class="text-muted mb-2">When a rating lands (from the built-in page <em>or</em> the webhook), it's also written to the ticket timeline as an internal <strong>Satisfaction Rating</strong> entry, so it shows inline in the conversation. Internal entries are never visible to the requester.</p>
<p class="text-muted mb-0 small"><i class="bi bi-info-circle text-primary me-1"></i> Tickets resolved before this feature shipped won't have the <em>Open survey</em> link (the link wasn't stored back then), but the rating / awaiting state still displays correctly.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="webhook">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-repeat text-primary me-2"></i>External response webhook</h5>
<p class="text-muted mb-2">In External mode, your survey tool collects the rating &mdash; this app never sees it, so the Satisfaction report and the on-ticket panel stay empty. The <strong>response webhook</strong> closes that gap: your tool POSTs each submitted response back to this app, and it lands on the ticket and in your reports exactly like a built-in rating.</p>
<p class="text-muted mb-3">It's optional. Skip it and External mode works fine &mdash; you just read ratings in your survey tool's own dashboard (see the <a href="#reports">External dashboard URL</a> setting). Wire it up when you want ticket-level CSAT reporting inside this app.</p>

<h6 class="fw-semibold mb-2">1. Get your endpoint and secret</h6>
<p class="text-muted mb-2">Set <strong>Survey type = External</strong>, fill in the survey URL, and <strong>Save</strong>. A signing secret is generated automatically the first time you save in External mode. The settings page then shows two values:</p>
<div class="table-responsive mb-3">
    <table class="table table-sm mb-0">
        <thead class="table-light"><tr><th style="width:160px;">Value</th><th>Use</th></tr></thead>
        <tbody>
            <tr><td><strong>Webhook URL</strong></td><td>Where your tool POSTs responses. It's <code>&lt;your-site&gt;/api/csat/webhook</code>, derived from your <code>APP_URL</code>. (If <code>APP_URL</code> is wrong, this will be wrong &mdash; fix it in <code>.env</code> first.)</td></tr>
            <tr><td><strong>Signing secret</strong></td><td>A shared secret used to sign and verify each request. Treat it like a password. Tick <em>Rotate secret on save</em> to mint a new one (then update your tool).</td></tr>
        </tbody>
    </table>
</div>

<h6 class="fw-semibold mb-2">2. The request your tool must send</h6>
<ul class="text-muted mb-2">
    <li><strong>Method:</strong> <code>POST</code> to the Webhook URL, over HTTPS.</li>
    <li><strong>Header:</strong> <code>Content-Type: application/json</code></li>
    <li><strong>Header:</strong> <code>X-CSAT-Signature:</code> the hex HMAC-SHA256 of the <em>raw request body</em>, keyed with your signing secret.</li>
    <li><strong>Body:</strong> a JSON object with these fields:</li>
</ul>
<div class="table-responsive mb-3">
    <table class="table table-sm mb-0">
        <thead class="table-light"><tr><th style="width:130px;">Field</th><th>Type</th><th>Notes</th></tr></thead>
        <tbody>
            <tr><td><code>rating</code></td><td>integer 1&ndash;5</td><td><strong>Required.</strong> Map your tool's scale to 1&ndash;5 before sending (e.g. a HappyOrNot smiley or a 1&ndash;10 NPS must be converted).</td></tr>
            <tr><td><code>token</code></td><td>string (64 hex)</td><td>Identifies the survey. <strong>Preferred.</strong> Use the <code>{token}</code> you passed into the survey URL.</td></tr>
            <tr><td><code>ticket_id</code></td><td>integer</td><td>Alternative to <code>token</code>. One of the two is required; if both are sent, <code>token</code> wins.</td></tr>
            <tr><td><code>comment</code></td><td>string</td><td>Optional free-text comment. Trimmed; anything over 2000 characters is truncated.</td></tr>
        </tbody>
    </table>
</div>

<div class="alert alert-warning small mb-3"><i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Sign the exact bytes you send.</strong> Compute the HMAC over the literal request body string, then send that <em>same</em> string. The single most common failure is signing one JSON string but transmitting a re-serialized one (different spacing or key order) &mdash; the signatures won't match and you'll get a <code>401</code>.
</div>

<h6 class="fw-semibold mb-2">3. Worked examples</h6>
<p class="text-muted small mb-1"><strong>bash / curl</strong> (uses OpenSSL for the HMAC):</p>
<pre class="bg-light rounded p-3 small mb-3" style="overflow:auto;"><code>SECRET="paste-your-signing-secret"
BODY='{"ticket_id":1234,"rating":5,"comment":"Great service!"}'
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

curl -X POST https://help.example.com/api/csat/webhook \
  -H "Content-Type: application/json" \
  -H "X-CSAT-Signature: $SIG" \
  -d "$BODY"</code></pre>

<p class="text-muted small mb-1"><strong>PHP:</strong></p>
<pre class="bg-light rounded p-3 small mb-3" style="overflow:auto;"><code>&lt;?php
$secret  = 'paste-your-signing-secret';
$payload = json_encode([
    'token'   =&gt; '…64-hex token…',
    'rating'  =&gt; 5,
    'comment' =&gt; 'Great service!',
]);
$sig = hash_hmac('sha256', $payload, $secret);

$ch = curl_init('https://help.example.com/api/csat/webhook');
curl_setopt_array($ch, [
    CURLOPT_POST           =&gt; true,
    CURLOPT_POSTFIELDS     =&gt; $payload,           // send the exact string we signed
    CURLOPT_HTTPHEADER     =&gt; ['Content-Type: application/json', 'X-CSAT-Signature: ' . $sig],
    CURLOPT_RETURNTRANSFER =&gt; true,
]);
echo curl_exec($ch);</code></pre>

<p class="text-muted small mb-1"><strong>Python:</strong></p>
<pre class="bg-light rounded p-3 small mb-3" style="overflow:auto;"><code>import hmac, hashlib, json, requests

SECRET  = "paste-your-signing-secret"
payload = json.dumps({"ticket_id": 1234, "rating": 5, "comment": "Great service!"})
sig     = hmac.new(SECRET.encode(), payload.encode(), hashlib.sha256).hexdigest()

requests.post("https://help.example.com/api/csat/webhook",
              data=payload,   # send the same string we hashed, not a re-dumped dict
              headers={"Content-Type": "application/json", "X-CSAT-Signature": sig})</code></pre>

<p class="text-muted small mb-1"><strong>Node.js</strong> (also the pattern for a Google Apps Script / Make / Zapier relay):</p>
<pre class="bg-light rounded p-3 small mb-3" style="overflow:auto;"><code>const crypto = require('crypto');

const SECRET = 'paste-your-signing-secret';
const body   = JSON.stringify({ token: '…64-hex token…', rating: 5, comment: 'Great service!' });
const sig    = crypto.createHmac('sha256', SECRET).update(body).digest('hex');

await fetch('https://help.example.com/api/csat/webhook', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'X-CSAT-Signature': sig },
  body,
});</code></pre>

<h6 class="fw-semibold mb-2">4. Responses you'll get back</h6>
<div class="table-responsive mb-3">
    <table class="table table-sm mb-0">
        <thead class="table-light"><tr><th style="width:90px;">Status</th><th>Body</th><th>Meaning</th></tr></thead>
        <tbody>
            <tr><td><code>200</code></td><td><code>{"ok":true,"status":"recorded"}</code></td><td>Saved. Rating + comment are now on the ticket and in reports.</td></tr>
            <tr><td><code>200</code></td><td><code>{"ok":true,"status":"already_recorded"}</code></td><td>A response was already stored for this ticket. Ignored &mdash; <strong>first response wins</strong>. Safe to retry.</td></tr>
            <tr><td><code>400</code></td><td><code>Body must be a JSON object</code></td><td>The body wasn't valid JSON.</td></tr>
            <tr><td><code>401</code></td><td><code>Invalid or missing signature</code></td><td>No <code>X-CSAT-Signature</code>, or it didn't match. Wrong secret, or you signed different bytes than you sent.</td></tr>
            <tr><td><code>404</code></td><td><code>No survey found for that ticket</code></td><td>No survey row matches that <code>token</code>/<code>ticket_id</code>. Was a survey ever sent for it?</td></tr>
            <tr><td><code>422</code></td><td><code>rating must be 1 to 5</code> / <code>token or ticket_id is required</code></td><td>Validation failed.</td></tr>
            <tr><td><code>503</code></td><td><code>CSAT webhook is not configured</code></td><td>No signing secret saved yet. Save Settings &rarr; CSAT once with External selected.</td></tr>
        </tbody>
    </table>
</div>
<p class="text-muted small mb-0">The endpoint is <strong>idempotent</strong>: replays and your tool's automatic retries are safe, and a (validly signed) duplicate can never overwrite a rating that's already recorded.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="webhook-providers">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-plug text-primary me-2"></i>Connecting specific survey tools</h5>
<div class="alert alert-secondary small mb-3"><i class="bi bi-lightbulb me-2"></i>
    <strong>The key constraint:</strong> most survey tools can fire a webhook on submission, but few can compute an <em>HMAC-SHA256 of a secret</em> natively. So the usual pattern is a tiny <strong>relay</strong> &mdash; a Google Apps Script, an Azure / AWS / Cloudflare function, or a Make / Zapier "Run code" step &mdash; that receives the tool's submission, builds the JSON, computes the signature, and forwards it here (the Node.js example above is exactly that relay). Tools that can run custom code at submit time can skip the relay.
</div>
<div class="table-responsive">
    <table class="table table-sm mb-0">
        <thead class="table-light"><tr><th style="width:160px;">Tool</th><th>Approach</th></tr></thead>
        <tbody>
            <tr><td><strong>Google Forms</strong></td><td>Best fit &mdash; no relay needed. Add an <code>onFormSubmit</code> Apps Script trigger; Apps Script computes the HMAC with <code>Utilities.computeHmacSha256Signature(...)</code> and POSTs via <code>UrlFetchApp</code>. Pass the ticket id/token into the form as a pre-filled hidden question.</td></tr>
            <tr><td><strong>Typeform</strong></td><td>Carry the ticket through as a <em>hidden field</em> (<code>?ref={token}</code>). Typeform can send a webhook on submit, but can't sign it &mdash; point its webhook at a relay that adds the signature and forwards here.</td></tr>
            <tr><td><strong>Jotform</strong></td><td>Hidden field for the ticket id/token; Jotform's webhook / "Send POST" → relay to sign, then forward.</td></tr>
            <tr><td><strong>SurveyGizmo / Alchemer</strong></td><td>Pass the ticket through as a URL variable; use a Webhook action on submit → relay to sign.</td></tr>
            <tr><td><strong>Microsoft Forms</strong></td><td>No native webhook. Use <strong>Power Automate</strong>: "When a new response is submitted" → compute HMAC (an Azure Function step, since Power Automate has no built-in HMAC) → HTTP POST here. Carrying a per-ticket id into MS Forms is awkward, so this suits generic surveys better.</td></tr>
            <tr><td><strong>HappyOrNot</strong> and kiosk/aggregate tools</td><td>These collect anonymous, aggregate feedback with no per-response webhook or ticket context, so responses <em>can't</em> be matched back to a ticket. Use the <a href="#reports">External dashboard URL</a> to link admins to the HappyOrNot dashboard instead.</td></tr>
        </tbody>
    </table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="webhook-troubleshooting">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-wrench-adjustable text-primary me-2"></i>Webhook checklist &amp; troubleshooting</h5>
<p class="text-muted mb-2"><strong>End-to-end checklist:</strong></p>
<ol class="text-muted mb-3">
    <li>Settings &rarr; CSAT: <em>Enable CSAT</em> on, <em>Survey type = External</em>, survey URL set and including <code>{ticket_id}</code> or <code>{token}</code>.</li>
    <li>Save once &mdash; copy the <strong>Webhook URL</strong> and <strong>signing secret</strong>.</li>
    <li>In your survey tool (or relay), POST the signed JSON to the Webhook URL on each submission.</li>
    <li>Trigger a real survey &mdash; resolve a test ticket, or use <em>Send a Test Survey</em> &mdash; and complete it in the external tool.</li>
    <li>Open that ticket: the Satisfaction panel should show the rating, and the Satisfaction report should count it.</li>
</ol>
<p class="text-muted mb-2"><strong>If it's not working:</strong></p>
<div class="table-responsive mb-0">
    <table class="table table-sm mb-0">
        <thead class="table-light"><tr><th style="width:120px;">Symptom</th><th>Likely cause &amp; fix</th></tr></thead>
        <tbody>
            <tr><td><code>401</code></td><td>Secret mismatch, or you signed a different string than you sent. Recompute the HMAC over the exact body bytes; confirm the secret matches the one shown in settings (re-copy it &mdash; a rotation invalidates the old one).</td></tr>
            <tr><td><code>404</code></td><td>No matching survey. Confirm CSAT is enabled and a survey was actually sent for that ticket (its panel should read "sent"), and that you're sending the right <code>token</code>/<code>ticket_id</code>. Remember: only one survey exists per ticket.</td></tr>
            <tr><td><code>503</code></td><td>No signing secret exists yet &mdash; save Settings &rarr; CSAT with External selected at least once.</td></tr>
            <tr><td><code>422</code></td><td><code>rating</code> isn't an integer 1&ndash;5 (map your scale first), or you sent neither <code>token</code> nor <code>ticket_id</code>.</td></tr>
            <tr><td>2xx but nothing on the report</td><td>You're getting <code>already_recorded</code> (a response was stored earlier) or the report's date range excludes it. A response only counts once <code>recorded</code>.</td></tr>
            <tr><td>Works locally, fails in production</td><td>The Webhook URL is derived from <code>APP_URL</code>. Point your tool at the production URL and make sure <code>APP_URL</code> in <code>.env</code> matches how the site is actually reached.</td></tr>
        </tbody>
    </table>
</div>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="webhook-security">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-shield-lock text-primary me-2"></i>Webhook security notes</h5>
<ul class="text-muted mb-0">
    <li>The endpoint is <strong>public</strong> (no login) but every request is authenticated by the HMAC signature &mdash; only a caller who holds the secret can post a response. An unsigned or wrongly signed request is rejected with <code>401</code>.</li>
    <li>Always use <strong>HTTPS</strong> so the body and signature can't be read in transit.</li>
    <li>Treat the signing secret like a password. If it leaks, tick <em>Rotate secret on save</em> and update your tool/relay with the new value.</li>
    <li><code>ticket_id</code> is sequential and therefore guessable, but forging a response still requires the secret. Using <code>{token}</code> (unguessable, one per ticket) adds defence in depth.</li>
    <li>Idempotency means a captured-and-replayed request can't change an already-recorded rating &mdash; the first valid response wins.</li>
    <li>There's no built-in rate limiting on the endpoint; if it's internet-facing, keep it behind your normal web/WAF protections.</li>
</ul>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="reopen-button">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-arrow-counterclockwise text-primary me-2"></i>The reopen button</h5>
<p class="text-muted mb-2">There's a separate switch &mdash; <strong>Include &ldquo;No, please reopen it&rdquo; button</strong> &mdash; that controls the email layout. It works the same for both modes:</p>
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-2">Switch ON (default)</div>
            <p class="text-muted small mb-2">The email shows <strong>two buttons</strong>: a red <em>&ldquo;No, please reopen it&rdquo;</em> and a green <em>&ldquo;Yes, it was resolved!&rdquo;</em>.</p>
            <p class="text-muted small mb-0">The reopen button always points back to this app (one click and the ticket flips back to Open with a timeline note). The rate button goes to either the built-in star page or your external URL, depending on the mode.</p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-2">Switch OFF</div>
            <p class="text-muted small mb-2">The email collapses to a <strong>single centered button</strong> &mdash; <em>&ldquo;Rate your experience&rdquo;</em> &mdash; styled in your brand colour.</p>
            <p class="text-muted small mb-0">Cleaner for a pure rating flow. Recipients who actually need their ticket reopened will have to reply to the ticket email or visit the portal &mdash; the email no longer offers a one-click reopen.</p>
        </div>
    </div>
</div>
<p class="text-muted mb-0 small"><i class="bi bi-lightbulb text-warning me-1"></i> External surveys can't trigger reopens on their own &mdash; the survey provider has no idea what a "ticket" is. If you want recipients to be able to reopen with one click, keep this switch on even in External mode.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="reports">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-bar-chart text-primary me-2"></i>Where the results show up</h5>
<p class="text-muted mb-2">The <a href="/admin/reports/csat"><strong>Settings &rarr; Reports &rarr; Satisfaction</strong></a> page is the same in both modes, but it shows different things:</p>
<ul class="text-muted mb-3">
    <li><strong>Built-in mode</strong> &mdash; full picture: surveys sent, responses, response rate, average rating, star distribution, and a table of every comment (linked to its ticket).</li>
    <li><strong>External mode <u>with</u> the webhook</strong> &mdash; the same full picture. Responses posted back via the <a href="#webhook">webhook</a> count toward response rate, average, distribution and comments just like built-in ratings.</li>
    <li><strong>External mode <u>without</u> the webhook</strong> &mdash; only the <em>Surveys Sent</em> count is meaningful; responses, average rating, etc. show as 0 because the ratings live only in your external system. A blue banner at the top of the report explains this.</li>
</ul>
<p class="text-muted mb-0">If you fill in the optional <strong>External dashboard URL</strong> setting (e.g. a link to your SurveyGizmo report or HappyOrNot dashboard), the banner gains an <strong>Open external dashboard</strong> button so admins can jump straight to where the ratings live &mdash; useful for tools that can't post responses back (like HappyOrNot).</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-send-check text-primary me-2"></i>Sending a test survey</h5>
<p class="text-muted mb-2">The <strong>Send a Test Survey</strong> form at the bottom of <a href="/admin/settings/csat">the CSAT settings page</a> uses the same logic as a real send, so you can preview the email end-to-end &mdash; including external URLs with placeholders substituted &mdash; before any real ticket triggers one.</p>
<p class="text-muted mb-0">Test emails reuse the most recent ticket that doesn't already have a survey attached, so the placeholders show plausible values rather than dummy data.</p>
</div>
</div>

<div class="card border-0 shadow-sm mb-4" id="settings-ref">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-list-check text-primary me-2"></i>Settings reference</h5>
<div class="table-responsive">
    <table class="table table-sm mb-0">
        <thead class="table-light">
            <tr><th style="width:240px;">Setting</th><th>What it does</th></tr>
        </thead>
        <tbody>
            <tr><td><strong>Enable CSAT surveys</strong></td><td>Master switch. Off = no survey emails ever go out, regardless of the other settings below.</td></tr>
            <tr><td><strong>Send survey when ticket is</strong></td><td><em>Resolved</em> (default, recommended) or <em>Closed</em>. Picks the status that triggers the email.</td></tr>
            <tr><td><strong>Survey type</strong></td><td><em>Built-in</em> (default) hosts the rating page in this app. <em>External</em> links to a URL you provide.</td></tr>
            <tr><td><strong>External survey URL</strong></td><td>Required when type is External. Supports <code>{ticket_id}</code>, <code>{token}</code>, <code>{user_email}</code>, <code>{first_name}</code>, <code>{last_name}</code>, <code>{user_name}</code>, <code>{subject}</code>.</td></tr>
            <tr><td><strong>External dashboard URL</strong></td><td>Optional. Surfaces as an <em>Open external dashboard</em> button on the Satisfaction report.</td></tr>
            <tr><td><strong>Webhook URL</strong></td><td>Read-only. The endpoint your external tool POSTs responses to (<code>/api/csat/webhook</code>, derived from <code>APP_URL</code>). See <a href="#webhook">the webhook section</a>.</td></tr>
            <tr><td><strong>Signing secret</strong></td><td>Auto-generated on first External save. Used to sign/verify webhook requests. <em>Rotate secret on save</em> mints a new one and invalidates the old.</td></tr>
            <tr><td><strong>Include &ldquo;No, please reopen it&rdquo; button</strong></td><td>On = two buttons (reopen + rate). Off = single centered "Rate your experience" button. Affects both modes.</td></tr>
        </tbody>
    </table>
</div>
</div>
</div>

</div></div>

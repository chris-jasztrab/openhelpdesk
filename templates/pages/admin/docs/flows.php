<?php
$layout       = 'app';
$pageTitle    = 'Docs: Assignment Flow Diagrams';
$sidebarItems = adminSidebar('docs');
$breadcrumbs  = [
    ['label' => 'Admin', 'url' => '/admin'],
    ['label' => 'Docs',  'url' => '/admin/docs'],
    ['label' => 'Assignment Flows'],
];
?>
<div class="row g-4">
<div class="col-lg-3"><?php require ROOT_DIR . '/templates/partials/docs-nav.php'; ?></div>
<div class="col-lg-9">

<h2 class="fw-bold mb-1">Ticket Assignment Flow Diagrams</h2>
<p class="text-muted mb-4">Step-by-step diagrams for every auto-assignment strategy, plus the master flow that runs on every new ticket and the catch-all fallbacks that prevent tickets from getting stuck. Diagrams render via <a href="https://mermaid.js.org/" target="_blank" rel="noopener">Mermaid</a> in your browser — no external rendering involved.</p>

<style>
  .flow-diagram { background: #f6f8fa; border: 1px solid #d0d7de; border-radius: 8px; padding: 18px; margin: 16px 0 24px; text-align: center; overflow-x: auto; }
  .flow-diagram .mermaid { font-size: 14px; }
</style>

<div class="card border-0 shadow-sm mb-4">
<div class="card-body p-4">
<h5 class="fw-semibold mb-3"><i class="bi bi-people text-primary me-2"></i>The Cast Used in Every Example</h5>
<p class="text-muted mb-2">One IT group, four agents. The same data appears in every diagram so you can compare strategies side-by-side.</p>
<p class="text-muted mb-2"><strong>Group:</strong> IT — last round-robin pick was Bob.</p>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
<thead class="table-light"><tr><th>Agent</th><th>Skills</th><th>Open tickets</th><th>Online?</th></tr></thead>
<tbody class="text-muted">
<tr><td>Alice</td><td>Printers, Networking</td><td>3</td><td>yes — 30s ago</td></tr>
<tr><td>Bob</td><td>Printers, Servers</td><td>1</td><td>no — 10 min ago</td></tr>
<tr><td>Carol</td><td>Servers, Telephony</td><td>5</td><td>yes — 60s ago</td></tr>
<tr><td>Dave</td><td>Networking, Telephony</td><td>2</td><td>no — never seen</td></tr>
</tbody>
</table>
</div>
<p class="text-muted mb-1"><strong>Sample ticket:</strong> Jane Doe submits via the portal:</p>
<ul class="text-muted mb-0">
<li><strong>Subject:</strong> "Front desk printer is jamming on every print job"</li>
<li><strong>Type:</strong> "Printer Issue" — default group is IT, requires the Printers skill.</li>
</ul>
</div>
</div>

<h3 id="d0" class="fw-bold mt-5 mb-3">Diagram 0 — Master flow (every new ticket runs this)</h3>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<p class="text-muted mb-2">Each newly-created ticket flows through these steps before any group strategy runs. The four "group is set" gates are <strong>three layers of defence</strong> against tickets ending up in the no-group queue:</p>
<ol class="text-muted mb-3">
<li><strong>Creation-time</strong> — every creation path calls <code>resolveTicketGroup()</code>, which tries the caller's explicit choice, then the ticket type's default group, then the system-wide <strong>Default Group</strong> setting, then the lowest-id existing group.</li>
<li><strong>Post-create</strong> — after AI classification and automations run, <code>backfillTicketGroupFromDefault()</code> sweeps any ticket whose group_id is somehow still NULL into the default group.</li>
<li><strong>Hourly cron</strong> — <code>scripts/process-stale-tickets.php</code> sweeps any orphans the previous two layers missed (e.g. legacy data, hand-edited rows).</li>
</ol>
<div class="flow-diagram"><pre class="mermaid">
flowchart TD
    A[Someone submits a ticket] --> B["resolveTicketGroup()<br/>caller's pick → type's group →<br/>default_group_id setting →<br/>lowest-id existing group"]
    B --> C["INSERT INTO tickets<br/>(group_id rarely NULL after this)"]
    C --> D[runPostTicketCreateHooks]
    D --> E["classifyTicketWithAI<br/>(skipped for confidential types<br/>or when AI disabled)"]
    E --> F["runAutomations 'ticket_created'<br/>may overwrite group_id, type_id,<br/>or assigned_to"]
    F --> G["backfillTicketGroupFromDefault<br/>last-ditch NULL-group sweep<br/>routes to default_group_id"]
    G --> H{Has anything<br/>already assigned<br/>an agent?}
    H -->|yes — automation took care of it| ZA[Done]
    H -->|no| I{Did the ticket<br/>land in a group?}
    I -->|no — only possible on a fresh install<br/>with zero groups defined| ZB[Sits in 'no group' queue<br/>until cron sweep or admin triage]
    I -->|yes| J["autoAssignTicket reads<br/>group's assignment strategy"]
    J --> K{Which strategy?}
    K -->|Manual| D1[See Diagram 1]
    K -->|Round Robin| D2[See Diagram 2]
    K -->|Load-Based| D3[See Diagram 3]
    K -->|Skill-Based| D4[See Diagram 4]
    K -->|AI Skill-Based| D5[See Diagram 5]
    K -->|First Available| D6[See Diagram 6]
</pre></div>
<div class="alert alert-info small mb-0"><i class="bi bi-info-circle me-2"></i>
Auto-assignment only fires when a group is set AND no assignee has been picked yet. Automations execute <em>before</em> auto-assign, so a "set group" automation can redirect the ticket and a "set assignee" automation can short-circuit auto-assign entirely.
</div>
</div></div>

<h3 id="d1" class="fw-bold mt-5 mb-3">Diagram 1 — Manual (the default strategy)</h3>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<p class="text-muted mb-2">The system creates the ticket, parks it in the group's queue, and waits for a human to grab it.</p>
<div class="flow-diagram"><pre class="mermaid">
flowchart TD
    A["Jane submits 'Printer Issue'<br/>type's default group: IT"] --> B["Ticket created in IT group<br/>No agent assigned yet"]
    B --> C["Group's strategy: Manual"]
    C --> D[System doesn't pick anyone]
    D --> E[Ticket waits in IT queue]
    E --> F["An IT agent opens the queue<br/>and clicks 'assign to me'<br/>(or an admin assigns it)"]
</pre></div>
<p class="text-muted mb-0"><strong>Outcome:</strong> Whichever IT agent picks it up first owns it. <strong>When to use:</strong> Small teams who self-triage, or when you want a human in the loop on every ticket.</p>
</div></div>

<h3 id="d2" class="fw-bold mt-5 mb-3">Diagram 2 — Round Robin</h3>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<p class="text-muted mb-2">Cycles through the group's members in a fixed order. Ignores skill, current load, and online status — it just rotates.</p>
<div class="flow-diagram"><pre class="mermaid">
flowchart TD
    A["Jane submits 'Printer Issue'<br/>Ticket lands in IT group"] --> B["Group's strategy: Round Robin"]
    B --> C["IT group members in order:<br/>Alice → Bob → Carol → Dave"]
    C --> D["Bob got the previous<br/>round-robin pick"]
    D --> E["Pick the next person after Bob<br/>→ Carol"]
    E --> F["Ticket assigned to Carol<br/>Group remembers Carol got<br/>the latest pick"]
    F --> G[Carol gets the ticket]
    G -.- H["Next ticket → Dave<br/>Then → Alice (wraps around)<br/>Then → Bob"]
</pre></div>
<p class="text-muted mb-0"><strong>Outcome:</strong> Carol. Bob is least-loaded with the right skill, but round-robin doesn't care.</p>
</div></div>

<h3 id="d3" class="fw-bold mt-5 mb-3">Diagram 3 — Load-Based</h3>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<p class="text-muted mb-2">Picks whichever group member has the fewest currently-open tickets.</p>
<div class="flow-diagram"><pre class="mermaid">
flowchart TD
    A["Jane submits 'Printer Issue'<br/>Ticket lands in IT group"] --> B["Group's strategy: Load-Based"]
    B --> C[Look at IT group members]
    C --> D["Count each agent's<br/>currently-open tickets<br/>(ignoring resolved and closed)"]
    D --> E["Open ticket counts:<br/>Alice = 3, Bob = 1<br/>Carol = 5, Dave = 2<br/>Bob has the fewest"]
    E --> F[Ticket assigned to Bob]
    F -.- G["Tie-break: lower user_id wins<br/>(stable / deterministic)"]
</pre></div>
<p class="text-muted mb-0"><strong>Outcome:</strong> Bob. Doesn't check whether he's online or has the right skill — only load matters.</p>
</div></div>

<h3 id="d4" class="fw-bold mt-5 mb-3">Diagram 4 — Skill-Based (uses the ticket type's required skills)</h3>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<p class="text-muted mb-2">Filters group members down to those whose skills cover <strong>every</strong> skill the ticket type requires, then picks the least-loaded one.</p>
<div class="flow-diagram"><pre class="mermaid">
flowchart TD
    A["Jane submits 'Printer Issue'<br/>Ticket lands in IT group"] --> B["Group's strategy: Skill-Based"]
    B --> C[Look at IT group members]
    C --> D["Read the required skills<br/>checked on this ticket type:<br/>Printers"]
    D --> E{Any required<br/>skills checked?}
    E -->|No — none checked| FB["Use the group's<br/>fallback strategy<br/>(see Diagram 7)"]
    E -->|Yes| F["Keep only members who hold<br/>ALL the required skills"]
    F --> G["Eligible:<br/>Alice (has Printers) ✓<br/>Bob (has Printers) ✓<br/>Carol (no Printers) ✗<br/>Dave (no Printers) ✗"]
    G --> H{Anyone<br/>eligible?}
    H -->|No| FB
    H -->|Yes| I["Among eligible, pick least loaded:<br/>Alice = 3, Bob = 1 → Bob wins"]
    I --> J[Ticket assigned to Bob]
</pre></div>
<p class="text-muted mb-0"><strong>Outcome:</strong> Bob — has Printers AND fewer open tickets than Alice.</p>
</div></div>

<h3 id="d5" class="fw-bold mt-5 mb-3">Diagram 5 — AI Skill-Based</h3>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<p class="text-muted mb-2">Same shape as Diagram 4, but the "required skills" come from an AI reading the ticket's content instead of checkboxes on the ticket type.</p>
<div class="flow-diagram"><pre class="mermaid">
flowchart TD
    A["Jane submits ticket<br/>Subject: 'Front desk printer<br/>keeps jamming'<br/>Type: 'IT Issue'<br/>(general, no required skills)"] --> B[Ticket created in IT group]
    B --> C[Post-creation steps run]
    C --> D{Is the ticket type<br/>confidential?<br/>Or AI turned off?}
    D -->|Yes| FB["Skip AI →<br/>fallback strategy<br/>(Diagram 7)"]
    D -->|No| E["AI's candidate list:<br/>global skills + skills<br/>scoped to the IT group"]
    E --> F["AI reads the subject + description<br/>and picks the relevant skills<br/>from the candidate list"]
    F --> G["AI's verdict:<br/>Suggested skill: Printers<br/>Confidence: 92%"]
    G --> H["Group's strategy:<br/>AI Skill-Based"]
    H --> I{Is the AI<br/>confident enough?<br/>default 70%}
    I -->|No| FB
    I -->|Yes| J["Use the admin's manual override<br/>if one is set,<br/>otherwise the AI's suggestion"]
    J --> K["Keep only members who hold<br/>ALL the suggested skills"]
    K --> L["Eligible: Alice ✓, Bob ✓"]
    L --> M{Anyone<br/>eligible?}
    M -->|No| FB
    M -->|Yes| N["Among eligible, pick least loaded:<br/>Alice = 3, Bob = 1 → Bob"]
    N --> O[Ticket assigned to Bob]
</pre></div>
<p class="text-muted mb-0"><strong>Outcome:</strong> Bob — same result as plain Skill-Based, but you didn't have to maintain a "Printer Issue" ticket type with required skills checked. One generic "IT Issue" type is enough.</p>
</div></div>

<h3 id="d6" class="fw-bold mt-5 mb-3">Diagram 6 — First Available</h3>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<p class="text-muted mb-2">Filters group members to those currently online, then picks the least-loaded one.</p>
<div class="flow-diagram"><pre class="mermaid">
flowchart TD
    A["Jane submits 'Printer Issue'<br/>Ticket lands in IT group"] --> B["Group's strategy: First Available"]
    B --> C[Look at IT group members]
    C --> D[Check who has been<br/>active in the last 2 minutes]
    D --> E["Online right now:<br/>Alice (active 30s ago) ✓<br/>Bob (active 10 min ago) ✗<br/>Carol (active 60s ago) ✓<br/>Dave (never seen) ✗"]
    E --> F{Anyone<br/>online?}
    F -->|No| FB["Use the group's<br/>fallback strategy<br/>(Diagram 7)"]
    F -->|Yes| G["Among those online, pick least loaded:<br/>Alice = 3, Carol = 5 → Alice wins"]
    G --> H[Ticket assigned to Alice]
</pre></div>
<p class="text-muted mb-0"><strong>Outcome:</strong> Alice. Bob would have won on load alone but he's offline. Skills aren't checked at all. <strong>Why 2 minutes?</strong> Browsers throttle background-tab timers to about once-a-minute, so a 60-second window would mistakenly mark backgrounded agents as offline.</p>
</div></div>

<h3 id="d7" class="fw-bold mt-5 mb-3">Diagram 7 — Strategy fallback (when the primary rule can't pick anyone)</h3>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<p class="text-muted mb-2">Every strategy except Manual can come up empty (no eligible specialist, nobody online, AI not confident enough, etc.). When that happens, the system runs the group's <strong>fallback</strong> on the full membership — no skill / availability filtering.</p>
<div class="flow-diagram"><pre class="mermaid">
flowchart TD
    A["Primary strategy didn't pick anyone<br/>(no eligible specialist,<br/>no one online,<br/>AI not confident enough, etc.)"] --> B["Check the group's<br/>fallback setting"]
    B --> C{Fallback?}
    C -->|Round Robin| D["Run Round Robin<br/>across the full group<br/>(Diagram 2 logic)"]
    C -->|Load-Based| E["Run Load-Based<br/>across the full group<br/>(Diagram 3 logic)"]
    C -->|None| F["Don't assign anyone<br/>Ticket waits in the group queue<br/>for human triage"]
    D --> G[Agent picked → assigned]
    E --> G
    F --> H[No assignment — but the ticket<br/>still has a group, so it's visible<br/>in that group's queue]
</pre></div>
<p class="text-muted mb-2"><strong>Recommended fallback:</strong> Set it to <strong>Load-Based</strong> if you want every ticket assigned to a human no matter what. Set it to <strong>None</strong> if you'd rather have ambiguous tickets sit unassigned for a human to triage.</p>
</div></div>

<h3 id="d8" class="fw-bold mt-5 mb-3">Diagram 8 — Default-group fallback (the "no ticket gets stuck" safety net)</h3>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<p class="text-muted mb-2">A separate, earlier safety net than Diagram 7. Diagram 7 handles "no eligible <em>agent</em> in this group" — Diagram 8 handles "no <em>group</em> at all." Configured at <a href="/admin/settings#default_group_id"><strong>Admin → Settings → Ticket Routing Defaults → Default Group</strong></a>.</p>
<div class="flow-diagram"><pre class="mermaid">
flowchart TD
    A[Ticket creation path begins] --> B{Did the caller<br/>specify a group?}
    B -->|Yes| Z[Use that group]
    B -->|No| C{Does the ticket type<br/>have a default group?}
    C -->|Yes| Z
    C -->|No| D{Is default_group_id<br/>setting configured?}
    D -->|Yes — and group exists| Z
    D -->|No or stale| E{Are there ANY<br/>groups defined?}
    E -->|Yes| F[Use the lowest-id<br/>existing group]
    E -->|No| G["Group stays NULL<br/>(only possible on a<br/>pristine fresh install)"]
    F --> Z
    Z[Ticket gets a group] --> H[Continue to Diagram 0]
    G --> I["Backfill happens later in<br/>runPostTicketCreateHooks<br/>and the hourly cron sweep"]
</pre></div>
<div class="alert alert-warning small mb-2"><i class="bi bi-exclamation-triangle me-2"></i>
The default group is genuinely <strong>last-resort</strong>. Don't use it as a primary routing target — point your ticket types at the right groups so most tickets never need the fallback.
</div>
<p class="text-muted mb-0">When this fallback fires it leaves a timeline entry on the ticket reading "No group was matched by ticket type, AI, or automations — routed to the system default group" so triage can spot the unrouted arrivals quickly.</p>
</div></div>

<h3 id="compare" class="fw-bold mt-5 mb-3">Side-by-side comparison</h3>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<h6 class="fw-semibold mb-2">What each strategy considers</h6>
<div class="table-responsive mb-3">
<table class="table table-sm mb-0">
<thead class="table-light"><tr><th>Strategy</th><th>Filters by skill?</th><th>Balances load?</th><th>Checks who's online?</th><th>Source of "required skills"</th></tr></thead>
<tbody class="text-muted">
<tr><td>Manual</td><td>—</td><td>—</td><td>—</td><td>—</td></tr>
<tr><td>Round Robin</td><td>No</td><td>No</td><td>No</td><td>—</td></tr>
<tr><td>Load-Based</td><td>No</td><td>Yes</td><td>No</td><td>—</td></tr>
<tr><td>Skill-Based</td><td>Yes</td><td>Yes (within eligible)</td><td>No</td><td>Required-skills checkboxes on the ticket type</td></tr>
<tr><td>AI Skill-Based</td><td>Yes</td><td>Yes (within eligible)</td><td>No</td><td>AI inference + admin override</td></tr>
<tr><td>First Available</td><td>No</td><td>Yes (within online)</td><td>Yes (last 2 minutes)</td><td>—</td></tr>
</tbody>
</table>
</div>

<h6 class="fw-semibold mb-2">Same Jane Doe "Printer Issue" ticket under each strategy</h6>
<div class="table-responsive mb-0">
<table class="table table-sm mb-0">
<thead class="table-light"><tr><th>Strategy</th><th>Picked agent</th><th>Why</th></tr></thead>
<tbody class="text-muted">
<tr><td>Manual</td><td>(nobody)</td><td>Sits in the queue</td></tr>
<tr><td>Round Robin</td><td>Carol</td><td>Next in line after Bob</td></tr>
<tr><td>Load-Based</td><td>Bob</td><td>Only 1 open ticket</td></tr>
<tr><td>Skill-Based</td><td>Bob</td><td>Has Printers AND lower load than Alice</td></tr>
<tr><td>AI Skill-Based</td><td>Bob</td><td>AI infers Printers, same eligible set as Skill-Based</td></tr>
<tr><td>First Available</td><td>Alice</td><td>Online, lower load than the other online agent (Carol)</td></tr>
</tbody>
</table>
</div>
</div></div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script>
  if (window.mermaid) {
    mermaid.initialize({ startOnLoad: true, theme: 'default', flowchart: { curve: 'basis', padding: 16 } });
  }
</script>

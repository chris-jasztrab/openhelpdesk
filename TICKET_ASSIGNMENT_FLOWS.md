# Ticket Assignment Workflows

Reference diagrams for every auto-assignment rule supported by the helpdesk.

## The cast used in every example

One IT group, four agents. The same data appears in every diagram so you can compare strategies side-by-side.

**Group:** IT — last round-robin pick was Bob.

| Agent | Skills                | Open tickets | Online right now? |
|-------|-----------------------|--------------|-------------------|
| Alice | Printers, Networking  | 3            | Yes — active 30 seconds ago     |
| Bob   | Printers, Servers     | 1            | No — last seen 10 minutes ago   |
| Carol | Servers, Telephony    | 5            | Yes — active 60 seconds ago     |
| Dave  | Networking, Telephony | 2            | No — never seen                 |

**Sample ticket:** Jane Doe submits via the portal:
- **Subject:** "Front desk printer is jamming on every print job"
- **Description:** "When I try to print to the front desk printer it jams every time."
- **Ticket type:** "Printer Issue" — its default group is IT, and it requires the Printers skill.

For the AI Skill-Based diagram, the sample ticket type is the broader "IT Issue" (no required skills) and the AI infers Printers from the content.

---

## Diagram 0 — The master flow

Every newly created ticket goes through these steps before any auto-assignment rule runs. Notice the **three layers of defence** against tickets ending up in the "no group" queue: `resolveTicketGroup()` at INSERT time, `backfillTicketGroupFromDefault()` after AI/automations run, and the hourly cron sweep in `scripts/process-stale-tickets.php`.

```mermaid
flowchart TD
    A[Someone submits a ticket] --> B["resolveTicketGroup()<br/>caller's pick → type's group →<br/>default_group_id setting →<br/>lowest-id existing group"]
    B --> C["INSERT INTO tickets<br/>(group_id rarely NULL after this)"]
    C --> D[runPostTicketCreateHooks]
    D --> E["classifyTicketWithAI<br/>(skipped for confidential ticket types<br/>or if AI is turned off)"]
    E --> F["runAutomations 'ticket_created'<br/>may overwrite group_id, type_id,<br/>or assigned_to"]
    F --> G["backfillTicketGroupFromDefault<br/>last-ditch NULL-group sweep<br/>routes to default_group_id"]
    G --> H{Has anything<br/>already assigned<br/>an agent?}
    H -->|Yes — automation took care of it| ZA[Done]
    H -->|No| I{Did the ticket<br/>land in a group?}
    I -->|No — only on a fresh install<br/>with zero groups defined| ZB["Sits in 'no group' queue<br/>until cron sweep or admin triage"]
    I -->|Yes| J["autoAssignTicket reads<br/>the group's assignment rule"]
    J --> K{Which rule?}
    K -->|Manual| D1[See Diagram 1]
    K -->|Round Robin| D2[See Diagram 2]
    K -->|Load-Based| D3[See Diagram 3]
    K -->|Skill-Based| D4[See Diagram 4]
    K -->|AI Skill-Based| D5[See Diagram 5]
    K -->|First Available| D6[See Diagram 6]
```

**Things to notice:**
- Auto-assignment only happens when a group was matched AND no agent has been assigned yet.
- Automations run *before* auto-assign, so a "set group" automation can redirect the ticket and a "set assignee" automation can short-circuit auto-assign entirely.
- Every rule except Manual can fall back to a backup rule if it can't find anyone — see [Diagram 7](#diagram-7--fallback-flow-when-the-primary-rule-cant-pick-anyone).
- A ticket reaching the "no group queue" outcome is now genuinely rare — only possible on a pristine install that has zero groups defined. See [Diagram 8](#diagram-8--default-group-fallback-the-no-ticket-gets-stuck-safety-net) for the full safety-net flow.

---

## Diagram 1 — Manual (the default)

The system creates the ticket, parks it in the group's queue, and waits for a human to grab it.

```mermaid
flowchart TD
    A["Jane submits 'Printer Issue'<br/>Ticket type's default group: IT"] --> B["Ticket created in IT group<br/>No agent assigned yet"]
    B --> C["Group's assignment rule:<br/>Manual"]
    C --> D[System doesn't pick anyone]
    D --> E[Ticket waits in the IT queue]
    E --> F["An IT agent opens the queue<br/>and clicks 'assign to me'<br/>(or an admin assigns it)"]
```

**Outcome:** Whichever IT agent picks it up first owns it.
**When to use:** Small teams who self-triage, or when you want a human in the loop on every ticket.

---

## Diagram 2 — Round Robin

Cycles through the group's members in a fixed order. Ignores skill, current load, and online status — it just rotates.

```mermaid
flowchart TD
    A["Jane submits 'Printer Issue'<br/>Ticket lands in IT group"] --> B["Group's rule: Round Robin"]
    B --> C["IT group members in order:<br/>Alice → Bob → Carol → Dave"]
    C --> D["Bob got the previous<br/>round-robin pick"]
    D --> E["Pick the next person after Bob<br/>→ Carol"]
    E --> F["Ticket assigned to Carol<br/>System remembers Carol got<br/>the latest pick"]
    F --> G[Carol gets the ticket]
    G -.- H["Next ticket → Dave<br/>Then → Alice (wraps around)<br/>Then → Bob"]
```

**Outcome:** Carol. Bob is least-loaded and has the right skill, but round-robin doesn't care.
**Edge case:** if the group has never had a round-robin assignment before, the first member of the group is picked.

---

## Diagram 3 — Load-Based

Picks whichever group member has the fewest currently-open tickets.

```mermaid
flowchart TD
    A["Jane submits 'Printer Issue'<br/>Ticket lands in IT group"] --> B["Group's rule: Load-Based"]
    B --> C[Look at IT group members]
    C --> D["Count each agent's<br/>currently-open tickets<br/>(ignoring resolved and closed)"]
    D --> E["Open ticket counts:<br/>Alice = 3, Bob = 1<br/>Carol = 5, Dave = 2<br/>Bob has the fewest"]
    E --> F[Ticket assigned to Bob]
    F -.- G["Tie-break: if two agents are tied,<br/>the one whose account was<br/>created first wins"]
```

**Outcome:** Bob. Doesn't check whether he's online or has the right skill — only load matters.
**When to use:** Generalist teams where everyone can handle anything; you mostly care about even queue lengths.

---

## Diagram 4 — Skill-Based (uses the ticket type's required skills)

Filters group members down to those whose skills cover **every** skill the ticket type requires, then picks the least-loaded one of those.

```mermaid
flowchart TD
    A["Jane submits 'Printer Issue'<br/>Ticket lands in IT group"] --> B["Group's rule: Skill-Based"]
    B --> C[Look at IT group members]
    C --> D["Read the required skills<br/>checked on this ticket type:<br/>Printers"]
    D --> E{Any required<br/>skills checked?}
    E -->|No — none checked| FB["Use the group's<br/>fallback rule<br/>(see Diagram 7)"]
    E -->|Yes| F["Keep only members who hold<br/>ALL the required skills"]
    F --> G["Eligible agents:<br/>Alice (has Printers) ✓<br/>Bob (has Printers) ✓<br/>Carol (no Printers) ✗<br/>Dave (no Printers) ✗"]
    G --> H{Anyone<br/>eligible?}
    H -->|No| FB
    H -->|Yes| I["Among eligible, pick least loaded:<br/>Alice = 3 open, Bob = 1 open<br/>→ Bob wins"]
    I --> J[Ticket assigned to Bob]
```

**Outcome:** Bob — has Printers AND fewer open tickets than Alice.
**Edge case:** If you forget to check any required skills on the ticket type, the rule can't pick anyone and the fallback runs. Don't leave it empty unless you mean to.

---

## Diagram 5 — AI Skill-Based

Same shape as Diagram 4, but the "required skills" come from an AI reading the ticket's content instead of checkboxes on the ticket type.

```mermaid
flowchart TD
    A["Jane submits ticket<br/>Subject: 'Front desk printer<br/>keeps jamming'<br/>Ticket type: 'IT Issue'<br/>(general, no required skills)"] --> B[Ticket created in IT group]
    B --> C[Post-creation steps run]
    C --> D{Is the ticket type<br/>confidential?<br/>Or AI turned off?}
    D -->|Yes| FB["Skip AI →<br/>use fallback rule<br/>(Diagram 7)"]
    D -->|No| E["AI's candidate list:<br/>global skills + skills<br/>scoped to the IT group"]
    E --> F["AI reads the subject + description<br/>and picks the relevant skills<br/>from the candidate list"]
    F --> G["AI's verdict:<br/>Suggested skill: Printers<br/>Confidence: 92%"]
    G --> H["Group's rule:<br/>AI Skill-Based"]
    H --> I{Is the AI<br/>confident enough?<br/>default 70%}
    I -->|No| FB
    I -->|Yes| J["Use the admin's manual override<br/>if one is set,<br/>otherwise use the AI's suggestion"]
    J --> K["Keep only members who hold<br/>ALL the suggested skills"]
    K --> L["Eligible: Alice ✓, Bob ✓"]
    L --> M{Anyone<br/>eligible?}
    M -->|No| FB
    M -->|Yes| N["Among eligible, pick least loaded:<br/>Alice = 3, Bob = 1 → Bob"]
    N --> O[Ticket assigned to Bob]
```

**Outcome:** Bob — same result as plain Skill-Based, but you didn't have to maintain a "Printer Issue" ticket type with required skills checked. One generic "IT Issue" type is enough.

**Edge cases:**
- If the AI provider is unreachable or returns garbage, no skills get tagged and the fallback runs.
- If the AI isn't confident enough (under 70%), the fallback runs. Vague tickets like "computer broken" often miss the threshold.
- If a skill is scoped to a *different* group (e.g. Telephony scoped to Facilities), the AI won't see it for an IT ticket — it can only suggest from global skills and IT-scoped skills.
- Admins can override the AI's suggestion; the override wins.

---

## Diagram 6 — First Available

Filters group members to those currently online, then picks the least-loaded one.

```mermaid
flowchart TD
    A["Jane submits 'Printer Issue'<br/>Ticket lands in IT group"] --> B["Group's rule: First Available"]
    B --> C[Look at IT group members]
    C --> D[Check who has been<br/>active in the last 2 minutes]
    D --> E["Online right now:<br/>Alice (active 30s ago) ✓<br/>Bob (active 10 min ago) ✗<br/>Carol (active 60s ago) ✓<br/>Dave (never seen) ✗"]
    E --> F{Anyone<br/>online?}
    F -->|No| FB["Use the group's<br/>fallback rule<br/>(Diagram 7)"]
    F -->|Yes| G["Among those online, pick least loaded:<br/>Alice = 3, Carol = 5 → Alice wins"]
    G --> H[Ticket assigned to Alice]
```

**Outcome:** Alice. Bob would have won on load alone but he's offline. Skills aren't checked at all.
**Why 2 minutes:** browsers slow down background tabs to roughly once-a-minute updates, so a 60-second window would mistakenly mark backgrounded agents as offline.

---

## Diagram 7 — Fallback flow (when the primary rule can't pick anyone)

Every rule except Manual can come up empty (no eligible specialist, no one online, AI not confident enough, etc.). When that happens, the system uses the group's backup rule on the **full group membership** — no filtering.

```mermaid
flowchart TD
    A["Primary rule didn't pick anyone<br/>(no eligible specialist,<br/>no one online,<br/>AI not confident enough, etc.)"] --> B["Check the group's<br/>fallback rule"]
    B --> C{Fallback rule?}
    C -->|Round Robin| D["Run Round Robin<br/>across the full group<br/>(Diagram 2 logic)"]
    C -->|Load-Based| E["Run Load-Based<br/>across the full group<br/>(Diagram 3 logic)"]
    C -->|None| F["Don't assign anyone<br/>Ticket waits for<br/>human triage"]
    D --> G[Agent picked → assigned]
    E --> G
    F --> H[No assignment]
```

**Common reasons each rule falls through to the fallback:**

| Rule              | Why it might fall through                                                                    |
|-------------------|----------------------------------------------------------------------------------------------|
| Skill-Based       | Ticket type has no required skills checked, OR no member has all of them                     |
| AI Skill-Based    | AI turned off, type confidential, AI failed, AI not confident enough, no member matched      |
| First Available   | Nobody in the group has been active in the last 2 minutes                                    |
| Round Robin       | Almost never — picks the first member if the previous-pick pointer is missing                |
| Load-Based        | Almost never — only fires if the group has zero members                                      |

**Fallback recommendation:**
- Set the fallback to **Load-Based** if you want every ticket assigned to a human no matter what.
- Set it to **None** if you'd rather have ambiguous tickets sit unassigned for a human to triage.

---

## Diagram 8 — Default-group fallback (the "no ticket gets stuck" safety net)

A separate, **earlier** safety net than Diagram 7. Diagram 7 handles "no eligible *agent* in this group" — Diagram 8 handles "no *group* at all." Configured at **Admin → Settings → Ticket Routing Defaults → Default Group**, this kicks in at three layers:

1. **At creation** — every ticket creation path calls `resolveTicketGroup()`, which chains caller's pick → ticket type's default group → `default_group_id` setting → lowest-id existing group.
2. **After post-create hooks** — `backfillTicketGroupFromDefault()` runs after AI and automations and routes any ticket still sitting with a NULL group to the default.
3. **Hourly cron** — `scripts/process-stale-tickets.php` sweeps any orphans the previous two layers somehow missed (legacy data, hand-edited rows, future creation paths a developer forgot to plumb).

```mermaid
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
```

**When to use:** Always. The setting is part of the routing core — leaving it unset is supported but inadvisable, since it's the difference between an inbound email landing in a triaged queue versus sitting invisible in the no-group filter view. Best practice: pick (or create) a generic *Triage* / *Service Desk* group, set it as the system default, and configure that group's auto-assign strategy so the catch-all queue is itself auto-distributed to a human.

**Timeline entry:** When this fallback fires it leaves an internal note on the ticket reading *"No group was matched by ticket type, AI, or automations — routed to the system default group so the ticket does not sit in the no-group queue."* That makes unrouted arrivals visible in normal triage workflow.

---

## Side-by-side comparison

**What each rule considers:**

| Rule              | Filters by skill?         | Balances load?            | Checks who's online?  | Source of "required skills"                                |
|-------------------|---------------------------|---------------------------|-----------------------|------------------------------------------------------------|
| Manual            | —                         | —                         | —                     | —                                                          |
| Round Robin       | No                        | No                        | No                    | —                                                          |
| Load-Based        | No                        | Yes                       | No                    | —                                                          |
| Skill-Based       | Yes                       | Yes (within eligible)     | No                    | Required-skills checkboxes on the ticket type              |
| AI Skill-Based    | Yes                       | Yes (within eligible)     | No                    | AI inference from subject + description; admin can override |
| First Available   | No                        | Yes (within online)       | Yes (last 2 minutes)  | —                                                          |

**The same Jane Doe "Printer Issue" ticket under each rule:**

| Rule              | Picked agent | Why                                                              |
|-------------------|--------------|------------------------------------------------------------------|
| Manual            | (nobody)     | Sits in the queue                                                |
| Round Robin       | Carol        | Next in line after Bob                                           |
| Load-Based        | Bob          | Only 1 open ticket                                               |
| Skill-Based       | Bob          | Has Printers AND lower load than Alice                           |
| AI Skill-Based    | Bob          | AI infers Printers, same eligible set as Skill-Based             |
| First Available   | Alice        | Online, lower load than the other online agent (Carol)           |

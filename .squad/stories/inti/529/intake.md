> **Fetched from azure:** [529](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/529)  
> *Fetched 2026-09-05T06:32:52.067Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 10.1 Escalation, manual and on breach  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a supervisor, I want a ticket that is going wrong to be marked as such and to reach me, so that attention arrives before the customer has to ask for it. 

Context 

Epic 10 — Automation and Agent Tools. Three small mechanisms that a support team asks for on day two and that are usually built as engines. They are not engines here. Escalation is one property, one manual action and one built-in condition. Assignment is a lookup table. Tasks, reminders and mentions are personal work that never touches a ticket's status and never reaches a customer. Each is the least mechanism that makes its scenario work, and each is deliberately hard to grow into a workflow product. 

Technical Constraints 

Columns on tickets: escalated_at, escalated_by, escalation_reason — nullable, and no escalation_level. A level implies a ladder, a ladder implies rules, and there are none. 

EscalateTicket is a named Tickets command (AD-3). The SLA breach sweep calls it with a System actor; it does not update ticket rows directly. 

Escalation is one of AD-18's four bounded automations — auto-close, breach recording, breach escalation, assignment on creation. This story adds no fifth. 

Sla observes ticket events and dispatches downward through Tickets' command; it never calls up the tier ladder. 

Idempotency: escalate only where escalated_at IS NULL for the breach event being handled, so running the minutely sweep twice produces one escalation and one notification. 

The one-step priority raise is a separate named command with a System actor, exempt from the version check — the same exemption auto-close already has in Story 4.2, for the same reason: a sweep has no stale user view to guard. 

No escalation_rules table. If one appears, FR-055–FR-057 have been re-derived and they are removed. 

 UX / Interaction Requirements 

<b>UX-14</b> — No ticket-history entry can be edited or deleted through any UI path, by any role. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/direction-e2-workspace.html — the Escalate action and the escalated marker on the ticket. mockups/direction-e2-list.html — the escalated marker in the row. mockups/screen-home.html — "Escalated to me" in the counts strip and the attention queue. 

Not in this version, though visible there: the escalation-rule table in screen-admin.html, ordered and individually enabled, with its condition/action editor · escalation levels ("Level 2" in screen-portal.html) · an Escalated entry in the status vocabulary · the rule execution log. 

Dependencies 

Blocked by: 4.2 (the lifecycle and the command path), 5.3 (the breach event that triggers it), 5.4 (the notification path) 

Traceability 

Story ID: 10.1
Epic: Epic 10: Automation and Agent Tools
Covers: FR-026, FR-027, FR-060 · BR-17, BR-19 · AD-3, AD-18, AD-23 · UX-14 

Delivery 

Sprint 8 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/529/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `529` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
10.1 Escalation, manual and on breach
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a supervisor, I want a ticket that is going wrong to be marked as such and to reach me, so that attention arrives before the customer has to ask for it. 

Context 

Epic 10 — Automation and Agent Tools. Three small mechanisms that a support team asks for on day two and that are usually built as engines. They are not engines here. Escalation is one property, one manual action and one built-in condition. Assignment is a lookup table. Tasks, reminders and mentions are personal work that never touches a ticket's status and never reaches a customer. Each is the least mechanism that makes its scenario work, and each is deliberately hard to grow into a workflow product. 

Technical Constraints 

Columns on tickets: escalated_at, escalated_by, escalation_reason — nullable, and no escalation_level. A level implies a ladder, a ladder implies rules, and there are none. 

EscalateTicket is a named Tickets command (AD-3). The SLA breach sweep calls it with a System actor; it does not update ticket rows directly. 

Escalation is one of AD-18's four bounded automations — auto-close, breach recording, breach escalation, assignment on creation. This story adds no fifth. 

Sla observes ticket events and dispatches downward through Tickets' command; it never calls up the tier ladder. 

Idempotency: escalate only where escalated_at IS NULL for the breach event being handled, so running the minutely sweep twice produces one escalation and one notification. 

The one-step priority raise is a separate named command with a System actor, exempt from the version check — the same exemption auto-close already has in Story 4.2, for the same reason: a sweep has no stale user view to guard. 

No escalation_rules table. If one appears, FR-055–FR-057 have been re-derived and they are removed. 

 UX / Interaction Requirements 

<b>UX-14</b> — No ticket-history entry can be edited or deleted through any UI path, by any role. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/direction-e2-workspace.html — the Escalate action and the escalated marker on the ticket. mockups/direction-e2-list.html — the escalated marker in the row. mockups/screen-home.html — "Escalated to me" in the counts strip and the attention queue. 

Not in this version, though visible there: the escalation-rule table in screen-admin.html, ordered and individually enabled, with its condition/action editor · escalation levels ("Level 2" in screen-portal.html) · an Escalated entry in the status vocabulary · the rule execution log. 

Dependencies 

Blocked by: 4.2 (the lifecycle and the command path), 5.3 (the breach event that triggers it), 5.4 (the notification path) 

Traceability 

Story ID: 10.1
Epic: Epic 10: Automation and Agent Tools
Covers: FR-026, FR-027, FR-060 · BR-17, BR-19 · AD-3, AD-18, AD-23 · UX-14 

Delivery 

Sprint 8 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
An authorised user escalates a ticket, recording who escalated it and why. The reason is required, not optional. 

Escalation is a property, not a status. An escalated ticket keeps the lifecycle status it had. There is no Escalated state, the lifecycle is still exactly Open · Pending · Resolved · Closed, and no filter treats escalation as a status. 

Escalated state is visible to staff on the ticket and in every ticket list, and is a filter on the list. 

It is never visible on any customer surface — not the portal, not an email, not a chat transcript. Verified by calling the portal API directly. 

Escalating carries no version and changes none. Two people escalating the same ticket both succeed, because they have not conflicted — they agree. It writes escalated_at, escalated_by and the reason, and appends its history row. 

Exactly one automatic condition exists: an SLA breach escalates the ticket, notifies the department's supervisors, and raises priority one step where a setting enables it. A ticket already at Urgent is left at Urgent, with no error. 

Automatic escalation is attributed to System(sla_breach) and appears in history exactly like a human action, through the same named command a human uses. 

A ticket already escalated that breaches a second target is not escalated twice; the sweep is idempotent and writes one escalation. 

There is no rule editor, no condition builder, no action list, no rule ordering, no per-rule enable/disable and no execution log. One condition, two fixed actions. 

The assignee is alerted on at-risk and on breach; the department's supervisors on breach and on escalation — in-app and by email, in each recipient's own language, through Story 5.4's path. 

The escalation notification names the ticket, who escalated it, and the reason — enough to act without opening the ticket first. 

Escalation is recorded in ticket history with actor and timestamp like every other event, and the history entry is immutable.
```

---

## Attachments

Place files in `attachments/` next to this `intake.md`, then list them here so the planner knows what to open.

| File (relative to this folder) | What it is |
| ------------------------------ | ---------- |
| *(e.g. `attachments/flow.png`)* | *(e.g. UX flow)* |

*(Add rows per file. If none, write "None.")*

---

## Dependencies

- **Blocked by / related ids:** (tracker ids only; optional short note)
- **Depends on code areas or other stories:**

## Extra notes (optional)

- Anything not captured above (e.g. chat context) — keep short.

## Technical hints (optional)

- APIs, screens, services already discussed. Repos/roots: `.`. Primary language: `php + Next`.

## Out of scope

- What this story explicitly does **not** cover:

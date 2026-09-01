> **Fetched from azure:** [503](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/503)  
> *Fetched 2026-09-01T08:09:13.797Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 4.1 The ticket write path — creation, the unique reference, category, priority and the version guard  
**Type:** User Story  
**Status:** New

### Description

User Story 

As the system, I want exactly one way a ticket is ever written, so that no change can happen without a history row and no ticket can exist without an owner and an identity. 

Context 

Epic 4 — Ticket Core & Agent Workspace. Every request becomes a ticket with one identity, one write path, one immutable history and a lifecycle that cannot be violated — categorised, prioritised, assigned, conversed on, listed and found, with the customer's context beside it and the day's work on one screen. 

The heart of the product. At the end of this epic a support team can work a full day in the system. 

Technical Constraints 

Reference allocation must be collision-free under concurrency: a dedicated PostgreSQL sequence, formatted at read time. Not MAX(id)+1, which races. 

The named-command pattern is the load-bearing constraint of this epic: CreateTicket, AssignTicket, ChangeStatus, ResolveTicket, ReopenTicket. One command, one public method, one transaction. A ticket write without its ticket_events row must be unreachable by construction, not by convention — put the event append inside the same transaction as the write, in the command itself. 

The Actor value object is a small class with three subtypes. Passing it explicitly, rather than reading ambient auth, is what lets the queue worker (Story 4.2 auto-close, Story 5.2 inbound email) write tickets correctly with no session. 

tickets.status and tickets.priority are native PHP backed enums, string-backed. 

tickets.version is a plain monotonic integer, incremented exactly once per command inside the same transaction as the write. Building it here, in the story that establishes the command path, is deliberate: every later command inherits it automatically rather than being retrofitted one story later. 

The form is an explicit version field in the request body — not ETag + If-Match. ETag was considered and rejected on three grounds: it conditions on the whole representation rather than on the five contended properties, so an appended message would spuriously invalidate an open edit form; it needs per-representation ETag generation; and a conditional header can be stripped or rewritten by any proxy, whereas a body field appears in the OpenAPI schema and reaches the generated TypeScript client identically. 

Condition on what changes, not on which endpoint is called. Put the check in the command, keyed on whether a contended property is in the change set — not in a route middleware, which cannot tell an append from an edit. 

Return the current version and the current values of the five properties in the 409 body. Without them the client must re-fetch, and the message becomes two round trips instead of one. 

The frontend handles ticket.stale_version in one shared place — the generated client's error handler — so every edit surface behaves identically. Do not write a per-screen handler. 

 Scope guard> Scope guardUX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the escalation control · the link and merge controls · the organisation picker · the branch field. (The reference version's full stale-version conflict dialog is not built — the refusal is one sentence and a Reload.) 

Design References 

mockups/direction-e2-workspace.html — the New Ticket surface and field grouping. 

Mockups in Azure DevOps (no Jira needed): 

direction-e2-workspace.html — attached to Story 4.4 (work item #506). 

 Dependencies 

3.1 — a ticket needs a customer. 

Blocked by: Story 3.1 

Blocks: Story 4.2, Story 3.3 

Traceability 

Story ID: 4.1 · Epic: 4 · Covers: FR-013 – FR-016, FR-018, FR-153 · BR-1, BR-3, BR-17 · AD-3, AD-10, AD-23 · UX-17 

Delivery 

Sprint 3 · Priority 1

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/503/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `503` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
4.1 The ticket write path — creation, the unique reference, category, priority and the version guard
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As the system, I want exactly one way a ticket is ever written, so that no change can happen without a history row and no ticket can exist without an owner and an identity. 

Context 

Epic 4 — Ticket Core & Agent Workspace. Every request becomes a ticket with one identity, one write path, one immutable history and a lifecycle that cannot be violated — categorised, prioritised, assigned, conversed on, listed and found, with the customer's context beside it and the day's work on one screen. 

The heart of the product. At the end of this epic a support team can work a full day in the system. 

Technical Constraints 

Reference allocation must be collision-free under concurrency: a dedicated PostgreSQL sequence, formatted at read time. Not MAX(id)+1, which races. 

The named-command pattern is the load-bearing constraint of this epic: CreateTicket, AssignTicket, ChangeStatus, ResolveTicket, ReopenTicket. One command, one public method, one transaction. A ticket write without its ticket_events row must be unreachable by construction, not by convention — put the event append inside the same transaction as the write, in the command itself. 

The Actor value object is a small class with three subtypes. Passing it explicitly, rather than reading ambient auth, is what lets the queue worker (Story 4.2 auto-close, Story 5.2 inbound email) write tickets correctly with no session. 

tickets.status and tickets.priority are native PHP backed enums, string-backed. 

tickets.version is a plain monotonic integer, incremented exactly once per command inside the same transaction as the write. Building it here, in the story that establishes the command path, is deliberate: every later command inherits it automatically rather than being retrofitted one story later. 

The form is an explicit version field in the request body — not ETag + If-Match. ETag was considered and rejected on three grounds: it conditions on the whole representation rather than on the five contended properties, so an appended message would spuriously invalidate an open edit form; it needs per-representation ETag generation; and a conditional header can be stripped or rewritten by any proxy, whereas a body field appears in the OpenAPI schema and reaches the generated TypeScript client identically. 

Condition on what changes, not on which endpoint is called. Put the check in the command, keyed on whether a contended property is in the change set — not in a route middleware, which cannot tell an append from an edit. 

Return the current version and the current values of the five properties in the 409 body. Without them the client must re-fetch, and the message becomes two round trips instead of one. 

The frontend handles ticket.stale_version in one shared place — the generated client's error handler — so every edit surface behaves identically. Do not write a per-screen handler. 

 Scope guard> Scope guardUX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the escalation control · the link and merge controls · the organisation picker · the branch field. (The reference version's full stale-version conflict dialog is not built — the refusal is one sentence and a Reload.) 

Design References 

mockups/direction-e2-workspace.html — the New Ticket surface and field grouping. 

Mockups in Azure DevOps (no Jira needed): 

direction-e2-workspace.html — attached to Story 4.4 (work item #506). 

 Dependencies 

3.1 — a ticket needs a customer. 

Blocked by: Story 3.1 

Blocks: Story 4.2, Story 3.3 

Traceability 

Story ID: 4.1 · Epic: 4 · Covers: FR-013 – FR-016, FR-018, FR-153 · BR-1, BR-3, BR-17 · AD-3, AD-10, AD-23 · UX-17 

Delivery 

Sprint 3 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
A ticket is created from agent entry and from portal submission. (The email path is Story 5.2 and reuses this same write path.) 

Every ticket carries: unique human-readable reference, subject, description, customer, channel of origin, category, priority, status, department, creator, assignee, created and updated timestamps. 

The reference is unique, sequential and human-readable — TKT-000123 — allocated once at creation, never changed and never reused, including after closure. 

A ticket always belongs to exactly one customer. It is never created without one. 

Category is set from the flat administrator-managed list and can be changed by an authorised user. 

Priority is set from the fixed set Low · Normal · High · Urgent and can be changed by an authorised user. 

No code outside the Tickets module mutates a ticket, message or assignment row. Every mutation is a named command that, in one database transaction, validates the change, applies it, and appends the history row. 

Every command takes an explicit actor — StaffUser, PortalAccount or System(reason). The domain never reads auth() or request(). 

A repeated create with the same Idempotency-Key returns the original response and does not create a second ticket. 

Every ticket carries a version, starting at 1, and every read returns it. 

A request that changes status, priority, category, assignee or department carries the version the user last saw. The command compares it inside the same transaction that would apply the change. 

A stale version is refused with 409 and the problem type ticket.stale_version. Nothing is applied, partially applied or queued. The response carries the current version and the current values of those five properties, so the client can render the ticket fresh without a second round trip. 

Appending a message, an internal note or an attachment carries no version and changes none. Two people replying to the same ticket at the same moment both succeed, and neither is refused. 

A composite request that appends and changes a contended property is treated as a change: it carries a version and is subject to the check. 

The client renders the refusal as one plain sentence — "this ticket was changed by someone else" — and a Reload action. Nothing more.
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

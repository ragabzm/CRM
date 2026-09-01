> **Fetched from azure:** [504](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/504)  
> *Fetched 2026-09-01T08:09:19.229Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 4.2 Ticket state — assignment, lifecycle, resolve, auto-close and reopen  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an agent and a supervisor, I want a ticket to move between people and through states under rules the server enforces, so that the lifecycle is real and not a convention the UI happens to follow. 

Context 

Epic 4 — Ticket Core & Agent Workspace. Every request becomes a ticket with one identity, one write path, one immutable history and a lifecycle that cannot be violated — categorised, prioritised, assigned, conversed on, listed and found, with the customer's context beside it and the day's work on one screen. 

The heart of the product. At the end of this epic a support team can work a full day in the system. 

Technical Constraints 

The permitted-transition table is data or a match expression in one place, checked inside the ChangeStatus command. Not scattered across controllers, and not enforced only by which buttons are rendered. 

Auto-close is a scheduled job running through the same ResolveTicket/ChangeStatus commands a human uses — never a direct UPDATE. That is what keeps requirement 10 true. 

The sweep must be idempotent and safe to run twice: it selects on status = resolved AND resolved_at < now() - window AND last_customer_activity_at < now() - window. 

Auto-close and reopen windows are Story 2.3 settings, read at sweep time. 

There is no escalation level, no escalated_by, no escalation reason column, and no rule engine. Escalating is raising priority and reassigning — both already here. 

There is no automatic assignment. Unassigned is a valid, visible, workable state. 

Department change is a plain field update through a named command so it lands in history. 

Assignment, status, priority, category and department are the five contended properties (Story 4.1). Every one of these commands carries a version and is subject to the stale check; the UI sends the version it loaded and handles ticket.stale_version through the shared handler. Auto-close runs as System and is exempt — a sweep has no stale user view to guard. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the Escalate action · the six-state SLA vocabulary in the status area (three states only) · the auto-assignment indicator. 

Design References 

mockups/direction-e2-workspace.html — the status control and the property rail. 

Mockups in Azure DevOps (no Jira needed): 

direction-e2-workspace.html — attached to Story 4.4 (work item #506). 

 Dependencies 

4.1 

Blocked by: Story 4.1 

Blocks: Story 4.3 

Traceability 

Story ID: 4.2 · Epic: 4 · Covers: FR-019, FR-020, FR-021, FR-024, FR-025, FR-143 · BR-7, BR-11, BR-17 · AD-3, AD-18, AD-23 

Delivery 

Sprint 3 · Priority 1

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/504/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `504` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
4.2 Ticket state — assignment, lifecycle, resolve, auto-close and reopen
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an agent and a supervisor, I want a ticket to move between people and through states under rules the server enforces, so that the lifecycle is real and not a convention the UI happens to follow. 

Context 

Epic 4 — Ticket Core & Agent Workspace. Every request becomes a ticket with one identity, one write path, one immutable history and a lifecycle that cannot be violated — categorised, prioritised, assigned, conversed on, listed and found, with the customer's context beside it and the day's work on one screen. 

The heart of the product. At the end of this epic a support team can work a full day in the system. 

Technical Constraints 

The permitted-transition table is data or a match expression in one place, checked inside the ChangeStatus command. Not scattered across controllers, and not enforced only by which buttons are rendered. 

Auto-close is a scheduled job running through the same ResolveTicket/ChangeStatus commands a human uses — never a direct UPDATE. That is what keeps requirement 10 true. 

The sweep must be idempotent and safe to run twice: it selects on status = resolved AND resolved_at < now() - window AND last_customer_activity_at < now() - window. 

Auto-close and reopen windows are Story 2.3 settings, read at sweep time. 

There is no escalation level, no escalated_by, no escalation reason column, and no rule engine. Escalating is raising priority and reassigning — both already here. 

There is no automatic assignment. Unassigned is a valid, visible, workable state. 

Department change is a plain field update through a named command so it lands in history. 

Assignment, status, priority, category and department are the five contended properties (Story 4.1). Every one of these commands carries a version and is subject to the stale check; the UI sends the version it loaded and handles ticket.stale_version through the shared handler. Auto-close runs as System and is exempt — a sweep has no stale user view to guard. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the Escalate action · the six-state SLA vocabulary in the status area (three states only) · the auto-assignment indicator. 

Design References 

mockups/direction-e2-workspace.html — the status control and the property rail. 

Mockups in Azure DevOps (no Jira needed): 

direction-e2-workspace.html — attached to Story 4.4 (work item #506). 

 Dependencies 

4.1 

Blocked by: Story 4.1 

Blocks: Story 4.3 

Traceability 

Story ID: 4.2 · Epic: 4 · Covers: FR-019, FR-020, FR-021, FR-024, FR-025, FR-143 · BR-7, BR-11, BR-17 · AD-3, AD-18, AD-23 

Delivery 

Sprint 3 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
An authorised user assigns a ticket to an agent, reassigns it to a different agent, or leaves it unassigned. 

A Supervisor can reassign any ticket, overriding an existing assignment. An Agent cannot. 

The lifecycle is exactly Open → Pending → Resolved → Closed, plus Closed → Open on reopen. Permitted transitions are: Open⇄Pending · Open→Resolved · Pending→Resolved · Resolved→Closed · Closed→Open (in window) · Resolved→Open (on customer reply). 

Any transition not in that list is rejected by the server, regardless of what the client sent. A test calls the API directly to prove it. 

There is no New state and no Cancelled state. A ticket is born Open. 

An agent resolves a ticket. A scheduled sweep closes resolved tickets after the configurable window (default 72 hours) with no further customer activity in that window — any customer activity resets the clock. 

A closed ticket can be reopened within the configurable window (default 14 days), returning to Open with full history intact. 

Past the reopen window, the refusal offers a linked new request rather than a dead end. 

An authorised user moves a ticket between departments, and the move is recorded in history. 

Auto-close runs as System(auto_close) and appears in history exactly like a human action.
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

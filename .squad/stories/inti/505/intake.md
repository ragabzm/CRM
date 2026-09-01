> **Fetched from azure:** [505](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/505)  
> *Fetched 2026-09-01T08:09:24.420Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 4.3 Immutable ticket history  
**Type:** User Story  
**Status:** New

### Description

User Story 

As anyone who later has to explain what happened, I want a complete record of every change to a ticket that nobody can edit, so that the ticket is its own evidence. 

Context 

Epic 4 — Ticket Core & Agent Workspace. Every request becomes a ticket with one identity, one write path, one immutable history and a lifecycle that cannot be violated — categorised, prioritised, assigned, conversed on, listed and found, with the customer's context beside it and the day's work on one screen. 

The heart of the product. At the end of this epic a support team can work a full day in the system. 

Technical Constraints 

ticket_events owned by Tickets. Separate from audit_entries (Story 2.4) — one answers what happened to this ticket, the other what did this actor do. 

No updated_at, no soft deletes, no model events that could mutate a row. Revoke UPDATE and DELETE at the database-user level where the deployment allows it. 

The append happens inside the same transaction as the ticket write (Story 4.1). This is not an observer or a listener that could be skipped — it is part of the command. 

Before/after as a JSON column. Store the changed fields only, not the whole row. 

Paginate; a long-running ticket accumulates dozens of entries. 

 Design References 

mockups/direction-e2-workspace.html — the history panel. 

Mockups in Azure DevOps (no Jira needed): 

direction-e2-workspace.html — attached to Story 4.4 (work item #506). 

 Dependencies 

4.1, 4.2 — there must be events to record. 

Blocked by: Story 4.2 

Blocks: Story 4.4 

Traceability 

Story ID: 4.3 · Epic: 4 · Covers: FR-022, FR-023 · BR-4 · AD-3, AD-8 · UX-14 

Delivery 

Sprint 4 · Priority 1

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/505/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `505` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
4.3 Immutable ticket history
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As anyone who later has to explain what happened, I want a complete record of every change to a ticket that nobody can edit, so that the ticket is its own evidence. 

Context 

Epic 4 — Ticket Core & Agent Workspace. Every request becomes a ticket with one identity, one write path, one immutable history and a lifecycle that cannot be violated — categorised, prioritised, assigned, conversed on, listed and found, with the customer's context beside it and the day's work on one screen. 

The heart of the product. At the end of this epic a support team can work a full day in the system. 

Technical Constraints 

ticket_events owned by Tickets. Separate from audit_entries (Story 2.4) — one answers what happened to this ticket, the other what did this actor do. 

No updated_at, no soft deletes, no model events that could mutate a row. Revoke UPDATE and DELETE at the database-user level where the deployment allows it. 

The append happens inside the same transaction as the ticket write (Story 4.1). This is not an observer or a listener that could be skipped — it is part of the command. 

Before/after as a JSON column. Store the changed fields only, not the whole row. 

Paginate; a long-running ticket accumulates dozens of entries. 

 Design References 

mockups/direction-e2-workspace.html — the history panel. 

Mockups in Azure DevOps (no Jira needed): 

direction-e2-workspace.html — attached to Story 4.4 (work item #506). 

 Dependencies 

4.1, 4.2 — there must be events to record. 

Blocked by: Story 4.2 

Blocks: Story 4.4 

Traceability 

Story ID: 4.3 · Epic: 4 · Covers: FR-022, FR-023 · BR-4 · AD-3, AD-8 · UX-14 

Delivery 

Sprint 4 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
Every ticket event is recorded: creation · status change · assignment change · priority change · category change · department change · message sent · message received · note added · attachment added · SLA breach. 

Each entry carries actor, timestamp, and before/after values. 

The history is append-only. No surface and no role can update or delete an entry — verified by calling the API directly, not only through the UI (UX-14). 

A user views a ticket's complete history on the ticket. 

A System actor is shown as such, with the reason — an auto-close or a breach record is never attributed to a person. 

History renders in Arabic RTL with Gregorian dates and Western digits.
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

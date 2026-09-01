> **Fetched from azure:** [506](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/506)  
> *Fetched 2026-09-01T08:09:33.170Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 4.4 Ticket detail — the conversation, internal notes, attachments, quick replies and customer context  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an agent, I want everything I need to answer this customer on one screen, so that I never leave the ticket to do my job. 

Context 

Epic 4 — Ticket Core & Agent Workspace. Every request becomes a ticket with one identity, one write path, one immutable history and a lifecycle that cannot be violated — categorised, prioritised, assigned, conversed on, listed and found, with the customer's context beside it and the day's work on one screen. 

The heart of the product. At the end of this epic a support team can work a full day in the system. 

Technical Constraints 

Messages are owned by Tickets and written through the Story 4.1 command path, so every message lands in history. 

messages.delivery_state is a backed enum — queued, sent, failed. Story 5.1 drives it. 

The composer draft is cached in localStorage keyed by ticket id (Story 2.1) and cleared on send. 

Quick replies are read from the Story 2.3 settings collection. The picker inserts text into the composer and nothing more — no variable substitution, no server-side rendering, no send-on-select. 

The AiSuggestionPanel component does not exist. The reference design had a fourth conversation treatment for an AI draft; there are three. 

The context panel must not trigger an N+1 — the counts come from one aggregate query, not a query per ticket. 

The composer and the property rail behave differently under the version guard (Story 4.1), and this is intentional. Sending a reply, adding a note or attaching a file is an append: it carries no version and is never refused as stale. Editing a field in the property rail is a change: it carries the version the screen loaded, and a stale one is refused with a message and a Reload. An agent typing a long reply must never be interrupted because a supervisor reassigned the ticket. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the AI panel and the AI-draft treatment · the mention and follow controls · the tasks and reminders tab · the escalate, link and merge actions · the knowledge panel · the organisation chip. 

Design References 

mockups/direction-e2-workspace.html — the primary reference for this story: the property rail, the editable edge, the conversation model. 

Mockups in Azure DevOps (no Jira needed): 

direction-e2-workspace.html — attached to this work item. 

 Dependencies 

4.2, 4.3, 3.2 (attachments), 2.3 (quick replies) 

Blocked by: Story 4.3, Story 3.2, Story 2.3 

Blocks: Story 4.5, Story 5.1 

Traceability 

Story ID: 4.4 · Epic: 4 · Covers: FR-044, FR-066, FR-067, FR-070 (picker half), FR-071 · BR-6, BR-17 · AD-28 · UX-09, UX-17 

Delivery 

Sprint 4 · Priority 1

### Attachments

| File | Size | Status |
| ---- | ---- | ------ |
| `attachments/direction-e2-workspace.html` | 122 KB | downloaded |

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/506/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `506` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
4.4 Ticket detail — the conversation, internal notes, attachments, quick replies and customer context
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an agent, I want everything I need to answer this customer on one screen, so that I never leave the ticket to do my job. 

Context 

Epic 4 — Ticket Core & Agent Workspace. Every request becomes a ticket with one identity, one write path, one immutable history and a lifecycle that cannot be violated — categorised, prioritised, assigned, conversed on, listed and found, with the customer's context beside it and the day's work on one screen. 

The heart of the product. At the end of this epic a support team can work a full day in the system. 

Technical Constraints 

Messages are owned by Tickets and written through the Story 4.1 command path, so every message lands in history. 

messages.delivery_state is a backed enum — queued, sent, failed. Story 5.1 drives it. 

The composer draft is cached in localStorage keyed by ticket id (Story 2.1) and cleared on send. 

Quick replies are read from the Story 2.3 settings collection. The picker inserts text into the composer and nothing more — no variable substitution, no server-side rendering, no send-on-select. 

The AiSuggestionPanel component does not exist. The reference design had a fourth conversation treatment for an AI draft; there are three. 

The context panel must not trigger an N+1 — the counts come from one aggregate query, not a query per ticket. 

The composer and the property rail behave differently under the version guard (Story 4.1), and this is intentional. Sending a reply, adding a note or attaching a file is an append: it carries no version and is never refused as stale. Editing a field in the property rail is a change: it carries the version the screen loaded, and a stale one is refused with a message and a Reload. An agent typing a long reply must never be interrupted because a supervisor reassigned the ticket. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the AI panel and the AI-draft treatment · the mention and follow controls · the tasks and reminders tab · the escalate, link and merge actions · the knowledge panel · the organisation chip. 

Design References 

mockups/direction-e2-workspace.html — the primary reference for this story: the property rail, the editable edge, the conversation model. 

Mockups in Azure DevOps (no Jira needed): 

direction-e2-workspace.html — attached to this work item. 

 Dependencies 

4.2, 4.3, 3.2 (attachments), 2.3 (quick replies) 

Blocked by: Story 4.3, Story 3.2, Story 2.3 

Blocks: Story 4.5, Story 5.1 

Traceability 

Story ID: 4.4 · Epic: 4 · Covers: FR-044, FR-066, FR-067, FR-070 (picker half), FR-071 · BR-6, BR-17 · AD-28 · UX-09, UX-17 

Delivery 

Sprint 4 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
The conversation shows the full thread with three semantic treatments — customer message · agent message · internal note. The distinction is semantic, not decorative. 

An internal note carries the literal words "Not visible to the customer" and is never delivered by email and never rendered on any customer surface. 

An agent composes a reply, attaches files to it, and sends. 

A quick reply is picked from the shared list in the composer, inserted into the reply, and is fully editable before sending. 

Every message records direction, sender, recipient, body, attachments, timestamp and delivery state. 

A failed send stays visible in the timeline with Retry and Edit available (UX-09). 

The property rail shows and allows editing of: status, priority, category, assignee, department — with the SLA state read-only. 

Customer context sits beside the thread: contact details, department, open and recent ticket count, and last interaction — enough that the agent does not need to open the record. 

A route to the full customer record and timeline exists without losing the ticket. 

The whole screen is usable at 390px, and complete in Arabic RTL.
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

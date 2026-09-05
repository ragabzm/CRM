> **Fetched from azure:** [531](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/531)  
> *Fetched 2026-09-05T06:33:06.767Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 10.3 Tasks, reminders and mentions  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an agent, I want to note the thing I must not forget and to pull a colleague into a ticket, so that my follow-ups live where I work instead of on a sticky note. 

Context 

Epic 10 — Automation and Agent Tools. Three small mechanisms that a support team asks for on day two and that are usually built as engines. They are not engines here. Escalation is one property, one manual action and one built-in condition. Assignment is a lookup table. Tasks, reminders and mentions are personal work that never touches a ticket's status and never reaches a customer. Each is the least mechanism that makes its scenario work, and each is deliberately hard to grow into a workflow product. 

Technical Constraints 

tasks and reminders are owned by Tickets — the tier that may call Platform's notifications downward (AD-2), and the only module with a structural relationship to the ticket a task or reminder may point at. A standalone task is the same row with a null ticket reference. 

Mentions are parsed server-side from the stored note body at write time and resolved to user ids; do not trust a client-supplied list of mentioned users, and do not re-parse on render. 

The reminder sweep is minutely and idempotent: fired_at is set in the same transaction as the dispatch, so a second run selects nothing. 

The past-date refusal lives in the command, not only in the date picker — the API is reachable directly. 

Notifications reuse Story 5.4's channels exactly. No per-type preference matrix, no digest, no quiet hours and no snooze — the three-trigger rule that story set is not relaxed here. 

Home's tab reads its own counts in one aggregate query alongside the existing counts strip, not five more requests on the busiest screen in the product. 

 UX / Interaction Requirements 

<b>UX-12</b> — Every agent function is completable on a 390px mobile browser, and no data is silently truncated at any band. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-home.html — "Tasks & reminders as a tab", the mention row, the overdue-task treatment and "A reminder fires on this ticket" in the attention queue. mockups/direction-e2-workspace.html — the mention control in the note composer. 

Not in this version, though visible there: task descriptions, separate assignees and sub-tasks · ticket following and a followers list · a standalone Tasks destination in the sidebar · a snooze control on a fired reminder. 

Dependencies 

Blocked by: 4.4 (internal notes and the ticket screen), 5.4 (the notification path) 

Traceability 

Story ID: 10.3
Epic: Epic 10: Automation and Agent Tools
Covers: FR-068, FR-069, FR-071 · BR-6, BR-21 · AD-2, AD-18 · UX-12 

Delivery 

Sprint 9 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/531/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `531` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
10.3 Tasks, reminders and mentions
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an agent, I want to note the thing I must not forget and to pull a colleague into a ticket, so that my follow-ups live where I work instead of on a sticky note. 

Context 

Epic 10 — Automation and Agent Tools. Three small mechanisms that a support team asks for on day two and that are usually built as engines. They are not engines here. Escalation is one property, one manual action and one built-in condition. Assignment is a lookup table. Tasks, reminders and mentions are personal work that never touches a ticket's status and never reaches a customer. Each is the least mechanism that makes its scenario work, and each is deliberately hard to grow into a workflow product. 

Technical Constraints 

tasks and reminders are owned by Tickets — the tier that may call Platform's notifications downward (AD-2), and the only module with a structural relationship to the ticket a task or reminder may point at. A standalone task is the same row with a null ticket reference. 

Mentions are parsed server-side from the stored note body at write time and resolved to user ids; do not trust a client-supplied list of mentioned users, and do not re-parse on render. 

The reminder sweep is minutely and idempotent: fired_at is set in the same transaction as the dispatch, so a second run selects nothing. 

The past-date refusal lives in the command, not only in the date picker — the API is reachable directly. 

Notifications reuse Story 5.4's channels exactly. No per-type preference matrix, no digest, no quiet hours and no snooze — the three-trigger rule that story set is not relaxed here. 

Home's tab reads its own counts in one aggregate query alongside the existing counts strip, not five more requests on the busiest screen in the product. 

 UX / Interaction Requirements 

<b>UX-12</b> — Every agent function is completable on a 390px mobile browser, and no data is silently truncated at any band. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-home.html — "Tasks & reminders as a tab", the mention row, the overdue-task treatment and "A reminder fires on this ticket" in the attention queue. mockups/direction-e2-workspace.html — the mention control in the note composer. 

Not in this version, though visible there: task descriptions, separate assignees and sub-tasks · ticket following and a followers list · a standalone Tasks destination in the sidebar · a snooze control on a fired reminder. 

Dependencies 

Blocked by: 4.4 (internal notes and the ticket screen), 5.4 (the notification path) 

Traceability 

Story ID: 10.3
Epic: Epic 10: Automation and Agent Tools
Covers: FR-068, FR-069, FR-071 · BR-6, BR-21 · AD-2, AD-18 · UX-12 

Delivery 

Sprint 9 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
A user creates a task carrying a title, an optional due date and a completed state, standalone or attached to a ticket. 

A task has no description, no separate assignee, no sub-tasks, no recurrence, no checklist and no dependencies. Its owner is its creator and it cannot be handed to anyone. 

A task never changes a ticket's status, and is never visible on any customer surface — verified by calling the portal API directly. 

A reminder is set on a ticket or on a task for a future date and time and notifies its owner when it falls due, in-app and by email, in the owner's language, through Story 5.4's path. 

A reminder in the past is refused at creation with the reason stated — not accepted and then fired immediately, and not silently moved to now. 

A due reminder fires once. The sweep is idempotent and a second run sends nothing. 

An @mention inside an internal note notifies the mentioned active user and does nothing else — no ticket following, no followers list, no subscription and no visibility grant, because department is not an access boundary and there is nothing to widen. 

The mention picker lists active users only; mentioning a deactivated user is refused at composition. 

A mention notification opens the ticket at the note that mentioned you. 

Tasks and reminders are a tab inside Home, not a destination — no sidebar entry, no route of their own, no global task list, no separate application section. 

A task is completed and un-completed from that tab; completion is a state, not a delete, and an overdue task is visually distinct from a merely open one. 

Mentions appear on Home alongside tasks, as a list of the notes that named you, each opening its ticket. 

Every surface renders in Arabic RTL with Gregorian dates and Western digits, and is complete at 390px.
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

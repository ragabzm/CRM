> **Fetched from azure:** [530](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/530)  
> *Fetched 2026-09-05T06:33:02.060Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 10.2 Automatic assignment by direct mapping  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an Administrator, I want new tickets to land on the right person by a rule I can read in one line, so that the queue sorts itself without anybody operating a scheduler. 

Context 

Epic 10 — Automation and Agent Tools. Three small mechanisms that a support team asks for on day two and that are usually built as engines. They are not engines here. Escalation is one property, one manual action and one built-in condition. Assignment is a lookup table. Tasks, reminders and mentions are personal work that never touches a ticket's status and never reaches a customer. Each is the least mechanism that makes its scenario work, and each is deliberately hard to grow into a workflow product. 

Technical Constraints 

assignment_mappings is owned by Tickets, unique on (source_type, source_id) — the unique index is what makes requirement 2 structural rather than a validation anyone can relax. 

The lookup is one indexed query, not an evaluator. There is no condition parser, no expression language and no rule engine (AD-18). 

The department move runs through the same named command an agent uses to move a department, inside the creating transaction, after AD-22 has resolved the department. AD-22 remains the only department resolver; this is a recorded move, not a fifth rung on its ladder. 

The System actor is exempt from the version check — at creation there is no user's stale view to guard. 

The editor is a section of the Story 2.3 console, and the mapping list is read through the settings path so it changes without a deploy (AD-5). 

If a strategy dropdown appears on this screen, FR-059 has been re-derived. It is deferred, and it needs an availability model that was removed with chat presence. 

 UX / Interaction Requirements 

Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the auto-assignment section's table shape. mockups/screen-home.html — the unassigned count this story feeds. 

Not in this version, though visible there: the round-robin and least-open strategies · the per-agent concurrent ticket ceiling and "Agents at their ceiling" on Home · agent availability state · rule ordering. 

Dependencies 

Blocked by: 4.2 (assignment and the command path), 2.3 (the console section and the settings path) 

Traceability 

Story ID: 10.2
Epic: Epic 10: Automation and Agent Tools
Covers: FR-058 · AD-3, AD-5, AD-18, AD-22 

Delivery 

Sprint 8 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/530/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `530` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
10.2 Automatic assignment by direct mapping
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an Administrator, I want new tickets to land on the right person by a rule I can read in one line, so that the queue sorts itself without anybody operating a scheduler. 

Context 

Epic 10 — Automation and Agent Tools. Three small mechanisms that a support team asks for on day two and that are usually built as engines. They are not engines here. Escalation is one property, one manual action and one built-in condition. Assignment is a lookup table. Tasks, reminders and mentions are personal work that never touches a ticket's status and never reaches a customer. Each is the least mechanism that makes its scenario work, and each is deliberately hard to grow into a workflow product. 

Technical Constraints 

assignment_mappings is owned by Tickets, unique on (source_type, source_id) — the unique index is what makes requirement 2 structural rather than a validation anyone can relax. 

The lookup is one indexed query, not an evaluator. There is no condition parser, no expression language and no rule engine (AD-18). 

The department move runs through the same named command an agent uses to move a department, inside the creating transaction, after AD-22 has resolved the department. AD-22 remains the only department resolver; this is a recorded move, not a fifth rung on its ladder. 

The System actor is exempt from the version check — at creation there is no user's stale view to guard. 

The editor is a section of the Story 2.3 console, and the mapping list is read through the settings path so it changes without a deploy (AD-5). 

If a strategy dropdown appears on this screen, FR-059 has been re-derived. It is deferred, and it needs an availability model that was removed with chat presence. 

 UX / Interaction Requirements 

Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the auto-assignment section's table shape. mockups/screen-home.html — the unassigned count this story feeds. 

Not in this version, though visible there: the round-robin and least-open strategies · the per-agent concurrent ticket ceiling and "Agents at their ceiling" on Home · agent availability state · rule ordering. 

Dependencies 

Blocked by: 4.2 (assignment and the command path), 2.3 (the console section and the settings path) 

Traceability 

Story ID: 10.2
Epic: Epic 10: Automation and Agent Tools
Covers: FR-058 · AD-3, AD-5, AD-18, AD-22 

Delivery 

Sprint 8 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
An Administrator maintains a list of mapping rows, each one a category or a department → an agent or a department. That is the entire editor: a source, a target, and nothing else. 

At most one mapping exists per source value — a category maps to exactly one target, a department maps to exactly one target — so there is no ordering, no priority and no most-specific matching to resolve. 

Where a ticket's category and its department both carry a mapping, the category mapping wins. That precedence is fixed in code, is not configurable, and is stated in the editor rather than left to be discovered. 

Assignment is applied at creation, on every creation path — agent entry, portal submission, the public web form and every channel. 

A mapping to an agent assigns the ticket to that agent. A mapping to a department moves the ticket to that department and leaves it unassigned there. 

No match ⇒ the ticket stays unassigned. Unassigned is a valid, visible, workable state and appears in Home's unassigned count. 

A mapped agent who is inactive ⇒ the ticket stays unassigned and is never forced onto anyone. There is no fallback agent, no next-in-list and no reassignment to a supervisor. 

Assignment is attributed to System(auto_assign) in history exactly like a human assignment, and the mapping row that matched is named in the history entry. 

No round-robin, no least-open-tickets, no direct-to-queue, no per-agent ceiling and no availability model. No column, no setting, no screen and no UI affordance exists for any of them. 

An assignee supplied at creation by an acting agent is never overridden by a mapping. 

A mapping whose target is deactivated is shown as inactive in the editor with the reason, is skipped at creation, and leaves existing tickets untouched. 

Mapping changes take effect without redeployment and are audited with actor, before and after. 

Assignment happens inside the creating transaction: a ticket is never briefly visible as unassigned and then assigned a moment later.
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

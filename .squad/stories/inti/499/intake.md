> **Fetched from azure:** [499](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/499)  
> *Fetched 2026-09-01T08:08:36.739Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 2.4 The audit log  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an Administrator, I want an immutable record of every security-relevant and data-changing action, so that accountability is structural rather than procedural. 

Context 

Epic 2 — Identity, Users & Administration. An Administrator signs in, builds the organisation out of departments, creates users in the four fixed roles, configures the system without a redeployment, and finds every action in a log nobody can edit. 

Technical Constraints 

audit_entries owned by Platform (T0), so every module can write to it downward. 

No updated_at, no soft deletes, no model events that could mutate a row. Revoke UPDATE and DELETE on the table at the database-user level if the deployment allows it — that is the difference between "we do not do that" and "that cannot happen". 

Before/after values as a JSON column. Redact anything credential-shaped at write time, never at read time. 

This is a comparative table for responsive purposes: horizontal scroll with a pinned identity column, not column collapse (Story 1.4). 

Ticket history is a separate table owned by Tickets (Story 4.3). Do not merge them: one answers what happened to this ticket, the other what did this person do, and merging loses one whenever the other is queried. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the advanced audit exploration UI — filtering is by actor, action and date, and nothing more. 

Design References 

mockups/screen-admin.html — the audit board and the DataTable contract. 

Mockups in Azure DevOps (no Jira needed): 

screen-admin.html — attached to Story 2.3 (work item #498). 

 Dependencies 

2.2, 2.3 — there must be actions worth auditing. 

Blocked by: Story 2.3 

Traceability 

Story ID: 2.4 · Epic: 2 · Covers: FR-118, FR-119, FR-120 · BR-5 · NFR-12 · AD-8 · UX-13 

Delivery 

Sprint 2 · Priority 1

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/499/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `499` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
2.4 The audit log
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an Administrator, I want an immutable record of every security-relevant and data-changing action, so that accountability is structural rather than procedural. 

Context 

Epic 2 — Identity, Users & Administration. An Administrator signs in, builds the organisation out of departments, creates users in the four fixed roles, configures the system without a redeployment, and finds every action in a log nobody can edit. 

Technical Constraints 

audit_entries owned by Platform (T0), so every module can write to it downward. 

No updated_at, no soft deletes, no model events that could mutate a row. Revoke UPDATE and DELETE on the table at the database-user level if the deployment allows it — that is the difference between "we do not do that" and "that cannot happen". 

Before/after values as a JSON column. Redact anything credential-shaped at write time, never at read time. 

This is a comparative table for responsive purposes: horizontal scroll with a pinned identity column, not column collapse (Story 1.4). 

Ticket history is a separate table owned by Tickets (Story 4.3). Do not merge them: one answers what happened to this ticket, the other what did this person do, and merging loses one whenever the other is queried. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the advanced audit exploration UI — filtering is by actor, action and date, and nothing more. 

Design References 

mockups/screen-admin.html — the audit board and the DataTable contract. 

Mockups in Azure DevOps (no Jira needed): 

screen-admin.html — attached to Story 2.3 (work item #498). 

 Dependencies 

2.2, 2.3 — there must be actions worth auditing. 

Blocked by: Story 2.3 

Traceability 

Story ID: 2.4 · Epic: 2 · Covers: FR-118, FR-119, FR-120 · BR-5 · NFR-12 · AD-8 · UX-13 

Delivery 

Sprint 2 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
An audit entry is written for: sign-in success, sign-in failure, user create/edit/deactivate, every configuration change, and every ticket and customer field change. 

Each entry records actor, action, target resource, before/after values where applicable, timestamp, and source IP address. 

The log is append-only. No surface, no role — Administrator included — can update or delete an entry. Verified by attempting it directly against the API, not only through the UI (UX-13). 

An Administrator can filter by actor, action type and date range. 

The read view expresses immutability as design, not as an absent button. An administrator used to editing everything must understand why not here. 

Only the Administrator role can read it. Supervisor and Agent cannot. 

Every action listed in requirement 1 is reconstructable from the log for the full retention period.
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

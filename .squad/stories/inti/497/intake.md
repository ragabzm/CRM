> **Fetched from azure:** [497](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/497)  
> *Fetched 2026-09-01T08:08:03.185Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 2.2 Users, the four fixed roles, departments, and server-side authorization  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an Administrator, I want to create users and give each one a role that actually constrains what they can do, so that authorization is a property of the system rather than of the interface. 

Context 

Epic 2 — Identity, Users & Administration. An Administrator signs in, builds the organisation out of departments, creates users in the four fixed roles, configures the system without a redeployment, and finds every action in a log nobody can edit. 

Technical Constraints 

spatie/laravel-permission 8.3.x — used to express the fixed, seeded matrix and to provide the gate. Not as a runtime role editor: the seeder is the only writer of roles and permissions, and there is no admin UI over them. 

Capability strings are resource.action — ticket.reassign, user.manage, audit.read. No .scope suffix exists — there is no scope dimension in this version. 

Do not register a global Eloquent scope for department. The reference version did, and removing it later would be a sweep through every query. Department filtering is an explicit where in the ticket list query only. 

The one row-level rule — Agent sees own + unassigned — lives in one place in the Tickets query builder, not scattered across controllers. 

users.department_id is many-to-one. There is no branch table and no branch column anywhere. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the permission matrix editor (the matrix is fixed and read-only) · the roles CRUD screens · branches · any Administration section beyond the six named in Story 2.3. 

Design References 

mockups/screen-admin.html — Administration density and the DataTable contract. 

Mockups in Azure DevOps (no Jira needed): 

screen-admin.html — attached to Story 2.3 (work item #498). 

 Dependencies 

2.1 — a user must be able to sign in before a role can constrain them. 

Blocked by: Story 2.1 

Blocks: Story 2.3, Story 3.1 

Traceability 

Story ID: 2.2 · Epic: 2 · Covers: FR-000, FR-113, FR-114, FR-116, FR-122, FR-141 · BR-12, BR-13 · AD-4 · UX-07 

Delivery 

Sprint 2 · Priority 1

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/497/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `497` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
2.2 Users, the four fixed roles, departments, and server-side authorization
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an Administrator, I want to create users and give each one a role that actually constrains what they can do, so that authorization is a property of the system rather than of the interface. 

Context 

Epic 2 — Identity, Users & Administration. An Administrator signs in, builds the organisation out of departments, creates users in the four fixed roles, configures the system without a redeployment, and finds every action in a log nobody can edit. 

Technical Constraints 

spatie/laravel-permission 8.3.x — used to express the fixed, seeded matrix and to provide the gate. Not as a runtime role editor: the seeder is the only writer of roles and permissions, and there is no admin UI over them. 

Capability strings are resource.action — ticket.reassign, user.manage, audit.read. No .scope suffix exists — there is no scope dimension in this version. 

Do not register a global Eloquent scope for department. The reference version did, and removing it later would be a sweep through every query. Department filtering is an explicit where in the ticket list query only. 

The one row-level rule — Agent sees own + unassigned — lives in one place in the Tickets query builder, not scattered across controllers. 

users.department_id is many-to-one. There is no branch table and no branch column anywhere. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the permission matrix editor (the matrix is fixed and read-only) · the roles CRUD screens · branches · any Administration section beyond the six named in Story 2.3. 

Design References 

mockups/screen-admin.html — Administration density and the DataTable contract. 

Mockups in Azure DevOps (no Jira needed): 

screen-admin.html — attached to Story 2.3 (work item #498). 

 Dependencies 

2.1 — a user must be able to sign in before a role can constrain them. 

Blocked by: Story 2.1 

Blocks: Story 2.3, Story 3.1 

Traceability 

Story ID: 2.2 · Epic: 2 · Covers: FR-000, FR-113, FR-114, FR-116, FR-122, FR-141 · BR-12, BR-13 · AD-4 · UX-07 

Delivery 

Sprint 2 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
Four roles ship pre-configured and the set is fixed: Administrator · Supervisor · Agent · Customer. There is no role builder and no way to create, edit or delete a role. 

The capability matrix is exactly prd.md §10, seeded, and not editable at runtime. 

An Administrator can create, edit and deactivate users. Each carries name, email, role, department and active state. 

Deactivating a user immediately revokes access and preserves every historical attribution. Their name still appears on the tickets and notes they wrote. 

Every capability is enforced server-side on every request. Calling an endpoint directly, with the UI bypassed, is refused exactly as the UI would refuse it — a test proves this for at least: Agent attempting reassign, Agent attempting user management, Supervisor attempting configuration, Supervisor attempting audit read. 

A Supervisor sees all tickets and can reassign any of them. An Agent sees tickets assigned to them plus unassigned tickets. 

Department is a grouping and a filter, never an access boundary. No query is silently narrowed by the caller's department. A request returning a ticket outside the caller's department is correct behaviour, not a leak. 

An Administrator can create, rename and deactivate departments. Deactivating a department that still holds active tickets is refused with a count and a path to those tickets — not a confirmation dialog. 

A permission refusal states what was refused and who to ask. It is never rendered as empty data (UX-06, UX-07). 

Hiding a control is never the enforcement mechanism. Hide what a user can never do; explain what is refused.
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

> **Fetched from azure:** [498](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/498)  
> *Fetched 2026-09-01T08:08:21.215Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 2.3 The settings registry and the configuration console  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an Administrator, I want to configure the system from a console and have it take effect immediately, so that running the product does not require a developer or a redeployment. 

Context 

Epic 2 — Identity, Users & Administration. An Administrator signs in, builds the organisation out of departments, creates users in the four fixed roles, configures the system without a redeployment, and finds every action in a log nobody can edit. 

Technical Constraints 

A settings table: key, typed value, and a per-module registry class declaring type, default and validation. Cache the resolved set and bust the cache on write — a setting that needs a restart fails requirement 3. 

Settings owned by Platform (T0) so every module can read them without an upward dependency. 

Quick replies are a settings collection, not an entity: no owner user, no foreign key to a user, no per-agent variant. 

Categories are a small owned table in Tickets with no self-referencing parent column — omitting the column is what prevents a hierarchy being added by accident. 

Priority is a native PHP backed enum, string-backed, not a table. Making it an enum rather than a configurable list is the thing that keeps it fixed. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

four of the ten sections (Knowledge settings · AI · Portal & branding · Integrations) · the escalation-rule editor · the auto-assignment editor · the SLA policy scoping editor · the channels list beyond email. 

Design References 

mockups/screen-admin.html — section index and editor density. 

Mockups in Azure DevOps (no Jira needed): 

screen-admin.html — attached to this work item. 

 Dependencies 

2.2 — only an Administrator reaches this console. 

Blocked by: Story 2.2 

Blocks: Story 2.4, Story 4.4 

Traceability 

Story ID: 2.3 · Epic: 2 · Covers: FR-070 (management half), FR-121, FR-123 · AD-5 

Delivery 

Sprint 2 · Priority 1

### Attachments

| File | Size | Status |
| ---- | ---- | ------ |
| `attachments/screen-admin.html` | 527 KB | downloaded |

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/498/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `498` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
2.3 The settings registry and the configuration console
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an Administrator, I want to configure the system from a console and have it take effect immediately, so that running the product does not require a developer or a redeployment. 

Context 

Epic 2 — Identity, Users & Administration. An Administrator signs in, builds the organisation out of departments, creates users in the four fixed roles, configures the system without a redeployment, and finds every action in a log nobody can edit. 

Technical Constraints 

A settings table: key, typed value, and a per-module registry class declaring type, default and validation. Cache the resolved set and bust the cache on write — a setting that needs a restart fails requirement 3. 

Settings owned by Platform (T0) so every module can read them without an upward dependency. 

Quick replies are a settings collection, not an entity: no owner user, no foreign key to a user, no per-agent variant. 

Categories are a small owned table in Tickets with no self-referencing parent column — omitting the column is what prevents a hierarchy being added by accident. 

Priority is a native PHP backed enum, string-backed, not a table. Making it an enum rather than a configurable list is the thing that keeps it fixed. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

four of the ten sections (Knowledge settings · AI · Portal & branding · Integrations) · the escalation-rule editor · the auto-assignment editor · the SLA policy scoping editor · the channels list beyond email. 

Design References 

mockups/screen-admin.html — section index and editor density. 

Mockups in Azure DevOps (no Jira needed): 

screen-admin.html — attached to this work item. 

 Dependencies 

2.2 — only an Administrator reaches this console. 

Blocked by: Story 2.2 

Blocks: Story 2.4, Story 4.4 

Traceability 

Story ID: 2.3 · Epic: 2 · Covers: FR-070 (management half), FR-121, FR-123 · AD-5 

Delivery 

Sprint 2 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
Every administrator-changeable value is a typed row with a declared type, default and validation rule. Reading one goes through a settings registry, never config() or env(). 

env() appears only in config/ at boot. A lint rule enforces it. 

Configuration changes take effect without a redeployment, and every change is written to the audit log with before and after values. 

The console has six sections, never one page, with a section index that makes the shape of the console legible in one screen:    - Organisation — departments, users (Story 2.2)    - Ticketing — the category list, auto-close window, reopen window, quick replies    - Service levels — response and resolution target per priority, the weekly working hours, the holiday list, the at-risk threshold (behaviour in Story 5.3)    - Email — mailbox settings, acknowledgement template, test send, the mail log (behaviour in Story 5.1)    - Platform — attachment type allow-list, attachment size cap, default language    - Audit log (Story 2.4) 

Quick replies are managed here: create, edit, delete, reorder a list of reusable saved text replies, each with a short label and a body, in both languages. They are shared and administrator-managed — not personal, not per-agent. 

Quick replies carry no variables, no placeholders, no template engine, no approval step and no versioning. They are saved text. 

The category list is flat — there is no subcategory level. 

Priorities are fixed at Low · Normal · High · Urgent and are not editable here. The section states this rather than omitting it silently. 

A destructive confirmation names its consequence concretely; a rule-blocked action is a refusal with a count and a path.
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

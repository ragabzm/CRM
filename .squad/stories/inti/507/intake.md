> **Fetched from azure:** [507](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/507)  
> *Fetched 2026-09-01T08:09:39.850Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 4.5 Ticket list and agent home — filters, search, counts and click-through  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an agent starting my day, I want to see what needs me and find any ticket quickly, so that I never have to ask for a report to answer an urgent question. 

Context 

Epic 4 — Ticket Core & Agent Workspace. Every request becomes a ticket with one identity, one write path, one immutable history and a lifecycle that cannot be violated — categorised, prioritised, assigned, conversed on, listed and found, with the customer's context beside it and the day's work on one screen. 

The heart of the product. At the end of this epic a support team can work a full day in the system. 

Technical Constraints 

Ticket search is PostgreSQL-native — tsvector with a GIN index over reference, subject and description, plus a trigram index on reference for partial-reference lookup. No search engine. Owned by Tickets. 

Index for the filters that actually run: composite indexes on (status, assignee_id), (status, sla_state), (department_id, status). Requirement 8 is not free at 50,000 tickets. 

Freshness is a per-query refetchInterval on the list and the open ticket, plus refetch-on-window-focus. There is no cursor-based sync?since= endpoint, no cross-module merge layer and no shared connection-state machine. A failed refetch leaves the last good data on screen and retries; a 401 redirects to sign-in. 

The counts are one aggregate query, not five. 

No bulk-select column and no bulk-action bar. The reference mockups show both; neither is a requirement. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the bulk-action bar and multi-select column · saved-view tabs under Tickets · the manager widget vocabulary on Home · the connection indicator. 

direction-e2-list.html predates the row-actions decision and does not draw the persistent overflow button. Where it differs, screen-responsive.html board R-5 wins.Design References 

mockups/direction-e2-list.html (row hierarchy, toolbar, filters) and mockups/screen-home.html (counts strip, attention queue). mockups/screen-responsive.html board R-5 is the normative RowActions rendering. 

Mockups in Azure DevOps (no Jira needed): 

direction-e2-list.html — attached to this work item. 

screen-home.html — attached to this work item. 

screen-responsive.html — attached to Story 1.4 (work item #495). 

 Dependencies 

4.2, 4.4 — and Story 5.3 for the SLA state shown in the list and counts. Home ships in Sprint 4 with the SLA column dark, and lights up in Sprint 5. 

Blocked by: Story 4.4 

Traceability 

Story ID: 4.5 · Epic: 4 · Covers: FR-030, FR-062 – FR-065, FR-110 · NFR-01, NFR-02 · R-06 · AD-9, AD-29 · UX-08 

Delivery 

Sprint 4 · Priority 1

### Attachments

| File | Size | Status |
| ---- | ---- | ------ |
| `attachments/screen-home.html` | 165 KB | downloaded |
| `attachments/direction-e2-list.html` | 109 KB | downloaded |

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/507/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `507` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
4.5 Ticket list and agent home — filters, search, counts and click-through
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an agent starting my day, I want to see what needs me and find any ticket quickly, so that I never have to ask for a report to answer an urgent question. 

Context 

Epic 4 — Ticket Core & Agent Workspace. Every request becomes a ticket with one identity, one write path, one immutable history and a lifecycle that cannot be violated — categorised, prioritised, assigned, conversed on, listed and found, with the customer's context beside it and the day's work on one screen. 

The heart of the product. At the end of this epic a support team can work a full day in the system. 

Technical Constraints 

Ticket search is PostgreSQL-native — tsvector with a GIN index over reference, subject and description, plus a trigram index on reference for partial-reference lookup. No search engine. Owned by Tickets. 

Index for the filters that actually run: composite indexes on (status, assignee_id), (status, sla_state), (department_id, status). Requirement 8 is not free at 50,000 tickets. 

Freshness is a per-query refetchInterval on the list and the open ticket, plus refetch-on-window-focus. There is no cursor-based sync?since= endpoint, no cross-module merge layer and no shared connection-state machine. A failed refetch leaves the last good data on screen and retries; a 401 redirects to sign-in. 

The counts are one aggregate query, not five. 

No bulk-select column and no bulk-action bar. The reference mockups show both; neither is a requirement. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the bulk-action bar and multi-select column · saved-view tabs under Tickets · the manager widget vocabulary on Home · the connection indicator. 

direction-e2-list.html predates the row-actions decision and does not draw the persistent overflow button. Where it differs, screen-responsive.html board R-5 wins.Design References 

mockups/direction-e2-list.html (row hierarchy, toolbar, filters) and mockups/screen-home.html (counts strip, attention queue). mockups/screen-responsive.html board R-5 is the normative RowActions rendering. 

Mockups in Azure DevOps (no Jira needed): 

direction-e2-list.html — attached to this work item. 

screen-home.html — attached to this work item. 

screen-responsive.html — attached to Story 1.4 (work item #495). 

 Dependencies 

4.2, 4.4 — and Story 5.3 for the SLA state shown in the list and counts. Home ships in Sprint 4 with the SLA column dark, and lights up in Sprint 5. 

Blocked by: Story 4.4 

Traceability 

Story ID: 4.5 · Epic: 4 · Covers: FR-030, FR-062 – FR-065, FR-110 · NFR-01, NFR-02 · R-06 · AD-9, AD-29 · UX-08 

Delivery 

Sprint 4 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
The ticket list filters by status, priority, category, assignee, department and date range, and searches by free text across reference, subject and description. 

Filters are set on the list and are not persisted as named views — saved views are deferred, and the absence is not a bug. 

Home lists the agent's assigned, non-closed tickets, sortable and filterable by SLA state, priority, status, category and age. 

Home defaults to ordering by SLA urgency, then priority, then age. 

Home shows counts for: assigned to me · unassigned · at risk · breached · pending customer reply. 

Every count opens the ticket list filtered to exactly the tickets behind it (UX-08). A figure with no click-through does not ship. 

A Supervisor sees all tickets and team counts. An Agent sees their own plus unassigned. 

List queries return within 1 second and search within 2 seconds at the expected volume. 

The list is a scanned list: it collapses columns into the row at tablet and mobile. It does not scroll horizontally, and it never silently truncates. 

Data stays reasonably fresh without a manual refresh.
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

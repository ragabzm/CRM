> **Fetched from azure:** [533](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/533)  
> *Fetched 2026-09-05T06:33:13.905Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 11.2 The fixed report set  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a supervisor, I want a small set of figures I can trust and click into, so that I can answer a question about last month without asking anyone to build a report. 

Context 

Epic 11 — Feedback and Reporting. Two figures a support manager is actually asked for — are we hitting our targets and are customers happy — and nothing else. Feedback is a boolean, deliberately, so no scale ever has to be converted or normalised. Reporting owns no table, writes nothing, reads facts recorded when they happened, and filters on one dimension: a date range. Every figure opens the tickets behind it, because a number nobody can audit is a number nobody believes. 

Technical Constraints 

Indexes exist for the queries that actually run: tickets(created_at), tickets(resolved_at), tickets(status, created_at), sla_events(occurred_at, target_type). A month-range aggregate over 50,000 tickets is not free. 

Averages are computed with the Sla module's business-hours arithmetic class (AD-16). Reporting calls it; it does not reimplement it. A naive wall-clock difference will disagree with the ticket's own SLA badge, and the customer will find the disagreement first. 

The satisfaction rate reads tickets.satisfaction WHERE NOT NULL. The denominator is ratings given, never tickets closed — those are two different numbers and only one of them answers the question. 

Click-through builds a Story 4.5 ticket-list URL with filters in query parameters, so the destination is the one ticket list and not a second, report-only list that drifts from it. 

The capability check is the single AD-4 gate, server-side. There is no report-specific permission model. 

Reduced deliberately, and these must not appear: the sealed-period stamp, the read log, the target in force column, backlog age bands, the reopen rate, and any agent self-view privacy rule — reporting is Supervisor-and-above, so there is no self-view to protect. 

 UX / Interaction Requirements 

<b>UX-05</b> — Ticket references, phone numbers, emails and dates render with Western digits 0-9 in both locales, identically. 

<b>UX-07</b> — A permission refusal states what was refused and who to ask. It never renders as empty data. 

<b>UX-08</b> — Every count on Home resolves to its underlying ticket list in one action. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-reports.html — the card strip, the breakdown tables and the compliance meter. mockups/screen-home.html — the live counts strip this surface deliberately does not duplicate. 

Not in this version, though visible there: the whole Export board and its CSV/PDF controls · the branch, department, category and assignee filter row (date range only) · the average rating out of 5 and response-rate pair (a positive rate over a boolean) · backlog age bands and the reopen rate · the policy-version, "last read" and event-ledger treatments on the compliance board · per-agent comparative and self-view shapes. 

Dependencies 

Blocked by: 11.1 (the satisfaction figure), 5.3 (the recorded sla_events), 4.5 (the ticket list every figure opens) 

Traceability 

Story ID: 11.2
Epic: Epic 11: Feedback and Reporting
Covers: FR-102, FR-104, FR-105, FR-106, FR-108, FR-110 (the Home counts panel this surface reuses the shape of, and leaves live) · AD-4, AD-15, AD-16, AD-25 · UX-05, UX-07, UX-08 

Delivery 

Sprint 10 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/533/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `533` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
11.2 The fixed report set
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a supervisor, I want a small set of figures I can trust and click into, so that I can answer a question about last month without asking anyone to build a report. 

Context 

Epic 11 — Feedback and Reporting. Two figures a support manager is actually asked for — are we hitting our targets and are customers happy — and nothing else. Feedback is a boolean, deliberately, so no scale ever has to be converted or normalised. Reporting owns no table, writes nothing, reads facts recorded when they happened, and filters on one dimension: a date range. Every figure opens the tickets behind it, because a number nobody can audit is a number nobody believes. 

Technical Constraints 

Indexes exist for the queries that actually run: tickets(created_at), tickets(resolved_at), tickets(status, created_at), sla_events(occurred_at, target_type). A month-range aggregate over 50,000 tickets is not free. 

Averages are computed with the Sla module's business-hours arithmetic class (AD-16). Reporting calls it; it does not reimplement it. A naive wall-clock difference will disagree with the ticket's own SLA badge, and the customer will find the disagreement first. 

The satisfaction rate reads tickets.satisfaction WHERE NOT NULL. The denominator is ratings given, never tickets closed — those are two different numbers and only one of them answers the question. 

Click-through builds a Story 4.5 ticket-list URL with filters in query parameters, so the destination is the one ticket list and not a second, report-only list that drifts from it. 

The capability check is the single AD-4 gate, server-side. There is no report-specific permission model. 

Reduced deliberately, and these must not appear: the sealed-period stamp, the read log, the target in force column, backlog age bands, the reopen rate, and any agent self-view privacy rule — reporting is Supervisor-and-above, so there is no self-view to protect. 

 UX / Interaction Requirements 

<b>UX-05</b> — Ticket references, phone numbers, emails and dates render with Western digits 0-9 in both locales, identically. 

<b>UX-07</b> — A permission refusal states what was refused and who to ask. It never renders as empty data. 

<b>UX-08</b> — Every count on Home resolves to its underlying ticket list in one action. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-reports.html — the card strip, the breakdown tables and the compliance meter. mockups/screen-home.html — the live counts strip this surface deliberately does not duplicate. 

Not in this version, though visible there: the whole Export board and its CSV/PDF controls · the branch, department, category and assignee filter row (date range only) · the average rating out of 5 and response-rate pair (a positive rate over a boolean) · backlog age bands and the reopen rate · the policy-version, "last read" and event-ledger treatments on the compliance board · per-agent comparative and self-view shapes. 

Dependencies 

Blocked by: 11.1 (the satisfaction figure), 5.3 (the recorded sla_events), 4.5 (the ticket list every figure opens) 

Traceability 

Story ID: 11.2
Epic: Epic 11: Feedback and Reporting
Covers: FR-102, FR-104, FR-105, FR-106, FR-108, FR-110 (the Home counts panel this surface reuses the shape of, and leaves live) · AD-4, AD-15, AD-16, AD-25 · UX-05, UX-07, UX-08 

Delivery 

Sprint 10 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
Six cards over the chosen period: total · open · pending · resolved · closed · breached. 

Ticket volume broken down by status, by category and by assignee. 

SLA performance: compliance rate against the response and resolution targets, breach count, average time to first response and average time to resolution. 

Customer satisfaction: the positive rate — thumbs up as a proportion of ratings given — with the up/down split and the response volume. 

A date range is the only filter. No department, channel, priority, agent, category or branch filter row; no report builder, no custom query, no ad-hoc modelling and no saved report. 

Compliance is computed from recorded sla_events rows and is never recalculated from current targets. A test edits a target, re-runs a past period, and asserts every figure is byte-identical. 

Reporting owns no table and performs no write, and no code in Reporting evaluates an SLA target. 

Every figure opens the ticket list behind it, filtered to exactly those tickets over the same period. A figure with no click-through does not ship. 

The reports are Supervisor and Administrator only, enforced server-side. An Agent calling the endpoint directly is refused with a stated reason and a named person to ask — never an empty page. 

There is no export. No CSV, no PDF, no print view, no download control and no share link anywhere on the surface. 

Reports query the same database — no warehouse, no read replica, no ETL and no aggregation table. Adding this module adds no infrastructure. 

Home's live counts panel is unchanged and stays live; the report cards are the same shape of figure over a chosen period, and the two are never mixed on one surface. 

Every date and numeral renders Gregorian with Western digits in both locales, including the range picker, and every figure column uses tabular numerals. 

Report queries return within the interactive page budget at the expected volume; a period that returns nothing renders an unmistakable empty state, not a grid of zeros presented as data.
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

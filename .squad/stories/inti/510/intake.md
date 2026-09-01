> **Fetched from azure:** [510](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/510)  
> *Fetched 2026-09-01T08:10:33.057Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 5.3 SLA — targets, the working-hours calendar, timers, the Pending pause and the breach record  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a supervisor, I want response and resolution time measured against the hours we actually work, so that the SLA indicator is believed instead of ignored. 

Context 

Epic 5 — Email, SLA & Notifications. Customers reach support by email and their replies land on the right ticket; response and resolution times become explicit, measured against real working hours, and the right people are told when something needs them. 

Technical Constraints 

Business-hours arithmetic exists in exactly one class, in the Sla module: how much working time elapsed between A and B. Every timer, indicator and breach calculation calls it. Two implementations will disagree, and the disagreement will be found by a customer. 

Every timestamp is stored and computed in UTC. Conversion to display timezone happens only in the formatting layer. Working hours are defined in the organisation's timezone and converted — this is the classic place a DST transition produces a wrong figure, so test across one. 

SLA state is computed, not stored — except the breach, which is a persisted sla_events row. Requirement 8 is the reason: a computed breach disappears the moment the policy or the ticket changes. 

A minutely scheduled sweep moves tickets into At risk and Breached and writes the breach row. It must be idempotent — a breach is written once, and running the sweep twice does not produce two rows. 

Sla (T4) depends on Tickets (T3) downward and observes ticket events; it never calls up. 

There is no SLA policy table, no most-specific matching, no per-department calendar and no consequence preview. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the six-state indicator (three) · the SLA policy list and its scoping editor · the escalation-rule editor · the auto-assignment editor · the policy consequence preview. 

Design References 

mockups/screen-responsive.html board R-1 — the SLA state treatments. Use three of the six drawn: On track, At risk, Breached. mockups/screen-admin.html — the Service levels section. 

Mockups in Azure DevOps (no Jira needed): 

screen-admin.html — attached to Story 2.3 (work item #498). 

screen-responsive.html — attached to Story 1.4 (work item #495). 

 Dependencies 

4.2 (lifecycle drives the pause), 5.1 (first outbound reply stops the response timer), 2.3 (the settings sections) 

Blocked by: Story 5.1 

Blocks: Story 5.4 

Traceability 

Story ID: 5.3 · Epic: 5 · Covers: FR-047, FR-049 – FR-054 · BR-8, BR-9 · AD-16, AD-18 · UX-11 

Delivery 

Sprint 5 · Priority 1

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/510/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `510` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
5.3 SLA — targets, the working-hours calendar, timers, the Pending pause and the breach record
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a supervisor, I want response and resolution time measured against the hours we actually work, so that the SLA indicator is believed instead of ignored. 

Context 

Epic 5 — Email, SLA & Notifications. Customers reach support by email and their replies land on the right ticket; response and resolution times become explicit, measured against real working hours, and the right people are told when something needs them. 

Technical Constraints 

Business-hours arithmetic exists in exactly one class, in the Sla module: how much working time elapsed between A and B. Every timer, indicator and breach calculation calls it. Two implementations will disagree, and the disagreement will be found by a customer. 

Every timestamp is stored and computed in UTC. Conversion to display timezone happens only in the formatting layer. Working hours are defined in the organisation's timezone and converted — this is the classic place a DST transition produces a wrong figure, so test across one. 

SLA state is computed, not stored — except the breach, which is a persisted sla_events row. Requirement 8 is the reason: a computed breach disappears the moment the policy or the ticket changes. 

A minutely scheduled sweep moves tickets into At risk and Breached and writes the breach row. It must be idempotent — a breach is written once, and running the sweep twice does not produce two rows. 

Sla (T4) depends on Tickets (T3) downward and observes ticket events; it never calls up. 

There is no SLA policy table, no most-specific matching, no per-department calendar and no consequence preview. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the six-state indicator (three) · the SLA policy list and its scoping editor · the escalation-rule editor · the auto-assignment editor · the policy consequence preview. 

Design References 

mockups/screen-responsive.html board R-1 — the SLA state treatments. Use three of the six drawn: On track, At risk, Breached. mockups/screen-admin.html — the Service levels section. 

Mockups in Azure DevOps (no Jira needed): 

screen-admin.html — attached to Story 2.3 (work item #498). 

screen-responsive.html — attached to Story 1.4 (work item #495). 

 Dependencies 

4.2 (lifecycle drives the pause), 5.1 (first outbound reply stops the response timer), 2.3 (the settings sections) 

Blocked by: Story 5.1 

Blocks: Story 5.4 

Traceability 

Story ID: 5.3 · Epic: 5 · Covers: FR-047, FR-049 – FR-054 · BR-8, BR-9 · AD-16, AD-18 · UX-11 

Delivery 

Sprint 5 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
An Administrator defines a response target and a resolution target for each of the four priorities. There is one such set, system-wide — no policies, no scoping. The editor says so plainly. 

An Administrator defines one weekly working-hours schedule and one holiday list, both on the Gregorian calendar in both locales. 

The response timer runs from ticket creation to the first outbound agent reply, then stops and never restarts. 

The resolution timer runs from creation to Resolved, pauses while Pending, and resumes on return to Open. 

Elapsed time counts only working hours. 

Every ticket exposes an SLA state — On track · At risk · Breached — with time remaining, on the ticket and in every ticket list. 

An at-risk warning is raised at a configurable percentage of a target (default 80%). 

A breach is recorded permanently on the ticket, naming which target was missed and by how much, and survives the ticket later being resolved. 

A paused timer is visually distinct from a running one and from a breached one (UX-11). 

The SLA editor shows what a target means against the configured schedule — "4 working hours, from now, is Tuesday 11:00" — so a target can be checked rather than guessed.
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

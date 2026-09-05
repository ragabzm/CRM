> **Fetched from azure:** [532](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/532)  
> *Fetched 2026-09-05T06:33:10.726Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 11.1 Customer feedback  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a customer whose problem is finished, I want to say in one tap whether it went well, so that giving feedback costs me nothing and still counts for something. 

Context 

Epic 11 — Feedback and Reporting. Two figures a support manager is actually asked for — are we hitting our targets and are customers happy — and nothing else. Feedback is a boolean, deliberately, so no scale ever has to be converted or normalised. Reporting owns no table, writes nothing, reads facts recorded when they happened, and filters on one dimension: a date range. Every figure opens the tickets behind it, because a number nobody can audit is a number nobody believes. 

Technical Constraints 

tickets.satisfaction is a nullable boolean, with satisfaction_comment and satisfaction_at beside it. Nullable is the third state. Do not model this as an integer "for flexibility" — an integer is a scale, and a scale is exactly what §19.1 and FR-101 refuse. 

The rating is written by a named Tickets command. It is append-shaped: it is not one of the five contended properties, so it carries no version and changes none. 

The email invitation is a signed, time-limited link scoped to that one ticket, valid for the change window, and grants nothing beyond rating that ticket. 

The change window is an AD-5 setting alongside the auto-close and reopen windows. 

No CSAT scale table, no NPS, no per-agent satisfaction attribution. Story 11.2 reports the positive rate over the whole period and nothing per person. 

 UX / Interaction Requirements 

<b>UX-03</b> — Every status, SLA state and priority remains distinguishable in greyscale. 

<b>UX-15</b> — The customer portal never renders an internal note, an SLA countdown, or another agent's activity. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-portal.html — the rating prompt on a closed request and its placement on the customer's request detail. 

Not in this version, though visible there: the five-star scale the mockup argues for ("Five stars, not five faces") · a required comment · an average-rating figure · per-agent satisfaction attribution. 

Dependencies 

Blocked by: 6.2 (the portal request surface and its confinement), 4.2 (Resolved and Closed, the states that trigger the invitation) 

Traceability 

Story ID: 11.1
Epic: Epic 11: Feedback and Reporting
Covers: FR-101 · BR-15 · AD-1, AD-3, AD-4 · UX-03, UX-15 

Delivery 

Sprint 9 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/532/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `532` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
11.1 Customer feedback
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a customer whose problem is finished, I want to say in one tap whether it went well, so that giving feedback costs me nothing and still counts for something. 

Context 

Epic 11 — Feedback and Reporting. Two figures a support manager is actually asked for — are we hitting our targets and are customers happy — and nothing else. Feedback is a boolean, deliberately, so no scale ever has to be converted or normalised. Reporting owns no table, writes nothing, reads facts recorded when they happened, and filters on one dimension: a date range. Every figure opens the tickets behind it, because a number nobody can audit is a number nobody believes. 

Technical Constraints 

tickets.satisfaction is a nullable boolean, with satisfaction_comment and satisfaction_at beside it. Nullable is the third state. Do not model this as an integer "for flexibility" — an integer is a scale, and a scale is exactly what §19.1 and FR-101 refuse. 

The rating is written by a named Tickets command. It is append-shaped: it is not one of the five contended properties, so it carries no version and changes none. 

The email invitation is a signed, time-limited link scoped to that one ticket, valid for the change window, and grants nothing beyond rating that ticket. 

The change window is an AD-5 setting alongside the auto-close and reopen windows. 

No CSAT scale table, no NPS, no per-agent satisfaction attribution. Story 11.2 reports the positive rate over the whole period and nothing per person. 

 UX / Interaction Requirements 

<b>UX-03</b> — Every status, SLA state and priority remains distinguishable in greyscale. 

<b>UX-15</b> — The customer portal never renders an internal note, an SLA countdown, or another agent's activity. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-portal.html — the rating prompt on a closed request and its placement on the customer's request detail. 

Not in this version, though visible there: the five-star scale the mockup argues for ("Five stars, not five faces") · a required comment · an average-rating figure · per-agent satisfaction attribution. 

Dependencies 

Blocked by: 6.2 (the portal request surface and its confinement), 4.2 (Resolved and Closed, the states that trigger the invitation) 

Traceability 

Story ID: 11.1
Epic: Epic 11: Feedback and Reporting
Covers: FR-101 · BR-15 · AD-1, AD-3, AD-4 · UX-03, UX-15 

Delivery 

Sprint 9 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
A customer is invited to give feedback when their ticket reaches Resolved or Closed — on the request in the portal, and in the resolution email. 

There are exactly two choices: thumbs up or thumbs down. No stars, no five-point scale, no faces and no numeric value shown anywhere. 

An optional free-text comment is offered after the rating, never before it and never as a required field. Rating without commenting is one tap and is complete. 

The rating is stored as a boolean against the ticket, owned by Tickets. There is no rating scale, no scale identifier, no scale version, and no conversion or normalisation anywhere in the product. 

A rating may be changed within a configurable window and is locked after it, with the locked state stating why rather than silently ignoring the tap. 

A ticket is rated once: a second submission inside the window replaces the first; outside it, the submission is refused with the reason. 

Only the ticket's own customer can rate it. A rating call for another customer's ticket is refused by the portal's own confinement, by any path including a guessed id. 

Feedback is visible to staff on the ticket and is never editable by staff — not by an Agent, a Supervisor or an Administrator. 

The invitation is presented once per surface and is never chased with repeat emails. 

The rating and any change to it are recorded on the ticket with actor and timestamp. 

An unrated ticket is neither positive nor negative. The absent rating is a third state meaning not rated, never a neutral value and never a zero. 

Both controls are distinguishable in greyscale, keyboard reachable, and carry a text label — not colour-only, not icon-only. 

The whole interaction is complete on a mobile browser and in Arabic RTL.
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

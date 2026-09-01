> **Fetched from azure:** [502](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/502)  
> *Fetched 2026-09-01T08:09:03.810Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 3.3 Customer interaction timeline  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an agent about to reply, I want this customer's entire history in one list, so that I can see whether we have discussed this before without opening four tickets. 

Context 

Epic 3 — Customers. Staff hold one authoritative record per customer — searchable, de-duplicated, annotated, with files attached and scanned — and can see that customer's whole story in one place. 

Technical Constraints 

Customers (T2) must not call Tickets (T3) — that is an upward call and it is forbidden. The timeline is either a frontend composition of the Customers surface and a Tickets list query, or it reads a read-model that Tickets publishes downward. Pick one and be consistent. This is the one place in the plan where the tier rule shapes the implementation, so it is called out rather than discovered in review. 

Paginate it. A three-year customer has hundreds of entries and this screen must not load them all. 

No channel filter and no date filter in this version — with one channel, a channel filter is meaningless. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the channel filter chips · the date-range filter · any WhatsApp, SMS or chat lane marker. 

Design References 

mockups/screen-customer-profile.html — the interaction timeline and its lane markers. 

Mockups in Azure DevOps (no Jira needed): 

screen-customer-profile.html — attached to Story 3.1 (work item #500). 

 Dependencies 

3.1, and Story 4.1 — there must be tickets to show. Sequenced into Sprint 4 for this reason. 

Blocked by: Story 4.1 

Traceability 

Story ID: 3.3 · Epic: 3 · Covers: FR-006 · AD-2 

Delivery 

Sprint 4 · Priority 1

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/502/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `502` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
3.3 Customer interaction timeline
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an agent about to reply, I want this customer's entire history in one list, so that I can see whether we have discussed this before without opening four tickets. 

Context 

Epic 3 — Customers. Staff hold one authoritative record per customer — searchable, de-duplicated, annotated, with files attached and scanned — and can see that customer's whole story in one place. 

Technical Constraints 

Customers (T2) must not call Tickets (T3) — that is an upward call and it is forbidden. The timeline is either a frontend composition of the Customers surface and a Tickets list query, or it reads a read-model that Tickets publishes downward. Pick one and be consistent. This is the one place in the plan where the tier rule shapes the implementation, so it is called out rather than discovered in review. 

Paginate it. A three-year customer has hundreds of entries and this screen must not load them all. 

No channel filter and no date filter in this version — with one channel, a channel filter is meaningless. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the channel filter chips · the date-range filter · any WhatsApp, SMS or chat lane marker. 

Design References 

mockups/screen-customer-profile.html — the interaction timeline and its lane markers. 

Mockups in Azure DevOps (no Jira needed): 

screen-customer-profile.html — attached to Story 3.1 (work item #500). 

 Dependencies 

3.1, and Story 4.1 — there must be tickets to show. Sequenced into Sprint 4 for this reason. 

Blocked by: Story 4.1 

Traceability 

Story ID: 3.3 · Epic: 3 · Covers: FR-006 · AD-2 

Delivery 

Sprint 4 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
The timeline shows every ticket and every inbound and outbound message for the customer, in one reverse-chronological list. 

Each entry is identifiable at a glance: what it was, when, and which ticket it belongs to. 

Opening a ticket from the timeline navigates to it; returning does not lose position. 

The timeline is usable at mobile width without silently truncating anything. 

It renders correctly in Arabic RTL, with Gregorian dates and Western digits.
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

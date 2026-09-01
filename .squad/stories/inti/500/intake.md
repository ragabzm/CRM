> **Fetched from azure:** [500](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/500)  
> *Fetched 2026-09-01T08:08:43.420Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 3.1 Customer record — create, search, edit, deactivate, with duplicate detection  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an agent, I want one authoritative record per customer that I can find on a partial name or number, so that a repeat caller's history is one search away and not scattered across four records. 

Context 

Epic 3 — Customers. Staff hold one authoritative record per customer — searchable, de-duplicated, annotated, with files attached and scanned — and can see that customer's whole story in one place. 

Technical Constraints 

contact_identifiers is a separate table, not columns on customers — requirement 2 stores several of each, and Story 5.2 must resolve an inbound email address against any one of them. 

Search is PostgreSQL-native: pg_trgm GIN indexes on name, email and phone for partial matching. No search engine. Owned by the Customers module — there is no cross-module search endpoint and no federated query. 

Duplicate detection queries contact_identifiers on normalised email and normalised phone (strip spaces, punctuation and country-code variants) before insert. It offers, it does not block — a real second person can share a household phone. 

Deactivation is a state column, never a delete. 

Do not add organisation_id. The reference version had it; adding it "just in case" is exactly what the reduction is meant to prevent. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the organisation chip and organisation link · the customer-merge flow · the interaction-history channel filter. 

Design References 

mockups/screen-customer-profile.html — the record layout and field grouping. 

Mockups in Azure DevOps (no Jira needed): 

screen-customer-profile.html — attached to this work item. 

 Dependencies 

2.2 — customer access is capability-gated. 

Blocked by: Story 2.2 

Blocks: Story 3.2, Story 4.1 

Traceability 

Story ID: 3.1 · Epic: 3 · Covers: FR-001 – FR-005, FR-012 · AD-9 · NFR-02 

Delivery 

Sprint 3 · Priority 1

### Attachments

| File | Size | Status |
| ---- | ---- | ------ |
| `attachments/screen-customer-profile.html` | 123 KB | downloaded |

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/500/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `500` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
3.1 Customer record — create, search, edit, deactivate, with duplicate detection
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an agent, I want one authoritative record per customer that I can find on a partial name or number, so that a repeat caller's history is one search away and not scattered across four records. 

Context 

Epic 3 — Customers. Staff hold one authoritative record per customer — searchable, de-duplicated, annotated, with files attached and scanned — and can see that customer's whole story in one place. 

Technical Constraints 

contact_identifiers is a separate table, not columns on customers — requirement 2 stores several of each, and Story 5.2 must resolve an inbound email address against any one of them. 

Search is PostgreSQL-native: pg_trgm GIN indexes on name, email and phone for partial matching. No search engine. Owned by the Customers module — there is no cross-module search endpoint and no federated query. 

Duplicate detection queries contact_identifiers on normalised email and normalised phone (strip spaces, punctuation and country-code variants) before insert. It offers, it does not block — a real second person can share a household phone. 

Deactivation is a state column, never a delete. 

Do not add organisation_id. The reference version had it; adding it "just in case" is exactly what the reduction is meant to prevent. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the organisation chip and organisation link · the customer-merge flow · the interaction-history channel filter. 

Design References 

mockups/screen-customer-profile.html — the record layout and field grouping. 

Mockups in Azure DevOps (no Jira needed): 

screen-customer-profile.html — attached to this work item. 

 Dependencies 

2.2 — customer access is capability-gated. 

Blocked by: Story 2.2 

Blocks: Story 3.2, Story 4.1 

Traceability 

Story ID: 3.1 · Epic: 3 · Covers: FR-001 – FR-005, FR-012 · AD-9 · NFR-02 

Delivery 

Sprint 3 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
An authorised user creates a customer with a full name and at least one contact identifier. 

A customer stores multiple contact details: several email addresses, several phone numbers, and a preferred contact channel. 

Search matches on partial input across name, email, phone and customer reference, and returns results fast enough to type against (NFR-02). 

A user can edit and deactivate a customer. Deactivated customers are excluded from search defaults but retain all history and are still reachable by direct link. 

At creation, an existing customer with the same email or phone is detected and offered — "this person already exists, open them instead" — rather than a second record being created silently. 

Every customer carries a department. It is a grouping and a filter, never an access boundary. 

There is no organisation, company or account field of any kind — not as an entity, not as a free-text field, not as a filter.
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

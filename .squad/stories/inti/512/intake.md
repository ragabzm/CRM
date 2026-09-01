> **Fetched from azure:** [512](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/512)  
> *Fetched 2026-09-01T08:10:41.774Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 6.1 Portal identity — registration, sign-in, password reset and the portal shell  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a customer, I want an account that lets me see my own requests, so that I can follow up without emailing to ask for an update. 

Context 

Epic 6 — Customer Portal. A customer registers, submits, tracks, replies and reopens — on a phone, in their own language, and can never reach another customer's data or a single internal note. 

Technical Constraints 

The second Sanctum guard over portal_accounts from Story 2.1, with its own session cookie. Two guards, two tables, no discriminator column. 

The portal is the (portal) route group of the same Next.js app — sharing the design system, the locale shell and the formatting layer, and sharing nothing in the data layer. There is no third artifact and no separate application. 

Registration links to an existing customers row by matching email; where none exists, one is created. 

Rate-limit registration, sign-in and reset endpoints. 

No branding. The portal wears the default design system — there is no logo upload, no primary-colour override and no header configuration. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the brand swap (logo, primary colour, header) · the Help centre destination · the CSAT prompt · the anonymous-request claim flow. 

Design References 

mockups/screen-portal.html — the .portal setting and the customer progress model. 

Mockups in Azure DevOps (no Jira needed): 

screen-portal.html — attached to this work item. 

 Dependencies 

2.1 (the two identity spaces), 1.3 (the shell) 

Blocked by: Story 2.1 

Blocks: Story 6.2 

Traceability 

Story ID: 6.1 · Epic: 6 · Covers: FR-092, FR-093 · BR-15 · AD-7, AD-13, AD-14 

Delivery 

Sprint 6 · Priority 1

### Attachments

| File | Size | Status |
| ---- | ---- | ------ |
| `attachments/screen-portal.html` | 212 KB | downloaded |

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/512/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `512` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
6.1 Portal identity — registration, sign-in, password reset and the portal shell
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a customer, I want an account that lets me see my own requests, so that I can follow up without emailing to ask for an update. 

Context 

Epic 6 — Customer Portal. A customer registers, submits, tracks, replies and reopens — on a phone, in their own language, and can never reach another customer's data or a single internal note. 

Technical Constraints 

The second Sanctum guard over portal_accounts from Story 2.1, with its own session cookie. Two guards, two tables, no discriminator column. 

The portal is the (portal) route group of the same Next.js app — sharing the design system, the locale shell and the formatting layer, and sharing nothing in the data layer. There is no third artifact and no separate application. 

Registration links to an existing customers row by matching email; where none exists, one is created. 

Rate-limit registration, sign-in and reset endpoints. 

No branding. The portal wears the default design system — there is no logo upload, no primary-colour override and no header configuration. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the brand swap (logo, primary colour, header) · the Help centre destination · the CSAT prompt · the anonymous-request claim flow. 

Design References 

mockups/screen-portal.html — the .portal setting and the customer progress model. 

Mockups in Azure DevOps (no Jira needed): 

screen-portal.html — attached to this work item. 

 Dependencies 

2.1 (the two identity spaces), 1.3 (the shell) 

Blocked by: Story 2.1 

Blocks: Story 6.2 

Traceability 

Story ID: 6.1 · Epic: 6 · Covers: FR-092, FR-093 · BR-15 · AD-7, AD-13, AD-14 

Delivery 

Sprint 6 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
A customer registers a portal account and signs in with email and password. 

Password reset is by email-delivered, time-limited, single-use link. 

The portal is a deliberately different shell: no sidebar, a slim top bar, mobile-first. Destinations: My requests · New request · Account. 

A portal account cannot reach any staff surface, by navigation or by direct API call. 

A portal account holds no role and no staff capability. A test proves the staff guard cannot resolve it. 

The portal is fully bilingual with genuine RTL and uses the same design system. 

The portal reads "request", never "ticket", wherever "request" will do.
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

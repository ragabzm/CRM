> **Fetched from azure:** [513](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/513)  
> *Fetched 2026-09-01T08:10:46.540Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 6.2 My requests — submit, track, view, reply and reopen  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a customer, I want to raise a request and follow it through, so that I know where it stands without asking. 

Context 

Epic 6 — Customer Portal. A customer registers, submits, tracks, replies and reopens — on a phone, in their own language, and can never reach another customer's data or a single internal note. 

Technical Constraints 

Every portal query is confined to the signed-in account's customer. This confinement lives in the Portal module's own query layer and is absolute — it is the one true data boundary in the system, as distinct from the capability checks everywhere else. Write the test that guesses another customer's ticket id. 

The customer-visible thread is a separate serialiser from the staff one. Do not filter the staff resource in the frontend — requirement 7 must hold at the API. Internal notes must not be in the response at all. 

Submission reuses the Story 4.1 CreateTicket command with a PortalAccount actor. The portal does not write ticket rows. 

Reopen reuses the Story 4.2 command and its window setting. 

The portal is a scanned list at mobile width; it folds, it does not scroll horizontally. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the Help centre and article surfaces · the CSAT rating prompt · the branded header · any SLA figure on a customer surface. 

Design References 

mockups/screen-portal.html — the request list, the request detail and the customer progress model. 

Mockups in Azure DevOps (no Jira needed): 

screen-portal.html — attached to Story 6.1 (work item #512). 

 Dependencies 

6.1, 4.4 (the conversation), 5.4 (the assignee notification) 

Blocked by: Story 6.1, Story 5.4 

Traceability 

Story ID: 6.2 · Epic: 6 · Covers: FR-094, FR-096 – FR-098, FR-100 · BR-6, BR-10, BR-15 · R-03, R-08 · AD-4 · UX-15 

Delivery 

Sprint 6 · Priority 1

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/513/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `513` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
6.2 My requests — submit, track, view, reply and reopen
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a customer, I want to raise a request and follow it through, so that I know where it stands without asking. 

Context 

Epic 6 — Customer Portal. A customer registers, submits, tracks, replies and reopens — on a phone, in their own language, and can never reach another customer's data or a single internal note. 

Technical Constraints 

Every portal query is confined to the signed-in account's customer. This confinement lives in the Portal module's own query layer and is absolute — it is the one true data boundary in the system, as distinct from the capability checks everywhere else. Write the test that guesses another customer's ticket id. 

The customer-visible thread is a separate serialiser from the staff one. Do not filter the staff resource in the frontend — requirement 7 must hold at the API. Internal notes must not be in the response at all. 

Submission reuses the Story 4.1 CreateTicket command with a PortalAccount actor. The portal does not write ticket rows. 

Reopen reuses the Story 4.2 command and its window setting. 

The portal is a scanned list at mobile width; it folds, it does not scroll horizontally. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the Help centre and article surfaces · the CSAT rating prompt · the branded header · any SLA figure on a customer surface. 

Design References 

mockups/screen-portal.html — the request list, the request detail and the customer progress model. 

Mockups in Azure DevOps (no Jira needed): 

screen-portal.html — attached to Story 6.1 (work item #512). 

 Dependencies 

6.1, 4.4 (the conversation), 5.4 (the assignee notification) 

Blocked by: Story 6.1, Story 5.4 

Traceability 

Story ID: 6.2 · Epic: 6 · Covers: FR-094, FR-096 – FR-098, FR-100 · BR-6, BR-10, BR-15 · R-03, R-08 · AD-4 · UX-15 

Delivery 

Sprint 6 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
A signed-in customer submits a request with subject, description, category and attachments. 

A customer sees the current status of each of their requests. 

A customer lists and opens all their requests, open and closed, with the full customer-visible thread. 

A customer adds a reply or an attachment. The reply appends to the thread, returns a Pending request to Open, and notifies the assignee — and the customer is told none of this, because none of it is their concern. 

A customer reopens a closed request within the configured window, and it returns with its full history intact. 

Past the reopen window, the refusal offers a linked new request rather than a dead end. 

The portal never renders an internal note, an SLA countdown, or another agent's activity (UX-15). Verified by calling the portal API directly, not only by inspecting the UI. 

A customer can reach no other customer's data, by any path including direct API call with a guessed id. 

Every function is completable on a mobile browser, including attaching a photo from the camera or photo library.
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

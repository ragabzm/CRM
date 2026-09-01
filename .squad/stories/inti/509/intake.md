> **Fetched from azure:** [509](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/509)  
> *Fetched 2026-09-01T08:10:26.125Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 5.2 Inbound email — intake, correlation, auto-created customers and quarantine  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a customer, I want to just email support, so that I get help without an account, a form or a portal. 

Context 

Epic 5 — Email, SLA & Notifications. Customers reach support by email and their replies land on the right ticket; response and resolution times become explicit, measured against real working hours, and the right people are told when something needs them. 

Technical Constraints 

Idempotency is the first thing that happens, before anything else. Key every inbound message by its provider message id (or Message-ID) in a mail_inbound table; a key already present is acknowledged and discarded. Do this before parsing, not after — requirement 6 depends on it. 

Two viable intake paths; the story picks one and states it: IMAP polling on the scheduler, or a provider inbound webhook posting to one signed route. The webhook route is one route, not the deferred webhook subsystem — do not build a general webhook framework. 

Parse with a real MIME library. Strip quoted history and signatures best-effort; when in doubt keep the text — a truncated customer message is worse than a noisy one. 

Auto-created customers get the auto-created flag as a column, so they can be found and merged later. 

Reuse the Story 4.1 commands with System(inbound_email) — inbound must not write ticket rows directly. 

Guard against mail loops: never auto-acknowledge a message that carries auto-submitted or auto-reply headers. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the channel-correlation window setting for non-email channels · the multi-channel identity resolution · the web-form intake path. 

Design References 

EXPERIENCE.md § State Patterns. The quarantine surface is an administrator list — derive it from the DataTable. 

Dependencies 

5.1 — correlation keys on the threading headers 5.1 writes. 

Blocked by: Story 5.1 

Traceability 

Story ID: 5.2 · Epic: 5 · Covers: FR-031, FR-033, FR-034, FR-035 · NFR-03 · BR-1, BR-2, BR-10 · AD-17 

Delivery 

Sprint 5 · Priority 1

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/509/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `509` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
5.2 Inbound email — intake, correlation, auto-created customers and quarantine
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a customer, I want to just email support, so that I get help without an account, a form or a portal. 

Context 

Epic 5 — Email, SLA & Notifications. Customers reach support by email and their replies land on the right ticket; response and resolution times become explicit, measured against real working hours, and the right people are told when something needs them. 

Technical Constraints 

Idempotency is the first thing that happens, before anything else. Key every inbound message by its provider message id (or Message-ID) in a mail_inbound table; a key already present is acknowledged and discarded. Do this before parsing, not after — requirement 6 depends on it. 

Two viable intake paths; the story picks one and states it: IMAP polling on the scheduler, or a provider inbound webhook posting to one signed route. The webhook route is one route, not the deferred webhook subsystem — do not build a general webhook framework. 

Parse with a real MIME library. Strip quoted history and signatures best-effort; when in doubt keep the text — a truncated customer message is worse than a noisy one. 

Auto-created customers get the auto-created flag as a column, so they can be found and merged later. 

Reuse the Story 4.1 commands with System(inbound_email) — inbound must not write ticket rows directly. 

Guard against mail loops: never auto-acknowledge a message that carries auto-submitted or auto-reply headers. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the channel-correlation window setting for non-email channels · the multi-channel identity resolution · the web-form intake path. 

Design References 

EXPERIENCE.md § State Patterns. The quarantine surface is an administrator list — derive it from the DataTable. 

Dependencies 

5.1 — correlation keys on the threading headers 5.1 writes. 

Blocked by: Story 5.1 

Traceability 

Story ID: 5.2 · Epic: 5 · Covers: FR-031, FR-033, FR-034, FR-035 · NFR-03 · BR-1, BR-2, BR-10 · AD-17 

Delivery 

Sprint 5 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
Email arriving at the configured support address becomes a ticket or a reply on an existing ticket, visible within 2 minutes of receipt. 

The customer is identified by the sending email address, matched against any of that customer's stored identifiers. 

Where no customer matches, one is created automatically and flagged auto-created. It is a first-class customer record in every other respect. 

Correlation to an existing ticket is attempted in a fixed order: mail thread reference (In-Reply-To / References) → a ticket reference in the subject → a ticket reference in the body. Only on total failure is a new ticket created. 

A customer reply on a Pending ticket returns it to Open and notifies the assignee. 

A retried delivery of the same message does not create a second ticket or a duplicate message. 

A message that cannot be parsed at all lands in quarantine with its raw source, visible to Administrators. It is never silently dropped. 

Inbound attachments go through the Story 3.2 subsystem — validated, scanned, quarantined until clean. 

Inbound processing runs with a System actor and appears in ticket history as such.
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

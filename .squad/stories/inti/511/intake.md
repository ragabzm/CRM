> **Fetched from azure:** [511](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/511)  
> *Fetched 2026-09-01T08:10:37.386Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 5.4 Notifications — in-app and email, in the recipient's language  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an agent, I want to be told when something becomes mine or changes under me, so that I do not have to poll a list to find out. 

Context 

Epic 5 — Email, SLA & Notifications. Customers reach support by email and their replies land on the right ticket; response and resolution times become explicit, measured against real working hours, and the right people are told when something needs them. 

Technical Constraints 

Laravel's notification system with the database and mail channels. Not the broadcast channel — there is no WebSocket server. 

Server-side rendering is the one exception to "the API emits codes, the frontend renders prose", because an email has no frontend. Render from lang/{en,ar}/ at send time, in the recipient's locale — not the actor's. 

Dispatch is queued. A slow mail provider must never slow down the request that triggered the notification. 

The bell reads the same notifications table; unread count is one indexed query. 

No per-type preference matrix, no digest, no quiet hours. Three triggers, both channels, always. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the notification centre surface · the preferences matrix on the profile · mention and follow notifications. 

Design References 

EXPERIENCE.md § Information Architecture — the bell in global chrome. 

Dependencies 

5.1 (the mail path), 5.3 (the SLA triggers), 4.2 (the assignment trigger) 

Blocked by: Story 5.3 

Blocks: Story 6.2 

Traceability 

Story ID: 5.4 · Epic: 5 · Covers: FR-060, FR-061, FR-138 · L-05 · AD-11 

Delivery 

Sprint 5 · Priority 2

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/511/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `511` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
5.4 Notifications — in-app and email, in the recipient's language
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an agent, I want to be told when something becomes mine or changes under me, so that I do not have to poll a list to find out. 

Context 

Epic 5 — Email, SLA & Notifications. Customers reach support by email and their replies land on the right ticket; response and resolution times become explicit, measured against real working hours, and the right people are told when something needs them. 

Technical Constraints 

Laravel's notification system with the database and mail channels. Not the broadcast channel — there is no WebSocket server. 

Server-side rendering is the one exception to "the API emits codes, the frontend renders prose", because an email has no frontend. Render from lang/{en,ar}/ at send time, in the recipient's locale — not the actor's. 

Dispatch is queued. A slow mail provider must never slow down the request that triggered the notification. 

The bell reads the same notifications table; unread count is one indexed query. 

No per-type preference matrix, no digest, no quiet hours. Three triggers, both channels, always. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the notification centre surface · the preferences matrix on the profile · mention and follow notifications. 

Design References 

EXPERIENCE.md § Information Architecture — the bell in global chrome. 

Dependencies 

5.1 (the mail path), 5.3 (the SLA triggers), 4.2 (the assignment trigger) 

Blocked by: Story 5.3 

Blocks: Story 6.2 

Traceability 

Story ID: 5.4 · Epic: 5 · Covers: FR-060, FR-061, FR-138 · L-05 · AD-11 

Delivery 

Sprint 5 · Priority 2
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
Notifications fire on exactly three triggers: ticket assigned to you · new customer reply on your ticket · SLA at-risk or breached on your ticket. 

On SLA at-risk and breach, the assignee is alerted. On breach, Supervisors are alerted too. 

Each is delivered in-app and by email. 

Every notification renders in the recipient's preferred language, defaulting to English. 

The in-app surface is the notification bell with a plain list — read and unread, newest first, each opening its ticket. It is not a notification centre. 

Customer-facing email — the acknowledgement and reply notifications — renders in the customer's language. 

Notification text rendered server-side uses Gregorian dates and Western digits, matching the interface.
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

> **Fetched from azure:** [534](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/534)  
> *Fetched 2026-09-05T06:33:19.798Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 12.1 Branches and branding  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an Administrator, I want to say which office a record belongs to and to put our logo on what the customer sees, so that the product looks like ours without acquiring a second permission system. 

Context 

Epic 12 — Platform and Integrations. The last epic makes the product legible to the rest of the organisation: a branch label to say where work happened, a brand that reaches customers and stops at the staff door, one public API that is the same API the application uses, and one generic ERP adapter with an exchange log that never holds a secret. Nothing in this epic is required for the product to work — with every integration disabled and no provider reachable, the ticketing loop, the portal and the console are fully operational. 

Technical Constraints 

branches is owned by Platform (T0). branch_id is a nullable foreign key on users, customers and tickets. 

No global scope class exists for branch, and none for department. That absence is the mechanism behind requirement 4 — a scope registered "just for filtering" is how FR-117 grows back. 

Branding values are AD-5 settings. The contrast check runs server-side at save, in one function, against the fixed set of surface backgrounds the colour will sit on, using the WCAG 2.1 relative-luminance formula, with fixtures tested at the pass/fail boundary. A client-side check is presentation; it is never the enforcement point. 

Branded surfaces read the values as CSS custom properties on their layout root. The staff layout root never reads them, which makes requirement 6 structural rather than a review item. 

Email templates take the logo and header at render time in the recipient's language (AD-11's one exception), with the same Gregorian/Western-digit formatter as everything else. 

Branch filtering is a plain WHERE on a query the caller already had the right to run — it is a filter the user chooses, never one the system applies. 

 UX / Interaction Requirements 

Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the Portal & branding section, the four-branded-surfaces statement, and the Branch column in the user table. mockups/screen-portal.html — the branded header on a customer surface. 

Not in this version, though visible there: the preview-before-publish workflow and its "1 change awaiting publish" state · the application-name field · a separate sign-in logo · palette and typography controls · branch-scoped data access. 

Dependencies 

Blocked by: 2.3 (the settings registry and the console), 7.1 (the public web form, one of the four branded surfaces), 7.3 (the widget, another) 

Traceability 

Story ID: 12.1
Epic: Epic 12: Platform and Integrations
Covers: FR-142, FR-144 · BR-12, BR-20 · AD-4, AD-5, AD-19 · NFR-13 

Delivery 

Sprint 10 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/534/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `534` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
12.1 Branches and branding
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an Administrator, I want to say which office a record belongs to and to put our logo on what the customer sees, so that the product looks like ours without acquiring a second permission system. 

Context 

Epic 12 — Platform and Integrations. The last epic makes the product legible to the rest of the organisation: a branch label to say where work happened, a brand that reaches customers and stops at the staff door, one public API that is the same API the application uses, and one generic ERP adapter with an exchange log that never holds a secret. Nothing in this epic is required for the product to work — with every integration disabled and no provider reachable, the ticketing loop, the portal and the console are fully operational. 

Technical Constraints 

branches is owned by Platform (T0). branch_id is a nullable foreign key on users, customers and tickets. 

No global scope class exists for branch, and none for department. That absence is the mechanism behind requirement 4 — a scope registered "just for filtering" is how FR-117 grows back. 

Branding values are AD-5 settings. The contrast check runs server-side at save, in one function, against the fixed set of surface backgrounds the colour will sit on, using the WCAG 2.1 relative-luminance formula, with fixtures tested at the pass/fail boundary. A client-side check is presentation; it is never the enforcement point. 

Branded surfaces read the values as CSS custom properties on their layout root. The staff layout root never reads them, which makes requirement 6 structural rather than a review item. 

Email templates take the logo and header at render time in the recipient's language (AD-11's one exception), with the same Gregorian/Western-digit formatter as everything else. 

Branch filtering is a plain WHERE on a query the caller already had the right to run — it is a filter the user chooses, never one the system applies. 

 UX / Interaction Requirements 

Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the Portal & branding section, the four-branded-surfaces statement, and the Branch column in the user table. mockups/screen-portal.html — the branded header on a customer surface. 

Not in this version, though visible there: the preview-before-publish workflow and its "1 change awaiting publish" state · the application-name field · a separate sign-in logo · palette and typography controls · branch-scoped data access. 

Dependencies 

Blocked by: 2.3 (the settings registry and the console), 7.1 (the public web form, one of the four branded surfaces), 7.3 (the widget, another) 

Traceability 

Story ID: 12.1
Epic: Epic 12: Platform and Integrations
Covers: FR-142, FR-144 · BR-12, BR-20 · AD-4, AD-5, AD-19 · NFR-13 

Delivery 

Sprint 10 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
An Administrator creates a branch with a name, a short code and an active state, and renames or deactivates it. Deactivation never deletes and never orphans a record. 

A user, a customer and a ticket may each carry one branch, or none. 

A ticket inherits its customer's branch at creation where the customer has one, and carries none otherwise. An unresolved branch has no consequence — which is why branch needs no resolution order of its own. 

Branch is a label and a filter. It appears on the record and in the ticket-list filter, and no query is scoped by it: no global scope is registered, no read or write is narrowed, and a request that returns a ticket outside the caller's branch is correct behaviour, not a leak. A test asserts exactly that. 

Branding consists of exactly three values: an organisation logo, a primary colour, and the portal/email header. 

Branding reaches exactly four surfaces: the customer portal, the public web form, the live-chat widget and outbound email. It reaches no staff surface — the workspace and the console wear the default design system, and a test asserts no branded token resolves there. 

A primary colour failing WCAG AA contrast against the surfaces it will sit on is refused at save, with the reason and the measured ratio stated. It is not accepted with a warning and not applied pending review. 

Branding alters presentation only — never behaviour, never permissions, never data. No branded surface gains or loses a control, and no branded surface renders a different field set. 

Absent, with no field, setting or upload for any of them: an application name, a separate sign-in logo, a palette, typography or spacing control, a preview workflow, a theme builder and custom CSS. 

The logo goes through the Story 3.2 attachment path — validated, scanned, and served from the object store, never from the application origin. 

The chat widget receives branding as tokens only, consistent with Story 7.3: it gains the colour and the logo, never the component tree. 

Branch and branding changes take effect without redeployment and are recorded in the audit log with actor, before and after. 

Where the brand does not reach is stated in the console, so nobody hunts for a switch that was never built.
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

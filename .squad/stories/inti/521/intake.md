> **Fetched from azure:** [521](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/521)  
> *Fetched 2026-09-05T06:31:27.078Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 7.1 The channel spine and the public web form  
**Type:** User Story  
**Status:** New

### Description

User Story 

As the system, I want every inbound message to enter through one pipeline whatever carried it, so that adding a channel adds a transport and not a second ticketing product. 

Context 

Epic 7 — Multi-Channel Intake. Email stopped being the only way in. This epic generalises Story 5.2's inbound path into one channel-agnostic pipeline — one idempotency key, one identification step, one correlation order, one quarantine, one department resolution — and then hangs four more transports off it: the public web form, WhatsApp, SMS and live chat. A channel supplies a transport and an identifier. It never supplies a second kind of ticket, a second history or a second correlation rule, and at the end of this epic that claim is enforced by structure rather than by discipline. 

Technical Constraints 

channel_accounts and inbound_messages are owned by Channels. The unique index is on the pair (channel, provider_message_id) — not on a mail Message-ID, which is what lets four more channels arrive without changing the shape. Story 5.2's mail_inbound table folds into inbound_messages; do not leave two intake tables alive. 

Correlation runs in one class with one entry point, called by every channel. A channel adapter supplies an identifier and a payload and gets back a ticket — it never decides correlation itself. Two implementations will diverge and the divergence will be found by a customer whose reply opened a duplicate ticket. 

The correlation window is an AD-5 setting per channel (a chat session and an SMS thread do not deserve the same window), read through the settings registry, changeable without redeployment and audited. 

Intake reuses the Story 4.1 commands with a System(inbound_<channel>) actor. No channel writes a ticket, message or assignment row directly. 

The web form is a route in the existing (public) route group of the one Next.js app. It is not a second artifact — the widget in Story 7.3 is the only exception to that, and there is no third. 

Bot protection with no new infrastructure: a per-IP and per-identifier throttle, a honeypot field, and a minimum fill time. No third-party captcha service, no external dependency (NFR-16). 

Form attachments go through the Story 3.2 subsystem — validated against the allow-list, scanned, quarantined until clean, never served from the application origin. 

The department default setting is not nullable: fail deployment validation rather than let a ticket exist with no department. 

No form builder, no second form, no configurable field set. FR-043 is deferred and the editor that would satisfy it must not appear. 

 UX / Interaction Requirements 

<b>UX-10</b> — An attachment pending scan is visible, labelled, and not downloadable. No attachment previews inline. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the Channels section: the per-channel enable/disable rows, the inbound counts, and the department binding on a channel account. 

Not in this version, though visible there: the two public forms and the field editor behind them (one form, six fixed fields) · the SLA-policy scoping that pins a policy to three of five channels · the escalation-rule table · the organisation picker on the form. 

Dependencies 

Blocked by: 5.2 — this generalises the email intake that story built, and inherits its correlation and quarantine tests. 

Traceability 

Story ID: 7.1
Epic: Epic 7: Multi-Channel Intake
Covers: FR-013, FR-033, FR-035, FR-042, FR-044, FR-046 · BR-1, BR-2, BR-22 · AD-6, AD-13, AD-17, AD-22 · UX-10 

Delivery 

Sprint 8 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/521/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `521` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
7.1 The channel spine and the public web form
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As the system, I want every inbound message to enter through one pipeline whatever carried it, so that adding a channel adds a transport and not a second ticketing product. 

Context 

Epic 7 — Multi-Channel Intake. Email stopped being the only way in. This epic generalises Story 5.2's inbound path into one channel-agnostic pipeline — one idempotency key, one identification step, one correlation order, one quarantine, one department resolution — and then hangs four more transports off it: the public web form, WhatsApp, SMS and live chat. A channel supplies a transport and an identifier. It never supplies a second kind of ticket, a second history or a second correlation rule, and at the end of this epic that claim is enforced by structure rather than by discipline. 

Technical Constraints 

channel_accounts and inbound_messages are owned by Channels. The unique index is on the pair (channel, provider_message_id) — not on a mail Message-ID, which is what lets four more channels arrive without changing the shape. Story 5.2's mail_inbound table folds into inbound_messages; do not leave two intake tables alive. 

Correlation runs in one class with one entry point, called by every channel. A channel adapter supplies an identifier and a payload and gets back a ticket — it never decides correlation itself. Two implementations will diverge and the divergence will be found by a customer whose reply opened a duplicate ticket. 

The correlation window is an AD-5 setting per channel (a chat session and an SMS thread do not deserve the same window), read through the settings registry, changeable without redeployment and audited. 

Intake reuses the Story 4.1 commands with a System(inbound_<channel>) actor. No channel writes a ticket, message or assignment row directly. 

The web form is a route in the existing (public) route group of the one Next.js app. It is not a second artifact — the widget in Story 7.3 is the only exception to that, and there is no third. 

Bot protection with no new infrastructure: a per-IP and per-identifier throttle, a honeypot field, and a minimum fill time. No third-party captcha service, no external dependency (NFR-16). 

Form attachments go through the Story 3.2 subsystem — validated against the allow-list, scanned, quarantined until clean, never served from the application origin. 

The department default setting is not nullable: fail deployment validation rather than let a ticket exist with no department. 

No form builder, no second form, no configurable field set. FR-043 is deferred and the editor that would satisfy it must not appear. 

 UX / Interaction Requirements 

<b>UX-10</b> — An attachment pending scan is visible, labelled, and not downloadable. No attachment previews inline. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the Channels section: the per-channel enable/disable rows, the inbound counts, and the department binding on a channel account. 

Not in this version, though visible there: the two public forms and the field editor behind them (one form, six fixed fields) · the SLA-policy scoping that pins a policy to three of five channels · the escalation-rule table · the organisation picker on the form. 

Dependencies 

Blocked by: 5.2 — this generalises the email intake that story built, and inherits its correlation and quarantine tests. 

Traceability 

Story ID: 7.1
Epic: Epic 7: Multi-Channel Intake
Covers: FR-013, FR-033, FR-035, FR-042, FR-044, FR-046 · BR-1, BR-2, BR-22 · AD-6, AD-13, AD-17, AD-22 · UX-10 

Delivery 

Sprint 8 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
A channel_accounts table holds one row per configured channel account — email, WhatsApp, SMS, chat and web form — each carrying its channel type, provider configuration, active state and the department it is bound to. Story 5.2's email intake is migrated onto it and behaves identically afterwards; no email test changes. 

An Administrator enables, disables and configures each channel independently, without affecting tickets already created through it. A disabled channel stops accepting inbound traffic, its existing tickets stay fully workable, and outbound on it is blocked with the reason shown to the agent before composing — not after they have written a reply. 

Every inbound message on every channel is keyed by (channel, provider_message_id), unique, recorded before anything else happens. A key already present is a no-op that returns the original result — not an error, and not a second ticket. 

The customer is identified by the channel identifier of the message: email address, phone number, WhatsApp number, chat session, or form-supplied email, matched against that customer's stored identifiers. Where none matches, a customer is created and flagged auto-created, and is a first-class record in every other respect. 

Correlation to an existing ticket is attempted in this order, stopping at the first hit: mail thread reference → a ticket reference in the subject or body → an open ticket on the same channel identifier inside a configurable window. Only on total failure is a new ticket created. 

Which rule matched is recorded on the message and is readable on the ticket, so "why did this land here?" is answered from the data. 

A message that cannot be parsed at all lands in quarantine with its raw source, visible to Administrators. No inbound message is ever discarded, on any channel. 

Every message records direction, channel, sender, recipient, body, attachments, timestamp and delivery state. 

A ticket's department is resolved once, at creation, in one place, stopping at the first hit: explicitly supplied by the acting agent → the department bound to the receiving channel account or web form → the customer's own department → the system default, which must be set before go-live and cannot be null. The rule that matched is recorded on the creating ticket event. 

The public web form is reachable without an account and carries exactly six fields: name · email or phone · subject · category · message · attachment. There is no seventh field, the set is not configurable, and there is only one form. 

The form creates a customer where new and a ticket in every case, through the same pipeline and the same commands as every other channel. A double submission produces one ticket. 

The form is rate-limited per source and refuses automated submission; a refused submission states what the person should do instead, and never renders as a blank page or a silent no-op.
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

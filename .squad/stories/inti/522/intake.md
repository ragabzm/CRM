> **Fetched from azure:** [522](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/522)  
> *Fetched 2026-09-05T06:31:41.829Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 7.2 WhatsApp and SMS on one provider gateway  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a customer, I want to message support from WhatsApp or by text, so that I get help from the app already open in my hand and the conversation is still on record. 

Context 

Epic 7 — Multi-Channel Intake. Email stopped being the only way in. This epic generalises Story 5.2's inbound path into one channel-agnostic pipeline — one idempotency key, one identification step, one correlation order, one quarantine, one department resolution — and then hangs four more transports off it: the public web form, WhatsApp, SMS and live chat. A channel supplies a transport and an identifier. It never supplies a second kind of ticket, a second history or a second correlation rule, and at the end of this epic that claim is enforced by structure rather than by discipline. 

Technical Constraints 

One ChannelTransport port with one adapter per provider. WhatsApp and SMS share the send job, the retry policy and the delivery-state enum; what differs is configuration, not code path. 

Phone identifiers are normalised to E.164 at the boundary, on the way in and on the way out. A customer's phone number and WhatsApp number are separate identifiers on the same record — the same digits may legitimately be both, and matching must not depend on which one the provider reported. 

The inbound webhook is one signed route per provider, verified by the provider's signature before the payload is read. This is a route, not the deferred webhook subsystem — do not build a general webhook framework (FR-128 stays deferred). 

Where a provider imposes a business-initiated messaging window, that constraint is surfaced on the composer before the agent writes, with the same treatment as a disabled channel. No template library, no template approval state, no template manager. 

messages.delivery_state extends the Story 4.4 enum with the states these providers actually report; a state the provider does not report is never invented. 

A null adapter ships for both channels so the full test suite passes in CI with no provider reachable. 

Adding these two channels adds no table. They are rows in channel_accounts and messages in the existing tables. 

 UX / Interaction Requirements 

<b>UX-09</b> — A failed message send remains visible in the timeline with Retry and Edit available. 

<b>UX-16</b> — The mail password and any provider credential are never retrievable in plaintext through any UI path. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the WhatsApp and SMS rows in the Channels section, and the credential-as-configured treatment. mockups/direction-e2-workspace.html — the channel marker on a message and the failed-message treatment. 

Not in this version, though visible there: the per-channel SLA policy pinning · the escalation-rule table beside it · the organisation chip on the customer context. 

Dependencies 

Blocked by: 7.1 — both channels are consumers of the pipeline that story builds. 

Traceability 

Story ID: 7.2
Epic: Epic 7: Multi-Channel Intake
Covers: FR-002, FR-040, FR-041, FR-130 · BR-22 · AD-6, AD-17 · UX-09, UX-16 

Delivery 

Sprint 9 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/522/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `522` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
7.2 WhatsApp and SMS on one provider gateway
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a customer, I want to message support from WhatsApp or by text, so that I get help from the app already open in my hand and the conversation is still on record. 

Context 

Epic 7 — Multi-Channel Intake. Email stopped being the only way in. This epic generalises Story 5.2's inbound path into one channel-agnostic pipeline — one idempotency key, one identification step, one correlation order, one quarantine, one department resolution — and then hangs four more transports off it: the public web form, WhatsApp, SMS and live chat. A channel supplies a transport and an identifier. It never supplies a second kind of ticket, a second history or a second correlation rule, and at the end of this epic that claim is enforced by structure rather than by discipline. 

Technical Constraints 

One ChannelTransport port with one adapter per provider. WhatsApp and SMS share the send job, the retry policy and the delivery-state enum; what differs is configuration, not code path. 

Phone identifiers are normalised to E.164 at the boundary, on the way in and on the way out. A customer's phone number and WhatsApp number are separate identifiers on the same record — the same digits may legitimately be both, and matching must not depend on which one the provider reported. 

The inbound webhook is one signed route per provider, verified by the provider's signature before the payload is read. This is a route, not the deferred webhook subsystem — do not build a general webhook framework (FR-128 stays deferred). 

Where a provider imposes a business-initiated messaging window, that constraint is surfaced on the composer before the agent writes, with the same treatment as a disabled channel. No template library, no template approval state, no template manager. 

messages.delivery_state extends the Story 4.4 enum with the states these providers actually report; a state the provider does not report is never invented. 

A null adapter ships for both channels so the full test suite passes in CI with no provider reachable. 

Adding these two channels adds no table. They are rows in channel_accounts and messages in the existing tables. 

 UX / Interaction Requirements 

<b>UX-09</b> — A failed message send remains visible in the timeline with Retry and Edit available. 

<b>UX-16</b> — The mail password and any provider credential are never retrievable in plaintext through any UI path. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the WhatsApp and SMS rows in the Channels section, and the credential-as-configured treatment. mockups/direction-e2-workspace.html — the channel marker on a message and the failed-message treatment. 

Not in this version, though visible there: the per-channel SLA policy pinning · the escalation-rule table beside it · the organisation chip on the customer context. 

Dependencies 

Blocked by: 7.1 — both channels are consumers of the pipeline that story builds. 

Traceability 

Story ID: 7.2
Epic: Epic 7: Multi-Channel Intake
Covers: FR-002, FR-040, FR-041, FR-130 · BR-22 · AD-6, AD-17 · UX-09, UX-16 

Delivery 

Sprint 9 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
An Administrator configures a WhatsApp business number and an SMS number as channel accounts, each with its provider, its credentials and its active state, through the Story 7.1 configuration. 

Inbound WhatsApp and SMS messages enter the Story 7.1 pipeline unchanged — same idempotency key, same customer identification, same correlation order, same quarantine, same department resolution. Neither channel has a correlation rule, a ticket type or an inbox of its own. 

A customer record stores a WhatsApp number alongside email addresses, phone numbers and a preferred contact channel, and an inbound WhatsApp message resolves the customer by that number. 

An agent replies from the ticket on the channel the ticket arrived on. Sending is a queued job with an explicit timeout and bounded retry-with-backoff. 

Delivery state is recorded on the message and surfaced in the conversation; a failed send stays visible in the timeline with Retry and Edit available, and transient failures retry per the configured policy. 

The provider for each channel is a configuration choice. Replacing a provider changes no channel behaviour, no ticket behaviour and no history — a test sends the same inbound payload through two adapters and asserts the same ticket, the same correlation rule and the same history rows. 

Where a provider supports no inbound, outbound alone is fully functional and the absence is stated in the channel configuration — discovered when the channel is configured, not when a customer's reply vanishes. 

If a provider is unreachable, tickets are still created, assigned, escalated, replied to and resolved. The failure is queued, retried and surfaced to Administrators; it never blocks the interface. 

Provider credentials are write-only through the console, encrypted at rest, never logged and never returned by any endpoint. A configured credential renders as configured — never as a masked secret with a reveal control. 

WhatsApp and SMS are one implementation with two configurations: one transport adapter shape serves both, and there is no channel-specific ticket path anywhere in the codebase. 

Outbound on a disabled WhatsApp or SMS channel is blocked with the reason shown before composing. 

Both channels render on the ticket with their channel named on every message, and the conversation reads as one thread regardless of how many transports contributed to it.
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

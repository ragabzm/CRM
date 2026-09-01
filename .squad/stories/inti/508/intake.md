> **Fetched from azure:** [508](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/508)  
> *Fetched 2026-09-01T08:10:18.096Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 5.1 Outbound email — send, thread preservation, acknowledgement, retry, the log and test send  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a customer, I want the agent's reply to arrive as a normal email I can just reply to, so that I never have to sign in to anything. 

Context 

Epic 5 — Email, SLA & Notifications. Customers reach support by email and their replies land on the right ticket; response and resolution times become explicit, measured against real working hours, and the right people are told when something needs them. 

Technical Constraints 

Laravel's mail layer with a configurable driver — SMTP or a provider API (Postmark, Mailgun, SES). This is the MailTransport port; a thin interface over it carries the degraded-behaviour contract, and a null adapter must exist so the full test suite passes in CI with no provider reachable. 

Thread preservation is the load-bearing detail and it is easy to get wrong. Set Message-ID on every outbound message and store it. Set In-Reply-To and References from the stored ids of the thread. Story 5.2 correlates on exactly these. Also embed the ticket reference in the subject as a fallback for clients that strip headers. 

Sending is a queued job with backoff. Failure after retries flips messages.delivery_state to failed and surfaces it — it must not be swallowed. 

mail_log owned by the Email module, with a retention setting. 

Credentials encrypted at rest via Laravel's encrypter, never logged, never returned by any endpoint, and write-only through the console. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the WhatsApp, SMS, live-chat and web-form channel sections · the integration console and its connector list · the multi-mailbox configuration. 

Design References 

mockups/screen-admin.html — the Email section, and the credential-as-configured treatment. mockups/direction-e2-workspace.html — the failed-message treatment in the conversation. 

Mockups in Azure DevOps (no Jira needed): 

direction-e2-workspace.html — attached to Story 4.4 (work item #506). 

screen-admin.html — attached to Story 2.3 (work item #498). 

 Dependencies 

4.4 — there must be a message to send. This story precedes 5.2 deliberately: inbound correlation keys on the threading headers this story writes, so building inbound first means building it against headers that do not exist yet. 

Blocked by: Story 4.4 

Blocks: Story 5.2, Story 5.3 

Traceability 

Story ID: 5.1 · Epic: 5 · Covers: FR-032, FR-036, FR-045, FR-046, FR-130, FR-132, FR-133 · NFR-06, NFR-17 · AD-6 · UX-16 

Delivery 

Sprint 5 · Priority 1

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/508/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `508` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
5.1 Outbound email — send, thread preservation, acknowledgement, retry, the log and test send
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a customer, I want the agent's reply to arrive as a normal email I can just reply to, so that I never have to sign in to anything. 

Context 

Epic 5 — Email, SLA & Notifications. Customers reach support by email and their replies land on the right ticket; response and resolution times become explicit, measured against real working hours, and the right people are told when something needs them. 

Technical Constraints 

Laravel's mail layer with a configurable driver — SMTP or a provider API (Postmark, Mailgun, SES). This is the MailTransport port; a thin interface over it carries the degraded-behaviour contract, and a null adapter must exist so the full test suite passes in CI with no provider reachable. 

Thread preservation is the load-bearing detail and it is easy to get wrong. Set Message-ID on every outbound message and store it. Set In-Reply-To and References from the stored ids of the thread. Story 5.2 correlates on exactly these. Also embed the ticket reference in the subject as a fallback for clients that strip headers. 

Sending is a queued job with backoff. Failure after retries flips messages.delivery_state to failed and surfaces it — it must not be swallowed. 

mail_log owned by the Email module, with a retention setting. 

Credentials encrypted at rest via Laravel's encrypter, never logged, never returned by any endpoint, and write-only through the console. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the WhatsApp, SMS, live-chat and web-form channel sections · the integration console and its connector list · the multi-mailbox configuration. 

Design References 

mockups/screen-admin.html — the Email section, and the credential-as-configured treatment. mockups/direction-e2-workspace.html — the failed-message treatment in the conversation. 

Mockups in Azure DevOps (no Jira needed): 

direction-e2-workspace.html — attached to Story 4.4 (work item #506). 

screen-admin.html — attached to Story 2.3 (work item #498). 

 Dependencies 

4.4 — there must be a message to send. This story precedes 5.2 deliberately: inbound correlation keys on the threading headers this story writes, so building inbound first means building it against headers that do not exist yet. 

Blocked by: Story 4.4 

Blocks: Story 5.2, Story 5.3 

Traceability 

Story ID: 5.1 · Epic: 5 · Covers: FR-032, FR-036, FR-045, FR-046, FR-130, FR-132, FR-133 · NFR-06, NFR-17 · AD-6 · UX-16 

Delivery 

Sprint 5 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
An agent's reply on a ticket is sent as an email to the customer. 

The outbound message sets the mail-threading headers so the customer's reply comes back onto the same ticket. 

On ticket creation, an automatic acknowledgement carrying the ticket reference is sent. Its content and its enablement are configurable. 

Outbound mail is sent in the customer's preferred language, defaulting to English. 

A delivery failure is surfaced on the message in the conversation, and transient failures are retried per a configurable policy. 

Every email exchange is logged — direction, address, status, timing, and error where present — retained per a configurable period. 

An Administrator can send a test email and see a clear success or failure with diagnostic detail. 

An Administrator can enable, disable and configure the email channel without affecting tickets already created through it. 

The mail provider is a configuration choice and can be replaced without changing any channel behaviour. 

If the mail provider is unreachable, the ticket is still created, still assignable and still workable. The failure is queued, retried, and surfaced — it never blocks the UI. 

Mail credentials are never retrievable in plaintext through any UI path — a configured credential renders as configured, never as a masked secret with a reveal control (UX-16).
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

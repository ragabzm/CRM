> **Fetched from azure:** [536](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/536)  
> *Fetched 2026-09-05T06:33:26.599Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 12.3 The ERP adapter and the exchange log  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an Administrator, I want one configurable adapter to whatever ERP we run and a log of every call it makes, so that an integration failure is diagnosable and never a mystery. 

Context 

Epic 12 — Platform and Integrations. The last epic makes the product legible to the rest of the organisation: a branch label to say where work happened, a brand that reaches customers and stops at the staff door, one public API that is the same API the application uses, and one generic ERP adapter with an exchange log that never holds a secret. Nothing in this epic is required for the product to work — with every integration disabled and no provider reachable, the ticketing loop, the portal and the console are fully operational. 

Technical Constraints 

One ErpTransport port with one generic adapter. Every exchange is a queued job with an explicit timeout and bounded retry-with-backoff (AD-6). Nothing here runs in-request. 

integration_exchanges is owned by Integrations, with the retention period as an AD-5 setting and a scheduled prune that is the only deletion path (BR-16). 

Redaction happens at the point of writing the log row, against the configured credential values and a header deny-list — not by a reviewer remembering to omit them. 

Imported customers are written through the Customers module's contract, never by a direct write from Integrations (AD-1). A duplicate on email or phone resolves to the existing customer rather than creating a second record. 

The field map is a typed setting validated at save: an unmappable target is refused when it is configured, not discovered at 3am in a failed job. 

There is no field map to write until a named ERP exists (ASM-13). What ships is the adapter, its configuration surface, its test action and its log — and that is the whole deliverable. 

No outbound webhook subsystem. FR-128 is deferred and needs a consumer that does not exist. 

 UX / Interaction Requirements 

Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the Integrations section: the connector list, the ERP configuration and the integration logs. 

Not in this version, though visible there: named vendor connectors in the list (one generic REST adapter, not a catalogue) · webhooks and the "1 webhook failing" attention state · the organisation mapping row · a field-map preview against live records. 

Dependencies 

Blocked by: 12.2 (the API surface, the auth model and the OpenAPI contract an external system meets), 7.2 (the provider-test path this generalises across channels) 

Traceability 

Story ID: 12.3
Epic: Epic 12: Platform and Integrations
Covers: FR-129, FR-132, FR-133 · BR-16 · AD-1, AD-5, AD-6 · NFR-06, NFR-17 

Delivery 

Sprint 11 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/536/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `536` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
12.3 The ERP adapter and the exchange log
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an Administrator, I want one configurable adapter to whatever ERP we run and a log of every call it makes, so that an integration failure is diagnosable and never a mystery. 

Context 

Epic 12 — Platform and Integrations. The last epic makes the product legible to the rest of the organisation: a branch label to say where work happened, a brand that reaches customers and stops at the staff door, one public API that is the same API the application uses, and one generic ERP adapter with an exchange log that never holds a secret. Nothing in this epic is required for the product to work — with every integration disabled and no provider reachable, the ticketing loop, the portal and the console are fully operational. 

Technical Constraints 

One ErpTransport port with one generic adapter. Every exchange is a queued job with an explicit timeout and bounded retry-with-backoff (AD-6). Nothing here runs in-request. 

integration_exchanges is owned by Integrations, with the retention period as an AD-5 setting and a scheduled prune that is the only deletion path (BR-16). 

Redaction happens at the point of writing the log row, against the configured credential values and a header deny-list — not by a reviewer remembering to omit them. 

Imported customers are written through the Customers module's contract, never by a direct write from Integrations (AD-1). A duplicate on email or phone resolves to the existing customer rather than creating a second record. 

The field map is a typed setting validated at save: an unmappable target is refused when it is configured, not discovered at 3am in a failed job. 

There is no field map to write until a named ERP exists (ASM-13). What ships is the adapter, its configuration surface, its test action and its log — and that is the whole deliverable. 

No outbound webhook subsystem. FR-128 is deferred and needs a consumer that does not exist. 

 UX / Interaction Requirements 

Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the Integrations section: the connector list, the ERP configuration and the integration logs. 

Not in this version, though visible there: named vendor connectors in the list (one generic REST adapter, not a catalogue) · webhooks and the "1 webhook failing" attention state · the organisation mapping row · a field-map preview against live records. 

Dependencies 

Blocked by: 12.2 (the API surface, the auth model and the OpenAPI contract an external system meets), 7.2 (the provider-test path this generalises across channels) 

Traceability 

Story ID: 12.3
Epic: Epic 12: Platform and Integrations
Covers: FR-129, FR-132, FR-133 · BR-16 · AD-1, AD-5, AD-6 · NFR-06, NFR-17 

Delivery 

Sprint 11 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
A single generic REST adapter is configured by endpoint, authentication, field map, sync direction and trigger. 

No vendor-specific adapter, SDK or client exists anywhere in the codebase — no vendor package in the dependency manifest, and a CI check fails the build on one. 

A mapped contact becomes a customer, and nothing maps above it. There is no organisation, no account and no company record to map to, and no field map row may target one. 

Sync direction (import, export or both) and trigger (scheduled or on an event) are configuration, changeable without redeployment and audited. 

An Administrator can test any configured integration or channel provider and see a clear success or failure with diagnostic detail — the endpoint reached, the status, the timing, and the error where present. 

Every external exchange is logged: direction, endpoint or address, status, timing, and error where present. This generalises Story 5.1's mail log; there is one exchange log, not one per integration. 

The log is retained per a configurable retention period, and deletion happens only through that policy, never ad hoc. 

The log never contains a credential in plaintext. Headers and bodies are redacted before the row is written, and a test asserts a configured secret never appears in any log row. 

With the ERP integration disabled and no provider reachable, every ticketing function still works — create, assign, escalate, reply, resolve — and nothing in the loop notices. 

An unreachable ERP retries with backoff and then records a terminal failure in the log, visible to an Administrator. It never blocks a request and never holds the interface. 

A field map referencing a field that no longer exists fails the exchange with a stated reason in the log and does not write a partial customer record. 

Credentials are write-only through the console, encrypted at rest, never logged and never returned by any endpoint. 

The test action runs the same adapter path as a real exchange, so a passing test means a working exchange — not a reachable host.
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

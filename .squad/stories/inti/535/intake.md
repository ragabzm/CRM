> **Fetched from azure:** [535](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/535)  
> *Fetched 2026-09-05T06:33:22.646Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 12.2 The public REST API and client credentials  
**Type:** User Story  
**Status:** New

### Description

User Story 

As another system in this organisation, I want a documented API with a credential an Administrator controls, so that integrating costs a token and a read of the docs, not a project. 

Context 

Epic 12 — Platform and Integrations. The last epic makes the product legible to the rest of the organisation: a branch label to say where work happened, a brand that reaches customers and stops at the staff door, one public API that is the same API the application uses, and one generic ERP adapter with an exchange log that never holds a secret. Nothing in this epic is required for the product to work — with every integration disabled and no provider reachable, the ticketing loop, the portal and the console are fully operational. 

Technical Constraints 

Sanctum personal access tokens with abilities, hashed at rest. The plaintext exists only in the issue response — never in a log line, never in an event payload, never in an audit entry. 

Ability names are capability names, drawn from the PRD §10 matrix, not route names. An ability list that names endpoints becomes a second permission model the moment a route is added. 

The rate limiter is keyed on the token id, with the limit an AD-5 setting per client, and returns the standard rate-limit headers alongside the problem document. 

No credential in a query string, ever — bearer header only, and reject a token presented any other way rather than accepting it quietly. 

The OpenAPI document is generated from the routes and validated in CI, so "documented" is a build artifact and not a wiki page that ages. 

Read endpoints for articles respect the internal/public axis exactly as the interface does: an API client with no staff capability reaches only public Published articles, filtered at the query. 

 UX / Interaction Requirements 

<b>UX-16</b> — The mail password and any provider credential are never retrievable in plaintext through any UI path. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the Integrations section's API clients list and the token-shown-once treatment. 

Not in this version, though visible there: outbound webhooks and the subscription list beside the API clients (FR-128 is deferred) · an API key with a reveal control · per-endpoint scopes. 

Dependencies 

Blocked by: 8.1 (articles are one of the four resources), 4.4 (messages are another), 2.2 (the fixed roles and the capability matrix abilities are drawn from) 

Traceability 

Story ID: 12.2
Epic: Epic 12: Platform and Integrations
Covers: FR-125, FR-126, FR-127 · AD-4, AD-10, AD-14, AD-23 · NFR-06 · UX-16 

Delivery 

Sprint 11 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/535/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `535` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
12.2 The public REST API and client credentials
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As another system in this organisation, I want a documented API with a credential an Administrator controls, so that integrating costs a token and a read of the docs, not a project. 

Context 

Epic 12 — Platform and Integrations. The last epic makes the product legible to the rest of the organisation: a branch label to say where work happened, a brand that reaches customers and stops at the staff door, one public API that is the same API the application uses, and one generic ERP adapter with an exchange log that never holds a secret. Nothing in this epic is required for the product to work — with every integration disabled and no provider reachable, the ticketing loop, the portal and the console are fully operational. 

Technical Constraints 

Sanctum personal access tokens with abilities, hashed at rest. The plaintext exists only in the issue response — never in a log line, never in an event payload, never in an audit entry. 

Ability names are capability names, drawn from the PRD §10 matrix, not route names. An ability list that names endpoints becomes a second permission model the moment a route is added. 

The rate limiter is keyed on the token id, with the limit an AD-5 setting per client, and returns the standard rate-limit headers alongside the problem document. 

No credential in a query string, ever — bearer header only, and reject a token presented any other way rather than accepting it quietly. 

The OpenAPI document is generated from the routes and validated in CI, so "documented" is a build artifact and not a wiki page that ages. 

Read endpoints for articles respect the internal/public axis exactly as the interface does: an API client with no staff capability reaches only public Published articles, filtered at the query. 

 UX / Interaction Requirements 

<b>UX-16</b> — The mail password and any provider credential are never retrievable in plaintext through any UI path. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the Integrations section's API clients list and the token-shown-once treatment. 

Not in this version, though visible there: outbound webhooks and the subscription list beside the API clients (FR-128 is deferred) · an API key with a reveal control · per-endpoint scopes. 

Dependencies 

Blocked by: 8.1 (articles are one of the four resources), 4.4 (messages are another), 2.2 (the fixed roles and the capability matrix abilities are drawn from) 

Traceability 

Story ID: 12.2
Epic: Epic 12: Platform and Integrations
Covers: FR-125, FR-126, FR-127 · AD-4, AD-10, AD-14, AD-23 · NFR-06 · UX-16 

Delivery 

Sprint 11 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
A documented REST API covers customers, tickets, messages and knowledge articles, supporting create, read, update and list. 

It is the same /api/v1 the Next.js client uses. There is no second API, no internal-only endpoint and no privileged bypass — a test proves an API client and the interface reach the same controller and the same command. 

Every API request passes the same validation, the same single capability gate and the same command path as the equivalent interface action — including the version guard on the five contended ticket properties and the Idempotency-Key replay on writes. 

Errors are RFC 9457 application/problem+json with the type URI as the machine code. The API returns codes and data, never a user-facing sentence in either language. 

Authentication is by per-client tokens scoped by abilities to a capability set, issued and revoked by an Administrator. 

Revocation takes effect on the client's next request — not on a cache expiry, not at the next deploy. 

A token is shown once, at issue, and is never retrievable afterwards through any interface, log or API response. There is no reveal control and no masked-secret treatment. 

The version lives in the path and never in a header. 

Rate limiting is per client and configurable. Exceeding it returns the rate-limit problem type with the retry interval — never a bare 429 with an empty body. 

One OpenAPI document covers every module, and the generated TypeScript client is the only contract between frontend and backend. Drift between routes and document fails CI. 

Issuing, scoping and revoking a client is audited with actor and timestamp. 

An API client can reach nothing a role cannot. A token's abilities are a subset of the fixed capability matrix; there is no ability outside it and no ability that names an endpoint rather than a capability. 

Lists paginate identically across every resource, and a client that follows pagination the same way for tickets follows it the same way for articles.
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

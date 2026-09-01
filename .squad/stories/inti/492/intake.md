> **Fetched from azure:** [492](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/492)  
> *Fetched 2026-08-31T17:07:35.369Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 1.1 Two separately deployable applications, the module skeleton, and the API contract  
**Type:** User Story  
**Status:** New

### Description

User Story 

As the team, I want a backend and a frontend that are physically separate and speak only through a generated, typed contract, so that either can be released without the other and no drift is possible between what the API returns and what the UI expects. 

Context 

Epic 1 — Foundation & Bilingual Shell. Two separately deployable applications exist and run; the design system, the bilingual RTL shell, the one formatting layer, the API contract and the responsive/accessibility floor are all in place — so every later epic builds on one foundation instead of six interpretations of it. 

Nothing in this epic is user-facing. It is the floor everything else stands on, and the one part of the plan that cannot be resequenced — RTL in particular cannot be retrofitted into a built UI. 

Technical Constraints 

PHP 8.5, Laravel 13.x. Sanctum is not in the default skeleton — install with php artisan install:api, which also creates routes/api.php. 

PostgreSQL 18.6. Chosen over MySQL because customer and ticket search use trigram and tsvector indexes (Story 3.1, 4.5) rather than a search engine. 

Node.js 24 LTS, Next.js 16.3.x — use the security release dated 2026-08-26 or later, never 16.3.2. React 19.2.x. TypeScript 5.x with strict: true. 

OpenAPI generation: any generator that reads Laravel routes and form requests. Client generation: any typed TS generator. Both run in CI. 

No Redis, no search engine, no message broker. If a story appears to need one, that is a signal to simplify the story, not to add infrastructure. 

Docker Compose for local; the three processes are separate services. 

 Design References 

UX Design Not Required. No UI in this story. 

Dependencies 

— (first story in the plan) 

Blocks: Story 1.2 

Traceability 

Story ID: 1.1 · Epic: 1 · Covers: the structural half of NFR-15, NFR-16, NFR-17 · AD-1, AD-2, AD-10, AD-11, AD-20 

Delivery 

Sprint 1 · Priority 1

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/492/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `492` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
1.1 Two separately deployable applications, the module skeleton, and the API contract
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As the team, I want a backend and a frontend that are physically separate and speak only through a generated, typed contract, so that either can be released without the other and no drift is possible between what the API returns and what the UI expects. 

Context 

Epic 1 — Foundation & Bilingual Shell. Two separately deployable applications exist and run; the design system, the bilingual RTL shell, the one formatting layer, the API contract and the responsive/accessibility floor are all in place — so every later epic builds on one foundation instead of six interpretations of it. 

Nothing in this epic is user-facing. It is the floor everything else stands on, and the one part of the plan that cannot be resequenced — RTL in particular cannot be retrofitted into a built UI. 

Technical Constraints 

PHP 8.5, Laravel 13.x. Sanctum is not in the default skeleton — install with php artisan install:api, which also creates routes/api.php. 

PostgreSQL 18.6. Chosen over MySQL because customer and ticket search use trigram and tsvector indexes (Story 3.1, 4.5) rather than a search engine. 

Node.js 24 LTS, Next.js 16.3.x — use the security release dated 2026-08-26 or later, never 16.3.2. React 19.2.x. TypeScript 5.x with strict: true. 

OpenAPI generation: any generator that reads Laravel routes and form requests. Client generation: any typed TS generator. Both run in CI. 

No Redis, no search engine, no message broker. If a story appears to need one, that is a signal to simplify the story, not to add infrastructure. 

Docker Compose for local; the three processes are separate services. 

 Design References 

UX Design Not Required. No UI in this story. 

Dependencies 

— (first story in the plan) 

Blocks: Story 1.2 

Traceability 

Story ID: 1.1 · Epic: 1 · Covers: the structural half of NFR-15, NFR-16, NFR-17 · AD-1, AD-2, AD-10, AD-11, AD-20 

Delivery 

Sprint 1 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
backend/ and frontend/ are separate directories with separate dependency manifests, separate CI pipelines and separate container images. The repository root contains no application file. 

Nothing in frontend/ imports from backend/ or the reverse. A test proves it. 

The seven modules exist as directories under backend/app/Modules/ — Platform, Security, Customers, Tickets, Sla, Email, Portal — each with Domain/, Http/, Policies/, Database/, Jobs/, Contracts/. 

A lint or test rule fails the build when a module imports another module's Eloquent model class, or when Contracts/ imports anything outside itself. 

A dependency test fails the build when a module calls a module in a higher or equal tier: Platform(T0) → Security(T1) → Customers(T2) → Tickets(T3) → {Sla, Email, Portal}(T4). The three T4 modules must not call each other. 

backend/openapi.yaml exists, is generated from the routes, and CI fails if it is stale. 

frontend/lib/api/ holds a TypeScript client generated from that document. It is never hand-edited; CI fails if it differs from a fresh generation. 

Every error response is RFC 9457 application/problem+json where the type URI is a stable machine code shaped module.condition. One shared exception handler produces it; no controller writes an error body. 

Write endpoints accept an Idempotency-Key header; a repeat key replays the stored response instead of acting twice. 

Structured JSON logging carries request id, actor type and id, module, and ticket id where present. 

Three runtime processes are defined and start in every environment: web, queue worker, scheduler. The queue uses the database driver.
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

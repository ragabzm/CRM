> **Fetched from azure:** [526](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/526)  
> *Fetched 2026-09-05T06:32:27.132Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 9.1 The AI connector, capability gating and the sanitiser  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an Administrator responsible for what leaves this building, I want one governed path to any AI provider and one switch per capability, so that AI is assistive and never load-bearing. 

Context 

Epic 9 — AI Assist. One rule governs this epic and outranks every requirement in it: AI proposes, a person decides. The connector lands first and everything else hangs off it — one port, one sanitiser, five independently switchable capabilities, and a product that runs completely with every one of them off. Nothing here holds a command, nothing here sends to a customer unattended, and the single exception is the chatbot inside its own session, answering from published articles and handing off the moment it cannot. 

Technical Constraints 

The Ai module owns the port, the sanitiser, the capability settings and the label. Its interface declares the degraded return value for every capability — no summary, no suggestions, no proposed category, immediate chatbot handoff — because that contract is a product decision (BR-18) and no vendor SDK defines it. 

Redaction is deterministic and tested against a fixture corpus in both languages. Arabic names are the hard case and the corpus must contain them; a redactor tuned only on Latin script will leak on the first Arabic ticket. 

No vector store, no embeddings index and no retrieval infrastructure (NFR-16). Where a capability needs article context, Knowledge's PostgreSQL search supplies the candidates. 

A per-request content cap: a request that would exceed it sends less, never more, and never silently truncates the middle of a customer's sentence without saying so. 

Responses are returned whole. There is no token streaming, which would need a held-open connection and there is no realtime transport (NFR-16, AD-21). 

Settings are typed rows in the registry (AD-5) — provider, model, per-capability enable, external-transmission mode, timeout. env() appears only in config/ at boot. 

This story ships no user-visible AI feature. Its deliverable is the port, the sanitiser, the switches, the label and the null adapter. Landing it before any capability is the point. 

 UX / Interaction Requirements 

Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the AI section: provider selection and the per-capability enable/disable rows. 

Not in this version, though visible there: the confidence threshold slider on auto-categorisation (nothing is applied automatically, so there is no threshold to set — Story 9.2) · usage and cost dashboards · per-role AI permissions. 

Dependencies 

Blocked by: 2.3 — the settings registry and the console section every switch here lives in. 

Traceability 

Story ID: 9.1
Epic: Epic 9: AI Assist
Covers: FR-131, FR-089 · NFR-09 · BR-18 · AD-5, AD-6, AD-30 

Delivery 

Sprint 10 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/526/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `526` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
9.1 The AI connector, capability gating and the sanitiser
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an Administrator responsible for what leaves this building, I want one governed path to any AI provider and one switch per capability, so that AI is assistive and never load-bearing. 

Context 

Epic 9 — AI Assist. One rule governs this epic and outranks every requirement in it: AI proposes, a person decides. The connector lands first and everything else hangs off it — one port, one sanitiser, five independently switchable capabilities, and a product that runs completely with every one of them off. Nothing here holds a command, nothing here sends to a customer unattended, and the single exception is the chatbot inside its own session, answering from published articles and handing off the moment it cannot. 

Technical Constraints 

The Ai module owns the port, the sanitiser, the capability settings and the label. Its interface declares the degraded return value for every capability — no summary, no suggestions, no proposed category, immediate chatbot handoff — because that contract is a product decision (BR-18) and no vendor SDK defines it. 

Redaction is deterministic and tested against a fixture corpus in both languages. Arabic names are the hard case and the corpus must contain them; a redactor tuned only on Latin script will leak on the first Arabic ticket. 

No vector store, no embeddings index and no retrieval infrastructure (NFR-16). Where a capability needs article context, Knowledge's PostgreSQL search supplies the candidates. 

A per-request content cap: a request that would exceed it sends less, never more, and never silently truncates the middle of a customer's sentence without saying so. 

Responses are returned whole. There is no token streaming, which would need a held-open connection and there is no realtime transport (NFR-16, AD-21). 

Settings are typed rows in the registry (AD-5) — provider, model, per-capability enable, external-transmission mode, timeout. env() appears only in config/ at boot. 

This story ships no user-visible AI feature. Its deliverable is the port, the sanitiser, the switches, the label and the null adapter. Landing it before any capability is the point. 

 UX / Interaction Requirements 

Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the AI section: provider selection and the per-capability enable/disable rows. 

Not in this version, though visible there: the confidence threshold slider on auto-categorisation (nothing is applied automatically, so there is no threshold to set — Story 9.2) · usage and cost dashboards · per-role AI permissions. 

Dependencies 

Blocked by: 2.3 — the settings registry and the console section every switch here lives in. 

Traceability 

Story ID: 9.1
Epic: Epic 9: AI Assist
Covers: FR-131, FR-089 · NFR-09 · BR-18 · AD-5, AD-6, AD-30 

Delivery 

Sprint 10 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
Exactly one AiProvider port exists. No feature calls a provider directly, and that is verifiable by tracing call paths — a CI check fails the build on a provider SDK import outside the Ai module. 

Provider and model are settings, changeable without redeployment and audited. Swapping either changes no behaviour on any surface. 

Each of the five capabilities — summary, suggested reply, category proposal, suggested articles, chatbot — is independently enabled or disabled as a setting, not a build flag, taking effect without redeployment. 

With every AI capability disabled, the full regression suite for the other ten modules passes unchanged. That test is the definition of assistive. 

When the provider is unreachable or times out, the AI surface is absent — no error, no toast, no retry control, no empty panel, no skeleton that never resolves. The screen is complete without it. 

Every AI call runs in-request behind a hard timeout, because a person is waiting for it. It never becomes a queued job and never holds a request open past the timeout. 

The sanitiser lives inside the port and cannot be bypassed. Every call passes it; there is no path around it and no flag that disables it. 

Never transmitted, by any path: credentials, secrets, API keys, tokens, and attachments or their content. A test asserts a configured secret and an uploaded file's bytes never appear in an outbound payload. 

Redacted: names, email addresses, phone numbers, postal addresses and customer references. Content that cannot be confidently redacted is omitted rather than sent. 

Each capability sends the minimum content it needs and nothing beside it. 

An Administrator-visible setting governs external transmission and has a mode that disables it entirely. In that mode nothing leaves the deployment and every capability degrades exactly as in criterion 5. 

A null adapter ships and no test depends on a live provider. 

The AI-generated label is built here, once, as a shared component. No capability invents its own labelling, and no AI artefact reaches a human without it.
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

> **Fetched from azure:** [527](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/527)  
> *Fetched 2026-09-05T06:32:37.262Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 9.2 The three in-ticket assists  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an agent, I want the machine to draft, summarise and suggest while I stay the one who decides, so that I answer faster without anything reaching a customer that I did not read. 

Context 

Epic 9 — AI Assist. One rule governs this epic and outranks every requirement in it: AI proposes, a person decides. The connector lands first and everything else hangs off it — one port, one sanitiser, five independently switchable capabilities, and a product that runs completely with every one of them off. Nothing here holds a command, nothing here sends to a customer unattended, and the single exception is the chatbot inside its own session, answering from published articles and handing off the moment it cannot. 

Technical Constraints 

Every call goes through the Story 9.1 port. No module in this story holds a provider client, a model name or an API key. 

Story 4.4 stated that AiSuggestionPanel does not exist; this story creates it. The conversation still has three semantic treatments — customer message, agent message, internal note. An AI draft is never a fourth treatment: it lives in the panel and in the composer, never in the thread. 

The category proposal is rendered beside the field, not inside it. A pre-filled field is an application, and applications are what this story refuses. 

Article ranking is a two-step: Knowledge's PostgreSQL search produces candidates, the port ranks them. That order is what keeps suggestions inside the knowledge base and keeps the vector store out. 

The summary is computed per request and not stored, so a stale summary of a moved-on conversation cannot exist. Regenerating is a fresh call, not a cache invalidation. 

No AI capability is an automation (AD-18). None of them holds a Tickets command; every one returns a proposal to a person. 

 UX / Interaction Requirements 

<b>UX-17</b> — A stale ticket write is refused, not applied. The user is told in one plain sentence that the ticket changed and is offered a Reload — there is no merge UI, no diff and no keep-mine/keep-theirs picker. Appending a message or a note is never refused this way. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/direction-e2-workspace.html — the AI panel beside the conversation, its placement in the rail and its relationship to the composer. 

Not in this version, though visible there: the AI-draft conversation treatment (three treatments, not four) · automatic summary above a message threshold · the confidence threshold and auto-apply on the category, drawn in screen-admin.html · the link and merge actions in the same rail. 

Dependencies 

Blocked by: 9.1, 8.2 (the article corpus and the search that feeds suggestions), 4.4 (the ticket screen, the composer and the property rail) 

Traceability 

Story ID: 9.2
Epic: Epic 9: AI Assist
Covers: FR-082, FR-084, FR-085, FR-086, FR-088 · BR-18 · AD-18, AD-23, AD-30 · UX-17 

Delivery 

Sprint 11 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/527/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `527` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
9.2 The three in-ticket assists
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an agent, I want the machine to draft, summarise and suggest while I stay the one who decides, so that I answer faster without anything reaching a customer that I did not read. 

Context 

Epic 9 — AI Assist. One rule governs this epic and outranks every requirement in it: AI proposes, a person decides. The connector lands first and everything else hangs off it — one port, one sanitiser, five independently switchable capabilities, and a product that runs completely with every one of them off. Nothing here holds a command, nothing here sends to a customer unattended, and the single exception is the chatbot inside its own session, answering from published articles and handing off the moment it cannot. 

Technical Constraints 

Every call goes through the Story 9.1 port. No module in this story holds a provider client, a model name or an API key. 

Story 4.4 stated that AiSuggestionPanel does not exist; this story creates it. The conversation still has three semantic treatments — customer message, agent message, internal note. An AI draft is never a fourth treatment: it lives in the panel and in the composer, never in the thread. 

The category proposal is rendered beside the field, not inside it. A pre-filled field is an application, and applications are what this story refuses. 

Article ranking is a two-step: Knowledge's PostgreSQL search produces candidates, the port ranks them. That order is what keeps suggestions inside the knowledge base and keeps the vector store out. 

The summary is computed per request and not stored, so a stale summary of a moved-on conversation cannot exist. Regenerating is a fresh call, not a cache invalidation. 

No AI capability is an automation (AD-18). None of them holds a Tickets command; every one returns a proposal to a person. 

 UX / Interaction Requirements 

<b>UX-17</b> — A stale ticket write is refused, not applied. The user is told in one plain sentence that the ticket changed and is offered a Reload — there is no merge UI, no diff and no keep-mine/keep-theirs picker. Appending a message or a note is never refused this way. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/direction-e2-workspace.html — the AI panel beside the conversation, its placement in the rail and its relationship to the composer. 

Not in this version, though visible there: the AI-draft conversation treatment (three treatments, not four) · automatic summary above a message threshold · the confidence threshold and auto-apply on the category, drawn in screen-admin.html · the link and merge actions in the same rail. 

Dependencies 

Blocked by: 9.1, 8.2 (the article corpus and the search that feeds suggestions), 4.4 (the ticket screen, the composer and the property rail) 

Traceability 

Story ID: 9.2
Epic: Epic 9: AI Assist
Covers: FR-082, FR-084, FR-085, FR-086, FR-088 · BR-18 · AD-18, AD-23, AD-30 · UX-17 

Delivery 

Sprint 11 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
A summary of the ticket's conversation is generated on demand only, when an agent asks for it. There is no message-count threshold, no automatic generation and no setting that would create one. 

The summary is labelled AI-generated and is never written into the conversation or the history as though a person had said it. 

A suggested reply is drawn from the ticket thread and the knowledge base and is inserted into the composer, where it is fully editable before sending. 

No code path sends a suggested reply automatically. There is no send-on-accept, no auto-send setting and no scheduled sender; a test asserts that no command sends a customer-facing message with an AI actor. 

A proposed category appears beside the category field for a person to confirm or replace. It is never applied automatically. 

Because nothing is applied automatically, there is no confidence threshold anywhere — no slider, no score gating an action, no "apply above N" setting. 

Confirming a proposal is an ordinary category change through the Story 4.1 command path, carrying the version, attributed to the person who confirmed it — never to System and never to the AI. 

Suggested articles are ranked knowledge-base articles shown beside the ticket, drawn only from the knowledge base and from nothing else, and insertable into a reply in one action, reusing Story 8.2's insert. 

Staff suggestions may include internal articles; nothing in this story reaches a customer surface. 

Every artefact carries the Story 9.1 AI-generated label wherever a human sees it. 

Disabling any one capability removes its surface and leaves the others untouched; with all of them off the ticket screen is complete, not gap-toothed. 

Provider unreachable or timed out ⇒ the surface is absent, with no error and no retry control. 

Each assist sends the minimum: the summary sends the thread; the category proposal sends subject and description only; article ranking sends the ticket text and the candidate articles that Knowledge's search returned — no vector store and no embedding index exists.
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

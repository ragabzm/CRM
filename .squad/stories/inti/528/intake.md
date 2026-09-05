> **Fetched from azure:** [528](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/528)  
> *Fetched 2026-09-05T06:32:41.235Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 9.3 The chatbot and its handoff  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a customer with a question we already answered, I want an immediate answer with the article it came from, so that I get help at once — and a person the moment the answer is not good enough. 

Context 

Epic 9 — AI Assist. One rule governs this epic and outranks every requirement in it: AI proposes, a person decides. The connector lands first and everything else hangs off it — one port, one sanitiser, five independently switchable capabilities, and a product that runs completely with every one of them off. Nothing here holds a command, nothing here sends to a customer unattended, and the single exception is the chatbot inside its own session, answering from published articles and handing off the moment it cannot. 

Technical Constraints 

The retrieval step is Knowledge's PostgreSQL search restricted to public Published, executed server-side. The widget never queries articles and never receives an article that was not cited. 

Handoff is a named Tickets command with a System(chatbot_handoff) actor, reusing the Story 7.3 persistence path — the chatbot does not write ticket rows and does not invent a second way a chat becomes a ticket. 

The "cannot answer" condition is explicit and conservative: no citable public article, or the port's degraded return. When in doubt, hand off — an unnecessary handoff costs an agent a minute; a confident wrong answer costs the customer. 

The cited article link is the same stable id-keyed URL Story 8.2 builds, so a cited article that is later retitled still resolves from an old transcript. 

Do not stream turns. A held-open connection is exactly what AD-21 refuses, and the polling interval already governs this surface. 

 UX / Interaction Requirements 

Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-portal.html — the chat conversation showing "Answered from our help centre" with its cited article, and "Getting a person for you" with the transcript being carried across. 

Not in this version, though visible there: typing indicators and read receipts · presence · a bot that resolves or closes a request. 

Dependencies 

Blocked by: 9.1, 8.2 (the corpus it answers from), 7.3 (the widget, the token and the waiting list) 

Traceability 

Story ID: 9.3
Epic: Epic 9: AI Assist
Covers: FR-090, FR-091 · BR-18, BR-22 · AD-6, AD-13, AD-18, AD-21, AD-30 

Delivery 

Sprint 11 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/528/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `528` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
9.3 The chatbot and its handoff
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a customer with a question we already answered, I want an immediate answer with the article it came from, so that I get help at once — and a person the moment the answer is not good enough. 

Context 

Epic 9 — AI Assist. One rule governs this epic and outranks every requirement in it: AI proposes, a person decides. The connector lands first and everything else hangs off it — one port, one sanitiser, five independently switchable capabilities, and a product that runs completely with every one of them off. Nothing here holds a command, nothing here sends to a customer unattended, and the single exception is the chatbot inside its own session, answering from published articles and handing off the moment it cannot. 

Technical Constraints 

The retrieval step is Knowledge's PostgreSQL search restricted to public Published, executed server-side. The widget never queries articles and never receives an article that was not cited. 

Handoff is a named Tickets command with a System(chatbot_handoff) actor, reusing the Story 7.3 persistence path — the chatbot does not write ticket rows and does not invent a second way a chat becomes a ticket. 

The "cannot answer" condition is explicit and conservative: no citable public article, or the port's degraded return. When in doubt, hand off — an unnecessary handoff costs an agent a minute; a confident wrong answer costs the customer. 

The cited article link is the same stable id-keyed URL Story 8.2 builds, so a cited article that is later retitled still resolves from an old transcript. 

Do not stream turns. A held-open connection is exactly what AD-21 refuses, and the polling interval already governs this surface. 

 UX / Interaction Requirements 

Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-portal.html — the chat conversation showing "Answered from our help centre" with its cited article, and "Getting a person for you" with the transcript being carried across. 

Not in this version, though visible there: typing indicators and read receipts · presence · a bot that resolves or closes a request. 

Dependencies 

Blocked by: 9.1, 8.2 (the corpus it answers from), 7.3 (the widget, the token and the waiting list) 

Traceability 

Story ID: 9.3
Epic: Epic 9: AI Assist
Covers: FR-090, FR-091 · BR-18, BR-22 · AD-6, AD-13, AD-18, AD-21, AD-30 

Delivery 

Sprint 11 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
The chatbot answers on the public site and the portal, inside the Story 7.3 widget — the same artifact, the same anonymous token, the same polling. It is not a second surface. 

It answers only from public Published knowledge articles, in the reader's language with fallback, and cites the article it drew on with a link the reader can open. 

Internal, Draft and Archived articles are excluded at query level. Verified by direct API call with an internal article's id, and by a test asserting no chatbot answer contains text from a non-public article. 

It never presents itself as a person. Every turn it produces is labelled as an assistant, and the label persists in the transcript that becomes the ticket. 

It hands off to a human — creating a ticket, or updating the conversation's existing ticket, carrying the full transcript — when it cannot answer, when the customer asks for a person, or when the provider fails or times out. 

A handoff caused by provider failure is immediate and says nothing about the provider. The customer is told a person is being fetched, and is not shown an error, a retry or a stack of apologies. 

On handoff, the conversation joins the Story 7.3 waiting list for chat-capable staff, carrying everything already said, so the customer never repeats themselves. 

A visitor who leaves during or after a handoff still leaves a ticket with the full transcript behind. 

The chatbot holds no command: it never changes a ticket's status, priority, category or assignee, never sends an email and never resolves or closes anything. 

Everything the chatbot sends leaves through the Story 9.1 port and its sanitiser — there is no direct provider call on the widget's path, and the widget never holds a provider credential. 

Disabled as a capability, the chat surface offers a person directly, with no error and no mention of a switched-off feature. 

Each turn runs in-request behind a hard timeout; a timeout is a handoff, not a retry loop and not a spinner that never ends. 

Polling, token scope, RTL and mobile behaviour are inherited from Story 7.3 — no persistent connection is introduced here.
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

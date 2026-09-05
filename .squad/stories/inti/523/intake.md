> **Fetched from azure:** [523](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/523)  
> *Fetched 2026-09-05T06:31:50.503Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 7.3 Live chat  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a customer with a quick question, I want to ask it in a chat on the website, so that I get an answer without composing an email — and the answer is still on record afterwards. 

Context 

Epic 7 — Multi-Channel Intake. Email stopped being the only way in. This epic generalises Story 5.2's inbound path into one channel-agnostic pipeline — one idempotency key, one identification step, one correlation order, one quarantine, one department resolution — and then hangs four more transports off it: the public web form, WhatsApp, SMS and live chat. A channel supplies a transport and an identifier. It never supplies a second kind of ticket, a second history or a second correlation rule, and at the end of this epic that claim is enforced by structure rather than by discipline. 

Technical Constraints 

The widget is the one exception to AD-13's single frontend artifact. Build it from the same repository as a second, tiny target that consumes the generated design-token stylesheet — and nothing else from the application bundle. There is no fourth artifact. 

Polling rides AD-29's existing per-query refetchInterval with a shorter value. Do not build a lib/sync/ layer, a shared cursor, a sync?since= endpoint or a healthy | degraded | offline state machine — that layer is retired (AD-26) and this story does not bring it back. 

Taking a conversation is a conditional update — WHERE taken_by IS NULL — so two agents clicking at once resolves to one winner in the database, not in the UI. 

Abandonment is an idempotent scheduled sweep on inactivity: it writes the ticket once, running it twice produces nothing new. 

The transcript is persisted as ordinary messages rows on the ticket, not as a blob — the ticket history stays uniform and the conversation is searchable like any other. 

Embedding is constrained by an origin allow-list and a frame-ancestors policy; a widget loaded from an unlisted origin gets no token. 

The waiting list is polled on the same short interval as an open conversation. It is a list, not a queue: no priority, no routing, no assignment strategy. 

 UX / Interaction Requirements 

<b>UX-12</b> — Every agent function is completable on a 390px mobile browser, and no data is silently truncated at any band. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-portal.html — the customer-side conversation, the message states (sending · sent · failed) and the stated absence of typing indicators and read receipts. mockups/screen-home.html — the live-chat row in the counts and attention queue. 

Not in this version, though visible there: the chat queue named on the channel row in screen-admin.html · agent availability and presence state · typing indicators and read receipts · routing rules. 

Dependencies 

Blocked by: 7.1 

Traceability 

Story ID: 7.3
Epic: Epic 7: Multi-Channel Intake
Covers: FR-037, FR-038, FR-039 · R-07 · BR-22 · AD-13, AD-14, AD-21, AD-29 · UX-12 

Delivery 

Sprint 10 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/523/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `523` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
7.3 Live chat
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a customer with a quick question, I want to ask it in a chat on the website, so that I get an answer without composing an email — and the answer is still on record afterwards. 

Context 

Epic 7 — Multi-Channel Intake. Email stopped being the only way in. This epic generalises Story 5.2's inbound path into one channel-agnostic pipeline — one idempotency key, one identification step, one correlation order, one quarantine, one department resolution — and then hangs four more transports off it: the public web form, WhatsApp, SMS and live chat. A channel supplies a transport and an identifier. It never supplies a second kind of ticket, a second history or a second correlation rule, and at the end of this epic that claim is enforced by structure rather than by discipline. 

Technical Constraints 

The widget is the one exception to AD-13's single frontend artifact. Build it from the same repository as a second, tiny target that consumes the generated design-token stylesheet — and nothing else from the application bundle. There is no fourth artifact. 

Polling rides AD-29's existing per-query refetchInterval with a shorter value. Do not build a lib/sync/ layer, a shared cursor, a sync?since= endpoint or a healthy | degraded | offline state machine — that layer is retired (AD-26) and this story does not bring it back. 

Taking a conversation is a conditional update — WHERE taken_by IS NULL — so two agents clicking at once resolves to one winner in the database, not in the UI. 

Abandonment is an idempotent scheduled sweep on inactivity: it writes the ticket once, running it twice produces nothing new. 

The transcript is persisted as ordinary messages rows on the ticket, not as a blob — the ticket history stays uniform and the conversation is searchable like any other. 

Embedding is constrained by an origin allow-list and a frame-ancestors policy; a widget loaded from an unlisted origin gets no token. 

The waiting list is polled on the same short interval as an open conversation. It is a list, not a queue: no priority, no routing, no assignment strategy. 

 UX / Interaction Requirements 

<b>UX-12</b> — Every agent function is completable on a 390px mobile browser, and no data is silently truncated at any band. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-portal.html — the customer-side conversation, the message states (sending · sent · failed) and the stated absence of typing indicators and read receipts. mockups/screen-home.html — the live-chat row in the counts and attention queue. 

Not in this version, though visible there: the chat queue named on the channel row in screen-admin.html · agent availability and presence state · typing indicators and read receipts · routing rules. 

Dependencies 

Blocked by: 7.1 

Traceability 

Story ID: 7.3
Epic: Epic 7: Multi-Channel Intake
Covers: FR-037, FR-038, FR-039 · R-07 · BR-22 · AD-13, AD-14, AD-21, AD-29 · UX-12 

Delivery 

Sprint 10 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
The chat widget is an embeddable loader script plus an iframe, built as a separate artifact from the application, embeddable on a third-party website and on the customer portal. It inherits design tokens only, never the component tree, and can reach no endpoint outside the chat surface. 

A visitor opens a conversation without an account. The widget authenticates with a short-lived anonymous token scoped to exactly one conversation: it grants no capability, resolves to no user, and expires with the conversation. 

The token is held by the widget's own iframe. No token is written to localStorage or sessionStorage, and none is handled by application JavaScript. 

Waiting conversations are listed to staff holding the chat capability, and one of them takes a conversation. A taken conversation leaves the waiting list for everyone else, and a second taker is refused with a stated reason rather than silently overwritten. 

Messages flow both ways by polling on a short interval — an AD-5 setting — while every other screen keeps the long one. 

There is no persistent connection: no WebSocket, no SSE, no long-poll held open, and therefore no broker and no second runtime. A message can take up to one interval to appear, and that is the accepted trade, stated in the widget rather than hidden. 

The conversation persists as a ticket carrying the full transcript when it ends and when it is abandoned; an abandoned conversation is detected by inactivity and still produces a ticket, with the transcript intact. 

Chat enters the Story 7.1 pipeline like every other channel: the customer is identified by the chat session and any identifier the visitor supplies, the department is resolved by the same ladder, and every message carries channel chat. 

The widget is fully usable on a mobile browser at 390px and complete in Arabic RTL. 

Chat is enabled and disabled as a channel like any other. Disabled, the widget does not offer a conversation and says so — it does not render an empty box or fail silently. 

Absent by design, not deferred: agent availability state, presence, queue management, routing, typing indicators and read receipts. No column, no setting, no endpoint and no UI affordance exists for any of them, and the widget states why there is no typing indicator rather than leaving a gap where one should be. 

A visitor who reloads the page inside the token's life rejoins the same conversation; one who returns after it expires starts a new one, and the previous conversation is already a ticket.
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

> **Fetched from azure:** [525](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/525)  
> *Fetched 2026-09-05T06:32:22.393Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 8.2 Knowledge search and the two reading surfaces  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an agent mid-reply and as a customer with a question, I want to find the right article in seconds, so that the answer we already wrote is the answer that gets used. 

Context 

Epic 8 — Knowledge Base. Support answers the same twenty questions all year. This epic gives those answers one home: articles with a lifecycle, a flat category list, an internal/public axis and both languages, plus one search — native to PostgreSQL, with no engine and no index outside the database — reaching the two places it matters. An agent searches without leaving the ticket and drops a link into a reply in one action; a customer searches the portal help centre and sees only what was published for them, excluded at query level rather than hidden in the interface. 

Technical Constraints 

One tsvector per translation row, with the locale-appropriate text search configuration, maintained as a generated column and indexed with GIN. Plus a pg_trgm GIN index over title and body — that index is what makes Arabic work and what makes partial-input matching work, and removing it silently breaks both. 

Weight the title above the body in the rank. An article titled Refunds must beat one that mentions refunds in paragraph nine. 

The in-ticket search is a Layer-C composition on the ticket screen reading Knowledge's published contract. Tickets never queries article tables and Knowledge never queries ticket tables (AD-1). 

Insert-link writes a stable article URL keyed on article id, never on a slug, so a retitled article's link in a two-year-old reply still resolves. 

The portal has its own serialiser and its own query, filtered at the query, not the staff resource with fields stripped in the frontend. Requirement 8 must hold at the API. 

Index and query cost matter more than they look: search runs on every keystroke-debounced request from inside a ticket, on the busiest screen in the product. 

 UX / Interaction Requirements 

<b>UX-06</b> — An empty result and a forbidden result are visually unmistakable, and no forbidden surface prints a numeral. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/direction-e2-workspace.html — the knowledge panel beside the conversation and the insert affordance. mockups/screen-portal.html — the Help centre destination, its search-first layout and the article surface. 

Not in this version, though visible there: per-article view counts and helpfulness voting · the "related articles" rail driven by anything other than search (the ranked suggestions in Story 9.2 are the only other source, and they draw on this same search) · saved searches. 

Dependencies 

Blocked by: 8.1, 4.4 (the ticket screen and the composer), 6.2 (the portal surface the help centre joins) 

Traceability 

Story ID: 8.2
Epic: Epic 8: Knowledge Base
Covers: FR-078, FR-079, FR-080, FR-099 · AD-1, AD-4, AD-9 · NFR-02, NFR-16 · UX-06 

Delivery 

Sprint 9 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/525/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `525` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
8.2 Knowledge search and the two reading surfaces
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an agent mid-reply and as a customer with a question, I want to find the right article in seconds, so that the answer we already wrote is the answer that gets used. 

Context 

Epic 8 — Knowledge Base. Support answers the same twenty questions all year. This epic gives those answers one home: articles with a lifecycle, a flat category list, an internal/public axis and both languages, plus one search — native to PostgreSQL, with no engine and no index outside the database — reaching the two places it matters. An agent searches without leaving the ticket and drops a link into a reply in one action; a customer searches the portal help centre and sees only what was published for them, excluded at query level rather than hidden in the interface. 

Technical Constraints 

One tsvector per translation row, with the locale-appropriate text search configuration, maintained as a generated column and indexed with GIN. Plus a pg_trgm GIN index over title and body — that index is what makes Arabic work and what makes partial-input matching work, and removing it silently breaks both. 

Weight the title above the body in the rank. An article titled Refunds must beat one that mentions refunds in paragraph nine. 

The in-ticket search is a Layer-C composition on the ticket screen reading Knowledge's published contract. Tickets never queries article tables and Knowledge never queries ticket tables (AD-1). 

Insert-link writes a stable article URL keyed on article id, never on a slug, so a retitled article's link in a two-year-old reply still resolves. 

The portal has its own serialiser and its own query, filtered at the query, not the staff resource with fields stripped in the frontend. Requirement 8 must hold at the API. 

Index and query cost matter more than they look: search runs on every keystroke-debounced request from inside a ticket, on the busiest screen in the product. 

 UX / Interaction Requirements 

<b>UX-06</b> — An empty result and a forbidden result are visually unmistakable, and no forbidden surface prints a numeral. 

 Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/direction-e2-workspace.html — the knowledge panel beside the conversation and the insert affordance. mockups/screen-portal.html — the Help centre destination, its search-first layout and the article surface. 

Not in this version, though visible there: per-article view counts and helpfulness voting · the "related articles" rail driven by anything other than search (the ranked suggestions in Story 9.2 are the only other source, and they draw on this same search) · saved searches. 

Dependencies 

Blocked by: 8.1, 4.4 (the ticket screen and the composer), 6.2 (the portal surface the help centre joins) 

Traceability 

Story ID: 8.2
Epic: Epic 8: Knowledge Base
Covers: FR-078, FR-079, FR-080, FR-099 · AD-1, AD-4, AD-9 · NFR-02, NFR-16 · UX-06 

Delivery 

Sprint 9 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
Keyword search runs across article titles, bodies and tags, ranked by relevance, implemented natively in PostgreSQL with tsvector and a GIN index. No search engine and no index outside the database. 

Arabic pairs the stemmer with trigram matching, and the trigram half is required, not optional. A test issues ordinary Arabic queries that return empty on arabic_stem alone and asserts the expected articles come back. 

Search is owned by Knowledge. There is no cross-module search endpoint, no federated query and no search service. 

An agent searches from inside a ticket without leaving it: results open in place, the composer draft survives, and the ticket does not unmount or lose scroll position. 

An agent inserts a link to an article into a reply in one action — one control, no intermediate dialog, no copy-and-paste of a URL. 

A link to an article later archived still resolves. Staff see the archived article with its state stated; a customer following a link to an article since archived gets a stated message and never the body of a non-public or non-Published article. 

The portal help centre opens on search, with browse by category second — search is the surface, the category list is the fallback. 

Portal results contain only public Published articles in the reader's language with fallback to the default. The exclusion is at query level, not hidden in the interface — verified by calling the portal API directly with an internal article's id and getting a not-found, not a filtered page. 

Staff search returns internal and public articles; nothing on a customer surface can reach an internal article by any path, including a guessed id. 

Search returns within 2 seconds at the expected article volume, on both surfaces. 

Both surfaces are complete in Arabic RTL and usable on a mobile browser. 

An empty result is visually unmistakable and offers the next action — browse, or raise a request — rather than an empty panel.
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

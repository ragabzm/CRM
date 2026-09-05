> **Fetched from azure:** [524](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/524)  
> *Fetched 2026-09-05T06:32:08.602Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 8.1 Articles, categories, publishing and both languages  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a support team, I want one place where our answers live and a clear state that says whether an answer is ready, so that nobody pastes last year's refund policy into a reply. 

Context 

Epic 8 — Knowledge Base. Support answers the same twenty questions all year. This epic gives those answers one home: articles with a lifecycle, a flat category list, an internal/public axis and both languages, plus one search — native to PostgreSQL, with no engine and no index outside the database — reaching the two places it matters. An agent searches without leaving the ticket and drops a link into a reply in one action; a customer searches the portal help centre and sees only what was published for them, excluded at query level rather than hidden in the interface. 

Technical Constraints 

articles and article_translations (article_id, locale, title, body) are owned by a Knowledge module. Language versions are rows, not columns — a title_ar column makes a third language a migration and makes an Arabic-only article awkward. 

The never-published guard is a has_been_published boolean set once at first publish and never cleared. Do not infer it from published_at, which an archive step will overwrite and a restore will confuse. 

The article category list is a Knowledge-owned table managed through the Story 2.3 console. It is not the ticket category list: an article about refunds and a ticket about refunds are related by meaning, not by foreign key. 

Attachments reuse the polymorphic attachments table; article is already the fourth member of that closed enumeration, so this story adds an owner type and no table. 

Sanitise on write and escape on render. One of the two will be bypassed eventually; the other is why nothing happens when it is. 

No article revision history, no draft-of-a-published-article, no scheduled publishing and no review queue. Each of those is a workflow, and there is no workflow engine in this product. 

 UX / Interaction Requirements 

Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the Knowledge settings section: the article list, the type and category filters, and the lifecycle chips. 

Not in this version, though visible there: the reviewers and the review queue counted on that section's summary line (there is no In review state) · nested subcategories · per-article view and helpfulness metrics · article revision history. 

Dependencies 

Blocked by: 2.3 (the category list and the console section), 3.2 (attachments and scanning) 

Traceability 

Story ID: 8.1
Epic: Epic 8: Knowledge Base
Covers: FR-073, FR-074, FR-075, FR-076, FR-077, FR-139 · L-06 · AD-1, AD-5, AD-19 · NFR-07 

Delivery 

Sprint 8 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/524/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `524` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
8.1 Articles, categories, publishing and both languages
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a support team, I want one place where our answers live and a clear state that says whether an answer is ready, so that nobody pastes last year's refund policy into a reply. 

Context 

Epic 8 — Knowledge Base. Support answers the same twenty questions all year. This epic gives those answers one home: articles with a lifecycle, a flat category list, an internal/public axis and both languages, plus one search — native to PostgreSQL, with no engine and no index outside the database — reaching the two places it matters. An agent searches without leaving the ticket and drops a link into a reply in one action; a customer searches the portal help centre and sees only what was published for them, excluded at query level rather than hidden in the interface. 

Technical Constraints 

articles and article_translations (article_id, locale, title, body) are owned by a Knowledge module. Language versions are rows, not columns — a title_ar column makes a third language a migration and makes an Arabic-only article awkward. 

The never-published guard is a has_been_published boolean set once at first publish and never cleared. Do not infer it from published_at, which an archive step will overwrite and a restore will confuse. 

The article category list is a Knowledge-owned table managed through the Story 2.3 console. It is not the ticket category list: an article about refunds and a ticket about refunds are related by meaning, not by foreign key. 

Attachments reuse the polymorphic attachments table; article is already the fourth member of that closed enumeration, so this story adds an owner type and no table. 

Sanitise on write and escape on render. One of the two will be bypassed eventually; the other is why nothing happens when it is. 

No article revision history, no draft-of-a-published-article, no scheduled publishing and no review queue. Each of those is a workflow, and there is no workflow engine in this product. 

 UX / Interaction Requirements 

Approved UX sources: EXPERIENCE.md revision 3 and DESIGN.md revision 3. What the expansion restored, and what it deliberately did not, is recorded in UX-EXPANDED-DELTA.md. Nothing marked “not in this version” may be reintroduced. 

Design References 

mockups/screen-admin.html — the Knowledge settings section: the article list, the type and category filters, and the lifecycle chips. 

Not in this version, though visible there: the reviewers and the review queue counted on that section's summary line (there is no In review state) · nested subcategories · per-article view and helpfulness metrics · article revision history. 

Dependencies 

Blocked by: 2.3 (the category list and the console section), 3.2 (attachments and scanning) 

Traceability 

Story ID: 8.1
Epic: Epic 8: Knowledge Base
Covers: FR-073, FR-074, FR-075, FR-076, FR-077, FR-139 · L-06 · AD-1, AD-5, AD-19 · NFR-07 

Delivery 

Sprint 8 — planning source of truth: _bmad-output/planning-artifacts/epics.md revision 3, Expanded Basic Scope. Scope reductions are recorded in prd.md §19.1.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
An authorised user creates, edits and deletes a knowledge article carrying a title, a rich-text body and attachments. 

Deletion is permitted only for an article that has never been published. A published article is archived instead, and the control offered says which of the two is available and why. 

Four article types — FAQ · Help article · Solution · Guide — each separately filterable. The type is a label: it changes no behaviour, no permission and no surface. 

Articles sit in an Administrator-managed flat category list, separate from the ticket category list, each article belonging to exactly one category. There is no subcategory, no nesting control and no move-to-parent action. 

The lifecycle is exactly Draft → Published → Archived. There is no In review state, no reviewer, no approval step and no submit-for-review action — an authorised user publishes directly. 

internal and public is an axis independent of the lifecycle: every combination of the two is expressible, and an article reaches a customer only when it is both public and Published. 

An article may exist in English, Arabic or both, as per-article language versions. An Arabic-only article is valid and complete. 

The reader's language is served; where that translation is absent the default language is served instead and the reader is told which language they are reading — never a blank page and never an empty section. 

Article bodies render in full RTL in Arabic, with Gregorian dates and Western digits in both locales. 

Attachments go through the Story 3.2 subsystem — validated against the allow-list, scanned, quarantined until clean, and served only by short-lived signed URL from the object store. 

The rich-text body is sanitised server-side to a restricted element and attribute set, and cannot execute script in any reader's browser — staff or customer. 

Publishing, archiving and deleting are recorded with actor and timestamp on the article.
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

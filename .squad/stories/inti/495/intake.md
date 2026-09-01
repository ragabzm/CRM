> **Fetched from azure:** [495](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/495)  
> *Fetched 2026-09-01T08:00:20.020Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 1.4 Responsive bands and the accessibility floor  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an agent on a phone and as a user relying on a keyboard or a screen reader, I want every function available to me, so that neither the device nor the input method decides what I can do. 

Context 

Epic 1 — Foundation & Bilingual Shell. Two separately deployable applications exist and run; the design system, the bilingual RTL shell, the one formatting layer, the API contract and the responsive/accessibility floor are all in place — so every later epic builds on one foundation instead of six interpretations of it. 

Nothing in this epic is user-facing. It is the floor everything else stands on, and the one part of the plan that cannot be resequenced — RTL in particular cannot be retrofitted into a built UI. 

Technical Constraints 

Tailwind's default breakpoints, used through the three named bands only. No ad-hoc media queries in components. 

Accessibility is a merge gate, not a later pass. Wire axe (or equivalent) into CI against the rendered pages that exist at each point in the plan, and fail the build on a violation. 

Interactive elements are semantic HTML, or fully-labelled ARIA where Radix provides it. Do not hand-roll what Radix already implements. 

File upload must work from a mobile browser including device camera and photo library where the browser exposes them — this is an accept and capture attribute concern on the input, and it must be tested on a real phone. 

Browser support: current and previous major of Chrome, Edge, Firefox and Safari, desktop and mobile. This sets the CSS baseline — logical properties are safe across all of them. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the six-state SLA row (use three: On track, At risk, Breached) · any report or knowledge surface shown at mobile width. 

Design References 

mockups/screen-responsive.html — all three bands and both collapse mechanisms. 

Mockups in Azure DevOps (no Jira needed): 

screen-responsive.html — attached to this work item. 

 Dependencies 

1.2, 1.3 — bands apply to real components in a real shell. 

Blocked by: Story 1.3 

Traceability 

Story ID: 1.4 · Epic: 1 · Covers: FR-140 · R-01 – R-06, R-08 · NFR-13, NFR-14 · UX-01, UX-02, UX-12 

Delivery 

Sprint 1 · Priority 1

### Attachments

| File | Size | Status |
| ---- | ---- | ------ |
| `attachments/screen-responsive.html` | 231 KB | downloaded |

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/495/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `495` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
1.4 Responsive bands and the accessibility floor
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an agent on a phone and as a user relying on a keyboard or a screen reader, I want every function available to me, so that neither the device nor the input method decides what I can do. 

Context 

Epic 1 — Foundation & Bilingual Shell. Two separately deployable applications exist and run; the design system, the bilingual RTL shell, the one formatting layer, the API contract and the responsive/accessibility floor are all in place — so every later epic builds on one foundation instead of six interpretations of it. 

Nothing in this epic is user-facing. It is the floor everything else stands on, and the one part of the plan that cannot be resequenced — RTL in particular cannot be retrofitted into a built UI. 

Technical Constraints 

Tailwind's default breakpoints, used through the three named bands only. No ad-hoc media queries in components. 

Accessibility is a merge gate, not a later pass. Wire axe (or equivalent) into CI against the rendered pages that exist at each point in the plan, and fail the build on a violation. 

Interactive elements are semantic HTML, or fully-labelled ARIA where Radix provides it. Do not hand-roll what Radix already implements. 

File upload must work from a mobile browser including device camera and photo library where the browser exposes them — this is an accept and capture attribute concern on the input, and it must be tested on a real phone. 

Browser support: current and previous major of Chrome, Edge, Firefox and Safari, desktop and mobile. This sets the CSS baseline — logical properties are safe across all of them. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the six-state SLA row (use three: On track, At risk, Breached) · any report or knowledge surface shown at mobile width. 

Design References 

mockups/screen-responsive.html — all three bands and both collapse mechanisms. 

Mockups in Azure DevOps (no Jira needed): 

screen-responsive.html — attached to this work item. 

 Dependencies 

1.2, 1.3 — bands apply to real components in a real shell. 

Blocked by: Story 1.3 

Traceability 

Story ID: 1.4 · Epic: 1 · Covers: FR-140 · R-01 – R-06, R-08 · NFR-13, NFR-14 · UX-01, UX-02, UX-12 

Delivery 

Sprint 1 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
Three breakpoint bands exist and are the only ones used: mobile · tablet · desktop. 

Every agent function is completable on a 390px mobile browser. No function is desktop-only (UX-12). 

Every customer portal function is completable on a mobile browser. 

Scanned lists — tickets, customers — collapse by folding columns into the row: tablet folds secondary fields into a second meta line, mobile becomes a compact block. They do not scroll horizontally. 

Comparative tables — the audit log, the mail log — scroll horizontally with a pinned identity column. They do not fold. 

No data is ever silently truncated at any band. It folds, it scrolls, or it is behind a disclosure — never cut. 

Touch targets meet the accessibility minimum. No action anywhere is reachable only by hover, at any band including 1440 (UX-02). 

Every function is completable by keyboard alone, with a visible focus indicator at every step (UX-01). 

WCAG 2.1 AA contrast holds for every load-bearing combination, checked in both locales. 

Verified on real devices, not only a resized desktop browser: at minimum one iOS Safari and one Android Chrome, in both locales.
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

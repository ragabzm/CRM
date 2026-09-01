> **Fetched from azure:** [493](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/493)  
> *Fetched 2026-08-31T17:46:44.359Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 1.2 Design tokens and the component library  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a developer building any screen, I want a token system and a set of shared components already built and restyled, so that six epics of UI do not each re-invent a badge, a row and an empty state until nothing in the product matches. 

Context 

Epic 1 — Foundation & Bilingual Shell. Two separately deployable applications exist and run; the design system, the bilingual RTL shell, the one formatting layer, the API contract and the responsive/accessibility floor are all in place — so every later epic builds on one foundation instead of six interpretations of it. 

Nothing in this epic is user-facing. It is the floor everything else stands on, and the one part of the plan that cannot be resequenced — RTL in particular cannot be retrofitted into a built UI. 

Technical Constraints 

Tailwind CSS 4.3.x and shadcn/ui (current CLI), which brings Radix UI transitively. shadcn is a component foundation, never a visual theme — every primitive is restyled to the tokens before use. 

Only Tailwind logical utilities: ms- me- ps- pe- start- end- text-start text-end. The physical forms ml- mr- pl- pr- left- right- text-left text-right must be configured as lint errors — this is the single cheapest guarantee that Arabic RTL actually holds, and it must be a lint rule rather than review discipline. 

IBM Plex Sans and IBM Plex Sans Arabic, SIL OFL 1.1, self-hosted. No external font CDN in the request path. Plex Sans Arabic carries its own matched Latin and figure set, so TKT-000123 inside Arabic prose is drawn by the same face as the prose around it. 

Layer-A behaviour and ARIA come from Radix; do not reimplement focus traps, roving tabindex or dismiss behaviour. 

font-variant-numeric: tabular-nums on every numeric column, timer, count and identifier. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the bulk-action bar and multi-select column · the command palette primitive · the AI panel and AI draft treatments · the chart system · the branding controls. 

Design References 

DESIGN.md is the token authority. mockups/screen-responsive.html board R-5 is the normative RowActions rendering; mockups/screen-admin.html board A-11 shows the DataTable contract; mockups/screen-reports.html board R-6 shows empty-vs-forbidden. 

Mockups in Azure DevOps (no Jira needed): 

screen-reports.html — attached to this work item. 

screen-admin.html — attached to Story 2.3 (work item #498). 

screen-responsive.html — attached to Story 1.4 (work item #495). 

 Dependencies 

1.1 — the frontend application must exist. 

Blocked by: Story 1.1 

Blocks: Story 1.3 

Traceability 

Story ID: 1.2 · Epic: 1 · Covers: AD-28 · the component half of NFR-13, UX-01, UX-02, UX-03, UX-06 

Delivery 

Sprint 1 · Priority 1

### Attachments

| File | Size | Status |
| ---- | ---- | ------ |
| `attachments/screen-reports.html` | 304 KB | downloaded |

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/493/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `493` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
1.2 Design tokens and the component library
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a developer building any screen, I want a token system and a set of shared components already built and restyled, so that six epics of UI do not each re-invent a badge, a row and an empty state until nothing in the product matches. 

Context 

Epic 1 — Foundation & Bilingual Shell. Two separately deployable applications exist and run; the design system, the bilingual RTL shell, the one formatting layer, the API contract and the responsive/accessibility floor are all in place — so every later epic builds on one foundation instead of six interpretations of it. 

Nothing in this epic is user-facing. It is the floor everything else stands on, and the one part of the plan that cannot be resequenced — RTL in particular cannot be retrofitted into a built UI. 

Technical Constraints 

Tailwind CSS 4.3.x and shadcn/ui (current CLI), which brings Radix UI transitively. shadcn is a component foundation, never a visual theme — every primitive is restyled to the tokens before use. 

Only Tailwind logical utilities: ms- me- ps- pe- start- end- text-start text-end. The physical forms ml- mr- pl- pr- left- right- text-left text-right must be configured as lint errors — this is the single cheapest guarantee that Arabic RTL actually holds, and it must be a lint rule rather than review discipline. 

IBM Plex Sans and IBM Plex Sans Arabic, SIL OFL 1.1, self-hosted. No external font CDN in the request path. Plex Sans Arabic carries its own matched Latin and figure set, so TKT-000123 inside Arabic prose is drawn by the same face as the prose around it. 

Layer-A behaviour and ARIA come from Radix; do not reimplement focus traps, roving tabindex or dismiss behaviour. 

font-variant-numeric: tabular-nums on every numeric column, timer, count and identifier. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the bulk-action bar and multi-select column · the command palette primitive · the AI panel and AI draft treatments · the chart system · the branding controls. 

Design References 

DESIGN.md is the token authority. mockups/screen-responsive.html board R-5 is the normative RowActions rendering; mockups/screen-admin.html board A-11 shows the DataTable contract; mockups/screen-reports.html board R-6 shows empty-vs-forbidden. 

Mockups in Azure DevOps (no Jira needed): 

screen-reports.html — attached to this work item. 

screen-admin.html — attached to Story 2.3 (work item #498). 

screen-responsive.html — attached to Story 1.4 (work item #495). 

 Dependencies 

1.1 — the frontend application must exist. 

Blocked by: Story 1.1 

Blocks: Story 1.3 

Traceability 

Story ID: 1.2 · Epic: 1 · Covers: AD-28 · the component half of NFR-13, UX-01, UX-02, UX-03, UX-06 

Delivery 

Sprint 1 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
Every colour, spacing, radius, elevation and type value from DESIGN.md exists as a token. No component contains a colour literal. 

Components reference semantic tokens (bg-surface-raised), never primitives (bg-n-800). A lint rule enforces it. 

The four component layers exist and are separated in the file tree: A restyled shadcn primitives in components/ui/, B shared domain components in components/domain/, C screen compositions, D the layout shell. A screen assembled from repeated Layer-A primitives fails review. 

Layer-A primitives are restyled once: buttons (primary, secondary, ghost, destructive), input, select, dropdown, dialog, sheet, toast, tabs, checkbox, radio, tooltip. 

DataTable exists with the full contract: server-driven sorting · filter chips and panel · text search · column visibility with the identity column locked, not absent · responsive collapse · RTL · real <table> semantics with sortable-column state announced · keyboard row and column navigation · focus retention across pagination. 

RowActions renders its overflow control persistently, never revealed on hover (UX-02). 

EmptyState and ForbiddenState are visually unmistakable from each other, and the forbidden state prints no numeral — it must not leak a count (UX-06). 

Every state that carries meaning survives greyscale — hue is always paired with a glyph, word, weight or shape (UX-03, NFR-13). 

Every component renders correctly under dir="rtl" with no physical-direction CSS.
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

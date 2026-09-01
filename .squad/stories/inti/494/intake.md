> **Fetched from azure:** [494](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/494)  
> *Fetched 2026-09-01T07:59:30.769Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 1.3 The bilingual RTL shell, the one formatting layer, and the global chrome  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an Arabic-speaking agent, I want the entire product in genuine right-to-left Arabic with dates and numbers I recognise, so that Arabic is a first-class language and not a translated overlay. 

Context 

Epic 1 — Foundation & Bilingual Shell. Two separately deployable applications exist and run; the design system, the bilingual RTL shell, the one formatting layer, the API contract and the responsive/accessibility floor are all in place — so every later epic builds on one foundation instead of six interpretations of it. 

Nothing in this epic is user-facing. It is the floor everything else stands on, and the one part of the plan that cannot be resequenced — RTL in particular cannot be retrofitted into a built UI. 

Technical Constraints 

next-intl 4.13.x. One dir attribute at the layout root drives direction; there is no second Arabic stylesheet. 

Pin the Arabic locale to ar-u-ca-gregory-nu-latn. Both halves matter and both are load-bearing: - -nu-latn is essential — ar-SA and ar-EG resolve numberingSystem=arab and will render Eastern Arabic digits ٠-٩ without it. - -ca-gregory is defence in depth — FR-152 forbids a Hijri default anywhere, the CLDR default has changed before, and the backend's ICU build differs from the browser's. (Verified on Node 24.2.0: current ICU resolves ar, ar-SA and ar-EG all to calendar=gregory. Pin it anyway — this is exactly the kind of default that changes under you.) 

Backend lang/{en,ar}/ is used only for artefacts the server renders and sends — emails and persisted notification text. Frontend messages/{en,ar}.json for everything a user reads on screen. No key exists in both. 

There is no ⌘K command palette and no global search. Search is per-destination (Stories 3.1, 4.5). 

There is no notification centre surface and no availability state — the bell opens a plain list. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the ⌘K search field in the chrome · the notification centre · the availability state in the user menu · the Chat, Knowledge and Reports destinations. 

Design References 

EXPERIENCE.md § Bilingual & RTL Behaviour and § Information Architecture. mockups/direction-e2-workspace.html shows the chrome and sidebar; mockups/screen-reports.html board R-8 and mockups/screen-admin.html board A-11d carry live dir="rtl" specimens. 

Mockups in Azure DevOps (no Jira needed): 

direction-e2-workspace.html — attached to Story 4.4 (work item #506). 

screen-admin.html — attached to Story 2.3 (work item #498). 

screen-reports.html — attached to Story 1.2 (work item #493). 

 Dependencies 

1.2 — the shell is assembled from Layer-A and Layer-B components. 

Blocked by: Story 1.2 

Blocks: Story 1.4, Story 2.1 

Traceability 

Story ID: 1.3 · Epic: 1 · Covers: FR-134, FR-135, FR-136, FR-137, FR-152 · L-01 – L-05, L-07 – L-12 · NFR-18 · AD-12, AD-25 · UX-04, UX-05 

Delivery 

Sprint 1 · Priority 1

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/494/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `494` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
1.3 The bilingual RTL shell, the one formatting layer, and the global chrome
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an Arabic-speaking agent, I want the entire product in genuine right-to-left Arabic with dates and numbers I recognise, so that Arabic is a first-class language and not a translated overlay. 

Context 

Epic 1 — Foundation & Bilingual Shell. Two separately deployable applications exist and run; the design system, the bilingual RTL shell, the one formatting layer, the API contract and the responsive/accessibility floor are all in place — so every later epic builds on one foundation instead of six interpretations of it. 

Nothing in this epic is user-facing. It is the floor everything else stands on, and the one part of the plan that cannot be resequenced — RTL in particular cannot be retrofitted into a built UI. 

Technical Constraints 

next-intl 4.13.x. One dir attribute at the layout root drives direction; there is no second Arabic stylesheet. 

Pin the Arabic locale to ar-u-ca-gregory-nu-latn. Both halves matter and both are load-bearing: - -nu-latn is essential — ar-SA and ar-EG resolve numberingSystem=arab and will render Eastern Arabic digits ٠-٩ without it. - -ca-gregory is defence in depth — FR-152 forbids a Hijri default anywhere, the CLDR default has changed before, and the backend's ICU build differs from the browser's. (Verified on Node 24.2.0: current ICU resolves ar, ar-SA and ar-EG all to calendar=gregory. Pin it anyway — this is exactly the kind of default that changes under you.) 

Backend lang/{en,ar}/ is used only for artefacts the server renders and sends — emails and persisted notification text. Frontend messages/{en,ar}.json for everything a user reads on screen. No key exists in both. 

There is no ⌘K command palette and no global search. Search is per-destination (Stories 3.1, 4.5). 

There is no notification centre surface and no availability state — the bell opens a plain list. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

the ⌘K search field in the chrome · the notification centre · the availability state in the user menu · the Chat, Knowledge and Reports destinations. 

Design References 

EXPERIENCE.md § Bilingual & RTL Behaviour and § Information Architecture. mockups/direction-e2-workspace.html shows the chrome and sidebar; mockups/screen-reports.html board R-8 and mockups/screen-admin.html board A-11d carry live dir="rtl" specimens. 

Mockups in Azure DevOps (no Jira needed): 

direction-e2-workspace.html — attached to Story 4.4 (work item #506). 

screen-admin.html — attached to Story 2.3 (work item #498). 

screen-reports.html — attached to Story 1.2 (work item #493). 

 Dependencies 

1.2 — the shell is assembled from Layer-A and Layer-B components. 

Blocked by: Story 1.2 

Blocks: Story 1.4, Story 2.1 

Traceability 

Story ID: 1.3 · Epic: 1 · Covers: FR-134, FR-135, FR-136, FR-137, FR-152 · L-01 – L-05, L-07 – L-12 · NFR-18 · AD-12, AD-25 · UX-04, UX-05 

Delivery 

Sprint 1 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
Every user-facing string is externalised into messages/en.json and messages/ar.json. No hard-coded text anywhere. A lint rule catches string literals in JSX. 

English is the default language. 

Switching to Arabic flips every surface to RTL — text direction, alignment, navigation, tables, forms, icon direction — with no untranslated string and no broken alignment (UX-04). 

Language is a per-user preference, persisted, and applied on every subsequent session. 

Every date and number in the product is formatted in exactly one module, frontend/lib/format/. No component calls Intl.*, toLocaleString or toLocaleDateString directly — enforced by lint. 

Dates render on the Gregorian calendar in both locales. Numerals render as Western digits 0-9 in both locales, identically — ticket references, phone numbers, counts and dates included (UX-05). 

Mixed-direction text is correct: a Latin ticket reference inside Arabic prose does not reverse or reorder. A BidiValue wrapper applies direction: ltr and unicode-bidi: isolate to the run. 

A missing translation falls back to English and is reported to Administrators, never rendered blank. 

The staff shell renders the four destinations — Home · Tickets · Customers · Administration — with Administration visible only to Administrators. 

Global chrome carries: the EN⇄AR switch, a notification bell with a plain list, and the user menu with sign-out and profile.
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

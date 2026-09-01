> **Fetched from azure:** [501](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/501)  
> *Fetched 2026-09-01T08:08:58.756Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 3.2 Notes and the attachment subsystem  
**Type:** User Story  
**Status:** New

### Description

User Story 

As an agent, I want to annotate a customer and attach files safely anywhere they are needed, so that context is captured and an uploaded file can never execute in someone's browser. 

Context 

Epic 3 — Customers. Staff hold one authoritative record per customer — searchable, de-duplicated, annotated, with files attached and scanned — and can see that customer's whole story in one place. 

Technical Constraints 

attachments is polymorphic over a closed set of three owners, carrying a type discriminator and no foreign key to its owner. That is what lets Platform (T0) own it without depending on Customers (T2) or Tickets (T3). 

Two storage prefixes: quarantine/ and clean/. Both private. The move between them is the state transition. 

Scan runs as a queued job with the FileScanner port and a null adapter for CI. ClamAV over its daemon socket is the conventional choice; the port is what makes it replaceable. 

Allow-list and size cap are Story 2.3 settings, read at validation time — not compile-time constants. 

Never trust the client-supplied MIME type. Sniff it server-side and reject on mismatch. 

 Design References 

mockups/screen-customer-profile.html — the notes lane and the attachment scan lifecycle, which is the normative rendering of the three states. 

Mockups in Azure DevOps (no Jira needed): 

screen-customer-profile.html — attached to Story 3.1 (work item #500). 

 Dependencies 

3.1 — a note and an attachment need a record to hang on. 

Blocked by: Story 3.1 

Blocks: Story 4.4 

Traceability 

Story ID: 3.2 · Epic: 3 · Covers: FR-008 – FR-011 · NFR-08 · AD-19 · UX-10 

Delivery 

Sprint 3 · Priority 1

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/501/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `501` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
3.2 Notes and the attachment subsystem
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As an agent, I want to annotate a customer and attach files safely anywhere they are needed, so that context is captured and an uploaded file can never execute in someone's browser. 

Context 

Epic 3 — Customers. Staff hold one authoritative record per customer — searchable, de-duplicated, annotated, with files attached and scanned — and can see that customer's whole story in one place. 

Technical Constraints 

attachments is polymorphic over a closed set of three owners, carrying a type discriminator and no foreign key to its owner. That is what lets Platform (T0) own it without depending on Customers (T2) or Tickets (T3). 

Two storage prefixes: quarantine/ and clean/. Both private. The move between them is the state transition. 

Scan runs as a queued job with the FileScanner port and a null adapter for CI. ClamAV over its daemon socket is the conventional choice; the port is what makes it replaceable. 

Allow-list and size cap are Story 2.3 settings, read at validation time — not compile-time constants. 

Never trust the client-supplied MIME type. Sniff it server-side and reject on mismatch. 

 Design References 

mockups/screen-customer-profile.html — the notes lane and the attachment scan lifecycle, which is the normative rendering of the three states. 

Mockups in Azure DevOps (no Jira needed): 

screen-customer-profile.html — attached to Story 3.1 (work item #500). 

 Dependencies 

3.1 — a note and an attachment need a record to hang on. 

Blocked by: Story 3.1 

Blocks: Story 4.4 

Traceability 

Story ID: 3.2 · Epic: 3 · Covers: FR-008 – FR-011 · NFR-08 · AD-19 · UX-10 

Delivery 

Sprint 3 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
A user adds free-text notes to a customer record, each stamped with author and timestamp. 

A user can edit or delete a note they authored. A Supervisor can delete any note. 

Files attach to a customer, a ticket, and an individual message — three owners, and the set is closed. 

Each attachment records filename, size, type, uploader and upload time. 

An upload is validated against the allow-list and size cap, and scanned, before it becomes downloadable. 

An attachment pending scan is visible, labelled, and not downloadable (UX-10). Upload succeeds; release waits. 

A file that fails validation or scanning is refused with the reason and never becomes downloadable. 

Download is a short-lived signed URL from object storage — never a path served by the application origin — always with Content-Disposition: attachment and a non-executable content type. 

No attachment previews inline. 

If the scanner is unreachable, uploads still succeed and stay quarantined. Nothing about a ticket becomes unworkable.
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

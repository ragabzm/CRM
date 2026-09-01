> **Fetched from azure:** [496](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/496)  
> *Fetched 2026-09-01T08:04:13.998Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 2.1 Staff authentication, sessions, password reset and own profile  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a staff member, I want to sign in securely and manage my own account, so that the system knows who I am on every action and a forgotten password is not an administrator's problem. 

Context 

Epic 2 — Identity, Users & Administration. An Administrator signs in, builds the organisation out of departments, creates users in the four fixed roles, configures the system without a redeployment, and finds every action in a log nobody can edit. 

Technical Constraints 

Laravel Sanctum, SPA cookie mode, same-site. Not token mode — cookie mode is what keeps requirement 5 true without any client-side credential handling. 

Two guards configured explicitly: web for users, a second guard for portal_accounts. The portal guard must not be able to resolve a staff user, and a test must prove it. 

bcrypt or argon2id via Laravel's hasher. Never a custom hash. 

Reset tokens: single-use, hashed at rest, expiring — Laravel's password broker does this; do not hand-roll it. 

Rate-limit sign-in and reset-request endpoints. 

The composer draft is a localStorage entry keyed by ticket id, written on change and cleared on send. Stated limitation to carry into QA rather than discover there: the draft survives session expiry, not a full page reload or a tab close. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

two-factor enrolment and challenge screens · the notification-preferences matrix on the profile · the in-place re-authentication overlay (a plain redirect replaces it). 

Design References 

⚠ Dedicated mockup missing — design required before implementation. 

EXPERIENCE.md § Interaction Primitives and the Identity surfaces in the screen inventory. No dedicated mockup — derive from the component set. 

Dependencies 

1.1, 1.3 

Blocked by: Story 1.3 

Blocks: Story 2.2, Story 6.1 

Traceability 

Story ID: 2.1 · Epic: 2 · Covers: FR-111, FR-112 · NFR-06 · BR-15 · AD-7, AD-14 

Delivery 

Sprint 2 · Priority 1

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/496/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `496` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
2.1 Staff authentication, sessions, password reset and own profile
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a staff member, I want to sign in securely and manage my own account, so that the system knows who I am on every action and a forgotten password is not an administrator's problem. 

Context 

Epic 2 — Identity, Users & Administration. An Administrator signs in, builds the organisation out of departments, creates users in the four fixed roles, configures the system without a redeployment, and finds every action in a log nobody can edit. 

Technical Constraints 

Laravel Sanctum, SPA cookie mode, same-site. Not token mode — cookie mode is what keeps requirement 5 true without any client-side credential handling. 

Two guards configured explicitly: web for users, a second guard for portal_accounts. The portal guard must not be able to resolve a staff user, and a test must prove it. 

bcrypt or argon2id via Laravel's hasher. Never a custom hash. 

Reset tokens: single-use, hashed at rest, expiring — Laravel's password broker does this; do not hand-roll it. 

Rate-limit sign-in and reset-request endpoints. 

The composer draft is a localStorage entry keyed by ticket id, written on change and cleared on send. Stated limitation to carry into QA rather than discover there: the draft survives session expiry, not a full page reload or a tab close. 

 UX / Interaction Requirements 

Not in this version, though visible in the referenced mockups: 

two-factor enrolment and challenge screens · the notification-preferences matrix on the profile · the in-place re-authentication overlay (a plain redirect replaces it). 

Design References 

⚠ Dedicated mockup missing — design required before implementation. 

EXPERIENCE.md § Interaction Primitives and the Identity surfaces in the screen inventory. No dedicated mockup — derive from the component set. 

Dependencies 

1.1, 1.3 

Blocked by: Story 1.3 

Blocks: Story 2.2, Story 6.1 

Traceability 

Story ID: 2.1 · Epic: 2 · Covers: FR-111, FR-112 · NFR-06 · BR-15 · AD-7, AD-14 

Delivery 

Sprint 2 · Priority 1
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
A staff member signs in with email and password. Sessions carry a configurable inactivity timeout. 

Password policy — minimum length and complexity — is configurable. Passwords are stored using a one-way cryptographic hash and are never logged or returned by any endpoint. 

Password reset is by email-delivered, time-limited, single-use link. 

Staff and portal customers are two separate identity spaces: two tables, two guards, two session cookies. There is no is_staff column and no shared table with a discriminator. A credential valid in one space is meaningless in the other. 

No credential, token or refresh value is ever handled by client JavaScript or written to localStorage or sessionStorage. 

Session expiry redirects to the staff sign-in route. The composer draft (Story 4.4) is preserved locally so the redirect does not destroy unsent text. 

A user can view and edit their own profile: name, password, and language preference. 

Sign-in success and sign-in failure are both written to the audit log (Story 2.4).
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

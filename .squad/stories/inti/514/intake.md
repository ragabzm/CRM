> **Fetched from azure:** [514](https://dev.azure.com/ibbaqi/ragab-crm/_workitems/edit/514)  
> *Fetched 2026-09-03T07:54:43.680Z. Edit the sections below as needed; the planner reads this file verbatim.*


## Source — work item (from tracker)

**Title:** 1.5 Demo data seeders — every module, idempotent, and wired into the restart script  
**Type:** User Story  
**Status:** New

### Description

User Story 

As a developer, reviewer or demonstrator of the CRM, I want a fresh environment to come up already carrying a complete and realistic dataset across every implemented module, so that the agent home, the ticket list, the ticket detail, the customer profile, the timeline and the administration console all show real data on first sign-in instead of empty states — and so nobody ever recreates that data by hand again. 

Context 

Two seeders exist today: the fixed role and capability matrix, and three staff accounts (one administrator, one supervisor, one agent). Every other table is empty after migrate:fresh --seed. The consequence is that no screen built in Epics 3 and 4 can be reviewed, demonstrated or eyeballed for a regression without somebody first creating a department, a customer and a handful of tickets through the UI — which is slow, inconsistent between machines, and destroyed by the next rebuild. 

Two further facts shape the work. First, no factory exists for any domain entity beyond User, so there is no reusable builder outside the test suite; the seeders are the first such builder and should be written to be reusable. Second, the restart script passes --seed to migrate:fresh, but the two other paths through that script — --no-fresh and --wipe-volume — leave a database that is migrated and empty, and there is no way to top up an existing database without dropping every table first. 

Scope 

One seeder class per module, each independently runnable, all orchestrated from the root database seeder in dependency order. 

Security — three active departments. The three existing staff accounts stay exactly as they are (administrator, supervisor, agent). Three additional agent accounts are added on top, each in a department, each active, so that ticket ownership can be spread across more than one person and so that "my queue" versus "the department queue" is visibly different for each of them. 

Customers — at least six customers spread across the departments and across both states (active and deactivated), each carrying contact identifiers (at minimum one email and one phone, with exactly one primary per kind), and at least two of them carrying a note written by a named staff author. 

Tickets — a flat set of at least five bilingual categories (English and Arabic names, ordered). At least eighteen tickets, distributed so that every agent account holds more than one, plus a pool of unassigned tickets. Across the set: all four statuses (open, pending, resolved, closed), all four priorities (low, normal, high, urgent), and all four channels (agent, portal, email, system). Every ticket carries at least two messages in a readable back-and-forth, and at least three tickets carry an internal note that a customer must never see. Ticket history is produced as a by-product of the write path, never fabricated. 

Platform — at least two attachments, one owned by a ticket and one by a customer, stored on disk and recorded as scanned and clean so they are actually downloadable rather than stuck in quarantine. Audit entries appear only as a by-product of the seeded writes; none are invented. 

Portal — at least two portal accounts, verified and linked to seeded customers, so the portal side of the product can be signed into and so a portal-raised ticket has a real originator. 

Out of scope, deliberately. SLA and Email own no tables yet, so there is nothing to seed for them; when those modules land, their seed data is added as part of their own stories. The settings table is not seeded: every setting declares a default in the registry and is resolved from that default when no row exists, so writing rows here would fabricate a difference between a fresh environment and a real one. 

Technical Constraints 

One write path for a ticket (AD-3). Tickets, ticket messages and ticket events are created through the Tickets module commands — create, append message, assign, change status, resolve — with an explicit actor on every call. A direct insert into tickets or ticket_events skips the version column, the lifecycle rules and the event append, and produces rows that look normal until somebody tries to reconstruct what happened. The architecture test that enforces this scans the module tree only; the seeders must honour the same rule by construction. 

References come from their allocators. The ticket reference and the customer reference are sequence-backed. They are requested from the allocator, never hard-coded, so the next reference issued after seeding does not collide with a seeded one. 

Idempotent, and it must check before it writes. Every seeder converges instead of accumulating: matched on a natural key (department name, account email, category name, customer reference, ticket reference) and short-circuiting when the data is already present. Running the full seed twice in a row leaves every row count identical and creates no duplicate ticket, message or identifier. This is the difference between a seeder that can be re-run on a live development database and one that can only ever follow a drop. 

Deterministic. Fixed names, e-mail addresses and subjects; any randomness carries a fixed seed; timestamps are relative to the moment of the run. Two developers on two machines get the same dataset, which is what makes "it looks wrong on my machine" a meaningful sentence. 

Local development only. Same guard as the existing accounts seeder: outside the local and testing environments the demo seeders warn and return without writing a row. The shared password is acceptable only because of that guard, and it is published with the other development credentials. 

Runnable in isolation. Each seeder can be invoked on its own by class, and pulls in whatever it depends on, so recovering one slice of the data does not require a full rebuild. 

Fast. The full seed completes in well under a minute on a normal development machine; it runs on every rebuild, so it is on the critical path of every developer's day. 

Restart script 

The seed step becomes explicit and reaches all three paths through ./scripts/restart.sh, rather than riding along on migrate:fresh alone: 

after a fresh rebuild, after a --no-fresh restart, and after a --wipe-volume run, the seed executes — and because it is idempotent, running it on a database that already holds the data is a no-op rather than a duplication; 

--no-seed continues to skip it entirely; 

a failing seed stops the script with a named error and a log to read, exactly as a failing migration already does — it must not scroll past as a warning; 

the script prints a short summary of what now exists (departments, staff, customers, tickets) so the person running it can see at a glance that the data landed. 

Dependencies 

Consumes the write paths delivered by 2.2 (users, roles, departments), 3.1 (customer record), 3.2 (notes and attachments), 4.1 (ticket creation), 4.2 (assignment and lifecycle), 4.3 (ticket history), 4.4 (conversation and internal notes) and 6.1 (portal identity). All are delivered; this story adds no new schema and no new endpoint. 

Traceability 

Developer-experience work, not a product requirement: it has no functional requirement behind it. It supports every reviewable outcome in Epics 2, 3, 4 and 6 by making them observable on a fresh environment. It respects AD-3 (one ticket write path), AD-4 (the capability matrix is seeded and fixed) and AD-5 (settings are typed rows read through a registry, with defaults that must not be shadowed by seeded rows). 

Delivery 

Backend and tooling only. No user interface, no API surface, no migration. Ships with a test that runs the full seed twice and asserts that the second run changes no row count.

### Attachments

None.

---
# Story intake

Fill this template for each story you want planned. Keep it copy-paste-friendly: the planner reads **this file and the files in `attachments/`**, nothing else.

- Folder: `.squad/stories/inti/514/intake.md`
- Binaries (screenshots, PDFs, exports): put them in `attachments/` next to this file and list them below.
- Do **not** rely on external links (tracker URLs, wiki, chat) — the planner cannot open them. Paste the content you want considered.

This is **not** an implementation prompt. It is the input to the plan-generation meta-prompt bundled with squad-kit (`generate-plan.md` in the installed package).

---

## Feature

- **Feature name (display):**
- **Feature slug (folder under `plans/`):** `inti`

## Tracker (metadata only)

- **Tracker type:** `azure`
- **Work item id:** `514` *(used in filenames and plan tables; fill manually if empty)*
- **Work item type:** `User Story`
- **Status:** `New`
- **Assignee:** ``
- **Labels:** ``

External tracker links are **not** followed by the planner. Keep the id for naming and traceability only.

---

## Title

*(Paste the work item title verbatim. Prefilled when `squad new-story` fetched from a tracker.)*

```
1.5 Demo data seeders — every module, idempotent, and wired into the restart script
```

---

## Description

*(Paste the full work item description. Prefilled when fetched from a tracker.)*

```
User Story 

As a developer, reviewer or demonstrator of the CRM, I want a fresh environment to come up already carrying a complete and realistic dataset across every implemented module, so that the agent home, the ticket list, the ticket detail, the customer profile, the timeline and the administration console all show real data on first sign-in instead of empty states — and so nobody ever recreates that data by hand again. 

Context 

Two seeders exist today: the fixed role and capability matrix, and three staff accounts (one administrator, one supervisor, one agent). Every other table is empty after migrate:fresh --seed. The consequence is that no screen built in Epics 3 and 4 can be reviewed, demonstrated or eyeballed for a regression without somebody first creating a department, a customer and a handful of tickets through the UI — which is slow, inconsistent between machines, and destroyed by the next rebuild. 

Two further facts shape the work. First, no factory exists for any domain entity beyond User, so there is no reusable builder outside the test suite; the seeders are the first such builder and should be written to be reusable. Second, the restart script passes --seed to migrate:fresh, but the two other paths through that script — --no-fresh and --wipe-volume — leave a database that is migrated and empty, and there is no way to top up an existing database without dropping every table first. 

Scope 

One seeder class per module, each independently runnable, all orchestrated from the root database seeder in dependency order. 

Security — three active departments. The three existing staff accounts stay exactly as they are (administrator, supervisor, agent). Three additional agent accounts are added on top, each in a department, each active, so that ticket ownership can be spread across more than one person and so that "my queue" versus "the department queue" is visibly different for each of them. 

Customers — at least six customers spread across the departments and across both states (active and deactivated), each carrying contact identifiers (at minimum one email and one phone, with exactly one primary per kind), and at least two of them carrying a note written by a named staff author. 

Tickets — a flat set of at least five bilingual categories (English and Arabic names, ordered). At least eighteen tickets, distributed so that every agent account holds more than one, plus a pool of unassigned tickets. Across the set: all four statuses (open, pending, resolved, closed), all four priorities (low, normal, high, urgent), and all four channels (agent, portal, email, system). Every ticket carries at least two messages in a readable back-and-forth, and at least three tickets carry an internal note that a customer must never see. Ticket history is produced as a by-product of the write path, never fabricated. 

Platform — at least two attachments, one owned by a ticket and one by a customer, stored on disk and recorded as scanned and clean so they are actually downloadable rather than stuck in quarantine. Audit entries appear only as a by-product of the seeded writes; none are invented. 

Portal — at least two portal accounts, verified and linked to seeded customers, so the portal side of the product can be signed into and so a portal-raised ticket has a real originator. 

Out of scope, deliberately. SLA and Email own no tables yet, so there is nothing to seed for them; when those modules land, their seed data is added as part of their own stories. The settings table is not seeded: every setting declares a default in the registry and is resolved from that default when no row exists, so writing rows here would fabricate a difference between a fresh environment and a real one. 

Technical Constraints 

One write path for a ticket (AD-3). Tickets, ticket messages and ticket events are created through the Tickets module commands — create, append message, assign, change status, resolve — with an explicit actor on every call. A direct insert into tickets or ticket_events skips the version column, the lifecycle rules and the event append, and produces rows that look normal until somebody tries to reconstruct what happened. The architecture test that enforces this scans the module tree only; the seeders must honour the same rule by construction. 

References come from their allocators. The ticket reference and the customer reference are sequence-backed. They are requested from the allocator, never hard-coded, so the next reference issued after seeding does not collide with a seeded one. 

Idempotent, and it must check before it writes. Every seeder converges instead of accumulating: matched on a natural key (department name, account email, category name, customer reference, ticket reference) and short-circuiting when the data is already present. Running the full seed twice in a row leaves every row count identical and creates no duplicate ticket, message or identifier. This is the difference between a seeder that can be re-run on a live development database and one that can only ever follow a drop. 

Deterministic. Fixed names, e-mail addresses and subjects; any randomness carries a fixed seed; timestamps are relative to the moment of the run. Two developers on two machines get the same dataset, which is what makes "it looks wrong on my machine" a meaningful sentence. 

Local development only. Same guard as the existing accounts seeder: outside the local and testing environments the demo seeders warn and return without writing a row. The shared password is acceptable only because of that guard, and it is published with the other development credentials. 

Runnable in isolation. Each seeder can be invoked on its own by class, and pulls in whatever it depends on, so recovering one slice of the data does not require a full rebuild. 

Fast. The full seed completes in well under a minute on a normal development machine; it runs on every rebuild, so it is on the critical path of every developer's day. 

Restart script 

The seed step becomes explicit and reaches all three paths through ./scripts/restart.sh, rather than riding along on migrate:fresh alone: 

after a fresh rebuild, after a --no-fresh restart, and after a --wipe-volume run, the seed executes — and because it is idempotent, running it on a database that already holds the data is a no-op rather than a duplication; 

--no-seed continues to skip it entirely; 

a failing seed stops the script with a named error and a log to read, exactly as a failing migration already does — it must not scroll past as a warning; 

the script prints a short summary of what now exists (departments, staff, customers, tickets) so the person running it can see at a glance that the data landed. 

Dependencies 

Consumes the write paths delivered by 2.2 (users, roles, departments), 3.1 (customer record), 3.2 (notes and attachments), 4.1 (ticket creation), 4.2 (assignment and lifecycle), 4.3 (ticket history), 4.4 (conversation and internal notes) and 6.1 (portal identity). All are delivered; this story adds no new schema and no new endpoint. 

Traceability 

Developer-experience work, not a product requirement: it has no functional requirement behind it. It supports every reviewable outcome in Epics 2, 3, 4 and 6 by making them observable on a fresh environment. It respects AD-3 (one ticket write path), AD-4 (the capability matrix is seeded and fixed) and AD-5 (settings are typed rows read through a registry, with defaults that must not be shadowed by seeded rows). 

Delivery 

Backend and tooling only. No user interface, no API surface, no migration. Ships with a test that runs the full seed twice and asserts that the second run changes no row count.
```

---

## Acceptance criteria

*(Checklist, bullets, Gherkin, etc. Prefilled for Azure DevOps when the work item has acceptance criteria.)*

```
On a database that has only just been migrated, a single seed run leaves every implemented module holding data: departments, staff accounts, ticket categories, customers, contact identifiers, customer notes, tickets, ticket messages, ticket events, attachments and portal accounts are all non-empty. 

The three existing staff accounts (administrator, supervisor, agent) survive the run unchanged in identity and role, and three additional agent accounts exist alongside them, each assigned to a department and each active. 

Every one of the agent accounts is the assignee of more than one ticket, and at least one ticket is left unassigned so the unassigned queue is not empty either. 

Across the seeded tickets, all four statuses, all four priorities and all four channels are represented, so no filter in the ticket list returns an empty result on a fresh environment. 

Every seeded ticket carries at least two messages forming a readable exchange, and at least three tickets carry an internal note that is not visible to a customer. 

Every seeded ticket carries the history events its lifecycle implies — creation, assignment, and any status change — and those events were produced by the ticket write path rather than inserted. 

No seeder writes to the tickets or ticket events tables directly; tickets, messages, assignments, status changes and resolutions are all performed through the Tickets module commands with an explicit actor. 

Ticket and customer references are obtained from their allocators: after seeding, creating a new ticket and a new customer through the application succeeds and produces references that continue the sequence without colliding. 

Every seeder checks for existing data before writing, on a natural key. Running the full seed twice in succession leaves every table's row count identical to the first run and creates no duplicate account, customer, identifier, category, ticket or message. 

Each seeder can be run on its own by class name against a database where the rest of the data already exists, and completes without error. 

Running a demo seeder in an environment other than local or testing writes nothing and reports that it was skipped. 

Two consecutive full runs produce the same names, e-mail addresses, references and subjects; nothing depends on unseeded randomness. 

At least two attachments exist, one on a ticket and one on a customer, both recorded as scanned and clean, with their stored files actually present so a download succeeds. 

At least two portal accounts exist, verified and linked to seeded customers, and can be signed into with the published development password. 

No rows are written to the settings table; every setting still resolves to the default declared in its registry definition. 

The restart script runs the seed on the fresh path, the no-fresh path and the wipe-volume path, skips it under the no-seed flag, aborts the run with a named error and a readable log if the seed fails, and prints a summary of the resulting counts. 

An automated test runs the full seed twice and asserts that the second run changes no row count. 

The full seed completes in under a minute on a normal development machine.
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

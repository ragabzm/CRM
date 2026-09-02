# Ragab CRM — backend

Laravel 13 API. Deployed independently of `frontend/`; the only thing the two
share is `openapi.yaml`.

> **PHP version.** The story targets **PHP 8.5**, and the Docker image runs
> `php:8.5-fpm-alpine`. The local toolchain on this machine is currently
> **8.4.12**, so `composer.json` requires `^8.4` (which admits 8.5) and CI pins
> 8.4 in `.github/workflows/backend.yml`. **Follow-up:** once the workstations
> and runners are on 8.5, raise `composer.json` to `^8.5` and `PHP_VERSION` in
> the workflow, then delete this note. Nothing in the codebase depends on an
> 8.4-only behaviour.

## Layout

```
app/Modules/            the seven modules; see "Modules" below
  module-tiers.php      the single declaration of the tier ordering
  Platform/             T0 — the cross-cutting HTTP, logging and idempotency machinery
config/problem-details.php   base URI for RFC 9457 `type` members
deptrac.yaml            per-module dependency rules (tier ordering + no sideways T4 calls)
deptrac-tiers.yaml      the same ordering checked at tier granularity
docker/entrypoint.sh    selects web | worker | scheduler at container start
openapi.yaml            THE CONTRACT — generated, committed, drift-checked in CI
docker/router.php       front controller for the built-in server (keeps stdout JSON-only)
scripts/                CI guards runnable outside PHPUnit
```

## Commands

```bash
composer install
cp .env.example .env && php artisan key:generate

php artisan test                 # unit + feature + architecture suites
composer arch                    # both deptrac configs
composer openapi:generate        # rewrite openapi.yaml from the routes
php artisan openapi:check        # fail if openapi.yaml is stale
```

The document is a function of the routes alone. `info.title` and `servers` are
pinned in `Platform/Support/OpenApiContract.php` rather than taken from
`APP_NAME` / `APP_URL`, because a contract that varies with the generating
environment fails the drift check on CI — which has no `.env` — for a document
nobody changed. That class also injects the two cross-cutting facts Scramble
cannot infer from a controller: the problem+json error shape on every operation,
and the `Idempotency-Key` header on every write.

```bash
composer no-cross-import         # fail if backend/ references frontend/
```

Tests run on in-memory SQLite (`phpunit.xml`), so no database service is needed
to run them. Postgres-specific column types (`jsonb`, `bytea`) are declared via
Laravel's schema builder, which maps them per driver.

## Modules

Seven modules, each with `Domain/ Http/ Policies/ Database/ Jobs/ Contracts/`.
A module may depend only on modules in a **strictly lower tier**:

| Tier | Modules |
| --- | --- |
| T0 | Platform |
| T1 | Security |
| T2 | Customers |
| T3 | Tickets |
| T4 | Sla, Email, Portal — these three must not call each other |

Two further rules are enforced by tests rather than by deptrac, because they are
about *what* is imported rather than *from where*:

- No module imports another module's Eloquent models
  (`tests/Architecture/NoCrossModuleModelsTest.php`). Models are a module's
  private storage representation; `Contracts/` is the public surface.
- `Contracts/` imports nothing outside itself, `Illuminate\Contracts\`, or PHP
  built-ins (`tests/Architecture/ContractsPurityTest.php`). A contract that
  depends on a concrete class exports that dependency to every consumer.

Each module carries a `Domain/Placeholder.php`. Deptrac reasons about classes,
not directories, so an empty module contributes no nodes and would pass every
rule vacuously. **Delete the placeholder when a module gains real code.**

Every architecture test also asserts that its rule *rejects* a known-bad
fixture (`tests/Fixtures/ArchViolations/`). Without that, a misconfigured rule
that silently passed everything would be indistinguishable from a healthy
codebase.

## Authentication

Sanctum in **SPA cookie mode**. No token is ever issued: the session lives in an
http-only cookie the browser attaches by itself and JavaScript cannot read, so
"no credential is ever handled by client JavaScript" holds by construction
rather than by discipline.

### Two identity spaces

| | Staff | Portal customers |
| --- | --- | --- |
| Guard | `web` | `portal` |
| Table | `users` | `portal_accounts` |
| Reset tokens | `password_reset_tokens` | `portal_password_reset_tokens` |
| Model | `App\Models\User` | `App\Modules\Portal\Domain\PortalAccount` |

There is **no `is_staff` column** and no shared table with a discriminator. A
flag is a value someone can set wrong; two tables make the question unanswerable
in the wrong direction — the portal guard queries `portal_accounts` and cannot
see a staff row at all. The same address may exist in both spaces with different
passwords, and neither works in the other.

The two modules never import each other. They meet only in `config/auth.php`, by
class-string, which is outside `app/Modules`. Enforced by
`tests/Feature/Security/GuardIsolationTest.php` and
`tests/Architecture/IdentitySpaceIsolationTest.php`.

### Endpoints

| Route | Notes |
| --- | --- |
| `POST /api/v1/auth/login` | `throttle:login` — 5/min per email+ip |
| `POST /api/v1/auth/logout` | Invalidates the session and re-issues a CSRF token |
| `GET /api/v1/auth/me` | The signed-in staff member |
| `GET /api/v1/auth/session` | `inactivity_minutes` so the client can warn before a lapse |
| `POST /api/v1/auth/password/forgot` | Always `202` — see below |
| `POST /api/v1/auth/password/reset` | Separate, more generous limit |
| `GET`/`PATCH /api/v1/profile` | Name and language |
| `POST /api/v1/profile/password` | Requires the current password |

These POSTs are **exempt from `Idempotency-Key`** (`withoutMiddleware`). That
middleware stops a retried *write* creating a second record; a session operation
creates none, and a replayed sign-in cannot carry a `Set-Cookie`, which would
make the replay actively wrong. `OpenApiContractTest` pins the exemption list so
a future write cannot join it silently.

### Decisions worth knowing

- **No enumeration.** Wrong password and unknown account return an identical
  body; forgot-password always returns `202`, even when throttled. Otherwise
  either endpoint becomes a way to test a list of addresses and learn who works
  here.
- **The policy governs setting a password, not using one.** Sign-in does not
  validate against `password_policy`: doing so would reveal the shape of a valid
  password and lock out anyone whose password predates a policy change.
- **Limits are keyed on email AND ip.** IP alone lets one attacker behind a NAT
  lock out an office; email alone lets a distributed attacker lock a named person
  out of their own account.
- **Requesting and redeeming a reset have separate budgets.** Sharing one
  3-per-10-minutes budget locks a user out of finishing their own reset after two
  mistyped passwords.
- **Session expiry is `security.session_expired`, not a bare 401.** The client
  must tell "your session ended, come back here" apart from "you were never
  signed in". Platform stays generic (`platform.unauthorized`); Security narrows
  it through `ProblemDetailsHandler::extend()`, so T0 never names a higher tier's
  vocabulary.
- **Sign-in success and failure both dispatch `StaffAuthAttempted`**, which
  carries no credential of any kind. A listener writes to the `audit` channel —
  `TODO(Story 2.4)` replaces the listener, not the event.

## Authorization

Four roles, seeded once, with a fixed capability matrix. There is no role
builder and no endpoint that writes roles or permissions — `RolesAndPermissionsSeeder`
is the only writer, so the whole authorization model is one reviewable file that
is identical in every environment.

### The matrix

| Capability | Administrator | Supervisor | Agent | Customer |
|---|:-:|:-:|:-:|:-:|
| `user.manage` | ✅ | | | |
| `department.manage` | ✅ | | | |
| `role.read` | ✅ | ✅ | | |
| `audit.read` | ✅ | | | |
| `setting.manage` | ✅ | | | |
| `ticket.read` | ✅ | ✅ | ✅ | ✅ |
| `ticket.create` | ✅ | ✅ | ✅ | ✅ |
| `ticket.update` | ✅ | ✅ | ✅ | |
| `ticket.reassign` | ✅ | ✅ | | |
| `ticket.close` | ✅ | ✅ | ✅ | |
| `customer.read` | ✅ | ✅ | ✅ | |
| `customer.manage` | ✅ | ✅ | | |

Capabilities are `resource.action`, **never** with a `.scope` suffix. Scope is a
row-level question and belongs in a query — baking it into a permission name
produces a combinatorial explosion nobody can audit and hides the row rule where
no test looks.

**Administrator holds everything implicitly** through a `Gate::before`, and has
zero rows in `role_has_permissions`. A capability added in a later story is one
they already have — no seeder re-run, and no window where the person who fixes
permissions is the one locked out.

### Refusals

`can.capability:<name>` on the route. A refusal is `403 application/problem+json`
naming **what** was refused and **who** to ask:

```json
{ "code": "security.forbidden", "title": "Forbidden",
  "detail": "You do not have permission to ticket.reassign. Ask your administrator.",
  "capability": "ticket.reassign", "contact": "administrator" }
```

Hiding a control in the UI is a suggestion; the middleware is the refusal, and it
runs whether or not a UI was involved. A route naming a capability that does not
exist throws immediately rather than failing closed and looking like working
security until an administrator is locked out — `CapabilitiesInSyncTest` catches
it earlier still.

Middleware order is authenticate → account-is-live → authorize → idempotency, so
a caller who was never allowed to make the request is told *forbidden* rather
than *missing Idempotency-Key*.

### Row-level visibility

`App\Modules\Tickets\Domain\Query\TicketVisibility` is the **only** place the
rule lives: Agent sees own + unassigned, Supervisor and Administrator see all,
Customer sees only their own, anyone else sees nothing.

Deliberately **not** a global Eloquent scope. A global scope applies invisibly —
it silently filters exports, reports and jobs that legitimately need every row,
and `withoutGlobalScope` removes the rule entirely rather than adjusting it. An
explicit call is greppable. `NoGlobalScopesTest` fails the build if one appears.

**Department is a grouping and a filter, not a boundary.** A ticket surfacing
outside the caller's department is not a leak.

### Users and departments

- Both are **deactivated, never deleted**. A deleted user orphans every
  historical attribution pointing at them; a deleted department orphans the
  department of everyone who ever belonged to it.
- Deactivating a user drops their tokens *and* their session rows, and
  `EnsureActiveUser` refuses on the next request regardless — a session this
  process did not delete must not survive. Sign-in itself is refused too, so a
  disabled account never receives a cookie it can never use.
- Deactivating a department holding active tickets is **refused with a count and
  a path**, not confirmed away. A confirmation dialog would let someone strand
  real work with one click and no way to find it again.

### Why the usage probe points the way it does

`DepartmentsController` needs to know whether tickets still reference a
department. Security is T1 and Tickets is T3, and dependencies may only run
downward — so Security declares `Contracts\DepartmentUsageProbe` and Tickets
implements it. The story plan proposed the reverse (a contract published from
Tickets, injected into Security), which deptrac rejects.

## Settings

Anything an administrator is meant to change at runtime lives in the settings
registry, not in `config/` and never in `.env`.

### Why not `env()`

Laravel caches configuration in production. After `config:cache` has run, every
`env()` call outside a config file returns `null` — so a value read that way
works on a developer's machine, passes review, and then silently evaluates to
null on the deployed box. There is no exception and no log line; the feature
just behaves as though it were switched off.

`tests/Architecture/NoEnvOutsideConfigTest.php` fails the build if any file
under `app/`, `routes/`, `database/` or `bootstrap/` calls `env(`.

### Declaring a setting

A module implements `Platform\Support\Settings\RegistersSettings` on its
service provider:

```php
public function registerSettings(SettingsRegistry $registry): void
{
    $registry->register(new SettingDefinition(
        key: 'tickets.auto_close_hours',
        type: SettingType::Int,
        default: 168,
        summary: 'Hours a resolved ticket waits before closing itself.',
        validator: fn (mixed $v): bool|string => is_int($v) && $v >= 1 && $v <= 2160
            ? true
            : 'Auto-close must be between 1 hour and 90 days.',
    ));
}
```

`PlatformServiceProvider` collects every such provider **inside the registry's
own container binding**, not in a `booted()` hook. A hook populates only the
instance that existed when it fired; drop that singleton — as a test or a queue
worker between jobs may — and the next resolution comes back empty with every
key reported unregistered.

Platform is T0 and cannot import Tickets, Sla or Email, so the interface is
declared low and implemented high. Same inversion as the usage probes above.

### What the registry guarantees

- **A key that was never declared cannot be read or written.** `set()` on an
  unknown key is a 404, not a new row — the table is a registry, not a
  key-value dumping ground.
- **One validator, every writer.** The rule lives on the definition, so the HTTP
  controller, a console command and a future importer are all judged by the same
  check rather than by whichever one its author remembered.
- **A refusal names the bound.** `platform.setting_invalid` carries the
  validator's own message ("Auto-close must be between 1 hour and 90 days"), not
  "invalid value".
- **A write is visible to the very next read.** `set()` busts the cache *and*
  the per-request memo, so "takes effect immediately" means this request.
- **A stored value that stops passing falls back to the default.** Tightening a
  validator after a value was written cannot leave the application reading
  something the current rules forbid.
- **Secrets are redacted on the way out and never on the way in.**
  `all()` masks them to `••••••••`; `get()` still returns the real value to the
  code that needs it. An unset secret masks to `null`, not to dots — otherwise
  the console shows a password for a mailbox nobody configured.
- **The key set is pinned.** `SettingsRegistryTest` asserts the exact list,
  because these keys are strings in the frontend's TSX too: a silent rename
  renders an empty panel that reads as "not configured yet".

### Cache

`SettingsCache` wraps `Cache::remember` in a try/catch and falls through to a
direct repository read, logging a warning. A cache outage makes settings slower,
not unavailable.

### Fixed sets that are NOT settings

The four priorities (`Tickets\Domain\Priority`) are an enum with no write route
at all — not a route that answers 403. `GET /admin/priorities` returns
`editable: false` so the console can say so out loud. Categories are flat by
construction: `ticket_categories` has no `parent_id` column, so a client that
sends one gets a flat category rather than a silently-ignored hierarchy.

Quick replies are stored as ONE settings key rather than a table. They are an
ordered list of a few dozen short strings, always read together, and their order
is the whole of their structure — a table would add a join, a sort column to
keep contiguous, and migrations, to model an array. Reordering requires the
complete list of ids: a partial list is refused rather than deleting whatever
was omitted, because a reorder that loses rows is the worst kind of destructive
action — nobody asked to delete anything.

## The audit log

Append-only, owned by Platform (T0) so every module can write to it downward.

### Immutability is enforced three times

Each layer covers a hole the others leave:

1. The **table** has no `updated_at` and no soft-delete column — there is
   nowhere for a mutation to record itself. It also has no foreign key into
   `users`, so deleting a user cannot cascade away the record of what that user
   did, and `actor_label` is denormalised so the log survives a rename.
2. The **model** throws `LogicException` on any save after the first insert, on
   `update()`, and on `delete()`. Code reaching for the obvious Eloquent verb
   fails loudly at the call site instead of quietly succeeding.
3. The **HTTP surface** registers only `GET`. No `apiResource` — that helper
   registers five routes, two of which mutate. With none registered the router
   answers 405, which is a stronger guarantee than a controller method that
   chooses to refuse.

The migration additionally attempts `REVOKE UPDATE, DELETE` at the database role
level. Best-effort and swallowed on failure: hosted databases routinely deny
`GRANT OPTION` to the application role, and a deploy failing for that reason
would be worse than a table protected only by the application.

Belt, braces and a second belt is proportionate here. An audit log that can be
edited is not a weaker audit log — it is worthless, because the one thing it is
for is being trustworthy after the fact.

### The writer

`AuditWriter` is the only way an entry comes into existence. It inserts through
the **query builder**, not the model: the model refuses every mutation (poor
thing to insert with), and a model insert runs `creating`/`created` hooks — a
hook added later by someone solving an unrelated problem is how recorded content
starts differing from what happened.

Actor, request id and client IP come from `RequestContext`, not from the call
site, so every writer inherits the same facts and none can invent a different
answer. `source_ip` in particular is read from there because behind a load
balancer a direct `$request->ip()` returns the balancer's address, and an audit
log full of one internal IP has no source in it at all.

### Redaction happens at write time

`AuditRedactor` strips credential-shaped values **before** they are stored.
Redacting on read would mean the secret really is in the database and the
protection is a formatting choice — one raw query or one future endpoint away
from being undone.

It matches on the KEY, not the value: value-shaped detection both misses
hand-written passwords and mangles legitimate data, while the key is the part a
developer controls. A key matching `/password/i` is replaced **whole**, even when
its value is an object — publishing the structure of a credential is itself a
hint worth withholding. The patterns live in `config/audit.php`, so a
newly-invented credential-shaped key is a config line rather than a deploy.

### Attribution

A **failed** sign-in records `actor_type = guest` with the attempted email as the
label and no actor id. Whoever typed that address has not proved they own it, and
filing the attempt under that person's name would put a stranger's action against
an innocent account.

`AuditActorType` mirrors `RequestContext::ACTOR_TYPES` exactly, and a test asserts
they still agree — two vocabularies for one concept is how a log records
`anonymous` while the correlated log line says `guest`.

### Reading it

`audit.read`, which by the role matrix only an administrator holds. A supervisor
manages people; reading every action every colleague has taken is a different
power. The refusal leaks nothing — not even a count.

Three filters and no more: actor, action, date range. An audit log invites making
everything filterable, which produces a query nobody can index and a screen
nobody can read. Unknown parameters are ignored rather than rejected, so a
bookmarked URL from a future version still returns rows.

Paging orders by `(occurred_at DESC, id DESC)`. Without the ULID tiebreak,
entries sharing a millisecond come back in an order the database may change
between pages — which silently duplicates some rows and drops others.

## Customers

### Contact details are their own table

`contact_identifiers`, never columns. A person has several emails and several
phones, and a schema with `email` and `email2` is one that will need `email3`.

Both the raw value and a normalised form are stored. The raw one is what the
customer gave us and what gets displayed and dialled; the normalised one is what
search and duplicate detection compare — so `+44 20 7946 0958` and
`020 7946 0958` are recognised as one number without rewriting what anyone typed.

Phones normalise to their **trailing** ten digits: a country code and a trunk
prefix both sit at the front, and the front is exactly what differs between two
spellings of one number. Deliberately loose — matching two different people who
share ten trailing digits offers a duplicate a human dismisses, while matching
neither loses it entirely, and only one of those failures is expensive.

The unique index is `(customer_id, kind, value_normalised)` — within a customer,
not across them. Two people in a household genuinely share a landline.

### Duplicate detection offers, never blocks

A create whose identifiers match an existing customer returns **409 with the
matches attached**, and resubmitting with `confirm_create_duplicate` creates the
record. The database was always willing; the refusal is a conversation.

What this catches is the accidental case — the same customer entered twice
because nobody searched first. A constraint would also catch the legitimate case,
and an agent works around a constraint while a real person waits on the line.

Inactive customers are included in the matches: someone returning after two years
is exactly the duplicate worth catching, and their old record already holds the
history a new one would lack.

### Search has two implementations

Postgres uses `pg_trgm` similarity — typo-tolerant, ranked, and indexed by two
GIN indexes. That is what production runs.

Every other driver falls back to `LIKE` containment. The test suite runs on
in-memory SQLite, so **most tests exercise the fallback**, which is why
`CustomerSearchPostgresTest` exists: it runs against a real Postgres when one is
reachable and asserts the trigram path, the indexes, and the CHECK constraints.
It **skips loudly** when Postgres is absent rather than passing silently — a
search tested only on a driver production does not use is a search nobody has
tested. Set `DB_TEST_*` to point it at a database.

Both paths search name, email, phone and reference at once rather than behind a
"search by" dropdown: an agent with a caller on the line has one fact to hand and
should not have to say which kind it is. A phone search needs at least four
digits, or a reference like `C-3AB` would yield the fragment `3` and match
everyone whose number contains a three.

Identifier scores come from a correlated sub-select, not a join — a customer with
four identifiers would otherwise appear four times and break both the count and
the paging.

### Deactivation, never deletion

`state` is `active` or `inactive`. The record is the anchor for every ticket, note
and interaction attached to it, so removing the row would orphan all of that.
Deactivated customers are absent from search by default and **still resolve by
id**: a link in a two-year-old ticket must open the person it refers to, because a
404 there reads as data loss.

### Department is grouping, never access

Every customer carries a `department_id`. It filters and it groups; whether an
agent may see a customer is a capability question, and the UI says so out loud so
nobody assumes the filter is doing security work.

The table is Security's — the one created in the users-and-roles story. Customers
(T2) depends downward on `Security\Contracts\DepartmentDirectory` rather than on
the Eloquent model, so how a department is stored stays Security's business. One
product concept with two tables is two lists that drift apart.

`department.read` is separated from `department.manage`: every staff member needs
the list to file a customer under a team, and none of them may edit it.

### No organisation

There is no organisation, company or account concept, and
`tests/Architecture/NoOrganisationFieldTest.php` fails the build if one appears.
It is out of scope by decision, and the way that decision gets reversed is not a
design discussion but somebody adding a "company" column because a form had a
spare field. Modelling organisations properly means hierarchy, membership,
billing ownership and a merge story; a lone string column looks like the same
feature, delivers none of it, and quietly becomes impossible to remove.

The guard reads **code, not comments** — via `SourceScanner::codeOnly()` — because
a guard that flags the comment explaining its own rule is an incentive to write
worse comments.

## Attachments

Owned by Platform (T0) so Customers (T2) and Tickets (T3) can both use it
without either depending on the other. The owner is a `(owner_type, owner_id)`
pair and **not** a foreign key: a real FK would have to point at one table, and
a column per possible owner is the same coupling written out longer.

The cost is accepted deliberately — the database cannot check that an owner
exists. For a customer we could look it up; for a ticket or a message, Platform
cannot see those modules at all. So the id is accepted if it is ULID-shaped, and
existence is the owning module's business. An attachment pointing at nothing is
invisible rather than dangerous: the only read is "everything attached to THIS
record".

### The upload path

Every check runs BEFORE anything is written, so a rejected upload leaves no
bytes on disk. A rejection that still wrote the file is a UI opinion, not a rule.

1. **Size**, against the current setting.
2. **Sniff** the contents with `finfo` — never `getClientMimeType()`. The
   client's value is a claim typed by whoever is uploading; trusting it is the
   same as having no allow-list, because a shell script announced as `image/png`
   would sail straight through one.
3. **Allow-list**, against the sniffed type.
4. **Mismatch**: the claim disagreeing with the contents is refused even when the
   contents are allowed. Not dangerous by itself, but it is either a broken
   client or a deliberate attempt, and both are worth refusing loudly.

Both limits come from the settings registry and are read at validation time, not
cached at boot. An administrator who tightens the allow-list expects the next
upload to obey it, not the next deploy.

### Quarantine is a prefix, not a flag

Files land under `quarantine/` and move to `clean/` only after a scan passes.
The prefix IS the state, so the filesystem and the row cannot disagree about
whether something has been checked, and a bug in the state machine cannot make
an unscanned file reachable.

`downloadable` is derived from `scan_status` at read time and never stored. A
column would be a second source of truth that a half-finished job could leave
disagreeing — and the direction it disagreed in would be the dangerous one.

### The three scan outcomes

| Outcome | File | Row |
|---|---|---|
| clean | moves to `clean/` | `scan_status=clean`, downloadable |
| failed | **stays** in `quarantine/` | `scan_status=failed`, reason recorded |
| unreachable | stays in `quarantine/` | unchanged — still `pending` |

The third is the safety property. Treating an outage as clean turns a scanner
going down into a delivery mechanism; treating it as failed tells a customer
their invoice contains a virus. `ScannerUnreachable` is a separate exception
from a failed scan precisely so the two cannot be confused.

A failed file is **not deleted**. An incident review needs the evidence, and
deleting it would also delete the record of what was uploaded.

`ScanAttachmentJob` swallows `ScannerUnreachable` rather than throwing, and
calls `release()` to retry when there is a queue to retry on. Throwing would
mark the job failed — and on a synchronous queue the exception would surface
inside the upload request and turn a scanner outage into "uploads are broken",
which is exactly what this design exists to prevent. A test asserts an upload
still returns 201 with the scanner down, running the job inline.

The move happens **before** the status update. A crash between them leaves the
file readable but the row still `pending` — and pending is not downloadable, so
the failure is safe and the retry fixes it. The reverse order would mark a file
downloadable while it was still in quarantine.

### Download is a short-lived link

`GET /attachments/{id}/download` answers 403 unless the scan passed, and
otherwise 302 to a signed URL that expires in five minutes — long enough to
click, short enough that a URL pasted into a chat thread is dead before anyone
else opens it. The redirect carries `Cache-Control: no-store`, because the
Location holds a credential and a cached 302 would hand it to the next person on
a shared machine.

On S3 the link is signed by storage and validated at its edge, so the bytes
never pass through the application. A local disk has no edge, so the application
signs a route of its own and streams the file — a development convenience, not a
second design. `StorageSignedUrlIssuer` chooses by **driver**, not by
`providesTemporaryUrls()`: Laravel's fake disk cheerfully claims the capability
and returns a URL nothing serves, which would let a test pass against a link no
browser could follow.

That local route re-checks the scan status. A valid signature proves the link
was issued, not that the file is still safe to hand over.

### A clean file can still be dangerous

`SafeContentType` coerces `text/html`, `application/xhtml+xml`, `image/svg+xml`,
XML, PDF and anything with `script` in its type to `application/octet-stream`.
A virus scanner has nothing to say about stored XSS: an HTML document served
from a domain the user trusts runs its script in that origin, and it contains no
virus at all. SVG is on the list because it is an image everywhere except in a
browser, where it is a document that can carry `<script>`.

There is **no inline preview endpoint**, and there will not be one.

Filenames are preserved verbatim, including non-Latin scripts, and emitted as
both `filename` (ASCII fallback) and `filename*=UTF-8''` per RFC 5987. Quotes
and backslashes are stripped from the fallback, or a filename could end the
parameter early and become header syntax.

## Notes on customers

`customer_notes`, not a shared `notes` table: a bare one invites every module to
put its own notes in it, and then the table means nothing in particular.

The author's **name** is stored alongside their id, so a note still says who
wrote it after that person leaves. A join would lose the name at exactly the
moment someone is working out who knew what.

Reading and writing need only `customer.read`. Recording what a caller said is
part of handling the call; an agent who could see a customer but not note
anything would keep that knowledge in their own head.

Editing and deleting are about **authorship**, not role:

- Only the author may edit, and that includes supervisors and administrators.
  Rewriting what a colleague said, in their name, leaves no trace that it
  happened.
- The author **or** anyone with `customer.manage` may delete. Someone has to be
  able to take down a note written in anger or holding a card number — and
  unlike an edit, a deletion is visible.

An edited note is flagged, so a reader knows the text is not what was originally
written.

## Errors

Every 4xx/5xx response is an RFC 9457 problem document produced by
`Platform/Http/ProblemDetailsHandler.php`, registered once in
`bootstrap/app.php`:

```json
{
  "type":     "https://errors.ragab-crm/platform.not_found",
  "title":    "Resource not found.",
  "status":   404,
  "detail":   "No resource matches the requested URI.",
  "instance": "/api/v1/customers/42",
  "code":     "platform.not_found",
  "trace_id": "01M1CDXTBQS6YACZQ6649PFFPP"
}
```

`code` is the stable machine identifier, shaped `module.condition` and validated
against that pattern in `ProblemDetails`'s constructor — a malformed code fails
where it is written, not in a consumer's error branch. **Clients branch on
`code`, never on `title`.**

To raise a specific error from module code, throw a `ProblemException`:

```php
throw ProblemException::make('customers.not_found', 'Customer not found.', 404, "No customer with id {$id}.");
```

Controllers must **not** build error responses.
`tests/Architecture/NoControllerErrorBodiesTest.php` scans `Http/` for
`response()->json(..., 4xx)` and friends and fails the build.

## Idempotency

`POST`, `PUT`, `PATCH` and `DELETE` require an `Idempotency-Key` header (ULID or
UUID). The middleware reserves a row, runs the handler and stores the response:

| Situation | Result |
| --- | --- |
| Missing or malformed key | 400 `platform.validation_failed` |
| First call | Executes, stores status + headers + body |
| Repeat, same body | Replays the stored response |
| Repeat, different body | 409 `platform.idempotency_conflict` |
| Concurrent call still running | Polls 100 ms × 30, then 425 `platform.idempotency_in_flight` |
| 5xx response | Key released, so a retry genuinely re-runs |
| Older than 24 h | Treated as fresh; pruned nightly by `platform:prune-idempotency-keys` |

Two deliberate details: the stored headers exclude `Date`, `X-Request-Id` and
`Set-Cookie`, so a replay reports the correlation id of the request the caller
actually made rather than resurrecting a stale one; and bodies are base64-encoded
into the `bytea` column so the round trip is byte-exact on every driver.

## Logging

### Stream discipline

The web container splits its output deliberately:

| Stream | Carries |
| --- | --- |
| **stdout** | the application log — one JSON object per line, nothing else |
| **stderr** | server lifecycle, the startup migration, entrypoint diagnostics |

That split is why `web` mode drives `php -S` with `docker/router.php` rather
than `php artisan serve`: Artisan funnels the dev server's access log through
its own output onto stdout, interleaved with the JSON, and `-q` silences the
application logs along with it. Verify with:

```bash
docker logs ragab-crm-backend-web-1 2>/dev/null   # stdout only: every line is JSON
docker logs ragab-crm-backend-web-1 2>&1 1>/dev/null   # stderr only
```

(`docker compose logs` merges both streams, so use `docker logs` to see them
apart.)

### Fields

`LOG_CHANNEL=json` emits one JSON object per line on stdout, with
`request_id`, `actor_type`, `actor_id`, `module` and `ticket_id` hoisted to the
top level — present on every line, null when unknown.

`ticket_id` is per-call: set it with `Log::withContext(['ticket_id' => $id])` and
the processor prefers it over the ambient value.

`actor_type` is resolved lazily at log time rather than snapshotted, because
`AssignRequestId` runs before `auth:sanctum`: a line written early in a request
would otherwise always say `guest`.

## Processes

One image, three commands (`docker/entrypoint.sh`):

| Command | Runs |
| --- | --- |
| `web` | migrations, then `php -S` via `docker/router.php` (or php-fpm when `APP_SERVER=fpm`) |
| `worker` | `queue:work --queue=default --tries=3 --backoff=5,15,60 -q` |
| `scheduler` | `schedule:work -q` |

The `-q` on the two console processes silences Artisan's own per-job lines,
which would otherwise interleave plain text with the JSON on stdout. It does
**not** silence the application log: the json channel writes to `php://stdout`
directly, outside Symfony Console, so failures still surface as structured
lines. (This is exactly why `-q` is not usable for `artisan serve` — there,
Artisan pipes the child server's output through its own, so quiet mode takes the
JSON with it.)

Only `web` runs migrations, so the three services do not race on a cold start.
Each uses `exec`, so SIGTERM reaches PHP directly and the worker can finish its
current job before exiting.

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

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

# Ragab CRM

Two separately deployable applications that speak only through a generated,
typed contract.

```
backend/    PHP 8.5 / Laravel 13 API          -> composer.json, its own CI pipeline, its own image
frontend/   Node 24 / Next.js 16.3 UI         -> package.json,  its own CI pipeline, its own image
```

The repository root holds no application code — only these two directories, the
Compose file for local development, the CI definitions, and this README.

## The contract

`backend/openapi.yaml` is generated from the Laravel routes.
`frontend/lib/api/schema.ts` is generated from that document. Neither is
hand-edited, and CI fails if either is stale, so the UI cannot drift from what
the API actually returns.

```
routes/api.php  ──generate──>  backend/openapi.yaml  ──generate──>  frontend/lib/api/schema.ts
                 (composer          committed              (pnpm api:generate)
                  openapi:generate)  contract
```

The frontend never reads backend source. In CI it consumes `openapi.yaml` as a
build artifact published by the backend pipeline, so the frontend job can run
with the backend directory entirely absent. Tests on both sides prove the
absence of cross-imports:

- `backend/tests/Architecture/NoCrossImportTest.php`
- `frontend/__tests__/no-cross-import.test.ts`

## Running the stack

```bash
docker compose up --build
```

Five services start: `postgres`, `backend-web`, `backend-worker`,
`backend-scheduler`, `frontend`.

```bash
curl -i http://localhost:8000/api/v1/healthz     # {"status":"ok"} + X-Request-Id
curl -i http://localhost:8000/api/v1/nope        # 404 application/problem+json
open http://localhost:3000                       # the Next.js default page
```

The three backend services run the **same image**; only the command differs
(`web`, `worker`, `scheduler`). That is what keeps them running identical code
while scaling and failing independently. The queue uses the **database** driver
— this system runs no Redis, no search engine and no message broker.

## Running each side on its own

That the two pipelines never reference each other is the point, so each is run
independently:

```bash
# backend
cd backend
composer install
php artisan test                                              # unit + feature + architecture
vendor/bin/deptrac analyse --config-file=deptrac.yaml --fail-on-uncovered
vendor/bin/deptrac analyse --config-file=deptrac-tiers.yaml --fail-on-uncovered
php artisan openapi:check                                     # fails if openapi.yaml is stale
php scripts/check-no-cross-import.php

# frontend
cd frontend
pnpm install --frozen-lockfile
pnpm run check:next-version                                   # pins the 16.3 security release
pnpm tsc --noEmit
pnpm eslint .
pnpm test
pnpm run api:check                                            # fails if schema.ts is stale
pnpm run check:no-cross-import
```

## CI

Two workflows, each triggered only by changes to its own directory:

| Workflow | Jobs |
| --- | --- |
| `.github/workflows/backend.yml` | install · test · module boundaries + tier ordering · openapi contract (publishes `openapi.yaml`) · no cross-import · build image |
| `.github/workflows/frontend.yml` | next version pin · typecheck · lint · test · no cross-import · design tokens · **accessibility (axe)** · build · generated client is not stale · build image |

Images are tagged by git SHA; rollback is redeploying the previous SHA.

## Cross-cutting API behaviour

| Concern | Where | Summary |
| --- | --- | --- |
| Errors | `backend/app/Modules/Platform/Http/ProblemDetailsHandler.php` | Every 4xx/5xx is RFC 9457 `application/problem+json` with a stable `code` shaped `module.condition`. One handler produces all of them; no controller writes an error body, and a test fails the build if one tries. |
| Idempotency | `backend/app/Modules/Platform/Http/Middleware/IdempotencyKey.php` | Writes require an `Idempotency-Key` (ULID or UUID). A repeat with the same body replays the stored response; a repeat with a different body returns 409. |
| Correlation | `backend/app/Modules/Platform/Http/Middleware/AssignRequestId.php` | Every response carries `X-Request-Id`, which is also the `trace_id` in problem bodies and the `request_id` in the logs. |
| Logging | `backend/app/Modules/Platform/Logging/` | One JSON object per line on **stdout**, always carrying `request_id`, `actor_type`, `actor_id`, `module`, `ticket_id`. Server lifecycle noise goes to stderr, so stdout is safe to ship verbatim. |

## The design system

`frontend/tokens/tokens.css` is the single source of every design value, split
into primitives (which only Tailwind reads) and semantic aliases (which are the
only names a component may use). Three lint rules keep it honest: logical
utilities only, semantic tokens only, and screens may not import Layer-A
primitives.

The UI is bilingual from the floor up — `<html dir lang>` is bound to the active
locale, IBM Plex Sans and IBM Plex Sans Arabic are self-hosted, and every numeric
run is bidi-isolated so a date range does not reverse inside Arabic prose.

See `frontend/README.md` for the full contract, including why the dark theme is
deliberately unpopulated.

## Bilingual shell

English is the default; Arabic is a per-user preference persisted in the
`ragab-locale` cookie and applied on every later session. Switching flips
`<html dir>` to `rtl` and the whole chrome mirrors — from one attribute, with no
second stylesheet and no mirroring code.

Every user-facing string lives in `frontend/messages/{en,ar}.json`; every date
and number goes through `frontend/lib/format/`. Both are lint-enforced, and the
Arabic locale is pinned to `ar-u-ca-gregory-nu-latn` so dates stay Gregorian and
digits stay Western `0-9` on every runtime.

Backend `backend/lang/{en,ar}/` holds server-rendered artefacts only — emails and
persisted notifications. No key is duplicated across that boundary.

See `frontend/README.md` for the full contract.

## Responsive and accessible by default

Three responsive bands — `mobile` (0–767), `tablet` (768–1023), `desktop`
(1024+) — are the only breakpoints in the product; bare Tailwind screens are
lint errors and are deleted from the theme so they cannot silently work.

Tables give up horizontal room in one of two documented ways: **fold** for lists
you scan, **scroll with a pinned identity column** for tables you compare.
Neither ever drops a value.

Axe runs in CI against every page and component fixture in both writing
directions and fails the build on any WCAG 2.1 AA violation.

See `frontend/README.md`.

## Authentication

Sanctum in SPA cookie mode: the session is an http-only cookie, no token is ever
issued, and nothing is written to `localStorage` or `sessionStorage`.

Staff and portal customers are **two identity spaces** — two tables, two guards,
two reset-token tables, no `is_staff` column. A credential valid in one is
invisible to the other. Passwords are bcrypt-hashed, never returned by any
endpoint and never logged; reset links are single-use, expiring, and hashed at
rest.

Session expiry returns `401 security.session_expired`, and the frontend
redirects to `/sign-in?redirect=…` **without clearing web storage**, so an
unsent composer draft survives.

See `backend/README.md` for the endpoint table and the reasoning.

## Modules and tiers

Seven modules under `backend/app/Modules/`, each with `Domain/ Http/ Policies/
Database/ Jobs/ Contracts/`. A module may depend only on modules in a strictly
lower tier, and the three T4 modules must not call each other:

```
T0 Platform  ->  T1 Security  ->  T2 Customers  ->  T3 Tickets  ->  T4 { Sla, Email, Portal }
```

The ordering is declared once in `backend/app/Modules/module-tiers.php` and
enforced by deptrac plus the tests in `backend/tests/Architecture/`. Those tests
also assert that the rules **reject** a known-bad fixture, so a ruleset that
silently passed everything would fail the build rather than look healthy.

As of Story 1.1 the seven modules are empty scaffolds apart from Platform.

## Versions

| | Required | Notes |
| --- | --- | --- |
| PHP | 8.5 | Images run 8.5. See the note at the top of `backend/README.md` about the local toolchain. |
| Laravel | 13.x | 13.29 |
| PostgreSQL | 18.6 | Chosen over MySQL for trigram / tsvector search in later epics. |
| Node | 24 LTS | |
| Next.js | 16.3.3 | The security release that superseded 16.3.2. Never 16.3.2 — enforced by `frontend/scripts/check-next-version.mjs`. |
| React | 19.2.x | 19.2.8 |
| TypeScript | 5.x, `strict` | Plus `noUncheckedIndexedAccess` and `exactOptionalPropertyTypes`. |

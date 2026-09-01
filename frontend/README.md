# Ragab CRM — frontend

Next.js 16.3 App Router UI. Deployed independently of `backend/`; the only thing
the two share is the OpenAPI contract.

Story 1.1 ships **no user-facing screen**. What exists here is the floor: the
generated API client, the version pins, and the guards that keep both honest.

## The generated client

`lib/api/schema.ts` is generated from `backend/openapi.yaml` and is **never**
hand-edited — it carries a do-not-edit banner and CI fails if it differs from a
fresh generation.

```bash
pnpm run api:generate   # rewrite lib/api/schema.ts from the contract
pnpm run api:check      # fail if it is stale
```

The generator reads `frontend/openapi.yaml` if present (CI downloads it as an
artifact from the backend pipeline) and otherwise falls back to
`../backend/openapi.yaml` for local work. That fallback is why a frontend CI run
never needs the backend checked out.

`lib/api/client.ts` wraps `openapi-fetch`:

```ts
import { createApiClient, idempotent, isProblem } from "@/lib/api/client";

const api = createApiClient({ requestId });          // requestId is optional

const { data, error } = await api.GET("/healthz");

if (isProblem(error)) {
  // Branch on the stable machine code, never on the human-readable title.
  if (error.code === "platform.not_found") { /* ... */ }
}
```

### Idempotency

Write operations require an `Idempotency-Key`; the contract marks it required,
so the generated types will not let you omit it. Use `idempotent()`:

```ts
const key = ulid();
await api.POST("/healthz-echo", { ...idempotent(key), body });

// A retry MUST reuse the same key — that is what makes the server replay the
// original response instead of acting twice.
await api.POST("/healthz-echo", { ...idempotent(key), body });
```

A middleware also injects a key on any write that reaches the network without
one. That is a backstop, not the mechanism: a key it generates is unique per
call, so it makes the request valid but cannot make a retry replay. Reach for
`idempotent(key)` whenever retry semantics matter.

## The design system

Established by Story 1.2 (work item 493).

### Tokens

Two tiers, and the distinction is load-bearing:

| Tier | Where | Who may use it |
| --- | --- | --- |
| Primitives | `@theme` in `tokens/tokens.css` | Tailwind, to generate utilities. **Never a component.** |
| Semantic aliases | `:root` + `@theme inline` | Everything. `bg-surface-raised`, `text-fg-muted`, … |

A component that reads `--color-n-800` has hard-coded a decision it does not
own; one that reads `--surface-raised` inherits every future retheme for free.
The ban is enforced by ESLint **and** by `scripts/check-tokens.mjs` — the second
because the first can be switched off with an inline directive.

`@theme inline` is what lets `bg-surface-raised` compile to
`background-color: var(--surface-raised)` rather than to a frozen hex, which is
what will make a dark theme work without touching a single component.

> **Provenance.** Every primitive value is copied verbatim from the `:root` block
> of the normative mockup at
> `.squad/stories/inti/493/attachments/screen-reports.html`. `DESIGN.md` — the
> token authority named by the story — was not in the repository when this
> landed. If it arrives and disagrees, DESIGN.md wins and `tokens/tokens.css` is
> the only file that changes.
>
> **The dark theme is deliberately empty.** The mockup defines no dark palette,
> so `[data-theme="dark"]` exists as a hook with a TODO rather than a fabricated
> set of values. Inventing them would put an invented palette behind a real API
> and let consumers build against it.

### shadcn is a foundation, not a theme

`shadcn init` writes its own neutral oklch palette and a `next/font/google`
loader. Both were removed: the palette is replaced by a compatibility block in
`tokens/tokens.css` that re-points every shadcn variable at our tokens, and the
Google font loader is forbidden by the intake (no external font CDN in the
request path). Radix supplies behaviour and ARIA; the tokens supply everything
visual.

`toast` is hand-built on `@radix-ui/react-toast` because shadcn's Radix base
does not ship one.

### The three rules

| Rule | Enforced by | Why |
| --- | --- | --- |
| Logical utilities only | `design-system/logical-utilities-only` | A physical utility breaks Arabic silently. This is the cheapest guarantee that RTL holds, and it has to be a lint error rather than review discipline. |
| Semantic tokens only, in `components/` | `design-system/semantic-tokens-only` + `scripts/check-tokens.mjs` | A colour literal is a value nobody can retheme. |
| Screens may not import Layer A | `no-restricted-imports` | A screen built from repeated primitives is how six epics end up with six different badges. |

The rules are real ESLint rules (`eslint-rules/index.mjs`), not regex selectors,
because both need to reason about *class tokens* rather than raw text: a regex
cannot tell `ml-2` (banned) from `slide-in-from-left-2` (an animation origin
keyed to Radix's own physical `data-[side]`) without false positives that teach
people to disable the rule.

One sanctioned exception exists, in `components/ui/sheet.tsx`: Radix's `side` is
a physical placement contract, so `side="left"` must open on the physical left in
both directions. It is scoped to a single constant and explained inline.

### Fonts and figures

IBM Plex Sans and IBM Plex Sans Arabic are self-hosted under
`public/fonts/ibm-plex/` (SIL OFL 1.1 committed alongside). The Arabic family
ships **both** its Arabic and its matched Latin subsets, which is what keeps
`TKT-000123` inside Arabic prose in the same face as the prose around it.

Two rules live in `globals.css` and nowhere else:

- **Tabular figures** on `.num`, `[data-numeric="true"]` and
  `td[data-column-type="number"]`. Proportional digits make a column ragged and
  a live timer jitter on every tick.
- **Bidi isolation** (L-08) on the same selectors. Without it `1-31` renders as
  `31-1` inside Arabic prose and a date range silently reverses.

### Component layers

See `components/README.md`. Four folders, `ui/` `domain/` `screens/` `shell/`,
each with its contract.

## Bilingual shell and formatting

Established by Story 1.3 (work item 494).

### One direction switch

`dir` and `lang` are set on `<html>` in `app/layout.tsx` and nowhere else. There
is no second Arabic stylesheet and no per-component `dir` override — every
logical utility (`ms-`/`me-`/`ps-`/`pe-`/`start-`/`end-`) resolves from that one
attribute, and `design-system/logical-utilities-only` keeps a physical utility
from ever creeping in.

`AppShell.test.tsx` asserts the rendered markup is byte-identical under `ltr` and
`rtl`. If a component ever branches on direction, that test fails.

### Locale resolution

```
cookie ragab-locale  ->  Accept-Language (q-values honoured)  ->  English
```

The cookie wins because it is an explicit choice made in this product; the
header is only a hint about the browser. `POST /api/locale` writes it for a
year. English is the default and the fallback.

Two files decide the locale, and both call the same `resolveLocale()`:

- `app/layout.tsx` — for `<html dir lang>` and the client provider.
- `i18n/request.ts` — next-intl's server config, required for the server render.

They cannot disagree.

### Messages

All user-facing text lives in `messages/{en,ar}.json`.
`design-system/no-literal-jsx-strings` makes hard-coded JSX text an error, and
`__tests__/messages-parity.test.ts` fails on key drift, placeholder mismatch,
empty values, and any Arabic string left identical to its English source.

A missing key falls back to English and is reported through
`translationReporter.report` — a swappable object rather than a bare function,
so Story 1.4 can wire the Administrators channel in one line.

### One formatting layer

Every date, number, currency and duration goes through `lib/format/`.
`design-system/no-direct-intl-formatting` makes a direct `Intl.*` or
`toLocale*` call anywhere else an error.

The reason is not tidiness. Bare `ar` resolves to the **Hijri calendar and
Eastern Arabic digits** on some ICU builds and not others, so the product's
answer is written once, as a pinned tag:

```ts
en: "en-US"
ar: "ar-u-ca-gregory-nu-latn"   // Gregorian, Western 0-9, on every runtime
```

Tests assert no `٠-٩` reaches any formatter output in either locale, and that
the same instant produces the same digits in both.

Client components use `useFormat()`, which binds the locale so no call site has
to thread it through.

### Mixed direction

`<BidiValue>` wraps any LTR run inside prose — ticket references, ULIDs, phone
numbers, emails, filenames. Without it the bidi algorithm reorders neutrals at
the boundary: `TKT-000123` renders as `000123-TKT` and `1-31` reverses to
`31-1`, silently, and only in Arabic.

## Responsive bands and the accessibility floor

Established by Story 1.4 (work item 495).

### Three bands, and only three

| Band | Width | Posture |
| --- | --- | --- |
| `mobile` | 0 – 767px | Base. One pane. A thumb. |
| `tablet` | 768 – 1023px | One pane plus a drawer. A finger. |
| `desktop` | 1024px + | Two and three panes. A pointer and a keyboard. |

Bare `sm:` / `md:` / `lg:` / `xl:` / `2xl:` and hand-rolled `@media` are lint
errors (`design-system/no-adhoc-breakpoint`). Tailwind's defaults are also
**deleted** from the theme (`--breakpoint-*: initial`), so a bare variant does
not silently resolve to a real media query — it generates nothing. Belt and
braces: the rule catches it at author time, the theme makes it inert if the rule
is ever disabled.

> The band values come from board R-0 of the mockup at
> `.squad/stories/inti/495/attachments/screen-responsive.html`, whose device
> table places a half-screen laptop (1024–1180px) in the **desktop** band. The
> story plan proposed 1280px for that edge; that would classify a half-screen
> laptop as a tablet, contradicting the design's own table, so the mockup's
> 1024px is used.

### Two collapse mechanisms

`fold` for lists you scan, `scroll` with a pinned identity column for tables you
compare. Neither drops a value. See `components/domain/README.md`.

### Focus indicator

One token, `--focus-ring`, applied once in `globals.css` via `:focus-visible`.
No primitive carries its own ring, and none may carry `outline-none` — a utility
emitted after `@layer base` would silently beat the rule and the indicator would
vanish.

It is an **outline, not a box-shadow**: Windows High Contrast Mode discards
box-shadows, which would make the indicator invisible to precisely the users who
most depend on it.

### Accessibility gate

`pnpm test:a11y` runs axe (WCAG 2.1 A + AA) against every rendered page and
component fixture, in **both** writing directions, and fails CI on any violation.
A violation that only appears in Arabic is the kind that ships, because most
reviewers never read the Arabic build.

The suite includes a guard-the-guard: a deliberately broken fixture that must
produce violations, so a misconfigured axe cannot pass everything silently.

### Mobile file upload

`FileInput` is a real `<input type="file">` inside a wrapping `<label>`, carrying
`accept` and `capture`. No hand-rolled dropzone: a custom shell is invisible to a
phone, breaks the camera affordance, and has to reimplement keyboard and
screen-reader behaviour the native control already has.

## Authentication

`lib/auth/api.ts` wraps the staff auth endpoints. Every call sends
`credentials: "include"`, primes the CSRF cookie first for writes, and echoes
`XSRF-TOKEN` in the header Laravel expects.

**Nothing is stored.** No token is requested or returned, and neither
`localStorage` nor `sessionStorage` is written — a test asserts
`Storage.prototype.setItem` is never called during sign-in.

`SessionExpiryListener` redirects a lapsed session to
`/sign-in?redirect=<current path>` and deliberately **clears nothing**: Story
4.4's composer keeps unsent text under `composer-draft:<ticketId>`, and a session
ending is the product's problem, not the reader's. There is nothing to clear for
security either — cookie mode puts no credential in web storage.

The `?redirect=` value is attacker-controllable, so it is validated to an
in-app path: `//evil.example` starts with a slash and would otherwise navigate
off-site, which on a sign-in page is a phishing primitive.

Screens compose Layer-B form components (`FormField`, `FormAlert`,
`SubmitButton`) rather than raw primitives — `FormField` owns the id/`htmlFor`/
`aria-describedby`/`aria-invalid` wiring that every hand-assembled form gets
subtly wrong.

## Version pins

Node 24 LTS, Next.js **16.3.3**, React 19.2.x, TypeScript 5.x with `strict`,
`noUncheckedIndexedAccess` and `exactOptionalPropertyTypes`.

`scripts/check-next-version.mjs` fails the build on 16.3.2 (explicitly
forbidden) or anything older than 16.3.3. It reads the *resolved* version from
`node_modules`, not the range in `package.json`, because it is the resolved
version that ships.

> The story asks for "the 16.3.x security release dated 2026-08-26 or later".
> 16.3.3 is the release that superseded 16.3.2, but its npm publish timestamp is
> `2026-08-25T15:32Z` — one day earlier than the date quoted. The guard
> therefore keys off the **version**, not the date. If a later 16.3.x ships,
> raise `MINIMUM_VERSION` rather than widening the check.

## Commands

```bash
pnpm install --frozen-lockfile
pnpm dev                      # http://localhost:3000
pnpm run build
pnpm test                     # vitest
pnpm tsc --noEmit
pnpm eslint .
pnpm run check:next-version
pnpm run check:no-cross-import
pnpm run lint:tokens        # no primitives or colour literals in components/
pnpm run test:a11y          # axe, WCAG 2.1 AA, both directions
pnpm run lint:all           # every static gate in one command
pnpm run format             # prettier
```

## Independence from the backend

Nothing here imports from `backend/`. Two guards, deliberately overlapping:

- `no-restricted-imports` in `eslint.config.mjs` — catches it during linting.
- `scripts/check-no-cross-import.mjs` — a text scan over `.ts/.tsx/.js/.jsx/.mjs`
  sources, so a file ESLint never visits is still covered. It scans source only:
  a `.md` file mentioning `../backend` is documentation, not a cross-import.

Both `scripts/check-no-cross-import.mjs` and `__tests__/no-cross-import.test.ts`
are exempt from the scan because they contain the forbidden strings as fixtures,
to prove the scan bites. Nothing else is exempt.

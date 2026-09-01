# Layer B — shared domain components

The components that encode a product decision rather than a widget: what a data
table *is* here, what an empty result looks like, how a row exposes its actions.

Built once so that six epics do not each invent their own.

## Contract

- May import from Layer A (`@/components/ui/*`) and from `@/lib/*`.
- May **not** import from `@/components/screens/**`.
- Same token and direction rules as Layer A.

## What lives here after Story 1.2

| Component | Guarantee |
| --- | --- |
| `DataTable` | Real `<table>` semantics, server-driven sort with `aria-sort`, filter chips + panel, search, column visibility with the identity column **locked not absent**, two collapse modes (below), roving tabindex, focus retention across pagination. |
| `RowActions` | The overflow control is **persistent**, never revealed on hover (UX-02). |
| `EmptyState` | "Nothing happened." May print a count. |
| `ForbiddenState` | "You may not see this." Prints **no numeral** — a count is itself information the reader is not scoped to (UX-06). |

## How a table gives up horizontal room

Two mechanisms, and the choice is about **what the reader is doing**, not about
how many columns there are.

| Mode | For | Mechanism |
| --- | --- | --- |
| `fold` *(default)* | Rows you **open** — tickets, customers, articles, tasks | Secondary columns hide below the desktop band; their values move into a labelled `<dl>` meta line inside the row. |
| `scroll` | Columns you **compare** — reports, SLA compliance, agent performance, the audit log | The table scrolls inside its own container with the identity column pinned to the inline-start edge. |

```tsx
<DataTable mode="fold" columns={ticketColumns} … />          // scanning
<DataTable mode="scroll" columns={auditColumns} … />         // comparing
```

- Mark a foldable column `secondary: true`. A column **without** that flag never
  folds at any band — that is how the design expresses "the SLA never folds": it
  is the reason the list is sorted the way it is, so it keeps its column even
  where Priority and Assignee lose theirs.
- Mark the pinned column `pinned: true` in `scroll` mode. Without it the
  horizontal scroll loses the reader, so `DataTable` logs a dev-time error.
- The two mechanisms are **exclusive**. `pinned` in fold mode and `secondary` in
  scroll mode are both dev-time warnings.

**Neither mode ever drops a value.** Folding moves data, it does not discard it,
and no cell may carry `truncate` or `text-ellipsis` — a clipped value and a
missing value look identical to the reader. Tests assert every fixture value is
somewhere in the DOM in both modes.

Normative rendering: board R-1 of
`.squad/stories/inti/495/attachments/screen-responsive.html`.

## Why bare Tailwind screens are forbidden

Components may reference only `mobile:` / `tablet:` / `desktop:`. Bare `sm:`,
`md:`, `lg:`, `xl:`, `2xl:` and hand-rolled `@media` are lint errors
(`design-system/no-adhoc-breakpoint`), and Tailwind's default screens are
deleted from the theme so a bare variant generates no CSS at all.

Each band is a posture — a thumb, a finger, a pointer. A fourth breakpoint is a
layout designed for nobody.

Established by Story 1.2 (work item 493); collapse modes and the responsive
contract by Story 1.4 (work item 495). See also Story 1.3 (work item 494) for the
RTL logical properties that make the pinned column work in Arabic.

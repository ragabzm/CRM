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
| `DataTable` | Real `<table>` semantics, server-driven sort with `aria-sort`, filter chips + panel, search, column visibility with the identity column **locked not absent**, responsive collapse, roving tabindex, focus retention across pagination. |
| `RowActions` | The overflow control is **persistent**, never revealed on hover (UX-02). |
| `EmptyState` | "Nothing happened." May print a count. |
| `ForbiddenState` | "You may not see this." Prints **no numeral** — a count is itself information the reader is not scoped to (UX-06). |

Established by Story 1.2 (work item 493).

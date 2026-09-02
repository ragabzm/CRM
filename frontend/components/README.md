# Component layers

Four layers, separated in the file tree and enforced by lint. Established by
Story 1.2 (work item 493).

| Layer | Folder     | What lives here                                                  |
| ----- | ---------- | ---------------------------------------------------------------- |
| **A** | `ui/`      | Restyled shadcn/Radix primitives. Button, input, select, dialog… |
| **B** | `domain/`  | Shared domain components. DataTable, RowActions, EmptyState…     |
| **C** | `screens/` | Screen compositions. Assembled from Layer B.                     |
| **D** | `shell/`   | Layout shell. AppShell, Sidebar, TopBar.                         |

## The rule that matters

**A screen may not import a Layer-A primitive directly.** Screens compose Layer
B; Layer B reaches Layer A. Enforced by `no-restricted-imports` in
`eslint.config.mjs`.

The reason is the whole point of this story: a screen assembled from repeated
raw primitives is how six epics end up with six different badges. If a screen
needs a primitive directly, that is the signal a domain component is missing.

## The other two rules

- **No primitives, no literals.** Components reference semantic tokens
  (`bg-surface-raised`), never primitive palette names (`bg-n-800`) or hex.
  Enforced by ESLint and by `scripts/check-tokens.mjs`.
- **No physical direction utilities.** `ms-`/`me-`/`ps-`/`pe-`/`start-`/`end-`/
  `text-start`/`text-end` only. `ml-`, `mr-`, `text-left`… are lint errors.
  This is the single cheapest guarantee that Arabic RTL actually holds.

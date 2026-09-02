# Layer C — screen compositions

A screen is assembled from Layer B. It decides _what_ appears and in what order;
it does not decide what a table or an empty state looks like.

## Contract

- May import from `@/components/domain/*` and `@/components/shell/*`.
- May **not** import from `@/components/ui/*` — enforced by
  `no-restricted-imports` in `eslint.config.mjs`. A screen built from repeated
  Layer-A primitives fails lint, not review.
- Same token and direction rules as every other layer.

Empty in Story 1.2: this story builds the floor, not the screens.

Established by Story 1.2 (work item 493).

# Layer A — restyled primitives

Behaviour and ARIA come from Radix. **Do not reimplement focus traps, roving
tabindex or dismiss behaviour** — that is what Radix is here for.

What this layer adds is exactly one thing: the tokens. Every primitive is
restyled once, here, so no screen ever restyles one again.

## Contract

- May import from: `@radix-ui/*`, `clsx`, `tailwind-merge`,
  `class-variance-authority`, `lucide-react`, `@/lib/cn`.
- May **not** import from `@/components/domain/**`, `@/components/screens/**`,
  `@/components/shell/**`, or `@/lib/api/**`.
- No hex, rgb, or primitive palette names. Semantic tokens only.
- No physical direction utilities.
- Every variant that carries meaning pairs its hue with a glyph, weight or
  shape, so it survives greyscale (UX-03, NFR-13).

Established by Story 1.2 (work item 493).

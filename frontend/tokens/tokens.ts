/**
 * The semantic token vocabulary, as types and runtime constants.
 *
 * This file deliberately contains NO colour, spacing or shadow values — only
 * the CSS variable *names* declared in tokens.css. Duplicating values here
 * would create a second source of truth that drifts silently the first time
 * someone retunes a colour.
 *
 * Use it where a class name has to be computed at runtime and TypeScript should
 * still catch a typo:
 *
 *   const bg = semanticVar("surface-raised");   // "var(--surface-raised)"
 */

/** Surfaces a component may paint on. */
export const SURFACE_TOKENS = [
  "surface-base",
  "surface-subtle",
  "surface-app",
  "surface-raised",
  "surface-sunken",
  "surface-hover",
  "surface-active",
  "surface-inverse",
  "scrim",
] as const;

/** Border colours. */
export const BORDER_TOKENS = [
  "border-subtle",
  "border-default",
  "border-strong",
  "border-inverse",
] as const;

/** Text/icon colours. */
export const FOREGROUND_TOKENS = [
  "fg-default",
  "fg-muted",
  "fg-subtle",
  "fg-placeholder",
  "fg-inverse",
] as const;

/** Iris. Product chrome only — never a data colour. */
export const ACCENT_TOKENS = [
  "accent-default",
  "accent-hover",
  "accent-active",
  "accent-fg",
  "accent-text",
  "accent-subtle",
  "accent-subtle-hover",
  "accent-border",
] as const;

/** Focus indication. */
export const FOCUS_TOKENS = [
  "focus-ring-color",
  "focus-ring",
  "focus-ring-width",
  "focus-ring-offset",
] as const;

/** Meaningful states. Each is paired with a glyph or weight at the component
 *  level so the meaning survives greyscale (UX-03, NFR-13). */
export const STATE_TOKENS = [
  "state-success",
  "state-success-bg",
  "state-success-border",
  "state-warning",
  "state-warning-bg",
  "state-warning-border",
  "state-danger",
  "state-danger-bg",
  "state-danger-border",
  "state-info",
  "state-info-bg",
  "state-info-border",
] as const;

/** Elevation. */
export const ELEVATION_TOKENS = [
  "elevation-none",
  "elevation-menu",
  "elevation-dialog",
  "elevation-palette",
] as const;

/**
 * The three responsive bands, in pixels.
 *
 * Each band is a posture, not a screen size: a thumb, a finger, a pointer. That
 * is why there are three and not five — a fourth breakpoint is a layout designed
 * for nobody.
 *
 * Mirrors `--breakpoint-*` in tokens.css. Values come from board R-0 of the
 * mockup at .squad/stories/inti/495/attachments/screen-responsive.html, whose
 * device table puts a half-screen laptop (1024–1180px) in the desktop band.
 *
 * `design-system/no-adhoc-breakpoint` makes any other breakpoint a lint error.
 */
export const BANDS = {
  /** 0 – 767px. One pane. A thumb. */
  mobile: 0,
  /** 768 – 1023px. One pane plus a drawer. A finger. */
  tablet: 768,
  /** 1024px and up. Two and three panes. A pointer and a keyboard. */
  desktop: 1024,
} as const;

export type Band = keyof typeof BANDS;

/** Band names in ascending order, for tests and docs that iterate them. */
export const BAND_NAMES = ["mobile", "tablet", "desktop"] as const;

/** Fixed measures. */
export const MEASURE_TOKENS = ["row-height", "rail-width", "motion-duration"] as const;

/** Focus indication. Outline-based, so it survives Windows High Contrast Mode. */
export const FOCUS_RING_TOKENS = [
  "focus-ring",
  "focus-ring-color",
  "focus-ring-width",
  "focus-ring-offset",
] as const;

/** Every semantic token, in one list. */
export const SEMANTIC_TOKENS = [
  ...SURFACE_TOKENS,
  ...BORDER_TOKENS,
  ...FOREGROUND_TOKENS,
  ...ACCENT_TOKENS,
  ...FOCUS_TOKENS,
  ...STATE_TOKENS,
  ...ELEVATION_TOKENS,
  ...MEASURE_TOKENS,
] as const;

export type SemanticToken = (typeof SEMANTIC_TOKENS)[number];

/** `semanticVar("fg-muted")` -> `"var(--fg-muted)"`. */
export function semanticVar(token: SemanticToken): string {
  return `var(--${token})`;
}

/**
 * Primitive scales exist in tokens.css so Tailwind can generate utilities from
 * them. They are listed here ONLY so the lint rule and check-tokens.mjs share
 * one definition of "this is a primitive" — never import them to style with.
 *
 * @internal
 */
export const PRIMITIVE_PREFIXES = [
  "n-",
  "status-",
  "sla-",
  "priority-",
  "channel-",
  "cat-",
  "ord-",
  "chart-",
  "ai-",
] as const;

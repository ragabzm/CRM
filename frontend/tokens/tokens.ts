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
export const FOCUS_TOKENS = ["focus-ring-color", "focus-ring"] as const;

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

/** Fixed measures. */
export const MEASURE_TOKENS = ["row-height", "rail-width", "motion-duration"] as const;

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

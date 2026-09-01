/**
 * Locale and writing direction.
 *
 * Story 1.2 establishes the plumbing and the contract; Story 1.4 wires the
 * runtime selector. Everything that needs to know the direction reads it from
 * here, so that later change is one function rather than a search across the
 * codebase.
 */

export const LOCALES = ["en", "ar"] as const;

export type Locale = (typeof LOCALES)[number];

export type Direction = "ltr" | "rtl";

export const DEFAULT_LOCALE: Locale = "en";

/** The one place that maps a locale to a writing direction. */
export function directionFor(locale: Locale): Direction {
  return locale === "ar" ? "rtl" : "ltr";
}

export function isLocale(value: string | undefined | null): value is Locale {
  return value === "en" || value === "ar";
}

/**
 * Resolves the active locale for a request.
 *
 * TODO(Story 1.4): read the cookie/header the locale selector writes. Until
 * then this is a single honest default rather than a half-built negotiator —
 * the callers and the types are already correct, so 1.4 changes only the body.
 */
export function resolveLocale(): Locale {
  return DEFAULT_LOCALE;
}

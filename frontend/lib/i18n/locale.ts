/**
 * Locale and writing direction.
 *
 * Everything that needs to know the direction reads it from here, so a change
 * of policy is one function rather than a search across the codebase.
 *
 * This module is deliberately isomorphic. `resolveLocale()` needs `next/headers`,
 * which is server-only, so it imports it *dynamically inside the function* — a
 * top-level import would poison every client bundle that only wanted
 * `directionFor` or `isLocale` and would fail the build.
 */

export const LOCALES = ["en", "ar"] as const;

export type Locale = (typeof LOCALES)[number];

export type Direction = "ltr" | "rtl";

export const DEFAULT_LOCALE: Locale = "en";

/** Where the per-user language preference is persisted. */
export const LOCALE_COOKIE = "ragab-locale";

/** One year. The preference is a setting, not a session. */
export const LOCALE_COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

/**
 * BCP-47 tags handed to every Intl constructor.
 *
 * Arabic is pinned to `ar-u-ca-gregory-nu-latn` and never bare `ar`, `ar-SA` or
 * `ar-EG`: those resolve to the Hijri calendar and Eastern Arabic digits
 * (٠١٢٣) depending on the platform's ICU build. The product shows Gregorian
 * dates and Western digits in both locales, so the tag says so explicitly
 * rather than depending on whatever ICU the runtime happens to ship.
 */
export const INTL_TAG: Record<Locale, string> = {
  en: "en-US",
  ar: "ar-u-ca-gregory-nu-latn",
};

/** The one place that maps a locale to a writing direction. */
export function directionFor(locale: Locale): Direction {
  return locale === "ar" ? "rtl" : "ltr";
}

export function isLocale(value: string | undefined | null): value is Locale {
  return value === "en" || value === "ar";
}

/**
 * Picks the best supported locale from an Accept-Language header.
 *
 * Honours quality values, so `en;q=0.4,ar;q=0.9` chooses Arabic even though
 * English appears first. Matching is on the primary subtag, so `ar-SA` and
 * `ar-EG` both select our single Arabic catalogue.
 */
export function negotiateFromAcceptLanguage(header: string | null | undefined): Locale | null {
  if (!header) return null;

  const ranked = header
    .split(",")
    .map((part) => {
      const [tag, ...params] = part.trim().split(";");
      const q = params
        .map((param) => /^\s*q=([0-9.]+)\s*$/i.exec(param)?.[1])
        .find((value) => value !== undefined);

      return {
        primary: (tag ?? "").trim().toLowerCase().split("-")[0] ?? "",
        quality: q === undefined ? 1 : Number.parseFloat(q),
      };
    })
    .filter((entry) => entry.primary !== "" && Number.isFinite(entry.quality) && entry.quality > 0)
    .sort((a, b) => b.quality - a.quality);

  for (const entry of ranked) {
    if (isLocale(entry.primary)) return entry.primary;
  }

  return null;
}

/**
 * Resolves the active locale for a request.
 *
 * Order: the persisted cookie, then the browser's Accept-Language, then English.
 * The cookie wins because it is an explicit choice the user made in this
 * product; the header is only a hint about their browser.
 *
 * Server-only — see the note at the top of this file about the dynamic import.
 */
export async function resolveLocale(): Promise<Locale> {
  const { cookies, headers } = await import("next/headers");

  const cookieValue = (await cookies()).get(LOCALE_COOKIE)?.value;
  if (isLocale(cookieValue)) return cookieValue;

  const negotiated = negotiateFromAcceptLanguage((await headers()).get("accept-language"));
  if (negotiated) return negotiated;

  return DEFAULT_LOCALE;
}

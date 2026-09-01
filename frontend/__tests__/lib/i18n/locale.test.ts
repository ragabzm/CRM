import { beforeEach, describe, expect, it, vi } from "vitest";

const cookieStore = { value: undefined as string | undefined };
const headerStore = { acceptLanguage: null as string | null };

vi.mock("next/headers", () => ({
  cookies: async () => ({
    get: (name: string) =>
      name === "ragab-locale" && cookieStore.value !== undefined
        ? { name, value: cookieStore.value }
        : undefined,
  }),
  headers: async () => ({
    get: (name: string) =>
      name.toLowerCase() === "accept-language" ? headerStore.acceptLanguage : null,
  }),
}));

import {
  DEFAULT_LOCALE,
  LOCALES,
  directionFor,
  isLocale,
  negotiateFromAcceptLanguage,
  resolveLocale,
} from "@/lib/i18n/locale";

beforeEach(() => {
  cookieStore.value = undefined;
  headerStore.acceptLanguage = null;
});

describe("direction", () => {
  it("maps Arabic to rtl and English to ltr", () => {
    expect(directionFor("ar")).toBe("rtl");
    expect(directionFor("en")).toBe("ltr");
  });

  it("covers every supported locale", () => {
    for (const locale of LOCALES) {
      expect(["ltr", "rtl"]).toContain(directionFor(locale));
    }
  });
});

describe("isLocale", () => {
  it.each(["en", "ar"])("accepts %s", (value) => {
    expect(isLocale(value)).toBe(true);
  });

  it.each(["fr", "EN", "", "ar-SA", undefined, null])("rejects %s", (value) => {
    expect(isLocale(value as string | undefined | null)).toBe(false);
  });
});

describe("Accept-Language negotiation", () => {
  it("picks Arabic when it leads", () => {
    expect(negotiateFromAcceptLanguage("ar,en;q=0.9")).toBe("ar");
  });

  it("honours quality values over source order", () => {
    // English appears first but Arabic is preferred — order alone is not enough.
    expect(negotiateFromAcceptLanguage("en;q=0.4,ar;q=0.9")).toBe("ar");
  });

  it("matches on the primary subtag, so regional Arabic still resolves", () => {
    expect(negotiateFromAcceptLanguage("ar-SA,en;q=0.5")).toBe("ar");
    expect(negotiateFromAcceptLanguage("en-GB")).toBe("en");
  });

  it("ignores languages we do not support", () => {
    expect(negotiateFromAcceptLanguage("fr-FR,de;q=0.8")).toBeNull();
  });

  it("ignores a zero-quality entry", () => {
    expect(negotiateFromAcceptLanguage("ar;q=0,en;q=0.5")).toBe("en");
  });

  it("returns null for an empty or missing header", () => {
    expect(negotiateFromAcceptLanguage("")).toBeNull();
    expect(negotiateFromAcceptLanguage(null)).toBeNull();
  });
});

describe("resolveLocale", () => {
  it("uses the persisted cookie when it is valid", async () => {
    cookieStore.value = "ar";
    headerStore.acceptLanguage = "en";

    // The cookie is an explicit choice made in this product; the header is only
    // a hint about the browser. The choice wins.
    await expect(resolveLocale()).resolves.toBe("ar");
  });

  it("falls through to the header when the cookie is invalid", async () => {
    cookieStore.value = "fr";
    headerStore.acceptLanguage = "ar,en;q=0.9";

    await expect(resolveLocale()).resolves.toBe("ar");
  });

  it("falls back to English when nothing is set", async () => {
    await expect(resolveLocale()).resolves.toBe(DEFAULT_LOCALE);
    await expect(resolveLocale()).resolves.toBe("en");
  });

  it("falls back to English when the header names no supported language", async () => {
    headerStore.acceptLanguage = "fr-FR,de;q=0.8";

    await expect(resolveLocale()).resolves.toBe("en");
  });
});

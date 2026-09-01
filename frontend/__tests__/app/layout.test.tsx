import { describe, expect, it, vi } from "vitest";

/*
 * next/font/local is a build-time transform: outside `next build` it returns
 * nothing usable, so importing app/layout.tsx directly would explode before any
 * assertion ran. Mocking the font module keeps the test about the shell's
 * direction contract, which is what it is here to check.
 */
vi.mock("@/app/fonts", () => ({
  plexSans: { variable: "--font-plex-sans" },
  plexSansArabic: { variable: "--font-plex-sans-arabic" },
}));

import { DEFAULT_LOCALE, LOCALES, directionFor, isLocale, resolveLocale } from "@/lib/i18n/locale";

/**
 * The bilingual shell.
 *
 * RootLayout renders <html>, which React Testing Library cannot mount into a
 * container (it would nest a document inside a div). The direction contract is
 * therefore asserted at its source — the locale module RootLayout reads — plus
 * a check that the layout actually binds both attributes.
 */
describe("writing direction", () => {
  it("maps Arabic to rtl and English to ltr", () => {
    expect(directionFor("ar")).toBe("rtl");
    expect(directionFor("en")).toBe("ltr");
  });

  it("covers every supported locale", () => {
    for (const locale of LOCALES) {
      expect(["ltr", "rtl"]).toContain(directionFor(locale));
    }
  });

  it("recognises supported locales and rejects others", () => {
    expect(isLocale("en")).toBe(true);
    expect(isLocale("ar")).toBe(true);
    expect(isLocale("fr")).toBe(false);
    expect(isLocale(undefined)).toBe(false);
  });

  it("defaults to English until the Story 1.4 selector lands", () => {
    expect(resolveLocale()).toBe(DEFAULT_LOCALE);
    expect(DEFAULT_LOCALE).toBe("en");
  });
});

describe("RootLayout binds dir and lang from the locale", () => {
  it("renders <html> with both attributes bound to the resolved locale", async () => {
    const { default: RootLayout } = await import("@/app/layout");

    const tree = RootLayout({ children: null }) as React.ReactElement<{
      lang: string;
      dir: string;
      className: string;
    }>;

    expect(tree.type).toBe("html");
    expect(tree.props.lang).toBe(resolveLocale());
    expect(tree.props.dir).toBe(directionFor(resolveLocale()));
  });

  it("applies both self-hosted font variables, so Arabic has a matched face", async () => {
    const { default: RootLayout } = await import("@/app/layout");

    const tree = RootLayout({ children: null }) as React.ReactElement<{ className: string }>;

    // next/font/local is mocked away in tests; assert both variables are wired.
    expect(tree.props.className.split(" ").filter(Boolean).length).toBe(2);
  });

  it("registers no external font loader", async () => {
    const { readFileSync } = await import("node:fs");
    const layout = readFileSync("app/layout.tsx", "utf8");
    const fonts = readFileSync("app/fonts.ts", "utf8");
    const config = readFileSync("next.config.ts", "utf8");

    // The intake forbids an external font CDN in the request path. `shadcn init`
    // adds a next/font/google loader here, so this asserts it stays removed.
    for (const source of [layout, fonts, config]) {
      expect(source).not.toMatch(/from\s+["']next\/font\/google["']/);
    }
  });
});

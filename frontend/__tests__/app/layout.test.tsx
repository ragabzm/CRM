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

/*
 * next/headers only exists inside a request. resolveLocale() reads it, so the
 * cookie/header pair is stubbed per test to drive the negotiation.
 */
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

import { INTL_TAG, directionFor } from "@/lib/i18n/locale";

interface HtmlProps {
  lang: string;
  dir: string;
  className: string;
  children: React.ReactElement<{ children: React.ReactElement<{ locale: string }> }>;
}

async function renderLayout() {
  const { default: RootLayout } = await import("@/app/layout");
  return (await RootLayout({ children: null })) as React.ReactElement<HtmlProps>;
}

describe("RootLayout is the one place direction is decided", () => {
  it("renders <html> with dir and lang bound to the resolved locale", async () => {
    cookieStore.value = undefined;
    headerStore.acceptLanguage = null;

    const tree = await renderLayout();

    expect(tree.type).toBe("html");
    expect(tree.props.lang).toBe("en");
    expect(tree.props.dir).toBe("ltr");
  });

  it("flips to rtl and lang=ar when the persisted preference is Arabic", async () => {
    cookieStore.value = "ar";

    const tree = await renderLayout();

    expect(tree.props.lang).toBe("ar");
    expect(tree.props.dir).toBe("rtl");
    expect(tree.props.dir).toBe(directionFor("ar"));
  });

  it("keeps lang as the bare BCP-47 base tag, not the Intl extension", async () => {
    cookieStore.value = "ar";

    const tree = await renderLayout();

    // Assistive technology expects "ar"; the -u- extension goes to Intl only.
    expect(tree.props.lang).toBe("ar");
    expect(tree.props.lang).not.toContain("-u-");
  });

  it("hands next-intl the pinned Intl tag, so Arabic gets Gregorian and Western digits", async () => {
    cookieStore.value = "ar";

    const tree = await renderLayout();
    const body = tree.props.children;
    const provider = body.props.children;

    expect(provider.props.locale).toBe(INTL_TAG.ar);
    expect(provider.props.locale).toBe("ar-u-ca-gregory-nu-latn");
  });

  it("applies both self-hosted font variables, so Arabic has a matched face", async () => {
    cookieStore.value = undefined;

    const tree = await renderLayout();

    expect(tree.props.className.split(" ").filter(Boolean)).toHaveLength(2);
  });

  it("registers no external font loader", async () => {
    const { readFileSync } = await import("node:fs");

    // The intake forbids an external font CDN in the request path. `shadcn init`
    // adds a next/font/google loader here, so this asserts it stays removed.
    for (const file of ["app/layout.tsx", "app/fonts.ts", "next.config.ts"]) {
      expect(readFileSync(file, "utf8")).not.toMatch(/from\s+["']next\/font\/google["']/);
    }
  });
});
